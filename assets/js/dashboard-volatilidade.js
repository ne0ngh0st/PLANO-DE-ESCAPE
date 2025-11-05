// Dashboard de Volatilidade - JavaScript específico

class DashboardVolatilidade {
    constructor() {
        this.charts = {};
        this.autoRefreshInterval = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeCharts();
        this.startAutoRefresh();
        this.setupTooltips();
    }

    setupEventListeners() {
        // Filtros
        const filterForm = document.querySelector('form[method="GET"]');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                this.showLoading();
            });
        }

        // Botões de ação
        const refreshBtn = document.querySelector('a[href="dashboard_volatilidade.php"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.refreshData();
            });
        }

        // Cards de métricas - adicionar efeito hover
        const metricCards = document.querySelectorAll('.metric-card');
        metricCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                this.animateCard(card, 'enter');
            });
            
            card.addEventListener('mouseleave', () => {
                this.animateCard(card, 'leave');
            });
        });
    }

    initializeCharts() {
        // Verificar se Chart.js está disponível
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js não está disponível');
            return;
        }

        // Gráfico de evolução
        this.initEvolucaoChart();
        
        // Gráfico de distribuição
        this.initDistribuicaoChart();
    }

    initEvolucaoChart() {
        const ctx = document.getElementById('evolucaoChart');
        if (!ctx) return;

        const metricasData = window.metricasData || [];
        const metricasComparacao = window.metricasComparacao || [];

        this.charts.evolucao = new Chart(ctx, {
            type: 'line',
            data: {
                labels: metricasData.map(m => new Date(m.data_metrica).toLocaleDateString('pt-BR')),
                datasets: [
                    {
                        label: 'WhatsApp',
                        data: metricasData.map(m => m.total_contatos_whatsapp),
                        borderColor: '#25D366',
                        backgroundColor: 'rgba(37, 211, 102, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#25D366',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'Presencial',
                        data: metricasData.map(m => m.total_contatos_presencial),
                        borderColor: '#FF6B6B',
                        backgroundColor: 'rgba(255, 107, 107, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#FF6B6B',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'Ligação',
                        data: metricasData.map(m => m.total_contatos_ligacao),
                        borderColor: '#4ECDC4',
                        backgroundColor: 'rgba(78, 205, 196, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#4ECDC4',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#666',
                            callback: function(value) {
                                return value.toLocaleString('pt-BR');
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    initDistribuicaoChart() {
        const ctx = document.getElementById('distribuicaoChart');
        if (!ctx) return;

        const metricasData = window.metricasData || [];
        const ultimaMetrica = metricasData[0];

        if (!ultimaMetrica) return;

        this.charts.distribuicao = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['WhatsApp', 'Presencial', 'Ligação'],
                datasets: [{
                    data: [
                        ultimaMetrica.total_contatos_whatsapp,
                        ultimaMetrica.total_contatos_presencial,
                        ultimaMetrica.total_contatos_ligacao
                    ],
                    backgroundColor: [
                        '#25D366',
                        '#FF6B6B',
                        '#4ECDC4'
                    ],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverBorderWidth: 4,
                    hoverBorderColor: '#667eea'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed.toLocaleString('pt-BR')} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    animateCard(card, action) {
        if (action === 'enter') {
            card.style.transform = 'translateY(-8px) scale(1.02)';
        } else {
            card.style.transform = 'translateY(0) scale(1)';
        }
    }

    showLoading() {
        const cards = document.querySelectorAll('.metric-card');
        cards.forEach(card => {
            const value = card.querySelector('.metric-value');
            if (value) {
                value.innerHTML = '<div class="loading-skeleton"></div>';
            }
        });
    }

    refreshData() {
        this.showLoading();
        window.location.reload();
    }

    startAutoRefresh() {
        // Auto-refresh a cada 5 minutos
        this.autoRefreshInterval = setInterval(() => {
            this.refreshData();
        }, 300000); // 5 minutos
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    setupTooltips() {
        // Adicionar tooltips aos cards de métricas
        const metricCards = document.querySelectorAll('.metric-card');
        metricCards.forEach(card => {
            const label = card.querySelector('.metric-label');
            if (label) {
                card.classList.add('tooltip-custom');
                card.setAttribute('data-tooltip', `Clique para ver detalhes de ${label.textContent}`);
            }
        });
    }

    // Método para atualizar gráficos com novos dados
    updateCharts(newData) {
        if (this.charts.evolucao) {
            this.charts.evolucao.data.labels = newData.map(m => new Date(m.data_metrica).toLocaleDateString('pt-BR'));
            this.charts.evolucao.data.datasets[0].data = newData.map(m => m.total_contatos_whatsapp);
            this.charts.evolucao.data.datasets[1].data = newData.map(m => m.total_contatos_presencial);
            this.charts.evolucao.data.datasets[2].data = newData.map(m => m.total_contatos_ligacao);
            this.charts.evolucao.update('active');
        }

        if (this.charts.distribuicao && newData.length > 0) {
            const ultimaMetrica = newData[0];
            this.charts.distribuicao.data.datasets[0].data = [
                ultimaMetrica.total_contatos_whatsapp,
                ultimaMetrica.total_contatos_presencial,
                ultimaMetrica.total_contatos_ligacao
            ];
            this.charts.distribuicao.update('active');
        }
    }

    // Método para exportar dados
    exportData(format = 'csv') {
        const metricasData = window.metricasData || [];
        
        if (format === 'csv') {
            this.exportToCSV(metricasData);
        } else if (format === 'json') {
            this.exportToJSON(metricasData);
        }
    }

    exportToCSV(data) {
        const headers = [
            'Data', 'WhatsApp', 'Presencial', 'Ligação', 
            'Clientes Ativos', 'Clientes Inativos', 'Faturamento', 'Meta'
        ];
        
        const csvContent = [
            headers.join(','),
            ...data.map(row => [
                row.data_metrica,
                row.total_contatos_whatsapp,
                row.total_contatos_presencial,
                row.total_contatos_ligacao,
                row.total_clientes_ativos,
                row.total_clientes_inativos,
                row.faturamento_dia,
                row.meta_dia
            ].join(','))
        ].join('\n');

        this.downloadFile(csvContent, 'metricas_volatilidade.csv', 'text/csv');
    }

    exportToJSON(data) {
        const jsonContent = JSON.stringify(data, null, 2);
        this.downloadFile(jsonContent, 'metricas_volatilidade.json', 'application/json');
    }

    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    // Método para mostrar notificações
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove após 5 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se estamos na página de volatilidade
    if (document.querySelector('.dashboard-volatilidade') || 
        window.location.pathname.includes('dashboard_volatilidade')) {
        
        window.dashboardVolatilidade = new DashboardVolatilidade();
        
        // Adicionar botões de exportação se não existirem
        const chartContainer = document.querySelector('.chart-container');
        if (chartContainer && !document.querySelector('.export-buttons')) {
            const exportButtons = document.createElement('div');
            exportButtons.className = 'export-buttons mb-3';
            exportButtons.innerHTML = `
                <button class="btn btn-outline-primary btn-sm me-2" onclick="window.dashboardVolatilidade.exportData('csv')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.dashboardVolatilidade.exportData('json')">
                    <i class="fas fa-file-code"></i> Exportar JSON
                </button>
            `;
            chartContainer.insertBefore(exportButtons, chartContainer.firstChild);
        }
    }
});

// Limpar intervalos quando a página for fechada
window.addEventListener('beforeunload', function() {
    if (window.dashboardVolatilidade) {
        window.dashboardVolatilidade.stopAutoRefresh();
    }
});


