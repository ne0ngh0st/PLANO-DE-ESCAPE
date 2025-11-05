<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Incluir configurações do sistema
require_once '../config.php';

// Verificar se a sessão foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'delete_gerenciador') {
    $gerenciador = trim($_POST['gerenciador'] ?? '');
    
    if (empty($gerenciador)) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador não especificado']);
        exit;
    }
    
    try {
        // Verificar permissões para exclusão
        $usuario = $_SESSION['usuario'];
        $pode_excluir = false;
        
        if (isset($usuario['perfil'])) {
            $perfil = strtolower(trim($usuario['perfil']));
            $pode_excluir = in_array($perfil, ['admin', 'diretor']);
        }
        
        if (!$pode_excluir) {
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir gerenciadores']);
            exit;
        }
        
        // Verificar se o gerenciador existe e quantas licitações tem
        $sql_check = "SELECT COUNT(*) as total_licitacoes FROM LICITACAO WHERE TRIM(GERENCIADOR) = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$gerenciador]);
        $result_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
        $total_licitacoes = $result_check['total_licitacoes'];
        
        // Verificar se existe na tabela GERENCIADOR
        $sql_gerenciador_check = "SELECT COUNT(*) as existe FROM GERENCIADOR WHERE TRIM(NOME) = ? AND ATIVO = 1";
        $stmt_gerenciador_check = $pdo->prepare($sql_gerenciador_check);
        $stmt_gerenciador_check->execute([$gerenciador]);
        $result_gerenciador_check = $stmt_gerenciador_check->fetch(PDO::FETCH_ASSOC);
        $gerenciador_existe = $result_gerenciador_check['existe'] > 0;
        
        if ($total_licitacoes == 0 && !$gerenciador_existe) {
            echo json_encode(['success' => false, 'message' => 'Gerenciador não encontrado no sistema']);
            exit;
        }
        
        $mensagem_sucesso = '';
        
        // Marcar todas as licitações do gerenciador como excluídas (se existirem)
        if ($total_licitacoes > 0) {
            $sql_update_licitacoes = "UPDATE LICITACAO SET STATUS = 'Excluído' WHERE TRIM(GERENCIADOR) = ?";
            $stmt_update_licitacoes = $pdo->prepare($sql_update_licitacoes);
            $result_licitacoes = $stmt_update_licitacoes->execute([$gerenciador]);
            
            if (!$result_licitacoes) {
                echo json_encode(['success' => false, 'message' => 'Erro ao marcar licitações como excluídas']);
                exit;
            }
            
            $mensagem_sucesso = "{$total_licitacoes} licitação(ões) marcada(s) como excluída(s). ";
        }
        
        // Desativar o gerenciador na tabela GERENCIADOR (se existir)
        if ($gerenciador_existe) {
            $sql_update_gerenciador = "UPDATE GERENCIADOR SET ATIVO = 0 WHERE TRIM(NOME) = ? AND ATIVO = 1";
            $stmt_update_gerenciador = $pdo->prepare($sql_update_gerenciador);
            $result_gerenciador = $stmt_update_gerenciador->execute([$gerenciador]);
            
            if (!$result_gerenciador) {
                echo json_encode(['success' => false, 'message' => 'Erro ao desativar gerenciador']);
                exit;
            }
            
            $mensagem_sucesso .= "Gerenciador desativado.";
        }
        
        // Log da exclusão
        error_log("Gerenciador excluído - Nome: {$gerenciador}, Licitações afetadas: {$total_licitacoes}, Gerenciador desativado: " . ($gerenciador_existe ? 'Sim' : 'Não') . ", Usuário: {$usuario['nome']}");
        
        // Debug: verificar se as licitações foram realmente marcadas como excluídas
        $sql_debug = "SELECT COUNT(*) as excluidas FROM LICITACAO WHERE TRIM(GERENCIADOR) = ? AND STATUS = 'Excluído'";
        $stmt_debug = $pdo->prepare($sql_debug);
        $stmt_debug->execute([$gerenciador]);
        $debug_result = $stmt_debug->fetch(PDO::FETCH_ASSOC);
        error_log("DEBUG - Licitações marcadas como excluídas para '{$gerenciador}': {$debug_result['excluidas']}");
        
        echo json_encode([
            'success' => true,
            'message' => "Gerenciador '{$gerenciador}' excluído com sucesso. {$mensagem_sucesso}",
            'gerenciador' => $gerenciador,
            'total_licitacoes' => $total_licitacoes,
            'gerenciador_desativado' => $gerenciador_existe
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro ao excluir gerenciador: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Erro geral ao excluir gerenciador: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
}
?>
