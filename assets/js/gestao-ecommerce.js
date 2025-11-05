// ========================================
// GESTÃO E-COMMERCE - JAVASCRIPT
// ========================================

// Variáveis globais
let dadosEcommerce = null;
let chartFaturamento = null;
let chartCustos = null;
let chartProdutos = null;
let chartExpandido = null;
let paginaAtual = 1;
let itensPorPagina = 10;
let paginaAtualVendas = 1;
let itensPorPaginaVendas = 20;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Configurar datas padrão (últimos 7 dias para ser mais rápido)
    const hoje = new Date();
    const seteDiasAtras = new Date(hoje);
    seteDiasAtras.setDate(hoje.getDate() - 7);
    
    document.getElementById('dataInicial').valueAsDate = seteDiasAtras;
    document.getElementById('dataFinal').valueAsDate = hoje;
    
    // Event listeners
    document.getElementById('btnRefresh').addEventListener('click', carregarDados);
    document.getElementById('btnFiltrar').addEventListener('click', carregarDados);
    document.getElementById('btnLimpar').addEventListener('click', limparFiltros);
    document.getElementById('btnExport').addEventListener('click', exportarDados);
    document.getElementById('searchTable').addEventListener('input', filtrarTabela);
    document.getElementById('ordenarPor').addEventListener('change', ordenarProdutos);
    
    // Carregar dados iniciais
    carregarDados();
});

// Função para carregar dados
async function carregarDados() {
    try {
        // Mostrar loading
        mostrarLoading();
        mostrarAlertaCarregamento();
        
        // Obter filtros
        const dataInicial = document.getElementById('dataInicial').value;
        const dataFinal = document.getElementById('dataFinal').value;
        const produto = document.getElementById('filtroProdu').value;
        const limite = document.getElementById('limite')?.value || 50;
        
        // Construir URL para API LOCAL (muito mais rápido!)
        let url = '/Site/includes/api/buscar_dados_local.php?';
        if (dataInicial) url += `data_inicial=${dataInicial}&`;
        if (dataFinal) url += `data_final=${dataFinal}&`;
        if (produto) url += `produto=${encodeURIComponent(produto)}`;
        
        // Fazer requisição
        const response = await fetch(url);
        const data = await response.json();
        
        // Verificar se precisa autorizar integração
        if (data.requer_autorizacao) {
            if (confirm('A integração com o Bling não está configurada. Deseja configurar agora?')) {
                window.location.href = '/Site/includes/api/bling_autorizar.php';
            }
            return;
        }
        
        // Verificar se precisa sincronizar
        if (data.requer_sincronizacao) {
            if (confirm('Nenhum dado sincronizado encontrado. Deseja sincronizar agora?')) {
                window.location.href = '/Site/bling_sync_control.php';
            }
            return;
        }
        
        if (!response.ok || data.erro) {
            throw new Error(data.mensagem || data.erro || 'Erro ao carregar dados');
        }
        
        dadosEcommerce = data;
        
        // Atualizar interface
        atualizarResumo(data.resumo);
        atualizarAnaliseFrete(data.analise_fretes);
        atualizarGraficos(data);
        atualizarTabelaProdutos(data.produtos);
        atualizarTabelaVendas(data.vendas_detalhadas);
        
        // Mostrar informações de sincronização
        if (data.info_sincronizacao) {
            console.log('Sincronização:', data.info_sincronizacao);
            mostrarInfoSincronizacao(data.info_sincronizacao);
        }
        
        esconderAlertaCarregamento();
        
    } catch (error) {
        console.error('Erro:', error);
        esconderAlertaCarregamento();
        mostrarErro('Erro ao carregar dados: ' + error.message);
    }
}

// Função para mostrar alerta de carregamento
function mostrarAlertaCarregamento() {
    const alerta = document.getElementById('alertaCarregamento');
    if (alerta) {
        const limite = document.getElementById('limite')?.value || 50;
        const tempoEstimado = Math.ceil(limite * 0.2);
        document.getElementById('mensagemProgresso').textContent = 
            `Buscando detalhes de até ${limite} pedidos. Tempo estimado: ~${tempoEstimado} segundos. Por favor, aguarde...`;
        alerta.style.display = 'block';
    }
}

// Função para esconder alerta de carregamento
function esconderAlertaCarregamento() {
    const alerta = document.getElementById('alertaCarregamento');
    if (alerta) {
        alerta.style.display = 'none';
    }
}

