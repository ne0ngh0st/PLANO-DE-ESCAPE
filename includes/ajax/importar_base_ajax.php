<?php
// Garantir que não há output antes do JSON
ob_start();

session_start();

// Limpar qualquer output anterior e enviar header
ob_clean();
header('Content-Type: application/json; charset=utf-8');

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

require_once '../config/config.php';

// Verificar permissões de admin
$usuario = $_SESSION['usuario'];

if (strtolower($usuario['perfil']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem importar bases.']);
    exit();
}

// Verificar se recebeu o tipo de importação
if (!isset($_POST['tipo'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de importação não especificado']);
    exit();
}

$tipo = $_POST['tipo'];

// Caminho para o script Python (na raiz do site)
$script_path = __DIR__ . '/../../importar_bases_sql.py';

// Verificar se o script existe
if (!file_exists($script_path)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Script Python não encontrado no caminho: ' . $script_path,
        'path' => $script_path
    ]);
    exit();
}

// Obter pasta .XLSX (padrão ou customizada)
$xlsx_dir = isset($_SESSION['xlsx_pasta_customizada']) && !empty($_SESSION['xlsx_pasta_customizada']) 
    ? $_SESSION['xlsx_pasta_customizada'] 
    : __DIR__ . '/../../.XLSX';

// Verificar se a pasta .XLSX existe
if (!file_exists($xlsx_dir)) {
    echo json_encode([
        'success' => false,
        'message' => 'Pasta não encontrada! Caminho esperado: ' . $xlsx_dir,
        'logs' => ['⚠️ A pasta de planilhas não foi encontrada', '📂 Caminho: ' . $xlsx_dir, 'ℹ️ Configure uma pasta customizada ou use a pasta padrão .XLSX']
    ]);
    exit();
}

// Definir timeout maior para importações grandes
set_time_limit(600); // 10 minutos

// Verificar se Python está instalado
$python_cmd = 'python'; // Pode ser 'python3' em alguns sistemas

// Definir variável de ambiente para o Python saber qual pasta usar
putenv("XLSX_CUSTOM_PATH=" . $xlsx_dir);

// Construir comando com caminho absoluto
$comando = "\"$python_cmd\" \"$script_path\" \"$tipo\" 2>&1";

// Capturar output em tempo real
$descriptorspec = array(
   0 => array("pipe", "r"),   // stdin
   1 => array("pipe", "w"),   // stdout
   2 => array("pipe", "w")    // stderr
);

$process = proc_open($comando, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Ler output
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_value = proc_close($process);
    
    // Processar output
    $lines = explode("\n", $output);
    $log_lines = [];
    
    foreach ($lines as $line) {
        if (!empty(trim($line))) {
            $log_lines[] = trim($line);
        }
    }
    
    // Verificar se houve sucesso
    if ($return_value === 0 || !empty($log_lines)) {
        // Tentar decodificar JSON da última linha (se o script retornar JSON)
        $last_line = end($log_lines);
        $json_result = @json_decode($last_line, true);
        
        if ($json_result && isset($json_result['status'])) {
            $success = $json_result['status'] === 'success';
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Importação concluída com sucesso!' : ($json_result['message'] ?? 'Erro na importação'),
                'logs' => $log_lines,
                'resultado' => $json_result
            ]);
        } else {
            // Verificar se há mensagens de erro nos logs
            $has_errors = false;
            foreach ($log_lines as $line) {
                if (stripos($line, '❌') !== false || stripos($line, 'erro') !== false || stripos($line, 'error') !== false) {
                    $has_errors = true;
                    break;
                }
            }
            
            echo json_encode([
                'success' => !$has_errors && $return_value === 0,
                'message' => $has_errors ? 'Erro durante a importação' : 'Importação concluída',
                'logs' => $log_lines,
                'errors' => !empty($errors) ? $errors : null
            ]);
        }
    } else {
        // Houve erro - sem output
        $error_msg = 'Erro ao executar script Python';
        
        // Tentar identificar o erro
        if (!empty($errors)) {
            if (stripos($errors, 'python') !== false && stripos($errors, 'not found') !== false) {
                $error_msg = 'Python não está instalado ou não está no PATH do sistema';
            } else if (stripos($errors, 'modulenotfounderror') !== false || stripos($errors, 'no module named') !== false) {
                $error_msg = 'Dependências Python não instaladas. Execute: pip install -r requirements.txt';
            }
        }
        
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'logs' => array_merge(['❌ Falha na execução do script'], $log_lines),
            'errors' => $errors,
            'return_code' => $return_value,
            'command' => $comando
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Não foi possível iniciar o processo Python',
        'command' => $comando
    ]);
}