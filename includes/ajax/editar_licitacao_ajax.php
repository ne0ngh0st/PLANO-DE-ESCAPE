<?php
require_once dirname(__DIR__) . '/config.php';
session_start();

// Definir header JSON
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados do formulário
$id = $_POST['id'] ?? '';
$gerenciador = $_POST['gerenciador'] ?? '';
$status = $_POST['status'] ?? '';
$sigla = $_POST['sigla'] ?? '';
$razao_social = $_POST['razao_social'] ?? '';
$numero_pregao = $_POST['numero_pregao'] ?? '';
$termo_contrato = $_POST['termo_contrato'] ?? '';
$valor_global = $_POST['valor_global'] ?? '';
$valor_consumido = $_POST['valor_consumido'] ?? '';
$data_inicio_vigencia = $_POST['data_inicio_vigencia'] ?? '';
$data_termino_vigencia = $_POST['data_termino_vigencia'] ?? '';

// Log dos dados recebidos
error_log("=== EDITAR LICITAÇÃO ===");
error_log("ID: '$id'");
error_log("Gerenciador: '$gerenciador'");
error_log("Status: '$status'");
error_log("Razão Social: '$razao_social'");
error_log("Valor Global: '$valor_global'");

// Validações básicas
if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID da licitação é obrigatório']);
    exit;
}

if (empty($razao_social)) {
    echo json_encode(['success' => false, 'message' => 'Razão Social é obrigatória']);
    exit;
}

if (empty($valor_global) || !is_numeric($valor_global)) {
    echo json_encode(['success' => false, 'message' => 'Valor Global deve ser um número válido']);
    exit;
}

// Validar valor consumido se fornecido
if (!empty($valor_consumido) && !is_numeric($valor_consumido)) {
    echo json_encode(['success' => false, 'message' => 'Valor Consumido deve ser um número válido']);
    exit;
}

try {
    // Verificar se a licitação existe
    $sql = "SELECT ID, ORGAO, GERENCIADOR FROM LICITACAO WHERE ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $licitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licitacao) {
        error_log("Licitação não encontrada - ID: $id");
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        exit;
    }
    
    error_log("Licitação encontrada: " . $licitacao['ORGAO'] . " - Gerenciador: " . $licitacao['GERENCIADOR']);
    
    // Preparar dados para atualização
    $updateFields = [];
    $updateValues = [];
    
    // Campos que podem ser atualizados
    if (!empty($status)) {
        $updateFields[] = "STATUS = ?";
        $updateValues[] = $status;
    }
    
    if (!empty($sigla)) {
        $updateFields[] = "SIGLA = ?";
        $updateValues[] = $sigla;
    }
    
    if (!empty($razao_social)) {
        $updateFields[] = "ORGAO = ?";
        $updateValues[] = $razao_social;
    }
    
    if (!empty($numero_pregao)) {
        $updateFields[] = "NUMERO_ATA = ?";
        $updateValues[] = $numero_pregao;
    }
    
    if (!empty($termo_contrato)) {
        $updateFields[] = "NUMERO_CONTRATO = ?";
        $updateValues[] = $termo_contrato;
    }
    
    if (!empty($valor_global)) {
        $updateFields[] = "VALOR_GLOBAL = ?";
        $updateValues[] = floatval($valor_global);
    }
    
    if (!empty($valor_consumido)) {
        $updateFields[] = "VALOR_CONSUMIDO = ?";
        $updateValues[] = floatval($valor_consumido);
    }
    
    if (!empty($data_inicio_vigencia)) {
        $updateFields[] = "DATA_INICIO_CONTRATO = ?";
        $updateValues[] = $data_inicio_vigencia;
    }
    
    if (!empty($data_termino_vigencia)) {
        $updateFields[] = "DATA_TERMINO_CONTRATO = ?";
        $updateValues[] = $data_termino_vigencia;
    }
    
    // Adicionar ID no final para a cláusula WHERE
    $updateValues[] = $id;
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
        exit;
    }
    
    // Construir query de atualização
    $sql = "UPDATE LICITACAO SET " . implode(', ', $updateFields) . " WHERE ID = ?";
    
    error_log("Query SQL: $sql");
    error_log("Valores: " . implode(', ', $updateValues));
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($updateValues);
    
    if ($result) {
        $rowsAffected = $stmt->rowCount();
        error_log("UPDATE executado com sucesso - Linhas afetadas: $rowsAffected");
        
        // Recalcular saldo e percentual se valores foram alterados
        if (!empty($valor_global) || !empty($valor_consumido)) {
            $sql_recalc = "UPDATE LICITACAO SET 
                            SALDO_CONTRATO = VALOR_GLOBAL - COALESCE(VALOR_CONSUMIDO, 0),
                            CONSUMO_CONTRATO_PERCENT = CASE 
                                WHEN VALOR_GLOBAL > 0 THEN (COALESCE(VALOR_CONSUMIDO, 0) / VALOR_GLOBAL) * 100 
                                ELSE 0 
                            END
                            WHERE ID = ?";
            $stmt_recalc = $pdo->prepare($sql_recalc);
            $stmt_recalc->execute([$id]);
            error_log("Recálculo de saldo e percentual executado");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Licitação atualizada com sucesso!',
            'debug' => [
                'id' => $id,
                'rows_affected' => $rowsAffected,
                'fields_updated' => count($updateFields)
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