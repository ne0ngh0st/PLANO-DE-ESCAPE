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
    
    $dados = [
        'gerenciador' => trim($_POST['gerenciador'] ?? ''),
        'sigla' => trim($_POST['sigla'] ?? ''),
        'razao_social' => trim($_POST['razao_social'] ?? ''),
        'numero_pregao' => trim($_POST['numero_pregao'] ?? ''),
        'termo_contrato' => trim($_POST['termo_contrato'] ?? ''),
        'valor_global' => floatval($_POST['valor_global'] ?? 0),
        'valor_consumido' => floatval($_POST['valor_consumido'] ?? 0),
        'data_inicio_vigencia' => $_POST['data_inicio_vigencia'] ?? '',
        'data_termino_vigencia' => $_POST['data_termino_vigencia'] ?? '',
        'status' => $_POST['status'] ?? 'Vigente',
        'cnpj' => trim($_POST['cnpj'] ?? ''),
        'endereco' => trim($_POST['endereco'] ?? ''),
        'cidade' => trim($_POST['cidade'] ?? ''),
        'uf' => trim($_POST['uf'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'observacoes' => trim($_POST['observacoes'] ?? '')
    ];
    
    // Validações
    if (empty($dados['gerenciador'])) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador é obrigatório']);
        return;
    }
    
    if (empty($dados['razao_social'])) {
        echo json_encode(['success' => false, 'message' => 'Razão social é obrigatória']);
        return;
    }
    
    if ($dados['valor_global'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor global deve ser maior que zero']);
        return;
    }
    
    // Validar datas
    if (!empty($dados['data_inicio_vigencia']) && !empty($dados['data_termino_vigencia'])) {
        $inicio = new DateTime($dados['data_inicio_vigencia']);
        $termino = new DateTime($dados['data_termino_vigencia']);
        
        if ($inicio >= $termino) {
            echo json_encode(['success' => false, 'message' => 'Data de término deve ser posterior à data de início']);
            return;
        }
    }
    
    $sql = "INSERT INTO contratos (
        gerenciador, sigla, razao_social, numero_pregao, termo_contrato,
        valor_global, valor_consumido, data_inicio_vigencia, data_termino_vigencia, status,
        cnpj, endereco, cidade, uf, telefone, email, observacoes,
        data_cadastro, usuario_cadastro
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $dados['gerenciador'],
        $dados['sigla'],
        $dados['razao_social'],
        $dados['numero_pregao'],
        $dados['termo_contrato'],
        $dados['valor_global'],
        $dados['valor_consumido'],
        $dados['data_inicio_vigencia'],
        $dados['data_termino_vigencia'],
        $dados['status'],
        $dados['cnpj'],
        $dados['endereco'],
        $dados['cidade'],
        $dados['uf'],
        $dados['telefone'],
        $dados['email'],
        $dados['observacoes'],
        $_SESSION['usuario']['nome'] ?? 'Sistema'
    ]);
    
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
    
    $dados = [
        'gerenciador' => trim($_POST['gerenciador'] ?? ''),
        'sigla' => trim($_POST['sigla'] ?? ''),
        'razao_social' => trim($_POST['razao_social'] ?? ''),
        'numero_pregao' => trim($_POST['numero_pregao'] ?? ''),
        'termo_contrato' => trim($_POST['termo_contrato'] ?? ''),
        'valor_global' => floatval($_POST['valor_global'] ?? 0),
        'valor_consumido' => floatval($_POST['valor_consumido'] ?? 0),
        'data_inicio_vigencia' => $_POST['data_inicio_vigencia'] ?? '',
        'data_termino_vigencia' => $_POST['data_termino_vigencia'] ?? '',
        'status' => $_POST['status'] ?? 'Vigente',
        'cnpj' => trim($_POST['cnpj'] ?? ''),
        'endereco' => trim($_POST['endereco'] ?? ''),
        'cidade' => trim($_POST['cidade'] ?? ''),
        'uf' => trim($_POST['uf'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'observacoes' => trim($_POST['observacoes'] ?? '')
    ];
    
    // Validações
    if (empty($dados['gerenciador'])) {
        echo json_encode(['success' => false, 'message' => 'Gerenciador é obrigatório']);
        return;
    }
    
    if (empty($dados['razao_social'])) {
        echo json_encode(['success' => false, 'message' => 'Razão social é obrigatória']);
        return;
    }
    
    if ($dados['valor_global'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor global deve ser maior que zero']);
        return;
    }
    
    // Validar datas
    if (!empty($dados['data_inicio_vigencia']) && !empty($dados['data_termino_vigencia'])) {
        $inicio = new DateTime($dados['data_inicio_vigencia']);
        $termino = new DateTime($dados['data_termino_vigencia']);
        
        if ($inicio >= $termino) {
            echo json_encode(['success' => false, 'message' => 'Data de término deve ser posterior à data de início']);
            return;
        }
    }
    
    $sql = "UPDATE contratos SET 
        gerenciador = ?, sigla = ?, razao_social = ?, numero_pregao = ?, termo_contrato = ?,
        valor_global = ?, valor_consumido = ?, data_inicio_vigencia = ?, data_termino_vigencia = ?, 
        status = ?, cnpj = ?, endereco = ?, cidade = ?, uf = ?, telefone = ?, email = ?, observacoes = ?,
        data_alteracao = NOW(), usuario_alteracao = ?
        WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $dados['gerenciador'],
        $dados['sigla'],
        $dados['razao_social'],
        $dados['numero_pregao'],
        $dados['termo_contrato'],
        $dados['valor_global'],
        $dados['valor_consumido'],
        $dados['data_inicio_vigencia'],
        $dados['data_termino_vigencia'],
        $dados['status'],
        $dados['cnpj'],
        $dados['endereco'],
        $dados['cidade'],
        $dados['uf'],
        $dados['telefone'],
        $dados['email'],
        $dados['observacoes'],
        $_SESSION['usuario']['nome'] ?? 'Sistema',
        $id
    ]);
    
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
