function resetarEdicaoLead(emailOriginal, leadUid) {
    try {
        const confirmar = confirm('Tem certeza que deseja resetar as edições desta lead e voltar aos dados padrão?\nVocê pode opcionalmente informar um motivo em seguida.');
        if (!confirmar) return;


        const fd = new FormData();
        // Se emailOriginal vier vazio, tentar ler da linha
        let emailToSend = (!emailOriginal || emailOriginal.toUpperCase() === 'N/A' || emailOriginal === '-') ? '' : emailOriginal;
        try {
            if (!emailToSend) {
                const btn = (this && this.tagName) ? this : ((typeof event !== 'undefined' && event && event.currentTarget) ? event.currentTarget : null);
                const tr = btn ? btn.closest('tr') : null;
                const emailOrigAttr = tr ? tr.getAttribute('data-email-original') : '';
                if (emailOrigAttr && emailOrigAttr.toUpperCase() !== 'N/A' && emailOrigAttr !== '-') {
                    emailToSend = emailOrigAttr;
                }
                if (!leadUid && tr) {
                    leadUid = tr.getAttribute('data-lead-uid') || '';
                }
            }
        } catch (e) {}
        if (emailToSend) fd.append('email_original', emailToSend);
        if (!emailToSend && leadUid) fd.append('lead_uid_original', leadUid);

        fetch('/Site/includes/crud/resetar_edicao_lead.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d && d.success) {
                    alert('Edições resetadas com sucesso. Recarregando a página...');
                    window.location.reload();
                } else {
                    alert('Falha ao resetar: ' + (d && d.message ? d.message : 'Erro desconhecido'));
                }
            })
            .catch(() => alert('Erro ao resetar a edição.'));
    } catch (e) {
        alert('Erro inesperado ao resetar.');
    }
}
// Variáveis globais
let ligacaoAtual = null;
let perguntasRoteiro = [];
let tempoInicio = null;
let timerInterval = null;
let emailLeadParaExcluir = null;
let leadRowParaExcluir = null;
let leadUidParaExcluir = '';
let observacaoTipoAtual = '';
let observacaoIdentificadorAtual = '';

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
        console.error('Identificador do lead inválido:', clienteId);
        alert('Erro: Identificador do lead não encontrado. Verifique se o lead possui email válido ou dados de identificação.');
        return;
    }
    
    if (!nomeCliente || nomeCliente.trim() === '' || nomeCliente === 'Lead sem nome') {
        console.error('Nome do cliente inválido:', nomeCliente);
        alert('Erro: Nome do lead não encontrado');
        return;
    }
    
    if (!telefone || telefone.trim() === '' || telefone === 'N/A' || telefone === '(-) -') {
        console.warn('Telefone inválido ou vazio:', telefone);
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
    
    fetch('/Site/includes/management/gerenciar_ligacoes.php', {
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

// Funções para exclusão de leads
function confirmarExclusaoLead(email, nomeLead, eventObj) {
    emailLeadParaExcluir = email;
    // Guardar a linha e UID diretamente do botão clicado
    try {
        // Tentar encontrar o botão que disparou o evento
        const evt = eventObj || (typeof event !== 'undefined' ? event : null);
        const btn = evt && evt.target ? evt.target.closest('button') : null;
        if (btn) {
            // Tentar pegar UID do botão ou da linha mais próxima
            leadUidParaExcluir = btn.getAttribute('data-lead-uid') || '';
            const tr = btn.closest('tr');
            if (tr) {
                leadRowParaExcluir = tr;
                if (!leadUidParaExcluir) {
                    leadUidParaExcluir = tr.getAttribute('data-lead-uid') || '';
                }
            } else {
                // Para mobile cards
                const card = btn.closest('.lead-card');
                if (card) {
                    leadRowParaExcluir = card;
                    if (!leadUidParaExcluir) {
                        leadUidParaExcluir = card.getAttribute('data-lead-uid') || '';
                    }
                }
            }
        } else {
            leadRowParaExcluir = null;
            leadUidParaExcluir = '';
        }
    } catch (e) {
        leadRowParaExcluir = null;
        leadUidParaExcluir = '';
    }
    document.getElementById('nomeLead').textContent = nomeLead;
    document.getElementById('modalConfirmacaoLead').style.display = 'flex';
    document.getElementById('motivoExclusaoLead').value = '';
    document.getElementById('motivoErrorLead').style.display = 'none';
}

function fecharModalLead() {
    document.getElementById('modalConfirmacaoLead').style.display = 'none';
    emailLeadParaExcluir = null;
    leadRowParaExcluir = null;
    leadUidParaExcluir = '';
    document.getElementById('motivoExclusaoLead').value = '';
    document.getElementById('motivoErrorLead').style.display = 'none';
    // Reset do campo de observações
    document.getElementById('observacaoExclusaoLead').value = '';
    // Reset do contador e barra de progresso
    atualizarContadorCaracteresLead('observacaoExclusaoLead', 'charCountLead', 'charStatusLead', 'progressBarLead');
}

// Função para gerenciar contador de caracteres nos leads
function atualizarContadorCaracteresLead(textareaId, countId, statusId, progressId) {
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

function excluirLead() {
    // Usar a linha salva no clique do botão Excluir
    const tr = leadRowParaExcluir;
    
    const motivoExclusao = document.getElementById('motivoExclusaoLead').value;
    const motivoError = document.getElementById('motivoErrorLead');
    
    if (!motivoExclusao) {
        motivoError.style.display = 'block';
        document.getElementById('motivoExclusaoLead').focus();
        return;
    } else {
        motivoError.style.display = 'none';
    }
    
    // Validar observações (obrigatórias - mínimo 30)
    const observacaoExclusao = document.getElementById('observacaoExclusaoLead').value.trim();
    if (!observacaoExclusao || observacaoExclusao.length < 30) {
        alert('As observações são obrigatórias e devem ter pelo menos 30 caracteres para fornecer contexto adequado.');
        document.getElementById('observacaoExclusaoLead').focus();
        return;
    }
    
    const confirmButton = document.querySelector('.btn-confirmar');
    const originalText = confirmButton.textContent;
    confirmButton.textContent = 'Processando...';
    confirmButton.disabled = true;
    
    const formData = new FormData();
    // Sempre enviar email, mesmo que vazio (backend aceitará UID quando email vazio)
    formData.append('email', emailLeadParaExcluir || '');
    // Anexar UID e dados auxiliares diretamente da linha correta
    if (tr) {
        const uid = leadUidParaExcluir || tr.getAttribute('data-lead-uid') || '';
        if (uid) formData.append('lead_uid', uid);
        const dataobs = tr.getAttribute('data-dataobs') || '';
        const uf = tr.getAttribute('data-uf') || '';
        const vend = tr.getAttribute('data-cod-vendedor') || '';
        const tel = tr.getAttribute('data-telefone-original') || '';
        const end = tr.getAttribute('data-endereco') || '';
        const nome = tr.getAttribute('data-nome') || tr.getAttribute('data-razao') || tr.getAttribute('data-fantasia') || '';
        if (dataobs) formData.append('dataobs', dataobs);
        if (uf) formData.append('uf', uf);
        if (vend) formData.append('codigo_vendedor', vend);
        if (tel) formData.append('telefone', tel);
        if (end) formData.append('endereco', end);
        if (nome) formData.append('nome', nome);
    }
    formData.append('motivo_exclusao', motivoExclusao);
    
    // Adicionar observação discursativa se fornecida
    if (observacaoExclusao) {
        formData.append('observacao_exclusao', observacaoExclusao);
    }
    
    fetch('/Site/includes/crud/excluir_lead.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Lead movido para lixeira com sucesso!');
            window.location.reload();
        } else {
            alert('Erro ao mover lead: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição: ' + error.message);
    })
    .finally(() => {
        confirmButton.textContent = originalText;
        confirmButton.disabled = false;
        fecharModalLead();
    });
}

// Funções para observações
function abrirObservacoes(tipo, identificador, titulo) {
    // Se o identificador for vazio/N/A, tentar obter o lead_uid da linha selecionada (quando chamado a partir de botões na tabela)
    let id = identificador;
    if (!id || id === 'N/A') {
        try {
            const btn = event && event.currentTarget ? event.currentTarget : null;
            const row = btn ? btn.closest('tr') : null;
            const uid = row ? row.getAttribute('data-lead-uid') : null;
            if (uid) id = uid;
        } catch (e) {
            // silencioso
        }
    }
    
    // Log para debug - verificar se a nova chave está sendo usada
    console.log('Abrindo observações para:', {
        tipo: tipo,
        identificador: id,
        titulo: titulo
    });
    
    observacaoTipoAtual = tipo;
    observacaoIdentificadorAtual = id;
    document.getElementById('tituloObservacao').textContent = titulo;
    document.getElementById('modalObservacoes').style.display = 'flex';
    document.getElementById('novaObservacao').value = '';
    carregarObservacoes();
}

function fecharModalObservacoes() {
    document.getElementById('modalObservacoes').style.display = 'none';
    observacaoTipoAtual = '';
    observacaoIdentificadorAtual = '';
}

function carregarObservacoes() {
    const formData = new FormData();
    formData.append('action', 'listar');
    formData.append('tipo', observacaoTipoAtual);
    formData.append('identificador', observacaoIdentificadorAtual);
    
    fetch('/Site/includes/management/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            exibirObservacoes(data.observacoes);
        } else {
            console.error('Erro ao carregar observações:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
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
                <span class="observacao-autor">${obs.usuario_nome}</span>
                <span class="observacao-data">
                    <i class="fas fa-clock" style="margin-right: 0.3rem;"></i>
                    ${obs.data_formatada}
                </span>
            </div>
            <div class="observacao-texto">${obs.observacao}</div>
            <div class="observacao-acoes">
                <button class="btn-observacao btn-observacao-editar" onclick="editarObservacao(${obs.id}, '${obs.observacao.replace(/'/g, "\\'")}')" title="Editar observação">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-observacao btn-observacao-excluir" onclick="excluirObservacao(${obs.id})" title="Excluir observação">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function adicionarObservacao() {
    const texto = document.getElementById('novaObservacao').value.trim();
    if (!texto) {
        alert('Digite uma observação');
        return;
    }
    
    if (!observacaoTipoAtual || !observacaoIdentificadorAtual) {
        console.error('Dados de observação inválidos:', {
            tipo: observacaoTipoAtual,
            identificador: observacaoIdentificadorAtual
        });
        alert('Erro: Dados da observação não foram carregados corretamente. Tente fechar e abrir o modal novamente.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'adicionar');
    formData.append('tipo', observacaoTipoAtual);
    formData.append('identificador', observacaoIdentificadorAtual);
    formData.append('observacao', texto);
    
    console.log('Enviando observação:', {
        action: 'adicionar',
        tipo: observacaoTipoAtual,
        identificador: observacaoIdentificadorAtual,
        observacao: texto.substring(0, 50) + '...'
    });
    
    fetch('/Site/includes/management/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text);
                throw new Error('Resposta do servidor não é JSON. Status: ' + response.status);
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Resposta recebida:', data);
        
        if (data.success) {
            document.getElementById('novaObservacao').value = '';
            carregarObservacoes();
        } else {
            alert('Erro ao adicionar observação: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        console.error('Stack:', error.stack);
        alert('Erro ao processar a requisição: ' + error.message + '\n\nVerifique o console para mais detalhes.');
    });
}

function editarObservacao(id, textoOriginal) {
    const item = document.querySelector(`[data-id="${id}"]`);
    const textoDiv = item.querySelector('.observacao-texto');
    const acoesDiv = item.querySelector('.observacao-acoes');
    
    item.classList.add('observacao-editando');
    textoDiv.innerHTML = `
        <textarea style="width: 100%; min-height: 60px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; margin-bottom: 8px;">${textoOriginal}</textarea>
    `;
    
    acoesDiv.innerHTML = `
        <button class="btn-observacao btn-observacao-salvar" onclick="salvarObservacao(${id})" title="Salvar">
            <i class="fas fa-save"></i>
        </button>
        <button class="btn-observacao btn-observacao-cancelar" onclick="cancelarEdicao(${id}, '${textoOriginal.replace(/'/g, "\\'")}'))" title="Cancelar">
            <i class="fas fa-times"></i>
        </button>
    `;
}

function salvarObservacao(id) {
    const item = document.querySelector(`[data-id="${id}"]`);
    const textarea = item.querySelector('textarea');
    const novoTexto = textarea.value.trim();
    
    if (!novoTexto) {
        alert('A observação não pode estar vazia');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'editar');
    formData.append('id', id);
    formData.append('observacao', novoTexto);
    
    fetch('/Site/includes/management/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarObservacoes();
        } else {
            alert('Erro ao editar observação: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição');
    });
}

function cancelarEdicao(id, textoOriginal) {
    const item = document.querySelector(`[data-id="${id}"]`);
    const textoDiv = item.querySelector('.observacao-texto');
    const acoesDiv = item.querySelector('.observacao-acoes');
    
    item.classList.remove('observacao-editando');
    textoDiv.innerHTML = textoOriginal;
    
    acoesDiv.innerHTML = `
        <button class="btn-observacao btn-observacao-editar" onclick="editarObservacao(${id}, '${textoOriginal.replace(/'/g, "\\'")}'))" title="Editar observação">
            <i class="fas fa-edit"></i>
        </button>
        <button class="btn-observacao btn-observacao-excluir" onclick="excluirObservacao(${id})" title="Excluir observação">
            <i class="fas fa-trash"></i>
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
    
    fetch('/Site/includes/management/gerenciar_observacoes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarObservacoes();
        } else {
            alert('Erro ao excluir observação: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição');
    });
}

// Funções para o Roteiro de Ligação
function exibirRoteiro() {
    console.log('Exibindo roteiro para:', ligacaoAtual);
    
    const modal = document.getElementById('modalRoteiroLigacao');
    if (!modal) {
        console.error('Modal não encontrado!');
        alert('Erro: Modal não encontrado');
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
    
    fetch('/Site/includes/management/gerenciar_ligacoes.php', {
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
    
    fetch('/Site/includes/management/gerenciar_ligacoes.php', {
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
    
    fetch('/Site/includes/management/gerenciar_ligacoes.php', {
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
    
    fetch('/Site/includes/management/gerenciar_ligacoes.php', {
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
    
    fetch('/Site/includes/management/gerenciar_ligacoes.php', {
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

// Inicialização quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    configurarDetecaoCancelamento();
    
    // Event listeners para botões de ligar usando delegação de eventos
    document.addEventListener('click', function(e) {
        // Verificar se o elemento clicado é um botão de ligar válido
        if (e.target.closest('button.btn-ligar:not(.disabled)')) {
            const button = e.target.closest('button.btn-ligar:not(.disabled)');
            const email = button.getAttribute('data-email');
            const nome = button.getAttribute('data-nome');
            const telefone = button.getAttribute('data-telefone');
            
            // Buscar o lead_uid da linha da tabela para leads sem email
            const row = button.closest('tr');
            const leadUid = row ? row.getAttribute('data-lead-uid') : null;
            
            // Usar lead_uid se email estiver vazio, senão usar email
            const identificador = (email && email.trim() !== '' && email !== 'N/A') ? email : leadUid;
            
            console.log('🔵 Botão de ligar clicado:', { email, nome, telefone, leadUid, identificador });
            
            // Evitar que elementos desabilitados respondam a cliques
            if (button.hasAttribute('disabled') || button.classList.contains('disabled')) {
                console.log('⚠️ Botão desabilitado - ignorando clique');
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            // Abrir primeiro o seletor de tipo de contato (telefone/whatsapp/email/presencial)
            if (typeof selecionarTipoContato === 'function') {
                console.log('✅ Chamando selecionarTipoContato()');
                selecionarTipoContato(identificador, nome, telefone);
            } else {
                console.log('⚠️ Função selecionarTipoContato não encontrada, usando iniciarLigacao()');
                iniciarLigacao(identificador, nome, telefone);
            }
        }
    });
    
    // Método adicional para botões específicos (backup)
    document.querySelectorAll('button.btn-ligar:not(.disabled)').forEach(button => {
        if (!button.hasAttribute('data-listener-added')) {
            button.setAttribute('data-listener-added', 'true');
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const email = this.getAttribute('data-email');
                const nome = this.getAttribute('data-nome');
                const telefone = this.getAttribute('data-telefone');
                
                // Buscar o lead_uid da linha da tabela para leads sem email
                const row = this.closest('tr');
                const leadUid = row ? row.getAttribute('data-lead-uid') : null;
                
                // Usar lead_uid se email estiver vazio, senão usar email
                const identificador = (email && email.trim() !== '' && email !== 'N/A') ? email : leadUid;
                
                console.log('🔵 Botão de ligar clicado (listener direto):', { email, nome, telefone, leadUid, identificador });
                if (typeof selecionarTipoContato === 'function') {
                    console.log('✅ Chamando selecionarTipoContato() - listener direto');
                    selecionarTipoContato(identificador, nome, telefone);
                } else {
                    console.log('⚠️ Função selecionarTipoContato não encontrada, usando iniciarLigacao() - listener direto');
                    iniciarLigacao(identificador, nome, telefone);
                }
            });
        }
    });
    
    // Event listeners para modais
    const modalConfirmacaoLead = document.getElementById('modalConfirmacaoLead');
    if (modalConfirmacaoLead) {
        modalConfirmacaoLead.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalLead();
            }
        });
    }
    
    // Event listener para campo de motivo
    const motivoSelectLead = document.getElementById('motivoExclusaoLead');
    const motivoErrorLead = document.getElementById('motivoErrorLead');
    
    if (motivoSelectLead) {
        motivoSelectLead.addEventListener('change', function() {
            if (this.value) {
                motivoErrorLead.style.display = 'none';
            }
        });
    }
    
    // Busca em tempo real (apenas para usuários autorizados)
    const searchInput = document.getElementById('searchLeads');
    const leadsTable = document.getElementById('leadsTable');
    
    if (searchInput && leadsTable) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = leadsTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) {
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        });
    }
});

// ===== FUNÇÕES PARA EDIÇÃO DE LEADS =====

// Variáveis para armazenar identificadores da lead sendo editada
let emailOriginalLead = '';
let leadUidOriginal = '';
let rowEditingEl = null;
let temEdicaoAtual = false;

// Função para abrir o modal de edição
function abrirEdicaoLead(email, nome, telefone, endereco, leadUid, eventObj) {
    console.log('Abrindo edição de lead:', { email, nome, telefone, endereco });
    
    // Capturar a linha diretamente a partir do botão clicado (mais robusto)
    try {
        const evt = eventObj || (typeof event !== 'undefined' ? event : null);
        const btn = (evt && evt.target) ? evt.target.closest('button') : null;
        if (btn) {
            const tr = btn.closest('tr');
            if (tr) {
                rowEditingEl = tr;
                // Preferir UID/email a partir da linha se não informado
                if (!leadUid) {
                    leadUid = tr.getAttribute('data-lead-uid') || '';
                }
                if (!email || email.toUpperCase() === 'N/A') {
                    email = tr.getAttribute('data-lead-email') || '';
                }
            } else {
                // Para mobile cards
                const card = btn.closest('.lead-card');
                if (card) {
                    rowEditingEl = card;
                    if (!leadUid) {
                        leadUid = card.getAttribute('data-lead-uid') || '';
                    }
                    if (!email || email.toUpperCase() === 'N/A') {
                        email = card.getAttribute('data-lead-email') || '';
                    }
                }
            }
        }
    } catch (e) {
        // silencioso
    }

    // Armazenar o email original
    const emailNorm = (email || '').trim();
    if (!emailNorm || emailNorm === '-' || emailNorm.toUpperCase() === 'N/A') {
        emailOriginalLead = '';
    } else {
        emailOriginalLead = emailNorm;
    }
    // Armazenar o identificador único (para leads sem email)
    leadUidOriginal = leadUid || '';
    
    // Guardar referência direta da linha da tabela para atualização posterior
    try {
        rowEditingEl = null;
        if (leadUidOriginal) {
            rowEditingEl = document.querySelector(`tr[data-lead-uid="${leadUidOriginal}"]`);
        }
        if (!rowEditingEl) {
            const normalizedEmail = (!emailOriginalLead || emailOriginalLead === '-' || emailOriginalLead.toUpperCase() === 'N/A') ? '' : emailOriginalLead;
            rowEditingEl = document.querySelector(`tr[data-lead-email="${normalizedEmail}"]`);
        }
    } catch (e) {
        console.warn('Não foi possível localizar a linha da lead ao abrir a edição.', e);
        rowEditingEl = null;
    }
    
    // Preencher os campos do formulário preferindo os valores da linha exibida (edição mais recente)
    try {
        const nomeInput = document.getElementById('editNomeLead');
        const emailInput = document.getElementById('editEmailLead');
        const telInput = document.getElementById('editTelefoneLead');
        const endInput = document.getElementById('editEnderecoLead');

        let nomeVal = nome || '';
        let emailVal = email || '';
        let telVal = telefone || '';
        let endVal = endereco || '';

        if (rowEditingEl) {
            const cells = rowEditingEl.querySelectorAll('td');
            if (cells[0]) {
                const n = cells[0].querySelector('.lead-nome');
                nomeVal = (n ? n.textContent : nomeVal) || nomeVal;
            }
            if (cells[1]) {
                const v = (cells[1].textContent || '').trim();
                if (v && v.toUpperCase() !== 'N/A') emailVal = v;
            }
            if (cells[2]) {
                const v = (cells[2].textContent || '').trim();
                if (v && v.toUpperCase() !== 'N/A') telVal = v;
            }
            if (cells[3]) {
                const v = (cells[3].textContent || '').trim();
                if (v && v.toUpperCase() !== 'N/A') endVal = v;
            }
        }

        if (nomeInput) nomeInput.value = nomeVal || '';
        if (emailInput) emailInput.value = emailVal || '';
        if (telInput) telInput.value = telVal || '';
        if (endInput) endInput.value = endVal || '';

        // Detectar visualmente se a linha tem edição aplicada (campo oculto/atributo)
        try {
            temEdicaoAtual = false;
            if (rowEditingEl) {
                // Heurística: se nome/email/telefone divergirem do dataset original, considerar editado
                const emailAttr = rowEditingEl.getAttribute('data-lead-email') || '';
                if (emailAttr && emailAttr !== emailOriginalLead) {
                    temEdicaoAtual = true;
                }
                // Poderíamos também depender de uma classe/ícone se houver
            }
        } catch (e) { temEdicaoAtual = false; }
    } catch (e) {
        // Fallback para valores recebidos nos parâmetros
        document.getElementById('editNomeLead').value = nome || '';
        document.getElementById('editEmailLead').value = email || '';
        document.getElementById('editTelefoneLead').value = telefone || '';
        document.getElementById('editEnderecoLead').value = endereco || '';
    }
    
    // Se já há edição aplicada, exigir reset antes de permitir nova edição
    try {
        const flagEd = rowEditingEl ? (rowEditingEl.getAttribute('data-tem-edicao') || '0') : '0';
        temEdicaoAtual = flagEd === '1';
    } catch (e) { temEdicaoAtual = false; }

    if (temEdicaoAtual) {
        const confirmar = confirm('Esta lead já possui edição aplicada. Você precisa resetar para os dados padrão antes de editar novamente. Deseja resetar agora?');
        if (!confirmar) {
            return;
        }
        // Chamar backend de reset
        const fd = new FormData();
        const emailToSend = (!emailOriginalLead || emailOriginalLead.toUpperCase() === 'N/A') ? '' : emailOriginalLead;
        if (emailToSend) fd.append('email_original', emailToSend);
        if (!emailToSend && leadUidOriginal) fd.append('lead_uid_original', leadUidOriginal);

        fetch('/Site/includes/crud/resetar_edicao_lead.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d && d.success) {
                    alert('Edição resetada. A página será recarregada para aplicar os dados padrão.');
                    window.location.reload();
                } else {
                    alert('Não foi possível resetar a edição: ' + (d && d.message ? d.message : 'Erro desconhecido'));
                }
            })
            .catch(() => {
                alert('Erro ao resetar a edição.');
            });
        return;
    }

    // Exibir o modal
    const modal = document.getElementById('modalEdicaoLead');
    modal.style.display = 'flex';
    
    // Focar no primeiro campo
    document.getElementById('editNomeLead').focus();

    // Se já há edição aplicada, exigir reset antes de permitir nova edição
    try {
        if (temEdicaoAtual) {
            const confirmar = confirm('Esta lead já possui edição aplicada. Você precisa resetar para os dados padrão antes de editar novamente. Deseja resetar agora?');
            if (!confirmar) {
                fecharModalEdicaoLead();
                return;
            }
            // Chamar backend de reset
            const fd = new FormData();
            const emailToSend = (!emailOriginalLead || emailOriginalLead.toUpperCase() === 'N/A') ? '' : emailOriginalLead;
            if (emailToSend) fd.append('email_original', emailToSend);
            if (!emailToSend && leadUidOriginal) fd.append('lead_uid_original', leadUidOriginal);

            fetch('/Site/includes/crud/resetar_edicao_lead.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d && d.success) {
                        alert('Edição resetada. A página será recarregada para aplicar os dados padrão.');
                        window.location.reload();
                    } else {
                        alert('Não foi possível resetar a edição: ' + (d && d.message ? d.message : 'Erro desconhecido'));
                        fecharModalEdicaoLead();
                    }
                })
                .catch(() => {
                    alert('Erro ao resetar a edição.');
                    fecharModalEdicaoLead();
                });
        }
    } catch (e) {}
}

// Função para fechar o modal de edição
function fecharModalEdicaoLead() {
    const modal = document.getElementById('modalEdicaoLead');
    modal.style.display = 'none';
    
    // Limpar o formulário
    document.getElementById('formEdicaoLead').reset();
    emailOriginalLead = '';
    leadUidOriginal = '';
    rowEditingEl = null;
}

// Função para salvar as alterações
function salvarEdicaoLead(formData) {
    console.log('Salvando edição de lead:', formData);
    
    // Adicionar o email original aos dados
    // Normalizar placeholder de email
    const emailToSend = (!emailOriginalLead || emailOriginalLead === '-' || emailOriginalLead.toUpperCase() === 'N/A') ? '' : emailOriginalLead;
    formData.append('email_original', emailToSend);
    // Adicionar o identificador único (quando não houver email)
    if (leadUidOriginal) {
        formData.append('lead_uid_original', leadUidOriginal);
    }
    
    fetch('/Site/includes/crud/editar_lead.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta da edição:', data);
        
        if (data.success) {
            // Mostrar mensagem de sucesso
            mostrarMensagem('Lead atualizado com sucesso!', 'success');
            
            // Atualizar a linha na tabela imediatamente
            try {
                const updated = data.updated || {};

                // Usar a referência direta capturada; se não existir, localizar pelo UID/email
                let rowEl = rowEditingEl;
                if (!rowEl) {
                    if (leadUidOriginal) {
                        rowEl = document.querySelector(`tr[data-lead-uid="${leadUidOriginal}"]`);
                    }
                    if (!rowEl && emailOriginalLead) {
                        const normalizedEmail = (!emailOriginalLead || emailOriginalLead === '-' || emailOriginalLead.toUpperCase() === 'N/A') ? '' : emailOriginalLead;
                        rowEl = document.querySelector(`tr[data-lead-email="${normalizedEmail}"]`);
                    }
                }

                if (rowEl) {
                    const cells = rowEl.querySelectorAll('td');
                    const novoNome = updated.nome || '';
                    const novoEmail = updated.email || '';
                    const novoTelefone = updated.telefone || '';
                    const novoEndereco = updated.endereco || '';

                    // Atualizar colunas visíveis: Nome, Email, Telefone, Endereço
                    if (cells[0] && cells[0].querySelector('.lead-nome')) {
                        cells[0].querySelector('.lead-nome').textContent = novoNome || 'N/A';
                    }
                    if (cells[1]) {
                        cells[1].textContent = (novoEmail && novoEmail.toUpperCase() !== 'N/A') ? novoEmail : 'N/A';
                    }
                    if (cells[2]) {
                        cells[2].textContent = (novoTelefone && novoTelefone.toUpperCase() !== 'N/A') ? novoTelefone : 'N/A';
                    }
                    if (cells[3]) {
                        cells[3].textContent = (novoEndereco && novoEndereco.toUpperCase() !== 'N/A') ? novoEndereco : 'N/A';
                    }

                    // Atualizar data attributes para futuras ações. Se email mudou, atualizar atributo e variáveis de estado
                    const novoEmailAttr = (novoEmail && novoEmail.toUpperCase() !== 'N/A') ? novoEmail : '';
                    rowEl.setAttribute('data-lead-email', novoEmailAttr);

                    // Atualizar botões da coluna verde (ligar e agendar)
                    const ligarBtn = rowEl.querySelector('.actions-green .btn-ligar');
                    if (ligarBtn) {
                        if (novoTelefone && novoTelefone !== 'N/A') {
                            ligarBtn.classList.remove('disabled');
                            ligarBtn.setAttribute('title', 'Ligar');
                        }
                        ligarBtn.dataset.email = novoEmail || '';
                        ligarBtn.dataset.nome = novoNome || '';
                        ligarBtn.dataset.telefone = novoTelefone || '';
                    }

                    const agendarBtn = rowEl.querySelector('.actions-green .btn.btn-success');
                    if (agendarBtn) {
                        // Reconfigurar onclick com dados atualizados (mantém UID se email vazio)
                        const idParaAgendar = (novoEmail && novoEmail.toUpperCase() !== 'N/A') ? novoEmail : (leadUidOriginal || '');
                        agendarBtn.setAttribute('onclick', `abrirAgendamentoLead('${idParaAgendar.replace(/'/g, "\\'")}', '${(novoNome || '').replace(/'/g, "\\'")}', '${(novoTelefone || '').replace(/'/g, "\\'")}')`);
                    }

                    // Atualizar botão de observações (primeiro botão amarelo)
                    const observacoesBtn = rowEl.querySelector('.actions-yellow .btn.btn-warning');
                    if (observacoesBtn) {
                        const idParaObs = (novoEmail && novoEmail.toUpperCase() !== 'N/A') ? novoEmail : (rowEl.getAttribute('data-lead-uid') || '');
                        observacoesBtn.setAttribute('onclick', `abrirObservacoes('lead', '${idParaObs.replace(/'/g, "\\'")}', '${(novoNome || '').replace(/'/g, "\\'")}')`);
                    }

                }
            } catch (e) {
                console.error('Falha ao atualizar a linha da tabela após edição:', e);
            }

            // Fechar o modal ao final
            fecharModalEdicaoLead();
        } else {
            // Mostrar mensagem de erro
            mostrarMensagem(data.message || 'Erro ao atualizar lead', 'error');
        }
    })
    .catch(error => {
        console.error('Erro ao salvar edição:', error);
        mostrarMensagem('Erro ao conectar com o servidor', 'error');
    });
}

// Função para mostrar mensagens
function mostrarMensagem(mensagem, tipo) {
    // Criar elemento de mensagem
    const mensagemDiv = document.createElement('div');
    mensagemDiv.className = `mensagem-flutuante mensagem-${tipo}`;
    mensagemDiv.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${mensagem}</span>
    `;
    
    // Adicionar ao body
    document.body.appendChild(mensagemDiv);
    
    // Mostrar com animação
    setTimeout(() => {
        mensagemDiv.classList.add('mostrar');
    }, 100);
    
    // Remover após 3 segundos
    setTimeout(() => {
        mensagemDiv.classList.remove('mostrar');
        setTimeout(() => {
            document.body.removeChild(mensagemDiv);
        }, 300);
    }, 3000);
}

// Event listener para o formulário de edição
document.addEventListener('DOMContentLoaded', function() {
    const formEdicaoLead = document.getElementById('formEdicaoLead');
    if (formEdicaoLead) {
        formEdicaoLead.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            salvarEdicaoLead(formData);
        });
    }
    
    // Event listener para fechar modal de edição ao clicar fora
    const modalEdicaoLead = document.getElementById('modalEdicaoLead');
    if (modalEdicaoLead) {
        modalEdicaoLead.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalEdicaoLead();
            }
        });
    }
    
    // Event listener para formulário de agendamento de leads
    const formAgendamentoLead = document.getElementById('formAgendamentoLead');
    if (formAgendamentoLead) {
        formAgendamentoLead.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar formulário
            if (!validarFormularioAgendamento()) {
                return;
            }
            
            const formData = new FormData(this);
            salvarAgendamentoLead(formData);
        });
    }
    
    // Event listener para fechar modal de agendamento ao clicar fora
    const modalAgendamentoLead = document.getElementById('modalAgendamentoLead');
    if (modalAgendamentoLead) {
        modalAgendamentoLead.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalAgendamentoLead();
            }
        });
    }
});

// ===== FUNÇÕES PARA AGENDAMENTO DE LEADS =====

// Variáveis para agendamento
let leadAgendamentoAtual = null;

// Função para abrir modal de agendamento
function abrirAgendamentoLead(email, nome, telefone) {
    console.log('=== DEBUG AGENDAMENTO LEAD ===');
    console.log('Identificador (email ou UID):', email);
    console.log('Nome:', nome);
    console.log('Telefone:', telefone);
    console.log('Tipo telefone:', typeof telefone);
    console.log('Telefone vazio?', !telefone);
    console.log('Telefone === N/A?', telefone === 'N/A');
    console.log('Telefone contém hífen?', telefone && telefone.includes('-'));
    
    // Tratar primeiro parâmetro como identificador genérico (email ou lead_uid)
    const identificador = email;
    const emailAparente = (typeof email === 'string' && email.includes('@')) ? email : '';
    
    // Verificar se os dados são válidos
    if (!identificador || identificador === 'N/A') {
        console.error('Identificador inválido:', identificador);
        alert('Identificador inválido para agendamento');
        return;
    }
    
    if (!nome || nome === 'N/A') {
        console.error('Nome inválido:', nome);
        alert('Nome inválido para agendamento');
        return;
    }
    
    if (!telefone || telefone === 'N/A') {
        console.error('Telefone inválido:', telefone);
        alert('Telefone inválido para agendamento');
        return;
    }
    
    // Armazenar dados do lead
    leadAgendamentoAtual = { identificador, email: emailAparente, nome, telefone };
    
    // Preencher informações do lead no modal
    const nomeElement = document.getElementById('agendamentoLeadNome');
    const emailElement = document.getElementById('agendamentoLeadEmail');
    const telefoneElement = document.getElementById('agendamentoLeadTelefone');
    
    if (!nomeElement || !emailElement || !telefoneElement) {
        console.error('Elementos do modal não encontrados:', {
            nome: !!nomeElement,
            email: !!emailElement,
            telefone: !!telefoneElement
        });
        alert('Erro ao abrir modal de agendamento');
        return;
    }
    
    nomeElement.textContent = nome || 'N/A';
    emailElement.textContent = emailAparente || 'N/A';
    telefoneElement.textContent = telefone || 'N/A';
    
    // Limpar o formulário
    const form = document.getElementById('formAgendamentoLead');
    if (form) {
        form.reset();
    }
    
    // Definir data mínima como hoje
    const dataInput = document.getElementById('agendamentoData');
    if (dataInput) {
        const hoje = new Date().toISOString().split('T')[0];
        dataInput.min = hoje;
    }
    
    // Exibir o modal
    const modal = document.getElementById('modalAgendamentoLead');
    if (modal) {
        modal.style.display = 'flex';
        console.log('Modal exibido com sucesso');
    } else {
        console.error('Modal não encontrado');
        alert('Erro ao abrir modal de agendamento');
        return;
    }
    
    // Focar no primeiro campo
    if (dataInput) {
        dataInput.focus();
    }
    
    console.log('=== FIM DEBUG ===');
}

// Função para fechar modal de agendamento
function fecharModalAgendamentoLead() {
    document.getElementById('modalAgendamentoLead').style.display = 'none';
    document.getElementById('formAgendamentoLead').reset();
    leadAgendamentoAtual = null;
}

// Função para salvar agendamento
function salvarAgendamentoLead(formData) {
    console.log('Salvando agendamento de lead');
    
    if (!leadAgendamentoAtual) {
        mostrarMensagem('Erro: Dados do lead não encontrados', 'error');
        return;
    }
    
    // Adicionar dados do lead ao formulário
    formData.append('acao', 'criar_agendamento_lead');
    formData.append('lead_identificador', leadAgendamentoAtual.identificador);
    formData.append('lead_email', leadAgendamentoAtual.email || '');
    formData.append('lead_nome', leadAgendamentoAtual.nome);
    formData.append('lead_telefone', leadAgendamentoAtual.telefone);
    
    // Mostrar loading
    const submitBtn = document.querySelector('#formAgendamentoLead .btn-confirmar');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Agendando...';
    submitBtn.disabled = true;
    
    fetch('/Site/includes/calendar/gerenciar_agendamentos_leads.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta do agendamento:', data);
        
        if (data.success) {
            mostrarMensagem('Agendamento criado com sucesso!', 'success');
            fecharModalAgendamentoLead();
            
            // Opcional: atualizar lista de agendamentos se estiver visível
            if (typeof carregarAgendamentosLeads === 'function') {
                carregarAgendamentosLeads();
            }
        } else {
            mostrarMensagem(data.message || 'Erro ao criar agendamento', 'error');
        }
    })
    .catch(error => {
        console.error('Erro ao salvar agendamento:', error);
        mostrarMensagem('Erro ao conectar com o servidor: ' + error.message, 'error');
    })
    .finally(() => {
        // Restaurar botão
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Função para carregar agendamentos de leads (opcional)
function carregarAgendamentosLeads() {
    fetch('/Site/includes/calendar/gerenciar_agendamentos_leads.php?acao=buscar_agendamentos_leads')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Agendamentos de leads carregados:', data.agendamentos);
            // Aqui você pode atualizar uma lista na interface se necessário
        }
    })
    .catch(error => {
        console.error('Erro ao carregar agendamentos:', error);
    });
}

// Função para validar formulário de agendamento
function validarFormularioAgendamento() {
    const data = document.getElementById('agendamentoData').value;
    const hora = document.getElementById('agendamentoHora').value;
    
    if (!data) {
        mostrarMensagem('Por favor, selecione uma data', 'error');
        return false;
    }
    
    if (!hora) {
        mostrarMensagem('Por favor, selecione um horário', 'error');
        return false;
    }
    
    // Verificar se a data/hora não é no passado
    const agendamento = new Date(data + 'T' + hora);
    const agora = new Date();
    
    if (agendamento <= agora) {
        mostrarMensagem('Não é possível agendar para datas/horários passados', 'error');
        return false;
    }
    
    // Verificar horário comercial (8h às 18h)
    const horaNum = parseInt(hora.split(':')[0]);
    if (horaNum < 8 || horaNum >= 18) {
        mostrarMensagem('Por favor, selecione um horário comercial (8h às 18h)', 'error');
        return false;
    }
    
    return true;
}

// Adicionar estilos CSS para mensagens flutuantes
const style = document.createElement('style');
style.textContent = `
    .mensagem-flutuante {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .mensagem-flutuante.mostrar {
        transform: translateX(0);
    }
    
    .mensagem-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    
    .mensagem-error {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
    
    .mensagem-flutuante i {
        font-size: 1.2rem;
    }
`;
document.head.appendChild(style);

// Adicionar event listeners quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    const observacaoTextarea = document.getElementById('observacaoExclusaoLead');
    if (observacaoTextarea) {
        observacaoTextarea.addEventListener('input', function() {
            atualizarContadorCaracteresLead('observacaoExclusaoLead', 'charCountLead', 'charStatusLead', 'progressBarLead');
        });
        
        observacaoTextarea.addEventListener('keyup', function() {
            atualizarContadorCaracteresLead('observacaoExclusaoLead', 'charCountLead', 'charStatusLead', 'progressBarLead');
        });
    }
});