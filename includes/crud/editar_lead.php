<?php
// Definir codificação UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Definir header JSON
header('Content-Type: application/json; charset=utf-8');

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar permissões - Permitir que vendedores e representantes também possam editar leads
$usuario = $_SESSION['usuario'];
$perfil_permitido = in_array($usuario['perfil'], ['admin', 'vendedor', 'representante', 'supervisor', 'diretor']);

if (!$perfil_permitido) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Funcionalidade temporariamente indisponível']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Incluir conexão
require_once __DIR__ . '/../config/conexao.php';

try {
    // Obter dados do formulário
    $email_original = trim($_POST['email_original'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    // Normalizar placeholder vindos do front PRIMEIRO
    $email_original_normalizado = $email_original;
    if ($email_original === '-' || strtoupper($email_original) === 'N/A') {
        $email_original_normalizado = '';
    }

    // Identificador único recebido do front (para leads sem email e sem risco de colisão na página)
    $lead_uid_original = trim($_POST['lead_uid_original'] ?? '');

    // Identificação segura para leads sem email: exigir um UID enviado pelo front (gerado na lista)
    $telefone_digits = preg_replace('/\D+/', '', $telefone);
    $lead_key = '';
    if ($email_original_normalizado === '') {
        if ($lead_uid_original === '') {
            throw new Exception('Lead sem email: identificador único ausente. Recarregue a página e tente novamente.');
        }
        $lead_key = $lead_uid_original;
    }
    
    // Validações básicas
    // Se não houver email_original, exige pelo menos alguma informação para gerar chave
    if ($email_original_normalizado === '' && empty($lead_key)) {
        throw new Exception('Identificador insuficiente para a lead (sem email). Informe nome/telefone/endereço.');
    }
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    // Email editado é opcional. Se informado, precisa ser válido
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    if (empty($telefone)) {
        throw new Exception('Telefone é obrigatório');
    }
    
    // Resolver email_original de forma robusta: garantir que a coluna guarde SEMPRE o email da BASE_LEADS
    $email_original_resolvido = $email_original_normalizado;
    $lead_existe = false;
    if ($email_original_resolvido !== '') {
        $sql_verificar = "SELECT COUNT(*) as total FROM BASE_LEADS WHERE Email = ?";
        $stmt_verificar = $pdo->prepare($sql_verificar);
        $stmt_verificar->execute([$email_original_resolvido]);
        $lead_existe = $stmt_verificar->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }
    if (!$lead_existe) {
        // 1) Tentar recuperar por lead_uid_original a partir de uma edição anterior
        if (!empty($lead_uid_original)) {
            try {
                $stmt_recupera = $pdo->prepare("SELECT email_original FROM LEADS_EDICOES WHERE lead_key = ? AND email_original IS NOT NULL AND email_original != '' ORDER BY id ASC LIMIT 1");
                $stmt_recupera->execute([$lead_uid_original]);
                $email_prev = trim((string)$stmt_recupera->fetch(PDO::FETCH_COLUMN));
                if ($email_prev !== '') {
                    $email_original_resolvido = $email_prev;
                    $lead_existe = true;
                }
            } catch (PDOException $e) { /* silencioso */ }
        }
    }
    if (!$lead_existe) {
        // 2) Como fallback, se o novo email informado existir na base, usá-lo como original
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt_verificar2 = $pdo->prepare("SELECT COUNT(*) as total FROM BASE_LEADS WHERE Email = ?");
                $stmt_verificar2->execute([$email]);
                if (($stmt_verificar2->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0) {
                    $email_original_resolvido = $email;
                    $lead_existe = true;
                }
            } catch (PDOException $e) { /* silencioso */ }
        }
    }
    
    // Se ainda não existe, e havia email_original informado porém não encontrado, retornar erro
    if ($email_original_normalizado !== '' && !$lead_existe) {
        throw new Exception('Lead não encontrada');
    }
    
    // Verificar se o novo email já existe (se for diferente do original)
    if ($email !== $email_original_resolvido) {
        $sql_verificar_email = "SELECT COUNT(*) as total FROM BASE_LEADS WHERE Email = ? AND Email != ?";
        $stmt_verificar_email = $pdo->prepare($sql_verificar_email);
        $stmt_verificar_email->execute([$email, $email_original_resolvido]);
        $email_existe = $stmt_verificar_email->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        
        if ($email_existe) {
            throw new Exception('Este email já está sendo usado por outra lead');
        }
    }
    
    // Ao invés de atualizar diretamente a BASE_LEADS, 
    // vamos inserir/atualizar na tabela LEADS_EDICOES para preservar dados originais
    
    // Garantir coluna lead_key (idempotente)
    try {
        $pdo->exec("ALTER TABLE LEADS_EDICOES ADD COLUMN IF NOT EXISTS lead_key VARCHAR(64) NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lead_key ON LEADS_EDICOES (lead_key)");
    } catch (PDOException $e) {
        // Ignorar se não suportado; ambiente MySQL antigos não aceitam IF NOT EXISTS em ALTER
        try { $pdo->exec("ALTER TABLE LEADS_EDICOES ADD COLUMN lead_key VARCHAR(64) NULL"); } catch (PDOException $e2) {}
        try { $pdo->exec("CREATE INDEX idx_lead_key ON LEADS_EDICOES (lead_key)"); } catch (PDOException $e3) {}
    }

    // Verificar se já existe uma edição para este identificador (email ou lead_key)
    $sql_verificar_edicao = "SELECT id FROM LEADS_EDICOES WHERE (
                                (email_original = :email_original AND :email_original_chk = 1)

                             OR (:email_original_blank = 1 AND (email_original = '' OR email_original = '-') AND lead_key = :lead_key AND :lead_key_chk = 1)
                             OR (:lead_uid_chk = 1 AND lead_key = :lead_uid_original)
                            ) AND ativo = 1 LIMIT 1";
    $stmt_verificar_edicao = $pdo->prepare($sql_verificar_edicao);
    $stmt_verificar_edicao->execute([
        ':email_original' => $email_original,
        ':email_original_chk' => $email_original_normalizado !== '' ? 1 : 0,
        ':email_original_blank' => $email_original_normalizado === '' ? 1 : 0,
        ':lead_key' => $lead_key,
        ':lead_key_chk' => $lead_key !== '' ? 1 : 0,
        ':lead_uid_chk' => $lead_uid_original !== '' ? 1 : 0,
        ':lead_uid_original' => $lead_uid_original,
    ]);
    $row_existente = $stmt_verificar_edicao->fetch(PDO::FETCH_ASSOC);
    $edicao_existe = $row_existente && isset($row_existente['id']);
    
    if ($edicao_existe) {
        // Atualizar edição existente
        $sql_atualizar_edicao = "UPDATE LEADS_EDICOES SET 
                                 nome_editado = ?, 
                                 email_editado = ?, 
                                 telefone_editado = ?, 
                                 endereco_editado = ?, 
                                 usuario_editor = ?,
                                 lead_key = ?,
                                 data_edicao = NOW()
                                 WHERE id = ? AND ativo = 1";
        
        $stmt_edicao = $pdo->prepare($sql_atualizar_edicao);
        $resultado = $stmt_edicao->execute([
            $nome,
            $email,
            $telefone,
            $endereco,
            $usuario['email'],
            $lead_key,
            $row_existente['id']
        ]);
    } else {
        // Inserir nova edição (com data_edicao atual para preservar ordenação por mais recente)
        $sql_inserir_edicao = "INSERT INTO LEADS_EDICOES 
                               (email_original, lead_key, nome_editado, email_editado, telefone_editado, endereco_editado, usuario_editor, data_edicao) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt_edicao = $pdo->prepare($sql_inserir_edicao);
        $resultado = $stmt_edicao->execute([
            $email_original_resolvido,
            $lead_key,
            $nome,
            $email,
            $telefone,
            $endereco,
            $usuario['email']
        ]);
    }
    
    if ($resultado) {
        // Log da alteração (se a tabela existir)
        try {
            $sql_log = "INSERT INTO LOGS_ALTERACOES (usuario_email, acao, tabela, registro_original, registro_novo, data_alteracao) 
                        VALUES (?, 'EDITAR', 'BASE_LEADS', ?, ?, NOW())";
            $stmt_log = $pdo->prepare($sql_log);
            $stmt_log->execute([
                $usuario['email'],
                $email_original_resolvido,
                $email
            ]);
        } catch (PDOException $e) {
            // Ignorar erro se a tabela de logs não existir
            error_log("Tabela de logs não encontrada: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Lead atualizada com sucesso',
            'updated' => [
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'endereco' => $endereco,
                'lead_uid' => $lead_uid_original !== '' ? $lead_uid_original : null,
                'email_original' => $email_original_normalizado,
            ]
        ]);
    } else {
        throw new Exception('Erro ao atualizar lead');
    }
    
} catch (PDOException $e) {
    error_log("ERRO EDIÇÃO LEAD: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
