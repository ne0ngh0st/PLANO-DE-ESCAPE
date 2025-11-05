<?php
// Gráfico de Comparação de Faturamento 2024 vs 2025
// Este include mostra um gráfico comparativo mensal entre os dois anos

// Verificar se $pdo está disponível
if (!isset($pdo)) {
    return;
}

// Verificar se $usuario está disponível
if (!isset($usuario)) {
    return;
}

try {
    $ano_atual = date('Y');
    $ano_anterior = $ano_atual - 1;
    
    // Preparar condições WHERE baseadas no perfil
    $where_conditions = [];
    $params = [];
    
    // Se for vendedor ou representante, filtrar apenas seus dados
    if (in_array(strtolower(trim($usuario['perfil'])), ['vendedor', 'representante']) && !empty($usuario['cod_vendedor'])) {
        $where_conditions[] = "f.COD_VENDEDOR = ?";
        $params[] = $usuario['cod_vendedor'];

    }
    // Se for supervisor, filtrar dados dos vendedores sob sua supervisão
    elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
        
        if (!empty($vendedor_selecionado)) {
            // Se um vendedor específico foi selecionado, filtrar apenas seus dados
            $where_conditions[] = "f.COD_VENDEDOR = ?";
            $params[] = $vendedor_selecionado;
        } else {
            // Filtrar dados de todos os vendedores da equipe
            $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = f.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params[] = $usuario['cod_vendedor'];
        }
    }
    // Se for diretor ou admin, verificar se há filtro de visão específica
    elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
        
        if (!empty($vendedor_selecionado)) {
            // Se um vendedor específico foi selecionado, filtrar apenas seus dados
            $where_conditions[] = "f.COD_VENDEDOR = ?";
            $params[] = $vendedor_selecionado;
        } elseif (!empty($supervisor_selecionado)) {
            // Se um supervisor foi selecionado, filtrar dados dos vendedores sob sua supervisão
            $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = f.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params[] = $supervisor_selecionado;
        }
    }
    // Se for admin, não aplicar filtro (vê todos os dados)
    elseif (strtolower(trim($usuario['perfil'])) === 'admin') {
        // Sem filtro para admin
    }
    // Para outros perfis, aplicar filtro por padrão se tiver cod_vendedor
    elseif (!empty($usuario['cod_vendedor'])) {
        $where_conditions[] = "f.COD_VENDEDOR = ?";
        $params[] = $usuario['cod_vendedor'];

    }
    // Se não tem cod_vendedor e não é diretor/admin, não mostrar nada
    elseif (!in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        $where_conditions[] = "1 = 0"; // Força retornar 0 resultados
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Buscar dados de faturamento de 2024 (tabela FATURAMENTO_2024) - Para o gráfico (todos os meses)
    $dados_2024_grafico = [];
    $sql_2024_grafico = "SELECT 
        MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) as mes,
        SUM(VLR_TOTAL) as total_faturamento
        FROM FATURAMENTO_2024 f
        " . (!empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) . " AND " : "WHERE ") . "YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?
        GROUP BY MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y'))
        ORDER BY mes";
    
    $params_2024_grafico = array_merge($params, [$ano_anterior]);
    $stmt_2024_grafico = $pdo->prepare($sql_2024_grafico);
    $stmt_2024_grafico->execute($params_2024_grafico);
    $resultados_2024_grafico = $stmt_2024_grafico->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar array com 12 meses para o gráfico
    for ($i = 1; $i <= 12; $i++) {
        $dados_2024_grafico[$i] = 0;
    }
    
    // Preencher dados de 2024 para o gráfico (todos os meses)
    foreach ($resultados_2024_grafico as $row) {
        $mes = intval($row['mes']);
        $valor = floatval($row['total_faturamento']);
        $dados_2024_grafico[$mes] = $valor;
    }
    
    // Buscar dados de faturamento de 2024 (tabela FATURAMENTO_2024) - Para o card (acumulado até a data limite)
    $dados_2024_card = [];
    $data_limite_2024 = date('Y-m-d', strtotime('-1 year')); // Mesmo dia do ano passado
    $sql_2024_card = "SELECT 
        MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) as mes,
        SUM(VLR_TOTAL) as total_faturamento
        FROM FATURAMENTO_2024 f
        " . (!empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) . " AND " : "WHERE ") . "YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?
        AND STR_TO_DATE(EMISSAO, '%d/%m/%Y') <= ?
        GROUP BY MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y'))
        ORDER BY mes";
    
    $params_2024_card = array_merge($params, [$ano_anterior, $data_limite_2024]);
    $stmt_2024_card = $pdo->prepare($sql_2024_card);
    $stmt_2024_card->execute($params_2024_card);
    $resultados_2024_card = $stmt_2024_card->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar array com 12 meses para o card
    for ($i = 1; $i <= 12; $i++) {
        $dados_2024_card[$i] = 0;
    }
    
    // Preencher dados de 2024 para o card (acumulado até a data limite)
    foreach ($resultados_2024_card as $row) {
        $mes = intval($row['mes']);
        $valor = floatval($row['total_faturamento']);
        $dados_2024_card[$mes] = $valor;
    }
    
    // Buscar total fechado de 2024 (até 30 de dezembro) para a meta de referência
    $total_2024_fechado = 0;
    $sql_2024_fechado = "SELECT SUM(VLR_TOTAL) as total_faturamento
        FROM FATURAMENTO_2024 f
        " . (!empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) . " AND " : "WHERE ") . "YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?
        AND STR_TO_DATE(EMISSAO, '%d/%m/%Y') <= ?";
    
    $params_2024_fechado = array_merge($params, [$ano_anterior, $ano_anterior . '-12-30']);
    $stmt_2024_fechado = $pdo->prepare($sql_2024_fechado);
    $stmt_2024_fechado->execute($params_2024_fechado);
    $resultado_2024_fechado = $stmt_2024_fechado->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado_2024_fechado) {
        $total_2024_fechado = floatval($resultado_2024_fechado['total_faturamento'] ?? 0);
    }
    

    
    // Buscar dados de faturamento de 2025 (tabela FATURAMENTO)
    $dados_2025 = [];
    $sql_2025 = "SELECT 
        MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) as mes,
        SUM(VLR_TOTAL) as total_faturamento
        FROM FATURAMENTO f
        " . (!empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) . " AND " : "WHERE ") . "YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?
        AND STR_TO_DATE(EMISSAO, '%d/%m/%Y') <= CURDATE()
        GROUP BY MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y'))
        ORDER BY mes";
    
    $params_2025 = array_merge($params, [$ano_atual]);
    $stmt_2025 = $pdo->prepare($sql_2025);
    $stmt_2025->execute($params_2025);
    $resultados_2025 = $stmt_2025->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar array com 12 meses
    for ($i = 1; $i <= 12; $i++) {
        $dados_2025[$i] = 0;
    }
    
    // Preencher dados de 2025
    foreach ($resultados_2025 as $row) {
        $mes = intval($row['mes']);
        $valor = floatval($row['total_faturamento']);
        
        // Usar o valor diretamente sem conversão
        $dados_2025[$mes] = $valor;
    }
    

    

    
    // Calcular totais acumulados até a data atual
    $total_2024 = array_sum($dados_2024_card); // Para o card (acumulado até a data limite)
    $total_2025 = array_sum($dados_2025);
    

    
    $crescimento_percentual = $total_2024 > 0 ? (($total_2025 - $total_2024) / $total_2024) * 100 : 0;
    

    
    // Preparar dados para o gráfico
    $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $valores_2024 = array_values($dados_2024_grafico); // Para o gráfico (todos os meses)
    $valores_2025 = array_values($dados_2025);
    

    
} catch (PDOException $e) {
    error_log("Erro ao buscar dados de faturamento comparativo: " . $e->getMessage());
    $dados_2024_grafico = array_fill(1, 12, 0);
    $dados_2024_card = array_fill(1, 12, 0);
    $dados_2025 = array_fill(1, 12, 0);
    $total_2024 = 0;
    $total_2025 = 0;
    $crescimento_percentual = 0;
    $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $valores_2024 = array_fill(0, 12, 0);
    $valores_2025 = array_fill(0, 12, 0);
}

