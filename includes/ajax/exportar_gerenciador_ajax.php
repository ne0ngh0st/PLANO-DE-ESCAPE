<?php
require_once __DIR__ . '/../config.php';

session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo 'Não autorizado';
    exit;
}

if (!isset($_GET['gerenciador'])) {
    http_response_code(400);
    echo 'Gerenciador não informado';
    exit;
}

$gerenciador = $_GET['gerenciador'];

// Verificar se é a Renata Bryar (cod_vendedor: 010585) para aplicar restrição
$cod_vendedor = $_SESSION['usuario']['COD_VENDEDOR'] ?? $_SESSION['usuario']['cod_vendedor'] ?? '';
$eh_renata_bryar = ($cod_vendedor === '010585');

// Verificar se o usuário pode ver AVN, MGI e AMERICANAS (apenas Renata, Admin e Diretor)
$perfil_usuario = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
$pode_ver_avn_mgi = $eh_renata_bryar || in_array($perfil_usuario, ['admin', 'diretor']);

// Verificar se o gerenciador solicitado é permitido para o usuário
$gerenciador_upper = strtoupper(trim($gerenciador));
$eh_avn = (strpos($gerenciador_upper, 'AVN') !== false || strpos($gerenciador_upper, 'A V N') !== false);
$eh_if_mg = (strpos($gerenciador_upper, 'IF') !== false && strpos($gerenciador_upper, 'MG') !== false);
$eh_mgi = (strpos($gerenciador_upper, 'MGI') !== false);
$eh_americanas = (strpos($gerenciador_upper, 'AMERICANAS') !== false);
$eh_gerenciador_restrito = $eh_avn || $eh_if_mg || $eh_mgi || $eh_americanas;

if ($eh_renata_bryar) {
    // Renata exporta APENAS MGI, AVN, IF-MG e AMERICANAS
    if (!$eh_gerenciador_restrito) {
        http_response_code(403);
        echo 'Acesso restrito: você só pode exportar contratos do GRUPO AVN, IF-MG, MGI e AMERICANAS';
        exit;
    }
} elseif (!$pode_ver_avn_mgi) {
    // Outros usuários (exceto admin/diretor) NÃO exportam MGI, AVN, IF-MG e AMERICANAS
    if ($eh_gerenciador_restrito) {
        http_response_code(403);
        echo 'Acesso restrito: você não tem permissão para exportar contratos do GRUPO AVN, IF-MG, MGI e AMERICANAS';
        exit;
    }
}

