# Black Flávio's 2026

Landing page destinada a `https://black.flavios.com.br/` pelo Git Version Control do cPanel 3ADS UX. O arquivo `.cpanel.yml` copia `.htaccess`, `index.html`, `lead.php` e `assets/` para o diretório público exclusivo do domínio. O `.htaccess` redireciona HTTP para HTTPS.

O formulário envia nome, telefone, categoria, consentimento opcional e parâmetros de campanha para `lead.php`. O servidor envia esses campos ao Apps Script vinculado à planilha de leads. O catálogo só é liberado depois que a planilha confirma a gravação. O endpoint do aplicativo web fica fora do repositório, em `/home2/hg3ads37/flavios-leads-private/sheet-webapp-url.txt`. O CSV no mesmo diretório permanece como cópia de segurança, com permissões 700 para a pasta e 600 para os arquivos privados. Não inserir endpoints, credenciais ou dados de clientes neste repositório.

A pergunta sobre condições iguais em todas as lojas foi retirada até a regra comercial ser validada pelo cliente.
