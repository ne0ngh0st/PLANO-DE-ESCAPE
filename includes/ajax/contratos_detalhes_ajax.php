<?php
// Suprimir warnings que podem corromper o JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if (!isset($_GET['gerenciador'])) {
    echo json_encode(['success' => false, 'message' => 'Gerenciador não informado']);
    exit;
}

$gerenciador = $_GET['gerenciador'];

// Verificar se deve mostrar licitações excluídas (mesmo parâmetro da página principal)
$mostrar_excluidas = isset($_GET['mostrar_excluidas']) && $_GET['mostrar_excluidas'] === '1';
$filtro_excluidas = !$mostrar_excluidas ? "AND STATUS != 'Excluído'" : '';

// Verificar se é a Renata Bryar (cod_vendedor: 010585) para aplicar restrição
$cod_vendedor = $_SESSION['usuario']['COD_VENDEDOR'] ?? $_SESSION['usuario']['cod_vendedor'] ?? '';
$eh_renata_bryar = ($cod_vendedor === '010585');

// Verificar se o usuário pode ver AVN, MGI e AMERICANAS (apenas Renata, Admin e Diretor)
$perfil_usuario = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
$pode_ver_avn_mgi = $eh_renata_bryar || in_array($perfil_usuario, ['admin', 'diretor']);

// Filtro adicional: apenas a Renata, Admin e Diretor podem ver registros que ela criou
$filtro_criacao = '';
if (!$eh_renata_bryar && !in_array($perfil_usuario, ['admin', 'diretor'])) {
    $filtro_criacao = "AND (COD_VENDEDOR_CRIACAO IS NULL OR COD_VENDEDOR_CRIACAO != '010585')";
}

// Debug: Log dos parâmetros recebidos
error_log("AJAX - Gerenciador: " . $gerenciador . ", Mostrar excluídas: " . ($mostrar_excluidas ? 'true' : 'false') . ", Filtro: '" . $filtro_excluidas . "'");

// Verificar se o gerenciador solicitado é permitido para o usuário
$gerenciador_upper = strtoupper(trim($gerenciador));
$eh_avn = (strpos($gerenciador_upper, 'AVN') !== false || strpos($gerenciador_upper, 'A V N') !== false);
$eh_if_mg = (strpos($gerenciador_upper, 'IF') !== false && strpos($gerenciador_upper, 'MG') !== false);
$eh_mgi = (strpos($gerenciador_upper, 'MGI') !== false);
$eh_americanas = (strpos($gerenciador_upper, 'AMERICANAS') !== false);
$eh_dpsp = (strpos($gerenciador_upper, 'DPSP') !== false);
$eh_gerenciador_restrito = $eh_avn || $eh_if_mg || $eh_mgi || $eh_americanas || $eh_dpsp;

if ($eh_renata_bryar) {
    // Renata vê APENAS MGI, AVN, IF-MG, AMERICANAS e DPSP
    if (!$eh_gerenciador_restrito) {
        echo json_encode([
            'success' => false,
            'message' => 'Acesso restrito: você só pode visualizar contratos do GRUPO AVN, IF-MG, MGI, AMERICANAS e DPSP'
        ]);
        exit;
    }
} elseif (!$pode_ver_avn_mgi) {
    // Outros usuários (exceto admin/diretor) NÃO veem MGI, AVN, IF-MG, AMERICANAS e DPSP
    if ($eh_gerenciador_restrito) {
        echo json_encode([
            'success' => false,
            'message' => 'Acesso restrito: você não tem permissão para visualizar contratos do GRUPO AVN, IF-MG, MGI, AMERICANAS e DPSP'
        ]);
        exit;
    }
}

