<?php
require_once 'conexao.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$usuario_id = $usuario['ID'] ?? $usuario['id'];
$cod_vendedor = $usuario['COD_VENDEDOR'] ?? $usuario['cod_vendedor'];
$perfil = strtolower(trim($usuario['PERFIL'] ?? $usuario['perfil'] ?? ''));

// Função para obter estatísticas de clientes
function obterEstatisticasClientes($pdo, $usuario_id, $cod_vendedor, $perfil) {
    try {
        // Definir condições baseadas no perfil do usuário
        $where_clause = "";
        $params = [];
        
        if ($perfil === 'vendedor') {
            $where_clause = "WHERE COD_VENDEDOR = ?";
            $params = [$cod_vendedor];
        } elseif ($perfil === 'supervisor') {
            $where_clause = "WHERE COD_SUPER = ?";
            $params = [$cod_vendedor];
        } elseif ($perfil === 'diretor' || $perfil === 'admin') {
            $where_clause = ""; // Todos os clientes
            $params = [];
        } else {
            // Perfil padrão - vendedor
            $where_clause = "WHERE COD_VENDEDOR = ?";
            $params = [$cod_vendedor];
        }
        
        // Data atual e datas de referência
        $data_atual = date('Y-m-d');
        $data_30_dias_atras = date('Y-m-d', strtotime('-30 days'));
        $data_90_dias_atras = date('Y-m-d', strtotime('-90 days'));
        $data_180_dias_atras = date('Y-m-d', strtotime('-180 days'));
        $data_ano_atual = date('Y-01-01');
        
        // Consulta para estatísticas gerais usando FATURAMENTO (ano atual) e ultimo_faturamento (histórico)
        $sql = "
            SELECT 
                COUNT(DISTINCT raiz_cnpj) as total_clientes,
                COUNT(DISTINCT CASE WHEN status_cliente = 'ativo' THEN raiz_cnpj ELSE NULL END) as clientes_ativos,
                COUNT(DISTINCT CASE WHEN status_cliente = 'inativando' THEN raiz_cnpj ELSE NULL END) as clientes_inativando,
                COUNT(DISTINCT CASE WHEN status_cliente = 'inativo' THEN raiz_cnpj ELSE NULL END) as clientes_inativos,
                COUNT(DISTINCT CASE WHEN eh_novo_ano = 1 THEN raiz_cnpj ELSE NULL END) as clientes_novos_ano,
                COUNT(DISTINCT CASE WHEN eh_novo_mes = 1 THEN raiz_cnpj ELSE NULL END) as clientes_novos_mes
            FROM (
                SELECT 
                    SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                    MAX(CNPJ) as cnpj_representativo,
                    MAX(CLIENTE) as cliente,
                    MAX(NOME_FANTASIA) as nome_fantasia,
                    MAX(ESTADO) as estado,
                    COD_VENDEDOR as cod_vendedor,
                    MAX(NOME_VENDEDOR) as nome_vendedor,
                    COD_SUPER as cod_supervisor,
                    MAX(FANT_SUPER) as nome_supervisor,
                    COUNT(*) as total_pedidos,
                    SUM(VLR_TOTAL) as valor_total,
                    MAX(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) as ultima_compra_data,
                    MIN(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) as primeira_compra_data,
                    CASE 
                        WHEN MAX(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 'ativo'
                        WHEN MAX(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 'inativando'
                        ELSE 'inativo'
                    END as status_cliente,
                    CASE 
                        WHEN MIN(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 1
                        ELSE 0
                    END as eh_novo_ano,
                    CASE 
                        WHEN MIN(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 1
                        ELSE 0
                    END as eh_novo_mes
                FROM FATURAMENTO 
                $where_clause
                GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8), COD_VENDEDOR
                
                UNION ALL
                
                SELECT 
                    SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                    MAX(CNPJ) as cnpj_representativo,
                    MAX(CLIENTE) as cliente,
                    MAX(NOME_FANTASIA) as nome_fantasia,
                    MAX(ESTADO) as estado,
                    COD_VENDEDOR as cod_vendedor,
                    MAX(NOME_VENDEDOR) as nome_vendedor,
                    COD_SUPER as cod_supervisor,
                    MAX(FANT_SUPER) as nome_supervisor,
                    COUNT(*) as total_pedidos,
                    SUM(VLR_TOTAL) as valor_total,
                    MAX(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) as ultima_compra_data,
                    MIN(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) as primeira_compra_data,
                    CASE 
                        WHEN MAX(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 'ativo'
                        WHEN MAX(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 'inativando'
                        ELSE 'inativo'
                    END as status_cliente,
                    CASE 
                        WHEN MIN(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 1
                        ELSE 0
                    END as eh_novo_ano,
                    CASE 
                        WHEN MIN(CASE WHEN DT_FAT IS NOT NULL AND DT_FAT != '' AND STR_TO_DATE(DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(DT_FAT, '%d/%m/%Y') END) >= ? THEN 1
                        ELSE 0
                    END as eh_novo_mes
                FROM ultimo_faturamento 
                $where_clause
                GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8), COD_VENDEDOR
            ) as clientes_consolidados
        ";
        
        // Parâmetros para FATURAMENTO (ano atual)
        $params_fat = array_merge($params, [
            $data_30_dias_atras,  // Ativos (últimos 30 dias)
            $data_90_dias_atras,  // Inativando (entre 30 e 90 dias)
            $data_ano_atual,      // Novos no ano
            $data_30_dias_atras   // Novos no mês
        ]);
        
        // Parâmetros para ultimo_faturamento (histórico)
        $params_ult = array_merge($params, [
            $data_30_dias_atras,  // Ativos (últimos 30 dias)
            $data_90_dias_atras,  // Inativando (entre 30 e 90 dias)
            $data_ano_atual,      // Novos no ano
            $data_30_dias_atras   // Novos no mês
        ]);
        
        // Combinar parâmetros para a consulta UNION
        $params_completos = array_merge($params_fat, $params_ult);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params_completos);
        $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não há resultados, criar estrutura vazia
        if (!$estatisticas) {
            $estatisticas = [
                'total_clientes' => 0,
                'clientes_ativos' => 0,
                'clientes_inativando' => 0,
                'clientes_inativos' => 0,
                'clientes_novos_ano' => 0,
                'clientes_novos_mes' => 0
            ];
        }
        
        // Calcular percentuais
        $total = $estatisticas['total_clientes'] ?: 1; // Evitar divisão por zero
        
        $estatisticas['percentual_ativos'] = round(($estatisticas['clientes_ativos'] / $total) * 100, 1);
        $estatisticas['percentual_inativando'] = round(($estatisticas['clientes_inativando'] / $total) * 100, 1);
        $estatisticas['percentual_inativos'] = round(($estatisticas['clientes_inativos'] / $total) * 100, 1);
        $estatisticas['percentual_novos_ano'] = round(($estatisticas['clientes_novos_ano'] / $total) * 100, 1);
        $estatisticas['percentual_novos_mes'] = round(($estatisticas['clientes_novos_mes'] / $total) * 100, 1);
        
        return $estatisticas;
        
    } catch (PDOException $e) {
        return ['erro' => 'Erro ao obter estatísticas: ' . $e->getMessage()];
    }
}

// Processar requisições AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'estatisticas':
            $resultado = obterEstatisticasClientes($pdo, $usuario_id, $cod_vendedor, $perfil);
            echo json_encode($resultado);
            break;
            
        default:
            echo json_encode(['erro' => 'Ação não reconhecida']);
            break;
    }
} else {
    // Retornar estatísticas por padrão
    $resultado = obterEstatisticasClientes($pdo, $usuario_id, $cod_vendedor, $perfil);
    echo json_encode($resultado);
}
?> 