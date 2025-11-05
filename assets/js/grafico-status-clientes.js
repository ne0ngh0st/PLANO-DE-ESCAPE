// ========================================
// GRÁFICO DE STATUS DOS CLIENTES
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
                maintainAspectRatio: true,
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
    // Passar TODOS os parâmetros de filtro para contar toda a carteira com os mesmos filtros
    const urlParams = new URLSearchParams(window.location.search);
    
    // Usar caminho absoluto para evitar problemas de diretório relativo
    let url = '/Site/includes/reports/grafico_status_clientes.php';
    
    // Montar query string com todos os parâmetros de filtro
    const queryParams = [];
    
    // Adicionar todos os parâmetros de filtro que existem na URL atual
    const filtros = [
        'supervisor_apenas_proprios',
        'visao_supervisor',
        'filtro_estado',
        'filtro_vendedor',
        'filtro_segmento',
        'filtro_ano',
        'filtro_mes',
        'filtro_inatividade',
        'pesquisa_geral'
    ];
    
    filtros.forEach(param => {
        const value = urlParams.get(param);
        if (value) {
            queryParams.push(param + '=' + encodeURIComponent(value));
        }
    });
    
    if (queryParams.length > 0) {
        url += '?' + queryParams.join('&');
    }
    
    fetch(url, {
        method: 'GET',
        credentials: 'same-origin', // Incluir cookies da sessão
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(async response => {
            console.log('Resposta da API de status dos clientes:', response.status, response.statusText);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return await response.json();
        })
        .then(data => {
            console.log('Dados de status dos clientes recebidos:', data);
            if (data && data.success) {
                criarGraficoStatusClientes(data.dados);
            } else {
                console.warn('API sem sucesso. Usando fallback DOM.');
                inicializarGraficoStatusClientesViaDOM();
            }
        })
        .catch(error => {
            console.warn('Falha na API de status; usando fallback DOM:', error);
            inicializarGraficoStatusClientesViaDOM();
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

// Fallback: computar dados a partir do DOM já renderizado (tabela/cards)
function inicializarGraficoStatusClientesViaDOM() {
    try {
        const ativos = document.querySelectorAll('.status-badge.status-ativo').length;
        const inativando = document.querySelectorAll('.status-badge.status-inativando').length;
        const inativos = document.querySelectorAll('.status-badge.status-inativo').length;

        const total = ativos + inativando + inativos;
        if (total === 0) {
            mostrarErroGraficoStatusClientes('Sem dados para exibir');
            return;
        }

        criarGraficoStatusClientes({ ativos, inativando, inativos });
    } catch (e) {
        console.error('Erro no fallback DOM do gráfico de status:', e);
        mostrarErroGraficoStatusClientes('Erro ao processar dados');
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