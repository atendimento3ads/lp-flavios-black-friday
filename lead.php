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
umask(0077);
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    reply(500, false);
}
chmod($dir, 0700);

$file = fopen($path, 'c+');
if ($file === false) {
    reply(500, false);
}
chmod($path, 0600);
if (!flock($file, LOCK_EX)) {
    fclose($file);
    reply(500, false);
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
reply($saved ? 201 : 500, $saved);
