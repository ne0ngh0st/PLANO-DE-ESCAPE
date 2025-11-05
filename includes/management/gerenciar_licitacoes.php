<?php
require_once __DIR__ . '/../config/config.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$perfilUsuario = strtolower($usuario['perfil'] ?? '');

// Verificar permissões
$perfis_permitidos = ['admin', 'diretor', 'licitação'];
if (!in_array($perfilUsuario, $perfis_permitidos)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado - Perfil: ' . $perfilUsuario]);
    exit;
}

header('Content-Type: application/json');

try {
    $acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
    
    switch ($acao) {
        case 'listar_licitacoes':
            listarLicitacoes();
            break;
            
        case 'obter_estatisticas':
            obterEstatisticas();
            break;
            
        case 'obter_detalhes':
            obterDetalhes();
            break;
            
        case 'adicionar_licitacao':
            adicionarLicitacao();
            break;
            
        case 'editar_licitacao':
            editarLicitacao();
            break;
            
        case 'excluir_licitacao':
            excluirLicitacao();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Obtém o conjunto de colunas existentes para a tabela informada
 */
function obterColunasTabela($pdo, $tabela) {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$tabela]);
    $nomes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Normalizar para comparação case-insensitive
    $set = [];
    foreach ($nomes as $nome) {
        $set[strtoupper($nome)] = $nome; // manter o nome original como valor
    }
    return $set; // chave em upper, valor com o case real
}

/**
 * Escolhe a primeira coluna existente dentre candidatas e devolve o nome escapado com crase
 */
function escolherColuna($colunasDisponiveis, $candidatas) {
    foreach ($candidatas as $c) {
        $upper = strtoupper($c);
        if (isset($colunasDisponiveis[$upper])) {
            $real = $colunasDisponiveis[$upper];
            return "`$real`";
        }
    }
    return null;
}

function listarLicitacoes() {
    global $pdo;
    
    $filtro_orgao = $_POST['filtro_orgao'] ?? '';
    $filtro_status = $_POST['filtro_status'] ?? '';
    $filtro_tipo = $_POST['filtro_tipo'] ?? '';
    $busca = $_POST['busca'] ?? '';
    
    // Resolver colunas dinamicamente (repara mudanças de nomes)
    $colunas = obterColunasTabela($pdo, 'LICITACAO');
    $colOrgao = escolherColuna($colunas, ['ORGAO']);
    $colSigla = escolherColuna($colunas, ['SIGLA']);
    $colCnpj = escolherColuna($colunas, ['CNPJ']);
    $colGerenciador = escolherColuna($colunas, ['GERENCIADOR','GERENTE','GERENCIADOR_NOME']);
    $colTipo = escolherColuna($colunas, ['TIPO','TIPO_LICITACAO']);
    $colProduto = escolherColuna($colunas, ['PRODUTO','ITEM','DESCRICAO_PRODUTO']);
    $colStatus = escolherColuna($colunas, ['STATUS','SITUACAO']);
    $colValorAta = escolherColuna($colunas, ['VALOR_ATA','VALOR_GLOBAL_ATA','VALOR_GLOBAL']);
    $colValorConsumido = escolherColuna($colunas, ['VALOR_CONSUMIDO','VALOR_CONSUMIDO_ATA','VALOR_CONSUMIDO_TOTAL']);
    $colConsumoAtaPct = escolherColuna($colunas, ['CONSUMO_ATA_PERCENT','PERCENTUAL_CONSUMO_ATA','CONSUMO_ATA_PCT']);
    $colDiasRestantesAta = escolherColuna($colunas, ['DIAS_RESTANTES_ATA','DIAS_RESTANTES','DIAS_RESTANTES_ATA_DIAS']);

    $where_conditions = [];
    $params = [];
    
    if (!empty($filtro_orgao) && $colOrgao) {
        $where_conditions[] = "$colOrgao LIKE ?";
        $params[] = "%$filtro_orgao%";
    }
    
    if (!empty($filtro_status) && $colStatus) {
        $where_conditions[] = "$colStatus LIKE ?";
        $params[] = "%$filtro_status%";
    }
    
    if (!empty($filtro_tipo) && $colTipo) {
        $where_conditions[] = "$colTipo = ?";
        $params[] = $filtro_tipo;
    }
    
    if (!empty($busca)) {
        $camposBusca = [];
        foreach ([$colOrgao, $colSigla, $colCnpj, $colProduto, $colGerenciador] as $c) {
            if ($c) { $camposBusca[] = "$c LIKE ?"; $params[] = "%$busca%"; }
        }
        if (!empty($camposBusca)) {
            $where_conditions[] = '(' . implode(' OR ', $camposBusca) . ')';
        }
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Montar SELECT dinâmico com aliases esperados pelo frontend
    $select = [
        $colOrgao ? "$colOrgao AS ORGAO" : "NULL AS ORGAO",
        $colSigla ? "$colSigla AS SIGLA" : "NULL AS SIGLA",
        $colCnpj ? "$colCnpj AS CNPJ" : "NULL AS CNPJ",
        $colGerenciador ? "TRIM($colGerenciador) AS GERENCIADOR" : "NULL AS GERENCIADOR",
        $colTipo ? "$colTipo AS TIPO" : "NULL AS TIPO",
        $colProduto ? "$colProduto AS PRODUTO" : "NULL AS PRODUTO",
        $colStatus ? "$colStatus AS STATUS" : "NULL AS STATUS",
        $colValorAta ? "$colValorAta AS VALOR_ATA" : "NULL AS VALOR_ATA",
        $colValorConsumido ? "$colValorConsumido AS VALOR_CONSUMIDO" : "NULL AS VALOR_CONSUMIDO",
        $colConsumoAtaPct ? "$colConsumoAtaPct AS CONSUMO_ATA_PERCENT" : "NULL AS CONSUMO_ATA_PERCENT",
        $colDiasRestantesAta ? "$colDiasRestantesAta AS DIAS_RESTANTES_ATA" : "NULL AS DIAS_RESTANTES_ATA",
    ];
    
    $orderA = $colOrgao ?: '1';
    $orderB = $colProduto ?: '2';
    
    $sql = "SELECT " . implode(",\n                ", $select) . "\n            FROM LICITACAO\n            $where_clause\n            ORDER BY $orderA, $orderB";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $licitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $licitacoes]);
}

