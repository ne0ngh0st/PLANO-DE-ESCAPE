<?php
session_start();
header('Content-Type: application/json');

// Verificação de login
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é admin
$usuario = $_SESSION['usuario'];
$perfilUsuario = strtolower($usuario['perfil'] ?? '');

if ($perfilUsuario !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Apenas administradores podem excluir observações']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar dados obrigatórios
if (!isset($_POST['observacao_id']) || empty($_POST['observacao_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID da observação não fornecido']);
    exit;
}

$observacaoId = intval($_POST['observacao_id']);

require_once __DIR__ . '/conexao.php';

try {
    // Verificar se a observação existe e não foi excluída
    $sqlVerificar = "SELECT id, tipo, identificador, observacao, usuario_id, usuario_nome, data_criacao
                     FROM observacoes 
                     WHERE id = ? 
                     AND NOT EXISTS (SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = ?)";
    $stmtVerificar = $pdo->prepare($sqlVerificar);
    $stmtVerificar->execute([$observacaoId, $observacaoId]);
    $observacao = $stmtVerificar->fetch(PDO::FETCH_ASSOC);
    
    if (!$observacao) {
        echo json_encode(['success' => false, 'message' => 'Observação não encontrada ou já foi excluída']);
        exit;
    }
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Inserir na tabela de observações excluídas
    $sqlInserirExcluida = "INSERT INTO observacoes_excluidas 
                          (observacao_id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, data_exclusao, usuario_exclusao, motivo_exclusao) 
                          SELECT id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, NOW(), ?, 'Excluída pelo administrador'
                          FROM observacoes 
                          WHERE id = ?";
    
    $stmtInserirExcluida = $pdo->prepare($sqlInserirExcluida);
    $resultadoInserir = $stmtInserirExcluida->execute([$usuario['id'], $observacaoId]);
    
    if (!$resultadoInserir) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao mover observação para excluídas']);
        exit;
    }
    
    // Verificar se existem observações filhas (respostas) e excluí-las também
    $sqlFilhas = "SELECT id FROM observacoes WHERE parent_id = ? AND NOT EXISTS (SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = observacoes.id)";
    $stmtFilhas = $pdo->prepare($sqlFilhas);
    $stmtFilhas->execute([$observacaoId]);
    $observacoesFilhas = $stmtFilhas->fetchAll(PDO::FETCH_COLUMN);
    
    // Excluir observações filhas (respostas)
    foreach ($observacoesFilhas as $filhaId) {
        $sqlInserirFilhaExcluida = "INSERT INTO observacoes_excluidas 
                                   (observacao_id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, data_exclusao, usuario_exclusao, motivo_exclusao) 
                                   SELECT id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, NOW(), ?, 'Excluída junto com observação pai'
                                   FROM observacoes 
                                   WHERE id = ?";
        
        $stmtInserirFilhaExcluida = $pdo->prepare($sqlInserirFilhaExcluida);
        $stmtInserirFilhaExcluida->execute([$usuario['id'], $filhaId]);
    }
    
    // Confirmar transação
    $pdo->commit();
    
    // Log da ação
    $totalExcluidas = 1 + count($observacoesFilhas);
    error_log("Admin {$usuario['id']} excluiu observação {$observacaoId} e {$totalExcluidas} observações relacionadas");
    
    echo json_encode([
        'success' => true,
        'message' => "Observação excluída com sucesso! ({$totalExcluidas} observações afetadas)",
        'observacoes_excluidas' => $totalExcluidas
    ]);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro ao excluir observação: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor'
    ]);
}
?>

