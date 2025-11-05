<?php
// ===== OTIMIZAÇÕES DE PERFORMANCE PARA CLIENTES GRANDES =====
// Aumentar limite de memória para processar clientes com muitos pedidos
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600); // 10 minutos

// Otimizações implementadas:
// 1. Redução de consultas SQL desnecessárias
// 2. Consolidação de estatísticas em uma única query
// 3. Otimização da conversão de valores brasileiros
// 4. Redução da paginação de 100 para 50 pedidos por página
// 5. Remoção de consultas redundantes
// 6. Simplificação do processamento de dados na tabela

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit;
}

// Configurar locale para português brasileiro
setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'portuguese');

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/config.php';

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar se o CNPJ foi fornecido
$cnpj = $_GET['cnpj'] ?? '';
$from_optimized = $_GET['from_optimized'] ?? '0';

// Determinar página de retorno baseado no parâmetro from_optimized
$basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
if ($from_optimized === '1') {
    $return_page = (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) 
        ? rtrim($basePath, '/') . '/carteira-admin' 
        : rtrim($basePath, '/') . '/carteira-teste';
} else {
    $return_page = rtrim($basePath, '/') . '/carteira-teste';
}

if (empty($cnpj)) {
    header('Location: ' . $return_page);
    exit;
}

// Função para extrair a raiz do CNPJ (primeiros 8 dígitos)
function extrairRaizCNPJ($cnpj) {
    if (empty($cnpj)) return '';
    
    // Remover caracteres não numéricos
    $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Retornar os primeiros 8 dígitos (raiz do CNPJ)
    return substr($cnpj_limpo, 0, 8);
}

// Função para converter valor brasileiro para decimal
function converterValorBrasileiro($valor) {
    if (empty($valor)) return 0;
    
    // Se já é um número, usar diretamente
    if (is_numeric($valor)) {
        return floatval($valor);
    }
    
    // Se tem vírgula e ponto, é formato brasileiro (67.485,58)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        $valor_limpo = str_replace('.', '', $valor);
        $valor_limpo = str_replace(',', '.', $valor_limpo);
        return floatval($valor_limpo);
    }
    
    // Se só tem vírgula, substituir por ponto
    if (strpos($valor, ',') !== false) {
        $valor_limpo = str_replace(',', '.', $valor);
        return floatval($valor_limpo);
    }
    
    return floatval($valor);
}

// Extrair raiz do CNPJ fornecido
$raiz_cnpj = extrairRaizCNPJ($cnpj);

// OTIMIZAÇÃO: Buscar todas as informações do cliente em uma única consulta
$sql_cliente = "SELECT 
                    f.CNPJ as cnpj,
                    f.CLIENTE as cliente,
                    f.CLIENTE as nome_fantasia,
                    f.Estado as estado,
                    COALESCE(c.Endereco, '') as endereco,
                    f.COD_CLI as cod_cliente,
                    f.COD_VENDEDOR as cod_vendedor,
                    f.NOME_VENDEDOR as nome_vendedor,
                    COUNT(*) as total_pedidos,
                    SUM(CASE 
                        WHEN f.VLR_TOTAL LIKE '%,%' AND f.VLR_TOTAL LIKE '%.%' THEN 
                            CAST(REPLACE(REPLACE(f.VLR_TOTAL, '.', ''), ',', '.') AS DECIMAL(15,2))
                        WHEN f.VLR_TOTAL LIKE '%,%' THEN 
                            CAST(REPLACE(f.VLR_TOTAL, ',', '.') AS DECIMAL(15,2))
                        ELSE CAST(f.VLR_TOTAL AS DECIMAL(15,2))
                    END) as valor_total,
                    MAX(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) as ultima_compra,
                    MIN(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) as primeira_compra
                FROM FATURAMENTO f
                LEFT JOIN CLIENTES c ON f.CNPJ = c.CNPJ
                WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?
                GROUP BY f.CNPJ, f.COD_VENDEDOR
                LIMIT 1";

$stmt = $pdo->prepare($sql_cliente);
$stmt->execute([$raiz_cnpj]);
$cliente_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug temporário
if (!$cliente_info) {
    echo "<h2>Debug - Cliente não encontrado</h2>";
    echo "<p>CNPJ original: " . htmlspecialchars($cnpj) . "</p>";
    echo "<p>Raiz CNPJ buscada: " . htmlspecialchars($raiz_cnpj) . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_cliente) . "</p>";
    echo "<p><a href='" . $return_page . "'>Voltar para Carteira</a></p>";
    exit;
}

