<?php
// Desabilitar output buffer e garantir que headers sejam enviados primeiro
if (ob_get_level()) {
    ob_end_clean();
}

session_start();

// Definir header JSON ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Incluir arquivo de conexão com tratamento de erros
try {
    require_once __DIR__ . '/../config/conexao.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao conectar com banco de dados']);
    exit;
}

$usuario = $_SESSION['usuario'];
$action = $_POST['acao'] ?? $_GET['acao'] ?? '';

try {
    switch ($action) {
        case 'criar_agendamento_lead':
            criarAgendamentoLead($pdo, $usuario);
            break;
            
        case 'buscar_agendamentos_leads':
            buscarAgendamentosLeads($pdo, $usuario);
            break;
            
        case 'marcar_realizado_lead':
            marcarComoRealizadoLead($pdo, $usuario);
            break;
            
        case 'cancelar_agendamento_lead':
            cancelarAgendamentoLead($pdo, $usuario);
            break;
            
        case 'buscar_lead':
            buscarLead($pdo, $usuario);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
} catch (PDOException $e) {
    error_log("ERRO PDO AGENDAMENTO LEADS: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("ERRO AGENDAMENTO LEADS: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

function criarAgendamentoLead($pdo, $usuario) {
    // Log para debug
    error_log("DEBUG criarAgendamentoLead - Usuário: " . print_r($usuario, true));
    
    // Obter ID do usuário
    $usuario_id = null;
    try {
        if (isset($usuario['id']) && !empty($usuario['id'])) {
            $usuario_id = intval($usuario['id']);
            error_log("DEBUG - Usuário ID da sessão: $usuario_id");
        } elseif (isset($usuario['email'])) {
            $sql_user = "SELECT id FROM USUARIOS WHERE EMAIL = ? AND ATIVO = 1 LIMIT 1";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$usuario['email']]);
            $usuario_id = $stmt_user->fetchColumn();
            error_log("DEBUG - Usuário ID buscado pelo email: " . ($usuario_id ?: 'não encontrado'));
        }
        
        // Se ainda não encontrou, tentar por COD_VENDEDOR
        if (!$usuario_id && isset($usuario['cod_vendedor'])) {
            $sql_user = "SELECT id FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1 LIMIT 1";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$usuario['cod_vendedor']]);
            $usuario_id = $stmt_user->fetchColumn();
            error_log("DEBUG - Usuário ID buscado pelo COD_VENDEDOR: " . ($usuario_id ?: 'não encontrado'));
        }
        
        if (!$usuario_id) {
            error_log("ERRO - Usuário não identificado. Dados da sessão: " . print_r($usuario, true));
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Usuário não identificado. Faça login novamente.']);
            return;
        }
    } catch (PDOException $e) {
        error_log("ERRO ao identificar usuário: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao identificar usuário: ' . $e->getMessage()]);
        return;
    }
    $lead_email = $_POST['lead_email'] ?? '';
    $lead_identificador = $_POST['lead_identificador'] ?? '';
    $lead_nome = $_POST['lead_nome'] ?? '';
    $lead_telefone = $_POST['lead_telefone'] ?? '';
    $data = $_POST['data'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $observacao = $_POST['observacao'] ?? '';
    
    // Exigir: nome, data, hora e pelo menos um identificador (email OU lead_identificador)
    if ((empty($lead_email) && empty($lead_identificador)) || empty($lead_nome) || empty($data) || empty($hora)) {
        echo json_encode(['success' => false, 'message' => 'Preencha nome, data, hora e email ou identificador']);
        return;
    }
    
    // Verificar se a data não é anterior a hoje
    $data_agendamento = $data . ' ' . $hora . ':00';
    $hoje = date('Y-m-d H:i:s');
    
    if ($data_agendamento < $hoje) {
        echo json_encode(['success' => false, 'message' => 'Não é possível agendar para datas passadas']);
        return;
    }
    
    // Verificar se o lead existe na base de dados quando houver email
    if (!empty($lead_email)) {
        try {
            $sql_verificar_lead = "SELECT COUNT(*) FROM BASE_LEADS WHERE Email = ? AND MARCAOPROSPECT = 'SAI PROSPECT'";
            $stmt_verificar = $pdo->prepare($sql_verificar_lead);
            $stmt_verificar->execute([$lead_email]);
            $lead_existe = $stmt_verificar->fetchColumn() > 0;
            if (!$lead_existe) {
                // Não bloquear se o lead não existir - pode ser um lead manual ou novo
                // Apenas avisar mas permitir o agendamento
                error_log("AVISO: Lead com email $lead_email não encontrado na BASE_LEADS, mas permitindo agendamento");
            }
        } catch (PDOException $e) {
            // Se houver erro na verificação, apenas logar mas continuar
            error_log("ERRO ao verificar lead: " . $e->getMessage());
        }
    }
    
    // Garantir coluna lead_identificador (para leads sem email)
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM agendamentos_leads LIKE 'lead_identificador'");
        $hasLeadIdentificador = $colCheck && $colCheck->fetch();
        if (!$hasLeadIdentificador) {
            $pdo->exec("ALTER TABLE agendamentos_leads ADD COLUMN lead_identificador VARCHAR(100) NULL, ADD INDEX idx_lead_identificador (lead_identificador)");
        }
    } catch (Exception $e) {
        // Silencioso: tabela pode não existir ainda, será criada abaixo
    }
    
    // Verificar se já existe agendamento para este lead na mesma data/hora
    if (!empty($lead_email)) {
        $sql_verificar_agendamento = "SELECT COUNT(*) FROM agendamentos_leads WHERE lead_email = ? AND data_agendamento = ? AND status = 'agendado'";
        $stmt_verificar_ag = $pdo->prepare($sql_verificar_agendamento);
        $stmt_verificar_ag->execute([$lead_email, $data_agendamento]);
        $agendamento_existe = $stmt_verificar_ag->fetchColumn() > 0;
    } else {
        $sql_verificar_agendamento = "SELECT COUNT(*) FROM agendamentos_leads WHERE lead_identificador = ? AND data_agendamento = ? AND status = 'agendado'";
        $stmt_verificar_ag = $pdo->prepare($sql_verificar_agendamento);
        $stmt_verificar_ag->execute([$lead_identificador, $data_agendamento]);
        $agendamento_existe = $stmt_verificar_ag->fetchColumn() > 0;
    }
    
    if ($agendamento_existe) {
        echo json_encode(['success' => false, 'message' => 'Já existe um agendamento para este lead nesta data/horário']);
        return;
    }
    
    // Criar tabela se não existir
    try {
        $sql_create_table = "CREATE TABLE IF NOT EXISTS agendamentos_leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            lead_email VARCHAR(255) NOT NULL DEFAULT '',
            lead_identificador VARCHAR(100) NULL,
            lead_nome VARCHAR(255) NOT NULL,
            lead_telefone VARCHAR(50),
            data_agendamento DATETIME NOT NULL,
            observacao TEXT,
            status ENUM('agendado', 'realizado', 'cancelado') DEFAULT 'agendado',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_usuario_id (usuario_id),
            INDEX idx_lead_email (lead_email),
            INDEX idx_lead_identificador (lead_identificador),
            INDEX idx_status (status),
            INDEX idx_data_agendamento (data_agendamento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql_create_table);
        error_log("DEBUG - Tabela agendamentos_leads verificada/criada com sucesso");
    } catch (PDOException $e) {
        error_log("ERRO ao criar tabela agendamentos_leads: " . $e->getMessage());
        // Continuar mesmo se falhar - a tabela pode já existir
    }
    
    // Normalizar valores vazios para evitar problemas
    $lead_email = $lead_email ?: '';
    $lead_identificador = $lead_identificador ?: null;
    
    // Inserir agendamento
    $sql = "INSERT INTO agendamentos_leads (usuario_id, lead_email, lead_identificador, lead_nome, lead_telefone, data_agendamento, observacao, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'agendado')";
    
    try {
        error_log("DEBUG - Tentando inserir agendamento com: usuario_id=$usuario_id, lead_email=$lead_email, lead_nome=$lead_nome");
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $usuario_id,
            $lead_email ?: '',
            $lead_identificador ?: null,
            $lead_nome,
            $lead_telefone ?: '',
            $data_agendamento,
            $observacao ?: ''
        ]);
        
        if ($result) {
            error_log("DEBUG - Agendamento inserido com sucesso. ID: " . $pdo->lastInsertId());
            echo json_encode(['success' => true, 'message' => 'Agendamento criado com sucesso']);
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("ERRO INSERT AGENDAMENTO LEADS: " . print_r($errorInfo, true));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao criar agendamento: ' . ($errorInfo[2] ?? 'Erro desconhecido')]);
        }
    } catch (PDOException $e) {
        error_log("ERRO PDO AGENDAMENTO LEADS: " . $e->getMessage());
        error_log("ERRO PDO - Código: " . $e->getCode());
        error_log("ERRO PDO - SQL: " . $sql);
        error_log("ERRO PDO - Parâmetros: usuario_id=$usuario_id, lead_email=$lead_email");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao criar agendamento: ' . $e->getMessage()]);
    }
}

