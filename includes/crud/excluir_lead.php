<?php
// Desabilitar output buffer e garantir que headers sejam enviados primeiro
if (ob_get_level()) {
    ob_end_clean();
}

// Definir header JSON ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Aceitar exclusão por email OU por lead_uid (para leads sem email)
$email_lead = trim($_POST['email'] ?? '');
$lead_uid   = trim($_POST['lead_uid'] ?? '');

if ($email_lead === '' && $lead_uid === '') {
    echo json_encode(['success' => false, 'message' => 'Identificador do lead não fornecido']);
    exit;
}

// Verificar se o motivo da exclusão foi fornecido
if (!isset($_POST['motivo_exclusao']) || empty($_POST['motivo_exclusao'])) {
    echo json_encode(['success' => false, 'message' => 'Motivo da exclusão é obrigatório']);
    exit;
}

$motivo_exclusao = $_POST['motivo_exclusao'];
$observacao_exclusao = $_POST['observacao_exclusao'] ?? '';

// Campos auxiliares (vindos do <tr> em leads.php) para ajudar a identificar quando não há email
$dataobs_post = trim($_POST['dataobs'] ?? '');
$uf_post = trim($_POST['uf'] ?? '');
$codigo_vendedor_post = trim($_POST['codigo_vendedor'] ?? '');
$telefone_post = trim($_POST['telefone'] ?? '');
$endereco_post = trim($_POST['endereco'] ?? '');
$nome_post = trim($_POST['nome'] ?? '');
// Normalizar placeholders
foreach ([&$uf_post, &$telefone_post, &$endereco_post, &$nome_post] as &$v) {
    if ($v === '-' || strtoupper($v) === 'N/A') { $v = ''; }
}

// Incluir arquivo de conexão
require_once __DIR__ . '/../config/conexao.php';

