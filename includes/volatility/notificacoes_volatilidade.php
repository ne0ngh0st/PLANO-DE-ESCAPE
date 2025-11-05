<?php
// Componente de notificações de volatilidade
if (isset($pdo)) {
    try {
        require_once 'sistema_alertas_volatilidade.php';
        $sistema_alertas = new SistemaAlertasVolatilidade($pdo);
        $alertas_recentes = $sistema_alertas->obterAlertasRecentes(5);
        $estatisticas_alertas = $sistema_alertas->obterEstatisticasAlertas(7);
    } catch (Exception $e) {
        $alertas_recentes = [];
        $estatisticas_alertas = [];
    }
} else {
    $alertas_recentes = [];
    $estatisticas_alertas = [];
}
?>

<!-- Componente de Notificações de Volatilidade -->
<div class="notificacoes-volatilidade">
    <!-- Badge de notificações no header -->
    <div class="notification-badge-container">
        <button class="btn btn-outline-light position-relative" id="btnNotificacoes" data-bs-toggle="dropdown">
            <i class="fas fa-bell"></i>
            <?php if (count($alertas_recentes) > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?php echo count($alertas_recentes); ?>
                </span>
            <?php endif; ?>
        </button>
        
        <!-- Dropdown de notificações -->
        <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="min-width: 350px; max-height: 400px; overflow-y: auto;">
            <div class="dropdown-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    Alertas de Volatilidade
                </h6>
                <small class="text-muted" id="ultimaAtualizacao">
                    <?php echo date('H:i'); ?>
                </small>
            </div>
            <div class="dropdown-divider"></div>
            
            <!-- Lista de alertas -->
            <div id="listaAlertas">
                <?php if (empty($alertas_recentes)): ?>
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-check-circle text-success mb-2"></i>
                        <p class="mb-0">Nenhum alerta ativo</p>
                        <small>Todas as métricas estão dentro dos limites normais</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($alertas_recentes as $alerta): ?>
                        <div class="alert-item-dropdown alert-<?php echo $alerta['severidade']; ?>" data-alerta-id="<?php echo $alerta['id']; ?>">
                            <div class="d-flex align-items-start">
                                <div class="flex-shrink-0 me-2">
                                    <?php
                                    $icones = [
                                        'critica' => 'fas fa-exclamation-triangle text-danger',
                                        'alta' => 'fas fa-exclamation-circle text-warning',
                                        'media' => 'fas fa-info-circle text-info',
                                        'baixa' => 'fas fa-check-circle text-success'
                                    ];
                                    $icone = $icones[$alerta['severidade']] ?? 'fas fa-info-circle text-info';
                                    ?>
                                    <i class="<?php echo $icone; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong class="alert-title">
                                                <?php echo ucfirst(str_replace('_', ' ', $alerta['tipo_alerta'])); ?>
                                            </strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('d/m H:i', strtotime($alerta['data_criacao'])); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $alerta['severidade'] === 'critica' ? 'danger' : ($alerta['severidade'] === 'alta' ? 'warning' : ($alerta['severidade'] === 'media' ? 'info' : 'success')); ?> badge-sm">
                                            <?php echo ucfirst($alerta['severidade']); ?>
                                        </span>
                                    </div>
                                    <p class="alert-description mb-1">
                                        <?php echo htmlspecialchars($alerta['descricao']); ?>
                                    </p>
                                    <div class="alert-change">
                                        <span class="badge bg-<?php echo $alerta['percentual_mudanca'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo ($alerta['percentual_mudanca'] > 0 ? '+' : '') . number_format($alerta['percentual_mudanca'], 1); ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <button class="btn btn-sm btn-outline-secondary btn-marcar-lido" 
                                            data-alerta-id="<?php echo $alerta['id']; ?>"
                                            title="Marcar como lido">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="dropdown-divider"></div>
            
            <!-- Estatísticas rápidas -->
            <div class="px-3 py-2">
                <div class="row text-center">
                    <?php
                    $cores_severidade = [
                        'critica' => 'danger',
                        'alta' => 'warning', 
                        'media' => 'info',
                        'baixa' => 'success'
                    ];
                    ?>
                    <?php foreach ($estatisticas_alertas as $stat): ?>
                        <div class="col-3">
                            <div class="text-<?php echo $cores_severidade[$stat['severidade']] ?? 'secondary'; ?>">
                                <strong><?php echo $stat['total']; ?></strong>
                                <br>
                                <small><?php echo ucfirst($stat['severidade']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="dropdown-divider"></div>
            
            <!-- Ações -->
            <div class="px-3 py-2">
                <div class="d-grid gap-2">
                    <a href="dashboard_volatilidade.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-chart-line"></i> Ver Dashboard Completo
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="atualizarNotificacoes()">
                        <i class="fas fa-sync-alt"></i> Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para notificações de volatilidade */
.notification-badge-container .btn {
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.8);
    transition: all 0.3s ease;
}

.notification-badge-container .btn:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
}

