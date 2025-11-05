// ===== PEDIDOS EM ABERTO - JAVASCRIPT =====

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Página de Pedidos em Aberto carregada');
    
    // Elementos principais
    const filtrosForm = document.querySelector('.filtros-form');
    const pedidosTableBody = document.getElementById('pedidosTableBody');
    const statsContainer = document.getElementById('statsContainer');
    const paginationContainer = document.getElementById('paginationContainer');
    const btnRefresh = document.getElementById('btnRefresh');
    const btnExport = document.getElementById('btnExport');
    
    // Modal
    const pedidoModal = document.getElementById('pedidoModal');
    const modalClose = document.getElementById('modalClose');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    // Estado da aplicação
    let currentPage = 1;
    let totalPages = 1;
    let totalRecords = 0;
    let isLoading = false;
    
    // Inicialização
    init();
    
    function init() {
        console.log('🔧 Inicializando aplicação...');
        
        // Event listeners
        setupEventListeners();
        
        // Carregar dados iniciais
        loadPedidos();
        
        console.log('✅ Aplicação inicializada');
    }
    
    function setupEventListeners() {
        console.log('🎯 Configurando event listeners...');
        
        // Filtros
        if (filtrosForm) {
            // Auto-submit em mudanças de filtros
            const filterInputs = filtrosForm.querySelectorAll('select, input[type="text"]');
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    console.log('🔄 Filtro alterado:', this.name, this.value);
                    currentPage = 1;
                    loadPedidos();
                });
                
                // Para inputs de texto, usar debounce
                if (input.type === 'text') {
                    let timeout;
                    input.addEventListener('input', function() {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => {
                            currentPage = 1;
                            loadPedidos();
                        }, 500);
                    });
                }
            });
        }
        
        // Botões
        if (btnRefresh) {
            btnRefresh.addEventListener('click', function() {
                loadPedidos();
            });
        }
        
        
        if (btnExport) {
            btnExport.addEventListener('click', function() {
                exportData();
            });
        }
        
        // Modal
        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }
        
        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', closeModal);
        }
        
        if (pedidoModal) {
            pedidoModal.addEventListener('click', function(e) {
                if (e.target === pedidoModal || e.target.classList.contains('modal-overlay')) {
                    closeModal();
                }
            });
        }
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !pedidoModal.classList.contains('hidden')) {
                closeModal();
            }
        });
        
        console.log('✅ Event listeners configurados');
    }
    
    async function loadPedidos() {
        if (isLoading) return;
        
        console.log('📊 Carregando pedidos...');
        isLoading = true;
        
        try {
            // Mostrar loading
            showLoading();
            
            // Preparar parâmetros
            if (!filtrosForm) {
                console.error('❌ Formulário de filtros não encontrado!');
                throw new Error('Formulário de filtros não encontrado');
            }
            
            const formData = new FormData(filtrosForm);
            formData.append('page', currentPage);
            formData.append('action', 'load_pedidos');
            
            // Debug: verificar filtros
            console.log('🔍 Filtros sendo enviados:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            // Fazer requisição - usar caminho dinâmico baseado no ambiente
            const ajaxUrl = (window.baseUrl || ((path) => {
                const base = window.BASE_PATH || '/Site';
                return base + (path.startsWith('/') ? path : '/' + path);
            }))('includes/ajax/pedidos_abertos_ajax.php');
            
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Erro ao fazer parse do JSON:', parseError);
                console.error('📄 Texto da resposta:', responseText);
                throw new Error('Resposta inválida do servidor');
            }
            
            if (data.success) {
                console.log('✅ Dados carregados com sucesso:', data);
                
                // Atualizar estado da aplicação
                if (data.pagination) {
                    currentPage = data.pagination.current_page || 1;
                    totalPages = data.pagination.total_pages || 1;
                    totalRecords = data.pagination.total_records || 0;
                } else {
                    // Fallback caso não venha paginação
                    totalRecords = data.pedidos ? data.pedidos.length : 0;
                }
                
                // Atualizar estatísticas
                updateStats(data.stats);
                
                // Atualizar tabela
                updateTable(data.pedidos);
                
                // Atualizar paginação
                updatePagination(data.pagination);
                
            } else {
                throw new Error(data.message || 'Erro ao carregar dados');
            }
            
        } catch (error) {
            console.error('❌ Erro ao carregar pedidos:', error);
            showError('Erro ao carregar pedidos: ' + error.message);
        } finally {
            isLoading = false;
            hideLoading();
        }
    }
    
    function showLoading() {
        if (pedidosTableBody) {
            pedidosTableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="loading-row">
                        <i class="fas fa-spinner fa-spin"></i>
                        Carregando pedidos...
                    </td>
                </tr>
            `;
        }
        
        if (btnRefresh) {
            btnRefresh.disabled = true;
            btnRefresh.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
        }
    }
    
    function hideLoading() {
        if (btnRefresh) {
            btnRefresh.disabled = false;
            btnRefresh.innerHTML = '<i class="fas fa-sync-alt"></i> Atualizar';
        }
    }
    
    function updateStats(stats) {
        console.log('📈 Atualizando estatísticas:', stats);
        
        const elements = {
            totalPedidos: document.getElementById('totalPedidos'),
            pedidosAtrasados: document.getElementById('pedidosAtrasados'),
            atrasoFaturamento: document.getElementById('atrasoFaturamento'),
            atrasoEntrega: document.getElementById('atrasoEntrega'),
            valorTotal: document.getElementById('valorTotal'),
            totalVendedores: document.getElementById('totalVendedores')
        };
        
        if (elements.totalPedidos) {
            elements.totalPedidos.textContent = formatNumber(stats.total_pedidos || 0);
        }
        
        if (elements.pedidosAtrasados) {
            elements.pedidosAtrasados.textContent = formatNumber(stats.pedidos_atrasados || 0);
        }
        
        if (elements.atrasoFaturamento) {
            elements.atrasoFaturamento.textContent = formatNumber(stats.atraso_faturamento || 0);
        }
        
        if (elements.atrasoEntrega) {
            elements.atrasoEntrega.textContent = formatNumber(stats.atraso_entrega || 0);
        }
        
        if (elements.valorTotal) {
            elements.valorTotal.textContent = formatCurrency(stats.valor_total || 0);
        }
        
        if (elements.totalVendedores) {
            elements.totalVendedores.textContent = formatNumber(stats.total_vendedores || 0);
        }
    }
    
    function updateTable(pedidos) {
        console.log('📋 Atualizando tabela com', pedidos.length, 'pedidos');
        
        if (!pedidosTableBody) return;
        
        if (pedidos.length === 0) {
            pedidosTableBody.innerHTML = `
                <tr>
                    <td colspan="12" class="loading-row">
                        <i class="fas fa-inbox"></i>
                        Nenhum pedido encontrado
                    </td>
                </tr>
            `;
            return;
        }
        
        let html = '';
        
        pedidos.forEach(pedido => {
            // Verificar se o pedido existe e tem as propriedades necessárias
            if (!pedido) return;
            
            const statusClass = getStatusClass(pedido.historico || '');
            const atrasoClass = getAtrasoClass(pedido.atraso);
            const atrasoText = getAtrasoText(pedido.atraso);
            
            html += `
                <tr class="fade-in">
                    <td class="pedido-cell">
                        <div class="pedido-info">
                            <strong>${pedido.numero_pedido || '-'}</strong>
                            <span class="filial-badge">${pedido.filial || '-'}</span>
                        </div>
                    </td>
                    <td class="cliente-cell">
                        <div class="cliente-info">
                            <strong>${pedido.cliente || '-'}</strong>
                            <div class="cliente-details">
                                <small class="text-muted">${pedido.cnpj || '-'}</small>
                                <small class="text-muted">${pedido.endereco || '-'}, ${pedido.estado || '-'}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="vendedor-info">
                            <strong>${pedido.nome_vendedor || '-'}</strong>
                            <br>
                            <small class="text-muted">${pedido.cod_vendedor || '-'}</small>
                        </div>
                    </td>
                    <td>
                        <div class="produto-info">
                            <strong>${pedido.cod_produto || '-'}</strong>
                            <br>
                            <small class="text-muted">${pedido.descricao_produto || '-'}</small>
                        </div>
                    </td>
                    <td class="text-center">
                        ${formatNumber(pedido.quantidade_venda)}
                        ${pedido.quantidade_liberada != pedido.quantidade_venda ? 
                            `<br><small class="text-warning">Lib: ${formatNumber(pedido.quantidade_liberada)}</small>` : 
                            ''
                        }
                    </td>
                    <td class="text-right">
                        <strong>${formatCurrency(pedido.vlr_total)}</strong>
                    </td>
                    <td>
                        ${formatDate(pedido.data_pedido)}
                    </td>
                    <td class="previsao-cell">
                        <div class="previsao-info">
                            <div class="previsao-faturamento">
                                <strong>${formatDate(pedido.data_previsao_faturamento) || 'N/A'}</strong>
                            </div>
                            ${pedido.data_entrega ? 
                                `<div class="previsao-entrega-badge">
                                    <small>Entrega: ${formatDate(pedido.data_entrega)}</small>
                                </div>` : 
                                ''
                            }
                        </div>
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            ${getStatusText(pedido.historico || '')}
                        </span>
                    </td>
                    <td class="atraso-cell">
                        <div class="status-entrega-container">
                            ${getFaturamentoBadge(pedido)}
                            ${getEntregaBadge(pedido)}
                        </div>
                    </td>
                    <td>
                        <div class="historico-info">
                            <small class="text-muted">
                                ${formatDate(pedido.data_historico)} ${pedido.hora_historico || ''}
                            </small>
                            <br>
                            <small>${pedido.historico || '-'}</small>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        pedidosTableBody.innerHTML = html;
        
        // Aplicar cores dinâmicas aos status badges
        setTimeout(() => {
            applyDynamicStatusColors();
        }, 100);
        
        // Criar cards mobile se a função estiver disponível
        if (window.criarCardsMobile && typeof window.criarCardsMobile === 'function') {
            setTimeout(() => {
                window.criarCardsMobile(pedidos);
            }, 200);
        }
    }
    
    function updatePagination(pagination) {
        console.log('📄 Atualizando paginação:', pagination);
        
        if (!paginationContainer) return;
        
        // Garantir que totalRecords esteja definido
        if (pagination && pagination.total_records !== undefined) {
            totalRecords = pagination.total_records;
        }
        
        totalPages = pagination ? (pagination.total_pages || 1) : 1;
        
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        let html = '<div class="paginacao-botoes">';
        
        // Botão anterior
        html += `
            <button class="btn" ${currentPage <= 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
                <i class="fas fa-chevron-left"></i>
            </button>
        `;
        
        // Páginas
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            html += `<button class="btn" onclick="changePage(1)">1</button>`;
            if (startPage > 2) {
                html += `<span>...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">
                    ${i}
                </button>
            `;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<span>...</span>`;
            }
            html += `<button class="btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
        }
        
        // Botão próximo
        html += `
            <button class="btn" ${currentPage >= totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
        
        html += '</div>';
        
        // Info da paginação
        const records = totalRecords || 0;
        const start = (currentPage - 1) * 25 + 1;
        const end = Math.min(currentPage * 25, records);
        
        html = `
            <div class="paginacao-info">
                Mostrando ${start} a ${end} de ${records} registros
            </div>
            ${html}
        `;
        
        paginationContainer.innerHTML = html;
    }
    
    function clearFilters() {
        console.log('🧹 Limpando filtros...');
        
        if (filtrosForm) {
            filtrosForm.reset();
            currentPage = 1;
            loadPedidos();
        }
    }
    
    async function exportData() {
        console.log('📤 Exportando dados...');
        
        try {
            const formData = new FormData(filtrosForm);
            formData.append('action', 'export');
            
            // Usar caminho dinâmico baseado no ambiente
            const ajaxUrl = (window.baseUrl || ((path) => {
                const base = window.BASE_PATH || '/Site';
                return base + (path.startsWith('/') ? path : '/' + path);
            }))('includes/ajax/pedidos_abertos_ajax.php');
            
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `pedidos_abertos_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            console.log('✅ Exportação concluída');
            
        } catch (error) {
            console.error('❌ Erro ao exportar:', error);
            showError('Erro ao exportar dados: ' + error.message);
        }
    }
    
    function showPedidoDetails(numeroPedido) {
        console.log('👁️ Mostrando detalhes do pedido:', numeroPedido);
        
        // Implementar modal de detalhes
        modalTitle.textContent = `Detalhes do Pedido ${numeroPedido}`;
        modalBody.innerHTML = `
            <div class="loading-row">
                <i class="fas fa-spinner fa-spin"></i>
                Carregando detalhes...
            </div>
        `;
        
        pedidoModal.classList.remove('hidden');
        
        // TODO: Implementar carregamento de detalhes via AJAX
        setTimeout(() => {
            modalBody.innerHTML = `
                <div class="pedido-details">
                    <p>Detalhes do pedido ${numeroPedido} serão implementados em breve.</p>
                </div>
            `;
        }, 1000);
    }
    
    function closeModal() {
        pedidoModal.classList.add('hidden');
    }
    
    
    function showError(message) {
        console.error('🚨 Erro:', message);
        
        if (pedidosTableBody) {
            pedidosTableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="loading-row" style="color: var(--danger-color);">
                        <i class="fas fa-exclamation-triangle"></i>
                        ${message}
                    </td>
                </tr>
            `;
        }
    }
    
    // Funções utilitárias
    function getStatusClass(historico) {
        if (!historico || typeof historico !== 'string') return 'status-generico';
        
        // Criar uma classe baseada no histórico limpo
        const h = historico.toLowerCase()
            .replace(/[^a-z0-9\s]/g, '') // Remove caracteres especiais
            .replace(/\s+/g, '-') // Substitui espaços por hífens
            .substring(0, 30); // Limita o tamanho
        
        return `status-${h}`;
    }
    
    function getStatusText(historico) {
        if (!historico || typeof historico !== 'string') return 'Em Andamento';
        
        let texto = historico.trim();
        
        // Remover prefixos como "PEDIDO 811303 COM..." e extrair apenas o motivo
        const prefixosRemover = [
            /^PEDIDO\s+\d+\s+COM\s+/i,
            /^PEDIDO\s+\d+\s+/i,
            /^COM\s+/i,
            /^PEDIDO\s+/i
        ];
        
        for (const prefixo of prefixosRemover) {
            texto = texto.replace(prefixo, '');
        }
        
        // Se ficou vazio após remover prefixos, usar o texto original
        if (!texto.trim()) {
            texto = historico.trim();
        }
        
        return texto.trim();
    }
    
    // Função para gerar cores automáticas baseadas no texto
    function generateStatusColors(text) {
        if (!text) return { bg: '#f5f5f5', color: '#616161', border: '#e0e0e0' };
        
        // Gerar hash do texto para consistência
        let hash = 0;
        for (let i = 0; i < text.length; i++) {
            hash = text.charCodeAt(i) + ((hash << 5) - hash);
        }
        
        // Paleta de cores predefinidas
        const colors = [
            { bg: '#e3f2fd', color: '#1976d2', border: '#90caf9' }, // Azul
            { bg: '#fff3e0', color: '#f57c00', border: '#ffcc02' }, // Laranja
            { bg: '#e8f5e8', color: '#2e7d32', border: '#c8e6c9' }, // Verde
            { bg: '#f3e5f5', color: '#7b1fa2', border: '#ce93d8' }, // Roxo
            { bg: '#ffebee', color: '#d32f2f', border: '#ffcdd2' }, // Vermelho
            { bg: '#e0f2f1', color: '#00695c', border: '#80cbc4' }, // Verde água
            { bg: '#fce4ec', color: '#c2185b', border: '#f8bbd9' }, // Rosa
            { bg: '#f1f8e9', color: '#558b2f', border: '#c5e1a5' }, // Verde claro
            { bg: '#fff8e1', color: '#f9a825', border: '#ffecb3' }, // Amarelo
            { bg: '#e8eaf6', color: '#3f51b5', border: '#9fa8da' }, // Índigo
            { bg: '#f3e5f5', color: '#9c27b0', border: '#ce93d8' }, // Roxo claro
            { bg: '#e0f7fa', color: '#00acc1', border: '#80deea' }, // Ciano
        ];
        
        // Selecionar cor baseada no hash
        const colorIndex = Math.abs(hash) % colors.length;
        return colors[colorIndex];
    }
    
    // Função para aplicar cores dinâmicas aos status badges
    function applyDynamicStatusColors() {
        const statusBadges = document.querySelectorAll('.status-badge[class*="status-"]');
        
        statusBadges.forEach(badge => {
            const statusText = badge.textContent.trim();
            const colors = generateStatusColors(statusText);
            
            // Aplicar cores via CSS custom properties
            badge.style.setProperty('--status-bg', colors.bg);
            badge.style.setProperty('--status-color', colors.color);
            badge.style.setProperty('--status-border', colors.border);
        });
    }
    
    function getAtrasoClass(atraso) {
        if (atraso === null || atraso === undefined || atraso === '') return 'neutro';
        const atrasoNum = parseInt(atraso);
        if (isNaN(atrasoNum)) return 'neutro';
        if (atrasoNum > 0) return 'atrasado'; // Atraso real (passou da data prevista)
        if (atrasoNum < 0) return 'a-faturar'; // A faturar (ainda no prazo)
        return 'neutro'; // Exatamente no prazo
    }
    
    function getAtrasoText(atraso) {
        if (atraso === null || atraso === undefined || atraso === '') return 'No prazo';
        const atrasoNum = parseInt(atraso);
        if (isNaN(atrasoNum)) return 'No prazo';
        if (atrasoNum > 0) return `${atrasoNum} dias atrasado`;
        if (atrasoNum < 0) return `A faturar (${Math.abs(atrasoNum)} dias)`;
        return 'No prazo';
    }
    
    function getAtrasoIcon(atraso) {
        if (atraso === null || atraso === undefined || atraso === '') return 'fa-clock';
        const atrasoNum = parseInt(atraso);
        if (isNaN(atrasoNum)) return 'fa-clock';
        if (atrasoNum > 0) return 'fa-exclamation-triangle'; // Atrasado
        if (atrasoNum < 0) return 'fa-file-invoice-dollar'; // A faturar
        return 'fa-check-circle'; // No prazo
    }
    
    function getFaturamentoBadge(pedido) {
        if (!pedido) return '';
        
        const dataPrevisaoFaturamento = pedido.data_previsao_faturamento;
        if (!dataPrevisaoFaturamento) {
            return '<span class="status-badge faturamento sem-data">Sem previsão</span>';
        }
        
        const hoje = new Date();
        const previsao = new Date(dataPrevisaoFaturamento.split('/').reverse().join('-'));
        const diffDias = Math.ceil((previsao - hoje) / (1000 * 60 * 60 * 24));
        
        if (diffDias < 0) {
            // Faturamento atrasado
            return `<span class="status-badge faturamento atrasado">
                <i class="fas fa-exclamation-triangle"></i>
                Fat. atrasado (${Math.abs(diffDias)}d)
            </span>`;
        } else if (diffDias <= 3) {
            // Faturamento próximo do prazo
            return `<span class="status-badge faturamento proximo">
                <i class="fas fa-clock"></i>
                Fat. em ${diffDias}d
            </span>`;
        } else {
            // Faturamento no prazo
            return `<span class="status-badge faturamento no-prazo">
                <i class="fas fa-check-circle"></i>
                Fat. OK
            </span>`;
        }
    }
    
    function getEntregaBadge(pedido) {
        if (!pedido) return '';
        
        const dataEntrega = pedido.data_entrega;
        if (!dataEntrega) {
            return '<span class="status-badge entrega sem-data">Sem entrega</span>';
        }
        
        const hoje = new Date();
        const entrega = new Date(dataEntrega.split('/').reverse().join('-'));
        const diffDias = Math.ceil((entrega - hoje) / (1000 * 60 * 60 * 24));
        
        if (diffDias < 0) {
            // Entrega atrasada
            return `<span class="status-badge entrega atrasado">
                <i class="fas fa-truck"></i>
                Ent. atrasada (${Math.abs(diffDias)}d)
            </span>`;
        } else if (diffDias <= 3) {
            // Entrega próxima do prazo
            return `<span class="status-badge entrega proximo">
                <i class="fas fa-clock"></i>
                Ent. em ${diffDias}d
            </span>`;
        } else {
            // Entrega no prazo
            return `<span class="status-badge entrega no-prazo">
                <i class="fas fa-check-circle"></i>
                Ent. OK
            </span>`;
        }
    }
    
    function formatNumber(num) {
        if (num === null || num === undefined || num === '') return '0';
        const number = parseFloat(num);
        if (isNaN(number)) return '0';
        return new Intl.NumberFormat('pt-BR').format(number);
    }
    
    function formatCurrency(value) {
        if (value === null || value === undefined || value === '') return 'R$ 0,00';
        const number = parseFloat(value);
        if (isNaN(number)) return 'R$ 0,00';
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(number);
    }
    
    function formatDate(dateStr) {
        if (!dateStr || dateStr === null || dateStr === undefined || dateStr === '') return '-';
        try {
            const [day, month, year] = dateStr.split('/');
            if (!day || !month || !year) return dateStr;
            return `${day}/${month}/${year}`;
        } catch (e) {
            return dateStr;
        }
    }
    
    // Função de teste para verificar filtros
    window.testFilters = function() {
        console.log('🧪 Testando filtros...');
        if (filtersForm) {
            const formData = new FormData(filtersForm);
            console.log('📋 Dados do formulário:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
        } else {
            console.error('❌ Formulário não encontrado!');
        }
    };
    
    // Funções globais para uso em onclick
    window.changePage = function(page) {
        if (page >= 1 && page <= totalPages && page !== currentPage) {
            currentPage = page;
            loadPedidos();
        }
    };
    
    window.showPedidoDetails = showPedidoDetails;
    
    // Função global para mudar visão (supervisor) - apenas para usuários com permissão
    window.mudarVisao = function() {
        const supervisorSelect = document.getElementById('visao_supervisor');
        const vendedorSelect = document.getElementById('filtro_vendedor');
        
        if (!supervisorSelect || !vendedorSelect) {
            console.log('Filtros de supervisão não disponíveis para este usuário');
            return;
        }
        
        const supervisorSelecionado = supervisorSelect.value;
        
        // Limpar seleção atual
        vendedorSelect.value = '';
        
        // Carregar vendedores baseado no supervisor
        carregarVendedores(supervisorSelecionado);
        
        // Aplicar filtros
        currentPage = 1;
        loadPedidos();
    };
    
    // Função para carregar vendedores baseado no supervisor
    function carregarVendedores(supervisorId) {
        const selectVendedor = document.getElementById('filtro_vendedor');
        
        if (!selectVendedor) {
            console.log('Filtro de vendedor não disponível para este usuário');
            return;
        }
        
        // Mostrar loading
        selectVendedor.innerHTML = '<option value="">Carregando...</option>';
        
        // Fazer requisição AJAX
        const url = '/Site/includes/ajax/carregar_vendedores_ajax.php' + (supervisorId ? '?supervisor=' + encodeURIComponent(supervisorId) : '');
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    selectVendedor.innerHTML = data.html;
                    console.log('✅ Vendedores carregados:', data.count);
                } else {
                    selectVendedor.innerHTML = '<option value="">Erro ao carregar</option>';
                    console.error('❌ Erro:', data.message);
                }
            })
            .catch(error => {
                console.error('❌ Erro:', error);
                selectVendedor.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    }
    
    // Função global para limpar filtros (chamada pelo botão HTML)
    window.limparFiltros = function() {
        const filtros = document.querySelectorAll('.filtros-form select, .filtros-form input[type="text"]');
        filtros.forEach(filtro => {
            filtro.value = '';
        });
        
        // Recarregar vendedores (todos) quando limpar filtros
        carregarVendedores('');
        
        // Aplicar filtros
        currentPage = 1;
        loadPedidos();
    };
    
    console.log('🎉 JavaScript de Pedidos em Aberto carregado com sucesso!');
});