try {
    // Verificar se é o GRUPO AVN ou IF-MG e ajustar a query
    $where_clause = '';
    $params = [];
    
    if ($gerenciador === 'GRUPO AVN') {
        // Para GRUPO AVN, buscar apenas gerenciadores que contêm AVN (sem IF-MG)
        $where_clause = "WHERE (GERENCIADOR LIKE '%AVN%' OR GERENCIADOR LIKE '%A V N%') AND STATUS = 'VIGENTE'";
    } elseif ($gerenciador === 'IF - MG') {
        // Para IF-MG, buscar apenas gerenciadores que contêm IF-MG
        $where_clause = "WHERE (GERENCIADOR LIKE '%IF%' AND GERENCIADOR LIKE '%MG%') AND STATUS = 'VIGENTE'";
    } else {
        // Para outros gerenciadores, buscar exatamente o nome
        $where_clause = "WHERE GERENCIADOR = ? AND STATUS = 'VIGENTE'";
        $params[] = $gerenciador;
    }
    
    // Buscar licitações da tabela LICITACAO
    $sql = "SELECT 
                COD_CLIENT,
                ORGAO,
                SIGLA,
                GERENCIADOR,
                NUMERO_ATA,
                NUMERO_CONTRATO,
                VALOR_GLOBAL,
                COALESCE(VALOR_CONSUMIDO, 0) as VALOR_CONSUMIDO,
                DATA_INICIO_CONTRATO,
                DATA_TERMINO_CONTRATO,
                STATUS,
                CNPJ,
                TIPO,
                PRODUTO,
                GRUPO,
                TABELA,
                DATA_INICIO_ATA,
                DATA_TERMINO_ATA,
                VALOR_ATA,
                SALDO_CONTRATO,
                CONSUMO_CONTRATO_PERCENT
            FROM LICITACAO
            $where_clause
            ORDER BY SIGLA, ORGAO";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $licitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não encontrou licitações vigentes, buscar todas
    if (empty($licitacoes)) {
        // Ajustar fallback também para GRUPO AVN e IF-MG
        if ($gerenciador === 'GRUPO AVN') {
            $where_clause_fallback = "WHERE (GERENCIADOR LIKE '%AVN%' OR GERENCIADOR LIKE '%A V N%')";
            $params_fallback = [];
        } elseif ($gerenciador === 'IF - MG') {
            $where_clause_fallback = "WHERE (GERENCIADOR LIKE '%IF%' AND GERENCIADOR LIKE '%MG%')";
            $params_fallback = [];
        } else {
            $where_clause_fallback = "WHERE GERENCIADOR = ?";
            $params_fallback = [$gerenciador];
        }
        
        $sql_fallback = "SELECT 
                    COD_CLIENT,
                    ORGAO,
                    SIGLA,
                    GERENCIADOR,
                    NUMERO_ATA,
                    NUMERO_CONTRATO,
                    VALOR_GLOBAL,
                    COALESCE(VALOR_CONSUMIDO, 0) as VALOR_CONSUMIDO,
                    DATA_INICIO_CONTRATO,
                    DATA_TERMINO_CONTRATO,
                    STATUS,
                    CNPJ,
                    TIPO,
                    PRODUTO,
                    GRUPO,
                    TABELA,
                    DATA_INICIO_ATA,
                    DATA_TERMINO_ATA,
                    VALOR_ATA,
                    SALDO_CONTRATO,
                    CONSUMO_CONTRATO_PERCENT
                FROM LICITACAO
                $where_clause_fallback
                ORDER BY SIGLA, ORGAO";
        
        $stmt_fallback = $pdo->prepare($sql_fallback);
        $stmt_fallback->execute($params_fallback);
        $licitacoes = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Configurar headers para download CSV
    $filename = 'licitacoes_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $gerenciador) . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Abrir output stream
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos do CSV
    $headers = [
        'Código Cliente',
        'Órgão',
        'Sigla',
        'Gerenciador',
        'Número ATA',
        'Número Contrato',
        'Valor Global',
        'Valor Consumido',
        'Saldo Contrato',
        'Data Início Contrato',
        'Data Término Contrato',
        'Status',
        'CNPJ',
        'Tipo',
        'Produto',
        'Grupo',
        'Tabela',
        'Data Início ATA',
        'Data Término ATA',
        'Valor ATA',
        'Consumo %'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Dados das licitações
    foreach ($licitacoes as $licitacao) {
        $row = [
            $licitacao['COD_CLIENT'],
            $licitacao['ORGAO'],
            $licitacao['SIGLA'],
            $licitacao['GERENCIADOR'],
            $licitacao['NUMERO_ATA'],
            $licitacao['NUMERO_CONTRATO'],
            number_format($licitacao['VALOR_GLOBAL'], 2, ',', '.'),
            number_format($licitacao['VALOR_CONSUMIDO'], 2, ',', '.'),
            number_format($licitacao['SALDO_CONTRATO'], 2, ',', '.'),
            $licitacao['DATA_INICIO_CONTRATO'] ? date('d/m/Y', strtotime($licitacao['DATA_INICIO_CONTRATO'])) : '',
            $licitacao['DATA_TERMINO_CONTRATO'] ? date('d/m/Y', strtotime($licitacao['DATA_TERMINO_CONTRATO'])) : '',
            $licitacao['STATUS'],
            $licitacao['CNPJ'],
            $licitacao['TIPO'],
            $licitacao['PRODUTO'],
            $licitacao['GRUPO'],
            $licitacao['TABELA'],
            $licitacao['DATA_INICIO_ATA'] ? date('d/m/Y', strtotime($licitacao['DATA_INICIO_ATA'])) : '',
            $licitacao['DATA_TERMINO_ATA'] ? date('d/m/Y', strtotime($licitacao['DATA_TERMINO_ATA'])) : '',
            number_format($licitacao['VALOR_ATA'], 2, ',', '.'),
            $licitacao['CONSUMO_CONTRATO_PERCENT']
        ];
        
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    
} catch (PDOException $e) {
    error_log("Erro ao exportar licitações do gerenciador: " . $e->getMessage());
    http_response_code(500);
    echo 'Erro ao exportar dados: ' . $e->getMessage();
}
?>
