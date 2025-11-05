// Contratos - Scripts Específicos

/**
 * Alternar visibilidade dos valores do contrato
 */
function toggleValores(button) {
    const card = button.closest('.contrato-card');
    const valores = card.querySelector('.contrato-valores');
    const progresso = card.querySelector('.progress-container');
    const icon = button.querySelector('i');
    const span = button.querySelector('span');
    
    // Verificar estado atual
    const isCollapsed = valores.classList.contains('collapsed');
    
    if (isCollapsed) {
        // Expandir
        valores.classList.remove('collapsed');
        valores.classList.add('expanded');
        progresso.classList.remove('collapsed');
        progresso.classList.add('expanded');
        button.classList.add('expanded');
        icon.style.transform = 'rotate(180deg)';
        span.textContent = 'Ocultar Valores';
    } else {
        // Recolher
        valores.classList.remove('expanded');
        valores.classList.add('collapsed');
        progresso.classList.remove('expanded');
        progresso.classList.add('collapsed');
        button.classList.remove('expanded');
        icon.style.transform = 'rotate(0deg)';
        span.textContent = 'Ver Valores';
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
    
    // Atualizar parâmetro mostrar_excluidas
    urlParams.set('mostrar_excluidas', mostrarExcluidas);
    
    // Construir nova URL
    const newUrl = window.location.pathname + '?' + urlParams.toString();
    
    // Redirecionar para nova URL
    window.location.href = newUrl;
}

/**
 * Excluir gerenciador (marcar todas as licitações como excluídas)
 */
async function excluirGerenciador(gerenciador) {
    // Confirmar exclusão
    if (!confirm(`Tem certeza que deseja excluir o gerenciador "${gerenciador}"?\n\nEsta ação irá:\n• Marcar TODAS as licitações deste gerenciador como excluídas (se existirem)\n• Desativar o gerenciador no sistema\n\nEsta ação não pode ser desfeita.`)) {
        return;
    }
    
    const form = new FormData();
    form.append('action', 'delete_gerenciador');
    form.append('gerenciador', gerenciador);
    
    try {
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('/Gestao/')) {
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Para produção, usar o caminho correto
            const pathParts = window.location.pathname.split('/');
            if (pathParts.includes('Site')) {
                baseUrl = window.location.origin + '/Site';
            } else {
                baseUrl = window.location.origin;
            }
        }
        
        const response = await fetch(`${baseUrl}/includes/ajax/excluir_gerenciador_ajax.php?t=${Date.now()}`, {
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
            // Recarregar página após exclusão com cache bust
            setTimeout(() => {
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('t', Date.now());
                window.location.href = currentUrl.toString();
            }, 1500);
        } else {
            mostrarNotificacao(data.message, 'error');
        }
    } catch (error) {
        console.error('Erro na exclusão:', error);
        mostrarNotificacao('Erro de conexão: ' + error.message, 'error');
    }
}

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
 * Ver detalhes de um gerenciador específico
 */
async function verDetalhes(gerenciador) {
    console.log('🔍 [DEBUG] Iniciando verDetalhes para gerenciador:', gerenciador);
    
    // Verificar se o gerenciador foi passado
    if (!gerenciador || gerenciador.trim() === '') {
        console.error('❌ [DEBUG] Gerenciador não informado ou vazio');
        mostrarNotificacao('Gerenciador não informado', 'error');
        return;
    }
    
    // Mostrar loading
    const btn = event.target.closest('.btn-detalhes');
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
        btn.disabled = true;
    }
    
    // Buscar o primeiro contrato do gerenciador para obter o ID
    try {
        // Verificar se deve mostrar excluídas baseado na URL atual
        const urlParams = new URLSearchParams(window.location.search);
        const mostrarExcluidas = urlParams.get('mostrar_excluidas') === '1' ? '1' : '0';
        
        // Debug: Log dos parâmetros
        console.log('🔍 [DEBUG] URL atual:', window.location.href);
        console.log('🔍 [DEBUG] Parâmetros URL:', urlParams.toString());
        console.log('🔍 [DEBUG] Mostrar excluídas:', mostrarExcluidas);
        
        // Usar window.baseUrl se disponível, caso contrário usar caminho relativo
        const url = window.baseUrl 
            ? `${window.baseUrl('includes/ajax/contratos_detalhes_ajax.php')}?gerenciador=${encodeURIComponent(gerenciador)}&mostrar_excluidas=${mostrarExcluidas}`
            : `includes/ajax/contratos_detalhes_ajax.php?gerenciador=${encodeURIComponent(gerenciador)}&mostrar_excluidas=${mostrarExcluidas}`;
        console.log('🌐 [DEBUG] Fazendo requisição para:', url);
        
        const response = await fetch(url);
        console.log('📡 [DEBUG] Resposta recebida. Status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 [DEBUG] Dados recebidos:', data);

        if (data.success && data.contratos && data.contratos.length > 0) {
            // Navegar para a página de detalhes do gerenciador
            console.log('✅ [DEBUG] Contratos encontrados:', data.contratos.length);
            
            const urlDetalhes = `${window.baseUrl('detalhes-gerenciador')}?gerenciador=${encodeURIComponent(gerenciador)}`;
            console.log('🔗 [DEBUG] Redirecionando para:', urlDetalhes);
            
            window.location.href = urlDetalhes;
        } else {
            console.log('⚠️ [DEBUG] Nenhum contrato encontrado. Resposta:', data);
            mostrarNotificacao(`Nenhum contrato encontrado para o gerenciador "${gerenciador}".`, 'error');
        }
    } catch (error) {
        console.error('💥 [DEBUG] Erro na requisição:', error);
        mostrarNotificacao('Erro ao carregar detalhes: ' + error.message, 'error');
    } finally {
        // Restaurar botão se ainda existir
        if (btn) {
            btn.innerHTML = '<i class="fas fa-eye"></i> Ver Detalhes';
            btn.disabled = false;
        }
    }
}

