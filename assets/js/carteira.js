// Variáveis globais
let cnpjClienteParaExcluir = null;
let observacaoTipoAtual = '';
let observacaoIdentificadorAtual = '';
let ligacaoAtual = null;
let perguntasRoteiro = [];
let tempoInicio = null;
let timerInterval = null;

// Função para mostrar notificações
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao notificacao-${tipo}`;
    notificacao.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    // Definir cores baseadas no tipo
    const cores = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notificacao.style.backgroundColor = cores[tipo] || cores.info;
    
    // Adicionar ícone
    const icones = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    notificacao.innerHTML = `
        <i class="${icones[tipo] || icones.info}" style="margin-right: 8px;"></i>
        ${mensagem}
    `;
    
    // Adicionar ao DOM
    document.body.appendChild(notificacao);
    
    // Animar entrada
    setTimeout(() => {
        notificacao.style.transform = 'translateX(0)';
    }, 100);
    
    // Remover após 5 segundos
    setTimeout(() => {
        notificacao.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notificacao.parentNode) {
                notificacao.parentNode.removeChild(notificacao);
            }
        }, 300);
    }, 5000);
}

// Garantir que as variáveis de observação estejam sempre disponíveis
if (typeof observacaoTipoAtual === 'undefined') {
    observacaoTipoAtual = '';
}
if (typeof observacaoIdentificadorAtual === 'undefined') {
    observacaoIdentificadorAtual = '';
}

// Verificar se as funções estão disponíveis após o carregamento da página
document.addEventListener('DOMContentLoaded', function() {
    
    // ====== FILTROS AJAX (debounced) ======
    try {
        const filtrosForm = document.querySelector('.filtros-form');

        function getClientesContainer() {
            return document.getElementById('clientesListContainer');
        }

        function serializeForm(form) {
            // Começa com os parâmetros atuais da URL para preservar contexto (ex.: visao_supervisor, janela)
            const params = new URLSearchParams(window.location.search);
            // Sobrepõe com valores do formulário
            new FormData(form).forEach((value, key) => {
                if (value !== null && value !== undefined) {
                    params.set(key, value);
                }
            });
            return params;
        }

        function showTableLoading() {
            const container = getClientesContainer();
            if (!container) return;
            container.innerHTML = '<div style="padding:2rem;text-align:center"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
        }

        let currentRequest = { controller: null, key: '' };
        function buildRequestKey(params) {
            // Ordena os pares para chave estável
            const entries = Array.from(params.entries()).sort((a,b) => a[0].localeCompare(b[0]) || String(a[1]).localeCompare(String(b[1])));
            return entries.map(([k,v]) => k + '=' + v).join('&');
        }

        async function carregarTabelaAjax(params) {
            // Detectar se estamos na página de teste ou ultra otimizada
            const isTestePage = window.location.pathname.includes('carteira_teste_otimizada') || window.location.pathname.includes('carteira_admin_diretor_ultra');
            const ajaxFile = isTestePage ? '/Site/includes/ajax/carteira_teste_filtros_ajax.php' : '/Site/includes/carteira_filtros_ajax.php';
            const url = ajaxFile + '?' + params.toString();
            const key = buildRequestKey(params);
            // Dedup: se mesma chave estiver em voo, ignore
            if (currentRequest.key === key && currentRequest.controller) {
                return Promise.resolve();
            }
            // Abort da anterior, se existir
            if (currentRequest.controller) {
                try { currentRequest.controller.abort(); } catch (e) {}
            }
            const controller = new AbortController();
            const signal = controller.signal;
            currentRequest = { controller, key };
            showTableLoading();
            try {
                const resp = await fetch(url, { signal, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!resp.ok) {
                    throw new Error(`HTTP error! status: ${resp.status}`);
                }
                const data = await resp.json();
                if (data.success) {
                    const container = getClientesContainer();
                    if (container) {
                        container.outerHTML = data.html;
                        
                        // Reconfigurar paginação após atualização AJAX será feita mais abaixo
                    }
                    // Atualizar total badge acima se presente nos dados
                    try {
                        const badgeEls = document.querySelectorAll('.total-faturamento-badge-above');
                        badgeEls.forEach(el => {
                            el.textContent = 'Total: R$ ' + (data.totais && typeof data.totais.faturamento !== 'undefined'
                                ? Number(data.totais.faturamento).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})
                                : el.textContent.replace('Total: ', ''));
                        });
                    } catch (e) {}
                    // Atualizar URL
                    const newUrl = window.location.pathname + '?' + params.toString();
                    window.history.replaceState({}, '', newUrl);

                    // Reanexar interceptadores de paginação após re-render
                    interceptarPaginacao();
                    reposicionarBadgeFaturamento();
                    return Promise.resolve();
                } else {
                    mostrarNotificacao(data.message || 'Erro ao carregar tabela', 'error');
                    return Promise.reject(new Error(data.message || 'Erro ao carregar tabela'));
                }
            } catch (e) {
                if (e.name !== 'AbortError') {
                    console.error('Erro ao carregar tabela via AJAX:', e);
                    mostrarNotificacao('Erro ao carregar tabela', 'error');
                    return Promise.reject(e);
                }
                return Promise.resolve();
            }
        }

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        let lastStatusValue = (document.getElementById('filtro_inatividade') && document.getElementById('filtro_inatividade').value) || '';

        const aplicarFiltrosDebounced = debounce(() => {
            if (!filtrosForm) return;
            const params = serializeForm(filtrosForm);
            // Preservar página atual se existir na URL
            const currentUrlParams = new URLSearchParams(window.location.search);
            if (currentUrlParams.has('pagina')) {
                params.set('pagina', currentUrlParams.get('pagina'));
            }
            // Se o status mudou, resetar para a primeira página para evitar página vazia
            const currentStatus = (document.getElementById('filtro_inatividade') && document.getElementById('filtro_inatividade').value) || '';
            if (currentStatus !== lastStatusValue) {
                params.set('pagina', '1');
                lastStatusValue = currentStatus;
            }
            carregarTabelaAjax(params);
        }, 500);

        if (filtrosForm) {
            filtrosForm.addEventListener('submit', function(e) {
                // Se JS estiver ativo, usar AJAX; caso contrário, deixar o submit normal
                e.preventDefault();
                const params = serializeForm(filtrosForm);
                const currentUrlParams = new URLSearchParams(window.location.search);
                if (currentUrlParams.has('pagina')) {
                    params.set('pagina', currentUrlParams.get('pagina'));
                }
                // Reset para primeira página no submit manual
                params.set('pagina', '1');
                carregarTabelaAjax(params);
            });
            // Limpar filtros via AJAX mantendo reload mínimo
            const btnLimpar = document.getElementById('btnLimparFiltros');
            if (btnLimpar) {
                btnLimpar.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Limpeza via AJAX para evitar reload pesado
                    fetch('includes/limpar_filtros.php', { method: 'POST', credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(data => {
                            // Resetar form
                            filtrosForm.reset();
                            // Limpar parâmetros da URL mantendo apenas a rota base
                            const baseUrl = window.location.pathname;
                            window.history.replaceState({}, '', baseUrl);
                            // Recarregar tabela sem filtros via AJAX
                            const params = new URLSearchParams();
                            // Garantir página 1
                            params.set('pagina', '1');
                            carregarTabelaAjax(params);
                        })
                        .catch(() => {
                            // Fallback: navegação tradicional
                            window.location.href = btnLimpar.href;
                        });
                });
            }
            // Mudanças nos selects/inputs disparam debounce
            filtrosForm.querySelectorAll('select,input').forEach(el => {
                el.addEventListener('change', aplicarFiltrosDebounced);
                if (el.type === 'number' || el.tagName === 'INPUT') {
                    el.addEventListener('input', aplicarFiltrosDebounced);
                }
            });
        }

        function interceptarPaginacao() {
            // Remover listeners anteriores para evitar duplicação
            document.querySelectorAll('.paginacao-link').forEach(a => {
                // Clonar o elemento para remover todos os event listeners
                const newA = a.cloneNode(true);
                a.parentNode.replaceChild(newA, a);
                
                // Adicionar novo listener apenas se o AJAX estiver funcionando
                newA.addEventListener('click', function(e) {
                    const href = new URL(this.href, window.location.origin);
                    const params = new URLSearchParams(href.search);
                    
                    // Tentar AJAX primeiro
                    e.preventDefault();
                    const newUrl = window.location.pathname + '?' + params.toString();
                    window.history.pushState({}, '', newUrl);
                    
                    // Carregar tabela via AJAX com fallback para reload
                    carregarTabelaAjax(params).catch(() => {
                        // Se AJAX falhar, fazer reload completo
                        window.location.href = this.href;
                    });
                });
            });
        }

        // Inicial - apenas se o formulário existir (para evitar erros em páginas sem filtros)
        if (filtrosForm) {
            interceptarPaginacao();
        }
    } catch (e) {
        console.error('Erro inicializando filtros AJAX:', e);
    }
    console.log('=== VERIFICAÇÃO DE FUNÇÕES DE OBSERVAÇÕES ===');
    console.log('observacaoTipoAtual:', observacaoTipoAtual);
    console.log('observacaoIdentificadorAtual:', observacaoIdentificadorAtual);
    
    // Verificar se as funções estão definidas
    const funcoes = ['abrirObservacoes', 'fecharModalObservacoes', 'carregarObservacoes', 'adicionarObservacao', 'exibirObservacoes'];
    funcoes.forEach(funcao => {
        if (typeof window[funcao] === 'function') {
            console.log(`✅ ${funcao}: OK`);
        } else {
            console.error(`❌ ${funcao}: Não encontrada`);
        }
    });
    
    // Verificar se o modal existe
    const modal = document.getElementById('modalObservacoes');
    if (modal) {
        console.log('✅ Modal de observações encontrado');
    } else {
        console.error('❌ Modal de observações não encontrado');
    }
    
    console.log('=== FIM DA VERIFICAÇÃO ===');
});

// Exibir elementos adiados (defer-lcp) apenas após carregamento completo
window.addEventListener('load', function() {
    try {
        document.querySelectorAll('.defer-lcp').forEach(function(el) {
            el.classList.add('is-visible');
        });
    } catch (e) {
        // noop
    }
});


// Função para toggle das metas detalhadas
function toggleMetasDetalhes() {
    const content = document.getElementById('metas-content');
    const icon = document.getElementById('toggle-metas-icon');
    const text = document.getElementById('toggle-metas-text');
    const urlParams = new URLSearchParams(window.location.search);
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.className = 'fas fa-eye-slash';
        text.textContent = 'Ocultar';
        urlParams.set('show_metas', '1');
    } else {
        content.style.display = 'none';
        icon.className = 'fas fa-eye';
        text.textContent = 'Mostrar';
        urlParams.delete('show_metas');
    }
    
    // Atualizar a URL sem recarregar a página
    const newUrl = window.location.pathname + '?' + urlParams.toString();
    window.history.pushState({}, '', newUrl);
}

// Função para mudar a visão do diretor (AJAX)
function mudarVisao() {
    const select = document.getElementById('visao_supervisor');
    if (!select) return;
    const supervisorSelecionado = select.value;
    const url = new URL(window.location.href);
    const urlParams = url.searchParams;

    if (supervisorSelecionado) {
        urlParams.set('visao_supervisor', supervisorSelecionado);
    } else {
        urlParams.delete('visao_supervisor');
    }

    // Manter possível filtro de vendedor combinado com a supervisão

    // Garantir que metas fiquem expandidas
    urlParams.set('show_metas', '1');

    // Atualizar URL sem recarregar
    const newUrl = window.location.pathname + '?' + urlParams.toString();
    window.history.pushState({}, '', newUrl);

    // Se mudar supervisão, recarregar opções de vendedores daquele supervisor
    try {
        const vendedorSelect = document.getElementById('filtro_vendedor');
        if (vendedorSelect) {
            // loading nas opções
            vendedorSelect.innerHTML = '<option value="">Carregando...</option>';
            fetch('includes/buscar_vendedores.php?supervisor=' + encodeURIComponent(supervisorSelecionado))
                .then(r => r.json())
                .then(data => {
                    const opts = ['<option value="">Todos</option>'];
                    if (data && data.success && Array.isArray(data.vendedores)) {
                        data.vendedores.forEach(v => {
                            const cod = (v.COD_VENDEDOR || '').toString();
                            const nome = v.NOME_COMPLETO || cod;
                            opts.push(`<option value="${cod}">${nome}</option>`);
                        });
                    }
                    vendedorSelect.innerHTML = opts.join('');
                })
                .catch(() => {
                    vendedorSelect.innerHTML = '<option value="">Todos</option>';
                });
        }
    } catch (e) { /* noop */ }

    // Loading na tabela de clientes (overlay)
    try {
        const listContainer = document.getElementById('clientesListContainer');
        if (listContainer) {
            const target = listContainer.querySelector('.table-container') || listContainer;
            if (target && !target.querySelector('.table-loading-overlay')) {
                if (!target.style.position) {
                    target.dataset.prevPosition = target.style.position || '';
                    target.style.position = 'relative';
                }
                const overlay = document.createElement('div');
                overlay.className = 'table-loading-overlay';
                overlay.style.cssText = 'position:absolute;inset:0;background:rgba(255,255,255,0.75);display:flex;align-items:center;justify-content:center;z-index:10;backdrop-filter:saturate(120%) blur(1px);';
                overlay.innerHTML = '<div style="text-align:center;color:#1a237e;font-weight:600;"><i class="fas fa-spinner fa-spin" style="font-size:1.1rem;"></i> Carregando tabela...</div>';
                target.appendChild(overlay);
            }
        }
    } catch (e) { /* noop */ }

    // Atualizar clientes via AJAX
    const isTestePage = window.location.pathname.includes('carteira_teste_otimizada') || window.location.pathname.includes('carteira_admin_diretor_ultra');
    const ajaxFile = isTestePage ? '/Site/includes/ajax/carteira_teste_filtros_ajax.php' : '/Site/includes/carteira_filtros_ajax.php';
    fetch(ajaxFile + '?' + urlParams.toString())
        .then(response => response.json())
        .then(data => {
            if (data && data.success && typeof data.html === 'string') {
                const container = document.getElementById('clientesListContainer');
                if (container) {
                    container.outerHTML = data.html;
                    
                    // Reconfigurar paginação após atualização AJAX
                    setTimeout(() => {
                        interceptarPaginacao();
                    }, 100);
                }
            }
            // Remover overlay após finalizar
            try {
                const newContainer = document.getElementById('clientesListContainer');
                const target = newContainer ? (newContainer.querySelector('.table-container') || newContainer) : null;
                const overlay = target ? target.querySelector('.table-loading-overlay') : null;
                if (overlay) overlay.remove();
                if (target && target.dataset && 'prevPosition' in target.dataset) {
                    target.style.position = target.dataset.prevPosition;
                    delete target.dataset.prevPosition;
                }
            } catch (e) { /* noop */ }
        })
        .catch(err => console.error('Erro ao atualizar clientes:', err));

    // Loading na aba de metas
    try {
        const metasPane = document.getElementById('metas');
        if (metasPane) {
            metasPane.innerHTML = '<div class="metas-loading" style="text-align: center; padding: 1.5rem;"><i class="fas fa-spinner fa-spin"></i> Atualizando metas...</div>';
        }
    } catch (e) { /* noop */ }

    // Atualizar metas via AJAX
    fetch('includes/metas_detalhes_ajax.php?' + urlParams.toString())
        .then(response => response.text())
        .then(html => {
            const metasPane = document.getElementById('metas');
            if (metasPane && typeof html === 'string') {
                metasPane.innerHTML = html;
            }
        })
        .catch(err => console.error('Erro ao atualizar metas:', err));
}

// Funções de exclusão de clientes
function confirmarExclusaoCliente(cnpj, nomeCliente) {
    cnpjClienteParaExcluir = cnpj;
    document.getElementById('nomeCliente').textContent = nomeCliente;
    document.getElementById('modalConfirmacaoCliente').style.display = 'flex';
    // Garantir compatibilidade: salvar no dataset para outras implementações
    const modal = document.getElementById('modalConfirmacaoCliente');
    if (modal) {
        modal.dataset.clienteId = cnpj;
    }
    // Reset do campo de motivo
    document.getElementById('motivoExclusao').value = '';
    document.getElementById('motivoError').style.display = 'none';
    // Botão permanece habilitado para testes
}

function fecharModalCliente() {
    document.getElementById('modalConfirmacaoCliente').style.display = 'none';
    cnpjClienteParaExcluir = null;
    // Reset do campo de motivo
    document.getElementById('motivoExclusao').value = '';
    document.getElementById('motivoError').style.display = 'none';
    // Reset do campo de observações
    document.getElementById('observacaoExclusao').value = '';
    // Reset do contador e barra de progresso
    atualizarContadorCaracteres('observacaoExclusao', 'charCountCliente', 'charStatusCliente', 'progressBarCliente');
    // Mantém estado do botão
}

// Função para gerenciar contador de caracteres
function atualizarContadorCaracteres(textareaId, countId, statusId, progressId) {
    const textarea = document.getElementById(textareaId);
    const countElement = document.getElementById(countId);
    const statusElement = document.getElementById(statusId);
    const progressBar = document.getElementById(progressId);
    
    if (!textarea || !countElement || !statusElement || !progressBar) return;
    
    const text = textarea.value;
    const length = text.length;
    const maxLength = 1000;
    const minLength = 30;
    
    // Atualizar contador
    countElement.textContent = `${length} / ${maxLength}`;
    
    // Atualizar status e cores
    if (length === 0) {
        statusElement.textContent = 'Mínimo: 30 caracteres';
        statusElement.className = 'char-status';
        textarea.className = 'observacao-textarea';
        countElement.className = 'char-count';
    } else if (length < minLength) {
        statusElement.textContent = `Faltam ${minLength - length} caracteres`;
        statusElement.className = 'char-status error';
        textarea.className = 'observacao-textarea error';
        countElement.className = 'char-count minimum';
    } else if (length <= maxLength) {
        statusElement.textContent = 'Observação válida';
        statusElement.className = 'char-status valid';
        textarea.className = 'observacao-textarea success';
        countElement.className = 'char-count valid';
    } else {
        statusElement.textContent = 'Limite excedido';
        statusElement.className = 'char-status error';
        textarea.className = 'observacao-textarea error';
        countElement.className = 'char-count error';
    }
    
    // Atualizar barra de progresso
    const progress = Math.min((length / minLength) * 100, 100);
    progressBar.style.width = `${progress}%`;
    
    if (length >= minLength) {
        progressBar.className = 'observacao-progress-bar valid';
    } else if (length > 0) {
        progressBar.className = 'observacao-progress-bar';
    } else {
        progressBar.className = 'observacao-progress-bar';
    }
}

// Adicionar event listeners quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    const observacaoTextarea = document.getElementById('observacaoExclusao');
    if (observacaoTextarea) {
        observacaoTextarea.addEventListener('input', function() {
            atualizarContadorCaracteres('observacaoExclusao', 'charCountCliente', 'charStatusCliente', 'progressBarCliente');
        });
        
        observacaoTextarea.addEventListener('keyup', function() {
            atualizarContadorCaracteres('observacaoExclusao', 'charCountCliente', 'charStatusCliente', 'progressBarCliente');
        });
    }
});

function excluirCliente() {
    if (!cnpjClienteParaExcluir) {
        alert('Erro: CNPJ do cliente não encontrado');
        return;
    }
    
    // Validar motivo da exclusão
    const motivoExclusao = document.getElementById('motivoExclusao').value;
    const motivoError = document.getElementById('motivoError');
    
    if (!motivoExclusao) {
        motivoError.style.display = 'block';
        document.getElementById('motivoExclusao').focus();
        return;
    } else {
        motivoError.style.display = 'none';
    }
    
    // Validar observações se fornecidas
    const observacaoExclusao = document.getElementById('observacaoExclusao').value.trim();
    if (observacaoExclusao && observacaoExclusao.length < 30) {
        alert('As observações devem ter pelo menos 30 caracteres para fornecer contexto adequado.');
        document.getElementById('observacaoExclusao').focus();
        return;
    }
    
    // Show loading state
    const confirmButton = document.querySelector('.btn-confirmar');
    const originalText = confirmButton.textContent;
    confirmButton.textContent = 'Processando...';
    confirmButton.disabled = true;
    
    // Create form data
    const formData = new FormData();
    formData.append('cnpj', cnpjClienteParaExcluir);
    formData.append('motivo_exclusao', motivoExclusao);
    
    // Adicionar observação discursativa se fornecida
    if (observacaoExclusao) {
        formData.append('observacao_exclusao', observacaoExclusao);
    }
    
    // Campos de compatibilidade (algumas páginas podem enviar estes nomes)
    formData.append('cliente_id', cnpjClienteParaExcluir);
    formData.append('id', cnpjClienteParaExcluir);
    formData.append('motivo', motivoExclusao);
    
    // Make AJAX request
    console.log('Enviando exclusao:', {
        cnpj: cnpjClienteParaExcluir,
        motivo_exclusao: motivoExclusao,
        observacao_exclusao: observacaoExclusao
    });
    fetch('includes/excluir_cliente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Cliente movido para lixeira com sucesso!');
            window.location.reload();
        } else {
            alert('Erro ao mover cliente: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição');
    })
    .finally(() => {
        // Restore button state
        confirmButton.textContent = originalText;
        confirmButton.disabled = false;
        fecharModalCliente();
    });
}

// Funções de observações
function abrirObservacoes(tipo, identificador, titulo) {
    console.log('=== INÍCIO DA FUNÇÃO abrirObservacoes ===');
    console.log('Parâmetros recebidos:', { tipo, identificador, titulo });
    
    // Validar parâmetros
    if (!tipo || !identificador || !titulo) {
        console.error('Parâmetros inválidos:', { tipo, identificador, titulo });
        alert('Erro: Dados inválidos para abrir observações');
        return;
    }
    
    // Definir variáveis globais
    observacaoTipoAtual = tipo;
    observacaoIdentificadorAtual = identificador;
    
    console.log('Variáveis globais definidas:');
    console.log('- observacaoTipoAtual:', observacaoTipoAtual);
    console.log('- observacaoIdentificadorAtual:', observacaoIdentificadorAtual);
    
    // Atualizar elementos do DOM
    const tituloElement = document.getElementById('tituloObservacao');
    const modalElement = document.getElementById('modalObservacoes');
    const textareaElement = document.getElementById('novaObservacao');
    
    if (tituloElement) {
        tituloElement.textContent = titulo;
        console.log('Título atualizado:', titulo);
    } else {
        console.error('Elemento tituloObservacao não encontrado');
    }
    
    if (modalElement) {
        modalElement.style.display = 'flex';
        console.log('Modal exibido');
    } else {
        console.error('Elemento modalObservacoes não encontrado');
        alert('Erro: Modal de observações não encontrado');
        return;
    }
    
    if (textareaElement) {
        textareaElement.value = '';
        console.log('Textarea limpa');
    } else {
        console.error('Elemento novaObservacao não encontrado');
    }
    
    // Carregar observações existentes
    console.log('Chamando carregarObservacoes...');
    carregarObservacoes();
    
    console.log('=== FIM DA FUNÇÃO abrirObservacoes ===');
}

function fecharModalObservacoes() {
    document.getElementById('modalObservacoes').style.display = 'none';
    observacaoTipoAtual = '';
    observacaoIdentificadorAtual = '';
}

function carregarObservacoes() {
    console.log('Carregando observações...');
    console.log('Tipo:', observacaoTipoAtual);
    console.log('Identificador:', observacaoIdentificadorAtual);
    
    const formData = new FormData();
    formData.append('action', 'listar');
    formData.append('tipo', observacaoTipoAtual);
    formData.append('identificador', observacaoIdentificadorAtual);
    
    fetch('/Site/includes/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Verificar se a resposta é JSON antes de tentar fazer parse
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text.substring(0, 500));
                throw new Error('Resposta do servidor não é JSON. Pode ser página de erro ou login.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            exibirObservacoes(data.observacoes);
        } else {
            console.error('Erro ao carregar observações:', data.message);
            mostrarNotificacao(data.message || 'Erro ao carregar observações', 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        mostrarNotificacao('Erro ao processar a requisição: ' + error.message, 'error');
    });
}

function exibirObservacoes(observacoes) {
    const container = document.getElementById('listaObservacoes');
    
    if (observacoes.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem 1rem; color: #6c757d;">
                <i class="fas fa-comment-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-style: italic; margin: 0;">Nenhuma observação encontrada.</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.7;">Seja o primeiro a adicionar uma observação!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = observacoes.map(obs => `
        <div class="observacao-item" data-id="${obs.id}">
            <div class="observacao-header">
                <div class="observacao-info">
                    <span class="observacao-autor">
                        <i class="fas fa-user-circle" style="margin-right: 0.5rem; color: #007bff;"></i>
                        ${obs.usuario_nome}
                    </span>
                    <span class="observacao-data">
                        <i class="fas fa-clock" style="margin-right: 0.3rem; color: #6c757d;"></i>
                        ${obs.data_formatada}
                    </span>
                </div>
                <div class="observacao-acoes">
                    <button class="btn-observacao btn-observacao-editar" onclick="editarObservacao(${obs.id}, '${obs.observacao.replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" title="Editar observação">
                        <i class="fas fa-edit"></i>
                        <span class="btn-text">Editar</span>
                    </button>
                    <button class="btn-observacao btn-observacao-excluir" onclick="excluirObservacao(${obs.id})" title="Excluir observação">
                        <i class="fas fa-trash"></i>
                        <span class="btn-text">Excluir</span>
                    </button>
                </div>
            </div>
            <div class="observacao-texto">${obs.observacao}</div>
        </div>
    `).join('');
}

function adicionarObservacao() {
    console.log('=== INÍCIO DA FUNÇÃO adicionarObservacao ===');
    
    const texto = document.getElementById('novaObservacao').value.trim();
    console.log('Texto da observação:', texto);
    
    if (!texto) {
        mostrarNotificacao('Digite uma observação', 'warning');
        console.log('Texto vazio - função interrompida');
        return;
    }
    
    console.log('Variáveis globais:');
    console.log('- observacaoTipoAtual:', observacaoTipoAtual);
    console.log('- observacaoIdentificadorAtual:', observacaoIdentificadorAtual);
    
    // Validar se as variáveis estão definidas
    if (!observacaoTipoAtual || !observacaoIdentificadorAtual) {
        console.error('Variáveis de observação não definidas!');
        mostrarNotificacao('Erro: Dados da observação não encontrados. Tente novamente.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'adicionar');
    formData.append('tipo', observacaoTipoAtual);
    formData.append('identificador', observacaoIdentificadorAtual);
    formData.append('observacao', texto);
    
    console.log('FormData criado:', {
        action: 'adicionar',
        tipo: observacaoTipoAtual,
        identificador: observacaoIdentificadorAtual,
        observacao: texto
    });
    
    console.log('Enviando requisição para gerenciar_observacoes.php...');
    
    fetch('/Site/includes/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Verificar se a resposta é JSON antes de tentar fazer parse
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text.substring(0, 500));
                throw new Error('Resposta do servidor não é JSON. Pode ser página de erro ou login.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            console.log('✅ Observação adicionada com sucesso!');
            document.getElementById('novaObservacao').value = '';
            carregarObservacoes();
            mostrarNotificacao('Observação adicionada com sucesso!', 'success');
        } else {
            console.error('❌ Erro ao adicionar observação:', data.message);
            mostrarNotificacao('Erro ao adicionar observação: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        mostrarNotificacao('Erro ao processar a requisição: ' + error.message, 'error');
    })
    .finally(() => {
        console.log('=== FIM DA FUNÇÃO adicionarObservacao ===');
    });
}

function editarObservacao(id, textoOriginal) {
    const item = document.querySelector(`[data-id="${id}"]`);
    const textoDiv = item.querySelector('.observacao-texto');
    const acoesDiv = item.querySelector('.observacao-acoes');
    
    item.classList.add('observacao-editando');
    textoDiv.innerHTML = `
        <textarea class="observacao-textarea-edit" style="width: 100%; min-height: 80px; padding: 12px; border: 2px solid #17a2b8; border-radius: 8px; font-family: inherit; font-size: 0.95rem; resize: vertical; background: #fff; box-shadow: 0 2px 8px rgba(23, 162, 184, 0.1);">${textoOriginal}</textarea>
    `;
    
    acoesDiv.innerHTML = `
        <button class="btn-observacao btn-observacao-salvar" onclick="salvarObservacao(${id})" title="Salvar alterações">
            <i class="fas fa-save"></i>
            <span class="btn-text">Salvar</span>
        </button>
        <button class="btn-observacao btn-observacao-cancelar" onclick="cancelarEdicao(${id}, '${textoOriginal.replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" title="Cancelar edição">
            <i class="fas fa-times"></i>
            <span class="btn-text">Cancelar</span>
        </button>
    `;
    
    // Focar no textarea
    const textarea = item.querySelector('textarea');
    if (textarea) {
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
    }
}

function salvarObservacao(id) {
    const item = document.querySelector(`[data-id="${id}"]`);
    const textarea = item.querySelector('textarea');
    const novoTexto = textarea.value.trim();
    
    if (!novoTexto) {
        mostrarNotificacao('A observação não pode estar vazia', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'editar');
    formData.append('id', id);
    formData.append('observacao', novoTexto);
    
    fetch('/Site/includes/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarObservacoes();
            mostrarNotificacao('Observação editada com sucesso!', 'success');
        } else {
            mostrarNotificacao('Erro ao editar observação: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao processar a requisição', 'error');
    });
}

function cancelarEdicao(id, textoOriginal) {
    const item = document.querySelector(`[data-id="${id}"]`);
    const textoDiv = item.querySelector('.observacao-texto');
    const acoesDiv = item.querySelector('.observacao-acoes');
    
    item.classList.remove('observacao-editando');
    textoDiv.innerHTML = textoOriginal;
    
    acoesDiv.innerHTML = `
        <button class="btn-observacao btn-observacao-editar" onclick="editarObservacao(${id}, '${textoOriginal.replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" title="Editar observação">
            <i class="fas fa-edit"></i>
            <span class="btn-text">Editar</span>
        </button>
        <button class="btn-observacao btn-observacao-excluir" onclick="excluirObservacao(${id})" title="Excluir observação">
            <i class="fas fa-trash"></i>
            <span class="btn-text">Excluir</span>
        </button>
    `;
}

function excluirObservacao(id) {
    if (!confirm('Tem certeza que deseja excluir esta observação?')) {
        return;
    }
    const motivo = prompt('Informe o motivo da exclusão (obrigatório):');
    if (motivo === null) return;
    const motivoTrim = (motivo || '').trim();
    if (!motivoTrim) {
        alert('Motivo da exclusão é obrigatório.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'excluir');
    formData.append('id', id);
    formData.append('motivo_exclusao', motivoTrim);
    
    fetch('/Site/includes/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarObservacoes();
            mostrarNotificacao('Observação movida para lixeira com sucesso!', 'success');
        } else {
            mostrarNotificacao('Erro ao excluir observação: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao processar a requisição', 'error');
    });
}

// Funcionalidade de busca otimizada com AJAX para buscar em todos os clientes
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchClientes');
    const clientesTable = document.getElementById('clientesTable');
    function getClientesTable() { return document.getElementById('clientesTable'); }
    
    // Inicializar o select de visão com o valor correto
    const visaoSelect = document.getElementById('visao_supervisor');
    if (visaoSelect) {
        const urlParams = new URLSearchParams(window.location.search);
        const supervisorSelecionado = urlParams.get('visao_supervisor');
        
        if (supervisorSelecionado) {
            visaoSelect.value = supervisorSelecionado;
        }
    }
    
    if (searchInput && clientesTable) {
        let searchTimeout;
        let isLoading = false;
        
        // Função para mostrar loading
        function showLoading() {
            const table = getClientesTable();
            const tbody = table ? table.querySelector('tbody') : null;
            const mobileCardsContainer = document.querySelector('.mobile-cards-container');
            
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Buscando clientes...</td></tr>';
            }
            
            if (mobileCardsContainer) {
                mobileCardsContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: #6c757d;"><i class="fas fa-spinner fa-spin"></i> Buscando clientes...</div>';
            }
            
            // Mostrar indicador de busca
            const searchStatus = document.getElementById('searchStatus');
            const searchInput = document.getElementById('searchClientes');
            if (searchStatus) {
                searchStatus.style.display = 'flex';
            }
            if (searchInput) {
                searchInput.classList.add('loading');
            }
        }
        
        // Função para renderizar resultados da busca
        function renderSearchResults(clientes) {
            const table = getClientesTable();
            const tbody = table ? table.querySelector('tbody') : null;
            const mobileCardsContainer = document.querySelector('.mobile-cards-container');
            
            if (!tbody && !mobileCardsContainer) return;
            
            // Verificação de perfil removida - todos os usuários podem editar
            
            if (clientes.length === 0) {
                // Contar colunas dinamicamente baseado no cabeçalho da tabela
                const headerRow = document.querySelector('#clientesTable thead tr:last-child');
                const columnCount = headerRow ? headerRow.children.length : 11;
                
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="${columnCount}" style="text-align: center; padding: 2rem;"><i class="fas fa-search"></i> Nenhum cliente encontrado</td></tr>`;
                }
                
                if (mobileCardsContainer) {
                    mobileCardsContainer.innerHTML = `<div style="text-align: center; padding: 2rem; color: #6c757d;"><i class="fas fa-search"></i> Nenhum cliente encontrado</div>`;
                }
                return;
            }
            
            let html = '';
            let mobileHtml = '';
            
            clientes.forEach(cliente => {
                const rowClass = cliente.is_inativo ? 'cliente-inativo' : (cliente.is_inativando ? 'cliente-inativando' : '');
                
                // HTML da tabela desktop
                html += `
                    <tr class="${rowClass}" data-cliente="${escapeHtml(cliente.cliente)}" data-cnpj="${escapeHtml(cliente.cnpj_representativo)}" data-vendedor="${escapeHtml(cliente.nome_vendedor)}" data-cliente-id="${escapeHtml(cliente.cnpj_representativo)}">
                        <td>
                            <div class="cliente-nome">${escapeHtml(cliente.cliente)}</div>
                            <div class="cliente-fantasia">${escapeHtml(cliente.nome_fantasia)}</div>
                        </td>
                        <td>
                            ${escapeHtml(cliente.cnpj_representativo)}
                            ${cliente.total_cnpjs_diferentes > 1 ? `
                                <div class="cnpj-unificado-info">
                                    <i class="fas fa-link"></i> 
                                    ${cliente.total_cnpjs_diferentes} CNPJs unificados
                                </div>
                            ` : ''}
                        </td>
                        <td>${escapeHtml(cliente.estado)}</td>
                        <td>${escapeHtml(cliente.segmento_atuacao)}</td>
                        ${document.querySelector('th:nth-child(5)') && document.querySelector('th:nth-child(5)').textContent.trim() === 'Vendedor' ? `<td>${escapeHtml(cliente.nome_vendedor)}</td>` : ''}
                        ${document.querySelector('th:nth-child(6)') && document.querySelector('th:nth-child(6)').textContent.trim() === 'Supervisor' ? `<td>${escapeHtml(cliente.nome_supervisor)}</td>` : ''}
                        <td>
                            <span class="status-badge ${cliente.status_class}">
                                ${cliente.status_text}
                            </span>
                        </td>
                        <td>${cliente.ultima_compra || '-'}</td>
                        <td>${cliente.ultima_ligacao ? formatarDataLigacao(cliente.ultima_ligacao) : '-'}</td>
                        <td>
                            R$ ${(cliente.faturamento_mes || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </td>
                        <td>
                            <div class="table-actions">
                                <!-- Coluna Verde - Visualização e Contato -->
                                <div class="actions-column actions-green">
                                    ${cliente.ultima_compra && new Date(cliente.ultima_compra.split('/').reverse().join('-')) >= new Date('2025-01-01') ? 
                                        `<a href="/Site/includes/api/detalhes_cliente.php?cnpj=${encodeURIComponent(cliente.cnpj_representativo)}&from_optimized=${(window.location.pathname.includes('carteira_teste_otimizada.php') || window.location.pathname.includes('carteira_admin_diretor_ultra.php')) ? '1' : '0'}" class="btn btn-sm btn-eye" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>` : 
                                        `<span class="btn btn-eye btn-sm disabled" title="DT_FAT < 1º de janeiro de 2025 - botão desabilitado" style="cursor: not-allowed;">
                                            <i class="fas fa-eye-slash"></i>
                                        </span>`
                                    }
                                    
                                    ${cliente.telefone && cliente.telefone !== 'N/A' && cliente.telefone !== '(-) -' ? 
                                        `<button class="btn btn-sm btn-ligar" onclick="selecionarTipoContato('${escapeHtml(cliente.cnpj_representativo)}', '${escapeHtml(cliente.cliente)}', '${escapeHtml(cliente.telefone)}')" title="Iniciar Contato">
                                            <i class="fas fa-phone"></i>
                                        </button>` : 
                                        `<span class="btn btn-sm btn-ligar disabled" style="cursor: not-allowed;" title="Telefone não disponível">
                                            <i class="fas fa-phone"></i>
                                        </span>`
                                    }
                                    
                                    <button class="btn btn-success btn-sm" title="Agendar ligação"
                                            onclick="agendarLigacao('${escapeHtml(cliente.raiz_cnpj || cliente.cnpj_representativo)}', '${escapeHtml(cliente.cliente)}')">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                </div>
                                
                                <!-- Coluna Amarela - Observações e Exportação -->
                                <div class="actions-column actions-yellow">
                                    <button class="btn btn-warning btn-sm" title="Observações" 
                                            onclick="abrirObservacoes('cliente', '${escapeHtml(cliente.cnpj_representativo)}', '${escapeHtml(cliente.cliente)}')">
                                        <i class="fas fa-comment"></i>
                                    </button>
                                    
                                </div>
                                
                                <!-- Coluna Vermelha - Edição e Exclusão -->
                                <div class="actions-column actions-red">
                                    <button class="btn btn-danger btn-sm" title="Editar Cliente" 
                                            onclick="abrirEdicaoCliente('${cliente.cnpj_representativo}')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <a href="#" class="btn btn-danger btn-sm" title="Excluir Cliente" 
                                       onclick="confirmarExclusaoCliente('${escapeHtml(cliente.cnpj_representativo)}', '${escapeHtml(cliente.cliente)}')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
                
                // HTML dos cards mobile
                mobileHtml += `
                    <div class="cliente-card ${rowClass}" data-cliente="${escapeHtml(cliente.cliente)}" data-cnpj="${escapeHtml(cliente.cnpj_representativo)}">
                        <!-- Header do Card -->
                        <div class="cliente-card-header">
                            <div class="cliente-card-nome">
                                <h4>${escapeHtml(cliente.cliente)}</h4>
                                <span class="cnpj-badge">${escapeHtml(cliente.cnpj_representativo)}</span>
                                ${cliente.total_cnpjs_diferentes > 1 ? `<span class="cnpj-agregados-info"><i class="fas fa-link"></i> ${cliente.total_cnpjs_diferentes} CNPJs agregados</span>` : ''}
                            </div>
                            <p class="cliente-card-fantasia">${escapeHtml(cliente.nome_fantasia)}</p>
                            <div class="cliente-card-status">
                                <span class="status-badge ${cliente.is_inativo ? 'inativo' : (cliente.is_inativando ? 'inativando' : 'ativo')}">
                                    ${cliente.is_inativo ? 'Inativo' : (cliente.is_inativando ? 'Inativando' : 'Ativo')}
                                </span>
                                <span class="estado-badge">${escapeHtml(cliente.estado)}</span>
                            </div>
                        </div>
                        
                        <!-- Informações do Cliente -->
                        <div class="cliente-card-info">
                            <div class="cliente-info-grid">
                                <div class="cliente-info-item">
                                    <span class="cliente-info-label">Segmento</span>
                                    <span class="cliente-info-value">${escapeHtml(cliente.segmento_atuacao)}</span>
                                </div>
                                ${document.querySelector('th:nth-child(5)') && document.querySelector('th:nth-child(5)').textContent.trim() === 'Vendedor' && cliente.nome_vendedor ? `<div class="cliente-info-item"><span class="cliente-info-label">Vendedor</span><span class="cliente-info-value">${escapeHtml(cliente.nome_vendedor)}</span></div>` : ''}
                                <div class="cliente-info-item">
                                    <span class="cliente-info-label">Últ. Compra</span>
                                    <span class="cliente-info-value data">${cliente.ultima_compra || '-'}</span>
                                </div>
                                <div class="cliente-info-item">
                                    <span class="cliente-info-label">Últ. Ligação</span>
                                    <span class="cliente-info-value data">${cliente.ultima_ligacao || '-'}</span>
                                </div>
                                <div class="cliente-info-item">
                                    <span class="cliente-info-label">Faturamento</span>
                                    <span class="cliente-info-value faturamento">R$ ${parseFloat(cliente.faturamento_mes || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ações do Card -->
                        <div class="cliente-card-actions">
                            <div class="cliente-actions-single-row">
                                <a href="/Site/includes/api/detalhes_cliente.php?cnpj=${encodeURIComponent(cliente.cnpj_representativo)}&from_optimized=1" class="btn btn-primary-action" title="Ver detalhes">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-primary-action" onclick="selecionarTipoContato('${escapeHtml(cliente.cnpj_representativo)}', '${escapeHtml(cliente.cliente)}', '${escapeHtml(cliente.telefone || '')}')" title="Iniciar Contato">
                                    <i class="fas fa-phone"></i>
                                </button>
                                <button class="btn btn-primary-action" title="Agendar ligação" onclick="agendarLigacao('${escapeHtml(cliente.raiz_cnpj || '')}', '${escapeHtml(cliente.cliente)}')">
                                    <i class="fas fa-calendar-plus"></i>
                                </button>
                                <button class="btn btn-warning-action" title="Observações" onclick="abrirObservacoes('cliente', '${escapeHtml(cliente.cnpj_representativo)}', '${escapeHtml(cliente.cliente)}')">
                                    <i class="fas fa-comment"></i>
                                </button>
                                <button class="btn btn-danger-action" title="Editar Cliente" onclick="abrirEdicaoCliente('${escapeHtml(cliente.cnpj_representativo)}'); return false;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="#" class="btn btn-danger-action" title="Excluir Cliente" onclick="confirmarExclusaoCliente('${escapeHtml(cliente.cnpj_representativo)}', '${escapeHtml(cliente.cliente)}')">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Inserir HTML na tabela desktop
            if (tbody) {
                tbody.innerHTML = html;
            }
            
            // Inserir HTML nos cards mobile
            if (mobileCardsContainer) {
                mobileCardsContainer.innerHTML = mobileHtml;
            }
        }
        
        // Função para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Função para formatar data de ligação
        function formatarDataLigacao(dataString) {
            if (!dataString) return '-';
            
            try {
                // Se já estiver no formato brasileiro (dd/mm/yyyy HH:mm), retornar como está
                if (/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/.test(dataString)) {
                    return dataString;
                }
                
                // Tentar criar um objeto Date
                const data = new Date(dataString);
                
                // Verificar se a data é válida
                if (isNaN(data.getTime())) {
                    return '-';
                }
                
                // Formatar para o padrão brasileiro
                return data.toLocaleString('pt-BR', { 
                    day: '2-digit', 
                    month: '2-digit', 
                    year: 'numeric', 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
            } catch (error) {
                console.error('Erro ao formatar data de ligação:', error);
                return '-';
            }
        }
        
        // Função para buscar clientes via AJAX
        async function buscarClientes(termo) {
            if (isLoading) return;
            
            // Se o termo estiver vazio, restaurar a tabela original sem fazer requisição
            if (!termo || termo.trim() === '') {
                // Ocultar indicadores de busca
                const searchStatus = document.getElementById('searchStatus');
                const searchResultsInfo = document.getElementById('searchResultsInfo');
                const searchInput = document.getElementById('searchClientes');
                
                if (searchStatus) {
                    searchStatus.style.display = 'none';
                }
                if (searchResultsInfo) {
                    searchResultsInfo.style.display = 'none';
                }
                if (searchInput) {
                    searchInput.classList.remove('loading');
                }
                
                // Recarregar a página para mostrar a tabela original com a estrutura correta dos botões
                window.location.reload();
                return;
            }
            
            isLoading = true;
            showLoading();
            
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const params = new URLSearchParams({
                    termo: termo,
                    ...Object.fromEntries(urlParams)
                });
                
                const response = await fetch(`includes/buscar_clientes.php?${params.toString()}`);
                const data = await response.json();
                
                if (data.success) {
                    renderSearchResults(data.clientes);
                    
                    // Mostrar informações dos resultados
                    const searchResultsInfo = document.getElementById('searchResultsInfo');
                    const searchResultsText = document.getElementById('searchResultsText');
                    if (searchResultsInfo && searchResultsText) {
                        searchResultsText.textContent = `${data.total} cliente(s) encontrado(s) para "${termo}"`;
                        searchResultsInfo.style.display = 'flex';
                    }
                    
                    // Atualizar contador de resultados se existir
                    const filtrosCount = document.querySelector('.filtros-count');
                    if (filtrosCount) {
                        filtrosCount.textContent = `(${data.total} clientes encontrados)`;
                    }
                } else {
                    console.error('Erro na busca:', data.error);
                    const table = getClientesTable();
                    const tbody = table ? table.querySelector('tbody') : null;
                    if (tbody) {
                        const headerRow = document.querySelector('#clientesTable thead tr:last-child');
                        const columnCount = headerRow ? headerRow.children.length : 11;
                        tbody.innerHTML = `<tr><td colspan="${columnCount}" style="text-align: center; padding: 2rem; color: red;"><i class="fas fa-exclamation-triangle"></i> Erro ao buscar clientes</td></tr>`;
                    }
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                const table = getClientesTable();
                const tbody = table ? table.querySelector('tbody') : null;
                if (tbody) {
                    const headerRow = document.querySelector('#clientesTable thead tr:last-child');
                    const columnCount = headerRow ? headerRow.children.length : 11;
                    tbody.innerHTML = `<tr><td colspan="${columnCount}" style="text-align: center; padding: 2rem; color: red;"><i class="fas fa-exclamation-triangle"></i> Erro de conexão</td></tr>`;
                }
            } finally {
                isLoading = false;
                
                // Ocultar indicador de busca
                const searchStatus = document.getElementById('searchStatus');
                const searchInput = document.getElementById('searchClientes');
                if (searchStatus) {
                    searchStatus.style.display = 'none';
                }
                if (searchInput) {
                    searchInput.classList.remove('loading');
                }
            }
        }
        
        // Função para restaurar tabela original
        function restaurarTabelaOriginal() {
            // Recarregar a página para restaurar tanto a tabela quanto os cards mobile
            window.location.reload();
        }
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim();
            
            if (searchTerm.length === 0) {
                // Se o campo estiver vazio, restaurar tabela original
                searchTimeout = setTimeout(restaurarTabelaOriginal, 500);
                return;
            }
            
            // Se tiver pelo menos 2 caracteres, fazer busca
            if (searchTerm.length >= 2) {
                searchTimeout = setTimeout(() => {
                    buscarClientes(searchTerm);
                }, 300); // Debounce de 300ms
            }
        });
        
        // Adicionar evento para limpar busca
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                restaurarTabelaOriginal();
            }
        });
        
        // Evento para o botão "Limpar busca"
        const clearSearchBtn = document.getElementById('clearSearch');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                restaurarTabelaOriginal();
            });
        }
    }
    
    // Evento para esconder mensagem de erro quando selecionar um motivo
    const motivoSelect = document.getElementById('motivoExclusao');
    const motivoError = document.getElementById('motivoError');
    
    if (motivoSelect) {
        motivoSelect.addEventListener('change', function() {
            if (this.value) {
                // Esconder erro
                if (motivoError) {
                    motivoError.style.display = 'none';
                }
            }
        });
    }
});

