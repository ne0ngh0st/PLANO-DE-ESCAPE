<?php
/**
 * Configurações para o sistema de aprovação genérico
 */

// Domínio genérico para aprovação (sem expor URL do site de gestão)
define('APROVACAO_DOMAIN', 'https://gestao-comercial.autopel.com');

// Configurações de segurança
define('APROVACAO_MAX_ATTEMPTS', 5);
define('APROVACAO_TOKEN_LENGTH', 128);
define('APROVACAO_EXPIRATION_DAYS', 7);

// Configurações de e-mail
define('APROVACAO_EMAIL_FROM', 'noreply@autopel.com.br');
define('APROVACAO_EMAIL_REPLY_TO', 'vendas@autopel.com.br');

// Configurações de log
define('APROVACAO_LOG_RETENTION_DAYS', 90);

/**
 * Gerar link de aprovação genérico
 */
function gerarUrlAprovacao($token) {
    return APROVACAO_DOMAIN . '/aprovar.php?token=' . $token;
}

/**
 * Verificar se o domínio atual é o de aprovação
 */
function isAprovacaoDomain() {
    $currentDomain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    return strpos($currentDomain, 'gestao-comercial.autopel.com') !== false;
}

/**
 * Obter configurações de aprovação
 */
function getAprovacaoConfig() {
    return [
        'domain' => APROVACAO_DOMAIN,
        'max_attempts' => APROVACAO_MAX_ATTEMPTS,
        'token_length' => APROVACAO_TOKEN_LENGTH,
        'expiration_days' => APROVACAO_EXPIRATION_DAYS,
        'email_from' => APROVACAO_EMAIL_FROM,
        'email_reply_to' => APROVACAO_EMAIL_REPLY_TO,
        'log_retention_days' => APROVACAO_LOG_RETENTION_DAYS
    ];
}
?>