// Função para mostrar info de sincronização
function mostrarInfoSincronizacao(syncInfo) {
    if (!syncInfo.ultima_sincronizacao) return;
    
    const container = document.querySelector('.ecommerce-container');
    const alerta = document.createElement('div');
    alerta.className = 'alert-sync';
    
    const dataSync = new Date(syncInfo.ultima_sincronizacao);
    const agora = new Date();
    const diferencaHoras = Math.floor((agora - dataSync) / (1000 * 60 * 60));
    
    let mensagem = '';
    if (diferencaHoras === 0) {
        mensagem = 'há menos de 1 hora';
    } else if (diferencaHoras === 1) {
        mensagem = 'há 1 hora';
    } else if (diferencaHoras < 24) {
        mensagem = `há ${diferencaHoras} horas`;
    } else {
        const dias = Math.floor(diferencaHoras / 24);
        mensagem = `há ${dias} dia${dias > 1 ? 's' : ''}`;
    }
    
    alerta.innerHTML = `
        <i class="fas fa-database"></i> 
        <strong>Dados sincronizados ${mensagem}</strong> 
        (${syncInfo.pedidos_sincronizados} pedidos) - 
        <a href="/Site/bling_sync_control.php" style="color: inherit; text-decoration: underline;">Sincronizar novamente</a>
        <button onclick="this.parentElement.remove()" style="float: right; background: none; border: none; color: inherit; cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
    `;
    alerta.style.cssText = 'background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #c3e6cb; font-size: 14px;';
    
    const pageHeader = container.querySelector('.page-header');
    if (pageHeader && pageHeader.nextSibling) {
        pageHeader.parentNode.insertBefore(alerta, pageHeader.nextSibling);
    } else {
        container.insertBefore(alerta, container.firstChild);
    }
}

// Função para mostrar erro
function mostrarErro(mensagem) {
    const container = document.querySelector('.ecommerce-container');
    const alerta = document.createElement('div');
    alerta.className = 'alert-error';
    alerta.innerHTML = `
        <i class="fas fa-exclamation-circle"></i> ${mensagem}
        <button onclick="this.parentElement.remove()" style="float: right; background: none; border: none; color: inherit; cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
    `;
    alerta.style.cssText = 'background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;';
    container.insertBefore(alerta, container.firstChild);
    
    // Remover após 5 segundos
    setTimeout(() => alerta.remove(), 5000);
}

// Função para mostrar loading
function mostrarLoading() {
    document.getElementById('tabelaProdutosBody').innerHTML = `
        <tr>
            <td colspan="8" class="loading-cell">
                <i class="fas fa-spinner fa-spin"></i> Carregando dados...
            </td>
        </tr>
    `;
    
    document.getElementById('tabelaVendasBody').innerHTML = `
        <tr>
            <td colspan="6" class="loading-cell">
                <i class="fas fa-spinner fa-spin"></i> Carregando dados...
            </td>
        </tr>
    `;
}

// Função para atualizar resumo
function atualizarResumo(resumo) {
    document.getElementById('totalVendas').textContent = formatarMoeda(resumo.total_vendas || 0);
    document.getElementById('quantidadeVendas').textContent = `${resumo.quantidade_vendas || 0} vendas`;
    document.getElementById('valorProdutos').textContent = formatarMoeda(resumo.valor_produtos || 0);
    document.getElementById('totalFretes').textContent = formatarMoeda(resumo.total_fretes || 0);
    document.getElementById('percentualFrete').textContent = `${(resumo.percentual_frete || 0).toFixed(1)}% do total`;
    document.getElementById('totalDespesas').textContent = formatarMoeda(resumo.total_despesas || 0);
    document.getElementById('percentualDespesas').textContent = `${(resumo.percentual_despesas || 0).toFixed(1)}% do total`;
}

// Função para atualizar análise de frete
function atualizarAnaliseFrete(frete) {
    document.getElementById('pedidosComFrete').textContent = frete.pedidos_com_frete || 0;
    document.getElementById('percentualPedidosComFrete').textContent = `${(frete.percentual_pedidos_com_frete || 0).toFixed(1)}% do total`;
    document.getElementById('pedidosSemFrete').textContent = frete.pedidos_sem_frete || 0;
    document.getElementById('percentualPedidosSemFrete').textContent = `${(frete.percentual_pedidos_sem_frete || 0).toFixed(1)}% do total`;
    document.getElementById('ticketMedioFrete').textContent = formatarMoeda(frete.ticket_medio_frete || 0);
    document.getElementById('maiorFrete').textContent = formatarMoeda(frete.maior_frete || 0);
    document.getElementById('produtoMaiorFrete').textContent = frete.produto_maior_frete || '-';
}

