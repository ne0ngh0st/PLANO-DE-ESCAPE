<?php
// PREVENIR QUALQUER OUTPUT ANTES DO JSON
ob_start();

// Configurar headers para JSON ANTES DE QUALQUER COISA
header('Content-Type: application/json; charset=utf-8');

// Desabilitar exibição de erros para não quebrar o JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // IMPORTANTE: Usar config.php que já tem toda configuração de sessão correta
    // Isso garante que a sessão seja configurada da mesma forma que o resto do sistema
    // Capturar qualquer output gerado pelos includes
    ob_start();
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../security/TokenSecurity.php';
    require_once __DIR__ . '/../config/aprovacao.php';
    $output = ob_get_clean();
    
    // Se algum include gerou output, logar e limpar
    if (!empty($output)) {
        error_log("AVISO: includes geraram output: " . substr($output, 0, 200));
    }
    
    // Garantir header JSON e limpar qualquer output residual
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8', true);
    
    // Verificar se o usuário está logado
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        ob_clean();
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Sessão expirada. Por favor, faça login novamente.', 
            'session_expired' => true
        ]);
        exit;
    }
    
    // Verificar se a conexão foi estabelecida (config.php já inclui conexao.php)
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Erro ao conectar ao banco de dados');
    }
    
    // Limpar buffer final antes de processar
    ob_end_clean();
    
} catch (Exception $e) {
    ob_clean();
    error_log("Erro na configuração orcamentos_ajax: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['success' => false, 'message' => 'Erro de configuração: ' . $e->getMessage()]);
    exit;
}

$usuario = $_SESSION['usuario'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Inicializar classe de segurança
$tokenSecurity = new TokenSecurity($pdo);

try {
    switch ($action) {
        case 'buscar':
            buscarOrcamento($pdo, $_GET['id'] ?? '');
            break;
            
        case 'criar':
            criarOrcamento($pdo, $_POST, $usuario);
            break;
            
        case 'editar':
            editarOrcamento($pdo, $_POST, $usuario);
            break;
            
        case 'excluir':
            excluirOrcamento($pdo, $_POST['id'], $usuario);
            break;
            
        case 'aprovar':
            aprovarOrcamento($pdo, $_POST['id'], $usuario);
            break;
            
        case 'rejeitar':
            rejeitarOrcamento($pdo, $_POST['id'], $usuario);
            break;
            
        case 'gerar_link_aprovacao':
            gerarLinkAprovacao($pdo, $_POST['id'], $usuario);
            break;
            
        case 'enviar_email_aprovacao':
            enviarEmailAprovacao($pdo, $_POST['id'], $usuario);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    // Limpar qualquer output antes de retornar erro
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log("Erro em orcamentos_ajax.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    // Limpar qualquer output antes de retornar erro
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log("Erro fatal em orcamentos_ajax.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['success' => false, 'message' => 'Erro fatal: ' . $e->getMessage()]);
    exit;
}

function buscarOrcamento($pdo, $id) {
    if (empty($id)) {
        throw new Exception('ID do orçamento não fornecido');
    }
    
    $sql = "SELECT * FROM ORCAMENTOS WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
    
    echo json_encode(['success' => true, 'data' => $orcamento]);
}

function criarOrcamento($pdo, $dados, $usuario) {
    // Limpar qualquer output antes de começar
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log para debug
    error_log("DEBUG criarOrcamento - Dados recebidos: " . print_r($dados, true));
    
    // Validar dados obrigatórios
    $campos_obrigatorios = ['cliente_nome', 'tipo_produto_servico', 'produto_servico', 'valor_total', 'codigo_vendedor', 'forma_pagamento'];
    $campos_faltando = [];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($dados[$campo])) {
            $campos_faltando[] = $campo;
        }
    }
    
    if (!empty($campos_faltando)) {
        $campos_str = implode(', ', $campos_faltando);
        throw new Exception("Campos obrigatórios não preenchidos: $campos_str");
    }
    
    // Gerar token de aprovação único
    $token_aprovacao = bin2hex(random_bytes(32));
    
    // Processar itens_orcamento se for string JSON
    $itens_orcamento = $dados['itens_orcamento'] ?? null;
    if (is_string($itens_orcamento)) {
        // Tentar decodificar se for JSON string
        $decoded = json_decode($itens_orcamento, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $itens_orcamento = $decoded;
        }
        // Se não for JSON válido, manter como string
    }
    
    $sql = "INSERT INTO ORCAMENTOS (
        cliente_nome, cliente_cnpj, cliente_email, cliente_telefone, tipo_produto_servico,
        produto_servico, descricao, valor_total, status, forma_pagamento, tipo_faturamento,
        itens_orcamento, data_validade, codigo_vendedor, observacoes,
        variacao_produtos, prazo_producao, garantia_imagem, texto_importante,
        usuario_criador, token_aprovacao
    ) VALUES (
        :cliente_nome, :cliente_cnpj, :cliente_email, :cliente_telefone, :tipo_produto_servico,
        :produto_servico, :descricao, :valor_total, :status, :forma_pagamento, :tipo_faturamento,
        :itens_orcamento, :data_validade, :codigo_vendedor, :observacoes,
        :variacao_produtos, :prazo_producao, :garantia_imagem, :texto_importante,
        :usuario_criador, :token_aprovacao
    )";
    
    $params = [
        'cliente_nome' => $dados['cliente_nome'],
        'cliente_cnpj' => $dados['cliente_cnpj'] ?? null,
        'cliente_email' => $dados['cliente_email'] ?? null,
        'cliente_telefone' => $dados['cliente_telefone'] ?? null,
        'tipo_produto_servico' => $dados['tipo_produto_servico'],
        'produto_servico' => $dados['produto_servico'],
        'descricao' => $dados['descricao'] ?? null,
        'valor_total' => floatval($dados['valor_total']),
        'status' => $dados['status'] ?? 'pendente',
        'forma_pagamento' => $dados['forma_pagamento'],
        'tipo_faturamento' => $dados['tipo_faturamento'] ?? null,
        'itens_orcamento' => is_array($itens_orcamento) ? json_encode($itens_orcamento, JSON_UNESCAPED_UNICODE) : $itens_orcamento,
        'data_validade' => !empty($dados['data_validade']) ? $dados['data_validade'] : null,
        'codigo_vendedor' => $dados['codigo_vendedor'],
        'observacoes' => $dados['observacoes'] ?? null,
        'variacao_produtos' => $dados['variacao_produtos'] ?? '(+ ou - 10% da quantidade produzida)',
        'prazo_producao' => $dados['prazo_producao'] ?? '10 dias após a aprovação do LAYOUT',
        'garantia_imagem' => $dados['garantia_imagem'] ?? '5 anos',
        'texto_importante' => $dados['texto_importante'] ?? 'Os preços são válidos para 05 dias, podendo ser realinhados até o fechamento do mesmo em detrimento de forte desequilíbrio econômico, e/ou de aumentos acima das expectativas habituais de insumos e matéria prima.',
        'usuario_criador' => $usuario['nome'] ?? 'Sistema',
        'token_aprovacao' => $token_aprovacao
    ];
    
    // Log dos parâmetros antes da execução
    error_log("DEBUG criarOrcamento - Parâmetros: " . print_r($params, true));
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $orcamento_id = $pdo->lastInsertId();
            
            // Garantir que não há output antes do JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8', true);
            
            $response = [
                'success' => true, 
                'message' => 'Orçamento criado com sucesso!',
                'id' => $orcamento_id
            ];
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit; // Garantir que nada mais é executado
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Erro ao executar SQL: " . print_r($errorInfo, true));
            throw new Exception('Erro ao criar orçamento: ' . ($errorInfo[2] ?? 'Erro desconhecido'));
        }
    } catch (PDOException $e) {
        error_log("Erro PDO ao criar orçamento: " . $e->getMessage());
        throw new Exception('Erro ao salvar no banco de dados: ' . $e->getMessage());
    }
}