.notification-dropdown {
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    padding: 0;
}

.notification-dropdown .dropdown-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem;
    border-radius: 10px 10px 0 0;
}

.alert-item-dropdown {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.3s ease;
}

.alert-item-dropdown:hover {
    background-color: #f8f9fa;
}

.alert-item-dropdown:last-child {
    border-bottom: none;
}

.alert-title {
    font-size: 0.9rem;
    color: #333;
}

.alert-description {
    font-size: 0.8rem;
    color: #666;
    line-height: 1.4;
}

.alert-change .badge {
    font-size: 0.7rem;
}

.btn-marcar-lido {
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

.btn-marcar-lido:hover {
    opacity: 1;
}

.badge-sm {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Animações */
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-item-dropdown {
    animation: slideInDown 0.3s ease-out;
}

/* Responsividade */
@media (max-width: 768px) {
    .notification-dropdown {
        min-width: 300px !important;
        max-width: 90vw;
    }
}
</style>

<script>
// JavaScript para notificações de volatilidade
class NotificacoesVolatilidade {
    constructor() {
        this.intervaloAtualizacao = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.iniciarAtualizacaoAutomatica();
    }

    setupEventListeners() {
        // Botões de marcar como lido
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-marcar-lido')) {
                const alertaId = e.target.closest('.btn-marcar-lido').dataset.alertaId;
                this.marcarComoLido(alertaId);
            }
        });

        // Atualizar notificações ao abrir dropdown
        const btnNotificacoes = document.getElementById('btnNotificacoes');
        if (btnNotificacoes) {
            btnNotificacoes.addEventListener('click', () => {
                this.atualizarNotificacoes();
            });
        }
    }

    iniciarAtualizacaoAutomatica() {
        // Atualizar a cada 2 minutos
        this.intervaloAtualizacao = setInterval(() => {
            this.atualizarNotificacoes();
        }, 120000);
    }

    async atualizarNotificacoes() {
        try {
            const response = await fetch('includes/sistema_alertas_volatilidade.php?action=alertas_recentes&limite=5');
            const data = await response.json();
            
            if (data.status === 'success') {
                this.atualizarListaAlertas(data.alertas);
                this.atualizarTimestamp();
            }
        } catch (error) {
            console.error('Erro ao atualizar notificações:', error);
        }
    }

    atualizarListaAlertas(alertas) {
        const listaAlertas = document.getElementById('listaAlertas');
        if (!listaAlertas) return;

        if (alertas.length === 0) {
            listaAlertas.innerHTML = `
                <div class="text-center py-3 text-muted">
                    <i class="fas fa-check-circle text-success mb-2"></i>
                    <p class="mb-0">Nenhum alerta ativo</p>
                    <small>Todas as métricas estão dentro dos limites normais</small>
                </div>
            `;
        } else {
            listaAlertas.innerHTML = alertas.map(alerta => this.criarItemAlerta(alerta)).join('');
        }

        // Atualizar badge de contagem
        this.atualizarBadgeContagem(alertas.length);
    }

    criarItemAlerta(alerta) {
        const icones = {
            'critica': 'fas fa-exclamation-triangle text-danger',
            'alta': 'fas fa-exclamation-circle text-warning',
            'media': 'fas fa-info-circle text-info',
            'baixa': 'fas fa-check-circle text-success'
        };

        const cores = {
            'critica': 'danger',
            'alta': 'warning',
            'media': 'info',
            'baixa': 'success'
        };

        const icone = icones[alerta.severidade] || 'fas fa-info-circle text-info';
        const cor = cores[alerta.severidade] || 'secondary';
        const dataFormatada = new Date(alerta.data_criacao).toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <div class="alert-item-dropdown alert-${alerta.severidade}" data-alerta-id="${alerta.id}">
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0 me-2">
                        <i class="${icone}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong class="alert-title">
                                    ${alerta.tipo_alerta.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                </strong>
                                <br>
                                <small class="text-muted">${dataFormatada}</small>
                            </div>
                            <span class="badge bg-${cor} badge-sm">
                                ${alerta.severidade.charAt(0).toUpperCase() + alerta.severidade.slice(1)}
                            </span>
                        </div>
                        <p class="alert-description mb-1">
                            ${alerta.descricao}
                        </p>
                        <div class="alert-change">
                            <span class="badge bg-${alerta.percentual_mudanca > 0 ? 'success' : 'danger'}">
                                ${alerta.percentual_mudanca > 0 ? '+' : ''}${parseFloat(alerta.percentual_mudanca).toFixed(1)}%
                            </span>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <button class="btn btn-sm btn-outline-secondary btn-marcar-lido" 
                                data-alerta-id="${alerta.id}"
                                title="Marcar como lido">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    atualizarBadgeContagem(quantidade) {
        const btnNotificacoes = document.getElementById('btnNotificacoes');
        if (!btnNotificacoes) return;

        const badge = btnNotificacoes.querySelector('.badge');
        if (quantidade > 0) {
            if (!badge) {
                const novoBadge = document.createElement('span');
                novoBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                novoBadge.textContent = quantidade;
                btnNotificacoes.appendChild(novoBadge);
            } else {
                badge.textContent = quantidade;
            }
        } else if (badge) {
            badge.remove();
        }
    }

    atualizarTimestamp() {
        const timestamp = document.getElementById('ultimaAtualizacao');
        if (timestamp) {
            timestamp.textContent = new Date().toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }

    async marcarComoLido(alertaId) {
        try {
            const formData = new FormData();
            formData.append('action', 'marcar_lido');
            formData.append('alerta_id', alertaId);

            const response = await fetch('includes/sistema_alertas_volatilidade.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                // Remover o item da lista
                const itemAlerta = document.querySelector(`[data-alerta-id="${alertaId}"]`);
                if (itemAlerta) {
                    itemAlerta.style.opacity = '0.5';
                    setTimeout(() => {
                        itemAlerta.remove();
                        this.atualizarBadgeContagem(
                            document.querySelectorAll('.alert-item-dropdown').length
                        );
                    }, 300);
                }
            }
        } catch (error) {
            console.error('Erro ao marcar alerta como lido:', error);
        }
    }

    destruir() {
        if (this.intervaloAtualizacao) {
            clearInterval(this.intervaloAtualizacao);
        }
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('btnNotificacoes')) {
        window.notificacoesVolatilidade = new NotificacoesVolatilidade();
    }
});

// Função global para atualizar notificações
function atualizarNotificacoes() {
    if (window.notificacoesVolatilidade) {
        window.notificacoesVolatilidade.atualizarNotificacoes();
    }
}

// Limpar intervalos quando a página for fechada
window.addEventListener('beforeunload', function() {
    if (window.notificacoesVolatilidade) {
        window.notificacoesVolatilidade.destruir();
    }
});
</script>
