<?php
/**
 * Endpoint para buscar dados de um lead manual específico
 */

require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$usuario = $_SESSION['usuario'];

// Verificar se é GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $lead_id = intval($_GET['lead_id'] ?? 0);
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do lead inválido']);
        exit;
    }
    
    // Buscar o lead
    $stmt = $pdo->prepare("
        SELECT lm.*, u.COD_VENDEDOR as cod_vendedor_usuario 
        FROM LEADS_MANUAIS lm 
        LEFT JOIN USUARIOS u ON u.EMAIL = ? 
        WHERE lm.id = ? AND lm.status = 'ativo'
    ");
    $stmt->execute([$usuario['email'], $lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo json_encode(['success' => false, 'message' => 'Lead não encontrado']);
        exit;
    }
    
    // Verificar permissões
    $pode_editar = false;
    $perfil = strtolower(trim($usuario['perfil']));
    
    if (in_array($perfil, ['admin', 'diretor'])) {
        $pode_editar = true;
    } elseif ($perfil === 'supervisor') {
        // Supervisor pode editar leads da sua equipe
        if ($lead['codigo_vendedor'] == $lead['cod_vendedor_usuario']) {
            $pode_editar = true;
        } else {
            // Verificar se o lead pertence à equipe do supervisor
            $stmt_equipe = $pdo->prepare("
                SELECT COUNT(*) FROM USUARIOS 
                WHERE COD_SUPER = ? AND COD_VENDEDOR = ? AND ATIVO = 1
            ");
            $stmt_equipe->execute([$lead['cod_vendedor_usuario'], $lead['codigo_vendedor']]);
            if ($stmt_equipe->fetchColumn() > 0) {
                $pode_editar = true;
            }
        }
    } elseif (in_array($perfil, ['vendedor', 'representante'])) {
        // Vendedor/representante só pode editar seus próprios leads
        if ($lead['codigo_vendedor'] == $lead['cod_vendedor_usuario']) {
            $pode_editar = true;
        }
    }
    
    if (!$pode_editar) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para visualizar este lead']);
        exit;
    }
    
    // Remover dados sensíveis e retornar
    unset($lead['cod_vendedor_usuario']);
    
    echo json_encode(['success' => true, 'data' => $lead]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar lead manual: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
} catch (Exception $e) {
    error_log("Erro geral ao buscar lead manual: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
}
?>
