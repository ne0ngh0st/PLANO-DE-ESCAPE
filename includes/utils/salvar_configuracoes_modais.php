<?php
session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$perfilUsuario = $_SESSION['usuario']['perfil'] ?? '';
if (!in_array($perfilUsuario, ['admin', 'diretor'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

require_once '../config/conexao.php';

try {
    // Ler dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Criar tabela de configurações se não existir
    $sql = "CREATE TABLE IF NOT EXISTS CONFIGURACOES_MODAIS (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        chave VARCHAR(100) NOT NULL,
        valor TEXT,
        ordem INT DEFAULT 0,
        ativo TINYINT(1) DEFAULT 1,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_tipo_chave (tipo, chave)
    )";
    $pdo->exec($sql);
    
    // Salvar configurações gerais
    $configuracoes = [
        ['tipo' => 'modal', 'chave' => 'titulo_ligacao', 'valor' => $input['titulo_modal'] ?? 'Roteiro de Ligação'],
        ['tipo' => 'modal', 'chave' => 'texto_confirmacao', 'valor' => $input['texto_confirmacao'] ?? 'Tem certeza que deseja remover este item da sua carteira?'],
        ['tipo' => 'modal', 'chave' => 'texto_observacoes', 'valor' => $input['texto_observacoes'] ?? 'Digite sua observação aqui...'],
        ['tipo' => 'modal', 'chave' => 'texto_agendamento', 'valor' => $input['texto_agendamento'] ?? 'Agendar Ligação']
    ];
    
    foreach ($configuracoes as $config) {
        $sql = "INSERT INTO CONFIGURACOES_MODAIS (tipo, chave, valor, ordem) 
                VALUES (?, ?, ?, 0) 
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), data_atualizacao = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$config['tipo'], $config['chave'], $config['valor']]);
    }
    
    // Salvar motivos de exclusão
    if (isset($input['motivos_exclusao']) && is_array($input['motivos_exclusao'])) {
        // Remover motivos antigos
        $sql = "DELETE FROM CONFIGURACOES_MODAIS WHERE tipo = 'exclusao'";
        $pdo->exec($sql);
        
        // Inserir novos motivos
        foreach ($input['motivos_exclusao'] as $index => $motivo) {
            if (!empty(trim($motivo))) {
                $sql = "INSERT INTO CONFIGURACOES_MODAIS (tipo, chave, valor, ordem) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['exclusao', 'motivo_' . $index, trim($motivo), $index]);
            }
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()]);
}
?>
