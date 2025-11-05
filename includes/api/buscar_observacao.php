<?php
session_start();
header('Content-Type: application/json');

// Verificação de login
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID da observação não fornecido']);
    exit;
}

require_once __DIR__ . '/conexao.php';

try {
    $observacaoId = intval($_GET['id']);
    
    // Buscar dados da observação
    $sql = "SELECT id, tipo, identificador, observacao, usuario_id, usuario_nome, 
                   data_criacao, parent_id,
                   DATE_FORMAT(data_criacao, '%d/%m/%Y %H:%i') as data_formatada
            FROM observacoes 
            WHERE id = ? 
            AND NOT EXISTS (SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$observacaoId, $observacaoId]);
    $observacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$observacao) {
        echo json_encode(['success' => false, 'message' => 'Observação não encontrada']);
        exit;
    }
    
    // Verificar permissões baseadas no perfil do usuário
    $usuario = $_SESSION['usuario'];
    $perfilUsuario = strtolower($usuario['perfil'] ?? '');
    
    // Supervisores só podem ver observações de sua equipe
    if ($perfilUsuario === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $sqlPermissao = "SELECT o.id FROM observacoes o 
                        INNER JOIN USUARIOS u ON o.usuario_id = u.ID 
                        WHERE o.id = ? AND u.COD_SUPER = ?";
        $stmtPermissao = $pdo->prepare($sqlPermissao);
        $stmtPermissao->execute([$observacaoId, $usuario['cod_vendedor']]);
        
        if (!$stmtPermissao->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para acessar esta observação']);
            exit;
        }
    }
    
    // Retornar dados da observação
    echo json_encode([
        'success' => true,
        'observacao' => $observacao
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar observação: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor'
    ]);
}
?>