// Função para atualizar gráficos
function atualizarGraficos(data) {
    // Converter faturamento por período para formato do gráfico
    const faturamentoPeriodo = data.faturamento_periodo || {};
    const vendasPorData = Object.keys(faturamentoPeriodo).map(mesAno => ({
        data: mesAno,
        valor_total: faturamentoPeriodo[mesAno]
    }));
    
    atualizarGraficoFaturamento(vendasPorData);
    atualizarGraficoCustos(data.resumo);
    atualizarGraficoProdutos(data.top10_produtos || data.produtos.slice(0, 10));
}

// Gráfico de Faturamento por Período
function atualizarGraficoFaturamento(vendasPorData) {
    const ctx = document.getElementById('chartFaturamento').getContext('2d');
    
    // Destruir gráfico anterior se existir
    if (chartFaturamento) {
        chartFaturamento.destroy();
    }
    
    // Preparar dados
    const labels = vendasPorData.map(v => v.data);
    const valores = vendasPorData.map(v => v.valor_total);
    
    chartFaturamento = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Faturamento Total',
                data: valores,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });
}

// Gráfico de Composição de Custos
function atualizarGraficoCustos(resumo) {
    const ctx = document.getElementById('chartCustos').getContext('2d');
    
    // Destruir gráfico anterior se existir
    if (chartCustos) {
        chartCustos.destroy();
    }
    
    chartCustos = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Produtos', 'Fretes', 'Outras Despesas'],
            datasets: [{
                data: [
                    resumo.valor_produtos || 0,
                    resumo.total_fretes || 0,
                    resumo.total_despesas || 0
                ],
                backgroundColor: [
                    '#059669',
                    '#f59e0b',
                    '#ef4444'
                ],
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': R$ ' + context.parsed.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Gráfico Top 10 Produtos
function atualizarGraficoProdutos(produtos) {
    const ctx = document.getElementById('chartProdutos').getContext('2d');
    
    // Destruir gráfico anterior se existir
    if (chartProdutos) {
        chartProdutos.destroy();
    }
    
    // Pegar top 10
    const top10 = produtos.slice(0, 10);
    const labels = top10.map(p => truncarTexto(p.descricao, 40));
    const valores = top10.map(p => p.valor_total);
    
    chartProdutos = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Faturamento',
                data: valores,
                backgroundColor: '#3b82f6',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.x.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });
}

// Função para atualizar tabela de produtos
function atualizarTabelaProdutos(produtos) {
    const tbody = document.getElementById('tabelaProdutosBody');
    
    if (!produtos || produtos.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="loading-cell">
                    <i class="fas fa-inbox"></i> Nenhum produto encontrado
                </td>
            </tr>
        `;
        return;
    }
    
    const totalGeral = produtos.reduce((sum, p) => sum + (p.valor_total || 0), 0);
    
    // Calcular paginação
    const inicio = (paginaAtual - 1) * itensPorPagina;
    const fim = inicio + itensPorPagina;
    const produtosPaginados = produtos.slice(inicio, fim);
    
    let html = '';
    produtosPaginados.forEach(produto => {
        const percentual = totalGeral > 0 ? ((produto.valor_total / totalGeral) * 100).toFixed(2) : 0;
        
        html += `
            <tr>
                <td title="${produto.descricao}">${truncarTexto(produto.descricao, 60)}</td>
                <td>${produto.quantidade_vendas || 0}</td>
                <td>${formatarMoeda(produto.valor_produtos || 0)}</td>
                <td>${formatarMoeda(produto.frete_total || 0)}</td>
                <td>${formatarMoeda(produto.outras_despesas || 0)}</td>
                <td><strong>${formatarMoeda(produto.valor_total || 0)}</strong></td>
                <td>${percentual}%</td>
                <td>
                    <button class="btn-detalhes" onclick="verDetalhesProduto('${produto.descricao.replace(/'/g, "\\'")}')">
                        <i class="fas fa-eye"></i> Ver
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    // Atualizar paginação
    atualizarPaginacao(produtos.length, paginaAtual, itensPorPagina, 'paginacao', (pagina) => {
        paginaAtual = pagina;
        atualizarTabelaProdutos(produtos);
    });
}

// Função para atualizar tabela de vendas
function atualizarTabelaVendas(vendas) {
    const tbody = document.getElementById('tabelaVendasBody');
    
    if (!vendas || vendas.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="loading-cell">
                    <i class="fas fa-inbox"></i> Nenhuma venda encontrada
                </td>
            </tr>
        `;
        return;
    }
    
    // Calcular paginação
    const inicio = (paginaAtualVendas - 1) * itensPorPaginaVendas;
    const fim = inicio + itensPorPaginaVendas;
    const vendasPaginadas = vendas.slice(inicio, fim);
    
    let html = '';
    vendasPaginadas.forEach(venda => {
        // Formatar data
        const dataFormatada = venda.data ? new Date(venda.data + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
        
        html += `
            <tr>
                <td>${dataFormatada}</td>
                <td title="${venda.produto}">${truncarTexto(venda.produto, 50)}</td>
                <td>${formatarMoeda(venda.valor_produto || 0)}</td>
                <td>${formatarMoeda(venda.frete || 0)}</td>
                <td>${formatarMoeda(venda.outras_despesas || 0)}</td>
                <td><strong>${formatarMoeda(venda.valor_total || 0)}</strong></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    // Atualizar paginação
    atualizarPaginacao(vendas.length, paginaAtualVendas, itensPorPaginaVendas, 'paginacaoVendas', (pagina) => {
        paginaAtualVendas = pagina;
        atualizarTabelaVendas(vendas);
    });
}

// Função para atualizar paginação
function atualizarPaginacao(totalItens, paginaAtual, itensPorPagina, elementoId, callback) {
    const totalPaginas = Math.ceil(totalItens / itensPorPagina);
    const elemento = document.getElementById(elementoId);
    
    if (totalPaginas <= 1) {
        elemento.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Botão anterior
    html += `
        <button class="pagination-btn" ${paginaAtual === 1 ? 'disabled' : ''} 
                onclick="changePage(${paginaAtual - 1}, '${elementoId}')">
            <i class="fas fa-chevron-left"></i>
        </button>
    `;
    
    // Páginas
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaAtual - 2 && i <= paginaAtual + 2)) {
            html += `
                <button class="pagination-btn ${i === paginaAtual ? 'active' : ''}" 
                        onclick="changePage(${i}, '${elementoId}')">
                    ${i}
                </button>
            `;
        } else if (i === paginaAtual - 3 || i === paginaAtual + 3) {
            html += `<span class="pagination-info">...</span>`;
        }
    }
    
    // Botão próximo
    html += `
        <button class="pagination-btn" ${paginaAtual === totalPaginas ? 'disabled' : ''} 
                onclick="changePage(${paginaAtual + 1}, '${elementoId}')">
            <i class="fas fa-chevron-right"></i>
        </button>
    `;
    
    elemento.innerHTML = html;
    
    // Armazenar callback
    window[`callback_${elementoId}`] = callback;
}

