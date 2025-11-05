<?php
// Verificar se deve mostrar as metas - agora para todos os perfis
$should_show_metas_cards = true;

// Ou se o parâmetro show_metas estiver definido
if (isset($_GET['show_metas']) && $_GET['show_metas'] === '1') {
    $should_show_metas_cards = true;
}

// Verificar se é representante para usar "objetivo" em vez de "meta"
$is_representante = strtolower(trim($usuario['perfil'])) === 'representante';
$termo_meta = $is_representante ? 'Objetivo' : 'Meta';
$termo_metas = $is_representante ? 'Objetivos' : 'Metas';
$termo_atingida = $is_representante ? 'Objetivo Atingido!' : 'Meta Atingida!';
$termo_proxima = $is_representante ? 'Próximo do Objetivo' : 'Próximo da Meta';
$termo_baixa = $is_representante ? 'Abaixo do Objetivo' : 'Abaixo da Meta';
$termo_superada = $is_representante ? 'Objetivo superado em' : 'Meta superada em';

// Garantir que metafat está definida
if (!isset($metafat)) {
    $metafat = 0;
}

// Se o valor da meta é muito grande (>= 10000000), assumir que está em centavos
// REMOVIDO: Esta conversão estava causando erro para metas de diretores
// if ($metafat >= 10000000) {
//     $metafat = $metafat / 100;
// }

// Calcular faturamento correto baseado no perfil do usuário
$faturamento_mes_atual_calculado = 0;
$mes_atual = date('m');
$ano_atual = date('Y');

// Limpar qualquer valor anterior que possa estar interferindo
unset($faturamento_mes_atual);

// Verificar se $pdo está disponível
if (!isset($pdo)) {
    error_log("DEBUG CARDS METAS - Variável \$pdo não está disponível");
    $faturamento_mes_atual_calculado = 0;
} else {
    try {
    // Preparar condições WHERE baseadas no perfil
    $where_conditions_fat = [];
    $params_fat = [];
    
    // Log do perfil para debug
    error_log("DEBUG CARDS METAS - Perfil original: " . $usuario['perfil']);
    error_log("DEBUG CARDS METAS - Perfil tratado: " . strtolower(trim($usuario['perfil'])));
    
    // Se for supervisor, buscar faturamento da equipe
    if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $where_conditions_fat[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = FATURAMENTO.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
        $params_fat[] = $usuario['cod_vendedor'];
        error_log("DEBUG CARDS METAS - Faturamento calculado para equipe do supervisor: " . $usuario['cod_vendedor']);
    }
    // Se for diretor ou admin, buscar faturamento total da empresa
    elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        if (!empty($supervisor_selecionado)) {
            // Buscar faturamento apenas dos vendedores sob supervisão do supervisor selecionado
            $where_conditions_fat[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = FATURAMENTO.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params_fat[] = $supervisor_selecionado;
            error_log("DEBUG CARDS METAS - Faturamento calculado para supervisor selecionado: " . $supervisor_selecionado);
        } else {
            // Buscar faturamento de todos os usuários ativos (visão geral da empresa)
            $where_conditions_fat[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = FATURAMENTO.COD_VENDEDOR AND u.ATIVO = 1)";
            error_log("DEBUG CARDS METAS - Faturamento calculado para visão geral da empresa (diretor)");
        }
    }
    // Para vendedor/representante individual
    else {
        $where_conditions_fat[] = "COD_VENDEDOR = ?";
        $params_fat[] = $usuario['cod_vendedor'];
        error_log("DEBUG CARDS METAS - Faturamento calculado para vendedor individual: " . $usuario['cod_vendedor']);
    }
    
    $where_conditions_fat[] = "MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
    $where_conditions_fat[] = "YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
    $params_fat[] = $mes_atual;
    $params_fat[] = $ano_atual;
    
    $where_clause_fat = implode(' AND ', $where_conditions_fat);
    
    error_log("DEBUG CARDS METAS - Cláusula WHERE: " . $where_clause_fat);
    error_log("DEBUG CARDS METAS - Parâmetros: " . implode(', ', $params_fat));
    
    // Tentar primeiro com a tabela FATURAMENTO
    $sql_faturamento_mes = "SELECT 
        SUM(VLR_TOTAL) as total_faturamento
        FROM FATURAMENTO 
        WHERE $where_clause_fat";
    
    $stmt_faturamento = $pdo->prepare($sql_faturamento_mes);
    $stmt_faturamento->execute($params_fat);
    $faturamento_result = $stmt_faturamento->fetch(PDO::FETCH_ASSOC);
    
    error_log("DEBUG CARDS METAS - Resultado da tabela FATURAMENTO: " . json_encode($faturamento_result));
    
    // Se não encontrar dados, tentar com ultimo_faturamento
    if (!$faturamento_result || $faturamento_result['total_faturamento'] == 0) {
        // Ajustar a cláusula WHERE para a tabela ultimo_faturamento
        $where_clause_ultimo = str_replace('FATURAMENTO.COD_VENDEDOR', 'ultimo_faturamento.COD_VENDEDOR', $where_clause_fat);
        
        $sql_faturamento_mes = "SELECT 
            SUM(VLR_TOTAL) as total_faturamento
            FROM ultimo_faturamento 
            WHERE $where_clause_ultimo";
        
        $stmt_faturamento = $pdo->prepare($sql_faturamento_mes);
        $stmt_faturamento->execute($params_fat);
        $faturamento_result = $stmt_faturamento->fetch(PDO::FETCH_ASSOC);
        
        error_log("DEBUG CARDS METAS - Resultado da tabela ultimo_faturamento: " . json_encode($faturamento_result));
    }
    
    if ($faturamento_result) {
        $faturamento_mes_atual_calculado = floatval($faturamento_result['total_faturamento'] ?? 0);
        
        // Se o valor é muito grande (>= 10000000), assumir que está em centavos
        // REMOVIDO: Esta conversão estava causando erro para valores de diretores
        // if ($faturamento_mes_atual_calculado >= 10000000) {
        //     $faturamento_mes_atual_calculado = $faturamento_mes_atual_calculado / 100;
        // }
    }
    
    error_log("DEBUG CARDS METAS - Faturamento calculado: " . $faturamento_mes_atual_calculado);
    
    } catch (PDOException $e) {
        error_log("Erro ao calcular faturamento baseado no perfil: " . $e->getMessage());
        $faturamento_mes_atual_calculado = 0;
    }
}

