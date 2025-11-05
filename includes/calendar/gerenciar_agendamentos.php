<?php
// Desabilitar output buffer e garantir que headers sejam enviados primeiro
if (ob_get_level()) {
    ob_end_clean();
}

// Inicializar sessão e conexão usando o mesmo padrão das outras páginas
require_once __DIR__ . '/../config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Definir header JSON ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$action = $_POST['acao'] ?? $_GET['acao'] ?? '';

try {
    switch ($action) {
        case 'criar_agendamento':
            criarAgendamento($pdo, $usuario);
            break;
            
        case 'buscar_agendamentos':
            buscarAgendamentos($pdo, $usuario);
            break;
            
        case 'buscar_clientes':
            buscarClientes($pdo, $usuario);
            break;
            
        case 'filtrar_agendamentos':
            filtrarAgendamentos($pdo, $usuario);
            break;
            
        case 'alterar_status':
            alterarStatus($pdo, $usuario);
            break;
            
        case 'salvar_agendamento':
            salvarAgendamento($pdo, $usuario);
            break;
            
        case 'marcar_realizado':
            marcarComoRealizado($pdo, $usuario);
            break;
            
        case 'cancelar_agendamento':
            cancelarAgendamento($pdo, $usuario);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

function salvarAgendamento($pdo, $usuario) {
    $cliente_cnpj = $_POST['cliente'] ?? '';
    $data = $_POST['data'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $observacao = $_POST['observacao'] ?? '';
    
    if (empty($cliente_cnpj) || empty($data) || empty($hora)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos']);
        return;
    }
    
    // Verificar se a data não é anterior a hoje
    $data_agendamento = $data . ' ' . $hora;
    $hoje = date('Y-m-d H:i:s');
    
    if ($data_agendamento < $hoje) {
        echo json_encode(['success' => false, 'message' => 'Não é possível agendar para datas passadas']);
        return;
    }
    
    // Buscar nome do cliente pela raiz CNPJ
    $sql_cliente = "SELECT cliente FROM ultimo_faturamento WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ? LIMIT 1";
    $stmt_cliente = $pdo->prepare($sql_cliente);
    $stmt_cliente->execute([$cliente_cnpj]);
    $cliente_nome = $stmt_cliente->fetchColumn() ?: 'Cliente não encontrado';
    
    // Inserir agendamento
    $sql = "INSERT INTO agendamentos_ligacoes (usuario_id, cliente_cnpj, cliente_nome, data_agendamento, observacao, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'agendado', NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $usuario['id'],
        $cliente_cnpj,
        $cliente_nome,
        $data_agendamento,
        $observacao
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Agendamento salvo com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar agendamento']);
    }
}

function buscarAgendamentos($pdo, $usuario) {
    $filtro_status = $_GET['filtro_status'] ?? '';
    
    $perfil = strtolower(trim($usuario['perfil'] ?? ''));
    $where = [];
    $params = [];
    
    // Regras de visibilidade:
    // - vendedor/representante: apenas seus agendamentos
    // - supervisor: agendamentos da equipe (USUARIOS.COD_SUPER = seu código)
    // - diretor/admin: todos
    if ($perfil === 'admin' || $perfil === 'diretor') {
        // Sem restrição por usuário
    } elseif ($perfil === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $cod_supervisor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where[] = "usuario_id IN (SELECT id FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor','representante'))";
        $params[] = $cod_supervisor_formatado;
    } else {
        // Usar id do usuário se disponível, senão buscar por email
        if (isset($usuario['id']) && !empty($usuario['id'])) {
            $where[] = "usuario_id = ?";
            $params[] = $usuario['id'];
        } elseif (isset($usuario['email'])) {
            // Buscar ID do usuário pelo email
            $sql_user = "SELECT id FROM USUARIOS WHERE EMAIL = ? AND ATIVO = 1 LIMIT 1";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$usuario['email']]);
            $user_id = $stmt_user->fetchColumn();
            if ($user_id) {
                $where[] = "usuario_id = ?";
                $params[] = $user_id;
            } else {
                // Se não encontrou usuário, retornar vazio
                echo json_encode(['success' => true, 'agendamentos' => []]);
                return;
            }
        } else {
            // Se não tem nem id nem email, retornar vazio (não bloquear)
            error_log("AVISO buscarAgendamentos: Usuário sem id e email, retornando vazio");
            echo json_encode(['success' => true, 'agendamentos' => []]);
            return;
        }
    }
    
    if (!empty($filtro_status)) {
        $where[] = "status = ?";
        $params[] = $filtro_status;
    }
    
    $sql = "SELECT id, usuario_id, cliente_cnpj, cliente_nome, data_agendamento, observacao, status FROM agendamentos_ligacoes";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY data_agendamento ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados para exibição
    $agendamentos_formatados = [];
    foreach ($agendamentos as $agendamento) {
        $data_obj = new DateTime($agendamento['data_agendamento']);
        $agendamentos_formatados[] = [
            'id' => $agendamento['id'],
            'usuario_id' => $agendamento['usuario_id'],
            'cliente_cnpj' => $agendamento['cliente_cnpj'],
            'cliente_nome' => $agendamento['cliente_nome'],
            'data' => $data_obj->format('d/m/Y'),
            'hora' => $data_obj->format('H:i'),
            'observacao' => $agendamento['observacao'],
            'status' => $agendamento['status'],
            'data_agendamento' => $agendamento['data_agendamento']
        ];
    }
    
    echo json_encode(['success' => true, 'agendamentos' => $agendamentos_formatados]);
}

