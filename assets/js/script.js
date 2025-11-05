/* ===== FUNÇÕES RESPONSIVAS GLOBAIS ===== */

// Toggle da sidebar no mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// Fechar sidebar ao clicar fora dela (mobile)
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    
    if (sidebar && sidebar.classList.contains('show')) {
        if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Ajustar layout baseado no tamanho da tela
function adjustLayout() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const dashboardContainer = document.querySelector('.dashboard-container');
    
    // Sempre esconder a sidebar e remover margens
    if (sidebar) sidebar.classList.remove('show');
    if (mainContent) mainContent.style.marginLeft = '0';
    if (dashboardContainer) {
        dashboardContainer.style.marginLeft = '0';
        dashboardContainer.style.maxWidth = '100%';
        dashboardContainer.style.width = '100%';
    }
}

// Executar ajuste de layout ao carregar e redimensionar
window.addEventListener('load', adjustLayout);
window.addEventListener('resize', adjustLayout);

/* ===== FUNÇÕES DE BUSCA ===== */

// Inicializar funcionalidade de busca
function initSearch() {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;

    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.toLowerCase();
        
        searchTimeout = setTimeout(() => {
            performSearch(searchTerm);
        }, 300);
    });
}

// Executar busca
function performSearch(searchTerm) {
    const table = document.querySelector('.table tbody');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/* ===== FUNÇÕES DE MODAL ===== */

// Inicializar modais
function initModals() {
    // Abrir modais
    document.addEventListener('click', function(event) {
        const target = event.target;
        
        if (target.hasAttribute('data-modal')) {
            const modalId = target.getAttribute('data-modal');
            openModal(modalId);
        }
        
        if (target.classList.contains('modal-close') || target.classList.contains('modal')) {
            closeModal();
        }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
}

// Abrir modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

// Fechar modal
function closeModal() {
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
        modal.classList.remove('show');
    });
    document.body.style.overflow = '';
}

/* ===== FUNÇÕES DE OBSERVAÇÕES ===== */

// Inicializar funcionalidade de observações
function initObservacoes() {
    // Adicionar observação
    const addObsBtn = document.querySelector('.btn-add-obs');
    if (addObsBtn) {
        addObsBtn.addEventListener('click', function() {
            const obsText = document.getElementById('nova-observacao').value.trim();
            if (obsText) {
                addObservacao(obsText);
                document.getElementById('nova-observacao').value = '';
            }
        });
    }

    // Carregar observações existentes
    loadObservacoes();
}

// Adicionar nova observação
function addObservacao(texto) {
    const clienteId = document.querySelector('[data-cliente-id]')?.getAttribute('data-cliente-id');
    if (!clienteId) return;

    fetch('gerenciar_observacoes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=adicionar&cliente_id=${clienteId}&observacao=${encodeURIComponent(texto)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadObservacoes();
            showMessage('Observação adicionada com sucesso!', 'success');
        } else {
            showMessage('Erro ao adicionar observação: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showMessage('Erro ao adicionar observação', 'error');
    });
}

// Carregar observações
function loadObservacoes() {
    const clienteId = document.querySelector('[data-cliente-id]')?.getAttribute('data-cliente-id');
    if (!clienteId) return;

    fetch(`gerenciar_observacoes.php?acao=listar&cliente_id=${clienteId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayObservacoes(data.observacoes);
        }
    })
    .catch(error => {
        console.error('Erro ao carregar observações:', error);
    });
}

// Exibir observações
function displayObservacoes(observacoes) {
    const container = document.getElementById('observacoes-container');
    if (!container) return;

    if (observacoes.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhuma observação encontrada.</p>';
        return;
    }

    const html = observacoes.map(obs => `
        <div class="observacao-item">
            <div class="observacao-header">
                <span class="observacao-data">${obs.data}</span>
                <button class="btn btn-sm btn-danger" onclick="excluirObservacao(${obs.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="observacao-texto">${obs.observacao}</div>
        </div>
    `).join('');

    container.innerHTML = html;
}

// Excluir observação
function excluirObservacao(obsId) {
    if (!confirm('Tem certeza que deseja excluir esta observação?')) return;
    const motivo = prompt('Informe o motivo da exclusão (obrigatório):');
    if (motivo === null) return;
    const motivoTrim = (motivo || '').trim();
    if (!motivoTrim) { showMessage('Motivo da exclusão é obrigatório.', 'error'); return; }

    fetch('gerenciar_observacoes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=excluir&observacao_id=${obsId}&motivo_exclusao=${encodeURIComponent(motivoTrim)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadObservacoes();
            showMessage('Observação movida para lixeira com sucesso!', 'success');
        } else {
            showMessage('Erro ao excluir observação: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showMessage('Erro ao excluir observação', 'error');
    });
}

/* ===== FUNÇÕES DE GRÁFICOS ===== */



/* ===== FUNÇÕES DE EXCLUSÃO ===== */

// Confirmar exclusão de cliente
// NOTA: Esta função foi removida pois a implementação principal está em carteira.js
// A implementação atual usa modal com seleção de motivo obrigatório
// function confirmarExclusaoCliente() - REMOVIDA - usar a implementação em carteira.js

// Confirmar exclusão de lead
function confirmarExclusaoLead(leadId, nomeLead) {
    if (confirm(`Tem certeza que deseja excluir o lead "${nomeLead}"? Esta ação não pode ser desfeita.`)) {
        window.location.href = `excluir_lead.php?id=${leadId}`;
    }
}

/* ===== FUNÇÕES AUXILIARES ===== */

// Mostrar mensagem
function showMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type}`;
    messageDiv.textContent = message;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

// Formatar valores monetários
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Formatar datas
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

// Debounce function para otimizar performance
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

/* ===== INICIALIZAÇÃO ===== */

// Inicializar todas as funcionalidades quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar funcionalidades básicas
    initSearch();
    initModals();
    initObservacoes();
    
    // Ajustar layout inicial
    adjustLayout();
    
    // Adicionar listeners para formulários
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            }
        });
    });
    
    // Adicionar listeners para links de exclusão
    const deleteLinks = document.querySelectorAll('a[href*="excluir"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja realizar esta ação? Esta ação não pode ser desfeita.')) {
                e.preventDefault();
            }
        });
    });
});