/**
 * Mostrar detalhes dos contratos no modal
 */
function mostrarDetalhes(contratos, gerenciador) {
    const modalBody = document.getElementById('modalBody');

    if (!contratos || contratos.length === 0) {
        modalBody.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-inbox" style="font-size: 2rem; color: #e0e0e0;"></i>
                <p style="margin-top: 1rem; color: #5f6368;">Nenhum contrato encontrado para ${gerenciador}</p>
            </div>
        `;
        return;
    }

    let html = `
        <div class="detalhes-container">
            <h3 style="color: #1a237e; margin-bottom: 1.5rem; font-size: 1.125rem;">
                Contratos de ${gerenciador}
            </h3>
            <div class="detalhes-tabela" style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
                    <thead>
                        <tr style="background: #f5f5f5; border-bottom: 2px solid #e0e0e0;">
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #202124; font-size: 0.875rem;">ID</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #202124; font-size: 0.875rem;">Empresa</th>
                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; color: #202124; font-size: 0.875rem;">Contrato</th>
                            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: #202124; font-size: 0.875rem;">Contratado</th>
                            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: #202124; font-size: 0.875rem;">Consumido</th>
                            <th style="padding: 0.75rem; text-align: right; font-weight: 600; color: #202124; font-size: 0.875rem;">Saldo</th>
                            <th style="padding: 0.75rem; text-align: center; font-weight: 600; color: #202124; font-size: 0.875rem;">%</th>
                            <th style="padding: 0.75rem; text-align: center; font-weight: 600; color: #202124; font-size: 0.875rem;">Vigência</th>
                            <th style="padding: 0.75rem; text-align: center; font-weight: 600; color: #202124; font-size: 0.875rem;">Status</th>
                            <th style="padding: 0.75rem; text-align: center; font-weight: 600; color: #202124; font-size: 0.875rem;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    contratos.forEach((contrato, index) => {
        const valorContratado = parseFloat(contrato.VALOR_CONTRATADO) || 0;
        const valorConsumido = parseFloat(contrato.VALOR_CONSUMIDO) || 0;
        const saldo = valorContratado - valorConsumido;
        const percentual = valorContratado > 0 ? (valorConsumido / valorContratado) * 100 : 0;

        const bgColor = index % 2 === 0 ? '#ffffff' : '#f9f9f9';
        const statusColor = percentual >= 90 ? '#f44336' : percentual >= 70 ? '#ff9800' : '#4caf50';

        // Formatar dados
        const empresa = contrato.razao_social || 'Não informado';
        const sigla = contrato.sigla || '';
        const siglaBadge = sigla ? `<span style="background: #e3f2fd; color: #1565c0; padding: 0.125rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">${sigla}</span>` : '';
        
        const numeroPregao = contrato.numero_pregao || '';
        const termoContrato = contrato.termo_contrato || '';
        
        // Informações do contrato
        let infoContrato = '';
        if (numeroPregao && termoContrato) {
            infoContrato = `Pregão: ${numeroPregao}<br>Termo: ${termoContrato}`;
        } else if (numeroPregao) {
            infoContrato = `Pregão: ${numeroPregao}`;
        } else if (termoContrato) {
            infoContrato = `Termo: ${termoContrato}`;
        } else {
            infoContrato = '-';
        }
        
        // Vigência
        const dataInicio = contrato.DATA_INICIO ? new Date(contrato.DATA_INICIO).toLocaleDateString('pt-BR') : '-';
        const dataFim = contrato.DATA_FIM ? new Date(contrato.DATA_FIM).toLocaleDateString('pt-BR') : '-';
        const vigencia = `${dataInicio}<br>até ${dataFim}`;
        
        // Status com cor
        const status = contrato.status || 'Vigente';
        const statusBgColor = status === 'Vigente' ? '#4caf50' : status === 'Finalizado' ? '#2196f3' : status === 'Suspenso' ? '#ff9800' : '#6c757d';
        
        // Botão de exclusão (apenas para admin/diretor)
        const botaoExcluir = contrato.PODE_EXCLUIR ? 
            `<button onclick="excluirContrato(${contrato.ID})" style="background: #dc3545; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;">
                <i class="fas fa-trash"></i> Excluir
            </button>` : '';
        
        html += `
            <tr style="background: ${bgColor}; border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 0.75rem; color: #5f6368; font-size: 0.875rem; font-weight: 600;">#${contrato.ID || '-'}</td>
                <td style="padding: 0.75rem; color: #202124; font-size: 0.875rem;">
                    ${siglaBadge}<br>
                    <strong>${empresa}</strong>
                </td>
                <td style="padding: 0.75rem; color: #5f6368; font-size: 0.875rem;">${infoContrato}</td>
                <td style="padding: 0.75rem; text-align: right; color: #202124; font-weight: 500; font-size: 0.875rem;">
                    R$ ${formatarMoeda(valorContratado)}
                </td>
                <td style="padding: 0.75rem; text-align: right; color: #f57c00; font-weight: 500; font-size: 0.875rem;">
                    R$ ${formatarMoeda(valorConsumido)}
                </td>
                <td style="padding: 0.75rem; text-align: right; color: #388e3c; font-weight: 500; font-size: 0.875rem;">
                    R$ ${formatarMoeda(saldo)}
                </td>
                <td style="padding: 0.75rem; text-align: center;">
                    <span style="background: ${statusColor}; color: #fff; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                        ${percentual.toFixed(1)}%
                    </span>
                </td>
                <td style="padding: 0.75rem; text-align: center; color: #5f6368; font-size: 0.875rem;">
                    ${vigencia}
                </td>
                <td style="padding: 0.75rem; text-align: center;">
                    <span style="background: ${statusBgColor}; color: #fff; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                        ${status}
                    </span>
                </td>
                <td style="padding: 0.75rem; text-align: center;">
                    ${botaoExcluir}
                </td>
            </tr>
        `;
    });

    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;

    modalBody.innerHTML = html;
}

