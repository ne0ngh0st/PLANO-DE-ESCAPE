// Detalhes do Gerenciador - Scripts Específicos

/**
 * Atualizar dados da página
 */
function atualizarDados() {
    // Mostrar indicador de carregamento
    const btn = event.target.closest('.btn-action');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando...';
    btn.disabled = true;

    // Recarregar a página após um pequeno delay
    setTimeout(() => {
        location.reload();
    }, 500);
}

/**
 * Exportar dados para CSV
 */
function exportarDados() {
    // Mostrar indicador de carregamento
    const btn = event.target.closest('.btn-action');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';
    btn.disabled = true;

    try {
        // Obter dados da URL atual
        const urlParams = new URLSearchParams(window.location.search);
        const gerenciador = urlParams.get('gerenciador');
        
        if (!gerenciador) {
            mostrarNotificacao('Gerenciador não identificado', 'error');
            return;
        }

        // Construir URL correta baseada no ambiente atual
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('gestao-comercial.autopel.com')) {
            // Ambiente de produção real
            baseUrl = 'https://gestao-comercial.autopel.com';
        } else if (currentUrl.includes('/Site/')) {
            // Ambiente local de produção (/Site/)
            baseUrl = currentUrl.split('/Site/')[0] + '/Site';
        } else if (currentUrl.includes('/Gestao/')) {
            // Ambiente local de desenvolvimento (/Gestao/)
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Fallback - usar origem atual
            baseUrl = window.location.origin;
        }
        
        const exportUrl = `${baseUrl}/includes/ajax/exportar_gerenciador_ajax.php?gerenciador=${encodeURIComponent(gerenciador)}&t=${Date.now()}`;
        
        // Abrir em nova aba para download
        window.open(exportUrl, '_blank');
        
        mostrarNotificacao('Exportação iniciada', 'success');
        
    } catch (error) {
        console.error('Erro ao exportar dados:', error);
        mostrarNotificacao('Erro ao exportar dados: ' + error.message, 'error');
    } finally {
        // Restaurar botão
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

/**
 * Ver contratos de um gerenciador específico
 */
async function verContratos(gerenciador) {
    console.log('🔍 [DEBUG] Iniciando verContratos para gerenciador:', gerenciador);
    
    // Verificar se o gerenciador foi passado
    if (!gerenciador || gerenciador.trim() === '') {
        console.error('❌ [DEBUG] Gerenciador não informado ou vazio');
        mostrarNotificacao('Gerenciador não informado', 'error');
        return;
    }
    
    // Mostrar loading
    const btn = event.target.closest('.btn-documentos-compact');
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
    }
    
    try {
        // Construir URL correta baseada no ambiente atual
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('gestao-comercial.autopel.com')) {
            // Ambiente de produção real
            baseUrl = 'https://gestao-comercial.autopel.com';
        } else if (currentUrl.includes('/Site/')) {
            // Ambiente local de produção (/Site/)
            baseUrl = currentUrl.split('/Site/')[0] + '/Site';
        } else if (currentUrl.includes('/Gestao/')) {
            // Ambiente local de desenvolvimento (/Gestao/)
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Fallback - usar origem atual
            baseUrl = window.location.origin;
        }
        
        // Verificar se deve mostrar excluídas baseado na URL atual
        const urlParams = new URLSearchParams(window.location.search);
        const mostrarExcluidas = urlParams.get('mostrar_excluidas') === '1' ? '1' : '0';
        
        // Debug: Log dos parâmetros
        console.log('🔍 [DEBUG] URL atual:', window.location.href);
        console.log('🔍 [DEBUG] Parâmetros URL:', urlParams.toString());
        console.log('🔍 [DEBUG] Mostrar excluídas:', mostrarExcluidas);
        
        const url = `${baseUrl}/includes/ajax/contratos_detalhes_ajax.php?gerenciador=${encodeURIComponent(gerenciador)}&mostrar_excluidas=${mostrarExcluidas}`;
        console.log('🌐 [DEBUG] Fazendo requisição para:', url);
        
        const response = await fetch(url);
        console.log('📡 [DEBUG] Resposta recebida. Status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 [DEBUG] Dados recebidos:', data);
        
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar dados dos contratos');
        }
        
        // Atualizar título do modal
        const modalTitulo = document.getElementById('modalContratosTitulo');
        if (modalTitulo) {
            modalTitulo.textContent = `Contratos - ${gerenciador}`;
        }
        
        // Atualizar conteúdo do modal
        const modalBody = document.getElementById('modalContratosBody');
        if (modalBody) {
            modalBody.innerHTML = data.html || '<p>Nenhum contrato encontrado.</p>';
        }
        
        // Mostrar modal
        const modal = document.getElementById('modalContratos');
        if (modal) {
            modal.classList.add('active');
        }
        
        console.log('✅ [DEBUG] Modal de contratos aberto com sucesso');
        
    } catch (error) {
        console.error('❌ [DEBUG] Erro ao carregar contratos:', error);
        mostrarNotificacao('Erro ao carregar contratos: ' + error.message, 'error');
    } finally {
        // Restaurar botão
        if (btn) {
            btn.innerHTML = '<i class="fas fa-file-alt"></i>';
            btn.disabled = false;
        }
    }
}

