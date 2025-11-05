<?php
session_start();

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario'])) {
        echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
        exit;
    }

    $usuario = $_SESSION['usuario'];
    $perfil = strtolower($usuario['perfil'] ?? '');
    if (!in_array($perfil, ['admin', 'diretor', 'supervisor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão para restaurar']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    $email = $_POST['email'] ?? '';
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }

    require_once __DIR__ . '/conexao.php';

    // Criar tabela de controle de restaurações de leads, caso não exista
    $pdo->exec("CREATE TABLE IF NOT EXISTS leads_restaurados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        usuario_id INT NULL,
        data_restauracao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Registrar restauração por email (sem usar DELETE)
    $stmt = $pdo->prepare("INSERT INTO leads_restaurados (email, usuario_id)
                           VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), data_restauracao = CURRENT_TIMESTAMP");
    $ok = $stmt->execute([$email, $usuario['id'] ?? null]);

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Lead restaurado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao registrar restauração']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