/**
 * Fechar modal
 */
function fecharModal(modalId = 'modalDetalhes') {
    const modal = document.getElementById(modalId);
    
    // Para todos os modais, usar classe active
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    
    // Limpeza específica para modal de upload
    if (modalId === 'modalUpload') {
        document.getElementById('formUpload').reset();
        document.getElementById('selectedFiles').style.display = 'none';
        document.getElementById('btnUpload').disabled = true;
        document.getElementById('filesList').innerHTML = '';
    }
    
    // Limpar formulários
    if (modalId === 'modalContrato') {
        document.getElementById('formContrato').reset();
        document.getElementById('contrato_id').value = '';
    } else if (modalId === 'modalGerenciador') {
        document.getElementById('formGerenciador').reset();
        document.getElementById('gerenciador_id').value = '';
    }
}

/**
 * Formatar valor monetário
 */
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}


/**
 * Abrir modal de gerenciador
 */
function abrirModalGerenciador() {
    const modal = document.getElementById('modalGerenciador');
    const titulo = document.getElementById('modalGerenciadorTitulo');
    
    titulo.textContent = 'Novo Gerenciador';
    document.getElementById('formGerenciador').reset();
    document.getElementById('gerenciador_id').value = '';
    
    modal.classList.add('active');
    
    // Focar no campo de nome
    setTimeout(() => {
        document.getElementById('gerenciador_nome').focus();
    }, 100);
}