// OTIMIZAÇÃO: Buscar estatísticas gerais em uma única consulta
$sql_estatisticas = "SELECT 
                        COUNT(DISTINCT CNPJ) as total_cnpjs,
                        COUNT(*) as total_pedidos,
                        SUM(CASE 
                            WHEN VLR_TOTAL LIKE '%,%' AND VLR_TOTAL LIKE '%.%' THEN 
                                CAST(REPLACE(REPLACE(VLR_TOTAL, '.', ''), ',', '.') AS DECIMAL(15,2))
                            WHEN VLR_TOTAL LIKE '%,%' THEN 
                                CAST(REPLACE(VLR_TOTAL, ',', '.') AS DECIMAL(15,2))
                            ELSE CAST(VLR_TOTAL AS DECIMAL(15,2))
                        END) as valor_total_real,
                        MAX(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) as ultima_compra,
                        MIN(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) as primeira_compra
                    FROM FATURAMENTO 
                    WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?";

$stmt = $pdo->prepare($sql_estatisticas);
$stmt->execute([$raiz_cnpj]);
$estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Extrair valores das estatísticas
$total_cnpjs = $estatisticas['total_cnpjs'];
$total_pedidos = $estatisticas['total_pedidos'];
$valor_total_real = $estatisticas['valor_total_real'] ?? 0;
$ultima_compra = $estatisticas['ultima_compra'];
$primeira_compra = $estatisticas['primeira_compra'];

// Calcular ticket médio
$ticket_medio = $total_pedidos > 0 ? $valor_total_real / $total_pedidos : 0;

// Configurações de paginação para CNPJs unificados
$cnpjs_por_pagina = 20;
$pagina_cnpjs = max(1, intval($_GET['pagina_cnpjs'] ?? 1));
$offset_cnpjs = ($pagina_cnpjs - 1) * $cnpjs_por_pagina;

// Buscar CNPJs unificados com paginação
$cnpjs_unificados_sql = "SELECT DISTINCT CNPJ, CLIENTE
                         FROM FATURAMENTO 
                         WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?
                         ORDER BY CNPJ
                         LIMIT " . intval($cnpjs_por_pagina) . " OFFSET " . intval($offset_cnpjs);

$stmt = $pdo->prepare($cnpjs_unificados_sql);
$stmt->execute([$raiz_cnpj]);
$cnpjs_unificados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_paginas_cnpjs = ceil($total_cnpjs / $cnpjs_por_pagina);

// OTIMIZAÇÃO: Simplificar busca de CNPJs sem faturamento (não faz sentido na mesma tabela)
$cnpjs_sem_faturamento = 0; // Removido pois não faz sentido buscar na mesma tabela

// Processar filtros de pedidos
$filtro_pedido = $_GET['filtro_pedido'] ?? '';
$filtro_nota_fiscal = $_GET['filtro_nota_fiscal'] ?? '';
$filtro_data_inicial = $_GET['filtro_data_inicial'] ?? '';
$filtro_data_final = $_GET['filtro_data_final'] ?? '';

// Configurações de paginação para pedidos
$pedidos_por_pagina = 50; // Reduzido de 100 para 50 para melhor performance
$pagina_pedidos = max(1, intval($_GET['pagina_pedidos'] ?? 1));
$offset_pedidos = ($pagina_pedidos - 1) * $pedidos_por_pagina;

// Construir condições WHERE para filtros de pedidos
$where_conditions_pedidos = ["SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?"];
$params_pedidos = [$raiz_cnpj];

// Filtro por número do pedido
if (!empty($filtro_pedido)) {
    $where_conditions_pedidos[] = "PEDIDO LIKE ?";
    $params_pedidos[] = '%' . $filtro_pedido . '%';
}

// Filtro por nota fiscal
if (!empty($filtro_nota_fiscal)) {
    $where_conditions_pedidos[] = "NTA_FISCAL LIKE ?";
    $params_pedidos[] = '%' . $filtro_nota_fiscal . '%';
}

