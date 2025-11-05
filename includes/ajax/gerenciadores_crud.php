<?php
// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_clean();
}

// Definir header JSON primeiro
header('Content-Type: application/json');

// Capturar erros e exibir como JSON
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once '../config.php';
    
    // Verificar se a conexão PDO está disponível
    if (!isset($pdo)) {
        throw new Exception('Variável $pdo não está definida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro de configuração: ' . $e->getMessage(),
        'error_type' => 'config_error'
    ]);
    exit;
}

session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Debug: log de todas as informações recebidas
error_log("DEBUG - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("DEBUG - Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'não definido'));
error_log("DEBUG - POST data: " . print_r($_POST, true));
error_log("DEBUG - GET data: " . print_r($_GET, true));
error_log("DEBUG - Action detectada: '$action'");

try {
    switch ($action) {
        case 'list':
            listarGerenciadores();
            break;
        case 'create':
            criarGerenciador();
            break;
        case 'update':
            atualizarGerenciador();
            break;
        case 'delete':
            excluirGerenciador();
            break;
        default:
            error_log("DEBUG - Ação não especificada: '$action'");
            echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
    }
} catch (Exception $e) {
    error_log("Erro no CRUD de gerenciadores: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

function listarGerenciadores() {
    global $pdo;
    
    // Listar a partir da nova tabela GERENCIADOR (apenas ativos)
    $sql = "SELECT NOME FROM GERENCIADOR WHERE ATIVO = 1 ORDER BY NOME";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $gerenciadores = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'gerenciadores' => $gerenciadores
    ]);
}

function criarGerenciador() {
    global $pdo;
    
    $gerenciador = trim($_POST['gerenciador'] ?? '');
    $cod_vendedor = $_SESSION['usuario']['COD_VENDEDOR'] ?? $_SESSION['usuario']['cod_vendedor'] ?? '';
    
    if (empty($gerenciador)) {
        echo json_encode(['success' => false, 'message' => 'Nome do gerenciador é obrigatório']);
        return;
    }
    
    // Verificar se já existe na tabela GERENCIADOR
    $sql_check = "SELECT COUNT(*) FROM GERENCIADOR WHERE UPPER(NOME) = UPPER(?)";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$gerenciador]);
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador já existe']);
        return;
    }

    // Inserir novo gerenciador com COD_VENDEDOR_CRIACAO
    $sql_insert = "INSERT INTO GERENCIADOR (NOME, ATIVO, COD_VENDEDOR_CRIACAO) VALUES (?, 1, ?)";
    $stmt = $pdo->prepare($sql_insert);
    $result = $stmt->execute([$gerenciador, $cod_vendedor]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Gerenciador criado com sucesso',
            'id' => $pdo->lastInsertId()
        ]);
    } else {
        $error_info = $stmt->errorInfo();
        echo json_encode(['success' => false, 'message' => 'Erro ao criar gerenciador: ' . $error_info[2]]);
    }
}

function atualizarGerenciador() {
    global $pdo;
    
    // Debug: log dos dados recebidos
    error_log("DEBUG - Dados POST recebidos: " . print_r($_POST, true));
    
    $gerenciador_antigo = trim($_POST['gerenciador_antigo'] ?? '');
    $gerenciador_novo = trim($_POST['gerenciador_novo'] ?? '');
    
    error_log("DEBUG - gerenciador_antigo: '$gerenciador_antigo'");
    error_log("DEBUG - gerenciador_novo: '$gerenciador_novo'");
    
    if (empty($gerenciador_antigo) || empty($gerenciador_novo)) {
        error_log("DEBUG - Erro: Dados obrigatórios não informados - antigo: '$gerenciador_antigo', novo: '$gerenciador_novo'");
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não informados']);
        return;
    }
    
    if ($gerenciador_antigo === $gerenciador_novo) {
        echo json_encode(['success' => true, 'message' => 'Nenhuma alteração necessária']);
        return;
    }
    
    // Verificar se o novo nome já existe na tabela GERENCIADOR
    $sql_check = "SELECT COUNT(*) FROM GERENCIADOR WHERE UPPER(NOME) = UPPER(?) AND UPPER(NOME) != UPPER(?)";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$gerenciador_novo, $gerenciador_antigo]);
    
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Já existe um gerenciador com este nome']);
        return;
    }
    
    // Iniciar transação para garantir consistência
    $pdo->beginTransaction();
    
    try {
        // Atualizar nome do gerenciador na tabela GERENCIADOR
        $sql_update_gerenciador = "UPDATE GERENCIADOR SET NOME = ? WHERE UPPER(NOME) = UPPER(?)";
        $stmt_gerenciador = $pdo->prepare($sql_update_gerenciador);
        $result_gerenciador = $stmt_gerenciador->execute([$gerenciador_novo, $gerenciador_antigo]);
        
        if (!$result_gerenciador) {
            throw new Exception('Erro ao atualizar gerenciador na tabela GERENCIADOR');
        }
        
        // Atualizar nome do gerenciador em todas as licitações
        $sql_update_licitacoes = "UPDATE LICITACAO SET GERENCIADOR = ? WHERE TRIM(GERENCIADOR) = ?";
        $stmt_licitacoes = $pdo->prepare($sql_update_licitacoes);
        $result_licitacoes = $stmt_licitacoes->execute([$gerenciador_novo, $gerenciador_antigo]);
        
        if (!$result_licitacoes) {
            throw new Exception('Erro ao atualizar licitações');
        }
        
        // Contar quantas licitações foram atualizadas
        $licitacoes_afetadas = $stmt_licitacoes->rowCount();
        
        // Confirmar transação
        $pdo->commit();
        
        $mensagem = "Gerenciador atualizado com sucesso";
        if ($licitacoes_afetadas > 0) {
            $mensagem .= " ({$licitacoes_afetadas} licitação(ões) atualizada(s))";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensagem,
            'licitacoes_afetadas' => $licitacoes_afetadas
        ]);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        error_log("Erro na transação de atualização de gerenciador: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar gerenciador: ' . $e->getMessage()]);
    }
}

function excluirGerenciador() {
    global $pdo;
    
    $gerenciador = trim($_POST['gerenciador'] ?? '');
    
    if (empty($gerenciador)) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador não informado']);
        return;
    }
    
    // Verificar se existem licitações vigentes com este nome
    $sql_check = "SELECT COUNT(*) FROM LICITACAO WHERE UPPER(GERENCIADOR) = UPPER(?) AND UPPER(STATUS) = 'VIGENTE'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$gerenciador]);
    
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir gerenciador com licitações vigentes']);
        return;
    }
    
    // Inativar o gerenciador na tabela GERENCIADOR ao invés de alterar LICITACAO
    $sql_update = "UPDATE GERENCIADOR SET ATIVO = 0 WHERE UPPER(NOME) = UPPER(?)";
    $stmt = $pdo->prepare($sql_update);
    $result = $stmt->execute([$gerenciador]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Gerenciador excluído com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir gerenciador']);
    }
}
?>