function marcarComoRealizado($pdo, $usuario) {
    $agendamento_id = $_POST['agendamento_id'] ?? '';
    
    if (empty($agendamento_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
        return;
    }
    
    // Verificar se o agendamento pertence ao usuário
    $sql_verificar = "SELECT id FROM agendamentos_ligacoes WHERE id = ? AND usuario_id = ?";
    $stmt_verificar = $pdo->prepare($sql_verificar);
    $stmt_verificar->execute([$agendamento_id, $usuario['id']]);
    
    if (!$stmt_verificar->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado ou não autorizado']);
        return;
    }
    
    // Atualizar status
    $sql = "UPDATE agendamentos_ligacoes SET status = 'realizado', updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$agendamento_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Agendamento marcado como realizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar agendamento']);
    }
}

function cancelarAgendamento($pdo, $usuario) {
    $agendamento_id = $_POST['agendamento_id'] ?? '';
    
    if (empty($agendamento_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
        return;
    }
    
    // Verificar se o agendamento pertence ao usuário
    $sql_verificar = "SELECT id FROM agendamentos_ligacoes WHERE id = ? AND usuario_id = ?";
    $stmt_verificar = $pdo->prepare($sql_verificar);
    $stmt_verificar->execute([$agendamento_id, $usuario['id']]);
    
    if (!$stmt_verificar->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado ou não autorizado']);
        return;
    }
    
    // Atualizar status
    $sql = "UPDATE agendamentos_ligacoes SET status = 'cancelado', updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$agendamento_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Agendamento cancelado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar agendamento']);
    }
}

function criarAgendamento($pdo, $usuario) {
    $cliente_cnpj = $_POST['cliente_cnpj'] ?? '';
    $data = $_POST['data'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $status = $_POST['status'] ?? 'agendado';
    $observacao = $_POST['observacao'] ?? '';
    
    if (empty($cliente_cnpj) || empty($data) || empty($hora)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos']);
        return;
    }
    
    // Buscar nome do cliente pela raiz CNPJ
    $sql_cliente = "SELECT cliente FROM ultimo_faturamento WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ? LIMIT 1";
    $stmt_cliente = $pdo->prepare($sql_cliente);
    $stmt_cliente->execute([$cliente_cnpj]);
    $cliente_nome = $stmt_cliente->fetchColumn() ?: 'Cliente não encontrado';
    
    // Combinar data e hora
    $data_agendamento = $data . ' ' . $hora . ':00';
    
    // Inserir agendamento
    $sql = "INSERT INTO agendamentos_ligacoes (usuario_id, cliente_cnpj, cliente_nome, data_agendamento, observacao, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $usuario['id'],
        $cliente_cnpj,
        $cliente_nome,
        $data_agendamento,
        $observacao,
        $status
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Agendamento criado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar agendamento']);
    }
}

function buscarClientes($pdo, $usuario) {
    $termo = $_GET['termo'] ?? '';
    
    // Buscar clientes da carteira do usuário com busca melhorada
    $sql = "SELECT DISTINCT 
                cnpj_representativo as cnpj, 
                cliente as nome,
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj
            FROM ultimo_faturamento 
            WHERE representante = ?";
    
    $params = [$usuario['nome']];
    
    // Se há termo de busca, adicionar filtros
    if (!empty($termo)) {
        $sql .= " AND (
            cliente LIKE ? OR 
            cnpj_representativo LIKE ? OR 
            SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) LIKE ?
        )";
        $termoBusca = "%{$termo}%";
        $params[] = $termoBusca;
        $params[] = $termoBusca;
        $params[] = $termoBusca;
    }
    
    $sql .= " ORDER BY cliente ASC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'clientes' => $clientes]);
}

function filtrarAgendamentos($pdo, $usuario) {
    $status = $_GET['status'] ?? '';
    $cliente = $_GET['cliente'] ?? '';
    
    $sql = "SELECT id, cliente_cnpj, cliente_nome, data_agendamento, observacao, status 
            FROM agendamentos_ligacoes 
            WHERE usuario_id = ?";
    
    $params = [$usuario['id']];
    
    if (!empty($status)) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    if (!empty($cliente)) {
        $sql .= " AND cliente_cnpj = ?";
        $params[] = $cliente;
    }
    
    $sql .= " ORDER BY data_agendamento ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'agendamentos' => $agendamentos]);
}

function alterarStatus($pdo, $usuario) {
    $agendamento_id = $_POST['agendamento_id'] ?? '';
    $novo_status = $_POST['novo_status'] ?? '';
    
    if (empty($agendamento_id) || empty($novo_status)) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        return;
    }
    
    // Verificar se o agendamento pertence ao usuário
    $sql_verificar = "SELECT id FROM agendamentos_ligacoes WHERE id = ? AND usuario_id = ?";
    $stmt_verificar = $pdo->prepare($sql_verificar);
    $stmt_verificar->execute([$agendamento_id, $usuario['id']]);
    
    if (!$stmt_verificar->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado ou não autorizado']);
        return;
    }
    
    // Atualizar status
    $sql = "UPDATE agendamentos_ligacoes SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$novo_status, $agendamento_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status alterado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao alterar status']);
    }
}
?>