/**
 * Toggle para mostrar/ocultar licitações excluídas
 */
function toggleExcluidas() {
    const checkbox = document.getElementById('toggle-excluidas');
    const mostrarExcluidas = checkbox.checked ? '1' : '0';
    
    // Obter parâmetros da URL atual
    const urlParams = new URLSearchParams(window.location.search);
    const gerenciador = urlParams.get('gerenciador');
    
    // Atualizar parâmetro mostrar_excluidas
    urlParams.set('mostrar_excluidas', mostrarExcluidas);
    
    // Construir nova URL
    const newUrl = window.location.pathname + '?' + urlParams.toString();
    
    // Redirecionar para nova URL
    window.location.href = newUrl;
}

/**
 * Excluir licitação
 */
async function excluirLicitacao(licitacaoId) {
    // Confirmar exclusão
    if (!confirm('Tem certeza que deseja excluir esta licitação?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', licitacaoId);
    
    try {
        const url = window.baseUrl 
            ? window.baseUrl('includes/ajax/excluir_licitacao_ajax.php')
            : 'includes/ajax/excluir_licitacao_ajax.php';
        
        const response = await fetch(url, {
            method: 'POST',
            body: form
        });
        
        // Verificar se a resposta é válida
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text);
            throw new Error('Resposta do servidor não é válida (não é JSON)');
        }
        
        const data = await response.json();
        
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            // Recarregar página após exclusão
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacao(data.message, 'error');
        }
    } catch (error) {
        console.error('Erro na exclusão:', error);
        mostrarNotificacao('Erro de conexão: ' + error.message, 'error');
    }
}

/**
 * Editar licitação - Abrir modal com dados
 */
