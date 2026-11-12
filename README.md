# Black Flávio's 2026

Landing page destinada a `https://black.flavios.com.br/` pelo Git Version Control do cPanel 3ADS UX. O arquivo `.cpanel.yml` copia `index.html`, `lead.php` e `assets/` para o diretório público exclusivo do domínio.

O formulário envia nome, telefone, categoria, consentimento opcional e parâmetros de campanha para `lead.php`. O catálogo só é liberado depois da resposta de sucesso do servidor. Os cadastros ficam em `/home2/hg3ads37/flavios-leads-private/leads.csv`, fora do diretório público, com permissões 700 para a pasta e 600 para o arquivo. O CSV é criado no primeiro envio válido. Não inserir credenciais ou dados de clientes neste repositório.

A pergunta sobre condições iguais em todas as lojas foi retirada até a regra comercial ser validada pelo cliente.
