<?php
/**
 * Bloquear acesso de usuários com perfil "ecommerce"
 * 
 * Usuários com perfil "ecommerce" só podem acessar:
 * - gestao-ecommerce.php
 * - includes/auth/logout.php
 * 
 * Incluir este arquivo no início de páginas que devem ser bloqueadas para o perfil ecommerce
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario']) && strtolower(trim($_SESSION['usuario']['perfil'])) === 'ecommerce') {
    // Redirecionar para a página de e-commerce
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'gestao-ecommerce');
    exit;
}
?>