/* ===== FUNÇÕES ESPECÍFICAS PARA PÁGINAS ===== */

// Função específica para carteira.php
function initCarteira() {
    // Inicializar filtros
    const filtros = document.querySelectorAll('.filtro-select');
    filtros.forEach(filtro => {
        filtro.addEventListener('change', function() {
            document.getElementById('form-filtros').submit();
        });
    });
    
    // Inicializar paginação
    const paginacaoBtns = document.querySelectorAll('.paginacao-btn');
    paginacaoBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const pagina = this.getAttribute('data-pagina');
            window.location.href = `carteira.php?pagina=${pagina}`;
        });
    });
}

// Função específica para leads.php
function initLeads() {
    // Inicializar filtros de status
    const statusFiltros = document.querySelectorAll('.status-filter');
    statusFiltros.forEach(filtro => {
        filtro.addEventListener('change', function() {
            document.getElementById('form-filtros-leads').submit();
        });
    });
}

// Função específica para detalhes_cliente.php
function initDetalhesCliente() {
    // Inicializar gráficos de histórico
    const historicoGraficos = document.querySelectorAll('.historico-grafico');
    historicoGraficos.forEach(grafico => {
        const barras = grafico.querySelectorAll('.barra > div');
        barras.forEach(barra => {
            const valor = parseFloat(barra.getAttribute('data-valor'));
            const maxValor = parseFloat(barra.parentElement.getAttribute('data-max'));
            const porcentagem = (valor / maxValor) * 100;
            barra.style.width = porcentagem + '%';
        });
    });
}

// Verificar se estamos em uma página específica e inicializar funcionalidades
if (document.body.classList.contains('page-carteira')) {
    initCarteira();
}

if (document.body.classList.contains('page-leads')) {
    initLeads();
}

if (document.body.classList.contains('page-detalhes-cliente')) {
    initDetalhesCliente();
} 