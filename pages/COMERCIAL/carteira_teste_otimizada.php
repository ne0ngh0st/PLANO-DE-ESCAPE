<?php
// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../includes/config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once __DIR__ . '/../../includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar permissões - usuários com perfil licitação não podem acessar carteira
$perfilUsuario = strtolower($usuario['perfil'] ?? '');
if ($perfilUsuario === 'licitação') {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'contratos');
    exit;
}

// Ajustes temporários de performance para testes desta página
$is_diretor_admin = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin']);
ini_set('max_execution_time', $is_diretor_admin ? 300 : 240); // 5 min para diretores, 4 min para outros
ini_set('memory_limit', $is_diretor_admin ? '768M' : '512M'); // Mais memória para diretores
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            // Ativar buffer para evitar erro 2014 (unbuffered queries active)
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    } catch (Throwable $e) {
        // Ignorar ajustes caso o driver não suporte
    }
}

// Função para extrair a raiz do CNPJ (primeiros 8 dígitos)
function extrairRaizCNPJ($cnpj) {
    if (empty($cnpj)) return '';
    $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);
    return substr($cnpj_limpo, 0, 8);
}

// Verificar permissões do usuário - incluindo assistente
$perfil_permitido = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin', 'vendedor', 'representante', 'assistente']);
$is_admin = strtolower(trim($usuario['perfil'])) === 'admin';

// Redirecionar admins e diretores para a página ultra otimizada
if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
    header('Location: /Site/admin/carteira-admin-diretor-ultra');
    exit;
}

// DEBUG: Log das informações do usuário
error_log("DEBUG CARTEIRA_TESTE - Usuário: " . json_encode($usuario));
error_log("DEBUG CARTEIRA_TESTE - Perfil: " . $usuario['perfil']);
error_log("DEBUG CARTEIRA_TESTE - COD_VENDEDOR: " . ($usuario['cod_vendedor'] ?? 'NÃO DEFINIDO'));
error_log("DEBUG CARTEIRA_TESTE - Perfil permitido: " . ($perfil_permitido ? 'SIM' : 'NÃO'));

// Processar filtros - persistência em sessão e limpeza opcional
if (isset($_GET['limpar_filtros'])) {
    unset($_SESSION['carteira_filtros']);
    header('Location: /Site/carteira-teste-otimizada');
    exit;
}

// Salvar filtros enviados via GET na sessão
if (!empty($_GET)) {
    foreach (['filtro_inatividade', 'filtro_estado', 'filtro_vendedor', 'filtro_segmento', 'filtro_ano', 'filtro_mes'] as $filtro) {
        if (isset($_GET[$filtro])) {
            $_SESSION['carteira_filtros'][$filtro] = $_GET[$filtro];
        }
    }
}

// Usar filtros da sessão se não houver GET
$filtro_inatividade = $_GET['filtro_inatividade'] ?? ($_SESSION['carteira_filtros']['filtro_inatividade'] ?? '');
$filtro_estado = $_GET['filtro_estado'] ?? ($_SESSION['carteira_filtros']['filtro_estado'] ?? '');
$filtro_vendedor = $_GET['filtro_vendedor'] ?? ($_SESSION['carteira_filtros']['filtro_vendedor'] ?? '');
$filtro_segmento = $_GET['filtro_segmento'] ?? ($_SESSION['carteira_filtros']['filtro_segmento'] ?? '');
$filtro_ano = $_GET['filtro_ano'] ?? ($_SESSION['carteira_filtros']['filtro_ano'] ?? '');
$filtro_mes = $_GET['filtro_mes'] ?? ($_SESSION['carteira_filtros']['filtro_mes'] ?? '');

// Processar parâmetro de pesquisa geral
$pesquisa_geral = $_GET['pesquisa_geral'] ?? '';

// Processar parâmetro para supervisor ver apenas seus próprios clientes
$supervisor_apenas_proprios = $_GET['supervisor_apenas_proprios'] ?? '';

// Configurações de paginação (otimizado para performance)
$itens_por_pagina = 25;
$pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Buscar clientes da carteira
$clientes = [];
$total_clientes = 0;