/**
 * Abrir modal de contrato
 */
async function abrirModalContrato(gerenciadorNome = '') {
    const modal = document.getElementById('modalContrato');
    const titulo = document.getElementById('modalContratoTitulo');
    const gerenciadorSelect = document.getElementById('contrato_gerenciador');
    
    titulo.textContent = 'Novo Contrato';
    document.getElementById('formContrato').reset();
    document.getElementById('contrato_id').value = '';
    
    // Carregar lista de gerenciadores
    await carregarGerenciadores();
    
    if (gerenciadorNome) {
        gerenciadorSelect.value = gerenciadorNome;
    }
    
    modal.classList.add('active');
}


/**
 * Carregar lista de gerenciadores
 */
async function carregarGerenciadores() {
    try {
        // Construir URL correta baseada no ambiente atual
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('/Site/')) {
            // Ambiente local de produção (/Site/)
            baseUrl = currentUrl.split('/Site/')[0] + '/Site';
        } else if (currentUrl.includes('/Gestao/')) {
            // Ambiente local de desenvolvimento (/Gestao/)
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Fallback - usar origem atual
            baseUrl = window.location.origin;
        }
        
        const response = await fetch(`${baseUrl}/includes/ajax/gerenciadores_crud.php?action=list`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('contrato_gerenciador');
            select.innerHTML = '<option value="">Selecione o gerenciador</option>';
            
            data.gerenciadores.forEach(gerenciador => {
                const option = document.createElement('option');
                option.value = gerenciador;
                option.textContent = gerenciador;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar gerenciadores:', error);
    }
}

/**
 * Carregar lista de gerenciadores para upload
 */
async function carregarGerenciadoresUpload() {
    try {
        // Construir URL correta baseada no ambiente atual
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('/Site/')) {
            // Ambiente local de produção (/Site/)
            baseUrl = currentUrl.split('/Site/')[0] + '/Site';
        } else if (currentUrl.includes('/Gestao/')) {
            // Ambiente local de desenvolvimento (/Gestao/)
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Fallback - usar origem atual
            baseUrl = window.location.origin;
        }
        
        const response = await fetch(`${baseUrl}/includes/ajax/gerenciadores_crud.php?action=list`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('upload_gerenciador');
            select.innerHTML = '<option value="">Selecione o gerenciador</option>';
            
            data.gerenciadores.forEach(gerenciador => {
                const option = document.createElement('option');
                option.value = gerenciador;
                option.textContent = gerenciador;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar gerenciadores para upload:', error);
    }
}


/**
 * Salvar gerenciador
 */
async function salvarGerenciador(formData) {
    const form = new FormData();
    
    // Determinar ação
    const action = formData.id ? 'update' : 'create';
    form.append('action', action);
    
    // Adicionar todos os campos
    Object.keys(formData).forEach(key => {
        if (formData[key] !== '') {
            form.append(key, formData[key]);
        }
    });
    
    try {
        // Construir URL correta baseada no ambiente atual
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('/Site/')) {
            // Ambiente local de produção (/Site/)
            baseUrl = currentUrl.split('/Site/')[0] + '/Site';
        } else if (currentUrl.includes('/Gestao/')) {
            // Ambiente local de desenvolvimento (/Gestao/)
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Fallback - usar origem atual
            baseUrl = window.location.origin;
        }
        
        const response = await fetch(`${baseUrl}/includes/ajax/gerenciadores_crud.php`, {
            method: 'POST',
            body: form
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        return { success: false, message: 'Erro de conexão: ' + error.message };
    }
}

/**
 * Salvar contrato
 */
async function salvarContrato(formData) {
    const form = new FormData();
    
    // Determinar ação
    const action = formData.id ? 'update' : 'create';
    form.append('action', action);
    
    // Adicionar todos os campos
    Object.keys(formData).forEach(key => {
        if (formData[key] !== '') {
            form.append(key, formData[key]);
        }
    });
    
    try {
        // Construir URL correta baseada no ambiente atual
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('/Site/')) {
            // Ambiente local de produção (/Site/)
            baseUrl = currentUrl.split('/Site/')[0] + '/Site';
        } else if (currentUrl.includes('/Gestao/')) {
            // Ambiente local de desenvolvimento (/Gestao/)
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Fallback - usar origem atual
            baseUrl = window.location.origin;
        }
        
        const response = await fetch(`${baseUrl}/includes/ajax/contratos_crud_licitacao.php`, {
            method: 'POST',
            body: form
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        return { success: false, message: 'Erro de conexão: ' + error.message };
    }
}

/**
 * Excluir contrato
 */
async function excluirContrato(contratoId) {
    // Confirmar exclusão
    if (!confirm('Tem certeza que deseja excluir este contrato?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', contratoId);
    
    try {
        // Construir URL correta baseada no ambiente atual
        const currentUrl = window.location.href;
        let baseUrl;
        
        if (currentUrl.includes('/Site/')) {
            // Ambiente local de produção (/Site/)
            baseUrl = currentUrl.split('/Site/')[0] + '/Site';
        } else if (currentUrl.includes('/Gestao/')) {
            // Ambiente local de desenvolvimento (/Gestao/)
            baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
        } else {
            // Fallback - usar origem atual
            baseUrl = window.location.origin;
        }
        
        const response = await fetch(`${baseUrl}/includes/ajax/contratos_crud_licitacao.php`, {
            method: 'POST',
            body: form
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            // Fechar modal de detalhes e recarregar página
            fecharModal('modalDetalhes');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacao(data.message, 'error');
        }
    } catch (error) {
        mostrarNotificacao('Erro de conexão: ' + error.message, 'error');
    }
}

/**
 * Ordenar cards por porcentagem de consumo (do mais verde para o mais vermelho)
 * Verde = bem consumido (100%) -> Vermelho = pouco consumido (0%)
 */
function ordenarCardsPorConsumo() {
    const grid = document.querySelector('.contratos-grid');
    if (!grid) return;
    
    const cards = Array.from(grid.querySelectorAll('.contrato-card'));
    
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
    // Fechar modais ao clicar fora
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                fecharModal(modal.id);
            }
        });
    });

    // Fechar modais com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.active');
            if (activeModal) {
                fecharModal(activeModal.id);
            }
        }
    });

    // Configurar estado inicial dos cards
    const cards = document.querySelectorAll('.contrato-card');
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


    // Event listener para formulário de gerenciador
    document.getElementById('formGerenciador').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        // Validar se o nome não está vazio
        if (!data.gerenciador || data.gerenciador.trim() === '') {
            mostrarNotificacao('Nome do gerenciador é obrigatório', 'error');
            return;
        }
        
        // Mostrar loading no botão
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        submitBtn.disabled = true;
        
        try {
            const result = await salvarGerenciador(data);
            
            if (result.success) {
                mostrarNotificacao(result.message, 'success');
                fecharModal('modalGerenciador');
                setTimeout(() => location.reload(), 1000);
            } else {
                mostrarNotificacao(result.message, 'error');
            }
        } catch (error) {
            mostrarNotificacao('Erro ao salvar gerenciador: ' + error.message, 'error');
        } finally {
            // Restaurar botão
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });

    // Event listener para formulário de contrato
    document.getElementById('formContrato').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const result = await salvarContrato(data);
        
        if (result.success) {
            mostrarNotificacao(result.message, 'success');
            fecharModal('modalContrato');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacao(result.message, 'error');
        }
    });
});

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
 * Filtrar contratos em tempo real
 */
