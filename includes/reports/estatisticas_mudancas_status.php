<?php
// Estatísticas de Mudanças de Status - Mês Atual
// Este include mostra quantos clientes ativos inativaram, inativos ativaram e prospects viraram clientes no mês

// Verificar se $pdo está disponível
if (!isset($pdo)) {
    error_log("DEBUG ESTATISTICAS MUDANCAS - Variável \$pdo não está disponível");
    return;
}

// Verificar se $usuario está disponível
if (!isset($usuario)) {
    error_log("DEBUG ESTATISTICAS MUDANCAS - Variável \$usuario não está disponível");
    return;
}

try {
    $mes_atual = date('m');
    $ano_atual = date('Y');
    $data_inicio_mes = date('Y-m-01');
    $data_fim_mes = date('Y-m-t');
    
    // Preparar condições WHERE baseadas no perfil
    $where_conditions = [];
    $params = [];
    
    // Se for supervisor, buscar dados da equipe
    if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = f.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
        $params[] = $usuario['cod_vendedor'];
    }
    // Se for diretor ou admin, buscar dados totais da empresa
    elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        if (!empty($supervisor_selecionado)) {
            // Buscar dados apenas dos vendedores sob supervisão do supervisor selecionado
            $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = f.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params[] = $supervisor_selecionado;
        } else {
            // Buscar dados de todos os usuários ativos (visão geral da empresa)
            $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = f.COD_VENDEDOR AND u.ATIVO = 1)";
        }
    }
    // Para vendedor/representante individual
    else {
        $where_conditions[] = "f.COD_VENDEDOR = ?";
        $params[] = $usuario['cod_vendedor'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Inicializar variáveis
    $ativos_inativaram = 0;
    $inativos_ativaram = 0;
    $prospects_clientes = 0;
    $total_mudancas = 0;
    
    // 1. Clientes ativos que inativaram no mês (não tiveram faturamento no mês)
    $sql_ativos_inativaram = "
        SELECT COUNT(DISTINCT raiz_cnpj) as total
        FROM (
            SELECT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                MAX(CASE WHEN f.EMISSAO IS NOT NULL AND f.EMISSAO != '' AND STR_TO_DATE(f.EMISSAO, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(f.EMISSAO, '%d/%m/%Y') END) as ultima_compra
            FROM FATURAMENTO f
            $where_clause
            GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
        ) as clientes_ativos
        WHERE ultima_compra >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND ultima_compra < ?
        AND NOT EXISTS (
            SELECT 1 FROM FATURAMENTO f2
            WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f2.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = raiz_cnpj
            AND f2.EMISSAO IS NOT NULL AND f2.EMISSAO != '' 
            AND STR_TO_DATE(f2.EMISSAO, '%d/%m/%Y') IS NOT NULL
            AND STR_TO_DATE(f2.EMISSAO, '%d/%m/%Y') >= ?
            $where_clause
        )
    ";
    
    $params_ativos_inativaram = array_merge($params, [$data_inicio_mes, $data_inicio_mes]);
    $stmt_ativos_inativaram = $pdo->prepare($sql_ativos_inativaram);
    $stmt_ativos_inativaram->execute($params_ativos_inativaram);
    $ativos_inativaram = $stmt_ativos_inativaram->fetch(PDO::FETCH_COLUMN);
    
    // 2. Clientes inativos que ativaram no mês (tiveram faturamento no mês após 30+ dias sem compra)
    $sql_inativos_ativaram = "
        SELECT COUNT(DISTINCT raiz_cnpj) as total
        FROM (
            SELECT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                MAX(CASE WHEN f.EMISSAO IS NOT NULL AND f.EMISSAO != '' AND STR_TO_DATE(f.EMISSAO, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(f.EMISSAO, '%d/%m/%Y') END) as ultima_compra
            FROM FATURAMENTO f
            $where_clause
            GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
        ) as clientes_inativos
        WHERE ultima_compra < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND EXISTS (
            SELECT 1 FROM FATURAMENTO f2
            WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f2.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = raiz_cnpj
            AND f2.EMISSAO IS NOT NULL AND f2.EMISSAO != '' 
            AND STR_TO_DATE(f2.EMISSAO, '%d/%m/%Y') IS NOT NULL
            AND STR_TO_DATE(f2.EMISSAO, '%d/%m/%Y') >= ?
            $where_clause
        )
    ";
    
    $params_inativos_ativaram = array_merge($params, [$data_inicio_mes]);
    $stmt_inativos_ativaram = $pdo->prepare($sql_inativos_ativaram);
    $stmt_inativos_ativaram->execute($params_inativos_ativaram);
    $inativos_ativaram = $stmt_inativos_ativaram->fetch(PDO::FETCH_COLUMN);
    
    // 3. Prospects que viraram clientes no mês (primeira compra no mês)
    $sql_prospects_clientes = "
        SELECT COUNT(DISTINCT raiz_cnpj) as total
        FROM (
            SELECT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                MIN(CASE WHEN f.EMISSAO IS NOT NULL AND f.EMISSAO != '' AND STR_TO_DATE(f.EMISSAO, '%d/%m/%Y') IS NOT NULL THEN STR_TO_DATE(f.EMISSAO, '%d/%m/%Y') END) as primeira_compra
            FROM FATURAMENTO f
            $where_clause
            GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
        ) as prospects
        WHERE primeira_compra >= ?
        AND primeira_compra <= ?
    ";
    
    $params_prospects_clientes = array_merge($params, [$data_inicio_mes, $data_fim_mes]);
    $stmt_prospects_clientes = $pdo->prepare($sql_prospects_clientes);
    $stmt_prospects_clientes->execute($params_prospects_clientes);
    $prospects_clientes = $stmt_prospects_clientes->fetch(PDO::FETCH_COLUMN);
    
    // 4. Total de mudanças no mês
    $total_mudancas = $ativos_inativaram + $inativos_ativaram + $prospects_clientes;
    
    // Log para debug
    error_log("DEBUG ESTATISTICAS MUDANCAS - Ativos que inativaram: " . $ativos_inativaram);
    error_log("DEBUG ESTATISTICAS MUDANCAS - Inativos que ativaram: " . $inativos_ativaram);
    error_log("DEBUG ESTATISTICAS MUDANCAS - Prospects que viraram clientes: " . $prospects_clientes);
    error_log("DEBUG ESTATISTICAS MUDANCAS - Total de mudanças: " . $total_mudancas);
    
} catch (PDOException $e) {
    error_log("Erro ao calcular estatísticas de mudanças de status: " . $e->getMessage());
    $ativos_inativaram = 0;
    $inativos_ativaram = 0;
    $prospects_clientes = 0;
    $total_mudancas = 0;
}

// Só mostrar se o usuário tem permissão
$perfil_permitido = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin', 'vendedor', 'representante']);

// Log para debug
error_log("DEBUG ESTATISTICAS MUDANCAS - Perfil permitido: " . ($perfil_permitido ? 'SIM' : 'NÃO'));
error_log("DEBUG ESTATISTICAS MUDANCAS - Total mudanças: " . $total_mudancas);

if ($perfil_permitido):
?>
<div class="mudancas-status-container" style="margin-bottom: 2rem;">
    <div class="mudancas-status-header">
        <h3><i class="fas fa-exchange-alt"></i> Mudanças de Status - <?php 
            $meses = [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
            ];
            echo $meses[date('n')] . '/' . date('Y');
        ?></h3>
    </div>
    
    <div class="mudancas-status-cards">
        <div class="mudanca-card negativo">
            <div class="mudanca-icon">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="mudanca-info">
                <span class="mudanca-numero"><?php echo number_format($ativos_inativaram); ?></span>
                <span class="mudanca-label">Ativos Inativaram</span>
            </div>
        </div>
        
        <div class="mudanca-card positivo">
            <div class="mudanca-icon">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="mudanca-info">
                <span class="mudanca-numero"><?php echo number_format($inativos_ativaram); ?></span>
                <span class="mudanca-label">Inativos Ativaram</span>
            </div>
        </div>
        
        <div class="mudanca-card novo">
            <div class="mudanca-icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div class="mudanca-info">
                <span class="mudanca-numero"><?php echo number_format($prospects_clientes); ?></span>
                <span class="mudanca-label">Prospects → Clientes</span>
            </div>
        </div>
    </div>
    
    <div class="mudancas-status-summary">
        <div class="summary-item">
            <span class="summary-label">Saldo do Mês:</span>
            <span class="summary-value <?php echo ($inativos_ativaram + $prospects_clientes - $ativos_inativaram) >= 0 ? 'positivo' : 'negativo'; ?>">
                <?php echo ($inativos_ativaram + $prospects_clientes - $ativos_inativaram) >= 0 ? '+' : ''; ?>
                <?php echo number_format($inativos_ativaram + $prospects_clientes - $ativos_inativaram); ?>
            </span>
        </div>
        
        <?php if ($total_mudancas > 0): ?>
        <div class="summary-item">
            <span class="summary-label">Taxa de Conversão:</span>
            <span class="summary-value">
                <?php echo number_format((($inativos_ativaram + $prospects_clientes) / $total_mudancas) * 100, 1); ?>%
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.mudancas-status-container {
    background: #fff;
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 2px 8px #0001;
    height: fit-content;
}

.mudancas-status-header h3 {
    margin: 0 0 24px 0;
    color: #1a237e;
    font-size: 20px;
    font-weight: 600;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.mudancas-status-header h3 i {
    margin-right: 0.5rem;
    color: #007bff;
}

.mudancas-status-cards {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.mudanca-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 8px;
    background: #f8f9fa;
    border-left: 4px solid;
    transition: transform 0.2s ease;
}

.mudanca-card:hover {
    transform: translateY(-2px);
}

.mudanca-card.negativo {
    border-left-color: #dc3545;
    background: linear-gradient(135deg, #fff5f5, #ffe6e6);
}

.mudanca-card.positivo {
    border-left-color: #28a745;
    background: linear-gradient(135deg, #f0fff4, #e6ffe6);
}

.mudanca-card.novo {
    border-left-color: #007bff;
    background: linear-gradient(135deg, #f0f8ff, #e6f3ff);
}

.mudanca-icon {
    margin-right: 1rem;
    font-size: 1.5rem;
}

.mudanca-card.negativo .mudanca-icon {
    color: #dc3545;
}

.mudanca-card.positivo .mudanca-icon {
    color: #28a745;
}

.mudanca-card.novo .mudanca-icon {
    color: #007bff;
}

.mudanca-info {
    display: flex;
    flex-direction: column;
}

.mudanca-numero {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
    line-height: 1;
}

.mudanca-label {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.25rem;
}

.mudancas-status-summary {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 1rem;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.summary-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.summary-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.summary-value {
    font-size: 1.1rem;
    font-weight: 600;
}

.summary-value.positivo {
    color: #28a745;
}

.summary-value.negativo {
    color: #dc3545;
}

@media (max-width: 768px) {
    .mudancas-status-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .mudanca-card {
        padding: 0.75rem;
    }
    
    .mudanca-numero {
        font-size: 1.25rem;
    }
    
    .mudanca-label {
        font-size: 0.75rem;
    }
}

@media (max-width: 480px) {
    .mudancas-status-cards {
        grid-template-columns: 1fr;
    }
    
    .mudancas-status-summary {
        flex-direction: column;
        align-items: center;
    }
}
</style>
<?php endif; ?> 