// Função para mudar página
function changePage(pagina, elementoId) {
    const callback = window[`callback_${elementoId}`];
    if (callback) {
        callback(pagina);
    }
}

// Função para ver detalhes do produto
function verDetalhesProduto(descricao) {
    if (!dadosEcommerce) return;
    
    const vendas = dadosEcommerce.vendas_detalhadas.filter(v => v.produto === descricao);
    const produto = dadosEcommerce.produtos.find(p => p.descricao === descricao);
    
    if (!produto) return;
    
    // Atualizar informações do modal
    document.getElementById('modalProdutoNome').textContent = descricao;
    document.getElementById('modalTotalVendas').textContent = vendas.length;
    document.getElementById('modalValorTotal').textContent = formatarMoeda(produto.valor_total || 0);
    
    // Atualizar tabela de vendas
    const tbody = document.getElementById('modalVendasBody');
    let html = '';
    
    vendas.forEach(v => {
        const dataFormatada = v.data ? new Date(v.data + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
        html += `
            <tr>
                <td>${dataFormatada}</td>
                <td>${formatarMoeda(v.valor_produto || 0)}</td>
                <td>${formatarMoeda(v.frete || 0)}</td>
                <td>${formatarMoeda(v.outras_despesas || 0)}</td>
                <td><strong>${formatarMoeda(v.valor_total || 0)}</strong></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html || `
        <tr>
            <td colspan="5" class="loading-cell">
                <i class="fas fa-inbox"></i> Nenhuma venda encontrada
            </td>
        </tr>
    `;
    
    // Armazenar descrição para exportação
    window.produtoAtualDetalhes = descricao;
    
    // Abrir modal
    abrirModal();
}

// Função para abrir modal
function abrirModal() {
    const modal = document.getElementById('modalDetalhes');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Função para fechar modal
function fecharModal() {
    const modal = document.getElementById('modalDetalhes');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Função para exportar detalhes do produto
function exportarDetalhesProduto() {
    if (!dadosEcommerce || !window.produtoAtualDetalhes) {
        alert('Nenhum produto selecionado');
        return;
    }
    
    const descricao = window.produtoAtualDetalhes;
    const vendas = dadosEcommerce.vendas_detalhadas.filter(v => v.produto === descricao);
    
    // Criar CSV
    let csv = 'Produto,Data,Valor Produtos,Frete,Outras Despesas,Valor Total\n';
    
    vendas.forEach(v => {
        const valorProduto = v.valor_produto || 0;
        const frete = v.frete || 0;
        const despesas = v.outras_despesas || 0;
        const total = v.valor_total || 0;
        csv += `"${descricao}","${v.data}","${valorProduto.toFixed(2)}","${frete.toFixed(2)}","${despesas.toFixed(2)}","${total.toFixed(2)}"\n`;
    });
    
    // Baixar arquivo
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const nomeArquivo = descricao.replace(/[^a-z0-9]/gi, '_').toLowerCase();
    link.setAttribute('href', url);
    link.setAttribute('download', `produto_${nomeArquivo}_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Função para expandir gráfico
function expandirGrafico(tipo) {
    if (!dadosEcommerce) return;
    
    const modal = document.getElementById('modalChart');
    const titulo = document.getElementById('modalChartTitulo');
    const ctx = document.getElementById('chartExpandido').getContext('2d');
    
    // Destruir gráfico anterior se existir
    if (chartExpandido) {
        chartExpandido.destroy();
    }
    
    // Configurar título e criar gráfico baseado no tipo
    switch(tipo) {
        case 'faturamento':
            titulo.innerHTML = '<i class="fas fa-chart-line"></i> Faturamento por Período';
            criarGraficoFaturamentoExpandido(ctx, dadosEcommerce.vendas_por_data);
            break;
        case 'produtos':
            titulo.innerHTML = '<i class="fas fa-chart-bar"></i> Top 10 Produtos por Faturamento';
            criarGraficoProdutosExpandido(ctx, dadosEcommerce.produtos);
            break;
        case 'custos':
            titulo.innerHTML = '<i class="fas fa-chart-pie"></i> Composição de Custos';
            criarGraficoCustosExpandido(ctx, dadosEcommerce.resumo);
            break;
    }
    
    // Abrir modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Função para fechar modal do gráfico
function fecharModalGrafico() {
    const modal = document.getElementById('modalChart');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    
    // Destruir gráfico expandido
    if (chartExpandido) {
        chartExpandido.destroy();
        chartExpandido = null;
    }
}

// Criar gráfico de faturamento expandido
function criarGraficoFaturamentoExpandido(ctx, vendasPorData) {
    const labels = vendasPorData.map(v => v.data);
    const valores = vendasPorData.map(v => v.valor_total);
    
    chartExpandido = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Faturamento Total',
                data: valores,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 4,
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { size: 14, weight: 'bold' }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 12 },
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(0);
                        }
                    }
                },
                x: {
                    ticks: {
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

// Criar gráfico de produtos expandido
function criarGraficoProdutosExpandido(ctx, produtos) {
    const top10 = produtos.slice(0, 10);
    const labels = top10.map(p => truncarTexto(p.descricao, 50));
    const valores = top10.map(p => p.valor_total);
    
    chartExpandido = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Faturamento',
                data: valores,
                backgroundColor: '#3b82f6',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1.8,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.x.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 12 },
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(0);
                        }
                    }
                },
                y: {
                    ticks: {
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

// Criar gráfico de custos expandido
function criarGraficoCustosExpandido(ctx, resumo) {
    chartExpandido = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Produtos', 'Fretes', 'Outras Despesas'],
            datasets: [{
                data: [
                    resumo.valor_produtos || 0,
                    resumo.total_fretes || 0,
                    resumo.total_despesas || 0
                ],
                backgroundColor: [
                    '#059669',
                    '#f59e0b',
                    '#ef4444'
                ],
                borderWidth: 4,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1.5,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 14, weight: 'bold' },
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': R$ ' + context.parsed.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Event listeners para os modais
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalDetalhes');
    const modalChart = document.getElementById('modalChart');
    
    // Fechar modal de detalhes ao clicar fora dele
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                fecharModal();
            }
        });
    }
    
    // Fechar modal de gráfico ao clicar fora dele
    if (modalChart) {
        modalChart.addEventListener('click', function(e) {
            if (e.target === modalChart) {
                fecharModalGrafico();
            }
        });
    }
    
    // Fechar modais com tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (modal && modal.classList.contains('active')) {
                fecharModal();
            }
            if (modalChart && modalChart.classList.contains('active')) {
                fecharModalGrafico();
            }
        }
    });
});

