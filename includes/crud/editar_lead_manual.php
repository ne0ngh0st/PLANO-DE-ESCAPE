<?php
/**
 * Endpoint para editar lead manual
 */

require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$usuario = $_SESSION['usuario'];

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se é AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
    exit;
}

try {
    $lead_id = intval($_POST['lead_id'] ?? 0);
    
    if ($lead_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do lead inválido']);
        exit;
    }
    
    // Verificar se o lead existe e se o usuário tem permissão para editá-lo
    $stmt_check = $pdo->prepare("
        SELECT lm.*, u.COD_VENDEDOR as cod_vendedor_usuario 
        FROM LEADS_MANUAIS lm 
        LEFT JOIN USUARIOS u ON u.EMAIL = ? 
        WHERE lm.id = ? AND lm.status = 'ativo'
    ");
    $stmt_check->execute([$usuario['email'], $lead_id]);
    $lead = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo json_encode(['success' => false, 'message' => 'Lead não encontrado']);
        exit;
    }
    
    // Verificar permissões
    $pode_editar = false;
    $perfil = strtolower(trim($usuario['perfil']));
    
    if (in_array($perfil, ['admin', 'diretor'])) {
        $pode_editar = true;
    } elseif ($perfil === 'supervisor') {
        // Supervisor pode editar leads da sua equipe
        if ($lead['codigo_vendedor'] == $lead['cod_vendedor_usuario']) {
            $pode_editar = true;
        } else {
            // Verificar se o lead pertence à equipe do supervisor
            $stmt_equipe = $pdo->prepare("
                SELECT COUNT(*) FROM USUARIOS 
                WHERE COD_SUPER = ? AND COD_VENDEDOR = ? AND ATIVO = 1
            ");
            $stmt_equipe->execute([$lead['cod_vendedor_usuario'], $lead['codigo_vendedor']]);
            if ($stmt_equipe->fetchColumn() > 0) {
                $pode_editar = true;
            }
        }
    } elseif (in_array($perfil, ['vendedor', 'representante'])) {
        // Vendedor/representante só pode editar seus próprios leads
        if ($lead['codigo_vendedor'] == $lead['cod_vendedor_usuario']) {
            $pode_editar = true;
        }
    }
    
    if (!$pode_editar) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para editar este lead']);
        exit;
    }
    
    // Coletar dados do formulário
    $nome = trim($_POST['nome'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $inscricao_estadual = trim($_POST['inscricao_estadual'] ?? '');
    $segmento_atuacao = trim($_POST['segmento_atuacao'] ?? '');
    $valor_estimado = trim($_POST['valor_estimado'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações básicas
    if (empty($nome) || empty($email) || empty($telefone) || empty($endereco) || 
        empty($cep) || empty($bairro) || empty($municipio) || empty($estado) || empty($segmento_atuacao)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido']);
        exit;
    }
    
    // Atualizar o lead
    $stmt_update = $pdo->prepare("
        UPDATE LEADS_MANUAIS SET 
            nome = ?, razao_social = ?, nome_fantasia = ?, email = ?, telefone = ?,
            endereco = ?, complemento = ?, cep = ?, bairro = ?, municipio = ?, estado = ?,
            cnpj = ?, inscricao_estadual = ?, segmento_atuacao = ?, valor_estimado = ?,
            observacoes = ?, data_ultima_atualizacao = NOW()
        WHERE id = ?
    ");
    
    $valor_estimado_float = !empty($valor_estimado) ? floatval($valor_estimado) : null;
    
    $resultado = $stmt_update->execute([
        $nome, $razao_social, $nome_fantasia, $email, $telefone,
        $endereco, $complemento, $cep, $bairro, $municipio, $estado,
        $cnpj, $inscricao_estadual, $segmento_atuacao, $valor_estimado_float,
        $observacoes, $lead_id
    ]);
    
    if ($resultado) {
        echo json_encode(['success' => true, 'message' => 'Lead atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar lead']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao editar lead manual: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
} catch (Exception $e) {
    error_log("Erro geral ao editar lead manual: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
}
?>