// Filtro por data inicial
if (!empty($filtro_data_inicial)) {
    // Converter data de YYYY-MM-DD para DD/MM/YYYY
    $data_inicial_brasileira = DateTime::createFromFormat('Y-m-d', $filtro_data_inicial);
    if ($data_inicial_brasileira) {
        $data_inicial_formatada = $data_inicial_brasileira->format('d/m/Y');
        $where_conditions_pedidos[] = "STR_TO_DATE(EMISSAO, '%d/%m/%Y') >= STR_TO_DATE(?, '%d/%m/%Y')";
        $params_pedidos[] = $data_inicial_formatada;
    }
}

// Filtro por data final
if (!empty($filtro_data_final)) {
    // Converter data de YYYY-MM-DD para DD/MM/YYYY
    $data_final_brasileira = DateTime::createFromFormat('Y-m-d', $filtro_data_final);
    if ($data_final_brasileira) {
        $data_final_formatada = $data_final_brasileira->format('d/m/Y');
        $where_conditions_pedidos[] = "STR_TO_DATE(EMISSAO, '%d/%m/%Y') <= STR_TO_DATE(?, '%d/%m/%Y')";
        $params_pedidos[] = $data_final_formatada;
    }
}

// OTIMIZAÇÃO: Buscar total de pedidos únicos apenas se necessário
$total_paginas_pedidos = 1;
if (empty($filtro_pedido) && empty($filtro_nota_fiscal) && empty($filtro_data_inicial) && empty($filtro_data_final)) {
    // Se não há filtros, calcular total de pedidos únicos
    $total_pedidos_unicos_sql = "SELECT COUNT(DISTINCT PEDIDO) as total
                                 FROM FATURAMENTO 
                                 WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?";
    $stmt = $pdo->prepare($total_pedidos_unicos_sql);
    $stmt->execute([$raiz_cnpj]);
    $total_pedidos_unicos_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_pedidos_unicos = $total_pedidos_unicos_result['total'];
    $total_paginas_pedidos = ceil($total_pedidos_unicos / $pedidos_por_pagina);
} else {
    // Se há filtros, calcular o total de pedidos únicos filtrado
    $total_pedidos_filtrado_sql = "SELECT COUNT(DISTINCT PEDIDO) as total
                                   FROM FATURAMENTO 
                                   WHERE " . implode(' AND ', $where_conditions_pedidos);
    $stmt = $pdo->prepare($total_pedidos_filtrado_sql);
    $stmt->execute($params_pedidos);
    $total_pedidos_filtrado_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_pedidos_filtrado = $total_pedidos_filtrado_result['total'];
    $total_paginas_pedidos = ceil($total_pedidos_filtrado / $pedidos_por_pagina);
}

// Buscar pedidos agrupados do cliente com filtros e paginação
$pedidos_sql = "SELECT 
                    EMISSAO as data_pedido,
                    PEDIDO,
                    GROUP_CONCAT(DISTINCT NTA_FISCAL ORDER BY NTA_FISCAL SEPARATOR ', ') as notas_fiscais,
                    NOME_VENDEDOR as nome_vendedor,
                    SUM(CASE 
                        WHEN VLR_TOTAL LIKE '%,%' AND VLR_TOTAL LIKE '%.%' THEN 
                            CAST(REPLACE(REPLACE(VLR_TOTAL, '.', ''), ',', '.') AS DECIMAL(15,2))
                        WHEN VLR_TOTAL LIKE '%,%' THEN 
                            CAST(REPLACE(VLR_TOTAL, ',', '.') AS DECIMAL(15,2))
                        ELSE CAST(VLR_TOTAL AS DECIMAL(15,2))
                    END) as valor_total_pedido,
                    COUNT(*) as total_itens
                FROM FATURAMENTO 
                WHERE " . implode(' AND ', $where_conditions_pedidos) . "
                GROUP BY PEDIDO, EMISSAO, NOME_VENDEDOR
                ORDER BY STR_TO_DATE(EMISSAO, '%d/%m/%Y') DESC, PEDIDO DESC
                LIMIT " . intval($pedidos_por_pagina) . " OFFSET " . intval($offset_pedidos);

$stmt = $pdo->prepare($pedidos_sql);
$stmt->execute($params_pedidos);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);