async function editarLicitacao(licitacaoId) {
    console.log('🔧 EDITANDO LICITAÇÃO - ID:', licitacaoId);
    
    // Verificar se o ID é válido
    if (!licitacaoId || licitacaoId === 'undefined' || licitacaoId === 'null') {
        console.error('❌ ID da licitação inválido:', licitacaoId);
        mostrarNotificacao('Erro: ID da licitação inválido', 'error');
        return;
    }
    
    try {
        // Mostrar modal com loading
        const modal = document.getElementById('modalEditarLicitacao');
        if (!modal) {
            console.error('❌ Modal não encontrado! Verificando elementos disponíveis...');
            const modals = document.querySelectorAll('[id*="modal"]');
            console.log('Modais encontrados:', modals);
            throw new Error('Modal não encontrado');
        }
        
        console.log('✅ Modal encontrado:', modal);
        console.log('Modal inicial display:', modal.style.display);
        console.log('Modal inicial classes:', modal.className);
        
        // Mostrar loading
        modal.classList.add('active');
        const modalBody = modal.querySelector('.modal-body');
        const originalBody = modalBody.innerHTML;
        
        modalBody.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #1a237e;"></i>
                <p style="margin-top: 1rem;">Carregando dados da licitação...</p>
            </div>
        `;
        
        // Buscar dados da licitação
        const url = window.baseUrl 
            ? `${window.baseUrl('includes/ajax/get_licitacao.php')}?id=${licitacaoId}`
            : `includes/ajax/get_licitacao.php?id=${licitacaoId}`;
        console.log('🌐 Buscando licitação em:', url);
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 Dados recebidos:', data);
        
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar dados da licitação');
        }
        
        // Restaurar conteúdo do modal
        modalBody.innerHTML = originalBody;
        
        // Preencher formulário com dados
        preencherFormularioEdicao(data.data);
        
        // Forçar exibição do modal com estilos inline
        modal.style.cssText = `
            display: flex !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.5) !important;
            z-index: 10000 !important;
            opacity: 1 !important;
            visibility: visible !important;
            align-items: center !important;
            justify-content: center !important;
        `;
        modal.classList.add('active');
        
        console.log('✅ MODAL ABERTO COM SUCESSO');
        console.log('Modal classes:', modal.className);
        console.log('Modal display:', modal.style.display);
        
    } catch (error) {
        console.error('💥 ERRO ao carregar licitação:', error);
        mostrarNotificacao('Erro ao carregar dados: ' + error.message, 'error');
        fecharModalEdicao();
    }
}

/**
 * Obter URL base do sistema
 */
function getBaseUrl() {
    const currentUrl = window.location.href;
    
    if (currentUrl.includes('gestao-comercial.autopel.com')) {
        return 'https://gestao-comercial.autopel.com';
    } else if (currentUrl.includes('/Site/')) {
        return currentUrl.split('/Site/')[0] + '/Site';
    } else if (currentUrl.includes('/Gestao/')) {
        return currentUrl.split('/Gestao/')[0] + '/Gestao';
    } else {
        return window.location.origin;
    }
}

/**
 * Preencher formulário de edição com dados da licitação
 */
function preencherFormularioEdicao(licitacao) {
    console.log('📝 PREENCHENDO FORMULÁRIO');
    console.log('Dados da licitação:', licitacao);
    
    // Mapear campos do banco para campos do formulário
    const campos = {
        'licitacao_id': licitacao.ID || '',
        'licitacao_cod_cliente': licitacao.COD_CLIENT || '',
        'licitacao_cnpj': licitacao.CNPJ || '',
        'licitacao_gerenciador': licitacao.GERENCIADOR || '',
        'licitacao_status': licitacao.STATUS || 'Vigente',
        'licitacao_sigla': licitacao.SIGLA || '',
        'licitacao_razao_social': licitacao.ORGAO || '',
        'licitacao_tipo': licitacao.TIPO || '',
        'licitacao_produto': licitacao.PRODUTO || '',
        'licitacao_numero_pregao': licitacao.NUMERO_ATA || '',
        'licitacao_termo_contrato': licitacao.NUMERO_CONTRATO || '',
        'licitacao_valor_ata': licitacao.VALOR_ATA || '',
        'licitacao_valor_global': licitacao.VALOR_GLOBAL || '',
        'licitacao_valor_consumido': licitacao.VALOR_CONSUMIDO || '',
        'licitacao_grupo': licitacao.GRUPO || '',
        'licitacao_tabela': licitacao.TABELA || '',
        'licitacao_edital': licitacao.EDITAL_LICITACAO || '',
        'licitacao_data_inicio_ata': formatarDataParaInput(licitacao.DATA_INICIO_ATA),
        'licitacao_data_termino_ata': formatarDataParaInput(licitacao.DATA_TERMINO_ATA),
        'licitacao_data_inicio': formatarDataParaInput(licitacao.DATA_INICIO_CONTRATO),
        'licitacao_data_termino': formatarDataParaInput(licitacao.DATA_TERMINO_CONTRATO)
    };
    
    // Preencher cada campo
    for (const [campoId, valor] of Object.entries(campos)) {
        const elemento = document.getElementById(campoId);
        if (elemento) {
            elemento.value = valor;
            console.log(`✅ ${campoId}: ${valor}`);
        } else {
            console.error(`❌ Campo não encontrado: ${campoId}`);
        }
    }
    
    console.log('✅ FORMULÁRIO PREENCHIDO COM SUCESSO');
}

/**
 * Formatar data para input type="date"
 */
function formatarDataParaInput(data) {
    if (!data || data === '0000-00-00' || data === 'NULL') {
        return '';
    }
    
    // Se a data já está no formato YYYY-MM-DD
    if (data.match(/^\d{4}-\d{2}-\d{2}$/)) {
        return data;
    }
    
    // Tentar converter de outros formatos
    try {
        const date = new Date(data);
        if (isNaN(date.getTime())) {
            return '';
        }
        return date.toISOString().split('T')[0];
    } catch (error) {
        return '';
    }
}

/**
 * Fechar modal de edição
 */
function fecharModalEdicao() {
    const modal = document.getElementById('modalEditarLicitacao');
    if (modal) {
        // Remover classes e limpar estilos inline
        modal.classList.remove('active');
        modal.style.cssText = '';
        
        // Limpar formulário
        const form = document.getElementById('formEditarLicitacao');
        if (form) {
            form.reset();
        }
        
        console.log('🔒 Modal fechado');
    }
}

/**
 * Salvar edição da licitação
 */
async function salvarEdicaoLicitacao() {
    console.log('🚀 SALVANDO EDIÇÃO DA LICITAÇÃO');
    
    try {
        // Obter dados do formulário
        const form = document.getElementById('formEditarLicitacao');
        if (!form) {
            console.error('❌ Formulário não encontrado');
            throw new Error('Formulário não encontrado');
        }
        
        console.log('✅ Formulário encontrado:', form);
        
        const formData = new FormData(form);
        
        // Log dos dados do formulário
        console.log('📋 Dados do formulário:');
        for (let [key, value] of formData.entries()) {
            console.log(`  ${key}: ${value}`);
        }
        
        // Validar dados obrigatórios
        const id = formData.get('id');
        const razaoSocial = formData.get('razao_social');
        const valorGlobal = formData.get('valor_global');
        
        if (!id) {
            throw new Error('ID da licitação não encontrado');
        }
        
        if (!razaoSocial || !valorGlobal) {
            throw new Error('Razão Social e Valor Global são obrigatórios');
        }
        
        // Mostrar loading no botão
        const btnSalvar = document.querySelector('#modalEditarLicitacao .btn-save');
        if (btnSalvar) {
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btnSalvar.disabled = true;
        }
        
        // Enviar dados
        const url = window.baseUrl 
            ? window.baseUrl('includes/ajax/save_licitacao.php')
            : 'includes/ajax/save_licitacao.php';
        
        console.log('🌐 Enviando para:', url);
        console.log('📤 Dados:', Object.fromEntries(formData.entries()));
        
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 Resposta:', data);
        
        if (data.success) {
            mostrarNotificacao(data.message || 'Licitação atualizada com sucesso!', 'success');
            fecharModalEdicao();
            
            // Recarregar página para mostrar alterações
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            throw new Error(data.message || 'Erro ao salvar alterações');
        }
        
    } catch (error) {
        console.error('💥 ERRO:', error);
        mostrarNotificacao('Erro: ' + error.message, 'error');
    } finally {
        // Restaurar botão
        const btnSalvar = document.querySelector('#modalEditarLicitacao .btn-save');
        if (btnSalvar) {
            btnSalvar.innerHTML = '<i class="fas fa-save"></i> Salvar Alterações';
            btnSalvar.disabled = false;
        }
    }
}

/**
 * Mostrar notificação
 */
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao notificacao-${tipo}`;
    notificacao.innerHTML = `
        <div class="notificacao-content">
            <i class="fas ${tipo === 'success' ? 'fa-check-circle' : tipo === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${mensagem}</span>
        </div>
        <button class="notificacao-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Adicionar estilos se não existirem
    if (!document.querySelector('#notificacao-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notificacao-styles';
        styles.textContent = `
            .notificacao {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                padding: 1rem 1.5rem;
                display: flex;
                align-items: center;
                gap: 1rem;
                z-index: 10000;
                transform: translateX(400px);
                transition: transform 0.3s ease;
                border-left: 4px solid #2196f3;
                min-width: 300px;
                max-width: 500px;
            }
            
            .notificacao.show {
                transform: translateX(0);
            }
            
            .notificacao-success {
                border-left-color: #4caf50;
            }
            
            .notificacao-error {
                border-left-color: #f44336;
            }
            
            .notificacao-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex: 1;
            }
            
            .notificacao-content i {
                font-size: 1.2rem;
            }
            
            .notificacao-success .notificacao-content i {
                color: #4caf50;
            }
            
            .notificacao-error .notificacao-content i {
                color: #f44336;
            }
            
            .notificacao-close {
                background: none;
                border: none;
                color: #666;
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 4px;
                transition: all 0.2s ease;
            }
            
            .notificacao-close:hover {
                background: #f0f0f0;
                color: #333;
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Adicionar ao body
    document.body.appendChild(notificacao);
    
    // Mostrar com animação
    setTimeout(() => notificacao.classList.add('show'), 100);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (notificacao.parentElement) {
            notificacao.classList.remove('show');
            setTimeout(() => notificacao.remove(), 300);
        }
    }, 5000);
}

