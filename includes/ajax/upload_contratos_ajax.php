<?php
require_once '../config.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se há arquivos enviados
if (!isset($_FILES['contratos']) || empty($_FILES['contratos']['name'][0])) {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo foi selecionado']);
    exit;
}

// Verificar se o gerenciador foi informado
if (!isset($_POST['gerenciador']) || empty(trim($_POST['gerenciador']))) {
    echo json_encode(['success' => false, 'message' => 'Gerenciador não informado']);
    exit;
}

$gerenciador = trim($_POST['gerenciador']);
$uploaded_files = [];
$errors = [];

// Criar diretório específico para o gerenciador
$gerenciador_sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $gerenciador);
$upload_dir = '../uploads/contratos/' . $gerenciador_sanitized . '/';

// Criar diretório se não existir
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Processar cada arquivo
$files = $_FILES['contratos'];
$file_count = count($files['name']);

for ($i = 0; $i < $file_count; $i++) {
    $file_name = $files['name'][$i];
    $file_tmp = $files['tmp_name'][$i];
    $file_size = $files['size'][$i];
    $file_error = $files['error'][$i];
    
    // Verificar se houve erro no upload
    if ($file_error !== UPLOAD_ERR_OK) {
        $errors[] = "Erro no upload do arquivo '$file_name': " . getUploadErrorMessage($file_error);
        continue;
    }
    
    // Verificar tamanho do arquivo (10MB máximo)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file_size > $max_size) {
        $errors[] = "Arquivo '$file_name' excede o tamanho máximo de 10MB";
        continue;
    }
    
    // Verificar extensão do arquivo
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $errors[] = "Arquivo '$file_name' tem extensão não permitida. Extensões aceitas: " . implode(', ', $allowed_extensions);
        continue;
    }
    
    // Gerar nome único para o arquivo
    $timestamp = date('Y-m-d_H-i-s');
    $random_string = substr(md5(uniqid()), 0, 8);
    $new_file_name = $timestamp . '_' . $random_string . '_' . $file_name;
    $file_path = $upload_dir . $new_file_name;
    
    // Mover arquivo para o diretório de upload
    if (move_uploaded_file($file_tmp, $file_path)) {
        $uploaded_files[] = [
            'original_name' => $file_name,
            'saved_name' => $new_file_name,
            'size' => $file_size,
            'path' => $file_path,
            'gerenciador' => $gerenciador,
            'upload_date' => date('Y-m-d H:i:s')
        ];
    } else {
        $errors[] = "Erro ao salvar o arquivo '$file_name'";
    }
}

// Preparar resposta
$response = [
    'success' => count($uploaded_files) > 0,
    'uploaded_files' => $uploaded_files,
    'errors' => $errors,
    'total_uploaded' => count($uploaded_files),
    'total_errors' => count($errors)
];

if (count($uploaded_files) > 0) {
    $response['message'] = count($uploaded_files) . ' arquivo(s) enviado(s) com sucesso';
    if (count($errors) > 0) {
        $response['message'] .= ' e ' . count($errors) . ' erro(s)';
    }
} else {
    $response['message'] = 'Nenhum arquivo foi enviado com sucesso';
}

echo json_encode($response);

// Função para obter mensagem de erro do upload
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Arquivo muito grande';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload incompleto';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo enviado';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Diretório temporário não encontrado';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Erro de escrita no disco';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload bloqueado por extensão';
        default:
            return 'Erro desconhecido';
    }
}
?>