// Função para detectar se é dispositivo móvel
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Função para fazer a ligação direta
function fazerLigacaoDireta(telefone) {
    if (!telefone || telefone.trim() === '') {
        console.log('Telefone vazio - não fazendo ligação direta');
        return;
    }
    
    let telefoneLimpo = telefone.replace(/[^\d]/g, '');
    
    // Para ligações interurbanas, adicionar 0 antes do DDD
    // Se o número tem 10 ou 11 dígitos (DDD + número), adicionar 0 na frente
    if (telefoneLimpo.length === 10 || telefoneLimpo.length === 11) {
        // Verificar se não começa com 0
        if (!telefoneLimpo.startsWith('0')) {
            telefoneLimpo = '0' + telefoneLimpo;
            console.log('Adicionado 0 para ligação interurbana:', telefoneLimpo);
        }
    }
    // Se tem apenas 8 ou 9 dígitos (número local sem DDD)
    else if (telefoneLimpo.length === 8 || telefoneLimpo.length === 9) {
        // Não precisa adicionar nada - é ligação local
        console.log('Ligação local detectada:', telefoneLimpo);
    }
    
    if (isMobileDevice()) {
        window.location.href = `tel:${telefoneLimpo}`;
    } else {
        try {
            window.location.href = `callto:${telefoneLimpo}`;
        } catch (e) {
            try {
                window.location.href = `sip:${telefoneLimpo}`;
            } catch (e2) {
                // Erro silencioso
            }
        }
    }
}