try {
    // Localizar lead por email (preferência) ou por assinatura (lead_uid)
    $lead = null;
    if ($email_lead !== '') {
        $stmt = $pdo->prepare("SELECT nomefinal, NOMEFANTASIA, RAZAOSOCIAL, Email, TelefonePrincipalFINAL, MARCAOPROSPECT, DATAOBS, UF, CodigoVendedor, `endereoCNPJJA` FROM `BASE_LEADS` WHERE Email = ? LIMIT 1");
        $stmt->execute([$email_lead]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$lead && $lead_uid !== '') {
        // Buscar candidatos usando apenas campos não editáveis para evitar divergência com edições
        $where = ["MARCAOPROSPECT = 'SAI PROSPECT'"];
        $params = [];
        if ($dataobs_post !== '') { $where[] = 'DATAOBS = ?'; $params[] = $dataobs_post; }
        if ($uf_post !== '') { $where[] = 'UF = ?'; $params[] = $uf_post; }
        if ($codigo_vendedor_post !== '') { $where[] = 'CAST(CodigoVendedor AS CHAR) = ?'; $params[] = $codigo_vendedor_post; }

        $sql_cands = 'SELECT nomefinal, NOMEFANTASIA, RAZAOSOCIAL, Email, TelefonePrincipalFINAL, MARCAOPROSPECT, DATAOBS, UF, CodigoVendedor, `endereoCNPJJA` FROM `BASE_LEADS`';
        if (!empty($where)) {
            $sql_cands .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql_cands .= ' ORDER BY DATAOBS DESC LIMIT 2000';
        $stmt = $pdo->prepare($sql_cands);
        $stmt->execute($params);
        $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidatos as $cand) {
            $nome_cand = $cand['nomefinal'] ?? '';
            if ($nome_cand === '' && !empty($cand['RAZAOSOCIAL'])) { $nome_cand = $cand['RAZAOSOCIAL']; }
            if ($nome_cand === '' && !empty($cand['NOMEFANTASIA'])) { $nome_cand = $cand['NOMEFANTASIA']; }

            $nome_base = trim($nome_cand);
            $razao_base = trim($cand['RAZAOSOCIAL'] ?? '');
            $fantasia_base = trim($cand['NOMEFANTASIA'] ?? '');
            $telefone_base = preg_replace('/\D+/', '', $cand['TelefonePrincipalFINAL'] ?? '');
            $endereco_base = trim($cand['endereoCNPJJA'] ?? '');
            $uf_base = trim($cand['UF'] ?? '');
            $vend_base = trim((string)($cand['CodigoVendedor'] ?? ''));
            $dataobs_base = trim($cand['DATAOBS'] ?? '');

            $payload = json_encode([
                'nome' => mb_strtolower($nome_base),
                'razao' => mb_strtolower($razao_base),
                'fantasia' => mb_strtolower($fantasia_base),
                'tel' => $telefone_base,
                'end' => mb_strtolower($endereco_base),
                'uf' => mb_strtolower($uf_base),
                'vend' => $vend_base,
                'dataobs' => $dataobs_base,
            ], JSON_UNESCAPED_UNICODE);
            $hash = sha1($payload);
            if (hash_equals($lead_uid, $hash)) {
                $lead = $cand;
                break;
            }
        }

        // Se ainda não encontrou, fazer uma segunda varredura apenas pelo MARCAOPROSPECT (limite maior)
        if (!$lead) {
            $stmt = $pdo->prepare(
                "SELECT nomefinal, NOMEFANTASIA, RAZAOSOCIAL, Email, TelefonePrincipalFINAL, MARCAOPROSPECT, DATAOBS, UF, CodigoVendedor, `endereoCNPJJA`\n                 FROM `BASE_LEADS`\n                 WHERE MARCAOPROSPECT = 'SAI PROSPECT'\n                 ORDER BY DATAOBS DESC LIMIT 3000"
            );
            $stmt->execute();
            $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($candidatos as $cand) {
                $nome_cand = $cand['nomefinal'] ?? '';
                if ($nome_cand === '' && !empty($cand['RAZAOSOCIAL'])) { $nome_cand = $cand['RAZAOSOCIAL']; }
                if ($nome_cand === '' && !empty($cand['NOMEFANTASIA'])) { $nome_cand = $cand['NOMEFANTASIA']; }

                $nome_base = trim($nome_cand);
                $razao_base = trim($cand['RAZAOSOCIAL'] ?? '');
                $fantasia_base = trim($cand['NOMEFANTASIA'] ?? '');
                $telefone_base = preg_replace('/\D+/', '', $cand['TelefonePrincipalFINAL'] ?? '');
                $endereco_base = trim($cand['endereoCNPJJA'] ?? '');
                $uf_base = trim($cand['UF'] ?? '');
                $vend_base = trim((string)($cand['CodigoVendedor'] ?? ''));
                $dataobs_base = trim($cand['DATAOBS'] ?? '');

                $payload = json_encode([
                    'nome' => mb_strtolower($nome_base),
                    'razao' => mb_strtolower($razao_base),
                    'fantasia' => mb_strtolower($fantasia_base),
                    'tel' => $telefone_base,
                    'end' => mb_strtolower($endereco_base),
                    'uf' => mb_strtolower($uf_base),
                    'vend' => $vend_base,
                    'dataobs' => $dataobs_base,
                ], JSON_UNESCAPED_UNICODE);
                $hash = sha1($payload);
                if (hash_equals($lead_uid, $hash)) {
                    $lead = $cand;
                    break;
                }
            }
        }
    }

    if (!$lead) {
        // Fallback extra: tentar localizar por igualdade direta apenas com campos não editáveis
        if ($lead_uid !== '' && ($codigo_vendedor_post !== '' || $dataobs_post !== '' || $uf_post !== '')) {
            $whereEq = ["MARCAOPROSPECT = 'SAI PROSPECT'"];
            $paramsEq = [];
            if ($uf_post !== '') { $whereEq[] = 'UF = ?'; $paramsEq[] = $uf_post; }
            if ($codigo_vendedor_post !== '') { $whereEq[] = 'CAST(CodigoVendedor AS CHAR) = ?'; $paramsEq[] = $codigo_vendedor_post; }
            if ($dataobs_post !== '') { $whereEq[] = 'DATAOBS = ?'; $paramsEq[] = $dataobs_post; }

            $sqlEq = 'SELECT nomefinal, NOMEFANTASIA, RAZAOSOCIAL, Email, TelefonePrincipalFINAL, MARCAOPROSPECT, DATAOBS, UF, CodigoVendedor, `endereoCNPJJA` FROM `BASE_LEADS` WHERE ' . implode(' AND ', $whereEq) . ' ORDER BY DATAOBS DESC LIMIT 1000';
            $stmtEq = $pdo->prepare($sqlEq);
            $stmtEq->execute($paramsEq);
            $candsEq = $stmtEq->fetchAll(PDO::FETCH_ASSOC);
            foreach ($candsEq as $cand) {
                $nome_cand = $cand['nomefinal'] ?? '';
                if ($nome_cand === '' && !empty($cand['RAZAOSOCIAL'])) { $nome_cand = $cand['RAZAOSOCIAL']; }
                if ($nome_cand === '' && !empty($cand['NOMEFANTASIA'])) { $nome_cand = $cand['NOMEFANTASIA']; }

                $nome_base = trim($nome_cand);
                $razao_base = trim($cand['RAZAOSOCIAL'] ?? '');
                $fantasia_base = trim($cand['NOMEFANTASIA'] ?? '');
                $telefone_base = preg_replace('/\D+/', '', $cand['TelefonePrincipalFINAL'] ?? '');
                $endereco_base = trim($cand['endereoCNPJJA'] ?? '');
                $uf_base = trim($cand['UF'] ?? '');
                $vend_base = trim((string)($cand['CodigoVendedor'] ?? ''));
                $dataobs_base = trim($cand['DATAOBS'] ?? '');

                $payload = json_encode([
                    'nome' => mb_strtolower($nome_base),
                    'razao' => mb_strtolower($razao_base),
                    'fantasia' => mb_strtolower($fantasia_base),
                    'tel' => $telefone_base,
                    'end' => mb_strtolower($endereco_base),
                    'uf' => mb_strtolower($uf_base),
                    'vend' => $vend_base,
                    'dataobs' => $dataobs_base,
                ], JSON_UNESCAPED_UNICODE);
                $hash = sha1($payload);
                if (hash_equals($lead_uid, $hash)) {
                    $lead = $cand;
                    break;
                }
            }
        }
    }

    if (!$lead) {
        echo json_encode(['success' => false, 'message' => 'Lead não encontrado']);
        exit;
    }

    // Iniciar transação
    $pdo->beginTransaction();
    try {
        // 1) Inserir em leads_excluidos
        $stmt = $pdo->prepare(
            "INSERT INTO leads_excluidos (nome_fantasia, razao_social, email, telefone, marcao_prospect, data_cadastro, data_exclusao, usuario_exclusao, motivo_exclusao, observacao_exclusao)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)"
        );
        $stmt->execute([
            $lead['nomefinal'] ?? $lead['NOMEFANTASIA'] ?? $lead['RAZAOSOCIAL'] ?? 'Lead sem nome',
            $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? 'N/A',
            $lead['Email'] ?? '',
            $lead['TelefonePrincipalFINAL'] ?? 'N/A',
            $lead['MARCAOPROSPECT'] ?? 'SAI PROSPECT',
            $lead['DATAOBS'] ?? '',
            $_SESSION['usuario']['id'] ?? 1,
            $motivo_exclusao,
            $observacao_exclusao
        ]);

        // 2) Remover da BASE_LEADS
        if (!empty($lead['Email'])) {
            $stmt = $pdo->prepare('DELETE FROM `BASE_LEADS` WHERE Email = ?');
            $delete_result = $stmt->execute([$lead['Email']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM `BASE_LEADS` WHERE 
                COALESCE(nomefinal, '') = ? AND 
                COALESCE(NOMEFANTASIA, '') = ? AND 
                COALESCE(RAZAOSOCIAL, '') = ? AND 
                COALESCE(REPLACE(REPLACE(REPLACE(TelefonePrincipalFINAL, '(', ''), ')', ''), '-', ''), '') = ? AND 
                COALESCE(`endereoCNPJJA`, '') = ? AND 
                COALESCE(UF, '') = ? AND 
                CAST(COALESCE(CodigoVendedor, '') AS CHAR) = ? AND 
                COALESCE(DATAOBS, '') = ?");
            $delete_result = $stmt->execute([
                $lead['nomefinal'] ?? '',
                $lead['NOMEFANTASIA'] ?? '',
                $lead['RAZAOSOCIAL'] ?? '',
                preg_replace('/\D+/', '', $lead['TelefonePrincipalFINAL'] ?? ''),
                $lead['endereoCNPJJA'] ?? '',
                $lead['UF'] ?? '',
                (string)($lead['CodigoVendedor'] ?? ''),
                $lead['DATAOBS'] ?? ''
            ]);
        }

        if ($delete_result) {
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Lead movido para exclusão com sucesso']);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir lead da tabela principal']);
        }
    } catch (PDOException $e) {
        // Fallback: commit para marcar exclusão lógica
        $errorCode = $e->getCode();
        $errorMsg = $e->getMessage();
        $errorInfo = isset($stmt) ? $stmt->errorInfo() : [];
        $permissionDenied = strpos($errorMsg, 'DELETE command denied') !== false || $errorCode === '42000' || (isset($errorInfo[1]) && (int)$errorInfo[1] === 1142);
        if ($permissionDenied) {
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Lead marcado como excluído (exclusão lógica). O lead não aparecerá mais nas listagens.']);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir lead: ' . $errorMsg]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>