function filtrarContratos() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const clearBtn = document.getElementById('clearSearchBtn');
    const resultsInfo = document.getElementById('searchResultsInfo');
    const resultsText = document.getElementById('searchResultsText');
    
    // Mostrar/ocultar botão de limpar
    if (searchTerm.length > 0) {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
        resultsInfo.style.display = 'none';
    }
    
    // Obter todos os cards de contrato
    const cards = document.querySelectorAll('.contrato-card');
    let visibleCount = 0;
    let totalCount = cards.length;
    
    cards.forEach(card => {
        const gerenciadorNome = card.querySelector('.gerenciador-nome').textContent.toLowerCase();
        const orgaosInfo = card.querySelector('.orgaos-content');
        const orgaosText = orgaosInfo ? orgaosInfo.textContent.toLowerCase() : '';
        
        // Verificar se o termo de busca está presente
        const matches = gerenciadorNome.includes(searchTerm) || orgaosText.includes(searchTerm);
        
        if (matches) {
            card.classList.remove('search-hidden');
            card.classList.add('search-highlight');
            visibleCount++;
            
            // Remover highlight após animação
            setTimeout(() => {
                card.classList.remove('search-highlight');
            }, 500);
        } else {
            card.classList.add('search-hidden');
            card.classList.remove('search-highlight');
        }
    });
    
    // Mostrar informações dos resultados
    if (searchTerm.length > 0) {
        resultsInfo.style.display = 'flex';
        if (visibleCount === 0) {
            resultsText.innerHTML = `<i class="fas fa-search"></i> Nenhum resultado encontrado para "${searchTerm}"`;
        } else if (visibleCount === totalCount) {
            resultsText.innerHTML = `<i class="fas fa-check-circle"></i> Todos os ${totalCount} gerenciadores correspondem à busca`;
        } else {
            resultsText.innerHTML = `<i class="fas fa-filter"></i> ${visibleCount} de ${totalCount} gerenciadores correspondem à busca`;
        }
    }
    
    // Reordenar cards visíveis por porcentagem de consumo
    // (apenas se houver filtros aplicados)
    if (searchTerm.length > 0) {
        setTimeout(() => {
            ordenarCardsPorConsumo();
        }, 100);
    }
}

