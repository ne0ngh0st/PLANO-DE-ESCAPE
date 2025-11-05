let licitacoesData = [];
let currentPage = 1;
const itemsPerPage = 20;
let gruposExpandidos = new Set();
const usarAgrupamento = true;

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Página de Licitações carregada');
    
    // Verificar se os elementos existem
    const elementos = [
        'buscaInput', 'filtroOrgao', 'filtroStatus', 'filtroTipo', 
        'limparFiltros', 'exportarBtn', 'licitacoesTableBody'
    ];
    
    elementos.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            console.log('✅ Elemento encontrado:', id);
        } else {
            console.error('❌ Elemento não encontrado:', id);
        }
    });
    
    // Carregar dados iniciais
    carregarEstatisticas();
    carregarLicitacoes();
    
    // Event listeners
    document.getElementById('buscaInput').addEventListener('input', debounce(carregarLicitacoes, 500));
    document.getElementById('filtroOrgao').addEventListener('change', carregarLicitacoes);
    document.getElementById('filtroStatus').addEventListener('change', carregarLicitacoes);
    document.getElementById('filtroTipo').addEventListener('change', carregarLicitacoes);
    document.getElementById('limparFiltros').addEventListener('click', limparFiltros);
    document.getElementById('exportarBtn').addEventListener('click', exportarDados);
    document.getElementById('expandirTodosBtn').addEventListener('click', expandirTodosGrupos);
    document.getElementById('retrairTodosBtn').addEventListener('click', retrairTodosGrupos);
});

async function carregarEstatisticas() {
    try {
        console.log('📊 Carregando estatísticas...');
        
        const formData = new FormData();
        formData.append('acao', 'obter_estatisticas');
        
        const endpoint = window.baseUrl ? window.baseUrl('includes/management/gerenciar_licitacoes.php') : 'includes/management/gerenciar_licitacoes.php';
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        console.log('📊 Resposta das estatísticas:', response.status);
        
        const result = await response.json();
        console.log('📊 Estatísticas recebidas:', result);
        
        if (result.success) {
            const stats = result.stats;
            
            document.getElementById('totalLicitacoes').textContent = stats.total || 0;
            
            // Contar contratos ativos (Vigente/Ativo/Em execução/Prorrogado) e não encerrados
            const ativas = (stats.por_status || []).reduce((sum, s) => {
                const nome = (s.STATUS || '').toLowerCase();
                const isFechado = /(encerra|finaliz|cancel|expira|rescind|conclu)/.test(nome);
                const isAtivo = /(vigent|ativo|execu|prorrog)/.test(nome);
                return sum + (isAtivo && !isFechado ? parseInt(s.count, 10) : 0);
            }, 0);
            document.getElementById('totalAtivas').textContent = ativas;
            
            // Valor total formatado (sem símbolo, pois o card já indica R$)
            const valorTotal = stats.valor_total_ata || 0;
            document.getElementById('valorTotal').textContent = formatarMoedaSemSimbolo(valorTotal);
            
            // Contar órgãos únicos
            const totalOrgaos = stats.total_orgaos || 0;
            document.getElementById('totalOrgaos').textContent = totalOrgaos;
            
            console.log('✅ Estatísticas carregadas com sucesso');
        } else {
            console.error('❌ Erro ao carregar estatísticas:', result.message);
        }
    } catch (error) {
        console.error('❌ Erro ao carregar estatísticas:', error);
    }
}

