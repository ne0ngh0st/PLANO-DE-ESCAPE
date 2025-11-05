<?php
require_once '../config.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar permissões para exclusão
function verificarPermissaoExclusao() {
    $perfil = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
    return in_array($perfil, ['admin', 'diretor']);
}

// Verificar quais campos existem na tabela
function obterCamposExistentes() {
    global $pdo;
    
    $sql = "SHOW COLUMNS FROM LICITACAO";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Obter campos que podem ser atualizados
function obterCamposAtualizaveis() {
    $existing_fields = obterCamposExistentes();
    $possible_fields = [
        'GERENCIADOR', 'SIGLA', 'ORGAO', 'EDITAL_LICITACAO', 'NUMERO_CONTRATO',
        'VALOR_GLOBAL', 'VALOR_CONSUMIDO', 'DATA_INICIO_CONTRATO', 'DATA_TERMINO_CONTRATO',
        'STATUS', 'CNPJ', 'PRODUTO', 'TIPO', 'COD_CLIENT'
    ];
    
    return array_intersect($possible_fields, $existing_fields);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            criarContrato();
            break;
        case 'update':
            atualizarContrato();
            break;
        case 'delete':
            excluirContrato();
            break;
        case 'get':
            obterContrato();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
    }
} catch (Exception $e) {
    error_log("Erro no CRUD de contratos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

function criarContrato() {
    global $pdo;
    
    $campos_atualizaveis = obterCamposAtualizaveis();
    $dados = [];
    
    // Coletar apenas campos que existem na tabela
    foreach ($campos_atualizaveis as $campo) {
        switch ($campo) {
            case 'valor_global':
            case 'valor_consumido':
                $dados[$campo] = floatval($_POST[$campo] ?? 0);
                break;
            default:
                $dados[$campo] = trim($_POST[$campo] ?? '');
        }
    }
    
    // Validações obrigatórias
    if (empty($dados['gerenciador'])) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador é obrigatório']);
        return;
    }
    
    if (empty($dados['razao_social'])) {
        echo json_encode(['success' => false, 'message' => 'Razão social é obrigatória']);
        return;
    }
    
    if (isset($dados['valor_global']) && $dados['valor_global'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor global deve ser maior que zero']);
        return;
    }
    
    // Validar datas se existirem
    if (isset($dados['data_inicio_vigencia']) && isset($dados['data_termino_vigencia'])) {
        if (!empty($dados['data_inicio_vigencia']) && !empty($dados['data_termino_vigencia'])) {
            $inicio = new DateTime($dados['data_inicio_vigencia']);
            $termino = new DateTime($dados['data_termino_vigencia']);
            
            if ($inicio >= $termino) {
                echo json_encode(['success' => false, 'message' => 'Data de término deve ser posterior à data de início']);
                return;
            }
        }
    }
    
    // Construir query dinâmica
    $campos = array_keys($dados);
    $placeholders = str_repeat('?,', count($campos) - 1) . '?';
    
    // Adicionar campos de auditoria se existirem
    $existing_fields = obterCamposExistentes();
    if (in_array('data_cadastro', $existing_fields)) {
        $campos[] = 'data_cadastro';
        $placeholders .= ', NOW()';
    }
    if (in_array('usuario_cadastro', $existing_fields)) {
        $campos[] = 'usuario_cadastro';
        $placeholders .= ', ?';
        $dados['usuario_cadastro'] = $_SESSION['usuario']['nome'] ?? 'Sistema';
    }
    
    $sql = "INSERT INTO contratos (" . implode(', ', $campos) . ") VALUES ({$placeholders})";
    
    $stmt = $pdo->prepare($sql);
    $values = array_values($dados);
    $result = $stmt->execute($values);
    
    if ($result) {
        $id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Contrato criado com sucesso',
            'id' => $id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar contrato']);
    }
}

function atualizarContrato() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do contrato inválido']);
        return;
    }
    
    $campos_atualizaveis = obterCamposAtualizaveis();
    $dados = [];
    
    // Coletar apenas campos que existem na tabela
    foreach ($campos_atualizaveis as $campo) {
        switch ($campo) {
            case 'valor_global':
            case 'valor_consumido':
                $dados[$campo] = floatval($_POST[$campo] ?? 0);
                break;
            default:
                $dados[$campo] = trim($_POST[$campo] ?? '');
        }
    }
    
    // Validações obrigatórias
    if (empty($dados['gerenciador'])) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador é obrigatório']);
        return;
    }
    
    if (empty($dados['razao_social'])) {
        echo json_encode(['success' => false, 'message' => 'Razão social é obrigatória']);
        return;
    }
    
    if (isset($dados['valor_global']) && $dados['valor_global'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor global deve ser maior que zero']);
        return;
    }
    
    // Validar datas se existirem
    if (isset($dados['data_inicio_vigencia']) && isset($dados['data_termino_vigencia'])) {
        if (!empty($dados['data_inicio_vigencia']) && !empty($dados['data_termino_vigencia'])) {
            $inicio = new DateTime($dados['data_inicio_vigencia']);
            $termino = new DateTime($dados['data_termino_vigencia']);
            
            if ($inicio >= $termino) {
                echo json_encode(['success' => false, 'message' => 'Data de término deve ser posterior à data de início']);
                return;
            }
        }
    }
    
    // Construir query dinâmica
    $set_clauses = [];
    foreach (array_keys($dados) as $campo) {
        $set_clauses[] = "{$campo} = ?";
    }
    
    // Adicionar campos de auditoria se existirem
    $existing_fields = obterCamposExistentes();
    if (in_array('data_alteracao', $existing_fields)) {
        $set_clauses[] = 'data_alteracao = NOW()';
    }
    if (in_array('usuario_alteracao', $existing_fields)) {
        $set_clauses[] = 'usuario_alteracao = ?';
        $dados['usuario_alteracao'] = $_SESSION['usuario']['nome'] ?? 'Sistema';
    }
    
    $sql = "UPDATE contratos SET " . implode(', ', $set_clauses) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $values = array_values($dados);
    $values[] = $id;
    $result = $stmt->execute($values);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Contrato atualizado com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar contrato']);
    }
}

function excluirContrato() {
    global $pdo;
    
    // Verificar permissão de exclusão
    if (!verificarPermissaoExclusao()) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir contratos. Apenas administradores e diretores podem realizar esta ação.']);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do contrato inválido']);
        return;
    }
    
    // Verificar se o contrato existe e não está já excluído
    $sql_check = "SELECT id, status, razao_social FROM contratos WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $contrato = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrato) {
        echo json_encode(['success' => false, 'message' => 'Contrato não encontrado']);
        return;
    }
    
    if ($contrato['status'] === 'Excluído') {
        echo json_encode(['success' => false, 'message' => 'Este contrato já foi excluído']);
        return;
    }
    
    // Marcar como excluído em vez de deletar
    $sql = "UPDATE contratos SET status = 'Excluído' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Contrato "' . $contrato['razao_social'] . '" excluído com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir contrato']);
    }
}

function obterContrato() {
    global $pdo;
    
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do contrato inválido']);
        return;
    }
    
    $sql = "SELECT * FROM contratos WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contrato) {
        echo json_encode([
            'success' => true,
            'contrato' => $contrato
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contrato não encontrado']);
    }
}
?>