function editarOrcamento($pdo, $dados, $usuario) {
    if (empty($dados['id'])) {
        throw new Exception('ID do orçamento não fornecido');
    }
    
    // Verificar se o orçamento existe
    $sql_check = "SELECT id FROM ORCAMENTOS WHERE id = :id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['id' => $dados['id']]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Orçamento não encontrado');
    }
    
    $sql = "UPDATE ORCAMENTOS SET 
        cliente_nome = :cliente_nome,
        cliente_cnpj = :cliente_cnpj,
        cliente_email = :cliente_email,
        cliente_telefone = :cliente_telefone,
        tipo_produto_servico = :tipo_produto_servico,
        produto_servico = :produto_servico,
        descricao = :descricao,
        valor_total = :valor_total,
        status = :status,
        forma_pagamento = :forma_pagamento,
        tipo_faturamento = :tipo_faturamento,
        itens_orcamento = :itens_orcamento,
        data_validade = :data_validade,
        codigo_vendedor = :codigo_vendedor,
        observacoes = :observacoes,
        variacao_produtos = :variacao_produtos,
        prazo_producao = :prazo_producao,
        garantia_imagem = :garantia_imagem,
        texto_importante = :texto_importante,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'id' => $dados['id'],
        'cliente_nome' => $dados['cliente_nome'],
        'cliente_cnpj' => $dados['cliente_cnpj'] ?? null,
        'cliente_email' => $dados['cliente_email'] ?? null,
        'cliente_telefone' => $dados['cliente_telefone'] ?? null,
        'tipo_produto_servico' => $dados['tipo_produto_servico'],
        'produto_servico' => $dados['produto_servico'],
        'descricao' => $dados['descricao'] ?? null,
        'valor_total' => $dados['valor_total'],
        'status' => $dados['status'] ?? 'pendente',
        'forma_pagamento' => $dados['forma_pagamento'],
        'tipo_faturamento' => $dados['tipo_faturamento'] ?? null,
        'itens_orcamento' => $dados['itens_orcamento'] ?? null,
        'data_validade' => $dados['data_validade'] ?? null,
        'codigo_vendedor' => $dados['codigo_vendedor'],
        'observacoes' => $dados['observacoes'] ?? null,
        'variacao_produtos' => $dados['variacao_produtos'] ?? '(+ ou - 10% da quantidade produzida)',
        'prazo_producao' => $dados['prazo_producao'] ?? '10 dias após a aprovação do LAYOUT',
        'garantia_imagem' => $dados['garantia_imagem'] ?? '5 anos',
        'texto_importante' => $dados['texto_importante'] ?? 'Os preços são válidos para 05 dias, podendo ser realinhados até o fechamento do mesmo em detrimento de forte desequilíbrio econômico, e/ou de aumentos acima das expectativas habituais de insumos e matéria prima.'
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Orçamento atualizado com sucesso!']);
    } else {
        throw new Exception('Erro ao atualizar orçamento');
    }
}

