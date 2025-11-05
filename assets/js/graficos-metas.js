// ========================================
// GRÁFICOS DE METAS E PROJEÇÕES
// ========================================

// Função para criar o gráfico de pizza de status dos clientes
function criarGraficoStatusClientes(dados) {
    console.log('Criando gráfico de status dos clientes com dados:', dados);
    
    const canvas = document.getElementById('statusClientesChart');
    if (!canvas) {
        console.error('Canvas do gráfico de status dos clientes não encontrado');
        return;
    }
    
    // Verificar se Chart.js está disponível
    if (typeof Chart === 'undefined') {
        console.error('Chart.js não está carregado');
        return;
    }
    
    try {
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Ativos', 'Inativando', 'Inativos'],
                datasets: [{
                    data: [dados.ativos, dados.inativando, dados.inativos],
                    backgroundColor: [
                        '#28a745', // Verde para ativos
                        '#ffc107', // Amarelo para inativando
                        '#dc3545'  // Vermelho para inativos
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // Usamos nossa própria legenda
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#ffffff',
                        borderWidth: 1,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000,
                    easing: 'easeOutQuart'
                },
                elements: {
                    arc: {
                        borderWidth: 2
                    }
                }
            }
        });
        
        console.log('Gráfico de status dos clientes criado com sucesso');
        
        // Atualizar a legenda com os dados
        atualizarLegendaStatusClientes(dados);
        
    } catch (error) {
        console.error('Erro ao criar gráfico de status dos clientes:', error);
    }
}

// Função para atualizar a legenda do gráfico
function atualizarLegendaStatusClientes(dados) {
    const legendContainer = document.querySelector('.clientes-legend');
    if (!legendContainer) {
        console.error('Container da legenda não encontrado');
        return;
    }
    
    const total = dados.ativos + dados.inativando + dados.inativos;
    
    const legendItems = [
        {
            label: 'Ativos',
            color: '#28a745',
            count: dados.ativos,
            percent: total > 0 ? ((dados.ativos / total) * 100).toFixed(1) : '0.0'
        },
        {
            label: 'Inativando',
            color: '#ffc107',
            count: dados.inativando,
            percent: total > 0 ? ((dados.inativando / total) * 100).toFixed(1) : '0.0'
        },
        {
            label: 'Inativos',
            color: '#dc3545',
            count: dados.inativos,
            percent: total > 0 ? ((dados.inativos / total) * 100).toFixed(1) : '0.0'
        }
    ];
    
    legendContainer.innerHTML = legendItems.map(item => `
        <div class="legend-item">
            <span class="legend-color" style="background-color: ${item.color};"></span>
            <span class="legend-text">${item.label} (${item.count})</span>
            <span class="legend-percent">${item.percent}%</span>
        </div>
    `).join('');
}

// Função para inicializar o gráfico quando o DOM estiver pronto
function inicializarGraficoStatusClientes() {
    console.log('Inicializando gráfico de status dos clientes...');
    
    // Buscar dados do gráfico via AJAX
    fetch('/Site/includes/reports/grafico_status_clientes.php', {
        method: 'GET',
        credentials: 'same-origin', // Incluir cookies da sessão
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => {
            console.log('Resposta da API de status dos clientes:', response.status, response.statusText);
            return response.json();
        })
        .then(data => {
            console.log('Dados de status dos clientes recebidos:', data);
            if (data.success) {
                criarGraficoStatusClientes(data.dados);
            } else {
                console.error('Erro na API de status dos clientes:', data.message);
                mostrarErroGraficoStatusClientes(data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados do gráfico de status dos clientes:', error);
            mostrarErroGraficoStatusClientes('Erro ao carregar dados');
        });
}

// Função para mostrar erro no gráfico
function mostrarErroGraficoStatusClientes(mensagem) {
    const container = document.querySelector('.clientes-chart-container');
    if (container) {
        container.innerHTML = `
            <div class="grafico-status-clientes-erro">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Erro ao carregar gráfico: ${mensagem}</p>
            </div>
        `;
    }
}

// Função para atualizar o gráfico
function atualizarGraficoStatusClientes() {
    console.log('Atualizando gráfico de status dos clientes...');
    inicializarGraficoStatusClientes();
}

// Função para animar a entrada do gráfico
function animarEntradaGraficoStatusClientes() {
    const container = document.querySelector('.clientes-chart-container');
    if (container) {
        container.style.opacity = '0';
        container.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            container.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, 100);
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado - inicializando gráfico de status dos clientes');
    
    // Aguardar um pouco para garantir que o Chart.js foi carregado
    setTimeout(() => {
        // Inicializar gráfico de status dos clientes
        inicializarGraficoStatusClientes();
        animarEntradaGraficoStatusClientes();
    }, 500);
});

// Exportar funções para uso global
window.GraficoStatusClientes = {
    criar: criarGraficoStatusClientes,
    atualizar: atualizarGraficoStatusClientes,
    inicializar: inicializarGraficoStatusClientes
};
