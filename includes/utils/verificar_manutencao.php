<?php
/**
 * Verificação de Modo Manutenção
 * 
 * Este arquivo verifica se o sistema está em modo manutenção
 * e permite bypass para admins e desenvolvedores.
 * 
 * Uso: Inclua este arquivo no início de qualquer página PHP
 * require_once __DIR__ . '/includes/utils/verificar_manutencao.php';
 */

// Verificar se o modo manutenção está ativo
$manutencao_flag = __DIR__ . '/../config/manutencao.flag';
$modo_manutencao = file_exists($manutencao_flag);

// Permitir acesso para admin e desenvolvedor
$dev_bypass = false;
$is_admin = false;

// Verificar se usuário está logado e é admin
if (isset($_SESSION['usuario']['perfil'])) {
    $perfil_usuario = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
    $is_admin = ($perfil_usuario === 'admin');
}

// Método 1: Cookie de desenvolvedor (válido por 24 horas)
if (isset($_COOKIE['dev_bypass']) && $_COOKIE['dev_bypass'] === 'autopel_dev_2024') {
    $dev_bypass = true;
}

// Método 2: Parâmetro na URL (?dev=true) - cria cookie
if (isset($_GET['dev']) && $_GET['dev'] === 'true') {
    $cookiePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/Site/';
    setcookie('dev_bypass', 'autopel_dev_2024', time() + (24 * 60 * 60), $cookiePath, '', false, false);
    $dev_bypass = true;
    // Redirecionar para remover o parâmetro da URL
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
    $current_url = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    header("Location: " . $current_url);
    exit;
}

// Método 3: IP específico (opcional - descomente e adicione seu IP)
// $seu_ip = '127.0.0.1'; // Localhost
// $ip_visitante = $_SERVER['REMOTE_ADDR'] ?? '';
// if ($ip_visitante === $seu_ip) {
//     $dev_bypass = true;
// }

// Se modo manutenção está ativo E não é admin E não é desenvolvedor, redirecionar
if ($modo_manutencao && !$is_admin && !$dev_bypass) {
    // Verificar se não está tentando acessar a própria página de manutenção ou arquivos estáticos
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_static = preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|json)$/i', $uri);
    $is_manutencao = strpos($uri, 'index_manutencao.php') !== false;
    $is_ajax = strpos($uri, 'ajax') !== false || strpos($uri, 'api') !== false;
    
    // Permitir arquivos estáticos e páginas de manutenção
    if (!$is_static && !$is_manutencao && !$is_ajax) {
        $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
        header('Location: ' . rtrim($basePath, '/') . '/index_manutencao.php');
        exit;
    }
}

