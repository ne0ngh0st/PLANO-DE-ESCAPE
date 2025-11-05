<?php
require_once __DIR__ . '/../../includes/config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Detalhes do Gerenciador';

// Verificar se foi passado um gerenciador
$gerenciador = $_GET['gerenciador'] ?? '';
if (empty($gerenciador)) {
    header('Location: ' . base_url('contratos'));
    exit;
}

// Buscar dados das licitações do gerenciador
$licitacoes = [];
$erro_mensagem = '';
$total_ata = 0;
$total_contratado = 0;
$total_consumido = 0;
$total_saldo = 0;

try {
    // Verificar se é o GRUPO AVN ou IF-MG e ajustar a query
    $where_clause = '';
    $params = [];
    
    if ($gerenciador === 'GRUPO AVN') {
        // Para GRUPO AVN, buscar apenas gerenciadores que contêm AVN (sem IF-MG)
        $where_clause = "WHERE (GERENCIADOR LIKE '%AVN%' OR GERENCIADOR LIKE '%A V N%') AND (STATUS IS NULL OR STATUS = '' OR STATUS != 'Excluído')";
    } elseif ($gerenciador === 'IF - MG') {
        // Para IF-MG, buscar apenas gerenciadores que contêm IF-MG
        $where_clause = "WHERE (GERENCIADOR LIKE '%IF%' AND GERENCIADOR LIKE '%MG%') AND (STATUS IS NULL OR STATUS = '' OR STATUS != 'Excluído')";
    } else {
        // Para outros gerenciadores, buscar exatamente o nome
        $where_clause = "WHERE GERENCIADOR = ? AND (STATUS IS NULL OR STATUS = '' OR STATUS != 'Excluído')";
        $params[] = $gerenciador;
    }
    
    // Buscar todas as licitações não excluídas da tabela LICITACAO
    $sql = "SELECT 
                ID,
                COD_CLIENT,
                ORGAO,
                SIGLA,
                GERENCIADOR,
                ORGAO as razao_social,
                NUMERO_ATA as numero_pregao,
                NUMERO_CONTRATO as termo_contrato,
                VALOR_GLOBAL as valor_global,
                COALESCE(VALOR_CONSUMIDO, 0) as valor_consumido,
                DATA_INICIO_CONTRATO as data_inicio_vigencia,
                DATA_TERMINO_CONTRATO as data_termino_vigencia,
                STATUS as status,
                CNPJ as cnpj,
                TIPO as tipo,
                PRODUTO as produto,
                GRUPO as grupo,
                TABELA as tabela,
                DATA_INICIO_ATA as data_inicio_ata,
                DATA_TERMINO_ATA as data_termino_ata,
                VALOR_ATA as valor_ata,
                SALDO_CONTRATO as saldo_contrato,
                CONSUMO_CONTRATO_PERCENT as consumo_contrato_percent
            FROM LICITACAO
            $where_clause
            ORDER BY SIGLA, ORGAO";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $licitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ordenar as licitações por porcentagem de consumo (decrescente) no PHP
    // para evitar reorganização no JavaScript
    usort($licitacoes, function($a, $b) {
        $valor_global_a = floatval($a['valor_global'] ?? 0);
        $valor_consumido_a = floatval($a['valor_consumido'] ?? 0);
        $percentual_a = $valor_global_a > 0 ? ($valor_consumido_a / $valor_global_a) * 100 : 0;
        
        $valor_global_b = floatval($b['valor_global'] ?? 0);
        $valor_consumido_b = floatval($b['valor_consumido'] ?? 0);
        $percentual_b = $valor_global_b > 0 ? ($valor_consumido_b / $valor_global_b) * 100 : 0;
        
        return $percentual_b <=> $percentual_a; // Decrescente (maior percentual primeiro)
    });
    
    // Calcular totais
    foreach ($licitacoes as $licitacao) {
        $total_ata += floatval($licitacao['valor_ata'] ?? 0);
        $total_contratado += floatval($licitacao['valor_global']);
        $total_consumido += floatval($licitacao['valor_consumido']);
        $total_saldo += floatval($licitacao['saldo_contrato']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar licitações do gerenciador: " . $e->getMessage());
    $erro_mensagem = $e->getMessage();
}

// Verificar permissões para exclusão
$pode_excluir = false;
if (isset($usuario['perfil'])) {
    $perfil = strtolower(trim($usuario['perfil']));
    $pode_excluir = in_array($perfil, ['admin', 'diretor']);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($usuario['secondary_color'] ?? '#ff8f00'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/detalhes-gerenciador.css'); ?>?v=<?php echo time(); ?>">
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include dirname(__DIR__, 2) . '/includes/components/navbar.php'; ?>
        
        <div class="dashboard-container">
            <main class="dashboard-main">
                <!-- Cards de Resumo -->
                <div class="resumo-cards">
                    <div class="resumo-card card-ata">
                        <div class="resumo-card-content">
                            <h3>Valor ATA</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_ata, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-contratado">
                        <div class="resumo-card-content">
                            <h3>Total Contratado</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_contratado, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-consumido">
                        <div class="resumo-card-content">
                            <h3>Total Faturado</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_consumido, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-saldo">
                        <div class="resumo-card-content">
                            <h3>Saldo Disponível</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_saldo, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-percentual">
                        <div class="resumo-card-content">
                            <h3>% FATURADO</h3>
                            <p class="resumo-card-valor">
                                <?php 
                                $percentual_consumido = $total_contratado > 0 
                                    ? ($total_consumido / $total_contratado) * 100 
                                    : 0;
                                echo number_format($percentual_consumido, 1, ',', '.'); 
                                ?>%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Lista de Licitações -->
                <div class="licitacoes-section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> <?php echo htmlspecialchars($gerenciador); ?> (<?php echo count($licitacoes); ?>)</h2>
                        <div class="section-actions">
                            <!-- Barra de Pesquisa -->
                            <div class="search-container">
                                <div class="search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" id="searchInput" placeholder="Pesquisar por ATA, código cliente, contrato, órgão..." 
                                           onkeyup="filtrarLicitacoes()" oninput="filtrarLicitacoes()">
                                    <button class="btn-clear-search" id="clearSearchBtn" onclick="limparPesquisa()" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <a href="<?php echo base_url('contratos'); ?>" class="btn-action-compact btn-back-compact" title="Voltar para Contratos">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <button class="btn-action-compact btn-documentos-compact" onclick="verContratos('<?php echo htmlspecialchars($gerenciador); ?>')" title="Ver Contratos">
                                <i class="fas fa-file-alt"></i>
                            </button>
                            <button class="btn-action-compact btn-refresh-compact" onclick="atualizarDados()" title="Atualizar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn-action-compact btn-secondary-compact" onclick="exportarDados()" title="Exportar">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Informações dos Resultados da Pesquisa -->
                    <div class="search-results-info" id="searchResultsInfo" style="display: none;">
                        <span id="searchResultsText"></span>
                    </div>

                    <?php if (empty($licitacoes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhuma licitação encontrada para este gerenciador</p>
                        </div>
                    <?php else: ?>
                        <div class="licitacoes-grid">
                            <?php foreach ($licitacoes as $licitacao): 
                                $valor_global = floatval($licitacao['valor_global'] ?? 0);
                                $valor_consumido = floatval($licitacao['valor_consumido'] ?? 0);
                                $saldo = floatval($licitacao['saldo_contrato'] ?? 0);
                                $percentual = $valor_global > 0 ? ($valor_consumido / $valor_global) * 100 : 0;
                                
                                // Determinar classe de status baseado no percentual - LÓGICA INVERTIDA
                                $status_class = '';
                                if ($percentual >= 70) {
                                    $status_class = 'status-normal'; // Verde - bem consumido (70%+)
                                } elseif ($percentual >= 30) {
                                    $status_class = 'status-alerta'; // Amarelo - consumo médio (30-69%)
                                } else {
                                    $status_class = 'status-critico'; // Vermelho - pouco consumido (0-29%)
                                }
                                
                                // Calcular dias restantes
                                $dias_restantes = '';
                                if (!empty($licitacao['data_termino_vigencia'])) {
                                    $data_termino = new DateTime($licitacao['data_termino_vigencia']);
                                    $hoje = new DateTime();
                                    $diferenca = $hoje->diff($data_termino);
                                    $dias_restantes = $diferenca->days;
                                    
                                    if ($hoje > $data_termino) {
                                        $dias_restantes = 'Vencido (' . $dias_restantes . ' dias)';
                                    } else {
                                        $dias_restantes = $dias_restantes . ' dias restantes';
                                    }
                                }
                            ?>
                                <div class="licitacao-card <?php echo $status_class; ?>">
                                    <div class="licitacao-header">
                                        <div class="licitacao-titulo">
                                            <h3>
                                                <i class="fas fa-building"></i>
                                                <?php echo htmlspecialchars($licitacao['razao_social'] ?? ''); ?>
                                            </h3>
                                            <?php if (!empty($licitacao['sigla'])): ?>
                                                <span class="sigla-badge"><?php echo htmlspecialchars($licitacao['sigla'] ?? ''); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="licitacao-status">
                                            <span class="status-badge status-<?php echo strtolower($licitacao['status'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($licitacao['status'] ?? ''); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="licitacao-info">
                                        <div class="info-row">
                                            <span class="info-label">Código Cliente:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($licitacao['COD_CLIENT'] ?? ''); ?></span>
                                        </div>
                                        
                                        <?php if (!empty($licitacao['numero_pregao'])): ?>
                                        <div class="info-row">
                                            <span class="info-label">Número ATA:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($licitacao['numero_pregao'] ?? ''); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($licitacao['termo_contrato'])): ?>
                                        <div class="info-row">
                                            <span class="info-label">Número Contrato:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($licitacao['termo_contrato'] ?? ''); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($licitacao['tipo'])): ?>
                                        <div class="info-row">
                                            <span class="info-label">Tipo:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($licitacao['tipo'] ?? ''); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($licitacao['produto'])): ?>
                                        <div class="info-row">
                                            <span class="info-label">Produto:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($licitacao['produto'] ?? ''); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="licitacao-valores">
                                        <div class="valor-item">
                                            <span class="valor-label">Valor Contratado</span>
                                            <span class="valor-amount">R$ <?php echo number_format($valor_global, 2, ',', '.'); ?></span>
                                        </div>
                                        <div class="valor-item">
                                            <span class="valor-label">Valor Consumido</span>
                                            <span class="valor-amount valor-consumido">R$ <?php echo number_format($valor_consumido, 2, ',', '.'); ?></span>
                                        </div>
                                        <div class="valor-item">
                                            <span class="valor-label">Saldo Disponível</span>
                                            <span class="valor-amount valor-saldo">R$ <?php echo number_format($saldo, 2, ',', '.'); ?></span>
                                        </div>
                                    </div>

                                    <div class="licitacao-progresso">
                                        <div class="progress-header">
                                            <span>Consumo</span>
                                            <span class="progress-percentual"><?php echo number_format($percentual, 1, ',', '.'); ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min($percentual, 100); ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="licitacao-prazos">
                                        <?php if (!empty($licitacao['data_inicio_vigencia'])): ?>
                                        <div class="prazo-item">
                                            <span class="prazo-label">Início da Vigência</span>
                                            <span class="prazo-value"><?php echo date('d/m/Y', strtotime($licitacao['data_inicio_vigencia'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($licitacao['data_termino_vigencia'])): ?>
                                        <div class="prazo-item">
                                            <span class="prazo-label">Término da Vigência</span>
                                            <span class="prazo-value"><?php echo date('d/m/Y', strtotime($licitacao['data_termino_vigencia'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($dias_restantes)): ?>
                                        <div class="prazo-item">
                                            <span class="prazo-label">Status</span>
                                            <span class="prazo-value <?php echo (strpos($dias_restantes, 'Vencido') !== false) ? 'prazo-vencido' : 'prazo-ativo'; ?>">
                                                <?php echo $dias_restantes; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="licitacao-actions">
                                        <button class="btn-edit-compact" onclick="editarLicitacao(<?php echo $licitacao['ID']; ?>)" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($pode_excluir): ?>
                                        <button class="btn-danger-compact" onclick="excluirLicitacao(<?php echo $licitacao['ID']; ?>)" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
            <?php include dirname(__DIR__, 2) . '/includes/components/footer.php'; ?>
        </div>
    </div>

    <!-- Modal de Edição de Licitação -->
    <div id="modalEditarLicitacao" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Licitação</h2>
                <button class="modal-close" onclick="fecharModalEdicao()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formEditarLicitacao">
                    <input type="hidden" id="licitacao_id" name="id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_cod_cliente">Código do Cliente</label>
                            <input type="text" id="licitacao_cod_cliente" name="cod_cliente" placeholder="Ex: 123456">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_cnpj">CNPJ</label>
                            <input type="text" id="licitacao_cnpj" name="cnpj" placeholder="00.000.000/0000-00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_gerenciador">Gerenciador *</label>
                            <input type="text" id="licitacao_gerenciador" name="gerenciador" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="licitacao_status">Status</label>
                            <select id="licitacao_status" name="status">
                                <option value="Vigente">Vigente</option>
                                <option value="Ativo">Ativo</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="Suspenso">Suspenso</option>
                                <option value="Encerrado">Encerrado</option>
                                <option value="Contrato não acionado">Contrato não acionado</option>
                                <option value="Falta 60 dias">Falta 60 dias</option>
                                <option value="Falta 30 dias">Falta 30 dias</option>
                                <option value="Falta 15 dias">Falta 15 dias</option>
                                <option value="Falta 7 dias">Falta 7 dias</option>
                                <option value="Vencido">Vencido</option>
                                <option value="Cancelado">Cancelado</option>
                                <option value="Renovado">Renovado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_sigla">Sigla</label>
                            <input type="text" id="licitacao_sigla" name="sigla" placeholder="Ex: TJSP, PMSP">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_razao_social">Órgão/Razão Social *</label>
                            <input type="text" id="licitacao_razao_social" name="razao_social" required placeholder="Nome da empresa/órgão">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_tipo">Tipo</label>
                            <select id="licitacao_tipo" name="tipo">
                                <option value="">Selecione o tipo</option>
                                <option value="Supply">Supply</option>
                                <option value="Services">Services</option>
                                <option value="Works">Works</option>
                                <option value="Consultoria">Consultoria</option>
                                <option value="Manutenção">Manutenção</option>
                                <option value="Suporte">Suporte</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="licitacao_produto">Produto</label>
                            <input type="text" id="licitacao_produto" name="produto" placeholder="Ex: Rede Suprimentos, Software">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_numero_pregao">Número do Pregão/ATA</label>
                            <input type="text" id="licitacao_numero_pregao" name="numero_pregao" placeholder="Ex: 123/2024">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_termo_contrato">Termo do Contrato</label>
                            <input type="text" id="licitacao_termo_contrato" name="termo_contrato" placeholder="Ex: TC-001/2024">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_valor_ata">Valor ATA</label>
                            <input type="number" id="licitacao_valor_ata" name="valor_ata" step="0.01" min="0" placeholder="0,00">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_valor_global">Valor Global *</label>
                            <input type="number" id="licitacao_valor_global" name="valor_global" required step="0.01" min="0" placeholder="0,00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_valor_consumido">Valor Consumido</label>
                            <input type="number" id="licitacao_valor_consumido" name="valor_consumido" step="0.01" min="0" placeholder="0,00">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_grupo">Grupo</label>
                            <input type="number" id="licitacao_grupo" name="grupo" placeholder="Ex: 940">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_tabela">Tabela</label>
                            <input type="text" id="licitacao_tabela" name="tabela" placeholder="Ex: 229">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_edital">Edital de Licitação</label>
                            <input type="text" id="licitacao_edital" name="edital_licitacao" placeholder="Número do edital">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_data_inicio_ata">Data de Início da ATA</label>
                            <input type="date" id="licitacao_data_inicio_ata" name="data_inicio_ata">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_data_termino_ata">Data de Término da ATA</label>
                            <input type="date" id="licitacao_data_termino_ata" name="data_termino_ata">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="licitacao_data_inicio">Data de Início da Vigência</label>
                            <input type="date" id="licitacao_data_inicio" name="data_inicio_vigencia">
                        </div>
                        <div class="form-group">
                            <label for="licitacao_data_termino">Data de Término da Vigência</label>
                            <input type="date" id="licitacao_data_termino" name="data_termino_vigencia">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="fecharModalEdicao()">
                            Cancelar
                        </button>
                        <button type="button" class="btn-save" onclick="salvarEdicaoLicitacao()">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização de Contratos -->
    <div id="modalContratos" class="modal">
        <div class="modal-content modal-extra-large">
            <div class="modal-header">
                <h2><i class="fas fa-file-alt"></i> <span id="modalContratosTitulo">Contratos do Gerenciador</span></h2>
                <button class="modal-close" onclick="fecharModal('modalContratos')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalContratosBody">
                <div class="loading-container" style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #1a237e;"></i>
                    <p style="margin-top: 1rem; color: #5f6368;">Carregando contratos...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url('assets/js/detalhes-gerenciador.js'); ?>?v=<?php echo time(); ?>"></script>
    
    <?php include dirname(__DIR__, 2) . '/includes/components/nav_hamburguer.php'; ?>
</body>
</html>
