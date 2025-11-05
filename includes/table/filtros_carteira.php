<!-- Filtros -->
<div class="filtros-container" style="max-width: 100%; width: 100%;">
    <div class="projecoes-clientes-container" style="max-width: 100%; width: 100%;">
        <div class="clientes-status-section" style="max-width: 100%; width: 100%;">
            <div class="clientes-title">
                <i class="fas fa-users"></i> Status dos Clientes
            </div>
            <div class="clientes-chart-container">
                <canvas id="statusClientesChart" width="150" height="150"></canvas>
                <div class="clientes-legend">
                </div>
            </div>
        </div>
    </div>
    <form method="GET" class="filtros-form" style="max-width: 100%; width: 100%;">
        <?php if (strtolower(trim($usuario['perfil'])) === 'supervisor' && isset($_GET['supervisor_apenas_proprios'])): ?>
            <input type="hidden" name="supervisor_apenas_proprios" value="<?php echo htmlspecialchars($_GET['supervisor_apenas_proprios']); ?>">
        <?php endif; ?>
        <div class="filtros-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; max-width: 100%;">
            <div class="filtro-grupo">
                <label for="filtro_inatividade">Status:</label>
                <select name="filtro_inatividade" id="filtro_inatividade">
                    <option value="">Todos</option>
                    <option value="ativo" <?php echo $filtro_inatividade === 'ativo' ? 'selected' : ''; ?>>Ativos (últimos 290 dias)</option>
                    <option value="inativando" <?php echo $filtro_inatividade === 'inativando' ? 'selected' : ''; ?>>Ativos... (290-365 dias)</option>
                    <option value="inativo" <?php echo $filtro_inatividade === 'inativo' ? 'selected' : ''; ?>>Inativos (+365 dias)</option>
                </select>
            </div>
            
            <div class="filtro-grupo">
                <label for="filtro_estado">Estado:</label>
                <select name="filtro_estado" id="filtro_estado">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $estado): ?>
                        <option value="<?php echo htmlspecialchars($estado); ?>" <?php echo $filtro_estado === $estado ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estado); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])): ?>
            <div class="filtro-grupo">
                <label for="visao_supervisor">Supervisão:</label>
                <select id="visao_supervisor" name="visao_supervisor" onchange="mudarVisao()">
                    <option value="">Todas as Equipes</option>
                    <?php
                    try {
                        $sql_supervisores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
                                           FROM USUARIOS u 
                                           INNER JOIN USUARIOS v ON v.COD_SUPER = u.COD_VENDEDOR 
                                           WHERE u.ATIVO = 1 AND u.PERFIL = 'supervisor' 
                                           AND v.ATIVO = 1 AND v.PERFIL IN ('vendedor', 'representante')
                                           ORDER BY u.NOME_COMPLETO";
                        $stmt_supervisores = $pdo->prepare($sql_supervisores);
                        $stmt_supervisores->execute();
                        $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) { $supervisores = []; }

                    $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
                    foreach ($supervisores as $supervisor):
                        $cod = (string)($supervisor['COD_VENDEDOR'] ?? '');
                        $nome = (string)($supervisor['NOME_COMPLETO'] ?? '');
                        $is_selected = ((string)$supervisor_selecionado === $cod) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($cod); ?>" <?php echo $is_selected; ?>>
                            <?php echo htmlspecialchars($nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (strtolower(trim($usuario['perfil'])) !== 'vendedor' && strtolower(trim($usuario['perfil'])) !== 'representante'): ?>
            <div class="filtro-grupo">
                <label for="filtro_vendedor">Vendedor:</label>
                <select name="filtro_vendedor" id="filtro_vendedor">
                    <option value="">Todos</option>
                    <?php foreach ($vendedores as $vendedor): ?>
                        <option value="<?php echo htmlspecialchars($vendedor['COD_VENDEDOR']); ?>" <?php echo $filtro_vendedor === $vendedor['COD_VENDEDOR'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vendedor['NOME_VENDEDOR']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filtro-grupo">
                <label for="filtro_segmento">Segmento:</label>
                <select name="filtro_segmento" id="filtro_segmento">
                    <option value="">Todos</option>
                    <?php foreach ($segmentos as $segmento): ?>
                        <option value="<?php echo htmlspecialchars($segmento); ?>" <?php echo $filtro_segmento === $segmento ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($segmento); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            
            <!-- Removidos filtros de valor mínimo/máximo -->
            
            <div class="filtro-grupo">
                <label for="filtro_ano">Ano:</label>
                <select name="filtro_ano" id="filtro_ano">
                    <option value="">Todos</option>
                    <?php 
                    $ano_atual = (int)date('Y');
                    for ($i = 0; $i < 6; $i++) {
                        $ano = (string)($ano_atual - $i);
                        $selected = ($filtro_ano ?? '') === $ano ? 'selected' : '';
                        echo "<option value='{$ano}' {$selected}>{$ano}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="filtro-grupo">
                <label for="filtro_mes">Mês:</label>
                <select name="filtro_mes" id="filtro_mes">
                    <option value="">Todos</option>
                    <?php 
                    $meses_pt = [
                        1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                        7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'
                    ];
                    for ($m = 1; $m <= 12; $m++) {
                        $selected = ((int)($filtro_mes ?? 0) === $m) ? 'selected' : '';
                        echo "<option value='{$m}' {$selected}>{$meses_pt[$m]}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        
        <div class="filtros-acoes">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="<?php 
                $url = basename($_SERVER['PHP_SELF']) . '?limpar_filtros=1';
                if (strtolower(trim($usuario['perfil'])) === 'supervisor' && isset($_GET['supervisor_apenas_proprios'])) {
                    $url .= '&supervisor_apenas_proprios=' . $_GET['supervisor_apenas_proprios'];
                }
                echo htmlspecialchars($url);
            ?>" class="btn btn-limpar-filtros" id="btnLimparFiltros">
                <i class="fas fa-times"></i> Limpar Filtros
            </a>
        </div>
    </form>
</div>

<div class="search-container" style="max-width: 100%; width: 100%;">
    <div class="search-wrapper" style="max-width: 100%; width: 100%;">
        <input type="text" id="searchClientes" class="search-input" placeholder="Buscar clientes por nome, CNPJ..." style="width: 100%; max-width: 100%;">
        <div id="searchStatus" class="search-status" style="display: none;">
            <i class="fas fa-search"></i>
            <span id="searchStatusText">Buscando...</span>
        </div>
    </div>
    <div id="searchResultsInfo" class="search-results-info" style="display: none;">
        <i class="fas fa-info-circle"></i>
        <span id="searchResultsText"></span>
        <button id="clearSearch" class="btn btn-sm btn-outline-secondary" style="margin-left: 10px;">
            <i class="fas fa-times"></i> Limpar busca
        </button>
    </div>
</div>

<?php 
// Mostrar informações dos filtros aplicados
$filtros_ativos = [];
if (!empty($filtro_inatividade)) {
    $status_text = '';
    if ($filtro_inatividade === 'ativo') $status_text = 'Ativos';
    elseif ($filtro_inatividade === 'inativando') $status_text = 'Ativos...';
    elseif ($filtro_inatividade === 'inativo') $status_text = 'Inativos';
    $filtros_ativos[] = "Status: " . $status_text;
}
if (!empty($filtro_estado)) $filtros_ativos[] = "Estado: " . $filtro_estado;
if (!empty($filtro_vendedor) && strtolower(trim($usuario['perfil'])) !== 'vendedor' && strtolower(trim($usuario['perfil'])) !== 'representante') {
    $nome_vendedor = '';
    foreach ($vendedores as $v) {
        if ($v['COD_VENDEDOR'] === $filtro_vendedor) {
            $nome_vendedor = $v['NOME_VENDEDOR'];
            break;
        }
    }
    $filtros_ativos[] = "Vendedor: " . $nome_vendedor;
}
if (!empty($filtro_segmento)) $filtros_ativos[] = "Segmento: " . $filtro_segmento;
if (!empty($filtro_ano)) $filtros_ativos[] = "Ano: " . $filtro_ano;
if (!empty($filtro_mes)) {
    $meses_pt = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    $filtros_ativos[] = "Mês: " . ($meses_pt[(int)$filtro_mes] ?? $filtro_mes);
}

// Adicionar informação de visão específica para diretores, admins e supervisores
if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
    $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
    if (!empty($supervisor_selecionado)) {
        // Buscar nome do supervisor selecionado
        $sql_nome_supervisor = "SELECT NOME_COMPLETO FROM USUARIOS WHERE COD_VENDEDOR = ?";
        $stmt_nome_supervisor = $pdo->prepare($sql_nome_supervisor);
        $stmt_nome_supervisor->execute([$supervisor_selecionado]);
        $nome_supervisor = $stmt_nome_supervisor->fetch(PDO::FETCH_COLUMN);
        $filtros_ativos[] = "<span style='color: #007bff; font-weight: 600;'>Visão: Equipe de " . htmlspecialchars($nome_supervisor) . "</span>";
    }
} elseif (strtolower(trim($usuario['perfil'])) === 'supervisor') {
    $supervisor_apenas_proprios = $_GET['supervisor_apenas_proprios'] ?? '';
    if ($supervisor_apenas_proprios === '1') {
        $filtros_ativos[] = "<span style='color: #28a745; font-weight: 600;'>Visão: Apenas Meus Clientes</span>";
    } else {
        $filtros_ativos[] = "<span style='color: #007bff; font-weight: 600;'>Visão: Minha Equipe</span>";
    }
} elseif (strtolower(trim($usuario['perfil'])) === 'vendedor' || strtolower(trim($usuario['perfil'])) === 'representante') {
    $filtros_ativos[] = "<span style='color: #007bff; font-weight: 600;'>Visão: Minha Carteira</span>";
}

if (!empty($filtros_ativos)): ?>
    <?php $filtros_title = strip_tags(implode(' | ', $filtros_ativos)); ?>
    <div class="filtros-info defer-lcp" title="<?php echo htmlspecialchars('Filtros aplicados: ' . $filtros_title); ?>">
        <i class="fas fa-filter"></i>
        <strong>Filtros aplicados:</strong> <?php echo implode(' | ', $filtros_ativos); ?>
        <span class="filtros-count">(<?php echo isset($total_registros) ? number_format($total_registros) : number_format($total_clientes); ?> clientes encontrados)</span>
    </div>
<?php endif; ?>

<!-- Informação sobre unificação de CNPJs -->
<?php $unificacao_title = 'Unificação de CNPJs: Clientes com a mesma raiz de CNPJ (primeiros 8 dígitos) são considerados o mesmo cliente. A data de última compra considerada é a mais recente entre todos os CNPJs unificados.'; ?>
<div class="filtros-info defer-lcp" style="border-left-color: #28a745;" title="<?php echo htmlspecialchars($unificacao_title); ?>">
    <i class="fas fa-link"></i>
    <strong>Unificação de CNPJs:</strong> Clientes com a mesma raiz de CNPJ (primeiros 8 dígitos) são considerados o mesmo cliente. A data de última compra considerada é a mais recente entre todos os CNPJs unificados.
</div>