// Função para atualizar tempo
function atualizarTempo() {
    if (!tempoInicio) return;
    
    const agora = new Date();
    const diferenca = Math.floor((agora - tempoInicio) / 1000);
    const minutos = Math.floor(diferenca / 60);
    const segundos = diferenca % 60;
    
    const tempoFormatado = `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
    const tempoElement = document.getElementById('tempoLigacao');
    if (tempoElement) {
        tempoElement.textContent = tempoFormatado;
    }
}

// Função principal para iniciar ligação
function iniciarLigacao(clienteId, nomeCliente, telefone) {
    console.log('Iniciando ligação:', { clienteId, nomeCliente, telefone });
    
    if (!clienteId || clienteId.trim() === '' || clienteId === '-') {
        return;
    }
    
    if (!nomeCliente || nomeCliente.trim() === '' || nomeCliente === 'Cliente sem nome') {
        return;
    }
    
    if (!telefone || telefone.trim() === '' || telefone === 'N/A' || telefone === '(-) -') {
        telefone = '';
    }
    
    if (telefone && telefone.trim() !== '') {
        fazerLigacaoDireta(telefone);
    }
    
    ligacaoAtual = {
        cliente_id: clienteId,
        nome: nomeCliente,
        telefone: telefone
    };
    
    tempoInicio = new Date();
    atualizarTempo();
    timerInterval = setInterval(atualizarTempo, 1000);
    
    const formData = new FormData();
    formData.append('action', 'iniciar_ligacao');
    formData.append('cliente_id', clienteId);
    formData.append('telefone', telefone);
    
    console.log('Enviando requisição para iniciar ligação...');
    
    fetch('includes/gerenciar_ligacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            ligacaoAtual.id = data.ligacao_id;
            perguntasRoteiro = data.perguntas;
            exibirRoteiro();
        } else {
            // Erro silencioso
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        // Erro silencioso
    });
}

// Funções para o Roteiro de Ligação
function exibirRoteiro() {
    console.log('Exibindo roteiro para:', ligacaoAtual);
    
    const modal = document.getElementById('modalRoteiroLigacao');
    if (!modal) {
        console.error('Modal não encontrado!');
        return;
    }
    
    const nomeElement = document.getElementById('clienteInfo');
    const telefoneElement = document.getElementById('telefoneInfo');
    
    if (nomeElement) nomeElement.textContent = ligacaoAtual.nome;
    if (telefoneElement) telefoneElement.textContent = ligacaoAtual.telefone;
    
    const container = document.getElementById('perguntasContainer');
    container.innerHTML = '';
    
    const progressoHtml = `
        <div class="progresso-texto">
            Progresso: <span id="progressoTexto">0/${perguntasRoteiro.length}</span> perguntas respondidas
        </div>
        <div class="progresso-roteiro">
            <div class="progresso-barra" id="progressoBarra" style="width: 0%"></div>
        </div>
    `;
    container.innerHTML = progressoHtml;
    
    perguntasRoteiro.forEach((pergunta, index) => {
        const perguntaHtml = criarHtmlPergunta(pergunta, index);
        container.innerHTML += perguntaHtml;
    });
    
    aplicarCondicionalInicial();
    restaurarRespostas();
    atualizarProgresso();
    
    if (modal) {
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        console.log('Modal exibido com sucesso');
    } else {
        console.error('Modal não encontrado para exibir');
    }
}

function criarHtmlPergunta(pergunta, index) {
    const obrigatoria = pergunta.obrigatoria ? 'obrigatoria' : '';
    let opcoesHtml = '';
    
    if (pergunta.tipo === 'radio') {
        const opcoes = JSON.parse(pergunta.opcoes);
        opcoes.forEach((opcao, opcaoIndex) => {
            const temCamposAdicionais = pergunta.campos_condicionais && 
                pergunta.campos_condicionais[opcao];
            
            opcoesHtml += `
                <div class="opcao-item" onclick="selecionarOpcao(${pergunta.id}, '${opcao}', this)">
                    <input type="radio" name="pergunta_${pergunta.id}" id="opcao_${pergunta.id}_${opcaoIndex}" value="${opcao}">
                    <label for="opcao_${pergunta.id}_${opcaoIndex}">${opcao}</label>
                </div>
                ${temCamposAdicionais ? `
                    <div class="campos-adicionais" id="campos_${pergunta.id}_${opcaoIndex}" style="display: none;">
                        <div class="campos-adicionais-container">
                            <div class="campos-adicionais-header">
                                <i class="fas fa-info-circle"></i>
                                <span>Informações adicionais necessárias:</span>
                            </div>
                            <div class="campos-adicionais-content">
                                ${pergunta.campos_condicionais[opcao].map(campo => {
                                    if (campo.tipo === 'date') {
                                        return `
                                            <div class="campo-adicional-item">
                                                <label class="campo-adicional-label">${campo.label}</label>
                                                <input type="date" class="form-control campo-adicional-input" onchange="salvarCampoAdicional(${pergunta.id}, '${campo.nome}', this.value)">
                                            </div>`;
                                    } else if (campo.tipo === 'select') {
                                        return `
                                            <div class="campo-adicional-item">
                                                <label class="campo-adicional-label">${campo.label}</label>
                                                <select class="form-control campo-adicional-input" onchange="salvarCampoAdicional(${pergunta.id}, '${campo.nome}', this.value)">
                                                    <option value="">Selecione uma opção</option>
                                                    ${campo.opcoes.map(op => `<option value="${op}">${op}</option>`).join('')}
                                                </select>
                                            </div>`;
                                    } else {
                                        return `
                                            <div class="campo-adicional-item">
                                                <label class="campo-adicional-label">${campo.label}</label>
                                                <input type="text" class="form-control campo-adicional-input" placeholder="Digite aqui..." onchange="salvarCampoAdicional(${pergunta.id}, '${campo.nome}', this.value)">
                                            </div>`;
                                    }
                                }).join('')}
                            </div>
                            <div class="campos-adicionais-footer">
                                <button type="button" class="btn btn-primary btn-continuar" onclick="continuarAposCamposAdicionais(${pergunta.id}, '${opcao}')">
                                    <i class="fas fa-arrow-right"></i>
                                    Continuar
                                </button>
                            </div>
                        </div>
                    </div>
                ` : ''}
            `;
        });
    } else if (pergunta.tipo === 'text') {
        opcoesHtml = `<textarea class="pergunta-texto-livre" placeholder="Digite sua resposta..." onchange="salvarResposta(${pergunta.id}, this.value)"></textarea>`;
    } else if (pergunta.tipo === 'select') {
        const opcoes = JSON.parse(pergunta.opcoes);
        opcoesHtml = `<select class="pergunta-select" onchange="salvarResposta(${pergunta.id}, this.value)">
            <option value="">Selecione uma opção</option>
            ${opcoes.map(opcao => `<option value="${opcao}">${opcao}</option>`).join('')}
        </select>`;
    }
    
    return `
        <div class="pergunta-item ${obrigatoria}" data-pergunta-id="${pergunta.id}" data-condicional="${pergunta.condicional ? JSON.stringify(pergunta.condicional) : ''}">
            <div class="pergunta-header">
                <div class="pergunta-texto">${pergunta.pergunta}</div>
            </div>
            <div class="pergunta-opcoes">
                ${opcoesHtml}
            </div>
        </div>
    `;
}

function selecionarOpcao(perguntaId, valor, elemento) {
    console.log('selecionarOpcao chamada - Pergunta ID:', perguntaId, 'Valor:', valor);
    
    const perguntaItem = elemento.closest('.pergunta-item');
    perguntaItem.querySelectorAll('.opcao-item').forEach(item => {
        item.classList.remove('selecionada');
    });
    
    elemento.classList.add('selecionada');
    
    const opcaoIndex = Array.from(perguntaItem.querySelectorAll('.opcao-item')).indexOf(elemento);
    console.log('Índice da opção selecionada:', opcaoIndex);
    
    const camposAdicionais = perguntaItem.querySelector(`#campos_${perguntaId}_${opcaoIndex}`);
    console.log('Elemento campos adicionais encontrado:', camposAdicionais);
    
    if (camposAdicionais) {
        camposAdicionais.style.display = 'block';
        console.log('Campos adicionais exibidos para:', valor);
        return;
    } else {
        console.log('Nenhum campo adicional encontrado para:', valor);
    }
    
    perguntaItem.querySelectorAll('.campos-adicionais').forEach(campo => {
        if (campo !== camposAdicionais) {
            campo.style.display = 'none';
        }
    });
    
    salvarResposta(perguntaId, valor);
}

