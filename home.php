<?php
// Ativar tratamento de erros para debug em caso de problemas
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir erros na tela em produção
ini_set('log_errors', 1);

// Função para detectar o caminho base (compatível com desenvolvimento e produção)
function detectarBasePath() {
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Se o script ou URI contém /Site/, está em desenvolvimento local
    if (strpos($script_name, '/Site/') !== false || strpos($request_uri, '/Site/') !== false) {
        return '/Site/';
    }
    
    // Caso contrário, está na raiz (produção)
    return '/';
}

$base_path = detectarBasePath();

// Tentar múltiplos caminhos para o config.php (compatibilidade com diferentes servidores)
$config_paths = [
    __DIR__ . '/includes/config/config.php',
    dirname(__FILE__) . '/includes/config/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/includes/config/config.php',
    dirname($_SERVER['SCRIPT_FILENAME']) . '/includes/config/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        error_log("Config carregado de: $path");
        break;
    }
}

// Se não conseguiu carregar, mostrar erro amigável
if (!$config_loaded) {
    error_log("ERRO: Não foi possível carregar config.php. Caminhos testados: " . implode(", ", $config_paths));
    http_response_code(500);
    die("Erro de configuração. Por favor, contate o administrador.");
}

// Verificar modo manutenção
require_once __DIR__ . '/includes/utils/verificar_manutencao.php';

// Verificar se sessão foi iniciada corretamente
if (session_status() === PHP_SESSION_NONE) {
    error_log("Aviso: Sessão não foi iniciada. Tentando iniciar manualmente.");
    @session_start();
}

// Se não estiver logado, enviar para login/index
if (!isset($_SESSION['usuario'])) {
    error_log("Redirecionamento: Usuário não logado. Redirecionando para {$base_path}");
    header("Location: {$base_path}", true, 302);
    exit;
}

$perfil = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));

// Normalizar possíveis variações (com/sem acento)
if ($perfil === 'licitacao') {
    $perfil = 'licitação';
}

error_log("Redirecionamento home.php - Perfil detectado: $perfil - Base path: $base_path");

// Decidir home por perfil usando URLs limpas (com base path correto)
if ($perfil === 'licitação') {
    error_log("Redirecionando perfil licitação para {$base_path}contratos");
    header("Location: {$base_path}contratos", true, 302);
    exit;
}

if ($perfil === 'ecommerce') {
    error_log("Redirecionando perfil ecommerce para {$base_path}gestao-ecommerce");
    header("Location: {$base_path}gestao-ecommerce", true, 302);
    exit;
}

// Comercial: admin, diretor, supervisor, vendedor, representante, assistente (se aplicável)
// Usar base_url() se disponível, senão usar base_path
if (function_exists('base_url')) {
    $homeUrl = base_url('home-comercial');
} else {
    $homeUrl = rtrim($base_path, '/') . '/home-comercial';
}
error_log("Redirecionando perfil comercial para {$homeUrl}");
header("Location: {$homeUrl}", true, 302);
exit;