function buscarAgendamentosLeads($pdo, $usuario) {
    $filtro_status = $_GET['filtro_status'] ?? '';
    
    $perfil = strtolower(trim($usuario['perfil'] ?? ''));
    $where = [];
    $params = [];
    
    // Verificar se a tabela existe
    $sql_table_exists = "SHOW TABLES LIKE 'agendamentos_leads'";
    $stmt_table = $pdo->query($sql_table_exists);
    if ($stmt_table->rowCount() == 0) {
        echo json_encode(['success' => true, 'agendamentos' => []]);
        return;
    }
    
    // Regras de visibilidade (mesmas da carteira)
    if ($perfil === 'admin' || $perfil === 'diretor') {
        // Sem restrição por usuário
    } elseif ($perfil === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $where[] = "usuario_id IN (SELECT id FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor','representante'))";
        $params[] = $usuario['cod_vendedor'];
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
            error_log("AVISO buscarAgendamentosLeads: Usuário sem id e email, retornando vazio");
            echo json_encode(['success' => true, 'agendamentos' => []]);
            return;
        }
    }
    
    if (!empty($filtro_status)) {
        $where[] = "status = ?";
        $params[] = $filtro_status;
    }
    
    $sql = "SELECT id, usuario_id, lead_email, lead_nome, lead_telefone, data_agendamento, observacao, status FROM agendamentos_leads";
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
            'lead_email' => $agendamento['lead_email'],
            'lead_nome' => $agendamento['lead_nome'],
            'lead_telefone' => $agendamento['lead_telefone'],
            'data' => $data_obj->format('d/m/Y'),
            'hora' => $data_obj->format('H:i'),
            'observacao' => $agendamento['observacao'],
            'status' => $agendamento['status'],
            'data_agendamento' => $agendamento['data_agendamento']
        ];
    }
    
    echo json_encode(['success' => true, 'agendamentos' => $agendamentos_formatados]);
}

