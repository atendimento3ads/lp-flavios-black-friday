<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');

function reply(int $status, bool $ok): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok]);
    exit;
}

function cleanField($value, int $maxLength): string
{
    if (!is_string($value) || strlen($value) > $maxLength) {
        return '';
    }
    $value = trim($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
    return is_string($value) ? trim($value) : '';
}

function csvCell(string $value): string
{
    // Impede que valores fornecidos pelo visitante virem fórmulas ao abrir o CSV.
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    reply(405, false);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== 'https://black.flavios.com.br') {
    reply(403, false);
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    reply(415, false);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 10000) {
    reply(413, false);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    reply(400, false);
}

// Campo invisível para reduzir cadastros automáticos.
if (!empty($data['website'])) {
    reply(200, true);
}

$name = cleanField($data['nome'] ?? null, 120);
$phone = preg_replace('/\D/', '', (string) ($data['telefone'] ?? ''));
$category = cleanField($data['categoria'] ?? '', 40);
$allowedCategories = ['', 'esportivo', 'feminino', 'infantil', 'masculino', 'acessorios', 'barra-mobile', 'cartao', 'cta-final', 'header', 'hero', 'oferta-1', 'oferta-2', 'oferta-3', 'oferta-4', 'oferta-5'];

if (strlen($name) < 2 || preg_match('/^(\d)\1+$/', $phone)
    || !in_array(strlen($phone), [10, 11], true)
    || $phone[0] === '0' || !in_array($category, $allowedCategories, true)) {
    reply(422, false);
}

$keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'];
$tracking = [];
foreach ($keys as $key) {
    $tracking[] = csvCell(cleanField($data[$key] ?? '', 255));
}

date_default_timezone_set('America/Sao_Paulo');
$dir = '/home2/hg3ads37/flavios-leads-private';
$path = $dir . '/leads.csv';
$webappPath = $dir . '/sheet-webapp-url.txt';
$webappUrl = is_file($webappPath) ? trim((string) file_get_contents($webappPath)) : '';
if (!preg_match('~^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$~', $webappUrl)
    || !function_exists('curl_init')) {
    reply(503, false);
}

// A planilha é o destino principal. Só confirmamos o cadastro após o Apps Script
// informar que gravou a linha; o CSV local permanece como cópia de segurança.
$payload = [
    'nome' => $name,
    'telefone' => $phone,
    'categoria' => $category,
    'optin_comunicacoes' => !empty($data['optin_comunicacoes']),
];
foreach ($keys as $key) {
    $payload[$key] = cleanField($data[$key] ?? '', 255);
}
$request = curl_init($webappUrl);
curl_setopt_array($request, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 18,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
]);
$sheetResponse = curl_exec($request);
$sheetCode = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
curl_close($request);
$sheetResult = is_string($sheetResponse) ? json_decode($sheetResponse, true) : null;
if ($sheetCode !== 200 || !is_array($sheetResult) || ($sheetResult['ok'] ?? false) !== true) {
    reply(502, false);
}

umask(0077);
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    reply(201, true);
}
chmod($dir, 0700);

$file = fopen($path, 'c+');
if ($file === false) {
    reply(201, true);
}
chmod($path, 0600);
if (!flock($file, LOCK_EX)) {
    fclose($file);
    reply(201, true);
}

fseek($file, 0, SEEK_END);
if (ftell($file) === 0) {
    fputcsv($file, array_merge(['enviado_em', 'nome', 'telefone', 'categoria', 'optin_comunicacoes'], $keys), ',', '"', '');
}
$row = array_merge(
    [date('Y-m-d H:i:s'), csvCell($name), $phone, $category, !empty($data['optin_comunicacoes']) ? 'sim' : 'nao'],
    $tracking
);
$saved = fputcsv($file, $row, ',', '"', '') !== false && fflush($file);
flock($file, LOCK_UN);
fclose($file);
reply(201, true);