async function carregarLicitacoes() {
    try {
        console.log('🔍 Iniciando carregamento de licitações...');
        
        const formData = new FormData();
        formData.append('acao', 'listar_licitacoes');
        formData.append('filtro_orgao', document.getElementById('filtroOrgao').value);
        formData.append('filtro_status', document.getElementById('filtroStatus').value);
        formData.append('filtro_tipo', document.getElementById('filtroTipo').value);
        formData.append('busca', document.getElementById('buscaInput').value);
        
        const endpoint = window.baseUrl ? window.baseUrl('includes/management/gerenciar_licitacoes.php') : 'includes/management/gerenciar_licitacoes.php';
        console.log('📤 Enviando requisição para:', endpoint);
        
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        console.log('📥 Resposta recebida:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('📊 Dados recebidos:', result);
        
        if (result.success) {
            licitacoesData = result.data;
            console.log('✅ Licitações carregadas:', licitacoesData.length, 'registros');
            carregarFiltros();
            exibirLicitacoes();
        } else {
            console.error('❌ Erro ao carregar licitações:', result.message);
            document.getElementById('licitacoesTableBody').innerHTML = 
                '<tr><td colspan="13" class="loading-row">Erro: ' + result.message + '</td></tr>';
        }
    } catch (error) {
        console.error('❌ Erro ao carregar licitações:', error);
        document.getElementById('licitacoesTableBody').innerHTML = 
            '<tr><td colspan="13" class="loading-row">Erro de conexão: ' + error.message + '</td></tr>';
    }
}

async function carregarFiltros() {
    try {
        // Carregar órgãos únicos
        const orgaos = [...new Set(licitacoesData.map(l => l.ORGAO).filter(Boolean))];
        const selectOrgao = document.getElementById('filtroOrgao');
        
        // Limpar opções existentes (exceto a primeira)
        while (selectOrgao.children.length > 1) {
            selectOrgao.removeChild(selectOrgao.lastChild);
        }
        
        orgaos.forEach(orgao => {
            const option = document.createElement('option');
            option.value = orgao;
            option.textContent = orgao;
            selectOrgao.appendChild(option);
        });
        
        // Carregar status únicos
        const status = [...new Set(licitacoesData.map(l => l.STATUS).filter(Boolean))];
        const selectStatus = document.getElementById('filtroStatus');
        
        while (selectStatus.children.length > 1) {
            selectStatus.removeChild(selectStatus.lastChild);
        }
        
        status.forEach(statusItem => {
            const option = document.createElement('option');
            option.value = statusItem;
            option.textContent = statusItem;
            selectStatus.appendChild(option);
        });
        
        // Carregar tipos únicos
        const tipos = [...new Set(licitacoesData.map(l => l.TIPO).filter(Boolean))];
        const selectTipo = document.getElementById('filtroTipo');
        
        while (selectTipo.children.length > 1) {
            selectTipo.removeChild(selectTipo.lastChild);
        }
        
        tipos.forEach(tipo => {
            const option = document.createElement('option');
            option.value = tipo;
            option.textContent = tipo;
            selectTipo.appendChild(option);
        });
    } catch (error) {
        console.error('Erro ao carregar filtros:', error);
    }
}

function exibirLicitacoes() {
    const tbody = document.getElementById('licitacoesTableBody');
    
    if (licitacoesData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="13" class="loading-row">Nenhuma licitação encontrada</td></tr>';
        atualizarPaginacao();
        return;
    }
    
    // Modo sem agrupamento (visual igual ao de leads)
    if (!usarAgrupamento) {
        const inicio = (currentPage - 1) * itemsPerPage;
        const fim = inicio + itemsPerPage;
        const pagina = licitacoesData.slice(inicio, fim);
        
        let html = '';
        pagina.forEach(licitacao => {
            html += `
                <tr>
                    <td></td>
                    <td>${licitacao.ORGAO || '-'}</td>
                    <td>${licitacao.SIGLA || '-'}</td>
                    <td>${licitacao.CNPJ || '-'}</td>
                    <td class="gerenciador-info">${licitacao.GERENCIADOR || '-'}</td>
                    <td>${licitacao.TIPO || '-'}</td>
                    <td>${licitacao.PRODUTO || '-'}</td>
                    <td>
                        <span class="status-badge ${getStatusClass(licitacao.STATUS)}">
                            ${licitacao.STATUS || 'N/A'}
                        </span>
                    </td>
                    <td class="valor-contratado">${formatarMoeda(licitacao.VALOR_ATA) || '-'}</td>
                    <td class="valor-consumido">${formatarMoeda(licitacao.VALOR_CONSUMIDO) || '-'}</td>
                    <td>
                        <span class="percentual-consumido ${getPercentualClass(licitacao.CONSUMO_ATA_PERCENT)}">
                            ${extrairPercentual(licitacao.CONSUMO_ATA_PERCENT) || '-'}
                        </span>
                    </td>
                    <td>${licitacao.DIAS_RESTANTES_ATA || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="verDetalhes('${licitacao.ORGAO}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        atualizarPaginacao();
        return;
    }
    
    // Modo agrupado (opcional)
    const grupos = agruparPorGerenciador(licitacoesData);
    let html = '';
    const gruposOrdenados = Object.keys(grupos).sort();
    
    gruposOrdenados.forEach(gerenciador => {
        const licitacoesGrupo = grupos[gerenciador];
        const isExpanded = gruposExpandidos.has(gerenciador);
        const totais = calcularTotaisGrupo(licitacoesGrupo);
        
        html += `
            <tr class="group-header" onclick="toggleGrupo('${gerenciador}')">
                <td>
                    <div class="group-toggle ${isExpanded ? 'expanded' : ''}">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </td>
                <td colspan="6">
                    <div style="display: flex; align-items: center;">
                        <span class="group-title">${gerenciador}</span>
                        <span class="group-stats">
                            ${licitacoesGrupo.length} licitação(ões) | 
                            Total: ${formatarMoeda(totais.valorContratado)} | 
                            Consumido: ${formatarMoeda(totais.valorConsumido)} | 
                            ${formatarPercentualNumero(totais.percentualMedio)}% médio
                        </span>
                    </div>
                </td>
                <td></td>
                <td class="total-value">${formatarMoeda(totais.valorContratado)}</td>
                <td class="total-value">${formatarMoeda(totais.valorConsumido)}</td>
                <td class="total-value">${formatarPercentualNumero(totais.percentualMedio)}%</td>
                <td></td>
                <td></td>
            </tr>
            <tr class="group-subheader ${isExpanded ? '' : 'hidden'}" data-grupo="${gerenciador}">
                <td></td>
                <td>Órgão</td>
                <td>Sigla</td>
                <td>CNPJ</td>
                <td>Gerenciador</td>
                <td>Tipo</td>
                <td>Produto</td>
                <td>Status</td>
                <td>Valor Contratado</td>
                <td>Valor Consumido</td>
                <td>% Consumido</td>
                <td>Dias Restantes</td>
                <td>Ações</td>
            </tr>
        `;
        
        licitacoesGrupo.forEach(licitacao => {
            html += `
                <tr class="group-item ${isExpanded ? '' : 'hidden'}" data-grupo="${gerenciador}">
                    <td></td>
                    <td>${licitacao.ORGAO || '-'}</td>
                    <td>${licitacao.SIGLA || '-'}</td>
                    <td>${licitacao.CNPJ || '-'}</td>
                    <td class="gerenciador-info">${licitacao.GERENCIADOR || '-'}</td>
                    <td>${licitacao.TIPO || '-'}</td>
                    <td>${licitacao.PRODUTO || '-'}</td>
                    <td>
                        <span class="status-badge ${getStatusClass(licitacao.STATUS)}">
                            ${licitacao.STATUS || 'N/A'}
                        </span>
                    </td>
                    <td class="valor-contratado">${formatarMoeda(licitacao.VALOR_ATA) || '-'}</td>
                    <td class="valor-consumido">${formatarMoeda(licitacao.VALOR_CONSUMIDO) || '-'}</td>
                    <td>
                        <span class="percentual-consumido ${getPercentualClass(licitacao.CONSUMO_ATA_PERCENT)}">
                            ${extrairPercentual(licitacao.CONSUMO_ATA_PERCENT) || '-'}
                        </span>
                    </td>
                    <td>${licitacao.DIAS_RESTANTES_ATA || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="verDetalhes('${licitacao.ORGAO}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        // Linha de totais do grupo alinhada às colunas
        html += `
            <tr class="group-totals ${isExpanded ? '' : 'hidden'}" data-grupo="${gerenciador}">
                <td></td>
                <td colspan="6" class="total-label">TOTAIS DO GRUPO:</td>
                <td></td>
                <td class="total-value">${formatarMoeda(totais.valorContratado)}</td>
                <td class="total-value">${formatarMoeda(totais.valorConsumido)}</td>
                <td class="total-value">${formatarPercentualNumero(totais.percentualMedio)}%</td>
                <td></td>
                <td></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    atualizarPaginacao();
}

function getStatusClass(status) {
    if (!status) return '';
    const s = status.toLowerCase();
    // Encerrado / Finalizado / Cancelado / Expirado / Concluído => Vermelho
    if (/(encerra|finaliz|cancel|expira|rescind|conclu)/.test(s)) {
        return 'status-encerrada';
    }
    // Vigente / Ativo / Em execução / Prorrogado => Verde
    if (/(vigent|ativo|execu|prorrog)/.test(s)) {
        return 'status-ativa';
    }
    // Em andamento / Processando / Tramitando => Amarelo
    if (/(andamento|process|tramit|anal)/.test(s)) {
        return 'status-andamento';
    }
    // Padrão: Amarelo (estado intermediário)
    return 'status-andamento';
}

function extrairPercentual(consumoPercent) {
    if (!consumoPercent) return null;
    
    // Extrair número do texto como "29,67% da ata consumida"
    const match = consumoPercent.match(/(\d+[,.]?\d*)%/);
    if (match) {
        const raw = match[1];
        const display = raw.includes(',') ? raw : raw.replace('.', ',');
        return display + '%';
    }
    return null;
}

function formatarPercentualNumero(valor) {
    if (valor === null || valor === undefined || valor === '') return '0,0';
    const numero = typeof valor === 'number' ? valor : normalizarNumeroGenerico(valor);
    if (isNaN(numero)) return '0,0';
    return numero.toFixed(1).replace('.', ',');
}

function getPercentualClass(consumoPercent) {
    const percentual = extrairPercentual(consumoPercent);
    if (!percentual) return '';
    
    const valor = normalizarNumeroGenerico(percentual.replace('%', ''));
    
    if (valor < 30) {
        return 'percentual-baixo';
    } else if (valor < 70) {
        return 'percentual-medio';
    } else {
        return 'percentual-alto';
    }
}

function normalizarNumeroGenerico(valor) {
    if (valor === null || valor === undefined || valor === '') return NaN;
    if (typeof valor === 'number') return valor;
    const str = valor.toString().trim();
    // Remover símbolo de moeda e espaços comuns
    const clean = str.replace(/[R$\s]/g, '');
    // Se contém vírgula e ponto, assumir ponto como milhar e vírgula como decimal (pt-BR)
    if (clean.includes(',') && clean.includes('.')) {
        return parseFloat(clean.replace(/\./g, '').replace(',', '.'));
    }
    // Se contém apenas vírgula, tratar como decimal
    if (clean.includes(',')) {
        return parseFloat(clean.replace(',', '.'));
    }
    // Caso contrário, tentar parse direto (ponto como decimal ou inteiro)
    return parseFloat(clean);
}

function formatarMoeda(valor) {
    if (valor === null || valor === undefined || valor === '') return null;
    const numero = typeof valor === 'number' ? valor : normalizarNumeroGenerico(valor);
    if (isNaN(numero)) return null;
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(numero);
}

function formatarMoedaSemSimbolo(valor) {
    if (valor === null || valor === undefined || valor === '') return '0,00';
    const numero = typeof valor === 'number' ? valor : normalizarNumeroGenerico(valor);
    if (isNaN(numero)) return '0,00';
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(numero);
}

function atualizarPaginacao() {
    const container = document.getElementById('paginationContainer');
    
    if (usarAgrupamento) {
        const grupos = agruparPorGerenciador(licitacoesData);
        const totalGrupos = Object.keys(grupos).length;
        const totalLicitacoes = licitacoesData.length;
        container.innerHTML = `
            <div style="text-align: center; color: #666; font-size: 0.9rem;">
                <i class="fas fa-layer-group"></i>
                ${totalGrupos} gerenciador(es) | ${totalLicitacoes} licitação(ões) total
            </div>
        `;
        return;
    }
    
    const totalItems = licitacoesData.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
    
    let html = '<ul class="pagination-controles">';
    html += `<li><button class="btn btn-secondary" data-page="${currentPage - 1}">&laquo;</button></li>`;
    for (let p = 1; p <= totalPages; p++) {
        html += `<li><button class="btn ${p === currentPage ? 'btn-primary' : 'btn-secondary'}" data-page="${p}">${p}</button></li>`;
    }
    html += `<li><button class="btn btn-secondary" data-page="${currentPage + 1}">&raquo;</button></li>`;
    html += '</ul>';
    
    container.innerHTML = html;
    
    container.querySelectorAll('button[data-page]').forEach(btn => {
        const page = parseInt(btn.getAttribute('data-page'), 10);
        btn.disabled = isNaN(page) || page < 1 || page > totalPages;
        btn.addEventListener('click', () => {
            if (!btn.disabled) mudarPagina(page);
        });
    });
}

function mudarPagina(pagina) {
    currentPage = pagina;
    exibirLicitacoes();
}

function limparFiltros() {
    document.getElementById('buscaInput').value = '';
    document.getElementById('filtroOrgao').value = '';
    document.getElementById('filtroStatus').value = '';
    document.getElementById('filtroTipo').value = '';
    currentPage = 1;
    carregarLicitacoes();
}

function verDetalhes(orgao) {
    alert(`Detalhes de ${orgao} - Funcionalidade em desenvolvimento`);
}

function exportarDados() {
    if (licitacoesData.length === 0) {
        alert('Nenhum dado para exportar');
        return;
    }
    
    // Criar CSV
    const headers = ['Órgão', 'Sigla', 'CNPJ', 'Gerenciador', 'Tipo', 'Produto', 'Status', 'Valor Contratado', 'Valor Consumido', '% Consumido', 'Dias Restantes'];
    const csvContent = [
        headers.join(','),
        ...licitacoesData.map(l => [
            `"${l.ORGAO || ''}"`,
            `"${l.SIGLA || ''}"`,
            `"${l.CNPJ || ''}"`,
            `"${l.GERENCIADOR || ''}"`,
            `"${l.TIPO || ''}"`,
            `"${l.PRODUTO || ''}"`,
            `"${l.STATUS || ''}"`,
            `"${l.VALOR_ATA || ''}"`,
            `"${l.VALOR_CONSUMIDO || ''}"`,
            `"${extrairPercentual(l.CONSUMO_ATA_PERCENT) || ''}"`,
            `"${l.DIAS_RESTANTES_ATA || ''}"`
        ].join(','))
    ].join('\n');
    
    // Download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `licitacoes_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
}

function agruparPorGerenciador(licitacoes) {
    const grupos = {};
    
    licitacoes.forEach(licitacao => {
        const rawGer = licitacao.GERENCIADOR;
        const gerenciadorTrim = rawGer && rawGer.toString ? rawGer.toString().trim() : '';
        const gerenciador = gerenciadorTrim || 'Sem Gerenciador';
        
        if (!grupos[gerenciador]) {
            grupos[gerenciador] = [];
        }
        
        grupos[gerenciador].push(licitacao);
    });
    
    return grupos;
}

function calcularTotaisGrupo(licitacoesGrupo) {
    let valorContratado = 0;
    let valorConsumido = 0;
    let percentuais = [];
    
    licitacoesGrupo.forEach(licitacao => {
        // Valor contratado
        const valorAta = normalizarNumeroGenerico(licitacao.VALOR_ATA ?? 0);
        if (!isNaN(valorAta)) {
            valorContratado += valorAta;
        }
        
        // Valor consumido
        const valorConsumidoItem = normalizarNumeroGenerico(licitacao.VALOR_CONSUMIDO ?? 0);
        if (!isNaN(valorConsumidoItem)) {
            valorConsumido += valorConsumidoItem;
        }
        
        // Percentual
        const percentual = extrairPercentual(licitacao.CONSUMO_ATA_PERCENT);
        if (percentual) {
            const valorPercentual = normalizarNumeroGenerico(percentual.replace('%', ''));
            if (!isNaN(valorPercentual)) {
                percentuais.push(valorPercentual);
            }
        }
    });
    
    const percentualMedio = percentuais.length > 0 
        ? (percentuais.reduce((sum, p) => sum + p, 0) / percentuais.length).toFixed(1)
        : '0.0';
    
    return {
        valorContratado,
        valorConsumido,
        percentualMedio
    };
}

function toggleGrupo(gerenciador) {
    if (gruposExpandidos.has(gerenciador)) {
        gruposExpandidos.delete(gerenciador);
    } else {
        gruposExpandidos.add(gerenciador);
    }
    
    // Atualizar visual
    const header = document.querySelector(`tr.group-header[onclick*="${gerenciador}"]`);
    const toggle = header.querySelector('.group-toggle');
    const items = document.querySelectorAll(`tr[data-grupo="${gerenciador}"]`);
    
    if (gruposExpandidos.has(gerenciador)) {
        toggle.classList.add('expanded');
        header.classList.add('expanded');
        items.forEach(item => item.classList.remove('hidden'));
    } else {
        toggle.classList.remove('expanded');
        header.classList.remove('expanded');
        items.forEach(item => item.classList.add('hidden'));
    }
}

function expandirTodosGrupos() {
    const grupos = agruparPorGerenciador(licitacoesData);
    Object.keys(grupos).forEach(gerenciador => {
        gruposExpandidos.add(gerenciador);
    });
    exibirLicitacoes();
}

function retrairTodosGrupos() {
    gruposExpandidos.clear();
    exibirLicitacoes();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
