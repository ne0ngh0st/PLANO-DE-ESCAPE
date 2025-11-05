<?php
// Carregar configurações (inclui session_start se necessário)
require_once __DIR__ . '/../config/config.php';

// Se há um cookie de "manter logado", removê-lo
if (isset($_COOKIE['manter_logado'])) {
    $token = $_COOKIE['manter_logado'];
    
    if (isset($pdo)) {
        $stmt = $pdo->prepare('UPDATE login_tokens SET ativo = 0 WHERE token = ?');
        $stmt->execute([$token]);
    }
    
    // Remover o cookie (usar mesmo path que foi usado para criar)
    $cookiePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/Site/';
    setcookie('manter_logado', '', time() - 3600, $cookiePath);
}

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login (URL limpa)
$basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
header('Location: ' . $basePath);
exit;
?> 