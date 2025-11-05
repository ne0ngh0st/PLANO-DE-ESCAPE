<?php
// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once dirname(__DIR__, 2) . '/includes/config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    echo '<tr><td colspan="10" class="text-center text-danger">Sessão expirada</td></tr>';
    exit;
}

$usuario = $_SESSION['usuario'];

// Verificar se é admin ou diretor
$is_admin_diretor = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin']);
if (!$is_admin_diretor) {
    echo '<tr><td colspan="10" class="text-center text-danger">Acesso negado</td></tr>';
    exit;
}

// Processar filtros
$filtro_estado = $_GET['filtro_estado'] ?? '';
$filtro_vendedor = $_GET['filtro_vendedor'] ?? '';
$filtro_segmento = $_GET['filtro_segmento'] ?? '';
$filtro_ano = $_GET['filtro_ano'] ?? '';
$filtro_mes = $_GET['filtro_mes'] ?? '';
$supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
$busca_cliente = $_GET['busca_cliente'] ?? '';
$pagina = max(1, intval($_GET['pagina'] ?? 1));

// Configurações
$itens_por_pagina = 30;
$offset = ($pagina - 1) * $itens_por_pagina;

try {
    // Construir condições WHERE
    $where_conditions = [];
    $params = [];
    
    // Filtro de supervisão - usar tabela USUARIOS para garantir consistência
    if (!empty($supervisor_selecionado)) {
        $where_conditions[] = "uf.COD_VENDEDOR IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante'))";
        $params[] = str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT);
        if (!empty($filtro_vendedor)) {
            $where_conditions[] = "uf.COD_VENDEDOR = ?";
            $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
        }
    }
    
    // Filtros básicos
    if (!empty($filtro_estado)) {
        $where_conditions[] = "uf.ESTADO = ?";
        $params[] = $filtro_estado;
    }
    
    if (!empty($filtro_vendedor) && empty($supervisor_selecionado)) {
        $where_conditions[] = "uf.COD_VENDEDOR = ?";
        $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
    }
    
    if (!empty($filtro_segmento)) {
        $where_conditions[] = "uf.Descricao1 = ?";
        $params[] = $filtro_segmento;
    }
    
    // Filtro de ano/mês usando tabela FATURAMENTO
    if (!empty($filtro_ano)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
        $params[] = intval($filtro_ano);
    }
    if (!empty($filtro_mes)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
        $params[] = intval($filtro_mes);
    }
    
    // Busca discricionária por nome ou CNPJ
    if (!empty($busca_cliente)) {
        $busca_termo = trim($busca_cliente);
        if (strlen($busca_termo) >= 2) { // Mínimo 2 caracteres para busca
            $where_conditions[] = "(uf.CLIENTE LIKE ? OR uf.NOME_FANTASIA LIKE ? OR uf.CNPJ LIKE ?)";
            $busca_param = '%' . $busca_termo . '%';
            $params[] = $busca_param;
            $params[] = $busca_param;
            $params[] = $busca_param;
        }
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Consulta principal
    $sql_clientes = "
        SELECT 
            SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,
            MAX(uf.CNPJ) AS cnpj_representativo,
            MAX(uf.CLIENTE) AS cliente,
            MAX(uf.NOME_FANTASIA) AS nome_fantasia,
            MAX(uf.ESTADO) AS estado,
            MAX(COALESCE(uf.Descricao1, '')) AS segmento_atuacao,
            COALESCE(MAX(uf.COD_VENDEDOR), '') AS cod_vendedor,
            MAX(uf.NOME_VENDEDOR) AS nome_vendedor,
            COALESCE(MAX(uf.COD_SUPER), '') AS cod_supervisor,
            MAX(uf.FANT_SUPER) AS nome_supervisor,
            COUNT(*) AS total_pedidos,
            SUM(uf.VLR_TOTAL) AS valor_total,
            DATE_FORMAT(MAX(STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')), '%d/%m/%Y') AS ultima_compra,
            MAX(STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) AS ultima_compra_data,
            COUNT(DISTINCT uf.CNPJ) AS total_cnpjs_diferentes,
            CASE 
                WHEN MAX(uf.DDD) IS NOT NULL AND MAX(uf.DDD) != '' AND MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' 
                    THEN CONCAT('(', MAX(uf.DDD), ') ', MAX(uf.TELEFONE))
                WHEN MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' THEN MAX(uf.TELEFONE)
                WHEN MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) != '' THEN MAX(uf.TELEFONE2)
                ELSE 'N/A'
            END AS telefone,
            0 AS faturamento_mes
        FROM ultimo_faturamento uf
        $where_clause
        GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
        ORDER BY ultima_compra_data DESC, valor_total DESC
        LIMIT $itens_por_pagina OFFSET $offset
    ";

    $stmt_clientes = $pdo->prepare($sql_clientes);
    $stmt_clientes->execute($params);
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
    $stmt_clientes->closeCursor();

    // Calcular faturamento do período se necessário
    if (!empty($clientes) && (!empty($filtro_mes) || !empty($filtro_ano))) {
        $raizes_pagina = array_column($clientes, 'raiz_cnpj');
        $placeholders_raiz = implode(',', array_fill(0, count($raizes_pagina), '?'));
        
        $sql_fat_periodo = "
            SELECT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,
                SUM(f.VLR_TOTAL) AS fat_periodo
            FROM FATURAMENTO f
            WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) IN ($placeholders_raiz)
            " . (!empty($filtro_ano) ? "AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = " . intval($filtro_ano) : "") . "
            " . (!empty($filtro_mes) ? "AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = " . intval($filtro_mes) : "") . "
            GROUP BY raiz_cnpj
        ";
        
        $stmt_fat_periodo = $pdo->prepare($sql_fat_periodo);
        $stmt_fat_periodo->execute($raizes_pagina);
        $fat_periodo_rows = $stmt_fat_periodo->fetchAll(PDO::FETCH_ASSOC);
        $stmt_fat_periodo->closeCursor();
        
        // Mapear faturamento do período
        $map_fat_periodo = [];
        foreach ($fat_periodo_rows as $row) {
            $map_fat_periodo[$row['raiz_cnpj']] = (float)$row['fat_periodo'];
        }
        
        // Adicionar faturamento do período aos clientes
        foreach ($clientes as &$cliente) {
            $cliente['faturamento_periodo'] = $map_fat_periodo[$cliente['raiz_cnpj']] ?? 0;
        }
        unset($cliente);
    }

    // Gerar HTML da tabela
    if (empty($clientes)) {
        echo '<tr><td colspan="10" class="text-center text-muted">Nenhum cliente encontrado</td></tr>';
    } else {
        foreach ($clientes as $cliente): 
            // Determinar status do cliente
            $is_ativo = false;
            $is_inativando = false;
            $is_inativo = false;
            if (!empty($cliente['ultima_compra_data'])) {
                $dias_inativo = (time() - strtotime($cliente['ultima_compra_data'])) / (60 * 60 * 24);
                $is_ativo = $dias_inativo <= 290;
                $is_inativando = $dias_inativo > 290 && $dias_inativo <= 365;
                $is_inativo = $dias_inativo > 365;
            } else {
                $is_inativo = true;
            }
            
            if ($is_ativo) {
                $status_class = 'status-ativo';
                $status_text = 'Ativo';
            } elseif ($is_inativando) {
                $status_class = 'status-inativando';
                $status_text = 'Ativos...';
            } else {
                $status_class = 'status-inativo';
                $status_text = 'Inativo';
            }
        ?>
            <tr>
                <td>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($cliente['cliente'] ?? ''); ?></div>
                    <div style="font-size: 0.8rem; color: #6c757d;"><?php echo htmlspecialchars($cliente['nome_fantasia'] ?? ''); ?></div>
                </td>
                <td>
                    <code><?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? ''); ?></code>
                    <?php if (($cliente['total_cnpjs_diferentes'] ?? 0) > 1): ?>
                        <div style="font-size: 0.7rem; color: #007bff;">
                            <i class="fas fa-link"></i> <?php echo $cliente['total_cnpjs_diferentes']; ?> CNPJs
                        </div>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($cliente['estado'] ?? ''); ?></span></td>
                <td><?php echo htmlspecialchars($cliente['segmento_atuacao'] ?? ''); ?></td>
                <td>
                    <div><?php echo htmlspecialchars($cliente['nome_vendedor'] ?? ''); ?></div>
                    <div style="font-size: 0.7rem; color: #6c757d;"><?php echo htmlspecialchars($cliente['cod_vendedor']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($cliente['nome_supervisor'] ?? ''); ?></td>
                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                <td><?php echo htmlspecialchars($cliente['ultima_compra'] ?? '-'); ?></td>
                <td><strong>R$ <?php echo number_format((!empty($filtro_mes) || !empty($filtro_ano)) ? ($cliente['faturamento_periodo'] ?? 0) : $cliente['valor_total'], 2, ',', '.'); ?></strong></td>
                <td>
                    <div class="table-actions">
                        <a href="../api/detalhes_cliente.php?cnpj=<?php echo urlencode($cliente['cnpj_representativo']); ?>&from_optimized=1" 
                           class="btn btn-primary btn-sm" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="btn btn-danger btn-sm" title="Excluir Cliente" 
                                onclick="confirmarExclusaoCliente('<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; 
    }
    
} catch (PDOException $e) {
    echo '<tr><td colspan="10" class="text-center text-danger">Erro ao carregar dados: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
}
?>