/**
 * Ordenar cards por porcentagem de consumo (do mais verde para o mais vermelho)
 * Verde = bem consumido (100%) -> Vermelho = pouco consumido (0%)
 */
function ordenarCardsPorConsumo() {
    const grid = document.querySelector('.licitacoes-grid');
    if (!grid) return;
    
    const cards = Array.from(grid.querySelectorAll('.licitacao-card'));
    
    // Verificar se já está ordenado para evitar reorganizações desnecessárias
    let isAlreadySorted = true;
    for (let i = 0; i < cards.length - 1; i++) {
        const percentualA = obterPercentualConsumo(cards[i]);
        const percentualB = obterPercentualConsumo(cards[i + 1]);
        if (percentualA < percentualB) {
            isAlreadySorted = false;
            break;
        }
    }
    
    // Se já está ordenado, não fazer nada
    if (isAlreadySorted) return;
    
    // Ordenar cards por porcentagem de consumo (decrescente - verde primeiro, vermelho por último)
    cards.sort((a, b) => {
        const percentualA = obterPercentualConsumo(a);
        const percentualB = obterPercentualConsumo(b);
        return percentualB - percentualA; // Maior percentual primeiro (verde)
    });
    
    // Reordenar os cards no DOM de forma mais suave
    cards.forEach((card, index) => {
        // Adicionar uma pequena transição para suavizar a reorganização
        if (!card.classList.contains('loading')) {
            card.style.transition = 'transform 0.3s ease';
        }
        card.style.transform = 'translateY(0)';
        grid.appendChild(card);
    });
}