if ($perfil_permitido && isset($pdo)) {
    try {
        // Construir condições WHERE baseadas no perfil do usuário
        $where_conditions = [];
        $params = [];
        
        // Se for vendedor, filtrar apenas seus clientes
        if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
            $cod_vendedor_formatado = trim($usuario['cod_vendedor']);
            $where_conditions[] = "uf.COD_VENDEDOR = ?";
            $params[] = $cod_vendedor_formatado;
            error_log("DEBUG CARTEIRA_TESTE - Filtro VENDEDOR aplicado: COD_VENDEDOR = " . $cod_vendedor_formatado);
        }
        // Se for representante, filtrar apenas seus clientes
        elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
            $cod_vendedor_formatado = trim($usuario['cod_vendedor']);
            $where_conditions[] = "uf.COD_VENDEDOR = ?";
            $params[] = $cod_vendedor_formatado;
            error_log("DEBUG CARTEIRA_TESTE - Filtro REPRESENTANTE aplicado: COD_VENDEDOR = " . $cod_vendedor_formatado);
        }
        // Se for supervisor, filtrar clientes dos vendedores sob sua supervisão
        elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            $cod_supervisor_formatado = trim($usuario['cod_vendedor']);
            
            // Se o parâmetro supervisor_apenas_proprios estiver ativo, mostrar apenas clientes do próprio supervisor
            if ($supervisor_apenas_proprios === '1') {
                $where_conditions[] = "uf.COD_VENDEDOR = ?";
                $params[] = $cod_supervisor_formatado;
            } else {
                // Comportamento padrão: mostrar clientes da equipe
                $where_conditions[] = "uf.COD_SUPER = ?";
                $params[] = $cod_supervisor_formatado;
            }
        }
        // Se for diretor ou admin, verificar se há filtro de supervisão específico
        elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
            $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
            if (!empty($supervisor_selecionado)) {
                // Comparação robusta independente de zeros à esquerda
                $where_conditions[] = "CAST(uf.COD_SUPER AS UNSIGNED) = CAST(? AS UNSIGNED)";
                $params[] = $supervisor_selecionado;
                if (!empty($filtro_vendedor)) {
                    $where_conditions[] = "uf.COD_VENDEDOR = ?";
                    $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
                }
            }
            // Diretores/admins têm acesso completo aos dados sem limitação de tempo
        }
        
        // Aplicar filtros adicionais
        if (!empty($filtro_estado)) {
            $where_conditions[] = "uf.ESTADO = ?";
            $params[] = $filtro_estado;
        }
        
        if (!empty($filtro_vendedor)) {
            $filtro_vendedor_formatado = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
            $where_conditions[] = "uf.COD_VENDEDOR = ?";
            $params[] = $filtro_vendedor_formatado;
        }
        
        if (!empty($filtro_segmento)) {
            $where_conditions[] = "uf.Descricao1 = ?";
            $params[] = $filtro_segmento;
        }
        
        // Condição de pesquisa geral (busca apenas em CNPJ e nome do cliente)
        if (!empty($pesquisa_geral)) {
            $pesquisa_termo = '%' . $pesquisa_geral . '%';
            $where_conditions[] = "(
                uf.CNPJ LIKE ? OR 
                uf.CLIENTE LIKE ?
            )";
            
            // Adicionar o mesmo termo de pesquisa 2 vezes (para cada campo)
            $params[] = $pesquisa_termo;
            $params[] = $pesquisa_termo;
        }
        
        // Novo filtro de ano/mês
        if (empty($filtro_inatividade)) {
            if (!empty($filtro_ano)) {
                $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
                $params[] = intval($filtro_ano);
            }
            if (!empty($filtro_mes)) {
                $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
                $params[] = intval($filtro_mes);
            }
        }
        
        // Montar WHERE base (sem filtro de excluídos) para consultas de contagem e chaves
        $where_clause_base = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        error_log("DEBUG CARTEIRA_TESTE - WHERE clause base: " . $where_clause_base);
        error_log("DEBUG CARTEIRA_TESTE - Params count: " . count($params));
        if (count($params) > 0) {
            error_log("DEBUG CARTEIRA_TESTE - Params: " . print_r($params, true));
        } else {
            error_log("DEBUG CARTEIRA_TESTE - ATENÇÃO: Nenhum filtro aplicado! WHERE vazio.");
        }

        // Filtro de janela de tempo removido para incluir todos os clientes, mesmo com tempos de compra espaçados
        // $janela_meses = intval($_GET['janela'] ?? 36);
        // if (empty($filtro_inatividade)) {
        //     if ($janela_meses > 0) {
        //         $filtro_janela = "STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') >= DATE_SUB(CURDATE(), INTERVAL {$janela_meses} MONTH)";
        //         $where_clause_base = $where_clause_base ? ($where_clause_base . ' AND ' . $filtro_janela) : ('WHERE ' . $filtro_janela);
        //     }
        // }

        // Condição para ocultar clientes excluídos — aplicar apenas na consulta de detalhes
        // Verificar se as tabelas existem antes de aplicar o filtro
        $cond_excluidos = "";
        try {
            $check_excluidos = $pdo->query("SHOW TABLES LIKE 'clientes_excluidos'");
            $tabela_excluidos_existe = ($check_excluidos->rowCount() > 0);
            $check_restaurados = $pdo->query("SHOW TABLES LIKE 'clientes_restaurados'");
            $tabela_restaurados_existe = ($check_restaurados->rowCount() > 0);
            
            if ($tabela_excluidos_existe || $tabela_restaurados_existe) {
                if ($tabela_excluidos_existe && $tabela_restaurados_existe) {
                    $cond_excluidos = "(NOT EXISTS (
                        SELECT 1 FROM clientes_excluidos ce
                        WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(ce.cnpj, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) =
                              SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                    ) OR EXISTS (
                        SELECT 1 FROM clientes_restaurados cr
                        WHERE cr.raiz_cnpj = SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                    ))";
                } elseif ($tabela_excluidos_existe) {
                    $cond_excluidos = "NOT EXISTS (
                        SELECT 1 FROM clientes_excluidos ce
                        WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(ce.cnpj, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) =
                              SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                    )";
                }
            }
        } catch (PDOException $e) {
            // Se houver erro ao verificar tabelas, não aplicar filtro de excluídos
            error_log("DEBUG - Erro ao verificar tabelas de excluídos: " . $e->getMessage());
        }
        
        // WHERE completo para detalhes (inclui excluídos se a condição foi criada)
        if (!empty($cond_excluidos)) {
            if (!empty($where_clause_base)) {
                $where_clause_detalhes = $where_clause_base . ' AND ' . $cond_excluidos;
            } else {
                $where_clause_detalhes = 'WHERE ' . $cond_excluidos;
            }
        } else {
            $where_clause_detalhes = $where_clause_base; // Pode ser vazio se não houver filtros
        }

        // Referência de mês/ano para agregações
        $ano_ref = null;
        $mes_ref = null;
        $usar_acumulado_ano = false;
        
        // Referência de agregações com novo filtro ano/mês
        if (!empty($filtro_ano)) {
            $ano_ref = intval($filtro_ano);
        } else {
            $ano_ref = intval(date('Y'));
        }
        if (!empty($filtro_mes)) {
            $mes_ref = intval($filtro_mes);
            $usar_acumulado_ano = false;
        } else {
            $mes_ref = null;
            $usar_acumulado_ano = true;
        }
        
        // Consulta base (igual à carteira original) para fallback e para filtros de inatividade quando necessário
        // Montar HAVING para filtrar por status diretamente no banco (mais rápido)
        $having_status_sql = '';
        if (!empty($filtro_inatividade)) {
            if ($filtro_inatividade === 'ativo') {
                $having_status_sql = "HAVING DATEDIFF(CURDATE(), COALESCE(ultima_compra_data, STR_TO_DATE('1900-01-01','%Y-%m-%d'))) <= 290";
            } elseif ($filtro_inatividade === 'inativando') {
                $having_status_sql = "HAVING DATEDIFF(CURDATE(), COALESCE(ultima_compra_data, STR_TO_DATE('1900-01-01','%Y-%m-%d'))) > 290 AND DATEDIFF(CURDATE(), COALESCE(ultima_compra_data, STR_TO_DATE('1900-01-01','%Y-%m-%d'))) <= 365";
            } elseif ($filtro_inatividade === 'inativo') {
                $having_status_sql = "HAVING (ultima_compra_data IS NULL OR DATEDIFF(CURDATE(), ultima_compra_data) > 365)";
            }
        }

        $sql_base = "SELECT 
                    SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                    MAX(uf.CNPJ) as cnpj_representativo,
                    CASE 
                        WHEN MAX(ce.cliente_editado) IS NOT NULL AND MAX(ce.cliente_editado) != '' 
                        THEN MAX(ce.cliente_editado) 
                        ELSE MAX(uf.CLIENTE) 
                    END as cliente,
                    CASE 
                        WHEN MAX(ce.nome_fantasia_editado) IS NOT NULL AND MAX(ce.nome_fantasia_editado) != '' 
                        THEN MAX(ce.nome_fantasia_editado) 
                        ELSE MAX(uf.NOME_FANTASIA) 
                    END as nome_fantasia,
                    CASE 
                        WHEN MAX(ce.estado_editado) IS NOT NULL AND MAX(ce.estado_editado) != '' 
                        THEN MAX(ce.estado_editado) 
                        ELSE MAX(uf.ESTADO) 
                    END as estado,
                    CASE 
                        WHEN MAX(ce.segmento_editado) IS NOT NULL AND MAX(ce.segmento_editado) != '' 
                        THEN MAX(ce.segmento_editado) 
                        ELSE MAX(COALESCE(uf.Descricao1, '')) 
                    END as segmento_atuacao,
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
                    COALESCE(fm.fat_mes, 0) as faturamento_mes,
                    CASE 
                        WHEN MAX(ce.telefone_editado) IS NOT NULL AND MAX(ce.telefone_editado) != '' 
                        THEN MAX(ce.telefone_editado)
                        ELSE 
                            CASE 
                                WHEN MAX(uf.DDD) IS NOT NULL AND MAX(uf.DDD) != '' AND MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' 
                                THEN CONCAT('(0', MAX(uf.DDD), ') ', MAX(uf.TELEFONE))
                                WHEN MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' 
                                THEN MAX(uf.TELEFONE)
                                WHEN MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) != '' 
                                THEN MAX(uf.TELEFONE2)
                                ELSE 'N/A'
                            END
                    END as telefone,
                    MAX(ultima_ligacao_cliente.data_ligacao) as ultima_ligacao,
                    MAX(ce.data_edicao) as data_edicao,
                    MAX(ce.usuario_editor) as usuario_editor,
                    CASE WHEN MAX(ce.id) IS NOT NULL THEN 1 ELSE 0 END as tem_edicao,
                    MAX(uf.GrpVendas) as grupo_vendas,
                    MAX(uf.Descricao) as nome_grupo,
                    CASE WHEN MAX(cl.id) IS NOT NULL THEN 1 ELSE 0 END as marcado_como_ligado
                FROM ultimo_faturamento uf
                LEFT JOIN (
                    SELECT 
                        SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                        SUM(f.VLR_TOTAL) as fat_mes
                    FROM FATURAMENTO f
                    WHERE f.EMISSAO IS NOT NULL 
                      AND f.EMISSAO != ''
                      AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = {$ano_ref}
                      " . ($usar_acumulado_ano ? "" : "AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = {$mes_ref}") . "
                    GROUP BY raiz_cnpj
                ) fm ON fm.raiz_cnpj = SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                LEFT JOIN CLIENTES_EDICOES ce ON uf.CNPJ = ce.cnpj_original AND ce.ativo = 1
                LEFT JOIN (
                    SELECT 
                        cliente_id,
                        MAX(data_ligacao) as data_ligacao
                    FROM LIGACOES 
                    WHERE status = 'finalizada'
                    GROUP BY cliente_id
                ) ultima_ligacao_cliente ON ultima_ligacao_cliente.cliente_id = uf.CNPJ
                LEFT JOIN clientes_ligados cl 
                    ON cl.usuario_id = ? 
                    AND cl.raiz_cnpj = SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                " . (!empty($where_clause_detalhes) ? $where_clause_detalhes : '') . " 
                GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                " . ($having_status_sql ? (" " . $having_status_sql . " ") : "") . "
                ORDER BY 
                    CASE WHEN MAX(ultima_ligacao_cliente.data_ligacao) IS NULL THEN 0 ELSE 1 END,
                    ultima_compra_data DESC, 
                    valor_total DESC";

        $total_registros = 0;
        $total_paginas = 0;
        $total_faturamento_mes = 0;

        if (empty($filtro_inatividade)) {
            // Caminho otimizado: paginação real no banco
            // 1) Raizes da página com ordenação (aplica filtro de excluídos também) — oversampling para compensar exclusões
            $oversample = 2;
            $limit_raizes = $itens_por_pagina * $oversample;
            $offset = ($pagina_atual - 1) * $itens_por_pagina;

            // Limitar linhas antes do agrupamento para evitar varredura completa da tabela
            $is_diretor_admin = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin']);
            $limit_pre_group = $is_diretor_admin ? 25000 : 100000; // limite menor para diretores/admins
            $sql_raizes = "
                SELECT r.raiz_cnpj
                FROM (
                    SELECT 
                        SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,
                        MAX(STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) AS ultima_compra_data,
                        MAX(ultima_ligacao_cliente.data_ligacao) AS ultima_ligacao
                    FROM ultimo_faturamento uf
                    LEFT JOIN (
                        SELECT 
                            cliente_id,
                            MAX(data_ligacao) as data_ligacao
                        FROM LIGACOES 
                        WHERE status = 'finalizada'
                        GROUP BY cliente_id
                    ) ultima_ligacao_cliente ON ultima_ligacao_cliente.cliente_id = uf.CNPJ
                    " . (!empty($where_clause_detalhes) ? $where_clause_detalhes : '') . "
                    GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                    ORDER BY 
                        CASE WHEN MAX(ultima_ligacao_cliente.data_ligacao) IS NULL THEN 0 ELSE 1 END,
                        ultima_compra_data DESC, 
                        raiz_cnpj
                    LIMIT $limit_raizes OFFSET $offset
                ) r
            ";
            try {
                // Preparar parâmetros
                $params_raizes = $params;
                error_log("DEBUG - Executando sql_raizes com " . count($params_raizes) . " parâmetros");
                error_log("DEBUG - WHERE clause: " . substr($where_clause_detalhes, 0, 200));
                $stmt_raizes = $pdo->prepare($sql_raizes);
                $stmt_raizes->execute($params_raizes);
                $raizes = $stmt_raizes->fetchAll(PDO::FETCH_COLUMN);
                $stmt_raizes->closeCursor();
                
                // Debug: verificar se encontrou raizes
                error_log("DEBUG - Raizes encontradas: " . count($raizes));
                error_log("DEBUG - Params count: " . count($params));
                error_log("DEBUG - SQL raizes executado com sucesso");
                
                if (count($raizes) > 0) {
                    error_log("DEBUG - Primeiras 3 raizes: " . implode(', ', array_slice($raizes, 0, 3)));
                }
            } catch (PDOException $e) {
                error_log("ERRO ao executar sql_raizes: " . $e->getMessage());
                error_log("ERRO - SQL raizes: " . substr($sql_raizes, 0, 1000));
                error_log("ERRO - Params: " . print_r($params_raizes, true));
                $raizes = [];
            }

            $clientes = [];
            $total_clientes = 0;

            if (empty($raizes) || !is_array($raizes) || count($raizes) == 0) {
                error_log("DEBUG - AVISO: Nenhuma raiz CNPJ encontrada! Verifique filtros e permissões.");
                error_log("DEBUG - Params usados: " . print_r($params, true));
                error_log("DEBUG - WHERE clause detalhes: " . $where_clause_detalhes);
                error_log("DEBUG - Perfil usuário: " . ($usuario['perfil'] ?? 'NÃO DEFINIDO'));
                error_log("DEBUG - COD_VENDEDOR: " . ($usuario['cod_vendedor'] ?? 'NÃO DEFINIDO'));
                
                // Teste simples: verificar se há clientes na tabela sem filtros
                try {
                    $sql_teste_simples = "SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total FROM ultimo_faturamento LIMIT 1";
                    $stmt_teste_simples = $pdo->query($sql_teste_simples);
                    $resultado_teste_simples = $stmt_teste_simples->fetch(PDO::FETCH_ASSOC);
                    error_log("DEBUG - Total geral de raizes CNPJ na tabela (sem filtros): " . ($resultado_teste_simples['total'] ?? 0));
                } catch (PDOException $e) {
                    error_log("DEBUG - Erro no teste simples: " . $e->getMessage());
                }
                
                // Teste com filtros de perfil apenas
                try {
                    $sql_teste_perfil = "SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total FROM ultimo_faturamento uf " . $where_clause_base . " LIMIT 1";
                    $stmt_teste_perfil = $pdo->prepare($sql_teste_perfil);
                    $stmt_teste_perfil->execute($params);
                    $resultado_teste_perfil = $stmt_teste_perfil->fetch(PDO::FETCH_ASSOC);
                    error_log("DEBUG - Total com filtros de perfil (sem excluídos): " . ($resultado_teste_perfil['total'] ?? 0));
                } catch (PDOException $e) {
                    error_log("DEBUG - Erro no teste de perfil: " . $e->getMessage());
                }
            }

            if (!empty($raizes) && is_array($raizes) && count($raizes) > 0) {
                $in_placeholders = implode(',', array_fill(0, count($raizes), '?'));
                $order_placeholders = $in_placeholders;

                // 3) Detalhes apenas das raizes da página (sem subselect pesado de LIGACOES e FATURAMENTO)
                $sql_clientes = "
                    SELECT 
                        SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,
                        MAX(uf.CNPJ) AS cnpj_representativo,
                        CASE 
                            WHEN MAX(ce.cliente_editado) IS NOT NULL AND MAX(ce.cliente_editado) != '' THEN MAX(ce.cliente_editado) 
                            ELSE MAX(uf.CLIENTE) 
                        END AS cliente,
                        CASE 
                            WHEN MAX(ce.nome_fantasia_editado) IS NOT NULL AND MAX(ce.nome_fantasia_editado) != '' THEN MAX(ce.nome_fantasia_editado) 
                            ELSE MAX(uf.NOME_FANTASIA) 
                        END AS nome_fantasia,
                        CASE 
                            WHEN MAX(ce.estado_editado) IS NOT NULL AND MAX(ce.estado_editado) != '' THEN MAX(ce.estado_editado) 
                            ELSE MAX(uf.ESTADO) 
                        END AS estado,
                        CASE 
                            WHEN MAX(ce.segmento_editado) IS NOT NULL AND MAX(ce.segmento_editado) != '' THEN MAX(ce.segmento_editado) 
                            ELSE MAX(COALESCE(uf.Descricao1, '')) 
                        END AS segmento_atuacao,
                        COALESCE(uf.COD_VENDEDOR, '') AS cod_vendedor,
                        MAX(uf.NOME_VENDEDOR) AS nome_vendedor,
                        COALESCE(uf.COD_SUPER, '') AS cod_supervisor,
                        MAX(uf.FANT_SUPER) AS nome_supervisor,
                        COUNT(*) AS total_pedidos,
                        SUM(uf.VLR_TOTAL) AS valor_total,
                        DATE_FORMAT(MAX(CASE 
                            WHEN uf.DT_FAT IS NOT NULL AND uf.DT_FAT != '' 
                                 AND STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') IS NOT NULL 
                            THEN STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') 
                        END), '%d/%m/%Y') AS ultima_compra,
                        MIN(uf.DT_FAT) AS primeira_compra,
                        MAX(CASE 
                            WHEN uf.DT_FAT IS NOT NULL AND uf.DT_FAT != '' 
                                 AND STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') IS NOT NULL 
                            THEN STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') 
                        END) AS ultima_compra_data,
                        COUNT(DISTINCT uf.CNPJ) AS total_cnpjs_diferentes,
                        0 AS faturamento_mes,
                        CASE 
                            WHEN MAX(ce.telefone_editado) IS NOT NULL AND MAX(ce.telefone_editado) != '' THEN MAX(ce.telefone_editado)
                            ELSE 
                                CASE 
                                    WHEN MAX(uf.DDD) IS NOT NULL AND MAX(uf.DDD) != '' AND MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' 
                                        THEN CONCAT('(0', MAX(uf.DDD), ') ', MAX(uf.TELEFONE))
                                    WHEN MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' THEN MAX(uf.TELEFONE)
                                    WHEN MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) != '' THEN MAX(uf.TELEFONE2)
                                    ELSE 'N/A'
                                END
                        END AS telefone,
                        MAX(ultima_ligacao_cliente.data_ligacao) AS ultima_ligacao,
                        MAX(ce.data_edicao) AS data_edicao,
                        MAX(ce.usuario_editor) AS usuario_editor,
                        CASE WHEN MAX(ce.id) IS NOT NULL THEN 1 ELSE 0 END AS tem_edicao,
                        MAX(uf.GrpVendas) AS grupo_vendas,
                        MAX(uf.Descricao) AS nome_grupo,
                        CASE WHEN MAX(cl.id) IS NOT NULL THEN 1 ELSE 0 END AS marcado_como_ligado
                    FROM ultimo_faturamento uf
                    LEFT JOIN CLIENTES_EDICOES ce 
                        ON uf.CNPJ = ce.cnpj_original AND ce.ativo = 1
                    LEFT JOIN (
                        SELECT 
                            cliente_id,
                            MAX(data_ligacao) as data_ligacao
                        FROM LIGACOES 
                        WHERE status = 'finalizada'
                        GROUP BY cliente_id
                    ) ultima_ligacao_cliente ON ultima_ligacao_cliente.cliente_id = uf.CNPJ
                    LEFT JOIN clientes_ligados cl 
                        ON cl.usuario_id = ? 
                        AND cl.raiz_cnpj = SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                    " . (!empty($where_clause_detalhes) ? ($where_clause_detalhes . " AND ") : "WHERE ") . "
                      SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) IN ($in_placeholders)
                    GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
                    ORDER BY FIELD(
                        SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8),
                        $order_placeholders
                    )
                ";

                // Parâmetros: usuario_id para JOIN + filtros + raízes para IN + raízes para ORDER BY FIELD
                $params_clientes = array_merge([$usuario['id']], $params, $raizes, $raizes);
                
                // Debug
                error_log("DEBUG - Params clientes count: " . count($params_clientes));
                error_log("DEBUG - Esperados: " . (1 + count($params) + count($raizes) + count($raizes)) . " (1 usuario_id + " . count($params) . " filtros + " . count($raizes) . " raízes IN + " . count($raizes) . " raízes ORDER)");
                
                try {
                    $stmt_clientes = $pdo->prepare($sql_clientes);
                    $stmt_clientes->execute($params_clientes);
                    $clientes_full = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
                    $stmt_clientes->closeCursor();
                    
                    error_log("DEBUG - Clientes encontrados: " . count($clientes_full));
                } catch (PDOException $e) {
                    error_log("ERRO na consulta clientes: " . $e->getMessage());
                    error_log("ERRO - SQL: " . substr($sql_clientes, 0, 1000));
                    error_log("ERRO - Params: " . print_r($params_clientes, true));
                    $clientes_full = [];
                }
                
                // Debug: verificar se última ligação está sendo carregada
                if (!empty($clientes_full)) {
                    $debug_ligacao = array_filter($clientes_full, function($cli) {
                        return !empty($cli['ultima_ligacao']);
                    });
                    error_log("DEBUG - Clientes com última ligação: " . count($debug_ligacao) . " de " . count($clientes_full));
                }

                // Última ligação já incluída na consulta principal
                // Garantir tamanho da página após aplicar excluídos
                $clientes = array_slice($clientes_full, 0, $itens_por_pagina);
                $total_clientes = count($clientes);
                $has_next = count($clientes_full) > $itens_por_pagina;
            } else {
                error_log("DEBUG - AVISO: Array de raizes vazio! Nenhum cliente será retornado.");
                error_log("DEBUG - Tentando query alternativa simples para diagnóstico...");
                // Tentar uma query simples para verificar se há dados no banco
                try {
                    $sql_teste = "SELECT COUNT(*) as total FROM ultimo_faturamento " . $where_clause_base;
                    $stmt_teste = $pdo->prepare($sql_teste);
                    $stmt_teste->execute($params);
                    $resultado_teste = $stmt_teste->fetch(PDO::FETCH_ASSOC);
                    error_log("DEBUG - Total de registros em ultimo_faturamento com filtros aplicados: " . ($resultado_teste['total'] ?? 0));
                } catch (PDOException $e) {
                    error_log("DEBUG - Erro ao executar query de teste: " . $e->getMessage());
                }
                $clientes = [];
                $total_clientes = 0;
                $has_next = false;
            }

            // Faturamento para a página: calcular em consulta separada e restrita às raizes
            $total_faturamento_mes = 0;
            if (!empty($clientes)) {
                $raizes_pagina = array_column($clientes, 'raiz_cnpj');
                $placeholders_raiz = implode(',', array_fill(0, count($raizes_pagina), '?'));
                $sql_fat_page = "
                    SELECT 
                        SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,
                        SUM(f.VLR_TOTAL) AS fat_mes
                    FROM FATURAMENTO f
                    WHERE f.EMISSAO IS NOT NULL AND f.EMISSAO != ''
                      AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = {$ano_ref}
                      " . ($usar_acumulado_ano ? "" : "AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = {$mes_ref}") . "
                      AND SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) IN ($placeholders_raiz)
                    GROUP BY raiz_cnpj
                ";
                $stmt_fat_page = $pdo->prepare($sql_fat_page);
                $stmt_fat_page->execute($raizes_pagina);
                $fat_rows = $stmt_fat_page->fetchAll(PDO::FETCH_ASSOC);
                $stmt_fat_page->closeCursor();
                $map_fat = [];
                foreach ($fat_rows as $r) { $map_fat[$r['raiz_cnpj']] = (float)$r['fat_mes']; }
                foreach ($clientes as &$c) {
                    $c['faturamento_mes'] = $map_fat[$c['raiz_cnpj']] ?? 0;
                    $total_faturamento_mes += $c['faturamento_mes'];
                }
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
                " . (!empty($where_clause_detalhes) ? $where_clause_detalhes : '') . "
            ";
            
            // Preparar parâmetros para contagem
            $params_count = $params;
            
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute($params_count);
            $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
            $total_registros = (int)($count_result['total'] ?? 0);
            $total_paginas = (int)ceil($total_registros / $itens_por_pagina);
            
            // Ajustar página atual se necessário
            if ($pagina_atual > $total_paginas && $total_paginas > 0) {
                $pagina_atual = $total_paginas;
            }
            
            // Garantir que $offset seja definido corretamente para a exibição
            $offset_display = ($pagina_atual - 1) * $itens_por_pagina;
        } else {
            // Caminho quando filtro de inatividade está ativo: banco já filtra via HAVING
            // usuario_id deve ser o primeiro parâmetro para o LEFT JOIN com clientes_ligados
            $params_base = array_merge([$usuario['id']], $params);
            $stmt_todos = $pdo->prepare($sql_base);
            $stmt_todos->execute($params_base);
            $todos_clientes = $stmt_todos->fetchAll(PDO::FETCH_ASSOC);

            // Paginação no PHP (compatível)
            $total_registros = count($todos_clientes);
            $total_paginas = (int)ceil($total_registros / $itens_por_pagina);
            if ($pagina_atual > $total_paginas && $total_paginas > 0) { $pagina_atual = $total_paginas; }
            $offset = ($pagina_atual - 1) * $itens_por_pagina;
            $clientes = array_slice($todos_clientes, $offset, $itens_por_pagina);
            $total_clientes = count($clientes);
            
            // Garantir que $offset seja definido corretamente para a exibição
            $offset_display = $offset;

            // Total do faturamento (todos os clientes filtrados)
            $total_faturamento_mes = 0;
            foreach ($todos_clientes as $cliente) {
                $total_faturamento_mes += (float)($cliente['faturamento_mes'] ?? 0);
            }
        }

        // Buscar metas do usuário logado (sem alterações)
        $metafat = 0;
        $faturamento_mes_atual = 0;
        
        try {
            // Buscar metas do usuário baseado no perfil
            $cod_vendedor_meta = $usuario['cod_vendedor'];
            
            // Se for supervisor, buscar metas da equipe ou apenas suas próprias
            if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
                $cod_supervisor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
                
                if ($supervisor_apenas_proprios === '1') {
                    // Modo individual: buscar apenas a meta do próprio supervisor
                    $sql_metas = "SELECT META_FATURAMENTO as metafat FROM USUARIOS WHERE COD_VENDEDOR = ?";
                    $stmt_metas = $pdo->prepare($sql_metas);
                    $stmt_metas->execute([$cod_supervisor_formatado]);
                } else {
                    // Modo equipe: buscar metas de toda a equipe
                    $sql_metas = "SELECT SUM(META_FATURAMENTO) as metafat FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')";
                    $stmt_metas = $pdo->prepare($sql_metas);
                    $stmt_metas->execute([$cod_supervisor_formatado]);
                }
            }
            // Se for diretor, buscar metas de todos os vendedores
            elseif (strtolower(trim($usuario['perfil'])) === 'diretor') {
                $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
                if (!empty($supervisor_selecionado)) {
                    $supervisor_formatado = str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT);
                    $sql_metas = "SELECT SUM(META_FATURAMENTO) as metafat FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')";
                    $stmt_metas = $pdo->prepare($sql_metas);
                    $stmt_metas->execute([$supervisor_formatado]);
                } else {
                    $sql_metas = "SELECT SUM(META_FATURAMENTO) as metafat FROM USUARIOS WHERE ATIVO = 1";
                    $stmt_metas = $pdo->prepare($sql_metas);
                    $stmt_metas->execute();
                }
            }
            // Para vendedor individual
            else {
                $sql_metas = "SELECT META_FATURAMENTO as metafat FROM USUARIOS WHERE COD_VENDEDOR = ?";
                $stmt_metas = $pdo->prepare($sql_metas);
                $stmt_metas->execute([$cod_vendedor_meta]);
            }
            
            $metas = $stmt_metas->fetch(PDO::FETCH_ASSOC);
            if ($metas) {
                $metafat = floatval($metas['metafat'] ?? 0);
            }
            
            // Calcular faturamento do mês atual baseado no perfil
            $mes_atual = date('m');
            $ano_atual = date('Y');
            
            // Preparar condições WHERE baseadas no perfil
            $where_conditions_fat = [];
            $params_fat = [];
            
            if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
                if ($supervisor_apenas_proprios === '1') {
                    // Modo individual: apenas faturamento do próprio supervisor
                    $where_conditions_fat[] = "COD_VENDEDOR = ?";
                    $params_fat[] = $usuario['cod_vendedor'];
                } else {
                    // Modo equipe: faturamento de toda a equipe
                    $where_conditions_fat[] = "COD_VENDEDOR IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante'))";
                    $params_fat[] = $usuario['cod_vendedor'];
                }
            } elseif (strtolower(trim($usuario['perfil'])) === 'diretor') {
                $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
                if (!empty($supervisor_selecionado)) {
                    $where_conditions_fat[] = "COD_VENDEDOR IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante'))";
                    $params_fat[] = $supervisor_selecionado;
                } else {
                    $where_conditions_fat[] = "COD_VENDEDOR IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE ATIVO = 1)";
                }
            } else {
                $where_conditions_fat[] = "COD_VENDEDOR = ?";
                $params_fat[] = $usuario['cod_vendedor'];
            }
            
            $where_conditions_fat[] = "MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
            $where_conditions_fat[] = "YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
            $params_fat[] = $mes_atual;
            $params_fat[] = $ano_atual;
            
            $where_clause_fat = implode(' AND ', $where_conditions_fat);
            
            $sql_faturamento_mes = "SELECT SUM(VLR_TOTAL) as total_faturamento FROM FATURAMENTO WHERE $where_clause_fat";
            $stmt_faturamento = $pdo->prepare($sql_faturamento_mes);
            $stmt_faturamento->execute($params_fat);
            $faturamento_result = $stmt_faturamento->fetch(PDO::FETCH_ASSOC);
            
            if (!$faturamento_result || $faturamento_result['total_faturamento'] == 0) {
                $sql_faturamento_mes = "SELECT SUM(VLR_TOTAL) as total_faturamento FROM ultimo_faturamento WHERE $where_clause_fat";
                $stmt_faturamento = $pdo->prepare($sql_faturamento_mes);
                $stmt_faturamento->execute($params_fat);
                $faturamento_result = $stmt_faturamento->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($faturamento_result) {
                $faturamento_mes_atual = floatval($faturamento_result['total_faturamento'] ?? 0);
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar metas e faturamento: " . $e->getMessage());
        }
        
        // Calcular percentual atingido
        $percentual_fat = $metafat > 0 ? ($faturamento_mes_atual / $metafat) * 100 : 0;
        
        // Calcular percentual geral de atingimento (cumulativo)
        $percentual_geral = 0;
        $mes_atual = date('n');
        
        // Buscar faturamento total do ano até agora
        $sql_faturamento_total = "SELECT SUM(VLR_TOTAL) as total_faturamento_ano FROM FATURAMENTO WHERE $where_clause_fat AND YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = YEAR(CURDATE())";
        $stmt_faturamento_total = $pdo->prepare($sql_faturamento_total);
        $stmt_faturamento_total->execute($params_fat);
        $faturamento_total_ano = floatval($stmt_faturamento_total->fetch(PDO::FETCH_ASSOC)['total_faturamento_ano'] ?? 0);
        
        // Calcular meta cumulativa (meta mensal * mês atual)
        $meta_fat_cumulativa = $metafat * $mes_atual;
        
        if ($meta_fat_cumulativa > 0 && $faturamento_total_ano > 0) {
            $percentual_geral = ($faturamento_total_ano / $meta_fat_cumulativa) * 100;
        }
        
    } catch (PDOException $e) {
        $error_message = "Erro ao buscar clientes: " . $e->getMessage();
        error_log("ERRO CARTEIRA - PDOException: " . $e->getMessage());
        error_log("ERRO CARTEIRA - SQL: " . (isset($sql_base) ? substr($sql_base, 0, 500) : 'SQL não definido'));
        // Garantir que variáveis estejam definidas mesmo em caso de erro
        if (!isset($clientes)) {
            $clientes = [];
        }
        if (!isset($total_registros)) {
            $total_registros = 0;
        }
        if (!isset($total_paginas)) {
            $total_paginas = 1;
        }
        if (!isset($total_faturamento_mes)) {
            $total_faturamento_mes = 0;
        }
    }
} else {
    if (!$perfil_permitido) {
        $error_message = "Você não tem permissão para acessar a carteira de clientes. Entre em contato com o administrador do sistema.";
    } elseif (!isset($pdo)) {
        $error_message = "Erro de conexão com o banco de dados. Tente novamente mais tarde.";
    }
    // Garantir que variáveis estejam definidas mesmo quando não há permissão
    if (!isset($clientes)) {
        $clientes = [];
    }
    if (!isset($total_registros)) {
        $total_registros = 0;
    }
    if (!isset($total_paginas)) {
        $total_paginas = 1;
    }
    if (!isset($total_faturamento_mes)) {
        $total_faturamento_mes = 0;
    }
}

