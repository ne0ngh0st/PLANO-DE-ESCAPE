<?php
/**
 * Endpoint para excluir lead manual
 */

require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$usuario = $_SESSION['usuario'];

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se é AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
    exit;
}

try {
    $lead_id = intval($_POST['lead_id'] ?? 0);
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do lead inválido']);
        exit;
    }
    
    // Verificar se o lead existe e se o usuário tem permissão para excluí-lo
    $stmt_check = $pdo->prepare("
        SELECT lm.*, u.COD_VENDEDOR as cod_vendedor_usuario 
        FROM LEADS_MANUAIS lm 
        LEFT JOIN USUARIOS u ON u.EMAIL = ? 
        WHERE lm.id = ? AND lm.status = 'ativo'
    ");
    $stmt_check->execute([$usuario['email'], $lead_id]);
    $lead = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo json_encode(['success' => false, 'message' => 'Lead não encontrado']);
        exit;
    }
    
    // Verificar permissões
    $pode_excluir = false;
    $perfil = strtolower(trim($usuario['perfil']));
    
    if (in_array($perfil, ['admin', 'diretor'])) {
        $pode_excluir = true;
    } elseif ($perfil === 'supervisor') {
        // Supervisor pode excluir leads da sua equipe
        if ($lead['codigo_vendedor'] == $lead['cod_vendedor_usuario']) {
            $pode_excluir = true;
        } else {
            // Verificar se o lead pertence à equipe do supervisor
            $stmt_equipe = $pdo->prepare("
                SELECT COUNT(*) FROM USUARIOS 
                WHERE COD_SUPER = ? AND COD_VENDEDOR = ? AND ATIVO = 1
            ");
            $stmt_equipe->execute([$lead['cod_vendedor_usuario'], $lead['codigo_vendedor']]);
            if ($stmt_equipe->fetchColumn() > 0) {
                $pode_excluir = true;
            }
        }
    } elseif (in_array($perfil, ['vendedor', 'representante'])) {
        // Vendedor/representante só pode excluir seus próprios leads
        if ($lead['codigo_vendedor'] == $lead['cod_vendedor_usuario']) {
            $pode_excluir = true;
        }
    }
    
    if (!$pode_excluir) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir este lead']);
        exit;
    }
    
    // Marcar como excluído (soft delete)
    $stmt_update = $pdo->prepare("
        UPDATE LEADS_MANUAIS SET 
            status = 'excluido',
            data_ultima_atualizacao = NOW()
        WHERE id = ?
    ");
    
    $resultado = $stmt_update->execute([$lead_id]);
    
    if ($resultado) {
        echo json_encode(['success' => true, 'message' => 'Lead excluído com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir lead']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao excluir lead manual: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
} catch (Exception $e) {
    error_log("Erro geral ao excluir lead manual: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
}
?>
