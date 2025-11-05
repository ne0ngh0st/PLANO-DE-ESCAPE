<?php
// ========================================
// API PARA GRÁFICO DE STATUS DOS CLIENTES
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir arquivos necessários
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/config.php';

// Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

try {
    // Usar dados do usuário da sessão
    $usuario = $_SESSION['usuario'];
    
    if (!$usuario) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não encontrado'
        ]);
        exit;
    }
    
    // Preparar condições WHERE baseadas no perfil (mesma lógica da carteira_teste_otimizada.php)
    $where_conditions = [];
    $params = [];
    
    // Se for vendedor, filtrar apenas seus clientes
    if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = trim($usuario['cod_vendedor']);
        $where_conditions[] = "uf.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for representante, filtrar apenas seus clientes
    elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = trim($usuario['cod_vendedor']);
        $where_conditions[] = "uf.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for supervisor, filtrar clientes dos vendedores sob sua supervisão
    elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $cod_supervisor_formatado = trim($usuario['cod_vendedor']);
        $supervisor_apenas_proprios = $_GET['supervisor_apenas_proprios'] ?? '';
        
        if ($supervisor_apenas_proprios === '1') {
            // Modo individual: apenas clientes do próprio supervisor
            $where_conditions[] = "uf.COD_VENDEDOR = ?";
            $params[] = $cod_supervisor_formatado;
        } else {
            // Modo equipe: clientes de toda a equipe
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
            if (!empty($_GET['filtro_vendedor'])) {
                $where_conditions[] = "uf.COD_VENDEDOR = ?";
                $params[] = str_pad($_GET['filtro_vendedor'], 3, '0', STR_PAD_LEFT);
            }
        }
    }
    
    // Aplicar filtros adicionais (mesmos da página principal)
    if (!empty($_GET['filtro_estado'])) {
        $where_conditions[] = "uf.ESTADO = ?";
        $params[] = $_GET['filtro_estado'];
    }
    
    if (!empty($_GET['filtro_vendedor']) && !in_array(strtolower(trim($usuario['perfil'])), ['vendedor', 'representante'])) {
        $filtro_vendedor_formatado = str_pad($_GET['filtro_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "uf.COD_VENDEDOR = ?";
        $params[] = $filtro_vendedor_formatado;
    }
    
    if (!empty($_GET['filtro_segmento'])) {
        $where_conditions[] = "uf.Descricao1 = ?";
        $params[] = $_GET['filtro_segmento'];
    }
    
    // Condição de pesquisa geral
    if (!empty($_GET['pesquisa_geral'])) {
        $pesquisa_termo = '%' . $_GET['pesquisa_geral'] . '%';
        $where_conditions[] = "(uf.CNPJ LIKE ? OR uf.CLIENTE LIKE ?)";
        $params[] = $pesquisa_termo;
        $params[] = $pesquisa_termo;
    }
    
    // Novo filtro de ano/mês
    if (empty($_GET['filtro_inatividade'])) {
        if (!empty($_GET['filtro_ano'])) {
            $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
            $params[] = intval($_GET['filtro_ano']);
        }
        if (!empty($_GET['filtro_mes'])) {
            $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
            $params[] = intval($_GET['filtro_mes']);
        }
    }
    
    // Montar WHERE base
    $where_clause_base = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Aplicar filtro de clientes excluídos (mesma lógica da página principal)
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
        $where_clause_detalhes = $where_clause_base;
    }
    
    $where_clause = $where_clause_detalhes;
    
    // Buscar TODOS os clientes agrupados por raiz CNPJ (sem paginação - conta toda a carteira)
    $sql = "SELECT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                MAX(uf.CLIENTE) as cliente,
                MAX(uf.DT_FAT) as ultima_compra,
                MAX(CASE WHEN uf.DT_FAT IS NOT NULL AND uf.DT_FAT != '' AND STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y') END) as ultima_compra_data,
                SUM(uf.VLR_TOTAL) as valor_total_consolidado,
                COUNT(DISTINCT uf.CNPJ) as total_cnpjs_diferentes
            FROM ultimo_faturamento uf
            " . (!empty($where_clause) ? $where_clause : '') . "
            GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
            ORDER BY valor_total_consolidado DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $todos_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log("DEBUG GRAFICO STATUS - SQL: " . $sql);
    error_log("DEBUG GRAFICO STATUS - Parâmetros: " . implode(', ', $params));
    error_log("DEBUG GRAFICO STATUS - Total clientes agrupados por raiz CNPJ: " . count($todos_clientes));
    
    // Calcular estatísticas de status baseadas nos clientes agrupados por raiz CNPJ
    $total_clientes = count($todos_clientes);
    $ativos = 0;
    $inativando = 0;
    $inativos = 0;
    
    if ($total_clientes > 0) {
        foreach ($todos_clientes as $cliente) {
            $ultima_compra_data = $cliente['ultima_compra_data'] ?? null;
            $ultima_compra = $cliente['ultima_compra'] ?? '';
            $valor_total = $cliente['valor_total_consolidado'] ?? 0;
            
            // Usar ultima_compra_data (datetime) se disponível, senão tentar parsear ultima_compra (string)
            $data_ultima = null;
            
            if (!empty($ultima_compra_data) && $ultima_compra_data !== '0000-00-00 00:00:00') {
                // Se temos datetime direto do banco
                $data_ultima = is_string($ultima_compra_data) ? strtotime($ultima_compra_data) : $ultima_compra_data;
            } elseif (!empty($ultima_compra)) {
                // Tentar diferentes formatos de data
                // Formato dd/mm/yyyy
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $ultima_compra)) {
                    $data_ultima = DateTime::createFromFormat('d/m/Y', $ultima_compra);
                    if ($data_ultima) {
                        $data_ultima = $data_ultima->getTimestamp();
                    }
                }
                // Formato yyyy-mm-dd
                elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ultima_compra)) {
                    $data_ultima = strtotime($ultima_compra);
                }
                // Outros formatos
                else {
                    $data_ultima = strtotime($ultima_compra);
                }
            }
            
            if ($data_ultima && $data_ultima > 0) {
                $data_atual = time();
                $dias_desde_ultima = ($data_atual - $data_ultima) / (60 * 60 * 24);
                
                if ($dias_desde_ultima <= 290) {
                    $ativos++;
                } elseif ($dias_desde_ultima > 290 && $dias_desde_ultima <= 365) {
                    $inativando++;
                } else {
                    $inativos++;
                }
            } else {
                // Se não tem data válida, considerar como inativo
                $inativos++;
            }
        }
    }
    
    // Calcular percentuais
    $percent_ativos = $total_clientes > 0 ? ($ativos / $total_clientes) * 100 : 0;
    $percent_inativando = $total_clientes > 0 ? ($inativando / $total_clientes) * 100 : 0;
    $percent_inativos = $total_clientes > 0 ? ($inativos / $total_clientes) * 100 : 0;
    
    // Preparar resposta
    $dados = [
        'total' => $total_clientes,
        'ativos' => $ativos,
        'inativando' => $inativando,
        'inativos' => $inativos,
        'percent_ativos' => round($percent_ativos, 1),
        'percent_inativando' => round($percent_inativando, 1),
        'percent_inativos' => round($percent_inativos, 1)
    ];
    
    echo json_encode([
        'success' => true,
        'dados' => $dados,
        'message' => 'Dados carregados com sucesso (agrupados por raiz CNPJ)'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro na API de status dos clientes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Erro geral na API de status dos clientes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>