/**
 * Limpar pesquisa
 */
function limparPesquisa() {
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const resultsInfo = document.getElementById('searchResultsInfo');
    
    // Limpar campo de busca
    searchInput.value = '';
    
    // Ocultar botão de limpar
    clearBtn.style.display = 'none';
    
    // Ocultar informações de resultados
    resultsInfo.style.display = 'none';
    
    // Mostrar todos os cards
    const cards = document.querySelectorAll('.contrato-card');
    cards.forEach(card => {
        card.classList.remove('search-hidden', 'search-highlight');
    });
    
    // Reordenar cards por porcentagem de consumo
    // (apenas se houver filtros aplicados anteriormente)
    setTimeout(() => {
        ordenarCardsPorConsumo();
    }, 100);
    
    // Focar no campo de busca
    searchInput.focus();
}

/**
 * Atualização automática (opcional)
 * Descomente para ativar atualização automática a cada 5 minutos
 */
/*
setInterval(() => {
    console.log('Atualizando dados automaticamente...');
    location.reload();
}, 300000); // 5 minutos
*/

// ========================================
// FUNCIONALIDADES DE UPLOAD DE CONTRATOS
// ========================================

/**
 * Abrir modal de upload
 */
async function abrirModalUpload() {
    const modal = document.getElementById('modalUpload');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Resetar formulário
        document.getElementById('formUpload').reset();
        document.getElementById('selectedFiles').style.display = 'none';
        document.getElementById('btnUpload').disabled = true;
        document.getElementById('filesList').innerHTML = '';
        
        // Carregar lista de gerenciadores
        await carregarGerenciadoresUpload();
    }
}

/**
 * Fechar modal de upload
 */
function fecharModalUpload() {
    const modal = document.getElementById('modalUpload');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

/**
 * Configurar drag and drop
 */
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    
    if (uploadArea && fileInput) {
        // Prevenir comportamento padrão do drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        // Destacar área de drop
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        // Processar arquivos soltos
        uploadArea.addEventListener('drop', handleDrop, false);
        
        // Processar arquivos selecionados
        fileInput.addEventListener('change', handleFiles);
    }
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    document.getElementById('uploadArea').classList.add('drag-over');
}

function unhighlight(e) {
    document.getElementById('uploadArea').classList.remove('drag-over');
}

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles({ target: { files: files } });
}

function handleFiles(e) {
    const files = e.target.files;
    if (files.length > 0) {
        displaySelectedFiles(files);
        document.getElementById('btnUpload').disabled = false;
    }
}

/**
 * Exibir arquivos selecionados
 */