try {
    // Se for "GRUPO AVN", buscar apenas por AVN (sem IF-MG)
    if ($gerenciador === 'GRUPO AVN') {
        $sql = "SELECT 
                    ID,
                    COALESCE(SIGLA, ORGAO) as sigla,
                    GERENCIADOR as gerenciador,
                    ORGAO as razao_social,
                    EDITAL_LICITACAO as numero_pregao,
                    NUMERO_CONTRATO as termo_contrato,
                    VALOR_GLOBAL as VALOR_CONTRATADO,
                    COALESCE(VALOR_CONSUMIDO, 0) as VALOR_CONSUMIDO,
                    DATA_INICIO_CONTRATO as DATA_INICIO,
                    DATA_TERMINO_CONTRATO as DATA_FIM,
                    STATUS as status
                FROM LICITACAO
                WHERE (UPPER(GERENCIADOR) LIKE '%AVN%' OR UPPER(GERENCIADOR) LIKE '%A V N%')
                " . $filtro_excluidas . "
                " . $filtro_criacao . "
                ORDER BY COALESCE(SIGLA, ORGAO), ID";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($gerenciador === 'IF - MG') {
        // Se for "IF - MG", buscar apenas por IF-MG
        $sql = "SELECT 
                    ID,
                    COALESCE(SIGLA, ORGAO) as sigla,
                    GERENCIADOR as gerenciador,
                    ORGAO as razao_social,
                    EDITAL_LICITACAO as numero_pregao,
                    NUMERO_CONTRATO as termo_contrato,
                    VALOR_GLOBAL as VALOR_CONTRATADO,
                    COALESCE(VALOR_CONSUMIDO, 0) as VALOR_CONSUMIDO,
                    DATA_INICIO_CONTRATO as DATA_INICIO,
                    DATA_TERMINO_CONTRATO as DATA_FIM,
                    STATUS as status
                FROM LICITACAO
                WHERE (UPPER(GERENCIADOR) LIKE '%IF%' AND UPPER(GERENCIADOR) LIKE '%MG%')
                " . $filtro_excluidas . "
                " . $filtro_criacao . "
                ORDER BY COALESCE(SIGLA, ORGAO), ID";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Query para buscar licitações da tabela LICITACAO por gerenciador específico
        // Primeiro tentar busca exata com TRIM
        $sql = "SELECT 
                    ID,
                    COALESCE(SIGLA, ORGAO) as sigla,
                    GERENCIADOR as gerenciador,
                    ORGAO as razao_social,
                    EDITAL_LICITACAO as numero_pregao,
                    NUMERO_CONTRATO as termo_contrato,
                    VALOR_GLOBAL as VALOR_CONTRATADO,
                    COALESCE(VALOR_CONSUMIDO, 0) as VALOR_CONSUMIDO,
                    DATA_INICIO_CONTRATO as DATA_INICIO,
                    DATA_TERMINO_CONTRATO as DATA_FIM,
                    STATUS as status
                FROM LICITACAO
                WHERE TRIM(GERENCIADOR) = ?
                " . $filtro_excluidas . "
                " . $filtro_criacao . "
                ORDER BY COALESCE(SIGLA, ORGAO), ID";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([trim($gerenciador)]);
        
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Se não encontrou com busca exata, tentar busca flexível
        if (empty($contratos)) {
            $sql_fallback = "SELECT 
                        ID,
                        COALESCE(SIGLA, ORGAO) as sigla,
                        GERENCIADOR as gerenciador,
                        ORGAO as razao_social,
                        EDITAL_LICITACAO as numero_pregao,
                        NUMERO_CONTRATO as termo_contrato,
                        VALOR_GLOBAL as VALOR_CONTRATADO,
                        COALESCE(VALOR_CONSUMIDO, 0) as VALOR_CONSUMIDO,
                        DATA_INICIO_CONTRATO as DATA_INICIO,
                        DATA_TERMINO_CONTRATO as DATA_FIM,
                        STATUS as status
                    FROM LICITACAO
                    WHERE GERENCIADOR LIKE ?
                    " . $filtro_excluidas . "
                    " . $filtro_criacao . "
                    ORDER BY COALESCE(SIGLA, ORGAO), ID";
            $stmt_fallback = $pdo->prepare($sql_fallback);
            $stmt_fallback->execute(["%" . trim($gerenciador) . "%"]);
            $contratos = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Adicionar campo DESCRICAO baseado em razao_social e converter gerenciador para maiúsculo
    foreach ($contratos as &$contrato) {
        $contrato['DESCRICAO'] = $contrato['razao_social'] . 
            ($contrato['numero_pregao'] ? ' - Pregão: ' . $contrato['numero_pregao'] : '') .
            ($contrato['termo_contrato'] ? ' - Termo: ' . $contrato['termo_contrato'] : '');
        
        // Converter nome do gerenciador para maiúsculo
        $contrato['gerenciador'] = strtoupper($contrato['gerenciador']);
    }
    
    echo json_encode([
        'success' => true,
        'contratos' => $contratos,
        'total' => count($contratos)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar detalhes: ' . $e->getMessage()
    ]);
}
?>