// Garantir que variáveis de paginação estejam sempre definidas antes de incluir a tabela
if (!isset($itens_por_pagina)) {
    $itens_por_pagina = 25;
}
if (!isset($pagina_atual)) {
    $pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
}
if (!isset($total_registros)) {
    $total_registros = 0;
}
if (!isset($total_paginas)) {
    $total_paginas = 1;
}
if (!isset($clientes)) {
    $clientes = [];
}

// Buscar estados, vendedores e segmentos para os filtros (mesma lógica da página original)
$estados = [];
$vendedores = [];
$segmentos = [];

if (isset($pdo)) {
    try {
        // Buscar estados únicos baseado no perfil do usuário
        if (strtolower(trim($usuario['perfil'])) === 'vendedor' || strtolower(trim($usuario['perfil'])) === 'representante') {
            $sql_estados = "SELECT DISTINCT ESTADO FROM ultimo_faturamento WHERE ESTADO IS NOT NULL AND ESTADO != '' AND COD_VENDEDOR = ? ORDER BY ESTADO";
            $stmt_estados = $pdo->prepare($sql_estados);
            $stmt_estados->execute([$usuario['cod_vendedor']]);
        } elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            if ($supervisor_apenas_proprios === '1') {
                // Modo individual: apenas estados dos próprios clientes do supervisor
                $sql_estados = "SELECT DISTINCT ESTADO FROM ultimo_faturamento WHERE ESTADO IS NOT NULL AND ESTADO != '' AND COD_VENDEDOR = ? ORDER BY ESTADO";
                $stmt_estados = $pdo->prepare($sql_estados);
                $stmt_estados->execute([$usuario['cod_vendedor']]);
            } else {
                // Modo equipe: estados de todos os vendedores da equipe
                $sql_estados = "SELECT DISTINCT uf.ESTADO FROM ultimo_faturamento uf 
                               INNER JOIN USUARIOS u ON uf.COD_VENDEDOR = u.COD_VENDEDOR 
                               WHERE uf.ESTADO IS NOT NULL AND uf.ESTADO != '' 
                               AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                               ORDER BY uf.ESTADO";
                $stmt_estados = $pdo->prepare($sql_estados);
                $stmt_estados->execute([$usuario['cod_vendedor']]);
            }
        } elseif ((strtolower(trim($usuario['perfil'])) === 'diretor' || strtolower(trim($usuario['perfil'])) === 'admin') && !empty($supervisor_selecionado)) {
            $sql_estados = "SELECT DISTINCT uf.ESTADO FROM ultimo_faturamento uf 
                           INNER JOIN USUARIOS u ON uf.COD_VENDEDOR = u.COD_VENDEDOR 
                           WHERE uf.ESTADO IS NOT NULL AND uf.ESTADO != '' 
                           AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                           ORDER BY uf.ESTADO";
            $stmt_estados = $pdo->prepare($sql_estados);
            $stmt_estados->execute([$supervisor_selecionado]);
        } else {
            $sql_estados = "SELECT DISTINCT ESTADO FROM ultimo_faturamento WHERE ESTADO IS NOT NULL AND ESTADO != '' ORDER BY ESTADO";
            $stmt_estados = $pdo->query($sql_estados);
        }
        
        $estados = $stmt_estados->fetchAll(PDO::FETCH_COLUMN);
        
        // Buscar vendedores únicos baseado no perfil do usuário e visão selecionada
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        
        if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            if ($supervisor_apenas_proprios === '1') {
                // Modo individual: apenas o próprio supervisor
                $sql_vendedores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO as NOME_VENDEDOR 
                                   FROM USUARIOS u 
                                   INNER JOIN ultimo_faturamento uf ON uf.COD_VENDEDOR = u.COD_VENDEDOR 
                                   WHERE u.COD_VENDEDOR = ? AND u.ATIVO = 1
                                   AND uf.COD_VENDEDOR IS NOT NULL AND uf.COD_VENDEDOR != ''
                                   ORDER BY u.NOME_COMPLETO";
                $stmt_vendedores = $pdo->prepare($sql_vendedores);
                $stmt_vendedores->execute([$usuario['cod_vendedor']]);
            } else {
                // Modo equipe: todos os vendedores da equipe
                $sql_vendedores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO as NOME_VENDEDOR 
                                   FROM USUARIOS u 
                                   INNER JOIN ultimo_faturamento uf ON uf.COD_VENDEDOR = u.COD_VENDEDOR 
                                   WHERE u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                                   AND uf.COD_VENDEDOR IS NOT NULL AND uf.COD_VENDEDOR != ''
                                   ORDER BY u.NOME_COMPLETO";
                $stmt_vendedores = $pdo->prepare($sql_vendedores);
                $stmt_vendedores->execute([$usuario['cod_vendedor']]);
            }
        } elseif ((strtolower(trim($usuario['perfil'])) === 'diretor' || strtolower(trim($usuario['perfil'])) === 'admin') && !empty($supervisor_selecionado)) {
            $sql_vendedores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO as NOME_VENDEDOR 
                               FROM USUARIOS u 
                               INNER JOIN ultimo_faturamento uf ON uf.COD_VENDEDOR = u.COD_VENDEDOR 
                               WHERE u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                               AND uf.COD_VENDEDOR IS NOT NULL AND uf.COD_VENDEDOR != ''
                               ORDER BY u.NOME_COMPLETO";
            $stmt_vendedores = $pdo->prepare($sql_vendedores);
            $stmt_vendedores->execute([$supervisor_selecionado]);
        } else {
            $sql_vendedores = "SELECT DISTINCT COD_VENDEDOR, NOME_VENDEDOR FROM ultimo_faturamento WHERE COD_VENDEDOR IS NOT NULL AND COD_VENDEDOR != '' ORDER BY NOME_VENDEDOR";
            $stmt_vendedores = $pdo->query($sql_vendedores);
        }
        
        $vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar segmentos únicos baseado no perfil do usuário
        if (strtolower(trim($usuario['perfil'])) === 'vendedor' || strtolower(trim($usuario['perfil'])) === 'representante') {
            $sql_segmentos = "SELECT DISTINCT Descricao1 FROM ultimo_faturamento WHERE Descricao1 IS NOT NULL AND Descricao1 != '' AND COD_VENDEDOR = ? ORDER BY Descricao1";
            $stmt_segmentos = $pdo->prepare($sql_segmentos);
            $stmt_segmentos->execute([$usuario['cod_vendedor']]);
        } elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            if ($supervisor_apenas_proprios === '1') {
                // Modo individual: apenas segmentos dos próprios clientes do supervisor
                $sql_segmentos = "SELECT DISTINCT Descricao1 FROM ultimo_faturamento WHERE Descricao1 IS NOT NULL AND Descricao1 != '' AND COD_VENDEDOR = ? ORDER BY Descricao1";
                $stmt_segmentos = $pdo->prepare($sql_segmentos);
                $stmt_segmentos->execute([$usuario['cod_vendedor']]);
            } else {
                // Modo equipe: segmentos de todos os vendedores da equipe
                $sql_segmentos = "SELECT DISTINCT uf.Descricao1 FROM ultimo_faturamento uf 
                                  INNER JOIN USUARIOS u ON uf.COD_VENDEDOR = u.COD_VENDEDOR 
                                  WHERE uf.Descricao1 IS NOT NULL AND uf.Descricao1 != '' 
                                  AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                                  ORDER BY uf.Descricao1";
                $stmt_segmentos = $pdo->prepare($sql_segmentos);
                $stmt_segmentos->execute([$usuario['cod_vendedor']]);
            }
        } elseif ((strtolower(trim($usuario['perfil'])) === 'diretor' || strtolower(trim($usuario['perfil'])) === 'admin') && !empty($supervisor_selecionado)) {
            $sql_segmentos = "SELECT DISTINCT uf.Descricao1 FROM ultimo_faturamento uf 
                              INNER JOIN USUARIOS u ON uf.COD_VENDEDOR = u.COD_VENDEDOR 
                              WHERE uf.Descricao1 IS NOT NULL AND uf.Descricao1 != '' 
                              AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                              ORDER BY uf.Descricao1";
            $stmt_segmentos = $pdo->prepare($sql_segmentos);
            $stmt_segmentos->execute([$supervisor_selecionado]);
        } else {
            $sql_segmentos = "SELECT DISTINCT Descricao1 FROM ultimo_faturamento WHERE Descricao1 IS NOT NULL AND Descricao1 != '' ORDER BY Descricao1";
            $stmt_segmentos = $pdo->query($sql_segmentos);
        }
        
        $segmentos = $stmt_segmentos->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Ignorar erros nos filtros
    }
}

