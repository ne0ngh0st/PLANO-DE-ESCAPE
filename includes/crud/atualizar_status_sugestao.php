<?php
session_start();
require_once __DIR__ . '/conexao.php';

// Requer usuário logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Permitir apenas admin/diretor
$perfil = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
if (!in_array($perfil, ['admin', 'diretor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Método POST obrigatório
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Validar parâmetros
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$novo_status = strtolower(trim($_POST['status'] ?? ''));
$resposta_admin = trim($_POST['resposta_admin'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$status_validos = ['pendente', 'em_analise', 'aprovada', 'rejeitada', 'implementada'];
if (!in_array($novo_status, $status_validos, true)) {
    echo json_encode(['success' => false, 'message' => 'Status inválido']);
    exit;
}

try {
    // Verificar se a sugestão existe
    $stmtCheck = $pdo->prepare("SELECT id FROM sugestoes WHERE id = ?");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Sugestão não encontrada']);
        exit;
    }

    // Atualizar status (e opcionalmente resposta_admin)
    if ($resposta_admin !== '') {
        $sql = "UPDATE sugestoes 
                SET status = ?, resposta_admin = ?, admin_respondeu_id = ?, data_atualizacao = CURRENT_TIMESTAMP 
                WHERE id = ?";
        $params = [$novo_status, $resposta_admin, $_SESSION['usuario']['id'], $id];
    } else {
        $sql = "UPDATE sugestoes 
                SET status = ?, admin_respondeu_id = ?, data_atualizacao = CURRENT_TIMESTAMP 
                WHERE id = ?";
        $params = [$novo_status, $_SESSION['usuario']['id'], $id];
    }

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao atualizar status']);
    }
} catch (PDOException $e) {
    error_log('Erro ao atualizar status da sugestão: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

?>