function salvarResposta(perguntaId, resposta) {
    console.log('Salvando resposta no backend:', perguntaId, resposta);
    
    if (!ligacaoAtual || !ligacaoAtual.id) {
        console.error('Ligação não iniciada');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'salvar_resposta');
    formData.append('ligacao_id', ligacaoAtual.id);
    formData.append('pergunta_id', perguntaId);
    formData.append('resposta', resposta);
    
    fetch('includes/gerenciar_ligacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Resposta salva com sucesso no backend');
            
            if (!ligacaoAtual.respostas) {
                ligacaoAtual.respostas = {};
            }
            ligacaoAtual.respostas[perguntaId] = resposta;
            
            verificarCondicional(perguntaId, resposta);
            
        } else {
            console.error('Erro ao salvar resposta:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao salvar resposta:', error);
    });
}

function verificarCondicional(perguntaId, valor) {
    console.log(`Verificando condicional: pergunta ${perguntaId}, valor "${valor}"`);
    
    if (!ligacaoAtual.respostas) {
        ligacaoAtual.respostas = {};
    }
    ligacaoAtual.respostas[perguntaId] = valor;
    
    const respostaPrimeira = ligacaoAtual.respostas[1];
    
    if (!respostaPrimeira) {
        console.log('Primeira pergunta ainda não respondida');
        return;
    }
    
    console.log('Fluxo ativo:', respostaPrimeira);
    
    perguntasRoteiro.forEach(pergunta => {
        const perguntaElement = document.querySelector(`[data-pergunta-id="${pergunta.id}"]`);
        if (perguntaElement) {
            perguntaElement.style.display = 'none';
        }
    });
    
    if (respostaPrimeira === 'Sim') {
        const perguntasSim = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        const ultimaRespondida = Math.max(...perguntasSim.filter(id => ligacaoAtual.respostas[id]));
        const proximaPergunta = ultimaRespondida + 1;
        
        if (proximaPergunta <= 9) {
            const perguntaElement = document.querySelector(`[data-pergunta-id="${proximaPergunta}"]`);
            if (perguntaElement) {
                perguntaElement.style.display = 'block';
                console.log(`Fluxo SIM - mostrando pergunta ${proximaPergunta}`);
            }
        } else {
            console.log('Fluxo SIM - todas as perguntas respondidas');
        }
        
    } else if (respostaPrimeira === 'Não') {
        const pergunta10 = document.querySelector(`[data-pergunta-id="10"]`);
        if (pergunta10) {
            pergunta10.style.display = 'block';
        }
        console.log('Fluxo NÃO ativo - pergunta 10 exibida');
    }
    
    atualizarProgresso();
}

