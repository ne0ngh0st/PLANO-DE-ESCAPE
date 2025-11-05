<?php
// Incluir conexão com o banco de dados
// Usar @ para suprimir erros de include se o arquivo não existir
@require_once __DIR__ . '/conexao.php';

// Configurações do sistema
define('SITE_NAME', 'Autopel BI');
define('SITE_VERSION', '1.0');

// Detectar automaticamente se está em desenvolvimento local ou produção
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // Desenvolvimento local (agora sem /Site)
    define('SITE_URL', 'http://localhost');
} else {
    // Produção
    define('SITE_URL', 'https://gestao-comercial.autopel.com');
}

// Função para detectar o caminho base do site (versão segura)
function detectarCaminhoBase() {
    if (!isset($_SERVER)) {
        return '/';
    }
    
    try {
        $scriptPath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Se o script ou request contém /Site/, usar /Site/ (desenvolvimento local)
        if (!empty($scriptPath) && strpos($scriptPath, '/Site/') !== false) {
            return '/Site/';
        }
        if (!empty($requestUri) && strpos($requestUri, '/Site/') !== false) {
            return '/Site/';
        }
        
        // Se contém /Gestao/, usar /Gestao/ (ambiente de desenvolvimento alternativo)
        if (!empty($scriptPath) && strpos($scriptPath, '/Gestao/') !== false) {
            return '/Gestao/';
        }
        if (!empty($requestUri) && strpos($requestUri, '/Gestao/') !== false) {
            return '/Gestao/';
        }
        
        // Caso contrário, está na raiz (produção no servidor Kinghost)
        return '/';
    } catch (Exception $e) {
        // Em caso de erro, usar raiz como padrão (produção)
        error_log("Erro ao detectar caminho base: " . $e->getMessage());
        return '/';
    }
}

// Definir caminho base para uso em cookies (com fallback seguro)
if (!defined('SITE_BASE_PATH')) {
    $basePath = detectarCaminhoBase();
    define('SITE_BASE_PATH', $basePath);
}

// Configurações de sessão (só se a sessão ainda não foi iniciada)
if (session_status() === PHP_SESSION_NONE) {
    // Configurar o caminho do cookie de sessão (usar constante definida anteriormente)
    // Se SITE_BASE_PATH ainda não estiver definido, detectar agora
    if (!defined('SITE_BASE_PATH')) {
        $basePath = detectarCaminhoBase();
        define('SITE_BASE_PATH', $basePath);
    }
    $sessionPath = SITE_BASE_PATH;
    
    // Em produção (quando basePath é /), garantir que o cookie seja válido para todo o domínio
    // Se o caminho base for /, usar / para que o cookie funcione em todas as rotas
    if ($sessionPath === '/' || empty($sessionPath)) {
        $sessionPath = '/';
    }
    
    // Configurar opções de sessão antes de iniciar
    @ini_set('session.cookie_path', $sessionPath);
    @ini_set('session.cookie_httponly', 1);
    
    // Detectar se está usando HTTPS
    $isHttps = false;
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $isHttps = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $isHttps = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isHttps = true;
    }
    
    @ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    @ini_set('session.use_strict_mode', 1);
    @ini_set('session.cookie_samesite', 'Lax');
    
    // Iniciar a sessão (com supressão de erros para evitar 500)
    @session_start();
}

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro (desabilitar em produção)
// Detectar se está em produção baseado no host
$host = $_SERVER['HTTP_HOST'] ?? '';
$is_production = strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false;

if ($is_production) {
    // Produção: ocultar erros da tela, apenas logar
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    // Desenvolvimento: mostrar erros
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// Função para verificar se o usuário está logado
function verificarLogin() {
    if (!isset($_SESSION['usuario'])) {
        $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : detectarCaminhoBase();
        header("Location: {$basePath}");
        exit;
    }
}

// Função para verificar permissões
function verificarPermissao($perfil_necessario) {
    if (!isset($_SESSION['usuario']['perfil']) || $_SESSION['usuario']['perfil'] !== $perfil_necessario) {
        $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : detectarCaminhoBase();
        header("Location: {$basePath}home");
        exit;
    }
}

// Função para verificar múltiplas permissões
function verificarPermissoes($perfis_permitidos) {
    if (!isset($_SESSION['usuario']['perfil']) || !in_array($_SESSION['usuario']['perfil'], $perfis_permitidos)) {
        $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : detectarCaminhoBase();
        header("Location: {$basePath}home");
        exit;
    }
}

// Função para sanitizar dados
function sanitizar($dados) {
    return htmlspecialchars(trim($dados), ENT_QUOTES, 'UTF-8');
}

// Função para formatar data
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// Função para formatar data e hora
function formatarDataHora($data) {
    return date('d/m/Y H:i', strtotime($data));
}

// Função para sincronizar dados do usuário na sessão com o banco de dados
function sincronizarSessaoUsuario($pdo) {
    if (isset($_SESSION['usuario']['id'])) {
        try {
            $stmt = $pdo->prepare('SELECT NOME_COMPLETO, NOME_EXIBICAO, EMAIL, PERFIL, COD_VENDEDOR, COD_SUPER, SIDEBAR_COLOR, FOTO_PERFIL FROM USUARIOS WHERE ID = ?');
            $stmt->execute([$_SESSION['usuario']['id']]);
            $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_db) {
                // Atualizar a sessão com os dados mais recentes do banco
                $_SESSION['usuario']['nome'] = $usuario_db['NOME_EXIBICAO'] ?: $usuario_db['NOME_COMPLETO'];
                $_SESSION['usuario']['email'] = $usuario_db['EMAIL'];
                $_SESSION['usuario']['perfil'] = $usuario_db['PERFIL'];
                $_SESSION['usuario']['COD_VENDEDOR'] = $usuario_db['COD_VENDEDOR'];
                $_SESSION['usuario']['cod_vendedor'] = $usuario_db['COD_VENDEDOR']; // Minúsculas também
                $_SESSION['usuario']['COD_SUPER'] = $usuario_db['COD_SUPER'];
                $_SESSION['usuario']['cod_super'] = $usuario_db['COD_SUPER']; // Minúsculas também
                $_SESSION['usuario']['sidebar_color'] = $usuario_db['SIDEBAR_COLOR'] ?: '#1a237e';
                $_SESSION['usuario']['foto_perfil'] = $usuario_db['FOTO_PERFIL'];
            }
        } catch (Exception $e) {
            error_log("Erro ao sincronizar sessão: " . $e->getMessage());
        }
    }
}

// Função helper para gerar URLs corretas (compatível com localhost e produção)
function base_url($path = '') {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : detectarCaminhoBase();
    
    // Remover barra inicial do path se existir
    $path = ltrim($path, '/');
    
    // Se basePath termina com / e path não está vazio, retornar basePath + path
    if (!empty($path)) {
        return rtrim($basePath, '/') . '/' . $path;
    }
    
    // Se path está vazio, retornar apenas o basePath
    return rtrim($basePath, '/');
}

// Alias para compatibilidade (WEB_BASE)
if (!function_exists('get_web_base')) {
    function get_web_base() {
        return base_url();
    }
}