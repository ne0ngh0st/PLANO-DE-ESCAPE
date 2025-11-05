<?php
require_once 'config.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo 'Usuário não autenticado';
    exit;
}

// Verificar se os parâmetros foram informados
if (!isset($_GET['gerenciador']) || !isset($_GET['file'])) {
    http_response_code(400);
    echo 'Parâmetros inválidos';
    exit;
}

$gerenciador = trim($_GET['gerenciador']);
$file_name = trim($_GET['file']);

// Sanitizar nome do gerenciador
$gerenciador_sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $gerenciador);
$upload_dir = 'uploads/contratos/' . $gerenciador_sanitized . '/';
$file_path = $upload_dir . $file_name;

// Verificar se o arquivo existe
if (!file_exists($file_path)) {
    http_response_code(404);
    echo 'Arquivo não encontrado';
    exit;
}

// Verificar se é um arquivo (não diretório)
if (!is_file($file_path)) {
    http_response_code(400);
    echo 'Caminho inválido';
    exit;
}

// Obter informações do arquivo
$file_info = pathinfo($file_path);
$original_name = $file_name;

// Tentar extrair nome original do arquivo
$parts = explode('_', $file_name, 3);
if (count($parts) >= 3) {
    $original_name = $parts[2];
}

// Determinar tipo MIME
$mime_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$extension = strtolower($file_info['extension'] ?? '');
$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Configurar headers para download
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $original_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Limpar buffer de saída
ob_clean();
flush();

// Enviar arquivo
readfile($file_path);
exit;
?>










