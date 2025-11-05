<?php
// Definir header JSON antes de qualquer output
header('Content-Type: application/json; charset=utf-8');

// Suprimir warnings que podem corromper o JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Endpoint para salvar edições de licitação
require_once __DIR__ . '/../config/config.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Obter dados do POST
$id = $_POST['id'] ?? '';
$cod_cliente = $_POST['cod_cliente'] ?? '';
$cnpj = $_POST['cnpj'] ?? '';
$status = $_POST['status'] ?? '';
$sigla = $_POST['sigla'] ?? '';
$razao_social = $_POST['razao_social'] ?? '';
$tipo = $_POST['tipo'] ?? '';
$produto = $_POST['produto'] ?? '';
$numero_pregao = $_POST['numero_pregao'] ?? '';
$termo_contrato = $_POST['termo_contrato'] ?? '';
$valor_ata = $_POST['valor_ata'] ?? '';
$valor_global = $_POST['valor_global'] ?? '';
$valor_consumido = $_POST['valor_consumido'] ?? '';
$grupo = $_POST['grupo'] ?? '';
$tabela = $_POST['tabela'] ?? '';
$edital_licitacao = $_POST['edital_licitacao'] ?? '';
$data_inicio_ata = $_POST['data_inicio_ata'] ?? '';
$data_termino_ata = $_POST['data_termino_ata'] ?? '';
$data_inicio = $_POST['data_inicio_vigencia'] ?? '';
$data_termino = $_POST['data_termino_vigencia'] ?? '';

// Validações básicas
if (empty($id) || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
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
    $sql = "SELECT ID FROM LICITACAO WHERE ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        exit;
    }
    
    // Preparar dados para atualização
    $campos = [];
    $valores = [];
    
    // Campos que podem ser atualizados
    if (!empty($cod_cliente)) {
        $campos[] = "COD_CLIENT = ?";
        $valores[] = $cod_cliente;
    }
    
    if (!empty($cnpj)) {
        $campos[] = "CNPJ = ?";
        $valores[] = $cnpj;
    }
    
    if (!empty($status)) {
        $campos[] = "STATUS = ?";
        $valores[] = $status;
    }
    
    if (!empty($sigla)) {
        $campos[] = "SIGLA = ?";
        $valores[] = $sigla;
    }
    
    if (!empty($razao_social)) {
        $campos[] = "ORGAO = ?";
        $valores[] = $razao_social;
    }
    
    if (!empty($tipo)) {
        $campos[] = "TIPO = ?";
        $valores[] = $tipo;
    }
    
    if (!empty($produto)) {
        $campos[] = "PRODUTO = ?";
        $valores[] = $produto;
    }
    
    if (!empty($numero_pregao)) {
        $campos[] = "NUMERO_ATA = ?";
        $valores[] = $numero_pregao;
    }
    
    if (!empty($termo_contrato)) {
        $campos[] = "NUMERO_CONTRATO = ?";
        $valores[] = $termo_contrato;
    }
    
    if (!empty($valor_ata)) {
        $campos[] = "VALOR_ATA = ?";
        $valores[] = floatval($valor_ata);
    }
    
    if (!empty($valor_global)) {
        $campos[] = "VALOR_GLOBAL = ?";
        $valores[] = floatval($valor_global);
    }
    
    if (!empty($valor_consumido)) {
        $campos[] = "VALOR_CONSUMIDO = ?";
        $valores[] = floatval($valor_consumido);
    }
    
    if (!empty($grupo)) {
        $campos[] = "GRUPO = ?";
        $valores[] = intval($grupo);
    }
    
    if (!empty($tabela)) {
        $campos[] = "TABELA = ?";
        $valores[] = $tabela;
    }
    
    if (!empty($edital_licitacao)) {
        $campos[] = "EDITAL_LICITACAO = ?";
        $valores[] = $edital_licitacao;
    }
    
    if (!empty($data_inicio_ata)) {
        $campos[] = "DATA_INICIO_ATA = ?";
        $valores[] = $data_inicio_ata;
    }
    
    if (!empty($data_termino_ata)) {
        $campos[] = "DATA_TERMINO_ATA = ?";
        $valores[] = $data_termino_ata;
    }
    
    if (!empty($data_inicio)) {
        $campos[] = "DATA_INICIO_CONTRATO = ?";
        $valores[] = $data_inicio;
    }
    
    if (!empty($data_termino)) {
        $campos[] = "DATA_TERMINO_CONTRATO = ?";
        $valores[] = $data_termino;
    }
    
    if (empty($campos)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
        exit;
    }
    
    // Adicionar ID para a cláusula WHERE
    $valores[] = $id;
    
    // Executar UPDATE
    $sql = "UPDATE LICITACAO SET " . implode(', ', $campos) . " WHERE ID = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($valores);
    
    if ($result) {
        // Recalcular saldo e percentual
        $sql_recalc = "UPDATE LICITACAO SET 
                        SALDO_CONTRATO = VALOR_GLOBAL - COALESCE(VALOR_CONSUMIDO, 0),
                        CONSUMO_CONTRATO_PERCENT = CASE 
                            WHEN VALOR_GLOBAL > 0 THEN (COALESCE(VALOR_CONSUMIDO, 0) / VALOR_GLOBAL) * 100 
                            ELSE 0 
                        END
                        WHERE ID = ?";
        $stmt_recalc = $pdo->prepare($sql_recalc);
        $stmt_recalc->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Licitação atualizada com sucesso!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar licitação']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao salvar licitação: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