// SEMPRE usar o faturamento calculado baseado no perfil
$faturamento_mes_atual = $faturamento_mes_atual_calculado;

// Log de debug para verificar os valores
error_log("DEBUG CARDS METAS - Faturamento calculado: " . $faturamento_mes_atual_calculado);
error_log("DEBUG CARDS METAS - Faturamento final usado: " . $faturamento_mes_atual);
error_log("DEBUG CARDS METAS - Perfil usuário: " . $usuario['perfil']);
error_log("DEBUG CARDS METAS - COD_VENDEDOR: " . $usuario['cod_vendedor']);

// Verificar se o valor está sendo sobrescrito
if (isset($faturamento_mes_atual_original)) {
    error_log("DEBUG CARDS METAS - Valor original sobrescrito: " . $faturamento_mes_atual_original);
}

// Calcular variáveis de tempo para projeções
$dias_no_mes = date('t');
$dia_atual = date('j');
$dias_restantes = $dias_no_mes - $dia_atual;
$dias_passados = $dia_atual;
$percentual_tempo = ($dias_passados / $dias_no_mes) * 100;

// Calcular percentual atingido
$percentual_fat = $metafat > 0 ? ($faturamento_mes_atual / $metafat) * 100 : 0;

// Calcular percentual geral de atingimento (cumulativo)
$percentual_geral = 0;
$mes_atual_num = date('n'); // Mês atual (1-12)

if (isset($pdo) && isset($where_clause_fat) && isset($params_fat)) {
    try {
        // Buscar faturamento total do ano até agora
        $sql_faturamento_total = "SELECT SUM(VLR_TOTAL) as total_faturamento_ano FROM FATURAMENTO WHERE $where_clause_fat AND YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = YEAR(CURDATE())";
        $stmt_faturamento_total = $pdo->prepare($sql_faturamento_total);
        $stmt_faturamento_total->execute($params_fat);
        $faturamento_total_ano = floatval($stmt_faturamento_total->fetch(PDO::FETCH_ASSOC)['total_faturamento_ano'] ?? 0);
        
        // Se o valor é muito grande (>= 10000000), assumir que está em centavos
        // REMOVIDO: Esta conversão estava causando erro para valores de diretores
        // if ($faturamento_total_ano >= 10000000) {
        //     $faturamento_total_ano = $faturamento_total_ano / 100;
        // }
        
        // Calcular meta cumulativa (meta mensal * mês atual)
        $meta_fat_cumulativa = $metafat * $mes_atual_num;
        
        // Calcular percentual geral baseado no faturamento
        if ($meta_fat_cumulativa > 0 && $faturamento_total_ano > 0) {
            $percentual_geral = ($faturamento_total_ano / $meta_fat_cumulativa) * 100;
        }
    } catch (PDOException $e) {
        error_log("Erro ao calcular percentual geral: " . $e->getMessage());
        $percentual_geral = 0;
    }
}

