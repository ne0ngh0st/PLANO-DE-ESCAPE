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
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verifica existência
    $stmtCheck = $pdo->prepare("SELECT id FROM sugestoes WHERE id = ?");
    $stmtCheck->execute([$id]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Sugestão não encontrada']);
        exit;
    }

    // Excluir
    $stmt = $pdo->prepare("DELETE FROM sugestoes WHERE id = ?");
    $ok = $stmt->execute([$id]);

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Sugestão excluída']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao excluir']);
    }
} catch (PDOException $e) {
    error_log('Erro ao excluir sugestão: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

?>