/**
 * Obter porcentagem de consumo de um card
 */
function obterPercentualConsumo(card) {
    const percentualElement = card.querySelector('.progress-percentual');
    if (!percentualElement) return 0;
    
    const percentualText = percentualElement.textContent.replace('%', '').replace(',', '.');
    return parseFloat(percentualText) || 0;
}

/**
 * Event Listeners
 */
document.addEventListener('DOMContentLoaded', function() {
    // Configurar estado inicial dos cards
    const cards = document.querySelectorAll('.licitacao-card');
    cards.forEach(card => {
        card.classList.add('loading');
    });

    // Animação suave dos cards ao carregar
    // A ordenação já é feita no PHP, então não precisamos reorganizar aqui
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.remove('loading');
            card.classList.add('loaded');
        }, 100 * index);
    });

    // Animar cards de resumo
    const resumoCards = document.querySelectorAll('.resumo-card');
    resumoCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 50 * index);
    });
    
    // Adicionar efeito hover nos cards
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Adicionar efeito hover nos cards de resumo
    resumoCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Event listener para fechar modal ao clicar fora
    const modal = document.getElementById('modalEditarLicitacao');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                fecharModalEdicao();
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                fecharModalEdicao();
            }
        });
        
        // Event listener para formulário de edição (apenas para prevenir submit acidental)
        const formEditar = document.getElementById('formEditarLicitacao');
        if (formEditar) {
            console.log('✅ Event listener adicionado ao formulário');
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('📤 FORMULÁRIO SUBMETIDO - prevenindo submit tradicional');
                // Não chamar salvarEdicaoLicitacao() aqui, pois o botão já chama diretamente
            });
        } else {
            console.error('❌ Formulário formEditarLicitacao não encontrado!');
        }
    }
    
    // Event listener para modal de contratos
    const modalContratos = document.getElementById('modalContratos');
    if (modalContratos) {
        modalContratos.addEventListener('click', function(e) {
            if (e.target === modalContratos) {
                fecharModal('modalContratos');
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalContratos.classList.contains('active')) {
                fecharModal('modalContratos');
            }
        });
    }
});