function excluirOrcamento($pdo, $id, $usuario) {
    // Garantir que não há output antes do JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (empty($id)) {
        throw new Exception('ID do orçamento não fornecido');
    }
    
    // Verificar permissões (apenas admin e diretor podem excluir)
    $perfil_usuario = strtolower(trim($usuario['perfil']));
    if (!in_array($perfil_usuario, ['admin', 'diretor'])) {
        throw new Exception('Você não tem permissão para excluir orçamentos');
    }
    
    $sql = "DELETE FROM ORCAMENTOS WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute(['id' => $id]);
        
        if ($result) {
            // Verificar se realmente excluiu algum registro
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected === 0) {
                throw new Exception('Orçamento não encontrado ou já foi excluído');
            }
            
            // Limpar qualquer output antes de retornar JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Garantir header JSON
            header('Content-Type: application/json; charset=utf-8', true);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Orçamento excluído com sucesso!'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit; // Garantir que nada mais é executado após retornar JSON
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Erro ao executar SQL de exclusão: " . print_r($errorInfo, true));
            throw new Exception('Erro ao excluir orçamento: ' . ($errorInfo[2] ?? 'Erro desconhecido'));
        }
    } catch (PDOException $e) {
        error_log("Erro PDO ao excluir orçamento: " . $e->getMessage());
        throw new Exception('Erro ao excluir no banco de dados: ' . $e->getMessage());
    }
}