?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Detalhes do Cliente - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/Site/assets/img/logo_site.png" type="image/png">
    <link rel="shortcut icon" href="/Site/assets/img/logo_site.png" type="image/png">
    <link rel="apple-touch-icon" href="/Site/assets/img/logo_site.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/Site/assets/css/estilo.css">
    <link rel="stylesheet" href="/Site/assets/css/admin.css">
    <link rel="stylesheet" href="/Site/assets/css/detalhes-cliente.css">
    <style>
        /* Estilos padronizados baseados na admin_gestao_unificado.php */
        .dashboard-layout {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            flex-direction: column;
        }
        
        .dashboard-main {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            flex: 1;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #1a237e;
        }
        
        .page-title {
            color: #1a237e;
            margin: 0 0 1rem 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .client-info-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            align-items: start;
        }
        
        .client-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .client-detail-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid #1a237e;
        }
        
        .client-detail-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .client-detail-value {
            color: #212529;
            font-size: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-direction: column;
        }
        
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-voltar {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }
        
        .btn-voltar:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        
        .btn-edit:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
        }
        
        .stat-card h3 {
            color: #495057;
            font-size: 0.9rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }
        
        .stat-value {
            color: #1a237e;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.8rem;
            margin: 0;
        }
        
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .section-card h2 {
            color: #1a237e;
            margin: 0 0 1.5rem 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pedidos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .pedidos-table th,
        .pedidos-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .pedidos-table th {
            background: #000000;
            font-weight: 600;
            color: #ffffff;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .pedidos-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .pedido-header {
            background: #f8f9fa;
            border-left: 3px solid #007bff;
        }
        
        .pedido-header:hover {
            background: #e9ecef;
        }
        
        .btn-expand {
            transition: transform 0.3s ease;
        }
        
        .btn-expand:hover {
            transform: scale(1.1);
        }
        
        .itens-pedido {
            background: #ffffff;
        }
        
        .valor-pedido {
            font-weight: 600;
            color: #28a745;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #1a237e;
            color: white;
            border-color: #1a237e;
        }
        
        .pagination span {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .filters-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #28a745;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-filter {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Estilos para modais */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1a237e;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-fechar-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .btn-fechar-modal:hover {
            background: #e9ecef;
            color: #495057;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid #dee2e6;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
        
        .modal-footer .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-footer .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .modal-footer .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        /* Estilos para o footer */
        .dashboard-footer {
            background: #1a237e;
            color: white;
            text-align: center;
            padding: 1rem;
            margin-top: auto;
            border-top: 3px solid #0d47a1;
        }
        
        .dashboard-footer p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }
        
        .dashboard-footer .system-info {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .dashboard-main {
                padding: 1rem;
            }
            
            .client-info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: row;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
		<?php include __DIR__ . '/../components/navbar.php'; ?>
		<?php include __DIR__ . '/../components/sidebar.php'; ?>
		<?php include __DIR__ . '/../components/nav_hamburguer.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                <!-- Header da página -->
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-building"></i>
                        Detalhes do Cliente
                    </h1>
                    
                    <div class="client-info-grid">
                        <div class="client-details">
                            <div class="client-detail-item">
                                <div class="client-detail-label">Nome do Cliente</div>
                                <div class="client-detail-value"><?php echo htmlspecialchars($cliente_info['cliente'] ?? ''); ?></div>
                            </div>
                            <div class="client-detail-item">
                                <div class="client-detail-label">CNPJ</div>
                                <div class="client-detail-value"><?php echo htmlspecialchars($cliente_info['cnpj'] ?? ''); ?></div>
                            </div>
                            <div class="client-detail-item">
                                <div class="client-detail-label">Código do Cliente</div>
                                <div class="client-detail-value"><?php echo htmlspecialchars($cliente_info['cod_cliente'] ?? ''); ?></div>
                            </div>
                            <div class="client-detail-item">
                                <div class="client-detail-label">Nome Fantasia</div>
                                <div class="client-detail-value"><?php echo htmlspecialchars($cliente_info['nome_fantasia'] ?? ''); ?></div>
                            </div>
                            <div class="client-detail-item">
                                <div class="client-detail-label">Endereço</div>
                                <div class="client-detail-value"><?php echo htmlspecialchars($cliente_info['endereco'] ?? ''); ?> - <?php echo htmlspecialchars($cliente_info['estado'] ?? ''); ?></div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="<?php echo $return_page; ?>" class="btn-modern btn-voltar">
                                <i class="fas fa-arrow-left"></i> Voltar
                            </a>
                            <button onclick="abrirEdicaoCliente('<?php echo htmlspecialchars($cnpj, ENT_QUOTES); ?>'); return false;" class="btn-modern btn-edit">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        </div>
                    </div>
                </div>
                     
                     <!-- Seção de CNPJs Unificados -->
                     <?php if ($total_cnpjs > 1): ?>
                     <div class="section-card">
                         <h2><i class="fas fa-link"></i> CNPJs Unificados (<?php echo $total_cnpjs; ?>)</h2>
                         
                         <!-- Informação de paginação para CNPJs -->
                         <?php if ($total_paginas_cnpjs > 1): ?>
                             <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ff9800;">
                                 <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                     <i class="fas fa-info-circle"></i> 
                                     Exibindo <?php echo count($cnpjs_unificados); ?> de <?php echo $total_cnpjs; ?> CNPJs 
                                     (página <?php echo $pagina_cnpjs; ?> de <?php echo $total_paginas_cnpjs; ?>)
                                 </p>
                             </div>
                         <?php endif; ?>
                         
                         <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #ff9800;">
                             <p style="margin-bottom: 15px; color: #666; font-size: 0.9rem;">
                                 <i class="fas fa-info-circle"></i> 
                                 Este cliente possui múltiplos CNPJs que compartilham a mesma raiz (primeiros 8 dígitos). 
                                 Todos os pedidos destes CNPJs estão sendo exibidos unificadamente.
                             </p>
                             <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                                 <?php foreach ($cnpjs_unificados as $cnpj_info): ?>
                                     <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;">
                                         <div style="font-weight: 600; color: #1a237e; margin-bottom: 5px;">
                                             <?php echo htmlspecialchars($cnpj_info['CNPJ']); ?>
                                         </div>
                                         <div style="color: #666; font-size: 0.9rem;">
                                             <?php echo htmlspecialchars($cnpj_info['CLIENTE']); ?>
                                         </div>
                                     </div>
                                 <?php endforeach; ?>
                             </div>
                             
                             <!-- Controles de paginação para CNPJs -->
                             <?php if ($total_paginas_cnpjs > 1): ?>
                                 <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e9ecef;">
                                     <?php if ($pagina_cnpjs > 1): ?>
                                         <a href="?cnpj=<?php echo urlencode($cnpj); ?>&pagina_cnpjs=<?php echo ($pagina_cnpjs - 1); ?>&pagina_pedidos=<?php echo $pagina_pedidos; ?>&filtro_pedido=<?php echo urlencode($filtro_pedido); ?>&filtro_nota_fiscal=<?php echo urlencode($filtro_nota_fiscal); ?>&filtro_data_inicial=<?php echo urlencode($filtro_data_inicial); ?>&filtro_data_final=<?php echo urlencode($filtro_data_final); ?>" 
                                            class="btn-modern btn-voltar" style="padding: 8px 16px; font-size: 0.9rem;">
                                             <i class="fas fa-chevron-left"></i> Anterior
                                         </a>
                                     <?php endif; ?>
                                     
                                     <span style="color: #666; font-size: 0.9rem;">
                                         Página <?php echo $pagina_cnpjs; ?> de <?php echo $total_paginas_cnpjs; ?>
                                     </span>
                                     
                                     <?php if ($pagina_cnpjs < $total_paginas_cnpjs): ?>
                                         <a href="?cnpj=<?php echo urlencode($cnpj); ?>&pagina_cnpjs=<?php echo ($pagina_cnpjs + 1); ?>&pagina_pedidos=<?php echo $pagina_pedidos; ?>&filtro_pedido=<?php echo urlencode($filtro_pedido); ?>&filtro_nota_fiscal=<?php echo urlencode($filtro_nota_fiscal); ?>&filtro_data_inicial=<?php echo urlencode($filtro_data_inicial); ?>&filtro_data_final=<?php echo urlencode($filtro_data_final); ?>" 
                                            class="btn-modern" style="padding: 8px 16px; font-size: 0.9rem;">
                                             Próxima <i class="fas fa-chevron-right"></i>
                                         </a>
                                     <?php endif; ?>
                                 </div>
                             <?php endif; ?>
                         </div>
                     </div>
                     <?php endif; ?>
                     
                <!-- Seção de Estatísticas -->
                <div class="section-card">
                    <h2><i class="fas fa-chart-bar"></i> Estatísticas Gerais</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total de Pedidos</h3>
                            <div class="stat-value"><?php echo number_format($total_pedidos); ?></div>
                            <div class="stat-label">Pedidos realizados</div>
                        </div>
                        <div class="stat-card">
                            <h3>Valor Total</h3>
                            <div class="stat-value">R$ <?php echo number_format($valor_total_real, 2, ',', '.'); ?></div>
                            <div class="stat-label">Volume total</div>
                        </div>
                        <div class="stat-card">
                            <h3>Ticket Médio</h3>
                            <div class="stat-value">R$ <?php echo number_format($ticket_medio, 2, ',', '.'); ?></div>
                            <div class="stat-label">Por pedido</div>
                        </div>
                        <div class="stat-card">
                            <h3>CNPJs sem Faturamento</h3>
                            <div class="stat-value"><?php echo number_format($cnpjs_sem_faturamento); ?></div>
                            <div class="stat-label">Sem pedidos</div>
                        </div>
                        <div class="stat-card">
                            <h3>Última Compra</h3>
                            <div class="stat-value"><?php 
                                if ($ultima_compra) {
                                    // Converter data do formato americano (YYYY-MM-DD) para brasileiro (DD/MM/YYYY)
                                    $data = DateTime::createFromFormat('Y-m-d', $ultima_compra);
                                    if ($data) {
                                        echo $data->format('d/m/Y');
                                    } else {
                                        echo $ultima_compra;
                                    }
                                } else {
                                    echo '-';
                                }
                            ?></div>
                            <div class="stat-label">Data recente</div>
                        </div>
                    </div>
                </div>
                    
                    
                    
                    <!-- Lista de todos os pedidos -->
                    <div class="section-card">
                        <h2><i class="fas fa-list"></i> Todos os Pedidos (<?php 
                            if (isset($total_pedidos_unicos)) {
                                echo $total_pedidos_unicos . ' pedidos únicos';
                            } else {
                                echo 'N/A';
                            }
                        ?>)</h2>
                        
                        <!-- Filtros de Pedidos -->
                        <div class="filters-section">
                            <h3 style="margin: 0 0 1rem 0; color: #1a237e; font-size: 1.1rem;">
                                <i class="fas fa-filter"></i> Filtros de Pedidos
                            </h3>
                            <form method="GET" class="filters-grid">
                                <input type="hidden" name="cnpj" value="<?php echo htmlspecialchars($cnpj); ?>">
                                <input type="hidden" name="pagina_cnpjs" value="<?php echo $pagina_cnpjs; ?>">
                                
                                <!-- Número do Pedido -->
                                <div class="filter-group">
                                    <label>Nº Pedido:</label>
                                    <input type="text" name="filtro_pedido" value="<?php echo htmlspecialchars($filtro_pedido); ?>" 
                                           placeholder="Número do pedido">
                                </div>
                                
                                <!-- Nota Fiscal -->
                                <div class="filter-group">
                                    <label>Nota Fiscal:</label>
                                    <input type="text" name="filtro_nota_fiscal" value="<?php echo htmlspecialchars($filtro_nota_fiscal); ?>" 
                                           placeholder="Número da nota">
                                </div>
                                
                                <!-- Data Inicial -->
                                <div class="filter-group">
                                    <label>Data Inicial:</label>
                                    <input type="date" name="filtro_data_inicial" value="<?php echo htmlspecialchars($filtro_data_inicial); ?>">
                                </div>
                                
                                <!-- Data Final -->
                                <div class="filter-group">
                                    <label>Data Final:</label>
                                    <input type="date" name="filtro_data_final" value="<?php echo htmlspecialchars($filtro_data_final); ?>">
                                </div>
                                
                                <!-- Botões -->
                                <div class="filter-buttons">
                                    <button type="submit" class="btn-filter btn-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                    <a href="?cnpj=<?php echo urlencode($cnpj); ?>&pagina_cnpjs=<?php echo $pagina_cnpjs; ?>" 
                                       class="btn-filter btn-secondary" style="text-decoration: none;">
                                        <i class="fas fa-times"></i> Limpar
                                    </a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Informação de paginação -->
                        <?php if ($total_paginas_pedidos > 1): ?>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1a237e;">
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i> 
                                    Exibindo <?php echo count($pedidos); ?> de <?php echo isset($total_pedidos_unicos) ? $total_pedidos_unicos : 'N/A'; ?> pedidos únicos 
                                    (página <?php echo $pagina_pedidos; ?> de <?php echo $total_paginas_pedidos; ?>)
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pedidos)): ?>
                            <div style="overflow-x: auto;">
                                <table class="pedidos-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <th>EMISSÃO</th>
                                            <th>PEDIDO</th>
                                            <th>NOTAS FISCAIS</th>
                                            <th>ITENS</th>
                                            <th>VALOR TOTAL</th>
                                            <th>VENDEDOR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $index => $pedido): ?>
                                            <!-- Linha do pedido (resumo) -->
                                            <tr class="pedido-header" data-pedido="<?php echo htmlspecialchars($pedido['PEDIDO']); ?>">
                                                <td>
                                                    <button class="btn-expand" onclick="toggleItensPedido('<?php echo htmlspecialchars($pedido['PEDIDO']); ?>')" 
                                                            style="background: none; border: none; cursor: pointer; color: #1a237e; font-size: 1.2rem;">
                                                        <i class="fas fa-chevron-right" id="icon-<?php echo htmlspecialchars($pedido['PEDIDO']); ?>"></i>
                                                    </button>
                                                </td>
                                                <td><?php 
                                                    if ($pedido['data_pedido']) {
                                                        $data = DateTime::createFromFormat('Y-m-d', $pedido['data_pedido']);
                                                        echo $data ? $data->format('d/m/Y') : $pedido['data_pedido'];
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?></td>
                                                <td><strong><?php echo htmlspecialchars($pedido['PEDIDO'] ?? ''); ?></strong></td>
                                                <td>
                                                    <?php 
                                                    $notas_fiscais = $pedido['notas_fiscais'] ?? '';
                                                    if (!empty($notas_fiscais)) {
                                                        $notas_array = explode(', ', $notas_fiscais);
                                                        if (count($notas_array) > 1) {
                                                            echo '<div style="display: flex; flex-wrap: wrap; gap: 4px;">';
                                                            foreach ($notas_array as $nota) {
                                                                echo '<span style="background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">' . htmlspecialchars(trim($nota)) . '</span>';
                                                            }
                                                            echo '</div>';
                                                        } else {
                                                            echo htmlspecialchars($notas_fiscais);
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td><span class="badge" style="background: #007bff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                                                    <?php echo $pedido['total_itens']; ?> item(s)
                                                </span></td>
                                                <td class="valor-pedido">
                                                    <strong>R$ <?php 
                                                        $valor_formatado = converterValorBrasileiro($pedido['valor_total_pedido']);
                                                        echo number_format($valor_formatado, 2, ',', '.');
                                                    ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($pedido['nome_vendedor'] ?? ''); ?></td>
                                            </tr>
                                            
                                            <!-- Linhas dos itens do pedido (ocultas inicialmente) -->
                                            <tr class="itens-pedido" id="itens-<?php echo htmlspecialchars($pedido['PEDIDO']); ?>" style="display: none;">
                                                <td colspan="7">
                                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 5px 0;">
                                                        <h4 style="margin: 0 0 15px 0; color: #1a237e; font-size: 1rem;">
                                                            <i class="fas fa-list"></i> Itens do Pedido <?php echo htmlspecialchars($pedido['PEDIDO']); ?>
                                                        </h4>
                                                        <div id="conteudo-itens-<?php echo htmlspecialchars($pedido['PEDIDO']); ?>">
                                                            <div style="text-align: center; padding: 20px; color: #666;">
                                                                <i class="fas fa-spinner fa-spin"></i> Carregando itens...
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Controles de paginação -->
                            <?php if ($total_paginas_pedidos > 1): ?>
                                <div class="pagination">
                                    <?php if ($pagina_pedidos > 1): ?>
                                        <a href="?cnpj=<?php echo urlencode($cnpj); ?>&pagina_pedidos=<?php echo ($pagina_pedidos - 1); ?>&pagina_cnpjs=<?php echo $pagina_cnpjs; ?>&filtro_pedido=<?php echo urlencode($filtro_pedido); ?>&filtro_nota_fiscal=<?php echo urlencode($filtro_nota_fiscal); ?>&filtro_data_inicial=<?php echo urlencode($filtro_data_inicial); ?>&filtro_data_final=<?php echo urlencode($filtro_data_final); ?>">
                                            <i class="fas fa-chevron-left"></i> Anterior
                                        </a>
                                    <?php endif; ?>
                                    
                                    <span>
                                        Página <?php echo $pagina_pedidos; ?> de <?php echo $total_paginas_pedidos; ?>
                                    </span>
                                    
                                    <?php if ($pagina_pedidos < $total_paginas_pedidos): ?>
                                        <a href="?cnpj=<?php echo urlencode($cnpj); ?>&pagina_pedidos=<?php echo ($pagina_pedidos + 1); ?>&pagina_cnpjs=<?php echo $pagina_cnpjs; ?>&filtro_pedido=<?php echo urlencode($filtro_pedido); ?>&filtro_nota_fiscal=<?php echo urlencode($filtro_nota_fiscal); ?>&filtro_data_inicial=<?php echo urlencode($filtro_data_inicial); ?>&filtro_data_final=<?php echo urlencode($filtro_data_final); ?>">
                                            Próxima <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-list" style="font-size: 3rem; margin-bottom: 20px; color: #ccc;"></i>
                                <p style="font-size: 1.1rem;">Nenhum pedido encontrado</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            <footer class="dashboard-footer">
                <p>© 2025 Autopel - Todos os direitos reservados</p>
                <p class="system-info">Sistema BI v1.0</p>
            </footer>
        </div>
    </div>
    
    <!-- Modal de Edição de Cliente (simplificado) -->
    <div id="modalEdicaoCliente" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Cliente</h3>
                <button class="btn-fechar-modal" onclick="fecharModalEdicao()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Funcionalidade de edição será implementada em breve.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalEdicao()">
                    <i class="fas fa-times"></i> Fechar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Incluir JavaScript -->
    <script src="../assets/js/carteira.js?v=<?php echo time(); ?>"></script>
    <script>
        // Função para abrir modal de edição
        function abrirEdicaoCliente(cnpj) {
            document.getElementById('modalEdicaoCliente').style.display = 'flex';
        }
        
        // Função para fechar modal de edição
        function fecharModalEdicao() {
            document.getElementById('modalEdicaoCliente').style.display = 'none';
        }
        
        // Função para expandir/colapsar itens do pedido
        function toggleItensPedido(numeroPedido) {
            const itensRow = document.getElementById('itens-' + numeroPedido);
            const icon = document.getElementById('icon-' + numeroPedido);
            const conteudoItens = document.getElementById('conteudo-itens-' + numeroPedido);
            
            if (itensRow.style.display === 'none') {
                // Expandir
                itensRow.style.display = 'table-row';
                icon.className = 'fas fa-chevron-down';
                
                // Carregar itens se ainda não foram carregados
                if (conteudoItens.innerHTML.includes('Carregando itens')) {
                    carregarItensPedido(numeroPedido);
                }
            } else {
                // Colapsar
                itensRow.style.display = 'none';
                icon.className = 'fas fa-chevron-right';
            }
        }
        
        // Função para carregar itens do pedido via AJAX
        function carregarItensPedido(numeroPedido) {
            const conteudoItens = document.getElementById('conteudo-itens-' + numeroPedido);
            const cnpj = '<?php echo htmlspecialchars($cnpj); ?>';
            
            // Mostrar loading
            conteudoItens.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;"><i class="fas fa-spinner fa-spin"></i> Carregando itens...</div>';
            
            // Criar formulário para enviar dados
            const formData = new FormData();
            formData.append('acao', 'buscar_itens_pedido');
            formData.append('numero_pedido', numeroPedido);
            formData.append('cnpj', cnpj);
            
            // Usar o arquivo específico para AJAX
            fetch('/Site/includes/api/buscar_itens_pedido.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                conteudoItens.innerHTML = data;
            })
            .catch(error => {
                conteudoItens.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Erro ao carregar itens: ' + error.message + '</div>';
                console.error('Erro:', error);
            });
        }
        
        // Fechar modal clicando fora
        document.addEventListener('DOMContentLoaded', function() {
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('modalEdicaoCliente');
                if (event.target === modal) {
                    fecharModalEdicao();
                }
            });
        });
    </script>
</body>
</html>