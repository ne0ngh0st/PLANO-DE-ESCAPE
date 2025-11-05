<?php
// Definir header JSON antes de qualquer output
header('Content-Type: application/json');

// Suprimir warnings que podem corromper o JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o gerenciador foi informado
if (!isset($_GET['gerenciador']) || empty(trim($_GET['gerenciador']))) {
    echo json_encode(['success' => false, 'message' => 'Gerenciador não informado']);
    exit;
}

$gerenciador = trim($_GET['gerenciador']);
$gerenciador_sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $gerenciador);
$upload_dir = __DIR__ . '/../../uploads/contratos/' . $gerenciador_sanitized . '/';

$contratos = [];

// Verificar se o diretório existe
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($upload_dir . $file)) {
            $file_path = $upload_dir . $file;
            $file_info = pathinfo($file);
            
            // Extrair informações do nome do arquivo
            // Formato: timestamp_random_original_name
            $parts = explode('_', $file, 3);
            $upload_date = '';
            $original_name = $file;
            
            if (count($parts) >= 3) {
                $upload_date = $parts[0] . ' ' . str_replace('-', ':', $parts[1]);
                $original_name = $parts[2];
            }
            
            // Usar base_url para gerar URL de download correta
            $download_url = base_url('includes/download/download_contrato.php') . '?gerenciador=' . urlencode($gerenciador) . '&file=' . urlencode($file);
            
            $contratos[] = [
                'file_name' => $file,
                'original_name' => $original_name,
                'file_size' => filesize($file_path),
                'file_extension' => strtoupper($file_info['extension'] ?? ''),
                'upload_date' => $upload_date,
                'file_path' => $file_path,
                'download_url' => $download_url
            ];
        }
    }
    
    // Ordenar por data de upload (mais recente primeiro)
    usort($contratos, function($a, $b) {
        return strcmp($b['upload_date'], $a['upload_date']);
    });
}

echo json_encode([
    'success' => true,
    'gerenciador' => $gerenciador,
    'contratos' => $contratos,
    'total' => count($contratos)
]);
?>














