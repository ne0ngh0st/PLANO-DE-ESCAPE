<?php
require_once '../config.php';
session_start();

// Definir header JSON
header('Content-Type: application/json');

error_log("=== TESTE SIMPLES ===");

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    error_log("Usuário não logado");
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

error_log("Usuário logado: " . ($_SESSION['usuario']['nome'] ?? 'N/A'));

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método não é POST: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

error_log("Método POST OK");

// Obter dados do formulário
$id = $_POST['id'] ?? '';
$razao_social = $_POST['razao_social'] ?? '';
$valor_global = $_POST['valor_global'] ?? '';

error_log("Dados recebidos:");
error_log("ID: '$id'");
error_log("Razão Social: '$razao_social'");
error_log("Valor Global: '$valor_global'");

// Validações básicas
if (empty($id)) {
    error_log("ID vazio");
    echo json_encode(['success' => false, 'message' => 'ID da licitação é obrigatório']);
    exit;
}

if (empty($razao_social)) {
    error_log("Razão social vazia");
    echo json_encode(['success' => false, 'message' => 'Razão Social é obrigatória']);
    exit;
}

if (empty($valor_global) || !is_numeric($valor_global)) {
    error_log("Valor global inválido: '$valor_global'");
    echo json_encode(['success' => false, 'message' => 'Valor Global deve ser um número válido']);
    exit;
}

error_log("Validações OK");

try {
    // Verificar se a licitação existe
    $sql = "SELECT ID, ORGAO FROM LICITACAO WHERE ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $licitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licitacao) {
        error_log("Licitação não encontrada - ID: $id");
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        exit;
    }
    
    error_log("Licitação encontrada: " . $licitacao['ORGAO']);
    
    // Atualizar apenas alguns campos para teste
    $sql = "UPDATE LICITACAO SET 
                ORGAO = ?,
                VALOR_GLOBAL = ?
            WHERE ID = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $razao_social,
        floatval($valor_global),
        $id
    ]);
    
    if ($result) {
        $rowsAffected = $stmt->rowCount();
        error_log("UPDATE executado com sucesso - Linhas afetadas: $rowsAffected");
        echo json_encode([
            'success' => true,
            'message' => 'Licitação atualizada com sucesso!',
            'debug' => [
                'id' => $id,
                'rows_affected' => $rowsAffected
            ]
        ]);
    } else {
        error_log("Erro no UPDATE");
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar licitação']);
    }
    
} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>

