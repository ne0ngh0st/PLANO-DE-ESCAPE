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
    if (!in_array($perfil, ['admin', 'supervisor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão para restaurar']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    $cnpj = $_POST['cnpj'] ?? '';
    $cnpj_limpo = preg_replace('/\D/', '', $cnpj);
    if (empty($cnpj_limpo)) {
        echo json_encode(['success' => false, 'message' => 'CNPJ inválido']);
        exit;
    }

    $raiz_cnpj = substr($cnpj_limpo, 0, 8);

    require_once __DIR__ . '/conexao.php';

    // Criar tabela de controle de restaurações, caso não exista
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes_restaurados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cnpj VARCHAR(32) NOT NULL,
        raiz_cnpj VARCHAR(8) NOT NULL,
        usuario_id INT NULL,
        data_restauracao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_raiz (raiz_cnpj)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Registrar restauração por raiz CNPJ (sem usar DELETE)
    $stmt = $pdo->prepare("INSERT INTO clientes_restaurados (cnpj, raiz_cnpj, usuario_id)
                           VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE cnpj = VALUES(cnpj), usuario_id = VALUES(usuario_id), data_restauracao = CURRENT_TIMESTAMP");
    $ok = $stmt->execute([$cnpj_limpo, $raiz_cnpj, $usuario['id'] ?? null]);

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao registrar restauração']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>