function atualizarProgresso() {
    const respostas = ligacaoAtual.respostas || {};
    const respostaPrimeira = respostas[1];
    
    let totalPerguntasEsperadas = 0;
    let perguntasRespondidas = 0;
    
    if (respostaPrimeira) {
        console.log('Fluxo ativo:', respostaPrimeira);
        
        if (respostaPrimeira === 'Sim') {
            totalPerguntasEsperadas = 9;
            
            for (let i = 1; i <= 9; i++) {
                if (respostas[i]) {
                    perguntasRespondidas++;
                }
            }
        } else if (respostaPrimeira === 'Não') {
            totalPerguntasEsperadas = 2;
            
            if (respostas[1]) perguntasRespondidas++;
            if (respostas[10]) perguntasRespondidas++;
        }
    } else {
        totalPerguntasEsperadas = 1;
        perguntasRespondidas = 0;
    }
    
    const percentual = totalPerguntasEsperadas > 0 ? (perguntasRespondidas / totalPerguntasEsperadas) * 100 : 0;
    
    const progressoTexto = document.getElementById('progressoTexto');
    const progressoBarra = document.getElementById('progressoBarra');
    
    if (progressoTexto) {
        progressoTexto.textContent = `${perguntasRespondidas}/${totalPerguntasEsperadas}`;
    }
    if (progressoBarra) {
        progressoBarra.style.width = `${percentual}%`;
    }
    
    console.log('Progresso atualizado:', {
        totalPerguntasEsperadas: totalPerguntasEsperadas,
        perguntasRespondidas: perguntasRespondidas,
        percentual: percentual,
        respostas: respostas
    });
    
    const btnFinalizar = document.getElementById('btnFinalizarLigacao');
    if (btnFinalizar) {
        if (perguntasRespondidas >= totalPerguntasEsperadas && totalPerguntasEsperadas > 0) {
            btnFinalizar.disabled = false;
            console.log('✅ Botão de finalizar habilitado - todas as perguntas respondidas');
        } else {
            btnFinalizar.disabled = true;
            console.log('⏳ Botão de finalizar desabilitado - faltam respostas');
        }
    }
}

