<?php
// Definir headers JSON antes de qualquer output
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Suprimir warnings que podem corromper o JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Incluir configurações do sistema
require_once __DIR__ . '/../config/config.php';

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

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
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
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir licitações']);
            exit;
        }
        
        // Verificar se a licitação existe
        $sql_check = "SELECT ID, GERENCIADOR, ORGAO, STATUS FROM LICITACAO WHERE ID = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$id]);
        $licitacao = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$licitacao) {
            echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
            exit;
        }
        
        // Verificar se já está excluída
        if ($licitacao['STATUS'] === 'Excluído') {
            echo json_encode(['success' => false, 'message' => 'Licitação já está excluída']);
            exit;
        }
        
        // Marcar como excluído (não deletar fisicamente)
        $sql_update = "UPDATE LICITACAO SET STATUS = 'Excluído' WHERE ID = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $result = $stmt_update->execute([$id]);
        
        if ($result) {
            // Log da exclusão
            error_log("Licitação excluída - ID: {$id}, Gerenciador: {$licitacao['GERENCIADOR']}, Órgão: {$licitacao['ORGAO']}, Usuário: {$usuario['nome']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Licitação excluída com sucesso',
                'id' => $id,
                'gerenciador' => $licitacao['GERENCIADOR'],
                'orgao' => $licitacao['ORGAO']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao executar exclusão']);
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao excluir licitação: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Erro geral ao excluir licitação: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
}
?>
