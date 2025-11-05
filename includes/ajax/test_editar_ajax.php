<?php
require_once '../config.php';
session_start();

// Definir header JSON
header('Content-Type: application/json');

// Log de debug
error_log("=== TESTE EDITAR AJAX ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("Dados POST: " . print_r($_POST, true));
error_log("Dados GET: " . print_r($_GET, true));

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    error_log("Usuário não logado");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método não é POST");
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados do formulário
$id = $_POST['id'] ?? '';
$gerenciador = $_POST['gerenciador'] ?? '';
$status = $_POST['status'] ?? 'Vigente';
$sigla = $_POST['sigla'] ?? '';
$razao_social = $_POST['razao_social'] ?? '';
$numero_pregao = $_POST['numero_pregao'] ?? '';
$termo_contrato = $_POST['termo_contrato'] ?? '';
$valor_global = $_POST['valor_global'] ?? '';
$valor_consumido = $_POST['valor_consumido'] ?? '';
$data_inicio_vigencia = $_POST['data_inicio_vigencia'] ?? '';
$data_termino_vigencia = $_POST['data_termino_vigencia'] ?? '';

error_log("Dados recebidos:");
error_log("ID: $id");
error_log("Gerenciador: $gerenciador");
error_log("Status: $status");
error_log("Razão Social: $razao_social");
error_log("Valor Global: $valor_global");

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
    error_log("Valor global inválido: $valor_global");
    echo json_encode(['success' => false, 'message' => 'Valor Global deve ser um número válido']);
    exit;
}

// Converter valores para float
$valor_global = floatval($valor_global);
$valor_consumido = floatval($valor_consumido);

// Calcular saldo
$saldo_contrato = $valor_global - $valor_consumido;
$consumo_percent = $valor_global > 0 ? ($valor_consumido / $valor_global) * 100 : 0;

error_log("Valores calculados:");
error_log("Valor Global: $valor_global");
error_log("Valor Consumido: $valor_consumido");
error_log("Saldo: $saldo_contrato");
error_log("Percentual: $consumo_percent");

try {
    // Verificar se a licitação existe
    $sql_check = "SELECT ID, ORGAO FROM LICITACAO WHERE ID = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $licitacao_existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$licitacao_existe) {
        error_log("Licitação não encontrada - ID: $id");
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        exit;
    }
    
    error_log("Licitação encontrada: " . $licitacao_existe['ORGAO']);
    
    // Atualizar licitação
    $sql = "UPDATE LICITACAO SET 
                GERENCIADOR = ?,
                STATUS = ?,
                SIGLA = ?,
                ORGAO = ?,
                NUMERO_ATA = ?,
                NUMERO_CONTRATO = ?,
                VALOR_GLOBAL = ?,
                VALOR_CONSUMIDO = ?,
                DATA_INICIO_CONTRATO = ?,
                DATA_TERMINO_CONTRATO = ?,
                SALDO_CONTRATO = ?,
                CONSUMO_CONTRATO_PERCENT = ?
            WHERE ID = ?";
    
    error_log("SQL: $sql");
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $gerenciador,
        $status,
        $sigla,
        $razao_social,
        $numero_pregao,
        $termo_contrato,
        $valor_global,
        $valor_consumido,
        $data_inicio_vigencia ?: null,
        $data_termino_vigencia ?: null,
        $saldo_contrato,
        $consumo_percent,
        $id
    ]);
    
    if ($result) {
        $rowsAffected = $stmt->rowCount();
        error_log("Licitação atualizada com sucesso - ID: $id, Linhas afetadas: $rowsAffected");
        echo json_encode([
            'success' => true,
            'message' => 'Licitação atualizada com sucesso!',
            'debug' => [
                'id' => $id,
                'rows_affected' => $rowsAffected,
                'valor_global' => $valor_global,
                'valor_consumido' => $valor_consumido
            ]
        ]);
    } else {
        error_log("Erro ao atualizar licitação - ID: $id");
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao atualizar licitação'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao editar licitação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>