function displaySelectedFiles(files) {
    const selectedFilesDiv = document.getElementById('selectedFiles');
    const filesListDiv = document.getElementById('filesList');
    
    filesListDiv.innerHTML = '';
    
    Array.from(files).forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div class="file-info">
                <i class="fas fa-file"></i>
                <div class="file-details">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${formatFileSize(file.size)}</span>
                </div>
            </div>
            <button type="button" class="btn-remove-file" onclick="removeFile(${index})">
                <i class="fas fa-times"></i>
            </button>
        `;
        filesListDiv.appendChild(fileItem);
    });
    
    selectedFilesDiv.style.display = 'block';
}

/**
 * Remover arquivo da lista
 */
function removeFile(index) {
    const fileInput = document.getElementById('fileInput');
    const dt = new DataTransfer();
    
    Array.from(fileInput.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    fileInput.files = dt.files;
    
    if (fileInput.files.length === 0) {
        document.getElementById('selectedFiles').style.display = 'none';
        document.getElementById('btnUpload').disabled = true;
    } else {
        displaySelectedFiles(fileInput.files);
    }
}

/**
 * Formatar tamanho do arquivo
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Processar upload de arquivos
 */
document.getElementById('formUpload').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validar se gerenciador foi selecionado
    const gerenciador = document.getElementById('upload_gerenciador').value;
    if (!gerenciador) {
        showNotification('error', 'Por favor, selecione um gerenciador');
        return;
    }
    
    // Validar se há arquivos selecionados
    const fileInput = document.getElementById('fileInput');
    if (!fileInput.files || fileInput.files.length === 0) {
        showNotification('error', 'Por favor, selecione pelo menos um arquivo');
        return;
    }
    
    const formData = new FormData(this);
    const btnUpload = document.getElementById('btnUpload');
    const originalText = btnUpload.innerHTML;
    
    // Desabilitar botão e mostrar loading
    btnUpload.disabled = true;
    btnUpload.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    
    fetch('../includes/ajax/upload_contratos_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Sucesso
            showNotification('success', data.message);
            
            // Mostrar detalhes dos arquivos enviados
            if (data.uploaded_files && data.uploaded_files.length > 0) {
                let details = 'Arquivos enviados:\n';
                data.uploaded_files.forEach(file => {
                    details += `• ${file.original_name}\n`;
                });
                console.log(details);
            }
            
            // Fechar modal
            fecharModalUpload();
            
            // Opcional: recarregar página para mostrar novos arquivos
            // location.reload();
            
        } else {
            // Erro
            showNotification('error', data.message || 'Erro no upload');
            
            // Mostrar erros detalhados
            if (data.errors && data.errors.length > 0) {
                console.error('Erros detalhados:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        showNotification('error', 'Erro na comunicação com o servidor');
    })
    .finally(() => {
        // Restaurar botão
        btnUpload.disabled = false;
        btnUpload.innerHTML = originalText;
    });
});

/**
 * Mostrar notificação
 */
function showNotification(type, message) {
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Adicionar ao body
    document.body.appendChild(notification);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

/**
 * Ver contratos de um gerenciador
 */
async function verContratos(gerenciador) {
    console.log('🔍 [DEBUG] Carregando contratos para gerenciador:', gerenciador);
    
    // Verificar se o gerenciador foi passado
    if (!gerenciador || gerenciador.trim() === '') {
        console.error('❌ [DEBUG] Gerenciador não informado ou vazio');
        mostrarNotificacao('Gerenciador não informado', 'error');
        return;
    }
    
    // Abrir modal
    const modal = document.getElementById('modalContratos');
    const titulo = document.getElementById('modalContratosTitulo');
    const body = document.getElementById('modalContratosBody');
    
    titulo.textContent = `Contratos de ${gerenciador}`;
    modal.classList.add('active');
    
    // Mostrar loading
    body.innerHTML = `
        <div class="loading-container" style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #1a237e;"></i>
            <p style="margin-top: 1rem; color: #5f6368;">Carregando contratos...</p>
        </div>
    `;
    
    try {
        // Usar window.baseUrl se disponível, caso contrário usar caminho relativo
        const url = window.baseUrl 
            ? `${window.baseUrl('includes/ajax/listar_contratos_gerenciador_ajax.php')}?gerenciador=${encodeURIComponent(gerenciador)}`
            : `includes/ajax/listar_contratos_gerenciador_ajax.php?gerenciador=${encodeURIComponent(gerenciador)}`;
        console.log('🌐 [DEBUG] Fazendo requisição para:', url);
        
        const response = await fetch(url);
        console.log('📡 [DEBUG] Resposta recebida. Status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 [DEBUG] Dados recebidos:', data);

        if (data.success) {
            mostrarContratos(data.contratos, gerenciador);
        } else {
            body.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ff9800;"></i>
                    <p style="margin-top: 1rem; color: #5f6368;">Erro ao carregar contratos: ${data.message}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('💥 [DEBUG] Erro na requisição:', error);
        body.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-exclamation-circle" style="font-size: 2rem; color: #f44336;"></i>
                <p style="margin-top: 1rem; color: #5f6368;">Erro ao carregar contratos: ${error.message}</p>
            </div>
        `;
    }
}

