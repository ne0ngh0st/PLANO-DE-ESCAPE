/**
 * JavaScript para a página de gerenciamento de modais
 * Funcionalidades: navegação por tabs, CRUD de perguntas, gerenciamento de configurações
 */

// Variáveis globais
let perguntaEditando = null;
let configuracoesAtuais = {};

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    inicializarPagina();
    carregarConfiguracoes();
});

/**
 * Inicializa a página com configurações padrão
 */
function inicializarPagina() {
    // Configurar event listeners
    configurarEventListeners();
    
    // Mostrar tab inicial
    showTab('overview');
    
    console.log('Página de gerenciamento de modais inicializada');
}

/**
 * Configura todos os event listeners da página
 */
function configurarEventListeners() {
    // Formulário de pergunta
    const formPergunta = document.getElementById('formPergunta');
    if (formPergunta) {
        formPergunta.addEventListener('submit', handleSubmitPergunta);
    }
    
    // Modal overlay
    const modalPergunta = document.getElementById('modalPergunta');
    if (modalPergunta) {
        modalPergunta.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
    }
    
    // Botões de ação
    configurarBotoesAcao();
}

/**
 * Configura os botões de ação da página
 */
function configurarBotoesAcao() {
    // Botão salvar configurações
    const btnSalvar = document.querySelector('[onclick="salvarConfiguracoes()"]');
    if (btnSalvar) {
        btnSalvar.addEventListener('click', salvarConfiguracoes);
    }
    
    // Botão preview modais
    const btnPreview = document.querySelector('[onclick="previewModais()"]');
    if (btnPreview) {
        btnPreview.addEventListener('click', previewModais);
    }
    
    // Botão exportar configurações
    const btnExportar = document.querySelector('[onclick="exportarConfiguracoes()"]');
    if (btnExportar) {
        btnExportar.addEventListener('click', exportarConfiguracoes);
    }
}

/**
 * Carrega as configurações atuais do servidor
 */
async function carregarConfiguracoes() {
    try {
        const response = await fetch('includes/carregar_configuracoes_modais.php');
        const data = await response.json();
        
        if (data.success) {
            configuracoesAtuais = data.configuracoes;
            preencherCamposConfiguracao();
        }
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
        mostrarNotificacao('Erro ao carregar configurações', 'error');
    }
}

/**
 * Preenche os campos de configuração com os dados carregados
 */
function preencherCamposConfiguracao() {
    const campos = {
        'titulo_modal': configuracoesAtuais.modal?.titulo_ligacao || 'Roteiro de Ligação',
        'texto_confirmacao': configuracoesAtuais.modal?.texto_confirmacao || 'Tem certeza que deseja remover este item da sua carteira?',
        'texto_observacoes': configuracoesAtuais.modal?.texto_observacoes || 'Digite sua observação aqui...',
        'texto_agendamento': configuracoesAtuais.modal?.texto_agendamento || 'Agendar Ligação',
        'motivos_exclusao': configuracoesAtuais.exclusao ? configuracoesAtuais.exclusao.join('\n') : 'Cliente Inativo\nFalta de Pagamento\nMudança de Gestão\nFalta de Contato\nMigração para Concorrência\nFalta de Interesse\nProblemas Técnicos\nOutros'
    };
    
    Object.entries(campos).forEach(([id, valor]) => {
        const campo = document.getElementById(id);
        if (campo) {
            campo.value = valor;
        }
    });
}

/**
 * Alterna entre as tabs da interface
 * @param {string} tabName - Nome da tab a ser exibida
 */
function showTab(tabName) {
    // Esconder todos os tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remover classe active de todos os botões
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar tab selecionado
    const tabElement = document.getElementById('tab-' + tabName);
    if (tabElement) {
        tabElement.classList.add('active');
    }
    
    // Adicionar classe active ao botão clicado
    const buttonElement = event.target.closest('.tab-button');
    if (buttonElement) {
        buttonElement.classList.add('active');
    }
    
    // Log para debug
    console.log(`Tab alterado para: ${tabName}`);
}

/**
 * Alterna a visibilidade das opções baseado no tipo de pergunta
 */
