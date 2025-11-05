<?php
/**
 * Configuração da API do Bling ERP
 * 
 * Para obter as credenciais:
 * 1. Acesse: https://www.bling.com.br/
 * 2. Vá em Configurações > API > Aplicativos
 * 3. Crie um novo aplicativo ou use um existente
 * 4. Anote o Client ID e Client Secret
 * 5. Configure a URL de redirecionamento
 */

// Configurações da API do Bling
define('BLING_CLIENT_ID', 'ac34bd142d76ece868f87d3e2cf3cea85d0a08a7'); // Seu Client ID do Bling
define('BLING_CLIENT_SECRET', '50203b0285ed52ec34f1293bb28e6bdee5858e9d24a61b856aadfcdb7527'); // Seu Client Secret do Bling
define('BLING_REDIRECT_URI', 'http://localhost/Site/includes/api/bling_callback.php'); // URL de callback
define('BLING_API_BASE_URL', 'https://www.bling.com.br/Api/v3'); // URL base da API v3
define('BLING_AUTH_URL', 'https://www.bling.com.br/Api/v3/oauth/authorize');
define('BLING_TOKEN_URL', 'https://www.bling.com.br/Api/v3/oauth/token');

// Estado para CSRF (será usado na autenticação OAuth)
define('BLING_OAUTH_STATE', 'autopel_bling_' . md5('autopel_secret_key'));

// Arquivo para armazenar tokens (criar pasta 'cache' dentro de includes/config)
define('BLING_TOKEN_FILE', __DIR__ . '/cache/bling_tokens.json');

// Criar pasta cache se não existir
if (!file_exists(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0755, true);
}

// Configurações de cache
define('BLING_CACHE_DURATION', 3600); // 1 hora em segundos
define('BLING_CACHE_DIR', __DIR__ . '/cache/');
