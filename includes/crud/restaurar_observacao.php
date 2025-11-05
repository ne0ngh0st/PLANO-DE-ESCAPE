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
    if (!in_array($perfil, ['admin', 'supervisor', 'diretor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão para restaurar observações']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    $observacao_id = isset($_POST['observacao_id']) ? (int)$_POST['observacao_id'] : 0;
    if ($observacao_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID da observação inválido']);
        exit;
    }

    require_once __DIR__ . '/conexao.php';

    // Verificar se existe registro arquivado correspondente
    $stmt = $pdo->prepare("SELECT * FROM observacoes_excluidas WHERE observacao_id = ? ORDER BY data_exclusao DESC LIMIT 1");
    $stmt->execute([$observacao_id]);
    $excluida = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$excluida) {
        echo json_encode(['success' => false, 'message' => 'Registro arquivado não encontrado']);
        exit;
    }

    // Restaurar inserindo novamente na tabela principal, se não existir
    $pdo->beginTransaction();
    try {
        // Evitar duplicidade: verificar se já existe id igual
        $check = $pdo->prepare("SELECT id FROM observacoes WHERE id = ?");
        $check->execute([$observacao_id]);
        $exists = $check->fetchColumn();

        if ($exists) {
            // Já existe na principal: apenas remover do arquivo
            $del = $pdo->prepare("DELETE FROM observacoes_excluidas WHERE observacao_id = ?");
            $del->execute([$observacao_id]);
        } else {
            // Inserir na principal com o mesmo id
            $insert = $pdo->prepare("INSERT INTO observacoes (id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $excluida['observacao_id'],
                $excluida['tipo'],
                $excluida['identificador'],
                $excluida['observacao'],
                $excluida['usuario_id'],
                $excluida['usuario_nome'],
                $excluida['parent_id'] ?: null,
                $excluida['data_criacao'] ?: date('Y-m-d H:i:s')
            ]);

            // Remover do arquivo
            $del = $pdo->prepare("DELETE FROM observacoes_excluidas WHERE observacao_id = ?");
            $del->execute([$observacao_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>