/**
 * Mostrar lista de contratos no modal
 */
function mostrarContratos(contratos, gerenciador) {
    const body = document.getElementById('modalContratosBody');
    
    if (!contratos || contratos.length === 0) {
        body.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-folder-open" style="font-size: 2rem; color: #e0e0e0;"></i>
                <p style="margin-top: 1rem; color: #5f6368;">Nenhum contrato encontrado para ${gerenciador}</p>
                <p style="color: #9e9e9e; font-size: 0.9rem;">Use o botão "Upload Contratos" para adicionar arquivos</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="contratos-container">
            <div class="contratos-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #e0e0e0;">
                <h3 style="color: #1a237e; margin: 0; font-size: 1.125rem;">
                    <i class="fas fa-file-alt"></i> ${contratos.length} contrato(s) encontrado(s)
                </h3>
                <button class="btn-action btn-primary" onclick="abrirModalUploadComGerenciador('${gerenciador}')" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                    <i class="fas fa-plus"></i> Adicionar Contrato
                </button>
            </div>
            <div class="contratos-list">
    `;
    
    contratos.forEach((contrato, index) => {
        const bgColor = index % 2 === 0 ? '#ffffff' : '#f9f9f9';
        const iconClass = getFileIcon(contrato.file_extension);
        const fileSize = formatFileSize(contrato.file_size);
        const uploadDate = contrato.upload_date ? new Date(contrato.upload_date).toLocaleString('pt-BR') : 'Data não disponível';
        
        html += `
            <div class="contrato-item" style="background: ${bgColor}; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <div class="contrato-info" style="display: flex; align-items: center; flex: 1;">
                    <div class="file-icon" style="margin-right: 1rem; font-size: 1.5rem; color: #1a237e;">
                        <i class="${iconClass}"></i>
                    </div>
                    <div class="file-details">
                        <h4 style="margin: 0 0 0.25rem 0; color: #202124; font-size: 1rem; font-weight: 600;">
                            ${contrato.original_name}
                        </h4>
                        <p style="margin: 0; color: #5f6368; font-size: 0.875rem;">
                            <i class="fas fa-weight-hanging"></i> ${fileSize} • 
                            <i class="fas fa-calendar"></i> ${uploadDate}
                        </p>
                    </div>
                </div>
                <div class="contrato-actions" style="display: flex; gap: 0.5rem;">
                    <button class="btn-download" onclick="downloadContrato('${gerenciador}', '${contrato.file_name}')" 
                            style="background: #4caf50; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.875rem; display: flex; align-items: center; gap: 0.25rem;">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
        </div>
    `;
    
    body.innerHTML = html;
}

/**
 * Obter ícone do arquivo baseado na extensão
 */
function getFileIcon(extension) {
    const icons = {
        'PDF': 'fas fa-file-pdf',
        'DOC': 'fas fa-file-word',
        'DOCX': 'fas fa-file-word',
        'XLS': 'fas fa-file-excel',
        'XLSX': 'fas fa-file-excel'
    };
    
    return icons[extension] || 'fas fa-file';
}

/**
 * Download de contrato
 */
function downloadContrato(gerenciador, fileName) {
    // Construir URL correta baseada no ambiente atual
    const currentUrl = window.location.href;
    let baseUrl;
    
    if (currentUrl.includes('/Site/')) {
        // Ambiente local de produção (/Site/)
        baseUrl = currentUrl.split('/Site/')[0] + '/Site';
    } else if (currentUrl.includes('/Gestao/')) {
        // Ambiente local de desenvolvimento (/Gestao/)
        baseUrl = currentUrl.split('/Gestao/')[0] + '/Gestao';
    } else {
        // Fallback - usar origem atual
        baseUrl = window.location.origin;
    }
    
    const downloadUrl = `${baseUrl}/includes/download_contrato.php?gerenciador=${encodeURIComponent(gerenciador)}&file=${encodeURIComponent(fileName)}`;
    
    // Abrir em nova aba para download
    window.open(downloadUrl, '_blank');
}

/**
 * Abrir modal de upload com gerenciador pré-selecionado
 */
async function abrirModalUploadComGerenciador(gerenciador) {
    await abrirModalUpload();
    
    // Selecionar o gerenciador
    const select = document.getElementById('upload_gerenciador');
    select.value = gerenciador;
}

