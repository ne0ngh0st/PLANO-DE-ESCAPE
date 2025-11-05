<?php
session_start();
header('Content-Type: application/json');

// Verificação de login
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar dados obrigatórios
if (!isset($_POST['observacao_id']) || !isset($_POST['resposta'])) {
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos']);
    exit;
}

$observacaoId = intval($_POST['observacao_id']);
$resposta = trim($_POST['resposta']);

if (empty($resposta)) {
    echo json_encode(['success' => false, 'message' => 'Resposta não pode estar vazia']);
    exit;
}

if (strlen($resposta) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Resposta muito longa (máximo 1000 caracteres)']);
    exit;
}

require_once __DIR__ . '/conexao.php';

try {
    $usuario = $_SESSION['usuario'];
    
    // Verificar se a observação original existe e não foi excluída
    $sqlVerificar = "SELECT id, tipo, identificador 
                     FROM observacoes 
                     WHERE id = ? 
                     AND NOT EXISTS (SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = ?)";
    $stmtVerificar = $pdo->prepare($sqlVerificar);
    $stmtVerificar->execute([$observacaoId, $observacaoId]);
    $observacaoOriginal = $stmtVerificar->fetch(PDO::FETCH_ASSOC);
    
    if (!$observacaoOriginal) {
        echo json_encode(['success' => false, 'message' => 'Observação original não encontrada']);
        exit;
    }
    
    // Verificar permissões baseadas no perfil do usuário
    $perfilUsuario = strtolower($usuario['perfil'] ?? '');
    
    // Supervisores só podem responder observações de sua equipe
    if ($perfilUsuario === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $sqlPermissao = "SELECT o.id FROM observacoes o 
                        INNER JOIN USUARIOS u ON o.usuario_id = u.ID 
                        WHERE o.id = ? AND u.COD_SUPER = ?";
        $stmtPermissao = $pdo->prepare($sqlPermissao);
        $stmtPermissao->execute([$observacaoId, $usuario['cod_vendedor']]);
        
        if (!$stmtPermissao->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para responder esta observação']);
            exit;
        }
    }
    
    // Buscar nome do usuário da sessão ou do banco
    $nomeUsuario = '';
    if (!empty($usuario['nome_completo'])) {
        $nomeUsuario = $usuario['nome_completo'];
    } elseif (!empty($usuario['NOME_COMPLETO'])) {
        $nomeUsuario = $usuario['NOME_COMPLETO'];
    } elseif (!empty($usuario['usuario'])) {
        $nomeUsuario = $usuario['usuario'];
    } else {
        // Buscar no banco se não tiver na sessão
        $sqlUsuario = "SELECT NOME_COMPLETO FROM USUARIOS WHERE ID = ?";
        $stmtUsuario = $pdo->prepare($sqlUsuario);
        $stmtUsuario->execute([$usuario['id']]);
        $dadosUsuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
        $nomeUsuario = $dadosUsuario['NOME_COMPLETO'] ?? 'Usuário';
    }
    
    // Inserir nova observação como resposta
    $sqlInserir = "INSERT INTO observacoes (tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, data_atualizacao) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmtInserir = $pdo->prepare($sqlInserir);
    $resultado = $stmtInserir->execute([
        $observacaoOriginal['tipo'],
        $observacaoOriginal['identificador'],
        $resposta,
        $usuario['id'],
        $nomeUsuario,
        $observacaoId
    ]);
    
    if ($resultado) {
        // Log da ação (opcional)
        error_log("Resposta criada pelo usuário {$usuario['id']} para observação {$observacaoId}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Resposta enviada com sucesso',
            'nova_observacao_id' => $pdo->lastInsertId()
        ]);
    } else {
        // Log do erro SQL
        $errorInfo = $stmtInserir->errorInfo();
        error_log("Erro SQL ao inserir resposta: " . print_r($errorInfo, true));
        
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao salvar resposta: ' . ($errorInfo[2] ?? 'Erro desconhecido')
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao enviar resposta: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor'
    ]);
}
?>