// Função para filtrar tabela
function filtrarTabela() {
    const termo = document.getElementById('searchTable').value.toLowerCase();
    
    if (!dadosEcommerce) return;
    
    const produtosFiltrados = dadosEcommerce.produtos.filter(p => 
        p.descricao.toLowerCase().includes(termo)
    );
    
    paginaAtual = 1;
    atualizarTabelaProdutos(produtosFiltrados);
}

// Função para ordenar produtos
function ordenarProdutos() {
    const ordenacao = document.getElementById('ordenarPor').value;
    
    if (!dadosEcommerce) return;
    
    let produtos = [...dadosEcommerce.produtos];
    
    switch (ordenacao) {
        case 'valor_total_desc':
            produtos.sort((a, b) => (b.valor_total || 0) - (a.valor_total || 0));
            break;
        case 'valor_total_asc':
            produtos.sort((a, b) => (a.valor_total || 0) - (b.valor_total || 0));
            break;
        case 'quantidade_desc':
            produtos.sort((a, b) => (b.quantidade_vendas || 0) - (a.quantidade_vendas || 0));
            break;
        case 'frete_desc':
            produtos.sort((a, b) => (b.frete_total || 0) - (a.frete_total || 0));
            break;
        case 'descricao_asc':
            produtos.sort((a, b) => a.descricao.localeCompare(b.descricao));
            break;
    }
    
    dadosEcommerce.produtos = produtos;
    paginaAtual = 1;
    atualizarTabelaProdutos(produtos);
}

