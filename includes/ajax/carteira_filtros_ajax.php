<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/conexao.php';

function json_error($message, $extra = []) {
    http_response_code(200);
    echo json_encode(array_merge(['success' => false, 'message' => $message], $extra));
    exit;
}

try {
    if (!isset($_SESSION['usuario'])) {
        json_error('Sessão expirada. Faça login novamente.');
    }
    $usuario = $_SESSION['usuario'];

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        json_error('Erro de conexão com o banco de dados.');
    }

    // Configs de performance compatíveis
    $perfil = strtolower(trim($usuario['perfil'] ?? ''));
    $is_diretor_admin = in_array($perfil, ['diretor', 'admin']);
    
    // Ajustar timeout para diretores/admins
    if ($is_diretor_admin) {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '768M');
    }
    
    try {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    } catch (Throwable $e) {}

    // Parâmetros de filtros (espelhar a página)
    $filtro_inatividade = $_GET['filtro_inatividade'] ?? '';
    $filtro_estado = $_GET['filtro_estado'] ?? '';
    $filtro_vendedor = $_GET['filtro_vendedor'] ?? '';
    $filtro_segmento = $_GET['filtro_segmento'] ?? '';
    // Novos filtros ano/mês (sem valor mínimo/máximo)
    $filtro_ano = $_GET['filtro_ano'] ?? '';
    $filtro_mes = $_GET['filtro_mes'] ?? '';

    // Paginação
    $itens_por_pagina = 10;
    $pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
    $offset = ($pagina_atual - 1) * $itens_por_pagina;

    // Construir condições WHERE baseadas no perfil do usuário
    $where_conditions = [];
    $params = [];

    if ($perfil === 'vendedor' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "uf.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    } elseif ($perfil === 'representante' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "uf.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    } elseif ($perfil === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $cod_supervisor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "uf.COD_SUPER = ?";
        $params[] = $cod_supervisor_formatado;
    } elseif ($perfil === 'diretor' || $perfil === 'admin') {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        if (!empty($supervisor_selecionado)) {
            // Comparação robusta independente de zeros à esquerda
            $where_conditions[] = "CAST(uf.COD_SUPER AS UNSIGNED) = CAST(? AS UNSIGNED)";
            $params[] = $supervisor_selecionado;
            // Se também veio filtro de vendedor, restringir ao vendedor dentro desta supervisão
            if (!empty($filtro_vendedor)) {
                $where_conditions[] = "uf.COD_VENDEDOR = ?";
                $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
            }
        }
        // Diretores/admins têm acesso completo aos dados sem limitação de tempo
    }

    // Filtros adicionais
    if (!empty($filtro_estado)) { $where_conditions[] = "uf.ESTADO = ?"; $params[] = $filtro_estado; }
    if (!empty($filtro_vendedor)) { $where_conditions[] = "uf.COD_VENDEDOR = ?"; $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT); }
    if (!empty($filtro_segmento)) { $where_conditions[] = "uf.Descricao1 = ?"; $params[] = $filtro_segmento; }
    if (empty($filtro_inatividade)) {
        if (!empty($filtro_ano)) { $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)"; $params[] = intval($filtro_ano); }
        if (!empty($filtro_mes)) { $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)"; $params[] = intval($filtro_mes); }
    }

    $where_clause_base = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Filtro de janela de tempo removido para incluir todos os clientes, mesmo com tempos de compra espaçados
    // $janela_meses = intval($_GET['janela'] ?? 36);
    // if (empty($filtro_inatividade)) {
    //     if ($janela_meses > 0) {
    //         $filtro_janela = "STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') >= DATE_SUB(CURDATE(), INTERVAL {$janela_meses} MONTH)";
    //         $where_clause_base = $where_clause_base ? ($where_clause_base . ' AND ' . $filtro_janela) : ('WHERE ' . $filtro_janela);
    //     }
    // }

    // Condição de excluídos (apenas na consulta de detalhes)
    $cond_excluidos = "(NOT EXISTS (\n            SELECT 1 FROM clientes_excluidos ce\n            WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(ce.cnpj, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) =\n                  SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)\n        ) OR EXISTS (\n            SELECT 1 FROM clientes_restaurados cr\n            WHERE cr.raiz_cnpj = SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)\n        ))";
    $where_clause_detalhes = $where_clause_base ? ($where_clause_base . ' AND ' . $cond_excluidos) : ('WHERE ' . $cond_excluidos);

    // Referência de mês/ano para agregações
    $usar_acumulado_ano = true;
    $ano_ref = !empty($filtro_ano) ? intval($filtro_ano) : intval(date('Y'));
    $mes_ref = !empty($filtro_mes) ? intval($filtro_mes) : null;
    if (!empty($mes_ref)) { $usar_acumulado_ano = false; }

    // Caminho otimizado (sem filtro de inatividade) com paginação real
    $has_next = false;
    $total_registros = 0;
    $total_paginas = 0;
    $total_faturamento_mes = 0;
    $clientes = [];

    if (empty($filtro_inatividade)) {
        $oversample = 2;
        $limit_raizes = $itens_por_pagina * $oversample;
        $is_diretor_admin = in_array($perfil, ['diretor', 'admin']);
        $limit_pre_group = $is_diretor_admin ? 15000 : 50000; // limite menor para diretores/admins

        $sql_raizes = "\n            SELECT r.raiz_cnpj\n            FROM (\n                SELECT \n                    SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,\n                    MAX(STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) AS ultima_compra_data,\n                    MAX(ultima_ligacao_cliente.data_ligacao) AS ultima_ligacao\n                FROM ultimo_faturamento uf\n                LEFT JOIN (\n                    SELECT \n                        cliente_id,\n                        MAX(data_ligacao) as data_ligacao\n                    FROM LIGACOES \n                    WHERE status = 'finalizada'\n                    GROUP BY cliente_id\n                ) ultima_ligacao_cliente ON ultima_ligacao_cliente.cliente_id = uf.CNPJ\n                $where_clause_base\n                GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)\n                ORDER BY 
                    CASE WHEN MAX(ultima_ligacao_cliente.data_ligacao) IS NULL THEN 0 ELSE 1 END,
                    ultima_compra_data DESC, 
                    raiz_cnpj\n                LIMIT ? OFFSET ?\n            ) r\n        ";
        $params_raizes = array_merge($params, [$limit_raizes, $offset]);
        $stmt_raizes = $pdo->prepare($sql_raizes);
        $stmt_raizes->execute($params_raizes);
        $raizes = $stmt_raizes->fetchAll(PDO::FETCH_COLUMN);
        $stmt_raizes->closeCursor();

        if (!empty($raizes)) {
            $in_placeholders = implode(',', array_fill(0, count($raizes), '?'));
            $order_placeholders = $in_placeholders;

            $sql_clientes = "\n                SELECT \n                    SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,\n                    MAX(uf.CNPJ) AS cnpj_representativo,\n                    CASE WHEN MAX(ce.cliente_editado) IS NOT NULL AND MAX(ce.cliente_editado) != '' THEN MAX(ce.cliente_editado) ELSE MAX(uf.CLIENTE) END AS cliente,\n                    CASE WHEN MAX(ce.nome_fantasia_editado) IS NOT NULL AND MAX(ce.nome_fantasia_editado) != '' THEN MAX(ce.nome_fantasia_editado) ELSE MAX(uf.NOME_FANTASIA) END AS nome_fantasia,\n                    CASE WHEN MAX(ce.estado_editado) IS NOT NULL AND MAX(ce.estado_editado) != '' THEN MAX(ce.estado_editado) ELSE MAX(uf.ESTADO) END AS estado,\n                    CASE WHEN MAX(ce.segmento_editado) IS NOT NULL AND MAX(ce.segmento_editado) != '' THEN MAX(ce.segmento_editado) ELSE MAX(COALESCE(uf.Descricao1, '')) END AS segmento_atuacao,\n                    COALESCE(uf.COD_VENDEDOR, '') AS cod_vendedor,\n                    MAX(uf.NOME_VENDEDOR) AS nome_vendedor,\n                    COALESCE(uf.COD_SUPER, '') AS cod_supervisor,\n                    MAX(uf.FANT_SUPER) AS nome_supervisor,\n                    COUNT(*) AS total_pedidos,\n                    SUM(uf.VLR_TOTAL) AS valor_total,\n                    DATE_FORMAT(MAX(CASE WHEN uf.DT_FAT IS NOT NULL AND uf.DT_FAT != '' AND STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') END), '%d/%m/%Y') AS ultima_compra,\n                    MIN(uf.DT_FAT) AS primeira_compra,\n                    MAX(CASE WHEN uf.DT_FAT IS NOT NULL AND uf.DT_FAT != '' AND STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') END) AS ultima_compra_data,\n                    COUNT(DISTINCT uf.CNPJ) AS total_cnpjs_diferentes,\n                    0 AS faturamento_mes,\n                    CASE WHEN MAX(ce.telefone_editado) IS NOT NULL AND MAX(ce.telefone_editado) != '' THEN MAX(ce.telefone_editado) ELSE CASE WHEN MAX(uf.DDD) IS NOT NULL AND MAX(uf.DDD) != '' AND MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' THEN CONCAT('(', MAX(uf.DDD), ') ', MAX(uf.TELEFONE)) WHEN MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' THEN MAX(uf.TELEFONE) WHEN MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) != '' THEN MAX(uf.TELEFONE2) ELSE 'N/A' END END AS telefone,\n                    MAX(ultima_ligacao_cliente.data_ligacao) AS ultima_ligacao,\n                    MAX(ce.data_edicao) AS data_edicao,\n                    MAX(ce.usuario_editor) AS usuario_editor,\n                    CASE WHEN MAX(ce.id) IS NOT NULL THEN 1 ELSE 0 END AS tem_edicao\n                FROM ultimo_faturamento uf\n                LEFT JOIN CLIENTES_EDICOES ce ON uf.CNPJ = ce.cnpj_original AND ce.ativo = 1\n                LEFT JOIN (\n                    SELECT \n                        cliente_id,\n                        MAX(data_ligacao) as data_ligacao\n                    FROM LIGACOES \n                    WHERE status = 'finalizada'\n                    GROUP BY cliente_id\n                ) ultima_ligacao_cliente ON ultima_ligacao_cliente.cliente_id = uf.CNPJ\n                $where_clause_detalhes\n                  AND SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) IN ($in_placeholders)\n                GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)\n                ORDER BY FIELD(\n                    SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8),\n                    $order_placeholders\n                )\n            ";
            $params_clientes = array_merge($params, $raizes, $raizes);
            $stmt_clientes = $pdo->prepare($sql_clientes);
            $stmt_clientes->execute($params_clientes);
            $clientes_full = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
            $stmt_clientes->closeCursor();

            // Última ligação já incluída na consulta principal

            // Tamanho da página após excluídos
            $clientes = array_slice($clientes_full, 0, $itens_por_pagina);
            $has_next = count($clientes_full) > $itens_por_pagina;

            // Faturamento da página
            $total_faturamento_mes = 0;
            if (!empty($clientes)) {
                $raizes_pagina = array_column($clientes, 'raiz_cnpj');
                $placeholders_raiz = implode(',', array_fill(0, count($raizes_pagina), '?'));
                $sql_fat_page = "\n                    SELECT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj, SUM(f.VLR_TOTAL) AS fat_mes\n                    FROM FATURAMENTO f\n                    WHERE f.EMISSAO IS NOT NULL AND f.EMISSAO != ''\n                      AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = {$ano_ref}\n                      " . ($usar_acumulado_ano ? "" : "AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = {$mes_ref}") . "\n                      AND SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) IN ($placeholders_raiz)\n                    GROUP BY raiz_cnpj\n                ";
                $stmt_fat_page = $pdo->prepare($sql_fat_page);
                $stmt_fat_page->execute($raizes_pagina);
                $fat_rows = $stmt_fat_page->fetchAll(PDO::FETCH_ASSOC);
                $stmt_fat_page->closeCursor();
                $map_fat = [];
                foreach ($fat_rows as $r) { $map_fat[$r['raiz_cnpj']] = (float)$r['fat_mes']; }
                foreach ($clientes as &$c) { $c['faturamento_mes'] = $map_fat[$c['raiz_cnpj']] ?? 0; $total_faturamento_mes += $c['faturamento_mes']; }
                unset($c);
            }

            // Calcular total real de registros e páginas
            // Fazer uma consulta para contar o total real de registros
            $sql_count = "
                SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
                FROM ultimo_faturamento uf
                LEFT JOIN (
                    SELECT 
                        cliente_id,
                        MAX(data_ligacao) as data_ligacao
                    FROM LIGACOES 
                    WHERE status = 'finalizada'
                    GROUP BY cliente_id
                ) ultima_ligacao_cliente ON ultima_ligacao_cliente.cliente_id = uf.CNPJ
                $where_clause_detalhes
            ";
            
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute($params);
            $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
            $total_registros = (int)($count_result['total'] ?? 0);
            $total_paginas = (int)ceil($total_registros / $itens_por_pagina);
            
            // Ajustar página atual se necessário
            if ($pagina_atual > $total_paginas && $total_paginas > 0) {
                $pagina_atual = $total_paginas;
            }
        }
    } else {
        // Quando filtro de inatividade está ativo, usar HAVING no banco para filtrar por dias desde última compra
        $having_status_sql = '';
        if ($filtro_inatividade === 'ativo') {
            $having_status_sql = "HAVING DATEDIFF(CURDATE(), COALESCE(ultima_compra_data, STR_TO_DATE('1900-01-01','%Y-%m-%d'))) <= 290";
        } elseif ($filtro_inatividade === 'inativando') {
            $having_status_sql = "HAVING DATEDIFF(CURDATE(), COALESCE(ultima_compra_data, STR_TO_DATE('1900-01-01','%Y-%m-%d'))) > 290 AND DATEDIFF(CURDATE(), COALESCE(ultima_compra_data, STR_TO_DATE('1900-01-01','%Y-%m-%d'))) <= 365";
        } elseif ($filtro_inatividade === 'inativo') {
            $having_status_sql = "HAVING (ultima_compra_data IS NULL OR DATEDIFF(CURDATE(), ultima_compra_data) > 365)";
        }

        $sql_base = "SELECT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                MAX(uf.CNPJ) as cnpj_representativo,
                CASE WHEN MAX(ce.cliente_editado) IS NOT NULL AND MAX(ce.cliente_editado) != '' THEN MAX(ce.cliente_editado) ELSE MAX(uf.CLIENTE) END as cliente,
                CASE WHEN MAX(ce.nome_fantasia_editado) IS NOT NULL AND MAX(ce.nome_fantasia_editado) != '' THEN MAX(ce.nome_fantasia_editado) ELSE MAX(uf.NOME_FANTASIA) END as nome_fantasia,
                CASE WHEN MAX(ce.estado_editado) IS NOT NULL AND MAX(ce.estado_editado) != '' THEN MAX(ce.estado_editado) ELSE MAX(uf.ESTADO) END as estado,
                CASE WHEN MAX(ce.segmento_editado) IS NOT NULL AND MAX(ce.segmento_editado) != '' THEN MAX(ce.segmento_editado) ELSE MAX(COALESCE(uf.Descricao1, '')) END as segmento_atuacao,
                COALESCE(uf.COD_VENDEDOR, '') as cod_vendedor,
                MAX(uf.NOME_VENDEDOR) as nome_vendedor,
                COALESCE(uf.COD_SUPER, '') as cod_supervisor,
                MAX(uf.FANT_SUPER) as nome_supervisor,
                COUNT(*) as total_pedidos,
                SUM(uf.VLR_TOTAL) as valor_total,
                DATE_FORMAT(MAX(CASE WHEN uf.DT_FAT IS NOT NULL AND uf.DT_FAT != '' AND STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') END), '%d/%m/%Y') as ultima_compra,
                MIN(uf.DT_FAT) as primeira_compra,
                MAX(CASE WHEN uf.DT_FAT IS NOT NULL AND uf.DT_FAT != '' AND STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') END) as ultima_compra_data,
                COUNT(DISTINCT uf.CNPJ) as total_cnpjs_diferentes,
                0 as faturamento_mes,
                CASE WHEN MAX(ce.telefone_editado) IS NOT NULL AND MAX(ce.telefone_editado) != '' THEN MAX(ce.telefone_editado)
                     ELSE CASE WHEN MAX(uf.DDD) IS NOT NULL AND MAX(uf.DDD) != '' AND MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' THEN CONCAT('(', MAX(uf.DDD), ') ', MAX(uf.TELEFONE))
                               WHEN MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' THEN MAX(uf.TELEFONE)
                               WHEN MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) != '' THEN MAX(uf.TELEFONE2)
                               ELSE 'N/A' END END as telefone,
                MAX(ultima_ligacao_cliente.data_ligacao) as ultima_ligacao,
                MAX(ce.data_edicao) as data_edicao,
                MAX(ce.usuario_editor) as usuario_editor,
                CASE WHEN MAX(ce.id) IS NOT NULL THEN 1 ELSE 0 END as tem_edicao
            FROM ultimo_faturamento uf
            LEFT JOIN CLIENTES_EDICOES ce ON uf.CNPJ = ce.cnpj_original AND ce.ativo = 1
            LEFT JOIN (
                SELECT 
                    cliente_id,
                    MAX(data_ligacao) as data_ligacao
                FROM LIGACOES 
                WHERE status = 'finalizada'
                GROUP BY cliente_id
            ) ultima_ligacao_cliente ON ultima_ligacao_cliente.cliente_id = uf.CNPJ
            $where_clause_detalhes
            GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
            " . ($having_status_sql ? (" " . $having_status_sql . " ") : "") . "
            ORDER BY 
                CASE WHEN MAX(ultima_ligacao_cliente.data_ligacao) IS NULL THEN 0 ELSE 1 END,
                ultima_compra_data DESC, 
                valor_total DESC";

        $stmt_todos = $pdo->prepare($sql_base);
        $stmt_todos->execute($params);
        $todos_clientes = $stmt_todos->fetchAll(PDO::FETCH_ASSOC);


        // Paginação no PHP
        $total_registros = count($todos_clientes);
        $total_paginas = (int)ceil($total_registros / $itens_por_pagina);
        if ($pagina_atual > $total_paginas && $total_paginas > 0) { $pagina_atual = $total_paginas; }
        $offset = ($pagina_atual - 1) * $itens_por_pagina;
        $clientes = array_slice($todos_clientes, $offset, $itens_por_pagina);

        // Total de faturamento
        $total_faturamento_mes = 0;
        foreach ($todos_clientes as $cliente) { $total_faturamento_mes += (float)($cliente['faturamento_mes'] ?? 0); }
    }

    // Renderizar HTML da tabela usando o mesmo partial
    ob_start();
    // variáveis usadas pelo partial: $clientes, $total_faturamento_mes, $offset, $itens_por_pagina, $total_registros, $total_paginas, $pagina_atual, $usuario, $usar_acumulado_ano
    include __DIR__ . '/tabela_clientes.php';
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'paginacao' => [
            'pagina' => $pagina_atual,
            'paginas' => $total_paginas,
            'total' => $total_registros,
            'itens_por_pagina' => $itens_por_pagina,
            'inicio' => (($pagina_atual - 1) * $itens_por_pagina + 1),
            'fim' => min($pagina_atual * $itens_por_pagina, $total_registros)
        ],
        'totais' => [
            'faturamento' => $total_faturamento_mes
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    json_error('Erro interno ao processar filtros', ['error' => $e->getMessage()]);
}


