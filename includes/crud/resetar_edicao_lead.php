<?php
// Definir codificação UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$perfil_permitido = in_array($usuario['perfil'], ['admin', 'vendedor', 'representante', 'supervisor', 'diretor']);
if (!$perfil_permitido) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

require_once 'conexao.php';

try {
    $email_original = trim($_POST['email_original'] ?? '');
    $lead_uid_original = trim($_POST['lead_uid_original'] ?? '');

    // Normalizar placeholders
    if ($email_original === '-' || strtoupper($email_original) === 'N/A') {
        $email_original = '';
    }

    if ($email_original === '' && $lead_uid_original === '') {
        throw new Exception('Identificador ausente.');
    }

    // Garantir tabela de arquivo (histórico) para armazenar edições removidas
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS LEADS_EDICOES_ARQUIVO (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email_original VARCHAR(255) NULL,
            lead_key VARCHAR(64) NULL,
            nome_editado VARCHAR(255) NULL,
            email_editado VARCHAR(255) NULL,
            telefone_editado VARCHAR(100) NULL,
            endereco_editado TEXT NULL,
            usuario_editor VARCHAR(255) NULL,
            data_edicao DATETIME NULL,
            ativo TINYINT(1) DEFAULT 1,
            arquivado_por VARCHAR(255) NOT NULL,
            data_arquivo DATETIME NOT NULL,
            INDEX idx_arq_email (email_original),
            INDEX idx_arq_leadkey (lead_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        // Se falhar a criação, ainda tentaremos seguir com o update/delete tradicional
    }

    // Mover as edições para a tabela de arquivo e remover da tabela principal
    if ($email_original !== '') {
        // Inserir no arquivo
        $sql_ins = "INSERT INTO LEADS_EDICOES_ARQUIVO (
                        email_original, lead_key, nome_editado, email_editado, telefone_editado, endereco_editado,
                        usuario_editor, data_edicao, ativo, arquivado_por, data_arquivo
                    )
                    SELECT email_original, lead_key, nome_editado, email_editado, telefone_editado, endereco_editado,
                           usuario_editor, data_edicao, ativo, ?, NOW()
                    FROM LEADS_EDICOES WHERE email_original = ?";
        $stmtIns = $pdo->prepare($sql_ins);
        $stmtIns->execute([$usuario['email'], $email_original]);
        $afetados = $stmtIns->rowCount();

        // Remover da principal
        $stmtDel = $pdo->prepare("DELETE FROM LEADS_EDICOES WHERE email_original = ?");
        $stmtDel->execute([$email_original]);
    } else {
        $sql_ins = "INSERT INTO LEADS_EDICOES_ARQUIVO (
                        email_original, lead_key, nome_editado, email_editado, telefone_editado, endereco_editado,
                        usuario_editor, data_edicao, ativo, arquivado_por, data_arquivo
                    )
                    SELECT email_original, lead_key, nome_editado, email_editado, telefone_editado, endereco_editado,
                           usuario_editor, data_edicao, ativo, ?, NOW()
                    FROM LEADS_EDICOES WHERE lead_key = ?";
        $stmtIns = $pdo->prepare($sql_ins);
        $stmtIns->execute([$usuario['email'], $lead_uid_original]);
        $afetados = $stmtIns->rowCount();

        $stmtDel = $pdo->prepare("DELETE FROM LEADS_EDICOES WHERE lead_key = ?");
        $stmtDel->execute([$lead_uid_original]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Edição resetada com sucesso',
        'rows_affected' => (int)$afetados,
    ]);
} catch (PDOException $e) {
    error_log('ERRO RESET EDIÇÃO LEAD: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