function aprovarOrcamento($pdo, $id, $usuario) {
    // Garantir que não há output antes do JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (empty($id)) {
        throw new Exception('ID do orçamento não fornecido');
    }
    
    // Verificar permissões (admin, diretor e supervisor podem aprovar)
    $perfil_usuario = strtolower(trim($usuario['perfil']));
    if (!in_array($perfil_usuario, ['admin', 'diretor', 'supervisor'])) {
        throw new Exception('Você não tem permissão para aprovar orçamentos');
    }
    
    $sql = "UPDATE ORCAMENTOS SET 
                status_gestor = 'aprovado', 
                data_aprovacao_gestor = CURRENT_TIMESTAMP, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute(['id' => $id]);
        
        if ($result) {
            // Verificar se realmente atualizou algum registro
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected === 0) {
                throw new Exception('Orçamento não encontrado ou já foi aprovado');
            }
            
            // Limpar qualquer output antes de retornar JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Garantir header JSON
            header('Content-Type: application/json; charset=utf-8', true);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Orçamento aprovado pelo gestor com sucesso!'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit; // Garantir que nada mais é executado após retornar JSON
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Erro ao executar SQL de aprovação: " . print_r($errorInfo, true));
            throw new Exception('Erro ao aprovar orçamento: ' . ($errorInfo[2] ?? 'Erro desconhecido'));
        }
    } catch (PDOException $e) {
        error_log("Erro PDO ao aprovar orçamento: " . $e->getMessage());
        throw new Exception('Erro ao salvar no banco de dados: ' . $e->getMessage());
    }
}

function rejeitarOrcamento($pdo, $id, $usuario) {
    // Garantir que não há output antes do JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    if (empty($id)) {
        throw new Exception('ID do orçamento não fornecido');
    }
    
    // Verificar permissões (admin, diretor e supervisor podem rejeitar)
    $perfil_usuario = strtolower(trim($usuario['perfil']));
    if (!in_array($perfil_usuario, ['admin', 'diretor', 'supervisor'])) {
        throw new Exception('Você não tem permissão para rejeitar orçamentos');
    }
    
    // Verificar se o motivo da recusa foi fornecido
    $motivo_recusa = $_POST['motivo_recusa'] ?? '';
    if (empty(trim($motivo_recusa))) {
        throw new Exception('Motivo da recusa é obrigatório');
    }
    
    $sql = "UPDATE ORCAMENTOS SET 
                status_gestor = 'rejeitado', 
                data_aprovacao_gestor = CURRENT_TIMESTAMP, 
                motivo_recusa = :motivo_recusa, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'id' => $id,
            'motivo_recusa' => trim($motivo_recusa)
        ]);
        
        if ($result) {
            // Limpar qualquer output antes de retornar JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Garantir header JSON
            header('Content-Type: application/json; charset=utf-8', true);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Orçamento rejeitado pelo gestor com sucesso!'
            ]);
            exit; // Garantir que nada mais é executado após retornar JSON
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Erro ao executar SQL de rejeição: " . print_r($errorInfo, true));
            throw new Exception('Erro ao rejeitar orçamento: ' . ($errorInfo[2] ?? 'Erro desconhecido'));
        }
    } catch (PDOException $e) {
        error_log("Erro PDO ao rejeitar orçamento: " . $e->getMessage());
        throw new Exception('Erro ao salvar no banco de dados: ' . $e->getMessage());
    }
}