if (($metafat > 0) && $should_show_metas_cards): 
?>
<div class="carteira-stats">
    <div>
    <?php if ($metafat > 0): ?>
              <div class="stat-card meta-card">
         <!-- Header - Resumo da Carteira -->
         <div class="meta-header">
             <div class="meta-carteira-card">
                 <div class="meta-carteira-icon">
                     <i class="fas fa-users"></i>
                 </div>
                 <div class="meta-carteira-info">
                     <div class="meta-carteira-value"><?php echo number_format(count($todos_clientes ?? []), 0, ',', '.'); ?></div>
                     <div class="meta-carteira-label">Total de Clientes</div>
                 </div>
             </div>
             
             <div class="meta-carteira-card">
                 <div class="meta-carteira-icon">
                     <i class="fas fa-link"></i>
                 </div>
                 <div class="meta-carteira-info">
                     <div class="meta-carteira-value"><?php echo number_format(array_sum(array_column($todos_clientes ?? [], 'total_cnpjs_diferentes')), 0, ',', '.'); ?></div>
                     <div class="meta-carteira-label">CNPJs Consolidados</div>
                 </div>
             </div>
             
             <div class="meta-carteira-card">
                 <div class="meta-carteira-icon">
                     <i class="fas fa-chart-bar"></i>
                 </div>
                 <div class="meta-carteira-info">
                     <div class="meta-carteira-value">R$ <?php echo number_format($faturamento_mes_atual, 2, ',', '.'); ?></div>
                     <div class="meta-carteira-label">Faturamento do Mês</div>
                 </div>
             </div>
             
             <div class="meta-carteira-card">
                 <div class="meta-carteira-icon">
                     <i class="fas fa-bullseye"></i>
                 </div>
                 <div class="meta-carteira-info">
                     <div class="meta-carteira-value">R$ <?php echo number_format($metafat, 2, ',', '.'); ?></div>
                     <div class="meta-carteira-label"><?php echo $termo_meta; ?></div>
                     <div class="meta-carteira-status">
                         <?php if ($percentual_fat >= 100): ?>
                             <span class="status-badge atingida"><?php echo $termo_atingida; ?></span>
                         <?php elseif ($percentual_fat >= 80): ?>
                             <span class="status-badge proximo"><?php echo $termo_proxima; ?></span>
                         <?php else: ?>
                             <span class="status-badge abaixo"><?php echo $termo_baixa; ?></span>
                         <?php endif; ?>
                     </div>
                 </div>
             </div>
         </div>
         
                   <!-- Barra de Progresso -->
          <div class="meta-progress-container">
              <div class="meta-progress-bar">
                  <div class="meta-progress-fill <?php 
                      if ($percentual_fat >= 100) echo 'atingida';
                      elseif ($percentual_fat >= 80) echo 'proximo-meta';
                      else echo 'abaixo-meta';
                  ?>" style="width: <?php echo min($percentual_fat, 100); ?>%;"></div>
              </div>
              <div class="meta-progress-labels">
                  <span class="meta-progress-text">
                      R$ <?php echo number_format($faturamento_mes_atual, 2, ',', '.'); ?>
                      <span class="meta-progress-percentual <?php 
                          if ($percentual_fat >= 100) echo 'atingida';
                          elseif ($percentual_fat >= 80) echo 'proximo-meta';
                          else echo 'abaixo-meta';
                      ?>"><?php echo number_format($percentual_fat, 1, ',', '.'); ?>%</span>
                  </span>
                  <span class="meta-progress-target">R$ <?php echo number_format($metafat, 2, ',', '.'); ?></span>
              </div>
          </div>
    </div>
         <?php endif; ?>
     </div>
</div>

<!-- Novo Card Separado para Projeções e Status dos Clientes -->
<?php if ($metafat > 0): ?>
<div class="carteira-stats">
    <div>
        <div class="stat-card projecoes-status-card">
            <!-- Projeções e Status dos Clientes lado a lado -->
            <div class="projecoes-clientes-container">
                
                <!-- Projeções -->
                <div class="projecao-section">
                
                    <?php 
                    // Calcular projeção com proteção contra divisão por zero
                    $projecao_fat = 0;
                    if ($dias_passados > 0) {
                        $projecao_fat = $faturamento_mes_atual * ($dias_no_mes / $dias_passados);
                    }
                    $diferenca_fat = $metafat - $faturamento_mes_atual;
                    
                    $ritmo_atual = $dias_passados > 0 ? $faturamento_mes_atual / $dias_passados : 0;
                    ?>
                    <?php if ($percentual_fat < 100): ?>
                        <div>
                            <span class="projecao-text">
                                <i class="fas fa-chart-line"></i>
                                Projeção: R$ <?php echo number_format($projecao_fat, 2, ',', '.'); ?>
                            </span>
                        </div>
                        <div>
                            <span class="projecao-falta">
                                <i class="fas fa-exclamation-triangle"></i>
                                Falta: R$ <?php echo number_format($diferenca_fat, 2, ',', '.'); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div>
                            <span class="projecao-sucesso">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $termo_superada; ?> <?php echo number_format($percentual_fat - 100, 1, ',', '.'); ?>%
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <span class="ritmo-text">
                            <i class="fas fa-tachometer-alt"></i>
                            Ritmo Atual: R$ <?php echo number_format($ritmo_atual, 2, ',', '.'); ?>/dia
                        </span>
                    </div>
                </div>
                
                <!-- Status dos Clientes -->
                <div class="clientes-status-section">
                    <div class="clientes-title">
                        <i class="fas fa-users"></i> Status dos Clientes
                    </div>
                    <div class="clientes-chart-container">
                        <canvas id="statusClientesChart" width="150" height="150"></canvas>
                        <div class="clientes-legend">
                            <!-- Legenda será preenchida dinamicamente pelo JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?> 