// Função para limpar filtros
function limparFiltros() {
    document.getElementById('dataInicial').value = '';
    document.getElementById('dataFinal').value = '';
    document.getElementById('filtroProdu').value = '';
    if (document.getElementById('limite')) {
        document.getElementById('limite').value = '50';
    }
    
    // Resetar para últimos 7 dias
    const hoje = new Date();
    const seteDiasAtras = new Date(hoje);
    seteDiasAtras.setDate(hoje.getDate() - 7);
    
    document.getElementById('dataInicial').valueAsDate = seteDiasAtras;
    document.getElementById('dataFinal').valueAsDate = hoje;
    
    carregarDados();
}

// Função para exportar dados
function exportarDados() {
    if (!dadosEcommerce) {
        alert('Nenhum dado para exportar');
        return;
    }
    
    // Criar CSV
    let csv = 'Data,Produto,Valor Produtos,Frete,Outras Despesas,Valor Total\n';
    
    dadosEcommerce.vendas_detalhadas.forEach(v => {
        const valorProduto = v.valor_produto || 0;
        const frete = v.frete || 0;
        const despesas = v.outras_despesas || 0;
        const total = v.valor_total || 0;
        csv += `"${v.data}","${v.produto}","${valorProduto.toFixed(2)}","${frete.toFixed(2)}","${despesas.toFixed(2)}","${total.toFixed(2)}"\n`;
    });
    
    // Baixar arquivo
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `ecommerce_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Função para formatar moeda
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

// Função para truncar texto
function truncarTexto(texto, maxLength) {
    if (texto.length <= maxLength) return texto;
    return texto.substr(0, maxLength) + '...';
}