// Só mostrar se o usuário tem permissão
$perfil_permitido = in_array($usuario['perfil'], ['diretor', 'supervisor', 'admin', 'vendedor', 'representante']);

if ($perfil_permitido):
?>
<div class="grafico-comparacao-container" style="border-radius: 12px; padding: 24px; margin-bottom: 16px;">
    <div class="grafico-header" style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 8px 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-chart-line"></i>
            Comparação de Faturamento <?php echo $ano_anterior; ?> vs <?php echo $ano_atual; ?>
        </h3>
        <p style="margin: 0; font-size: 13px; color: #666;">
            Comparação acumulada até o mesmo dia do ano passado vs período atual
        </p>
    </div>
    
    <!-- Card de Referência: Total 2024 Fechado -->
    <div class="card-referencia-destaque" style="background: #f8f9fa; border: 1px solid #e9ecef; color: #495057; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px;">
            <i class="fas fa-flag-checkered" style="font-size: 14px; color: #6c757d;"></i>
            <span style="font-size: 13px; font-weight: 600; color: #6c757d;">Meta de Referência <?php echo $ano_anterior; ?> (Fechado)</span>
        </div>
        <div style="font-size: 18px; font-weight: bold; color: #212529; margin-bottom: 4px;">
            R$ <?php echo number_format($total_2024_fechado, 0, ',', '.'); ?>
        </div>
        <div style="font-size: 11px; color: #6c757d; line-height: 1.3;">
            Total fechado em <?php echo $ano_anterior; ?> (até 30/12)
        </div>
    </div>
    
    <!-- Cards de Resumo Compactos -->
    <div class="resumo-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div style="background: linear-gradient(135deg, #ff9800, #f57c00); color: white; padding: 12px; border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: bold;">
                R$ <?php echo number_format($total_2024, 0, ',', '.'); ?>
            </div>
            <div style="font-size: 11px; opacity: 0.9; margin-top: 4px;">
                <?php echo $ano_anterior; ?> (até o mesmo dia)
            </div>
            <div style="font-size: 10px; opacity: 0.8; margin-top: 2px; font-style: italic;">
                Comparação de referência
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, #4caf50, #45a049); color: white; padding: 12px; border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: bold;">
                R$ <?php echo number_format($total_2025, 0, ',', '.'); ?>
            </div>
            <div style="font-size: 11px; opacity: 0.9; margin-top: 4px;">
                <?php echo $ano_atual; ?> (até hoje)
            </div>
            <div style="font-size: 10px; opacity: 0.8; margin-top: 2px; font-style: italic;">
                Período atual
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, <?php echo $crescimento_percentual >= 0 ? '#28a745' : '#dc3545'; ?>, <?php echo $crescimento_percentual >= 0 ? '#20c997' : '#c82333'; ?>); color: white; padding: 12px; border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: bold;">
                <?php echo ($crescimento_percentual >= 0 ? '+' : ''); ?><?php echo number_format($crescimento_percentual, 1); ?>%
            </div>
            <div style="font-size: 11px; opacity: 0.9; margin-top: 4px;">
                Crescimento vs <?php echo $ano_anterior; ?>
            </div>
            <div style="font-size: 10px; opacity: 0.8; margin-top: 2px; font-style: italic;">
                Em relação ao mesmo dia do ano passado
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, #2196f3, #1976d2); color: white; padding: 12px; border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: bold;">
                R$ <?php echo number_format($total_2025 - $total_2024, 0, ',', '.'); ?>
            </div>
            <div style="font-size: 11px; opacity: 0.9; margin-top: 4px;">
                Diferença
            </div>
            <div style="font-size: 10px; opacity: 0.8; margin-top: 2px; font-style: italic;">
                <?php echo ($total_2025 >= $total_2024) ? 'Acima da meta' : 'Abaixo da meta'; ?>
            </div>
        </div>
    </div>
    
    <!-- Informação de Comparação -->
    <div style="background: #f5f5f5; border-left: 4px solid #2196f3; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
            <i class="fas fa-info-circle" style="color: #2196f3; font-size: 16px;"></i>
            <strong style="color: #333; font-size: 13px;">Como funciona a comparação:</strong>
        </div>
        <p style="margin: 0; font-size: 12px; color: #666; line-height: 1.5;">
            A comparação é feita <strong>até o mesmo dia do ano passado</strong>. 
            Por exemplo: se hoje é 15 de janeiro de <?php echo $ano_atual; ?>, 
            comparamos com o faturamento acumulado de <?php echo $ano_anterior; ?> até 15 de janeiro de <?php echo $ano_anterior; ?>. 
            A <strong>Meta de Referência</strong> mostra o total fechado de <?php echo $ano_anterior; ?> (até 30/12) como parâmetro de comparação.
        </p>
    </div>
    
    <!-- Container do Gráfico - OTIMIZADO PARA USAR TODA A LARGURA -->
    <div style="position: relative; height: 350px; margin-bottom: 12px;">
        <canvas id="graficoComparacaoFaturamento"></canvas>
    </div>
    
    <!-- Legenda Compacta -->
    <div style="display: flex; justify-content: center; gap: 20px; margin-top: 12px;">
        <div style="display: flex; align-items: center; gap: 6px;">
            <div style="width: 12px; height: 12px; background: #ff9800; border-radius: 3px;"></div>
            <span style="font-size: 12px; color: #666;"><?php echo $ano_anterior; ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <div style="width: 12px; height: 12px; background: #4caf50; border-radius: 3px;"></div>
            <span style="font-size: 12px; color: #666;"><?php echo $ano_atual; ?></span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('graficoComparacaoFaturamento').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($meses); ?>,
            datasets: [
                {
                    label: '<?php echo $ano_anterior; ?>',
                    data: <?php echo json_encode($valores_2024); ?>,
                    borderColor: '#ff9800',
                    backgroundColor: 'rgba(255, 152, 0, 0.1)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: '#ff9800',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: '<?php echo $ano_atual; ?>',
                    data: <?php echo json_encode($valores_2025); ?>,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: '#4caf50',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#fff',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': R$ ' + context.parsed.y.toLocaleString('pt-BR', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#666',
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#666',
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            elements: {
                point: {
                    hoverBackgroundColor: function(context) {
                        return context.dataset.borderColor;
                    }
                }
            }
        }
    });
});
</script>

<style>
@media (max-width: 768px) {
    .resumo-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grafico-comparacao-container {
        padding: 16px;
    }
    
    /* Card de referência responsivo */
    .card-referencia-destaque {
        padding: 10px 12px !important;
        margin-bottom: 12px !important;
    }
    
    .card-referencia-destaque > div:first-child span {
        font-size: 12px !important;
    }
    
    .card-referencia-destaque > div:nth-child(2) {
        font-size: 16px !important;
    }
}

@media (max-width: 480px) {
    .resumo-cards {
        grid-template-columns: 1fr;
    }
    
    .card-referencia-destaque {
        padding: 8px 10px !important;
        margin-bottom: 10px !important;
    }
    
    .card-referencia-destaque > div:first-child span {
        font-size: 11px !important;
    }
    
    .card-referencia-destaque > div:nth-child(2) {
        font-size: 14px !important;
    }
    
    .card-referencia-destaque > div:last-child {
        font-size: 10px !important;
    }
}
</style>
<?php endif; ?>