function obterEstatisticas() {
    global $pdo;
    
    $stats = [];
    
    // Resolver colunas
    $colunas = obterColunasTabela($pdo, 'LICITACAO');
    $colStatus = escolherColuna($colunas, ['STATUS','SITUACAO']);
    $colTipo = escolherColuna($colunas, ['TIPO','TIPO_LICITACAO']);
    $colValorGlobal = escolherColuna($colunas, ['VALOR_GLOBAL','VALOR_GLOBAL_ATA','VALOR_ATA']);
    $colOrgao = escolherColuna($colunas, ['ORGAO']);

    // Total de licitações
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM LICITACAO");
    $stats['total'] = $stmt->fetchColumn();
    
    // Por status
    if ($colStatus) {
        $stmt = $pdo->query("SELECT $colStatus AS STATUS, COUNT(*) as count FROM LICITACAO WHERE $colStatus IS NOT NULL GROUP BY $colStatus");
        $stats['por_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stats['por_status'] = [];
    }
    
    // Por tipo
    if ($colTipo) {
        $stmt = $pdo->query("SELECT $colTipo AS TIPO, COUNT(*) as count FROM LICITACAO WHERE $colTipo IS NOT NULL GROUP BY $colTipo");
        $stats['por_tipo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stats['por_tipo'] = [];
    }
    
    // Valor total global (valores já estão em formato decimal com ponto)
    if ($colValorGlobal) {
        $stmt = $pdo->query("SELECT SUM(
            CAST(
                REPLACE(REPLACE(TRIM($colValorGlobal), 'R$', ''), ' ', '') AS DECIMAL(18,2)
            )
        ) AS total_global
        FROM LICITACAO
        WHERE $colValorGlobal IS NOT NULL AND $colValorGlobal != ''");
        $stats['valor_total_ata'] = $stmt->fetchColumn() ?: 0;
    } else {
        $stats['valor_total_ata'] = 0;
    }
    
    // Órgãos únicos
    if ($colOrgao) {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT $colOrgao) as total_orgaos FROM LICITACAO WHERE $colOrgao IS NOT NULL");
        $stats['total_orgaos'] = $stmt->fetchColumn();
    } else {
        $stats['total_orgaos'] = 0;
    }
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

function obterDetalhes() {
    global $pdo;
    
    $orgao = $_POST['orgao'] ?? '';
    $produto = $_POST['produto'] ?? '';
    
    if (empty($orgao) || empty($produto)) {
        echo json_encode(['success' => false, 'message' => 'Órgão e produto são obrigatórios']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM LICITACAO WHERE ORGAO = ? AND PRODUTO = ?");
    $stmt->execute([$orgao, $produto]);
    $licitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licitacao) {
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $licitacao]);
}

function adicionarLicitacao() {
    global $pdo;
    
    // Validar campos obrigatórios
    $campos_obrigatorios = ['ORGAO', 'SIGLA', 'CNPJ', 'TIPO', 'PRODUTO'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            echo json_encode(['success' => false, 'message' => "Campo $campo é obrigatório"]);
            return;
        }
    }
    
    try {
        $sql = "INSERT INTO LICITACAO (
            ORGAO, SIGLA, CNPJ, GERENCIADOR, TIPO, PRODUTO, COD_CLIENT, GRUPO, TABELA,
            EDITAL_LICITACAO, NUMERO_ATA, DATA_INICIO_ATA, DATA_TERMINO_ATA, DIAS_RESTANTES_ATA,
            NUMERO_CONTRATO, DATA_INICIO_CONTRATO, DATA_TERMINO_CONTRATO, DIAS_RESTANTES_CONTRATO,
            NUMERO_ADITAMENTO, DATA_INICIO_ADITAMENTO, DATA_TERMINO_ADITAMENTO, DIAS_RESTANTES_ADITAMENTO,
            VALOR_ATA, SALDO_ATA, CONSUMO_ATA_PERCENT, VALOR_GLOBAL, VALOR_CONSUMIDO, TOTAL_CONTRATO,
            SALDO_CONTRATO, CONSUMO_CONTRATO_PERCENT, VALOR_ADITADO, VALOR_CONSUMIDO_ADITAMENTO,
            SALDO_ADITADO, STATUS
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['ORGAO'],
            $_POST['SIGLA'],
            $_POST['CNPJ'],
            $_POST['GERENCIADOR'] ?? null,
            $_POST['TIPO'],
            $_POST['PRODUTO'],
            $_POST['COD_CLIENT'] ?? null,
            $_POST['GRUPO'] ?? null,
            $_POST['TABELA'] ?? null,
            $_POST['EDITAL_LICITACAO'] ?? null,
            $_POST['NUMERO_ATA'] ?? null,
            $_POST['DATA_INICIO_ATA'] ?? null,
            $_POST['DATA_TERMINO_ATA'] ?? null,
            $_POST['DIAS_RESTANTES_ATA'] ?? null,
            $_POST['NUMERO_CONTRATO'] ?? null,
            $_POST['DATA_INICIO_CONTRATO'] ?? null,
            $_POST['DATA_TERMINO_CONTRATO'] ?? null,
            $_POST['DIAS_RESTANTES_CONTRATO'] ?? null,
            $_POST['NUMERO_ADITAMENTO'] ?? null,
            $_POST['DATA_INICIO_ADITAMENTO'] ?? null,
            $_POST['DATA_TERMINO_ADITAMENTO'] ?? null,
            $_POST['DIAS_RESTANTES_ADITAMENTO'] ?? null,
            $_POST['VALOR_ATA'] ?? null,
            $_POST['SALDO_ATA'] ?? null,
            $_POST['CONSUMO_ATA_PERCENT'] ?? null,
            $_POST['VALOR_GLOBAL'] ?? null,
            $_POST['VALOR_CONSUMIDO'] ?? null,
            $_POST['TOTAL_CONTRATO'] ?? null,
            $_POST['SALDO_CONTRATO'] ?? null,
            $_POST['CONSUMO_CONTRATO_PERCENT'] ?? null,
            $_POST['VALOR_ADITADO'] ?? null,
            $_POST['VALOR_CONSUMIDO_ADITAMENTO'] ?? null,
            $_POST['SALDO_ADITADO'] ?? null,
            $_POST['STATUS'] ?? 'Nova'
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Licitação adicionada com sucesso']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar licitação: ' . $e->getMessage()]);
    }
}

