<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    exit('Não autorizado');
}

require_once 'conexao.php';

$usuario = $_SESSION['usuario'];
$termo_busca = $_GET['termo'] ?? '';
$perfil_permitido = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin', 'vendedor', 'representante']);

if (!$perfil_permitido || !isset($pdo)) {
    http_response_code(403);
    exit('Sem permissão');
}

try {
    // Construir condições WHERE baseadas no perfil do usuário
    $where_conditions = [];
    $params = [];
    
    // Se for vendedor, filtrar apenas seus clientes
    if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for representante, filtrar apenas seus clientes
    elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for supervisor, filtrar clientes dos vendedores sob sua supervisão
    elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $cod_supervisor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "COD_SUPER = ?";
        $params[] = $cod_supervisor_formatado;
    }
    // Se for diretor ou admin, verificar se há filtro de visão específica
    elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        if (!empty($supervisor_selecionado)) {
            $supervisor_formatado = str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT);
            $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = ultimo_faturamento.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params[] = $supervisor_formatado;
        }
    }
    
    // Adicionar busca por termo se fornecido
    if (!empty($termo_busca)) {
        $termo_busca_limpo = preg_replace('/[^0-9]/', '', $termo_busca);
        if (strlen($termo_busca_limpo) >= 8) {
            // Se parece ser um CNPJ, buscar por raiz do CNPJ
            $raiz_cnpj = substr($termo_busca_limpo, 0, 8);
            $where_conditions[] = "SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) LIKE ?";
            $params[] = $raiz_cnpj . '%';
        } else {
            // Buscar por nome do cliente ou vendedor
            $where_conditions[] = "(CLIENTE LIKE ? OR NOME_FANTASIA LIKE ? OR NOME_VENDEDOR LIKE ?)";
            $params[] = '%' . $termo_busca . '%';
            $params[] = '%' . $termo_busca . '%';
            $params[] = '%' . $termo_busca . '%';
        }
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Condição para ocultar excluídos, exceto restaurados
    $filter_excluidos = "(\n                NOT EXISTS (\n                    SELECT 1 \n                    FROM clientes_excluidos ce \n                    WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(ce.cnpj, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) =\n                          SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)\n                )\n                OR EXISTS (\n                    SELECT 1 FROM clientes_restaurados cr\n                    WHERE cr.raiz_cnpj = SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)\n                )\n            )";

    $where_for_sql = $where_clause ? ($where_clause . ' AND ' . $filter_excluidos) : ('WHERE ' . $filter_excluidos);

    // Determinar referência de mês/ano para agregações (igual ao arquivo principal)
    $ano_ref = intval(date('Y'));
    $mes_ref = null;
    $usar_acumulado_ano = true;
    
    // Verificar se há filtro de mês específico
    $filtro_mes = $_GET['filtro_mes'] ?? '';
    if (!empty($filtro_mes) && preg_match('/^\d{4}-\d{2}$/', $filtro_mes)) {
        $ano_ref = intval(substr($filtro_mes, 0, 4));
        $mes_ref = intval(substr($filtro_mes, 5, 2));
        $usar_acumulado_ano = false;
    }

    // Consulta para buscar clientes com busca
    $sql = "SELECT 
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
                            THEN CONCAT('(', MAX(uf.DDD), ') ', MAX(uf.TELEFONE))
                            WHEN MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' 
                            THEN MAX(uf.TELEFONE)
                            WHEN MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) != '' 
                            THEN MAX(uf.TELEFONE2)
                            ELSE 'N/A'
                        END
                END as telefone,
                MAX(ultima_ligacao_cliente.data_ligacao) as ultima_ligacao
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
            $where_for_sql
            GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
            ORDER BY 
                CASE WHEN MAX(ultima_ligacao_cliente.data_ligacao) IS NULL THEN 0 ELSE 1 END,
                ultima_compra_data DESC, 
                valor_total DESC
            LIMIT 100"; // Limitar a 100 resultados para performance
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar resultados para incluir status
    $resultados = [];
    foreach ($clientes as $cliente) {
        // Determinar status do cliente
        $is_ativo = false;
        $is_inativando = false;
        $is_inativo = false;
        $dias_inativo = 0;
        
        if (!empty($cliente['ultima_compra'])) {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $cliente['ultima_compra'])) {
                $data_ultima = DateTime::createFromFormat('d/m/Y', $cliente['ultima_compra'])->getTimestamp();
            } else {
                $data_ultima = strtotime($cliente['ultima_compra']);
            }
            $data_atual = time();
            $dias_inativo = round(($data_atual - $data_ultima) / (60 * 60 * 24));
            $is_ativo = $dias_inativo <= 290;
            $is_inativando = $dias_inativo > 290 && $dias_inativo <= 365;
            $is_inativo = $dias_inativo > 365;
        } else {
            $is_inativo = true;
        }
        
        // Determinar status e cor
        if ($is_ativo) {
            $status_class = 'status-ativo';
            $status_text = 'Ativo';
        } elseif ($is_inativando) {
            $status_class = 'status-inativando';
            $status_text = 'Ativos...';
        } elseif ($is_inativo) {
            $status_class = 'status-inativo';
            $status_text = 'Inativo';
        } else {
            $status_class = 'status-sem-compras';
            $status_text = 'Sem compras';
        }
        
        $resultados[] = [
            'cliente' => $cliente['cliente'] ?? '',
            'nome_fantasia' => $cliente['nome_fantasia'] ?? '',
            'cnpj_representativo' => $cliente['cnpj_representativo'] ?? '',
            'raiz_cnpj' => $cliente['raiz_cnpj'] ?? '',
            'estado' => $cliente['estado'] ?? '',
            'segmento_atuacao' => $cliente['segmento_atuacao'] ?? '',
            'nome_vendedor' => $cliente['nome_vendedor'] ?? '',
            'nome_supervisor' => $cliente['nome_supervisor'] ?? '',
            'ultima_compra' => $cliente['ultima_compra'] ?? '',
            'ultima_ligacao' => $cliente['ultima_ligacao'] ?? '',
            'dias_inativo' => $dias_inativo,
            'status_class' => $status_class,
            'status_text' => $status_text,
            'telefone' => $cliente['telefone'] ?? '',
            'total_cnpjs_diferentes' => $cliente['total_cnpjs_diferentes'] ?? 1,
            'valor_total' => $cliente['valor_total'] ?? 0,
            'faturamento_mes' => $cliente['faturamento_mes'] ?? 0,
            'is_ativo' => $is_ativo,
            'is_inativando' => $is_inativando,
            'is_inativo' => $is_inativo
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'clientes' => $resultados,
        'total' => count($resultados)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar clientes: ' . $e->getMessage()
    ]);
}
?>