function salvarCampoAdicional(perguntaId, campo, valor) {
    if (!ligacaoAtual || !ligacaoAtual.id) {
        console.error('Ligação não iniciada');
        return;
    }
    
    const camposExistentes = JSON.parse(localStorage.getItem(`campos_adicional_${ligacaoAtual.id}_${perguntaId}`) || '{}');
    camposExistentes[campo] = valor;
    
    localStorage.setItem(`campos_adicional_${ligacaoAtual.id}_${perguntaId}`, JSON.stringify(camposExistentes));
    
    const formData = new FormData();
    formData.append('action', 'salvar_resposta');
    formData.append('ligacao_id', ligacaoAtual.id);
    formData.append('pergunta_id', perguntaId);
    formData.append('resposta', 'CAMPOS_ADICIONAIS');
    formData.append('campos_adicionais', JSON.stringify(camposExistentes));
    
    fetch('includes/gerenciar_ligacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Campo adicional salvo:', campo, valor);
        } else {
            console.error('Erro ao salvar campo adicional:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
    });
}

function continuarAposCamposAdicionais(perguntaId, valor) {
    console.log('Continuando após campos adicionais - Pergunta ID:', perguntaId, 'Valor:', valor);
    
    if (!ligacaoAtual || !ligacaoAtual.id) {
        console.error('Ligação não iniciada');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'salvar_resposta');
    formData.append('ligacao_id', ligacaoAtual.id);
    formData.append('pergunta_id', perguntaId);
    formData.append('resposta', valor);
    
    fetch('includes/gerenciar_ligacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Resposta salva com sucesso no backend');
            
            if (!ligacaoAtual.respostas) {
                ligacaoAtual.respostas = {};
            }
            ligacaoAtual.respostas[perguntaId] = valor;
            
            verificarCondicional(perguntaId, valor);
            
        } else {
            console.error('Erro ao salvar resposta:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao salvar resposta:', error);
    });
}

function aplicarCondicionalInicial() {
    console.log('Aplicando condicional inicial');
    
    perguntasRoteiro.forEach(pergunta => {
        const perguntaElement = document.querySelector(`[data-pergunta-id="${pergunta.id}"]`);
        if (perguntaElement) {
            if (pergunta.id == 1) {
                perguntaElement.style.display = 'block';
            } else {
                perguntaElement.style.display = 'none';
            }
        }
    });
    
    if (!ligacaoAtual.respostas) {
        ligacaoAtual.respostas = {};
    }
    
    console.log('Condicional inicial aplicado - apenas pergunta 1 visível');
}

function restaurarRespostas() {
    console.log('Restaurando respostas salvas:', ligacaoAtual.respostas);
    
    if (!ligacaoAtual.respostas || Object.keys(ligacaoAtual.respostas).length === 0) {
        console.log('Nenhuma resposta para restaurar');
        return;
    }
    
    Object.keys(ligacaoAtual.respostas).forEach(perguntaId => {
        const resposta = ligacaoAtual.respostas[perguntaId];
        const perguntaElement = document.querySelector(`[data-pergunta-id="${perguntaId}"]`);
        
        if (perguntaElement) {
            const opcaoElement = perguntaElement.querySelector(`[onclick*="'${resposta}'"]`);
            if (opcaoElement) {
                opcaoElement.classList.add('selecionada');
            }
            
            const radioElement = perguntaElement.querySelector(`input[value="${resposta}"]`);
            if (radioElement) {
                radioElement.checked = true;
            }
            
            console.log(`Resposta restaurada: pergunta ${perguntaId} = "${resposta}"`);
        }
    });
    
    const respostaPrimeira = ligacaoAtual.respostas[1];
    if (respostaPrimeira) {
        verificarCondicional(1, respostaPrimeira);
    }
}

function finalizarLigacao() {
    console.log('Tentando finalizar ligação:', ligacaoAtual);
    
    if (!ligacaoAtual || !ligacaoAtual.id) {
        return;
    }
    
    if (ligacaoAtual.cancelada) {
        const confirmacao = confirm('Esta ligação foi cancelada. Deseja finalizar mesmo assim com as informações preenchidas?');
        if (!confirmacao) {
            return;
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'finalizar_ligacao');
    formData.append('ligacao_id', ligacaoAtual.id);
    formData.append('ligacao_cancelada', ligacaoAtual.cancelada ? '1' : '0');
    
    console.log('Enviando requisição para finalizar ligação...');
    
    fetch('includes/gerenciar_ligacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            fecharModalRoteiro();
            // Recarregar a página para mostrar a nova ordenação (clientes com ligação no final)
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            // Erro silencioso
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Erro silencioso
    });
}

function fecharModalRoteiro() {
    if (ligacaoAtual && ligacaoAtual.id && !ligacaoAtual.cancelada) {
        console.log('Cancelando ligação por fechamento do modal');
        cancelarLigacao('Fechamento do modal de questionário');
    }
    
    document.getElementById('modalRoteiroLigacao').style.display = 'none';
    
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    
    ligacaoAtual = null;
    perguntasRoteiro = [];
    tempoInicio = null;
    
    console.log('Modal fechado e timer parado');
}

function cancelarLigacao(motivo = 'Cancelamento pelo usuário') {
    if (!ligacaoAtual || !ligacaoAtual.id) {
        console.log('Nenhuma ligação ativa para cancelar');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'cancelar_ligacao');
    formData.append('ligacao_id', ligacaoAtual.id);
    formData.append('motivo_cancelamento', motivo);
    
    console.log('Cancelando ligação:', ligacaoAtual.id, 'Motivo:', motivo);
    
    fetch('includes/gerenciar_ligacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Ligação cancelada com sucesso');
            ligacaoAtual.cancelada = true;
        } else {
            console.error('Erro ao cancelar ligação:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao processar cancelamento:', error);
    });
}

function configurarDetecaoCancelamento() {
    window.addEventListener('beforeunload', function(e) {
        if (ligacaoAtual && ligacaoAtual.id) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'includes/gerenciar_ligacoes.php', false);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=cancelar_ligacao&ligacao_id=' + ligacaoAtual.id + '&motivo_cancelamento=Fechamento da aba/janela');
            
            console.log('Ligação cancelada por fechamento da aba');
        }
    });
    
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden' && ligacaoAtual && ligacaoAtual.id) {
            console.log('Usuário saiu da página - ligação pode ser cancelada');
        }
    });
    
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-roteiro-cancelar')) {
            if (ligacaoAtual && ligacaoAtual.id) {
                cancelarLigacao('Cancelamento manual pelo usuário');
            }
        }
    });
}