function toggleOpcoes() {
    const tipo = document.getElementById('perguntaTipo').value;
    const container = document.getElementById('opcoesContainer');
    
    if (container) {
        if (tipo === 'radio' || tipo === 'select') {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }
}

/**
 * Abre o modal para adicionar nova pergunta
 */
function adicionarPergunta() {
    perguntaEditando = null;
    document.getElementById('modalTitulo').textContent = 'Adicionar Pergunta';
    document.getElementById('formPergunta').reset();
    document.getElementById('perguntaId').value = '';
    document.getElementById('perguntaOrdem').value = getProximaOrdem();
    toggleOpcoes();
    document.getElementById('modalPergunta').style.display = 'flex';
}

/**
 * Edita uma pergunta existente
 * @param {number} id - ID da pergunta a ser editada
 */
async function editarPergunta(id) {
    try {
        const response = await fetch('includes/gerenciar_perguntas_ligacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=buscar&id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            perguntaEditando = data.pergunta;
            document.getElementById('modalTitulo').textContent = 'Editar Pergunta';
            document.getElementById('perguntaId').value = data.pergunta.id;
            document.getElementById('perguntaTexto').value = data.pergunta.pergunta;
            document.getElementById('perguntaTipo').value = data.pergunta.tipo;
            document.getElementById('perguntaOrdem').value = data.pergunta.ordem;
            document.getElementById('perguntaObrigatoria').checked = data.pergunta.obrigatoria == 1;
            
            if (data.pergunta.opcoes) {
                const opcoes = JSON.parse(data.pergunta.opcoes);
                document.getElementById('perguntaOpcoes').value = opcoes.join('\n');
            }
            
            toggleOpcoes();
            document.getElementById('modalPergunta').style.display = 'flex';
        } else {
            mostrarNotificacao('Erro ao carregar pergunta: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao carregar pergunta', 'error');
    }
}

/**
 * Exclui uma pergunta
 * @param {number} id - ID da pergunta a ser excluída
 */
async function excluirPergunta(id) {
    if (!confirm('Tem certeza que deseja excluir esta pergunta?')) {
        return;
    }
    
    try {
        const response = await fetch('includes/gerenciar_perguntas_ligacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=excluir&id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarNotificacao('Pergunta excluída com sucesso!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacao('Erro ao excluir pergunta: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao excluir pergunta', 'error');
    }
}

/**
 * Fecha o modal atual
 */
function fecharModal() {
    const modal = document.getElementById('modalPergunta');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Obtém a próxima ordem para uma nova pergunta
 * @returns {number} Próxima ordem disponível
 */
function getProximaOrdem() {
    const perguntas = document.querySelectorAll('.pergunta-item');
    return perguntas.length + 1;
}

/**
 * Salva todas as configurações
 */
async function salvarConfiguracoes() {
    const configuracoes = {
        titulo_modal: document.getElementById('titulo_modal').value,
        texto_confirmacao: document.getElementById('texto_confirmacao').value,
        texto_observacoes: document.getElementById('texto_observacoes').value,
        texto_agendamento: document.getElementById('texto_agendamento').value,
        motivos_exclusao: document.getElementById('motivos_exclusao').value.split('\n').filter(m => m.trim())
    };
    
    try {
        const response = await fetch('includes/salvar_configuracoes_modais.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(configuracoes)
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarNotificacao('Configurações salvas com sucesso!', 'success');
        } else {
            mostrarNotificacao('Erro ao salvar configurações: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao salvar configurações', 'error');
    }
}

/**
 * Abre a página de preview dos modais
 */
function previewModais() {
    window.open('preview_modais.php', '_blank');
}

/**
 * Visualiza modais de uma categoria específica
 * @param {string} categoria - Nome da categoria
 */
function visualizarModais(categoria) {
    mostrarNotificacao(`Visualizando modais da categoria: ${categoria}`, 'info');
}

/**
 * Edita uma categoria de modais
 * @param {string} categoria - Nome da categoria
 */
function editarCategoria(categoria) {
    mostrarNotificacao(`Editando categoria: ${categoria}`, 'info');
}

/**
 * Preview de um modal específico
 * @param {string} modalId - ID do modal
 */
function previewModal(modalId) {
    mostrarNotificacao(`Preview do modal: ${modalId}`, 'info');
}

/**
 * Edita um modal específico
 * @param {string} modalId - ID do modal
 * @param {string} categoria - Categoria do modal
 */
function editarModal(modalId, categoria) {
    mostrarNotificacao(`Editando modal: ${modalId} da categoria: ${categoria}`, 'info');
}

/**
 * Exporta as configurações atuais
 */
function exportarConfiguracoes() {
    const configuracoes = {
        titulo_modal: document.getElementById('titulo_modal').value,
        texto_confirmacao: document.getElementById('texto_confirmacao').value,
        texto_observacoes: document.getElementById('texto_observacoes').value,
        texto_agendamento: document.getElementById('texto_agendamento').value,
        motivos_exclusao: document.getElementById('motivos_exclusao').value.split('\n').filter(m => m.trim()),
        data_exportacao: new Date().toISOString()
    };
    
    const blob = new Blob([JSON.stringify(configuracoes, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `configuracoes_modais_${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    mostrarNotificacao('Configurações exportadas com sucesso!', 'success');
}

/**
 * Manipula o submit do formulário de pergunta
 * @param {Event} e - Evento de submit
 */
async function handleSubmitPergunta(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('action', perguntaEditando ? 'editar' : 'adicionar');
    
    if (perguntaEditando) {
        formData.append('id', document.getElementById('perguntaId').value);
    }
    
    formData.append('pergunta', document.getElementById('perguntaTexto').value);
    formData.append('tipo', document.getElementById('perguntaTipo').value);
    formData.append('ordem', document.getElementById('perguntaOrdem').value);
    formData.append('obrigatoria', document.getElementById('perguntaObrigatoria').checked ? '1' : '0');
    
    const tipo = document.getElementById('perguntaTipo').value;
    if (tipo === 'radio' || tipo === 'select') {
        const opcoes = document.getElementById('perguntaOpcoes').value
            .split('\n')
            .filter(opcao => opcao.trim())
            .map(opcao => opcao.trim());
        formData.append('opcoes', JSON.stringify(opcoes));
    }
    
    try {
        const response = await fetch('includes/gerenciar_perguntas_ligacao.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarNotificacao(
                perguntaEditando ? 'Pergunta atualizada com sucesso!' : 'Pergunta adicionada com sucesso!', 
                'success'
            );
            fecharModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacao('Erro ao salvar pergunta: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao salvar pergunta', 'error');
    }
}

/**
 * Mostra uma notificação na tela
 * @param {string} mensagem - Mensagem a ser exibida
 * @param {string} tipo - Tipo da notificação (success, error, info)
 */
function mostrarNotificacao(mensagem, tipo) {
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao ${tipo}`;
    
    const icone = {
        'success': 'check-circle',
        'error': 'exclamation-triangle',
        'info': 'info-circle'
    }[tipo] || 'info-circle';
    
    notificacao.innerHTML = `
        <i class="fas fa-${icone}"></i>
        <span>${mensagem}</span>
    `;
    
    document.body.appendChild(notificacao);
    
    setTimeout(() => notificacao.classList.add('show'), 100);
    
    setTimeout(() => {
        notificacao.classList.remove('show');
        setTimeout(() => notificacao.remove(), 300);
    }, 3000);
}

/**
 * Utilitário para debounce de funções
 * @param {Function} func - Função a ser executada
 * @param {number} wait - Tempo de espera em ms
 * @returns {Function} Função com debounce aplicado
 */
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

/**
 * Utilitário para throttle de funções
 * @param {Function} func - Função a ser executada
 * @param {number} limit - Limite de tempo em ms
 * @returns {Function} Função com throttle aplicado
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Exportar funções para uso global
window.showTab = showTab;
window.toggleOpcoes = toggleOpcoes;
window.adicionarPergunta = adicionarPergunta;
window.editarPergunta = editarPergunta;
window.excluirPergunta = excluirPergunta;
window.fecharModal = fecharModal;
window.getProximaOrdem = getProximaOrdem;
window.salvarConfiguracoes = salvarConfiguracoes;
window.previewModais = previewModais;
window.visualizarModais = visualizarModais;
window.editarCategoria = editarCategoria;
window.previewModal = previewModal;
window.editarModal = editarModal;
window.exportarConfiguracoes = exportarConfiguracoes;
window.mostrarNotificacao = mostrarNotificacao;

