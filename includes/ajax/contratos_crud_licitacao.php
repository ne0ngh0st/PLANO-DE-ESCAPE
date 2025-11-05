<?php
// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_clean();
}

// Incluir apenas o necessário
require_once '../config.php';

header('Content-Type: application/json');

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar permissões para exclusão
function verificarPermissaoExclusao() {
    $perfil = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
    return in_array($perfil, ['admin', 'diretor']);
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
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'Erro fatal: ' . $e->getMessage()]);
}

function criarContrato() {
    global $pdo;
    
    // Mapear campos do formulário para campos da tabela LICITACAO
    $mapeamento = [
        'gerenciador' => 'GERENCIADOR',
        'sigla' => 'SIGLA',
        'razao_social' => 'ORGAO',
        'numero_pregao' => 'EDITAL_LICITACAO',
        'termo_contrato' => 'NUMERO_CONTRATO',
        'valor_global' => 'VALOR_GLOBAL',
        'valor_consumido' => 'VALOR_CONSUMIDO',
        'data_inicio_vigencia' => 'DATA_INICIO_CONTRATO',
        'data_termino_vigencia' => 'DATA_TERMINO_CONTRATO',
        'status' => 'STATUS'
    ];
    
    $dados = [];
    
    // Coletar dados do formulário e mapear para campos da tabela
    foreach ($mapeamento as $campo_form => $campo_tabela) {
        $valor = $_POST[$campo_form] ?? '';
        
        // Tratar valores numéricos
        if (in_array($campo_form, ['valor_global', 'valor_consumido'])) {
            $dados[$campo_tabela] = floatval($valor);
        } else {
            $dados[$campo_tabela] = trim($valor);
        }
    }
    
    // Validações obrigatórias
    if (empty($dados['GERENCIADOR'])) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador é obrigatório']);
        return;
    }
    
    if (empty($dados['ORGAO'])) {
        echo json_encode(['success' => false, 'message' => 'Órgão é obrigatório']);
        return;
    }
    
    if (isset($dados['VALOR_GLOBAL']) && $dados['VALOR_GLOBAL'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor global deve ser maior que zero']);
        return;
    }
    
    // Definir status padrão se não informado
    if (empty($dados['STATUS'])) {
        $dados['STATUS'] = 'Vigente';
    }
    
    // Adicionar COD_VENDEDOR_CRIACAO
    $cod_vendedor = $_SESSION['usuario']['COD_VENDEDOR'] ?? $_SESSION['usuario']['cod_vendedor'] ?? '';
    $dados['COD_VENDEDOR_CRIACAO'] = $cod_vendedor;
    
    // Construir query de inserção
    $campos = array_keys($dados);
    $placeholders = str_repeat('?,', count($campos) - 1) . '?';
    
    $sql = "INSERT INTO LICITACAO (" . implode(', ', $campos) . ") VALUES ({$placeholders})";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute(array_values($dados));
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Licitação criada com sucesso',
            'id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar licitação']);
    }
}

function atualizarContrato() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    // Mapear campos do formulário para campos da tabela LICITACAO
    $mapeamento = [
        'gerenciador' => 'GERENCIADOR',
        'sigla' => 'SIGLA',
        'razao_social' => 'ORGAO',
        'numero_pregao' => 'EDITAL_LICITACAO',
        'termo_contrato' => 'NUMERO_CONTRATO',
        'valor_global' => 'VALOR_GLOBAL',
        'valor_consumido' => 'VALOR_CONSUMIDO',
        'data_inicio_vigencia' => 'DATA_INICIO_CONTRATO',
        'data_termino_vigencia' => 'DATA_TERMINO_CONTRATO',
        'status' => 'STATUS'
    ];
    
    $dados = [];
    
    // Coletar dados do formulário e mapear para campos da tabela
    foreach ($mapeamento as $campo_form => $campo_tabela) {
        if (isset($_POST[$campo_form])) {
            $valor = $_POST[$campo_form];
            
            // Tratar valores numéricos
            if (in_array($campo_form, ['valor_global', 'valor_consumido'])) {
                $dados[$campo_tabela] = floatval($valor);
            } else {
                $dados[$campo_tabela] = trim($valor);
            }
        }
    }
    
    if (empty($dados)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum dado para atualizar']);
        return;
    }
    
    // Construir query de atualização
    $set_clauses = [];
    foreach ($dados as $campo => $valor) {
        $set_clauses[] = "$campo = ?";
    }
    
    $sql = "UPDATE LICITACAO SET " . implode(', ', $set_clauses) . " WHERE ID = ?";
    $dados['ID'] = $id;
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute(array_values($dados));
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Licitação atualizada com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar licitação']);
    }
}

function excluirContrato() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    // Verificar permissões
    if (!verificarPermissaoExclusao()) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para excluir']);
        return;
    }
    
    // Marcar como excluído ao invés de deletar fisicamente
    $sql = "UPDATE LICITACAO SET STATUS = 'Excluído' WHERE ID = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Licitação excluída com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir licitação']);
    }
}

function obterContrato() {
    global $pdo;
    
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    
    $sql = "SELECT * FROM LICITACAO WHERE ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($contrato) {
        echo json_encode([
            'success' => true,
            'contrato' => $contrato
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
    }
}
?>