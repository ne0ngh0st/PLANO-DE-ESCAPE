<?php 
// Garantir $usuario definido quando chamado via include/AJAX
if (!isset($usuario) && isset($_SESSION['usuario'])) {
    $usuario = $_SESSION['usuario'];
}

// Definir termos baseado no perfil do usuário
$perfil_atual = strtolower(trim($usuario['perfil'] ?? ''));
$is_representante = $perfil_atual === 'representante';
$termo_meta = $is_representante ? 'Objetivo' : 'Meta';
$termo_metas = $is_representante ? 'Objetivos' : 'Metas';
$termo_atingida = $is_representante ? 'Objetivo Atingido!' : 'Meta Atingida!';
$termo_proxima = $is_representante ? 'Próximo do Objetivo' : 'Próximo da Meta';
$termo_baixa = $is_representante ? 'Baixa Performance' : 'Baixa Performance';

// Verificar se deve mostrar a seção de detalhes de metas
$should_show_metas_details = false;

// Mostrar se uma das visões estiver selecionada
if ($perfil_atual === 'supervisor' || $perfil_atual === 'diretor' || $perfil_atual === 'admin') {
    $should_show_metas_details = true;
}

// Ou se o parâmetro show_metas estiver definido
if (isset($_GET['show_metas']) && $_GET['show_metas'] === '1') {
    $should_show_metas_details = true;
}