// Ordenar clientes por última compra (mais recente primeiro) e depois por faturamento
usort($clientes, function($a, $b) {
    // Primeiro ordena por última compra (mais recente primeiro)
    $data_a = !empty($a['ultima_compra_data']) ? strtotime($a['ultima_compra_data']) : 0;
    $data_b = !empty($b['ultima_compra_data']) ? strtotime($b['ultima_compra_data']) : 0;
    if ($data_a !== $data_b) {
        return $data_b <=> $data_a; // Descendente
    }
    // Se mesma data, ordena por faturamento (maior primeiro)
    $fat_a = (float)($a['faturamento_mes'] ?? 0);
    $fat_b = (float)($b['faturamento_mes'] ?? 0);
    return $fat_b <=> $fat_a; // Descendente
});
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Carteira de Clientes - Autopel (Teste Otimizada)</title>
    <meta name="viewport" content="width=device-width, initial-scale=0.8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="usuario-id" content="<?php echo $usuario['id'] ?? ''; ?>">
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/correcao-carteira-mobile.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/carteira.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/modais.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/calendario.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/carteira-optimized.css'); ?>?v=<?php echo time(); ?>">
    <style>
        /* Correção para ocupar toda a largura da página - PRIORIDADE MÁXIMA */
        .dashboard-layout,
        .dashboard-layout.sidebar-visible,
        .dashboard-layout:not(.sidebar-visible) {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            display: flex !important;
            min-height: 100vh !important;
        }

        .dashboard-container,
        .dashboard-layout.sidebar-visible .dashboard-container,
        .dashboard-layout:not(.sidebar-visible) .dashboard-container {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 100vh !important;
            box-sizing: border-box !important;
        }

        .dashboard-main,
        .dashboard-layout.sidebar-visible .dashboard-main,
        .dashboard-layout:not(.sidebar-visible) .dashboard-main {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding: 1rem !important;
            flex: 1 !important;
        }

        .carteira-container,
        .dashboard-layout.sidebar-visible .carteira-container,
        .dashboard-layout:not(.sidebar-visible) .carteira-container {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding: 1rem !important;
        }

        /* Esconder completamente a sidebar */
        .sidebar-modern {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            z-index: -9999 !important;
        }
        
        /* Forçar largura total em todos os elementos principais */
        .main-content,
        .main-content-modern {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        /* Estilos específicos para o gráfico de status dos clientes */
        .clientes-chart-container {
            width: 100% !important;
            height: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0.5rem !important;
        }
        
        #statusClientesChart {
            width: 140px !important;
            height: 140px !important;
            max-width: 140px !important;
            max-height: 140px !important;
        }
        
        .clientes-status-section {
            width: 100% !important;
            height: 100% !important;
        }
        
        .projecoes-clientes-container {
            width: 100% !important;
            min-height: 220px !important;
            height: auto !important;
            padding: 1rem !important;
        }
        
        @media (max-width: 768px) {
            .dashboard-main {
                padding: 0.5rem !important;
            }
            
            .carteira-container {
                padding: 0.5rem !important;
            }
            
            #statusClientesChart {
                width: 110px !important;
                height: 110px !important;
                max-width: 110px !important;
                max-height: 110px !important;
            }
            
            .projecoes-clientes-container {
                min-height: 200px !important;
                height: auto !important;
                padding: 0.75rem !important;
            }
            
            .clientes-chart-container {
                padding: 0.5rem !important;
                overflow: visible !important;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-main {
                padding: 0.25rem !important;
            }
            
            .carteira-container {
                padding: 0.25rem !important;
            }
        }
        
        /* Estilos para marcação de clientes ligados */
        .checkbox-ligado {
            width: 20px !important;
            height: 20px !important;
            cursor: pointer;
            accent-color: #28a745;
            transition: all 0.2s ease;
        }
        
        .checkbox-ligado:hover {
            transform: scale(1.1);
        }
        
        .checkbox-ligado:disabled {
            opacity: 0.6;
            cursor: wait;
        }
        
        .cliente-marcado-ligado {
            background-color: #f0fff4 !important;
            border-left: 3px solid #28a745 !important;
        }
        
        .cliente-marcado-ligado:hover {
            background-color: #e6ffed !important;
        }
        
        .cliente-card.cliente-marcado-ligado {
            border: 2px solid #28a745;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }
        
        .checkbox-ligado-container,
        .checkbox-ligado-container-mobile {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        /* Estilos para o toggle do supervisor */
        .supervisor-toggle-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 0.5rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .supervisor-toggle-container .btn-group .btn {
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .supervisor-toggle-container .btn-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .supervisor-toggle-container .alert {
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        @media (max-width: 576px) {
            .supervisor-toggle-container .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .supervisor-toggle-container .btn-group .btn {
                border-radius: 0.375rem !important;
                margin-bottom: 0.5rem;
            }
            
            .supervisor-toggle-container .btn-group .btn:last-child {
                margin-bottom: 0;
            }
        }
    </style>
    
    <script>
        // DEBUG: Mostrar dados do usuário na sessão
        console.log('=== DEBUG SESSÃO STHEFANY ===');
        console.log('Usuário completo:', <?php echo json_encode($usuario); ?>);
        console.log('Perfil:', '<?php echo $usuario['perfil'] ?? "NÃO DEFINIDO"; ?>');
        console.log('COD_VENDEDOR:', '<?php echo $usuario['cod_vendedor'] ?? "NÃO DEFINIDO"; ?>');
        console.log('Perfil permitido:', <?php echo $perfil_permitido ? 'true' : 'false'; ?>);
        console.log('Total clientes:', <?php echo $total_clientes ?? 0; ?>);
        console.log('================================');
        
        // Garantir que a sidebar não interfira no layout
        document.addEventListener('DOMContentLoaded', function() {
            const dashboardLayout = document.querySelector('.dashboard-layout');
            if (dashboardLayout) {
                // Remover classe sidebar-visible se existir
                dashboardLayout.classList.remove('sidebar-visible');
                
                // Forçar estilos inline para garantir largura total
                dashboardLayout.style.width = '100%';
                dashboardLayout.style.maxWidth = '100%';
                dashboardLayout.style.margin = '0';
                dashboardLayout.style.padding = '0';
                
                const dashboardContainer = document.querySelector('.dashboard-container');
                if (dashboardContainer) {
                    dashboardContainer.style.width = '100%';
                    dashboardContainer.style.maxWidth = '100%';
                    dashboardContainer.style.margin = '0';
                    dashboardContainer.style.marginLeft = '0';
                    dashboardContainer.style.padding = '0';
                }
                
                const dashboardMain = document.querySelector('.dashboard-main');
                if (dashboardMain) {
                    dashboardMain.style.width = '100%';
                    dashboardMain.style.maxWidth = '100%';
                    dashboardMain.style.margin = '0';
                    dashboardMain.style.marginLeft = '0';
                }
                
                const carteiraContainer = document.querySelector('.carteira-container');
                if (carteiraContainer) {
                    carteiraContainer.style.width = '100%';
                    carteiraContainer.style.maxWidth = '100%';
                    carteiraContainer.style.margin = '0';
                    carteiraContainer.style.marginLeft = '0';
                }
            }
        });
        
    </script>
            <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="dashboard-layout" data-perfil="<?php echo strtolower(trim($usuario['perfil'])); ?>">
        <!-- Incluir navbar -->
        <?php include __DIR__ . '/../../includes/components/navbar.php'; ?>
        <?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                
                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['erro']) && $_GET['erro'] === 'sem_permissao'): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Acesso Negado:</strong> Funcionalidade temporariamente indisponível.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                

                
                <!-- Título Principal -->
                <div style="text-align: center; margin-bottom: 2rem; max-width: 100%; width: 100%;">
                    <h2 style="font-size: 2.5rem; font-weight: bold; margin-bottom: 1rem; color: #333;">
                        <i class="fas fa-chart-pie"></i>
                        Status dos Clientes - <?php 
                            $meses = [
                                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                            ];
                            echo $meses[date('n')] . '/' . date('Y');
                        ?>
                    </h2>
                    <?php if ($is_diretor_admin): ?>
                        <div class="alert alert-info" style="margin-top: 1rem; font-size: 1rem; max-width: 800px; margin-left: auto; margin-right: auto;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Modo Diretor/Admin:</strong> Acesso completo a todos os dados com otimizações de performance aplicadas.
                        </div>
                    <?php endif; ?>
                </div>
                
                
                

                
                <!-- Container principal -->
                <div class="carteira-container" style="max-width: 100%; width: 100%; margin: 0; padding: 0 1rem; margin-left: 0 !important;">

                    <!-- Botão para Supervisor ver apenas seus próprios clientes -->
                    <?php if (strtolower(trim($usuario['perfil'])) === 'supervisor'): ?>
                        <div class="supervisor-toggle-container mb-3" style="text-align: center;">
                            <div class="btn-group" role="group" aria-label="Modo de visualização">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['supervisor_apenas_proprios' => '0'])); ?>" 
                                   class="btn <?php echo $supervisor_apenas_proprios !== '1' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    <i class="fas fa-users"></i> Ver Equipe Completa
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['supervisor_apenas_proprios' => '1'])); ?>" 
                                   class="btn <?php echo $supervisor_apenas_proprios === '1' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    <i class="fas fa-user"></i> Apenas Meus Clientes
                                </a>
                            </div>
                            <?php if ($supervisor_apenas_proprios === '1'): ?>
                                <div class="alert alert-info mt-2" style="font-size: 0.9rem; margin-bottom: 0;">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Modo Individual:</strong> Exibindo apenas seus próprios clientes, não da equipe.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mt-2" style="font-size: 0.9rem; margin-bottom: 0;">
                                    <i class="fas fa-users"></i>
                                    <strong>Modo Equipe:</strong> Exibindo clientes de toda sua equipe.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <?php include __DIR__ . '/../../includes/table/filtros_carteira.php'; ?>
                    
                    <!-- Navegação por Abas -->
                    <div class="tabs-container">
                        <ul class="nav nav-tabs" id="carteiraTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="clientes-tab" data-bs-toggle="tab" data-bs-target="#clientes" type="button" role="tab" aria-controls="clientes" aria-selected="true">
                                    <i class="fas fa-users"></i> Clientes
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="calendario-tab" data-bs-toggle="tab" data-bs-target="#calendario" type="button" role="tab" aria-controls="calendario" aria-selected="false">
                                    <i class="fas fa-calendar-alt"></i> Calendário
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="metas-tab" data-bs-toggle="tab" data-bs-target="#metas" type="button" role="tab" aria-controls="metas" aria-selected="false">
                                    <i class="fas fa-bullseye"></i> Metas
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="carteiraTabContent">
                            <!-- Aba Clientes -->
                            <div class="tab-pane fade show active" id="clientes" role="tabpanel" aria-labelledby="clientes-tab">
                                <?php include __DIR__ . '/../../includes/table/tabela_clientes.php'; ?>
                            </div>
                            
                            <!-- Aba Calendário -->
                            <div class="tab-pane fade" id="calendario" role="tabpanel" aria-labelledby="calendario-tab">
                                <?php include __DIR__ . '/../../includes/calendar/calendario_agendamentos_unificado.php'; ?>
                            </div>
                            
                            <!-- Aba Metas -->
                            <div class="tab-pane fade" id="metas" role="tabpanel" aria-labelledby="metas-tab">
                                <?php 
                                $prev_show_metas = $_GET['show_metas'] ?? null; 
                                $_GET['show_metas'] = '1'; 
                                include __DIR__ . '/../../includes/reports/metas_detalhes.php'; 
                                if ($prev_show_metas === null) { 
                                    unset($_GET['show_metas']); 
                                } else { 
                                    $_GET['show_metas'] = $prev_show_metas; 
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <footer class="dashboard-footer">
                <p>© 2025 Autopel - Todos os direitos reservados</p>
                <p class="system-info">Sistema BI v1.0</p>
            </footer>
        </div>
    </div>
    
    <!-- Incluir modais -->
    <?php include __DIR__ . '/../../includes/modal/modais_carteira.php'; ?>
    
    <!-- Incluir JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo base_url('assets/js/carteira.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo base_url('assets/js/ligacao.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo base_url('assets/js/calendario_unificado.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo base_url('assets/js/modais.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo base_url('assets/js/grafico-status-clientes.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo base_url('assets/js/marcar-cliente-ligado.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html> 