function marcarComoRealizadoLead($pdo, $usuario) {
    $agendamento_id = $_POST['agendamento_id'] ?? '';
    
    if (empty($agendamento_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
        return;
    }
    
    // Verificar se o agendamento pertence ao usuário ou está na hierarquia
    $perfil = strtolower(trim($usuario['perfil'] ?? ''));
    $sql_verificar = "SELECT id FROM agendamentos_leads WHERE id = ?";
    $params_verificar = [$agendamento_id];
    
    if ($perfil !== 'admin' && $perfil !== 'diretor') {
        if ($perfil === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            $sql_verificar .= " AND usuario_id IN (SELECT id FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor','representante'))";
            $params_verificar[] = $usuario['cod_vendedor'];
        } else {
            $sql_verificar .= " AND usuario_id = ?";
            $params_verificar[] = $usuario['id'];
        }
    }
    
    $stmt_verificar = $pdo->prepare($sql_verificar);
    $stmt_verificar->execute($params_verificar);
    
    if (!$stmt_verificar->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado ou não autorizado']);
        return;
    }
    
    // Atualizar status
    $sql = "UPDATE agendamentos_leads SET status = 'realizado' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$agendamento_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Agendamento marcado como realizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar agendamento']);
    }
}

function cancelarAgendamentoLead($pdo, $usuario) {
    $agendamento_id = $_POST['agendamento_id'] ?? '';
    
    if (empty($agendamento_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
        return;
    }
    
    // Verificar se o agendamento pertence ao usuário ou está na hierarquia
    $perfil = strtolower(trim($usuario['perfil'] ?? ''));
    $sql_verificar = "SELECT id FROM agendamentos_leads WHERE id = ?";
    $params_verificar = [$agendamento_id];
    
    if ($perfil !== 'admin' && $perfil !== 'diretor') {
        if ($perfil === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            $sql_verificar .= " AND usuario_id IN (SELECT id FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor','representante'))";
            $params_verificar[] = $usuario['cod_vendedor'];
        } else {
            $sql_verificar .= " AND usuario_id = ?";
            $params_verificar[] = $usuario['id'];
        }
    }
    
    $stmt_verificar = $pdo->prepare($sql_verificar);
    $stmt_verificar->execute($params_verificar);
    
    if (!$stmt_verificar->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado ou não autorizado']);
        return;
    }
    
    // Atualizar status
    $sql = "UPDATE agendamentos_leads SET status = 'cancelado' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$agendamento_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Agendamento cancelado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar agendamento']);
    }
}

function buscarLead($pdo, $usuario) {
    $email = $_GET['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email não fornecido']);
        return;
    }
    
    // Buscar lead na tabela BASE_LEADS
    $sql = "SELECT nomefinal, RAZAOSOCIAL, NOMEFANTASIA, TelefonePrincipalFINAL 
            FROM BASE_LEADS 
            WHERE Email = ? AND MARCAOPROSPECT = 'SAI PROSPECT' 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lead) {
        $nome = $lead['nomefinal'] ?? $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? '';
        $telefone = $lead['TelefonePrincipalFINAL'] ?? '';
        
        echo json_encode([
            'success' => true,
            'lead' => [
                'nome' => $nome,
                'telefone' => $telefone
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lead não encontrado']);
    }
}
?>