function gerarLinkAprovacao($pdo, $id, $usuario) {
    global $tokenSecurity;
    
    // Garantir que não há output antes do JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verificar se a classe de segurança foi inicializada
    if (!isset($tokenSecurity)) {
        throw new Exception('Classe de segurança não inicializada');
    }
    
    if (empty($id)) {
        throw new Exception('ID do orçamento não fornecido');
    }
    
    // Buscar dados do orçamento
    $sql = "SELECT * FROM ORCAMENTOS WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
    
    // Gerar token seguro se não existir ou se expirou
    if (empty($orcamento['token_aprovacao']) || 
        ($orcamento['token_expires_at'] && strtotime($orcamento['token_expires_at']) < time())) {
        
        // Capturar qualquer output que possa ser gerado
        ob_start();
        try {
            $token_aprovacao = $tokenSecurity->createApprovalToken($id);
            $output = ob_get_clean();
            
            if (!empty($output)) {
                error_log("AVISO: TokenSecurity gerou output: " . substr($output, 0, 200));
            }
        } catch (Exception $e) {
            ob_end_clean();
            throw new Exception('Erro ao gerar token: ' . $e->getMessage());
        }
        
        if (!$token_aprovacao) {
            throw new Exception('Erro ao gerar token de aprovação');
        }
    } else {
        $token_aprovacao = $orcamento['token_aprovacao'];
    }
    
    // Gerar link de aprovação genérico (sem expor URL do site de gestão)
    $link = gerarUrlAprovacao($token_aprovacao);
    
    // Limpar qualquer output antes de retornar JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Garantir header JSON
    header('Content-Type: application/json; charset=utf-8', true);
    
    // Retornar resposta JSON
    echo json_encode([
        'success' => true,
        'message' => 'Link de aprovação gerado com sucesso!',
        'link' => $link,
        'orcamento' => $orcamento,
        'expires_at' => $orcamento['token_expires_at'] ?? date('Y-m-d H:i:s', strtotime('+7 days'))
    ]);
    exit; // Garantir que nada mais é executado após retornar JSON
}

function enviarEmailAprovacao($pdo, $id, $usuario) {
    if (empty($id)) {
        throw new Exception('ID do orçamento não fornecido');
    }
    
    // Buscar dados do orçamento
    $sql = "SELECT * FROM ORCAMENTOS WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado');
    }
    
    if (empty($orcamento['cliente_email'])) {
        throw new Exception('E-mail do cliente não informado');
    }
    
    // Gerar link se não existir
    if (empty($orcamento['token_aprovacao'])) {
        $token_aprovacao = bin2hex(random_bytes(32));
        $sql_update = "UPDATE ORCAMENTOS SET token_aprovacao = :token WHERE id = :id";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute(['token' => $token_aprovacao, 'id' => $id]);
        $orcamento['token_aprovacao'] = $token_aprovacao;
    }
    
    // Gerar link de aprovação genérico
    $link = gerarUrlAprovacao($orcamento['token_aprovacao']);
    
    // Configurar e-mail
    $para = $orcamento['cliente_email'];
    $assunto = "Orçamento #{$orcamento['id']} - Autopel";
    $mensagem = "
    <html>
    <head>
        <title>Orçamento para Aprovação</title>
    </head>
    <body>
        <h2>Orçamento para Aprovação</h2>
        <p>Olá {$orcamento['cliente_nome']},</p>
        <p>Você recebeu um orçamento para aprovação:</p>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3>Orçamento #{$orcamento['id']}</h3>
            <p><strong>Produto/Serviço:</strong> {$orcamento['produto_servico']}</p>
            <p><strong>Valor Total:</strong> R$ " . number_format($orcamento['valor_total'], 2, ',', '.') . "</p>
            <p><strong>Forma de Pagamento:</strong> " . ($orcamento['forma_pagamento'] === 'a_vista' ? 'À Vista' : '28 DDL') . "</p>
            <p><strong>Validade:</strong> " . ($orcamento['data_validade'] ? date('d/m/Y', strtotime($orcamento['data_validade'])) : 'Não definida') . "</p>
        </div>
        
        <p>Para visualizar e aprovar este orçamento, clique no link abaixo:</p>
        <p><a href='{$link}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Aprovar Orçamento</a></p>
        
        <p>Ou copie e cole este link no seu navegador:</p>
        <p style='word-break: break-all; color: #666;'>{$link}</p>
        
        <hr>
        <p><small>Este link expira em 7 dias. Se você não solicitou este orçamento, pode ignorar este e-mail.</small></p>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Autopel <' . APROVACAO_EMAIL_FROM . '>',
        'Reply-To: ' . APROVACAO_EMAIL_REPLY_TO,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $enviado = mail($para, $assunto, $mensagem, implode("\r\n", $headers));
    
    if ($enviado) {
        echo json_encode(['success' => true, 'message' => 'E-mail enviado com sucesso para ' . $orcamento['cliente_email']]);
    } else {
        throw new Exception('Erro ao enviar e-mail. Verifique as configurações do servidor.');
    }
}
?>