function editarLicitacao() {
    global $pdo;
    
    $orgao = $_POST['orgao_original'] ?? '';
    $produto = $_POST['produto_original'] ?? '';
    
    if (empty($orgao) || empty($produto)) {
        echo json_encode(['success' => false, 'message' => 'Órgão e produto originais são obrigatórios']);
        return;
    }
    
    try {
        $sql = "UPDATE LICITACAO SET 
            ORGAO = ?, SIGLA = ?, CNPJ = ?, GERENCIADOR = ?, TIPO = ?, PRODUTO = ?, 
            COD_CLIENT = ?, GRUPO = ?, TABELA = ?, EDITAL_LICITACAO = ?, NUMERO_ATA = ?, 
            DATA_INICIO_ATA = ?, DATA_TERMINO_ATA = ?, DIAS_RESTANTES_ATA = ?, 
            NUMERO_CONTRATO = ?, DATA_INICIO_CONTRATO = ?, DATA_TERMINO_CONTRATO = ?, 
            DIAS_RESTANTES_CONTRATO = ?, NUMERO_ADITAMENTO = ?, DATA_INICIO_ADITAMENTO = ?, 
            DATA_TERMINO_ADITAMENTO = ?, DIAS_RESTANTES_ADITAMENTO = ?, VALOR_ATA = ?, 
            SALDO_ATA = ?, CONSUMO_ATA_PERCENT = ?, VALOR_GLOBAL = ?, VALOR_CONSUMIDO = ?, 
            TOTAL_CONTRATO = ?, SALDO_CONTRATO = ?, CONSUMO_CONTRATO_PERCENT = ?, 
            VALOR_ADITADO = ?, VALOR_CONSUMIDO_ADITAMENTO = ?, SALDO_ADITADO = ?, STATUS = ?
            WHERE ORGAO = ? AND PRODUTO = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['ORGAO'],
            $_POST['SIGLA'],
            $_POST['CNPJ'],
            $_POST['GERENCIADOR'] ?? null,
            $_POST['TIPO'],
            $_POST['PRODUTO'],
            $_POST['COD_CLIENT'] ?? null,
            $_POST['GRUPO'] ?? null,
            $_POST['TABELA'] ?? null,
            $_POST['EDITAL_LICITACAO'] ?? null,
            $_POST['NUMERO_ATA'] ?? null,
            $_POST['DATA_INICIO_ATA'] ?? null,
            $_POST['DATA_TERMINO_ATA'] ?? null,
            $_POST['DIAS_RESTANTES_ATA'] ?? null,
            $_POST['NUMERO_CONTRATO'] ?? null,
            $_POST['DATA_INICIO_CONTRATO'] ?? null,
            $_POST['DATA_TERMINO_CONTRATO'] ?? null,
            $_POST['DIAS_RESTANTES_CONTRATO'] ?? null,
            $_POST['NUMERO_ADITAMENTO'] ?? null,
            $_POST['DATA_INICIO_ADITAMENTO'] ?? null,
            $_POST['DATA_TERMINO_ADITAMENTO'] ?? null,
            $_POST['DIAS_RESTANTES_ADITAMENTO'] ?? null,
            $_POST['VALOR_ATA'] ?? null,
            $_POST['SALDO_ATA'] ?? null,
            $_POST['CONSUMO_ATA_PERCENT'] ?? null,
            $_POST['VALOR_GLOBAL'] ?? null,
            $_POST['VALOR_CONSUMIDO'] ?? null,
            $_POST['TOTAL_CONTRATO'] ?? null,
            $_POST['SALDO_CONTRATO'] ?? null,
            $_POST['CONSUMO_CONTRATO_PERCENT'] ?? null,
            $_POST['VALOR_ADITADO'] ?? null,
            $_POST['VALOR_CONSUMIDO_ADITAMENTO'] ?? null,
            $_POST['SALDO_ADITADO'] ?? null,
            $_POST['STATUS'] ?? 'Atualizada',
            $orgao,
            $produto
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Licitação atualizada com sucesso']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar licitação: ' . $e->getMessage()]);
    }
}

function excluirLicitacao() {
    global $pdo;
    
    $orgao = $_POST['orgao'] ?? '';
    $produto = $_POST['produto'] ?? '';
    
    if (empty($orgao) || empty($produto)) {
        echo json_encode(['success' => false, 'message' => 'Órgão e produto são obrigatórios']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM LICITACAO WHERE ORGAO = ? AND PRODUTO = ?");
        $stmt->execute([$orgao, $produto]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Licitação excluída com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir licitação: ' . $e->getMessage()]);
    }
}
?>