/**
 * Fechar modal genérico
 */
function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

/**
 * Filtrar licitações por termo de pesquisa
 */
function filtrarLicitacoes() {
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const resultsInfo = document.getElementById('searchResultsInfo');
    const resultsText = document.getElementById('searchResultsText');
    
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.licitacao-card');
    let visibleCount = 0;
    
    // Mostrar/ocultar botão de limpar
    if (searchTerm.length > 0) {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
        resultsInfo.style.display = 'none';
    }
    
    // Filtrar cards
    cards.forEach(card => {
        const cardData = extrairDadosCard(card);
        const matches = verificarCorrespondencia(cardData, searchTerm);
        
        if (matches) {
            card.classList.remove('search-hidden');
            card.classList.add('search-highlight');
            visibleCount++;
        } else {
            card.classList.add('search-hidden');
            card.classList.remove('search-highlight');
        }
    });
    
    // Mostrar informações dos resultados
    if (searchTerm.length > 0) {
        const totalCount = cards.length;
        resultsText.textContent = `${visibleCount} de ${totalCount} licitação(ões) encontrada(s)`;
        resultsInfo.style.display = 'flex';
        
        // Remover highlight após animação
        setTimeout(() => {
            cards.forEach(card => {
                card.classList.remove('search-highlight');
            });
        }, 500);
    }
}

/**
 * Extrair dados de um card para pesquisa
 */
function extrairDadosCard(card) {
    const data = {};
    
    // Nome do órgão/razão social
    const titulo = card.querySelector('.licitacao-titulo h3');
    if (titulo) {
        data.razaoSocial = titulo.textContent.trim();
    }
    
    // Sigla
    const sigla = card.querySelector('.sigla-badge');
    if (sigla) {
        data.sigla = sigla.textContent.trim();
    }
    
    // Código cliente
    const codCliente = card.querySelector('.info-row .info-value');
    if (codCliente) {
        data.codCliente = codCliente.textContent.trim();
    }
    
    // Número ATA
    const numeroAta = card.querySelector('.info-row:nth-child(2) .info-value');
    if (numeroAta) {
        data.numeroAta = numeroAta.textContent.trim();
    }
    
    // Número contrato
    const numeroContrato = card.querySelector('.info-row:nth-child(3) .info-value');
    if (numeroContrato) {
        data.numeroContrato = numeroContrato.textContent.trim();
    }
    
    // Tipo
    const tipo = card.querySelector('.info-row:nth-child(4) .info-value');
    if (tipo) {
        data.tipo = tipo.textContent.trim();
    }
    
    // Produto
    const produto = card.querySelector('.info-row:nth-child(5) .info-value');
    if (produto) {
        data.produto = produto.textContent.trim();
    }
    
    return data;
}

/**
 * Verificar se os dados do card correspondem ao termo de pesquisa
 */
function verificarCorrespondencia(cardData, searchTerm) {
    if (!searchTerm) return true;
    
    const searchableText = [
        cardData.razaoSocial || '',
        cardData.sigla || '',
        cardData.codCliente || '',
        cardData.numeroAta || '',
        cardData.numeroContrato || '',
        cardData.tipo || '',
        cardData.produto || ''
    ].join(' ').toLowerCase();
    
    return searchableText.includes(searchTerm);
}

/**
 * Limpar pesquisa
 */
function limparPesquisa() {
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const resultsInfo = document.getElementById('searchResultsInfo');
    const cards = document.querySelectorAll('.licitacao-card');
    
    if (searchInput) {
        searchInput.value = '';
    }
    
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }
    
    if (resultsInfo) {
        resultsInfo.style.display = 'none';
    }
    
    // Mostrar todos os cards
    cards.forEach(card => {
        card.classList.remove('search-hidden', 'search-highlight');
    });
    
    // Focar no input
    if (searchInput) {
        searchInput.focus();
    }
}
