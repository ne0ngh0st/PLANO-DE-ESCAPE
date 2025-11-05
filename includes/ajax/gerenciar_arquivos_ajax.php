<?php
// Garantir que não há output antes do JSON
ob_start();

session_start();

// Limpar qualquer output anterior e enviar header
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Desabilitar exibição de erros (apenas para produção)
// error_reporting(0);
// ini_set('display_errors', 0);

// Tratamento de erros global
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit();
});

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

$usuario = $_SESSION['usuario'];

if (strtolower($usuario['perfil']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores.']);
    exit();
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

// Função auxiliar para formatar bytes (MOVER PARA O TOPO)
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Função para obter pasta .XLSX (padrão ou customizada)
function getPastaXLSX() {
    // Verificar se há pasta customizada configurada
    if (isset($_SESSION['xlsx_pasta_customizada']) && !empty($_SESSION['xlsx_pasta_customizada'])) {
        return $_SESSION['xlsx_pasta_customizada'];
    }
    // Pasta padrão
    return __DIR__ . '/../../.XLSX';
}

switch($acao) {
    
    // LISTAR ARQUIVOS na pasta .XLSX
    case 'listar':
        $pasta = getPastaXLSX();
        
        if (!file_exists($pasta) || !is_dir($pasta)) {
            echo json_encode([
                'success' => false,
                'message' => 'Pasta não encontrada: ' . $pasta,
                'pasta' => $pasta
            ]);
            exit();
        }
        
        $arquivos = [];
        $files = scandir($pasta);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep' || $file === 'README.md') {
                continue;
            }
            
            $filepath = $pasta . DIRECTORY_SEPARATOR . $file;
            
            if (is_file($filepath)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if ($ext === 'xlsx' || $ext === 'xls') {
                    $arquivos[] = [
                        'nome' => $file,
                        'tamanho' => filesize($filepath),
                        'tamanho_formatado' => formatBytes(filesize($filepath)),
                        'modificado' => filemtime($filepath),
                        'modificado_formatado' => date('d/m/Y H:i:s', filemtime($filepath)),
                        'extensao' => $ext
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'arquivos' => $arquivos,
            'pasta' => realpath($pasta) ?: $pasta,
            'total' => count($arquivos)
        ]);
        break;
    
    // UPLOAD DE ARQUIVOS
    case 'upload':
        $pasta = getPastaXLSX();
        
        // Criar pasta se não existir
        if (!file_exists($pasta)) {
            mkdir($pasta, 0777, true);
        }
        
        if (!isset($_FILES['arquivos'])) {
            echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);
            exit();
        }
        
        $files = $_FILES['arquivos'];
        $uploadados = [];
        $erros = [];
        
        // Processar múltiplos arquivos
        $total_files = count($files['name']);
        
        for ($i = 0; $i < $total_files; $i++) {
            $filename = $files['name'][$i];
            $tmp_name = $files['tmp_name'][$i];
            $error = $files['error'][$i];
            $size = $files['size'][$i];
            
            // Verificar extensão
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext !== 'xlsx' && $ext !== 'xls') {
                $erros[] = "$filename - Formato não permitido (apenas .xlsx ou .xls)";
                continue;
            }
            
            // Verificar se houve erro no upload
            if ($error !== UPLOAD_ERR_OK) {
                $erros[] = "$filename - Erro no upload (código: $error)";
                continue;
            }
            
            // Verificar tamanho (máximo 50MB)
            if ($size > 50 * 1024 * 1024) {
                $erros[] = "$filename - Arquivo muito grande (máximo 50MB)";
                continue;
            }
            
            // Mover arquivo
            $destino = $pasta . DIRECTORY_SEPARATOR . $filename;
            
            if (move_uploaded_file($tmp_name, $destino)) {
                $uploadados[] = [
                    'nome' => $filename,
                    'tamanho' => formatBytes($size),
                    'caminho' => $destino
                ];
            } else {
                $erros[] = "$filename - Falha ao mover arquivo";
            }
        }
        
        echo json_encode([
            'success' => count($uploadados) > 0,
            'message' => count($uploadados) . ' arquivo(s) enviado(s) com sucesso',
            'uploadados' => $uploadados,
            'erros' => $erros,
            'total_enviados' => count($uploadados),
            'total_erros' => count($erros)
        ]);
        break;
    
    // DELETAR ARQUIVO
    case 'deletar':
        $arquivo = $_POST['arquivo'] ?? '';
        
        if (empty($arquivo)) {
            echo json_encode(['success' => false, 'message' => 'Nome do arquivo não especificado']);
            exit();
        }
        
        // Sanitizar nome do arquivo (evitar path traversal)
        $arquivo = basename($arquivo);
        
        $pasta = getPastaXLSX();
        $filepath = $pasta . DIRECTORY_SEPARATOR . $arquivo;
        
        if (!file_exists($filepath)) {
            echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado']);
            exit();
        }
        
        if (unlink($filepath)) {
            echo json_encode([
                'success' => true,
                'message' => 'Arquivo deletado com sucesso',
                'arquivo' => $arquivo
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar arquivo']);
        }
        break;
    
    // CONFIGURAR PASTA CUSTOMIZADA
    case 'configurar_pasta':
        $caminho = $_POST['caminho'] ?? '';
        
        // Limpar caminho
        $caminho = trim($caminho);
        
        if (empty($caminho)) {
            // Remover configuração customizada (voltar ao padrão)
            unset($_SESSION['xlsx_pasta_customizada']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Configuração restaurada para o padrão',
                'pasta' => __DIR__ . '/../../.XLSX'
            ]);
            exit();
        }
        
        // Verificar se o caminho existe
        if (!file_exists($caminho)) {
            echo json_encode([
                'success' => false,
                'message' => 'Caminho não existe: ' . $caminho
            ]);
            exit();
        }
        
        if (!is_dir($caminho)) {
            echo json_encode([
                'success' => false,
                'message' => 'O caminho especificado não é uma pasta válida'
            ]);
            exit();
        }
        
        // Salvar na sessão
        $_SESSION['xlsx_pasta_customizada'] = $caminho;
        
        echo json_encode([
            'success' => true,
            'message' => 'Pasta customizada configurada com sucesso!',
            'pasta' => $caminho
        ]);
        break;
    
    // TESTAR PASTA
    case 'testar_pasta':
        $caminho = $_POST['caminho'] ?? '';
        $caminho = trim($caminho);
        
        if (empty($caminho)) {
            echo json_encode(['success' => false, 'message' => 'Caminho não especificado']);
            exit();
        }
        
        $info = [
            'existe' => file_exists($caminho),
            'eh_diretorio' => is_dir($caminho),
            'legivel' => is_readable($caminho),
            'gravavel' => is_writable($caminho),
            'caminho_absoluto' => realpath($caminho) ?: $caminho
        ];
        
        if (!$info['existe']) {
            echo json_encode([
                'success' => false,
                'message' => 'Pasta não existe',
                'info' => $info
            ]);
            exit();
        }
        
        if (!$info['eh_diretorio']) {
            echo json_encode([
                'success' => false,
                'message' => 'O caminho não é um diretório válido',
                'info' => $info
            ]);
            exit();
        }
        
        // Contar arquivos Excel
        $arquivos_excel = 0;
        $files = scandir($caminho);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'xlsx' || $ext === 'xls') {
                $arquivos_excel++;
            }
        }
        
        $info['arquivos_excel'] = $arquivos_excel;
        
        echo json_encode([
            'success' => true,
            'message' => 'Pasta válida! Encontrados ' . $arquivos_excel . ' arquivo(s) Excel.',
            'info' => $info
        ]);
        break;
    
    // OBTER PASTA ATUAL
    case 'obter_pasta':
        echo json_encode([
            'success' => true,
            'pasta' => getPastaXLSX(),
            'eh_customizada' => isset($_SESSION['xlsx_pasta_customizada'])
        ]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        break;
}