if ($should_show_metas_details): 
?>
<!-- Seção de Detalhes de Metas por Vendedor -->
<div class="metas-detalhes-container" style="padding-top: 0 !important;">
    <div class="metas-content" id="metas-content" style="display: block;">
    
    <?php
    // Buscar vendedores baseado no perfil
    if ($perfil_atual === 'supervisor') {
        $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO, META_FATURAMENTO 
                         FROM USUARIOS 
                         WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
                         ORDER BY NOME_COMPLETO";
        $stmt_vendedores = $pdo->prepare($sql_vendedores);
        $stmt_vendedores->execute([$usuario['cod_vendedor']]);
    } elseif ($perfil_atual === 'diretor' || $perfil_atual === 'admin') {
        // Para diretores e admins, verificar se há filtro de supervisor
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        
        if (!empty($supervisor_selecionado)) {
            // Buscar vendedores de um supervisor específico
            $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO, META_FATURAMENTO 
                             FROM USUARIOS 
                             WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
                             ORDER BY NOME_COMPLETO";
            $stmt_vendedores = $pdo->prepare($sql_vendedores);
            $stmt_vendedores->execute([$supervisor_selecionado]);
        } else {
            // Buscar todos os vendedores
            $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO, META_FATURAMENTO 
                             FROM USUARIOS 
                             WHERE ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
                             ORDER BY NOME_COMPLETO";
            $stmt_vendedores = $pdo->prepare($sql_vendedores);
            $stmt_vendedores->execute();
        }
    } else {
        // Para vendedor/representante: mostrar somente ele mesmo
        $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO, META_FATURAMENTO 
                         FROM USUARIOS 
                         WHERE COD_VENDEDOR = ? AND ATIVO = 1 ";
        $stmt_vendedores = $pdo->prepare($sql_vendedores);
        $stmt_vendedores->execute([$usuario['cod_vendedor'] ?? '']);
    }
    
    $vendedores_metas = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular faturamento e percentual para cada vendedor
    $vendedores_com_dados = [];
    foreach ($vendedores_metas as $vendedor) {
        // Calcular faturamento do vendedor no mês atual
        $mes_atual = date('m');
        $ano_atual = date('Y');
        
        $sql_fat_vendedor = "SELECT 
            SUM(VLR_TOTAL) as total_faturamento
            FROM FATURAMENTO 
            WHERE COD_VENDEDOR = ? 
            AND MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ? 
            AND YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
        
        $stmt_fat = $pdo->prepare($sql_fat_vendedor);
        $stmt_fat->execute([$vendedor['COD_VENDEDOR'], $mes_atual, $ano_atual]);
        $fat_result = $stmt_fat->fetch(PDO::FETCH_ASSOC);
        
        // Se não encontrar na FATURAMENTO, tentar ultimo_faturamento
        if (!$fat_result || $fat_result['total_faturamento'] == 0) {
            $sql_fat_vendedor = "SELECT 
                SUM(VLR_TOTAL) as total_faturamento
                FROM ultimo_faturamento 
                WHERE COD_VENDEDOR = ? 
                AND MONTH(STR_TO_DATE(DT_FAT, '%d/%m/%Y')) = ? 
                AND YEAR(STR_TO_DATE(DT_FAT, '%d/%m/%Y')) = ?";
            
            $stmt_fat = $pdo->prepare($sql_fat_vendedor);
            $stmt_fat->execute([$vendedor['COD_VENDEDOR'], $mes_atual, $ano_atual]);
            $fat_result = $stmt_fat->fetch(PDO::FETCH_ASSOC);
        }
        
        $faturamento_atual = floatval($fat_result['total_faturamento'] ?? 0);
        
        // Se o valor é muito grande (>= 10000000), assumir que está em centavos
        if ($faturamento_atual >= 10000000) {
            $faturamento_atual = $faturamento_atual / 100;
        }
        
        $meta_fat = floatval($vendedor['META_FATURAMENTO'] ?? 0);
        
        // Se o valor da meta é muito grande (>= 10000000), assumir que está em centavos
        if ($meta_fat >= 10000000) {
            $meta_fat = $meta_fat / 100;
        }
        
        // Calcular percentual
        $percentual_fat = $meta_fat > 0 ? ($faturamento_atual / $meta_fat) * 100 : 0;
        
        // Determinar status para ordenação
        $status_ordem = 0; // Baixa performance
        if ($percentual_fat >= 100) {
            $status_ordem = 3; // Meta atingida (maior prioridade)
        } elseif ($percentual_fat >= 80) {
            $status_ordem = 2; // Próximo da meta
        } else {
            $status_ordem = 1; // Baixa performance
        }
        
        // Só adicionar vendedores que têm faturamento maior que zero
        if ($faturamento_atual > 0) {
            $vendedores_com_dados[] = [
                'vendedor' => $vendedor,
                'faturamento_atual' => $faturamento_atual,
                'meta_fat' => $meta_fat,
                'percentual_fat' => $percentual_fat,
                'status_ordem' => $status_ordem
            ];
        }
    }
    
    // Ordenar: primeiro por status (atingida > próxima > baixa), depois por faturamento decrescente
    usort($vendedores_com_dados, function($a, $b) {
        if ($a['status_ordem'] !== $b['status_ordem']) {
            return $b['status_ordem'] - $a['status_ordem']; // Status decrescente
        }
        return $b['faturamento_atual'] - $a['faturamento_atual']; // Faturamento decrescente
    });
    
    // Configuração da paginação
    $itens_por_pagina = 20;
    $pagina_atual = isset($_GET['pagina_metas']) ? (int)$_GET['pagina_metas'] : 1;
    $total_vendedores = count($vendedores_com_dados);
    $total_paginas = ceil($total_vendedores / $itens_por_pagina);
    $inicio = ($pagina_atual - 1) * $itens_por_pagina;
    $vendedores_paginados = array_slice($vendedores_com_dados, $inicio, $itens_por_pagina);
    
    if ($vendedores_paginados): ?>
    <div class="carteira-stats">
        <?php 
        // Card da Meta Pessoal do Supervisor (apenas para supervisores)
        if (strtolower(trim($usuario['perfil'])) === 'supervisor') {
            // Buscar meta pessoal do supervisor
            $sql_meta_supervisor = "SELECT META_FATURAMENTO FROM USUARIOS WHERE COD_VENDEDOR = ?";
            $stmt_meta_supervisor = $pdo->prepare($sql_meta_supervisor);
            $stmt_meta_supervisor->execute([$usuario['cod_vendedor']]);
            $meta_supervisor = $stmt_meta_supervisor->fetch(PDO::FETCH_COLUMN);
            
            if ($meta_supervisor) {
                // Calcular faturamento pessoal do supervisor no mês atual
                $mes_atual = date('m');
                $ano_atual = date('Y');
                
                $sql_fat_supervisor = "SELECT 
                    SUM(VLR_TOTAL) as total_faturamento
                    FROM FATURAMENTO 
                    WHERE COD_VENDEDOR = ? 
                    AND MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ? 
                    AND YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
                
                $stmt_fat_supervisor = $pdo->prepare($sql_fat_supervisor);
                $stmt_fat_supervisor->execute([$usuario['cod_vendedor'], $mes_atual, $ano_atual]);
                $fat_supervisor_result = $stmt_fat_supervisor->fetch(PDO::FETCH_ASSOC);
                
                // Se não encontrar na FATURAMENTO, tentar ultimo_faturamento
                if (!$fat_supervisor_result || $fat_supervisor_result['total_faturamento'] == 0) {
                    $sql_fat_supervisor = "SELECT 
                        SUM(VLR_TOTAL) as total_faturamento
                        FROM ultimo_faturamento 
                        WHERE COD_VENDEDOR = ? 
                        AND MONTH(STR_TO_DATE(DT_FAT, '%d/%m/%Y')) = ? 
                        AND YEAR(STR_TO_DATE(DT_FAT, '%d/%m/%Y')) = ?";
                    
                    $stmt_fat_supervisor = $pdo->prepare($sql_fat_supervisor);
                    $stmt_fat_supervisor->execute([$usuario['cod_vendedor'], $mes_atual, $ano_atual]);
                    $fat_supervisor_result = $stmt_fat_supervisor->fetch(PDO::FETCH_ASSOC);
                }
                
                $faturamento_supervisor = floatval($fat_supervisor_result['total_faturamento'] ?? 0);
                
                // Se o valor é muito grande (>= 10000000), assumir que está em centavos
                if ($faturamento_supervisor >= 10000000) {
                    $faturamento_supervisor = $faturamento_supervisor / 100;
                }
                
                $meta_supervisor_valor = floatval($meta_supervisor);
                
                // Se o valor da meta é muito grande (>= 10000000), assumir que está em centavos
                if ($meta_supervisor_valor >= 10000000) {
                    $meta_supervisor_valor = $meta_supervisor_valor / 100;
                }
                
                // Calcular percentual
                $percentual_supervisor = $meta_supervisor_valor > 0 ? ($faturamento_supervisor / $meta_supervisor_valor) * 100 : 0;
                
                // Determinar status
                $status_supervisor = $percentual_supervisor >= 100 ? 'atingida' : ($percentual_supervisor >= 80 ? 'proxima' : 'baixa');
        ?>
        <!-- Card da Meta Pessoal do Supervisor -->
        <div class="stat-card meta-card supervisor-personal-card <?php 
            if ($percentual_supervisor >= 100) {
                echo 'meta-atingida-card';
            } elseif ($percentual_supervisor >= 80) {
                echo 'meta-proxima-card';
            } else {
                echo 'meta-baixa-card';
            }
        ?>" style="border: 2px solid var(--primary-color); background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color-light) 100%); color: white;">
            <div style="text-align: center; margin-bottom: 0.5rem;">
                <i class="fas fa-crown" style="font-size: 1.2rem; margin-bottom: 0.25rem;"></i>
                <div style="font-weight: 600; font-size: 0.8rem; margin-bottom: 0.25rem;">MINHA META PESSOAL</div>
            </div>
            
            <h3 style="color: white; margin-bottom: 0.5rem;">R$ <?php echo number_format($faturamento_supervisor, 2, ',', '.'); ?></h3>
            <p style="color: white; opacity: 0.9; margin-bottom: 1rem;"><i class="fas fa-chart-bar"></i> Meu Faturamento do Mês</p>
            
            <!-- Barra de Progresso -->
            <div class="meta-progress-container">
                <div class="meta-progress-bar" style="background: rgba(255,255,255,0.3);">
                    <div class="meta-progress-fill <?php echo $percentual_supervisor >= 100 ? 'meta-atingida' : ($percentual_supervisor >= 80 ? 'meta-proxima' : 'meta-baixa'); ?>" 
                         style="width: <?php echo min($percentual_supervisor, 100); ?>%; background: white;"></div>
                </div>
                <div class="meta-progress-labels" style="color: white;">
                    <span class="meta-progress-text">
                        R$ <?php echo number_format($faturamento_supervisor, 2, ',', '.'); ?>
                        <span class="meta-progress-percentual"><?php echo number_format($percentual_supervisor, 1, ',', '.'); ?>%</span>
                    </span>
                    <span class="meta-progress-target">R$ <?php echo number_format($meta_supervisor_valor, 2, ',', '.'); ?></span>
                </div>
            </div>
            
            <div class="meta-info" style="margin-top: 0.5rem;">
                <span class="meta-label" style="color: white; opacity: 0.9;">Meta: R$ <?php echo number_format($meta_supervisor_valor, 2, ',', '.'); ?></span>
                <span class="meta-percentual <?php echo $percentual_supervisor >= 100 ? 'meta-atingida' : ($percentual_supervisor >= 80 ? 'meta-proxima' : 'meta-baixa'); ?>" style="color: white; font-weight: 600;">
                    <?php echo number_format($percentual_supervisor, 1, ',', '.'); ?>%
                </span>
            </div>
            
            <!-- Status -->
            <div class="meta-status" style="margin-top: 0.5rem;">
                <?php if ($percentual_supervisor >= 100): ?>
                    <span class="status-atingida" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white;">Meta Atingida!</span>
                <?php elseif ($percentual_supervisor >= 80): ?>
                    <span class="status-proxima" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white;">Próximo da Meta</span>
                <?php else: ?>
                    <span class="status-baixa" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white;">Baixa Performance</span>
                <?php endif; ?>
            </div>
            
            <!-- Informações do Supervisor -->
            <div class="vendedor-info" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.3); text-align: center;">
                <div style="font-weight: 600; color: white; margin-bottom: 0.1rem; font-size: 0.7rem; line-height: 1.1;">
                    <?php echo htmlspecialchars(substr($usuario['nome'], 0, 20)) . (strlen($usuario['nome']) > 20 ? '...' : ''); ?>
                </div>
                <div style="font-size: 0.6rem; color: rgba(255,255,255,0.8);">
                    Supervisor - <?php echo $usuario['cod_vendedor']; ?>
                </div>
            </div>
        </div>
        <?php 
            }
        }
        ?>
        
        <?php foreach ($vendedores_paginados as $dados_vendedor): 
            $vendedor = $dados_vendedor['vendedor'];
            $faturamento_atual = $dados_vendedor['faturamento_atual'];
            $meta_fat = $dados_vendedor['meta_fat'];
            $percentual_fat = $dados_vendedor['percentual_fat'];
            
            // Determinar status
            $status_fat = $percentual_fat >= 100 ? 'atingida' : ($percentual_fat >= 80 ? 'proxima' : 'baixa');
        ?>
        <div class="stat-card meta-card <?php 
            if ($percentual_fat >= 100) {
                echo 'meta-atingida-card';
            } elseif ($percentual_fat >= 80) {
                echo 'meta-proxima-card';
            } else {
                echo 'meta-baixa-card';
            }
        ?>">
            <h3>R$ <?php echo number_format($faturamento_atual, 2, ',', '.'); ?></h3>
            <p><i class="fas fa-chart-bar"></i> Faturamento do Mês</p>
            
            <!-- Barra de Progresso -->
            <div class="meta-progress-container">
                <div class="meta-progress-bar">
                    <div class="meta-progress-fill <?php echo $percentual_fat >= 100 ? 'meta-atingida' : ($percentual_fat >= 80 ? 'meta-proxima' : 'meta-baixa'); ?>" 
                         style="width: <?php echo min($percentual_fat, 100); ?>%"></div>
                </div>
                <div class="meta-progress-labels">
                    <span class="meta-progress-text">
                        R$ <?php echo number_format($faturamento_atual, 2, ',', '.'); ?>
                        <span class="meta-progress-percentual"><?php echo number_format($percentual_fat, 1, ',', '.'); ?>%</span>
                    </span>
                    <span class="meta-progress-target">R$ <?php echo number_format($meta_fat, 2, ',', '.'); ?></span>
                </div>
            </div>
            
            <div class="meta-info">
                <span class="meta-label"><?php echo $termo_meta; ?>: R$ <?php echo number_format($meta_fat, 2, ',', '.'); ?></span>
                <span class="meta-percentual <?php echo $percentual_fat >= 100 ? 'meta-atingida' : ($percentual_fat >= 80 ? 'meta-proxima' : 'meta-baixa'); ?>">
                    <?php echo number_format($percentual_fat, 1, ',', '.'); ?>%
                </span>
            </div>
            
            <!-- Status -->
            <div class="meta-status">
                <?php if ($percentual_fat >= 100): ?>
                    <span class="status-atingida"><?php echo $termo_atingida; ?></span>
                <?php elseif ($percentual_fat >= 80): ?>
                    <span class="status-proxima"><?php echo $termo_proxima; ?></span>
                <?php else: ?>
                    <span class="status-baixa"><?php echo $termo_baixa; ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Informações do Vendedor -->
            <div class="vendedor-info" style="margin-top: 0.25rem; padding-top: 0.25rem; border-top: 1px solid var(--border-color); text-align: center;">
                <div style="font-weight: 600; color: var(--dark-color); margin-bottom: 0.1rem; font-size: 0.7rem; line-height: 1.1;">
                    <?php echo htmlspecialchars(substr($vendedor['NOME_COMPLETO'], 0, 20)) . (strlen($vendedor['NOME_COMPLETO']) > 20 ? '...' : ''); ?>
                </div>
                <div style="font-size: 0.6rem; color: var(--text-muted);">
                    <?php echo $vendedor['COD_VENDEDOR']; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination-container" style="margin-top: 1rem; text-align: center;">
        <div class="pagination-info" style="margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.8rem;">
            Mostrando <?php echo $inicio + 1; ?> a <?php echo min($inicio + $itens_por_pagina, $total_vendedores); ?> de <?php echo $total_vendedores; ?> vendedores
        </div>
        <div class="pagination">
            <?php if ($pagina_atual > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina_metas' => $pagina_atual - 1])); ?>" class="page-link" style="padding: 0.3rem 0.6rem; border: 1px solid var(--border-color); background: var(--white); color: var(--text-color); text-decoration: none; border-radius: 4px; margin: 0 0.1rem; font-size: 0.8rem;">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina_metas' => $i])); ?>" 
                   class="page-link <?php echo $i === $pagina_atual ? 'active' : ''; ?>" 
                   style="padding: 0.3rem 0.6rem; border: 1px solid var(--border-color); background: <?php echo $i === $pagina_atual ? 'var(--primary-color)' : 'var(--white)'; ?>; color: <?php echo $i === $pagina_atual ? 'var(--white)' : 'var(--text-color)'; ?>; text-decoration: none; border-radius: 4px; margin: 0 0.1rem; font-size: 0.8rem;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($pagina_atual < $total_paginas): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina_metas' => $pagina_atual + 1])); ?>" class="page-link" style="padding: 0.3rem 0.6rem; border: 1px solid var(--border-color); background: var(--white); color: var(--text-color); text-decoration: none; border-radius: 4px; margin: 0 0.1rem; font-size: 0.8rem;">
                    Próximo <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="no-data">
        <i class="fas fa-info-circle"></i>
        <p>Nenhum vendedor com faturamento no mês encontrado para exibir metas.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