// Função para criar o gráfico de atividade da carteira
function criarGraficoAtividade() {
    console.log('Iniciando criação do gráfico de atividade...');
    
    const container = document.getElementById('grafico-atividade-container');
    if (!container) {
        console.error('Container do gráfico não encontrado');
        return;
    }
    
    console.log('Container encontrado:', container);
    
    // Verificar se Chart.js está disponível
    if (typeof Chart === 'undefined') {
        console.error('Chart.js não está carregado');
        container.innerHTML = `
            <div class="grafico-atividade-loading">
                <i class="fas fa-exclamation-triangle"></i>
                Erro: Chart.js não foi carregado
            </div>
        `;
        return;
    }
    
    console.log('Chart.js está disponível');
    
    // Mostrar loading
    container.innerHTML = `
        <div class="grafico-atividade-loading">
            <i class="fas fa-spinner"></i>
            Carregando gráfico de atividade...
        </div>
    `;
    
    console.log('Fazendo requisição para a API...');
    
    // Buscar dados do gráfico via AJAX
    fetch('includes/grafico_atividade_carteira.php')
        .then(response => {
            console.log('Resposta da API:', response.status, response.statusText);
            return response.json();
        })
        .then(data => {
            console.log('Dados recebidos:', data);
            if (data.success) {
                renderizarGraficoAtividade(data.dados);
            } else {
                console.error('Erro na API:', data.message);
                container.innerHTML = `
                    <div class="grafico-atividade-loading">
                        <i class="fas fa-exclamation-triangle"></i>
                        Erro ao carregar dados: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados do gráfico:', error);
            container.innerHTML = `
                <div class="grafico-atividade-loading">
                    <i class="fas fa-exclamation-triangle"></i>
                    Erro ao carregar dados do gráfico
                </div>
            `;
        });
}

// Função para renderizar o gráfico
function renderizarGraficoAtividade(dados) {
    console.log('Renderizando gráfico com dados:', dados);
    
    const container = document.getElementById('grafico-atividade-container');
    
    // Criar estrutura HTML do gráfico
    container.innerHTML = `
        <div class="grafico-atividade-titulo">
            <i class="fas fa-chart-pie"></i>
            Atividade da Carteira
        </div>
        <div class="grafico-atividade-canvas-container">
            <canvas id="graficoAtividadeCarteira" class="grafico-atividade-canvas"></canvas>
        </div>
        <div class="grafico-atividade-legenda" id="graficoAtividadeLegenda">
        </div>
    `;
    
    // Preparar dados para o gráfico
    const labels = [];
    const values = [];
    const colors = [];
    const legendItems = [];
    
    if (dados.ativo > 0) {
        labels.push('Ativos');
        values.push(dados.ativo);
        colors.push('#28a745');
        legendItems.push({
            label: 'Ativos',
            color: '#28a745',
            count: dados.ativo
        });
    }
    
    if (dados.inativando > 0) {
        labels.push('Inativando');
        values.push(dados.inativando);
        colors.push('#ffc107');
        legendItems.push({
            label: 'Inativando',
            color: '#ffc107',
            count: dados.inativando
        });
    }
    
    if (dados.inativo > 0) {
        labels.push('Inativos');
        values.push(dados.inativo);
        colors.push('#dc3545');
        legendItems.push({
            label: 'Inativos',
            color: '#dc3545',
            count: dados.inativo
        });
    }
    
    if (dados.sem_dados > 0) {
        labels.push('Sem Dados');
        values.push(dados.sem_dados);
        colors.push('#6c757d');
        legendItems.push({
            label: 'Sem Dados',
            color: '#6c757d',
            count: dados.sem_dados
        });
    }
    
    // Se não há dados, mostrar mensagem
    if (values.length === 0) {
        container.innerHTML = `
            <div class="grafico-atividade-titulo">
                <i class="fas fa-chart-pie"></i>
                Atividade da Carteira
            </div>
            <div class="grafico-atividade-loading">
                <i class="fas fa-info-circle"></i>
                Nenhum dado disponível para exibir
            </div>
        `;
        return;
    }
    
    // Criar legenda
    const legendContainer = document.getElementById('graficoAtividadeLegenda');
    legendContainer.innerHTML = legendItems.map(item => `
        <div class="grafico-atividade-legenda-item">
            <div class="grafico-atividade-legenda-cor" style="background-color: ${item.color};"></div>
            <span>${item.label} (${item.count})</span>
        </div>
    `).join('');
    
    // Criar gráfico
    const ctx = document.getElementById('graficoAtividadeCarteira');
    if (!ctx) {
        console.error('Canvas do gráfico não encontrado');
        return;
    }
    
    try {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
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
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
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
                cutout: '60%'
            }
        });
        
        console.log('Gráfico criado com sucesso');
    } catch (error) {
        console.error('Erro ao criar gráfico:', error);
    }
}

// Função para atualizar o gráfico
function atualizarGraficoAtividade() {
    criarGraficoAtividade();
}

// Inicialização quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    configurarDetecaoCancelamento();
    
    // Event listeners para botões de ligar
    document.querySelectorAll('.btn-ligar').forEach(button => {
        button.addEventListener('click', function() {
            const cnpj = this.getAttribute('data-cnpj');
            const nome = this.getAttribute('data-nome');
            const telefone = this.getAttribute('data-telefone');
            iniciarLigacao(cnpj, nome, telefone);
        });
    });
    
    // Inicializar gráfico de atividade
    console.log('Inicializando gráfico de atividade...');
    criarGraficoAtividade();
    
    // Animar gráfico de status em barras
    animarGraficoStatus();
});

// Função para animar o gráfico de status em barras
function animarGraficoStatus() {
    const statusBars = document.querySelectorAll('.status-bar-fill');
    
    if (statusBars.length === 0) {
        console.log('Barras de status não encontradas');
        return;
    }
    
    // Verificar se os dados estão disponíveis
    if (typeof statusData === 'undefined') {
        console.log('Dados de status não encontrados');
        return;
    }
    
    console.log('Animando gráfico de status com dados:', statusData);
    
    // Animar cada barra
    statusBars.forEach((bar, index) => {
        const percent = bar.getAttribute('data-percent');
        if (percent && percent > 0) {
            setTimeout(() => {
                bar.style.width = percent + '%';
            }, index * 200); // Delay escalonado para cada barra
        }
    });
}

// Garantir que as funções estejam disponíveis globalmente
window.abrirObservacoes = window.abrirObservacoes || function(tipo, identificador, titulo) {
    console.log('=== INÍCIO DA FUNÇÃO abrirObservacoes ===');
    console.log('Parâmetros recebidos:', { tipo, identificador, titulo });
    
    // Validar parâmetros
    if (!tipo || !identificador || !titulo) {
        console.error('Parâmetros inválidos:', { tipo, identificador, titulo });
        alert('Erro: Dados inválidos para abrir observações');
        return;
    }
    
    // Definir variáveis globais
    observacaoTipoAtual = tipo;
    observacaoIdentificadorAtual = identificador;
    
    console.log('Variáveis globais definidas:');
    console.log('- observacaoTipoAtual:', observacaoTipoAtual);
    console.log('- observacaoIdentificadorAtual:', observacaoIdentificadorAtual);
    
    // Atualizar elementos do DOM
    const tituloElement = document.getElementById('tituloObservacao');
    const modalElement = document.getElementById('modalObservacoes');
    const textareaElement = document.getElementById('novaObservacao');
    
    if (tituloElement) {
        tituloElement.textContent = titulo;
        console.log('Título atualizado:', titulo);
    } else {
        console.error('Elemento tituloObservacao não encontrado');
    }
    
    if (modalElement) {
        modalElement.style.display = 'flex';
        console.log('Modal exibido');
    } else {
        console.error('Elemento modalObservacoes não encontrado');
        mostrarNotificacao('Erro: Modal de observações não encontrado', 'error');
        return;
    }
    
    if (textareaElement) {
        textareaElement.value = '';
        console.log('Textarea limpa');
    } else {
        console.error('Elemento novaObservacao não encontrado');
    }
    
    // Carregar observações existentes
    console.log('Chamando carregarObservacoes...');
    carregarObservacoes();
    
    console.log('=== FIM DA FUNÇÃO abrirObservacoes ===');
};

window.fecharModalObservacoes = window.fecharModalObservacoes || function() {
    document.getElementById('modalObservacoes').style.display = 'none';
    observacaoTipoAtual = '';
    observacaoIdentificadorAtual = '';
};

window.carregarObservacoes = window.carregarObservacoes || function() {
    console.log('Carregando observações...');
    
    const formData = new FormData();
    formData.append('action', 'listar');
    formData.append('tipo', observacaoTipoAtual);
    formData.append('identificador', observacaoIdentificadorAtual);
    
    // Usar API local para testes
    const apiUrl = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' 
        ? '../includes/gerenciar_observacoes_local.php' 
        : '../includes/gerenciar_observacoes.php';
    
    fetch(apiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            exibirObservacoes(data.observacoes);
            console.log('✅ Observações carregadas:', data.observacoes);
        } else {
            console.error('❌ Erro ao carregar observações:', data.message);
            mostrarNotificacao('Erro ao carregar observações: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        mostrarNotificacao('Erro na requisição: ' + error.message, 'error');
    });
};

window.adicionarObservacao = window.adicionarObservacao || function() {
    const texto = document.getElementById('novaObservacao').value.trim();
    
    if (!texto) {
        mostrarNotificacao('Digite uma observação', 'warning');
        return;
    }
    
    console.log('Adicionando observação...');
    
    const formData = new FormData();
    formData.append('action', 'adicionar');
    formData.append('tipo', observacaoTipoAtual);
    formData.append('identificador', observacaoIdentificadorAtual);
    formData.append('observacao', texto);
    
    // Usar API local para testes
    const apiUrl = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' 
        ? '../includes/gerenciar_observacoes_local.php' 
        : '../includes/gerenciar_observacoes.php';
    
    fetch(apiUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('novaObservacao').value = '';
            carregarObservacoes();
            console.log('✅ Observação adicionada com sucesso!');
        } else {
            console.error('❌ Erro ao adicionar observação:', data.message);
            mostrarNotificacao('Erro ao adicionar observação: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        mostrarNotificacao('Erro na requisição: ' + error.message, 'error');
    });
};

window.exibirObservacoes = window.exibirObservacoes || function(observacoes) {
    const container = document.getElementById('listaObservacoes');
    
    if (observacoes.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem 1rem; color: #6c757d;">
                <i class="fas fa-comment-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-style: italic; margin: 0;">Nenhuma observação encontrada.</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.7;">Seja o primeiro a adicionar uma observação!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = observacoes.map(obs => `
        <div class="observacao-item" data-id="${obs.id}">
            <div class="observacao-header">
                <span class="observacao-autor">${obs.usuario_nome}</span>
                <span class="observacao-data">
                    <i class="fas fa-clock" style="margin-right: 0.3rem;"></i>
                    ${obs.data_formatada}
                </span>
            </div>
            <div class="observacao-texto">${obs.observacao}</div>
            <div class="observacao-acoes">
                <button class="btn-observacao btn-observacao-editar" onclick="editarObservacao(${obs.id}, '${obs.observacao.replace(/'/g, "\\'")}')" title="Editar observação">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn-observacao btn-observacao-excluir" onclick="excluirObservacao(${obs.id})" title="Excluir observação">
                    <i class="fas fa-trash"></i> Excluir
                </button>
            </div>
        </div>
    `).join('');
};

// ===== FUNÇÕES PARA EDIÇÃO DE CLIENTES =====

// Variáveis para armazenar dados do cliente sendo editado
let cnpjOriginalCliente = '';

// Função para abrir o modal de edição de cliente
window.abrirEdicaoCliente = function(cnpj) {
    console.log('🚀 abrirEdicaoCliente chamada com CNPJ:', cnpj);
    console.log('🔍 Tipo do evento:', typeof event !== 'undefined' ? event.type : 'sem evento');
    
    // Prevenir comportamento padrão se for um evento
    if (typeof event !== 'undefined' && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
        console.log('✋ Evento padrão prevenido');
    }
    
    if (!cnpj || cnpj.trim() === '') {
        mostrarNotificacao('CNPJ do cliente não encontrado', 'error');
        return;
    }
    
    // Armazenar o CNPJ original
    cnpjOriginalCliente = cnpj;
    
    // Buscar dados do cliente
    fetch(`/Site/includes/ajax/editar_cliente_ajax.php?cnpj=${encodeURIComponent(cnpj)}`)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            preencherModalEdicaoCliente(data.cliente, data.segmentos);
            // Exibir o modal
            const modal = document.getElementById('modalEdicaoCliente');
            modal.style.display = 'flex';
            
            // Focar no primeiro campo
            document.getElementById('editClienteNome').focus();
        } else {
            mostrarNotificacao(data.message || 'Erro ao carregar dados do cliente', 'error');
        }
    })
    .catch(error => {
        console.error('Erro ao buscar dados do cliente:', error);
        mostrarNotificacao('Erro ao conectar com o servidor', 'error');
    });
};

// Função para preencher o modal com os dados do cliente
function preencherModalEdicaoCliente(cliente, segmentos) {
    console.log('Preenchendo modal com dados:', cliente);
    
    // Preencher os campos do formulário
    document.getElementById('editClienteNome').value = cliente.cliente || '';
    document.getElementById('editClienteFantasia').value = cliente.nome_fantasia || '';
    document.getElementById('editClienteContato').value = cliente.nome_contato || '';
    document.getElementById('editClienteTelefone').value = cliente.telefone || '';
    document.getElementById('editClienteEmail').value = cliente.email || '';
    document.getElementById('editClienteEndereco').value = cliente.endereco || '';
    document.getElementById('editClienteEstado').value = cliente.estado || '';
    
    // Preencher select de segmentos
    const segmentoSelect = document.getElementById('editClienteSegmento');
    segmentoSelect.innerHTML = '<option value="">Selecione o segmento</option>';
    
    segmentos.forEach(segmento => {
        const option = document.createElement('option');
        option.value = segmento;
        option.textContent = segmento;
        if (segmento === cliente.segmento) {
            option.selected = true;
        }
        segmentoSelect.appendChild(option);
    });
}

// Função para fechar o modal de edição de cliente
window.fecharModalEdicaoCliente = function() {
    const modal = document.getElementById('modalEdicaoCliente');
    modal.style.display = 'none';
    
    // Limpar o formulário
    document.getElementById('formEdicaoCliente').reset();
    cnpjOriginalCliente = '';
};

// Função para atualizar linha da tabela dinamicamente
function atualizarLinhaClienteNaTabela(cnpj, formData) {
    console.log('🔄 Atualizando linha da tabela para CNPJ:', cnpj);
    
    // Encontrar a linha da tabela com o CNPJ correspondente
    const linhaCliente = document.querySelector(`tr[data-cliente-id="${cnpj}"]`);
    
    if (linhaCliente) {
        console.log('✅ Linha encontrada, atualizando dados...');
        
        // Atualizar nome do cliente
        const nomeCliente = formData.get('cliente');
        if (nomeCliente) {
            const clienteNomeElement = linhaCliente.querySelector('.cliente-nome');
            if (clienteNomeElement) {
                clienteNomeElement.textContent = nomeCliente;
            }
        }
        
        // Atualizar nome fantasia
        const nomeFantasia = formData.get('nome_fantasia');
        if (nomeFantasia) {
            const clienteFantasiaElement = linhaCliente.querySelector('.cliente-fantasia');
            if (clienteFantasiaElement) {
                clienteFantasiaElement.textContent = nomeFantasia;
            }
        }
        
        // Atualizar estado
        const estado = formData.get('estado');
        if (estado) {
            const estadoBadge = linhaCliente.querySelector('.estado-badge');
            if (estadoBadge) {
                estadoBadge.textContent = estado;
            }
        }
        
        // Atualizar segmento
        const segmento = formData.get('segmento');
        if (segmento) {
            const segmentoInfo = linhaCliente.querySelector('.segmento-info');
            if (segmentoInfo) {
                segmentoInfo.textContent = segmento;
            }
        }
        
        // Adicionar indicador visual de que foi editado
        linhaCliente.style.backgroundColor = '#e8f5e8';
        setTimeout(() => {
            linhaCliente.style.backgroundColor = '';
        }, 2000);
        
        console.log('✅ Linha atualizada com sucesso');
    } else {
        console.warn('⚠️ Linha da tabela não encontrada para CNPJ:', cnpj);
    }
}

// Função para salvar as alterações do cliente
function salvarEdicaoCliente(formData) {
    console.log('Salvando edição de cliente');
    
    // Adicionar o CNPJ original aos dados
    formData.append('cnpj_original', cnpjOriginalCliente);
    
    // Mostrar loading no botão
    const submitBtn = document.querySelector('#formEdicaoCliente .btn-confirmar');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Salvando...';
    submitBtn.disabled = true;
    
    fetch('/Site/includes/ajax/editar_cliente_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            mostrarNotificacao('Cliente atualizado com sucesso!', 'success');
            window.fecharModalEdicaoCliente();
            
            // Tentar atualizar a linha da tabela dinamicamente primeiro
            atualizarLinhaClienteNaTabela(cnpjOriginalCliente, formData);
            
            // Também recarregar a página como backup (com delay maior)
            setTimeout(() => {
                // Adicionar timestamp para forçar reload
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('_refresh', Date.now());
                window.location.href = currentUrl.toString();
            }, 3000);
        } else {
            mostrarNotificacao(data.message || 'Erro ao atualizar cliente', 'error');
        }
    })
    .catch(error => {
        console.error('Erro ao salvar edição:', error);
        mostrarNotificacao('Erro ao conectar com o servidor', 'error');
    })
    .finally(() => {
        // Restaurar botão
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Event listeners para edição de cliente
document.addEventListener('DOMContentLoaded', function() {
    // Event listener para o formulário de edição de cliente
    const formEdicaoCliente = document.getElementById('formEdicaoCliente');
    if (formEdicaoCliente) {
        formEdicaoCliente.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            salvarEdicaoCliente(formData);
        });
    }
    
    // Event listener para fechar modal de edição ao clicar fora
    const modalEdicaoCliente = document.getElementById('modalEdicaoCliente');
    if (modalEdicaoCliente) {
        modalEdicaoCliente.addEventListener('click', function(e) {
            if (e.target === this) {
                window.fecharModalEdicaoCliente();
            }
        });
    }
});

// ===== FUNÇÕES PARA AGENDAMENTO DE LIGAÇÕES =====

// Variáveis para armazenar dados do agendamento
let cnpjClienteAgendamento = '';
let nomeClienteAgendamento = '';

// Função para abrir o modal de agendamento de ligação
window.agendarLigacao = function(cnpj, nomeCliente) {
    console.log('🚀 agendarLigacao chamada com:', { cnpj, nomeCliente });
    
    if (!cnpj || !nomeCliente) {
        mostrarNotificacao('Dados do cliente não encontrados', 'error');
        return;
    }
    
    // Armazenar dados do cliente
    cnpjClienteAgendamento = cnpj;
    nomeClienteAgendamento = nomeCliente;
    
    // Preencher o modal
    document.getElementById('agendamentoClienteNome').value = nomeCliente;
    document.getElementById('agendamentoClienteCnpj').value = cnpj;
    
    // Definir data mínima como hoje
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('agendamentoData').min = hoje;
    document.getElementById('agendamentoData').value = hoje;
    
    // Definir hora padrão (próxima hora)
    const agora = new Date();
    const proximaHora = new Date(agora.getTime() + 60 * 60 * 1000);
    const horaFormatada = proximaHora.toTimeString().slice(0, 5);
    document.getElementById('agendamentoHora').value = horaFormatada;
    
    // Limpar observações
    document.getElementById('agendamentoObservacoes').value = '';
    
    // Exibir o modal
    const modal = document.getElementById('modalAgendamentoCliente');
    modal.style.display = 'flex';
    
    // Focar no campo de data
    document.getElementById('agendamentoData').focus();
};

// Função para fechar o modal de agendamento
window.fecharModalAgendamentoCliente = function() {
    const modal = document.getElementById('modalAgendamentoCliente');
    modal.style.display = 'none';
    
    // Limpar dados
    cnpjClienteAgendamento = '';
    nomeClienteAgendamento = '';
    
    // Limpar formulário
    document.getElementById('formAgendamentoCliente').reset();
};

// Função para salvar o agendamento
function salvarAgendamentoCliente(formData) {
    console.log('Salvando agendamento:', formData);
    
    // Adicionar dados do cliente (usar 'cliente' como esperado pelo backend)
    formData.append('cliente', cnpjClienteAgendamento);
    formData.append('cliente_nome', nomeClienteAgendamento);
    formData.append('tipo', 'cliente');
    
    // Mostrar loading no botão
    const submitBtn = document.querySelector('#formAgendamentoCliente .btn-confirmar');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Agendando...';
    submitBtn.disabled = true;
    
    // Adicionar ação
    formData.append('acao', 'salvar_agendamento');
    
    fetch('/Site/includes/calendar/gerenciar_agendamentos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            mostrarNotificacao('Ligação agendada com sucesso!', 'success');
            window.fecharModalAgendamentoCliente();
            
            // Atualizar calendário se estiver visível
            if (typeof inicializarCalendario === 'function') {
                inicializarCalendario();
            }
        } else {
            mostrarNotificacao(data.message || 'Erro ao agendar ligação', 'error');
        }
    })
    .catch(error => {
        console.error('Erro ao salvar agendamento:', error);
        mostrarNotificacao('Erro ao conectar com o servidor', 'error');
    })
    .finally(() => {
        // Restaurar botão
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Event listeners para agendamento de cliente
document.addEventListener('DOMContentLoaded', function() {
    // Event listener para o formulário de agendamento
    const formAgendamentoCliente = document.getElementById('formAgendamentoCliente');
    if (formAgendamentoCliente) {
        formAgendamentoCliente.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar data e hora
            const data = document.getElementById('agendamentoData').value;
            const hora = document.getElementById('agendamentoHora').value;
            
            if (!data || !hora) {
                mostrarNotificacao('Por favor, preencha data e hora', 'warning');
                return;
            }
            
            // Verificar se a data não é no passado
            const dataHoraAgendamento = new Date(data + 'T' + hora);
            const agora = new Date();
            
            if (dataHoraAgendamento <= agora) {
                mostrarNotificacao('Não é possível agendar para datas/horários passados', 'warning');
                return;
            }
            
            const formData = new FormData(this);
            salvarAgendamentoCliente(formData);
        });
    }
    
    // Event listener para fechar modal de agendamento ao clicar fora
    const modalAgendamentoCliente = document.getElementById('modalAgendamentoCliente');
    if (modalAgendamentoCliente) {
        modalAgendamentoCliente.addEventListener('click', function(e) {
            if (e.target === this) {
                window.fecharModalAgendamentoCliente();
            }
        });
    }
});

// Log de confirmação de carregamento
console.log('✅ carteira.js carregado com sucesso!');
console.log('🔧 Funções disponíveis:', {
    abrirObservacoes: typeof window.abrirObservacoes,
    fecharModalObservacoes: typeof window.fecharModalObservacoes,
    carregarObservacoes: typeof window.carregarObservacoes,
    adicionarObservacao: typeof window.adicionarObservacao,
    exibirObservacoes: typeof window.exibirObservacoes,
    abrirEdicaoCliente: typeof window.abrirEdicaoCliente,
    fecharModalEdicaoCliente: typeof window.fecharModalEdicaoCliente,
    agendarLigacao: typeof window.agendarLigacao,
    fecharModalAgendamentoCliente: typeof window.fecharModalAgendamentoCliente
});

// Teste específico para função de edição de cliente
if (typeof window.abrirEdicaoCliente === 'function') {
    console.log('🟢 Função abrirEdicaoCliente carregada corretamente');
} else {
    console.error('🔴 Função abrirEdicaoCliente NÃO foi carregada');
}

// Adicionar função de teste global para debug
window.testarEdicaoCliente = function() {
    console.log('🧪 Testando função de edição de cliente...');
    if (typeof window.abrirEdicaoCliente === 'function') {
        console.log('✅ Função disponível, chamando com CNPJ de teste...');
        window.abrirEdicaoCliente('12345678000123');
    } else {
        console.error('❌ Função não está disponível');
    }
};

// Função para reposicionar o badge do total de faturamento
function reposicionarBadgeFaturamento() { /* badge removido */ }

// Executar quando a página carregar
document.addEventListener('DOMContentLoaded', function() { /* sem badge */ });

// Popular vendedores conforme supervisão na carga inicial
document.addEventListener('DOMContentLoaded', function() {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const sup = urlParams.get('visao_supervisor') || '';
        const vendedorSelect = document.getElementById('filtro_vendedor');
        if (!vendedorSelect) return;
        const vendedorAtual = urlParams.get('filtro_vendedor') || '';
        vendedorSelect.innerHTML = '<option value="">Carregando...</option>';
        const endpoint = 'includes/buscar_vendedores.php' + (sup ? ('?supervisor=' + encodeURIComponent(sup)) : '');
        fetch(endpoint)
            .then(r => r.json())
            .then(data => {
                const opts = ['<option value="">Todos</option>'];
                if (data && data.success && Array.isArray(data.vendedores)) {
                    data.vendedores.forEach(v => {
                        const cod = (v.COD_VENDEDOR || '').toString();
                        const nome = v.NOME_COMPLETO || cod;
                        const selected = (vendedorAtual && vendedorAtual === cod) ? ' selected' : '';
                        opts.push(`<option value="${cod}"${selected}>${nome}</option>`);
                    });
                }
                vendedorSelect.innerHTML = opts.join('');
            })
            .catch(() => {
                // fallback
                vendedorSelect.innerHTML = '<option value="">Todos</option>';
            });
    } catch (e) { /* noop */ }
});

// ===== Limpar filtros via AJAX (sem reload) =====
document.addEventListener('DOMContentLoaded', function() {
    const btnLimpar = document.getElementById('btnLimparFiltros');
    if (!btnLimpar) return;
    btnLimpar.addEventListener('click', function(e) {
        e.preventDefault();

        // Desabilitar botão e indicar processamento
        const originalText = btnLimpar.innerHTML;
        btnLimpar.classList.add('disabled');
        btnLimpar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Limpando...';

        // Limpar campos de filtro no formulário
        const idsParaLimpar = ['filtro_inatividade','filtro_estado','filtro_vendedor','filtro_segmento','filtro_ano','filtro_mes'];
        idsParaLimpar.forEach(function(id) {
            const el = document.getElementById(id);
            if (el) { el.value = ''; }
        });

        // Limpar busca rápida
        const searchInput = document.getElementById('searchClientes');
        if (searchInput) {
            searchInput.value = '';
        }

        // Atualizar URL removendo parâmetros de filtros e paginação
        const url = new URL(window.location.href);
        const params = url.searchParams;
        ['filtro_inatividade','filtro_estado','filtro_vendedor','filtro_segmento','filtro_ano','filtro_mes','pagina','pagina_metas','janela']
            .forEach(k => params.delete(k));
        // Manter visao_supervisor se houver e garantir metas expandidas
        params.set('show_metas', '1');
        window.history.pushState({}, '', window.location.pathname + '?' + params.toString());

        // Chamada para limpar sessão no backend
        const limparSessao = fetch('includes/limpar_filtros.php', { method: 'POST' })
            .then(r => r.json())
            .catch(() => ({ success: true }));

        // Mostrar loading na tabela
        try {
            const tbody = document.querySelector('#clientesTable tbody');
            if (tbody) {
                const headerRow = document.querySelector('#clientesTable thead tr:last-child');
                const columnCount = headerRow ? headerRow.children.length : 11;
                tbody.innerHTML = `<tr><td colspan="${columnCount}" style="text-align: center; padding: 1.5rem;"><i class=\"fas fa-spinner fa-spin\"></i> Atualizando...</td></tr>`;
            }
        } catch (e) { /* noop */ }

        // Atualizar clientes e metas em paralelo
        const isTestePage = window.location.pathname.includes('carteira_teste_otimizada') || window.location.pathname.includes('carteira_admin_diretor_ultra');
        const ajaxFile = isTestePage ? '/Site/includes/ajax/carteira_teste_filtros_ajax.php' : '/Site/includes/carteira_filtros_ajax.php';
        const atualizarClientes = fetch(ajaxFile + '?' + params.toString())
            .then(r => r.json());
        const atualizarMetas = fetch('includes/metas_detalhes_ajax.php?' + params.toString())
            .then(r => r.text());

        Promise.all([limparSessao, atualizarClientes, atualizarMetas])
            .then(([, dataClientes, htmlMetas]) => {
                if (dataClientes && dataClientes.success && typeof dataClientes.html === 'string') {
                    const container = document.getElementById('clientesListContainer');
                    if (container) {
                        container.outerHTML = dataClientes.html;
                    }
                    // Atualizar contador de filtros aplicados
                    const filtrosInfo = document.querySelector('.filtros-info');
                    if (filtrosInfo) {
                        const total = dataClientes.paginacao && typeof dataClientes.paginacao.total === 'number' ? dataClientes.paginacao.total : 0;
                        filtrosInfo.innerHTML = `<i class=\"fas fa-filter\"></i> <strong>Filtros aplicados:</strong> Nenhum <span class=\"filtros-count\">(${new Intl.NumberFormat('pt-BR').format(total)} clientes encontrados)</span>`;
                    }
                }
                const metasPane = document.getElementById('metas');
                if (metasPane && typeof htmlMetas === 'string') {
                    metasPane.innerHTML = htmlMetas;
                }
                if (typeof mostrarNotificacao === 'function') {
                    mostrarNotificacao('Filtros limpos com sucesso!', 'success');
                }

                // Repopular vendedores conforme supervisão atual (ou todos se vazio)
                const vendedorSelect = document.getElementById('filtro_vendedor');
                if (vendedorSelect) {
                    vendedorSelect.innerHTML = '<option value="">Carregando...</option>';
                    const sup = params.get('visao_supervisor') || '';
                    const endpoint = 'includes/buscar_vendedores.php' + (sup ? ('?supervisor=' + encodeURIComponent(sup)) : '');
                    fetch(endpoint)
                        .then(r => r.json())
                        .then(data => {
                            const opts = ['<option value="">Todos</option>'];
                            if (data && data.success && Array.isArray(data.vendedores)) {
                                data.vendedores.forEach(v => {
                                    const cod = (v.COD_VENDEDOR || '').toString();
                                    const nome = v.NOME_COMPLETO || cod;
                                    opts.push(`<option value="${cod}">${nome}</option>`);
                                });
                            }
                            vendedorSelect.innerHTML = opts.join('');
                        })
                        .catch(() => {
                            vendedorSelect.innerHTML = '<option value="">Todos</option>';
                        });
                }
            })
            .catch(err => {
                console.error('Erro ao limpar filtros:', err);
                if (typeof mostrarNotificacao === 'function') {
                    mostrarNotificacao('Erro ao limpar filtros', 'error');
                }
            })
            .finally(() => {
                btnLimpar.classList.remove('disabled');
                btnLimpar.innerHTML = originalText;
            });
    });
});

