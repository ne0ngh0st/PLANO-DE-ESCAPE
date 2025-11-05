// Função para detectar se é dispositivo móvel
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Função para fazer a ligação direta
function fazerLigacaoDireta(telefone) {
    // Verificar se o telefone está vazio
    if (!telefone || telefone.trim() === '') {
        console.log('Telefone vazio - não fazendo ligação direta');
        return;
    }
    
    // Limpar o telefone (remover caracteres especiais)
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
        // Em dispositivos móveis, usar o dialer nativo
        window.location.href = `tel:${telefoneLimpo}`;
    } else {
        // Em desktop, tentar abrir o Microsip
        // Microsip usa o protocolo sip: ou callto:
        try {
            // Tentar primeiro com callto: (mais comum)
            window.location.href = `callto:${telefoneLimpo}`;
        } catch (e) {
            // Se falhar, tentar com sip:
            try {
                window.location.href = `sip:${telefoneLimpo}`;
            } catch (e2) {
                // Se ambos falharem, não mostrar mensagem
            }
        }
    }
}

// Função para abrir modal de seleção do tipo de contato
function selecionarTipoContato(clienteId, nomeCliente, telefone) {
    console.log('=== SELECIONAR TIPO DE CONTATO ===');
    console.log('Dados do cliente:', { clienteId, nomeCliente, telefone });
    
    // Validar parâmetros
    if (!clienteId || clienteId.trim() === '' || clienteId === '-') {
        console.error('❌ Cliente ID inválido:', clienteId);
        alert('Erro: Identificador do lead não encontrado. Verifique se o lead possui dados de identificação válidos.');
        return;
    }
    
    if (!nomeCliente || nomeCliente.trim() === '' || nomeCliente === 'Cliente sem nome') {
        console.error('❌ Nome do cliente inválido:', nomeCliente);
        alert('Erro: Nome do lead não encontrado.');
        return;
    }
    
    console.log('✅ Validação passou - abrindo modal de seleção de tipo de contato');
    
    // Armazenar dados do cliente para uso posterior
    window.clienteParaContato = {
        cliente_id: clienteId,
        nome: nomeCliente,
        telefone: telefone || ''
    };
    
    // Exibir modal de seleção
    exibirModalTipoContato();
}

// Função para exibir modal de seleção do tipo de contato
function exibirModalTipoContato() {
    const modal = document.getElementById('modalTipoContato');
    if (modal) {
        try {
            // Preencher informações do lead no modal, se os elementos existirem
            const nomeSpan = document.getElementById('contatoLeadNome');
            const telSpan = document.getElementById('contatoLeadTelefone');
            if (nomeSpan && window.clienteParaContato && window.clienteParaContato.nome) {
                nomeSpan.textContent = window.clienteParaContato.nome;
            }
            if (telSpan && window.clienteParaContato) {
                const telefone = window.clienteParaContato.telefone || '';
                telSpan.textContent = telefone && telefone.trim() !== '' ? telefone : 'N/A';
            }
        } catch (e) {
            // silencioso
        }
        modal.style.display = 'flex';
    }
}

// Função para fechar modal de seleção do tipo de contato
function fecharModalTipoContato() {
    const modal = document.getElementById('modalTipoContato');
    if (modal) {
        modal.style.display = 'none';
    }
    // Limpar dados temporários
    window.clienteParaContato = null;
}

// Função para confirmar tipo de contato e iniciar processo
function confirmarTipoContato(tipoContato) {
    console.log('Tipo de contato selecionado:', tipoContato);
    
    if (!window.clienteParaContato) {
        console.error('Dados do cliente não encontrados');
        return;
    }
    
    const { cliente_id, nome, telefone } = window.clienteParaContato;
    
    // Fechar modal de seleção
    fecharModalTipoContato();
    
    // Iniciar processo baseado no tipo de contato
    if (tipoContato === 'telefonica') {
        // Ligação telefônica - fazer ligação direta E abrir roteiro
        if (telefone && telefone.trim() !== '') {
            fazerLigacaoDireta(telefone);
        }
        iniciarRoteiroLigacao(cliente_id, nome, telefone, 'telefonica');
    } else if (tipoContato === 'presencial') {
        // Contato presencial - apenas abrir roteiro
        iniciarRoteiroLigacao(cliente_id, nome, telefone, 'presencial');
    } else if (tipoContato === 'whatsapp') {
        // WhatsApp - abrir WhatsApp E abrir roteiro
        abrirWhatsApp(telefone, nome);
        iniciarRoteiroLigacao(cliente_id, nome, telefone, 'whatsapp');
    } else if (tipoContato === 'email') {
        // Email - abrir email E abrir roteiro
        abrirEmail(nome);
        iniciarRoteiroLigacao(cliente_id, nome, telefone, 'email');
    }
}

// Função para abrir WhatsApp
function abrirWhatsApp(telefone, nomeCliente) {
    if (!telefone || telefone.trim() === '') {
        console.log('Telefone vazio - não abrindo WhatsApp');
        return;
    }
    
    // Limpar o telefone (remover caracteres especiais)
    let telefoneLimpo = telefone.replace(/[^\d]/g, '');
    
    // Remover DDD 11 se o número começar com 11 e tiver pelo menos 10 dígitos
    if (telefoneLimpo.startsWith('11') && telefoneLimpo.length >= 10) {
        telefoneLimpo = telefoneLimpo.substring(2);
    }
    
    // Adicionar código do país se necessário
    if (telefoneLimpo.length === 8 || telefoneLimpo.length === 9) {
        telefoneLimpo = '5511' + telefoneLimpo;
    } else if (telefoneLimpo.length === 10 || telefoneLimpo.length === 11) {
        telefoneLimpo = '55' + telefoneLimpo;
    }
    
    // Mensagem padrão
    const mensagem = `Olá! Estou entrando em contato através do sistema da Autopel. Como posso ajudá-lo?`;
    
    // URL do WhatsApp
    const urlWhatsApp = `https://wa.me/${telefoneLimpo}?text=${encodeURIComponent(mensagem)}`;
    
    console.log('Abrindo WhatsApp:', urlWhatsApp);
    
    // Abrir WhatsApp
    window.open(urlWhatsApp, '_blank');
}

// Função para abrir email
function abrirEmail(nomeCliente) {
    // Abrir Outlook Web diretamente
    const urlOutlook = 'https://outlook.live.com/mail/0/';
    
    console.log('Abrindo Outlook Web:', urlOutlook);
    
    // Abrir Outlook Web em nova aba
    window.open(urlOutlook, '_blank');
}

// Função para iniciar roteiro de ligação (renomeada da função original)
function iniciarRoteiroLigacao(clienteId, nomeCliente, telefone, tipoContato = 'telefonica') {
    console.log('=== INICIANDO ROTEIRO DE LIGAÇÃO ===');
    console.log('Dados:', { clienteId, nomeCliente, telefone, tipoContato });
    
    ligacaoAtual = {
        cliente_id: clienteId,
        nome: nomeCliente,
        telefone: telefone,
        tipo_contato: tipoContato
    };
    
    // Iniciar timer
    tempoInicio = new Date();
    atualizarTempo();
    timerInterval = setInterval(atualizarTempo, 1000);
    
    // Buscar perguntas do roteiro
    const formData = new FormData();
    formData.append('action', 'iniciar_ligacao');
    formData.append('cliente_id', clienteId);
    formData.append('telefone', telefone);
    formData.append('tipo_contato', tipoContato);
    
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
        console.error('Error details:', {
            name: error.name,
            message: error.message,
            stack: error.stack
        });
        // Erro silencioso
    });
}

function exibirRoteiro() {
    console.log('=== FUNÇÃO EXIBIR ROTEIRO CHAMADA ===');
    console.log('Exibindo roteiro para:', ligacaoAtual);
    
    const modal = document.getElementById('modalRoteiroLigacao');
    if (!modal) {
        console.error('Modal não encontrado!');
        return;
    }
    
    const nomeElement = document.getElementById('nomeClienteLigacao');
    const clienteElement = document.getElementById('clienteInfo');
    const telefoneElement = document.getElementById('telefoneInfo');
    const tipoContatoElement = document.getElementById('tipoContatoInfo');
    
    if (nomeElement) nomeElement.textContent = ligacaoAtual.nome;
    if (clienteElement) clienteElement.textContent = ligacaoAtual.nome;
    if (telefoneElement) {
        if (ligacaoAtual.tipo_contato === 'presencial') {
            telefoneElement.textContent = 'Contato Presencial';
        } else if (ligacaoAtual.tipo_contato === 'whatsapp') {
            telefoneElement.textContent = 'WhatsApp';
        } else {
            telefoneElement.textContent = ligacaoAtual.telefone;
        }
    }
    if (tipoContatoElement) {
        const tipoText = {
            'telefonica': 'Ligação Telefônica',
            'presencial': 'Contato Presencial',
            'whatsapp': 'WhatsApp'
        };
        tipoContatoElement.textContent = tipoText[ligacaoAtual.tipo_contato] || 'Ligação Telefônica';
    }
    
    const container = document.getElementById('perguntasContainer');
    container.innerHTML = '';
    
    // Adicionar barra de progresso
    const progressoHtml = `
        <div class="progresso-texto">
            Progresso: <span id="progressoTexto">0/${perguntasRoteiro.length}</span> perguntas respondidas
        </div>
        <div class="progresso-roteiro">
            <div class="progresso-barra" id="progressoBarra" style="width: 0%"></div>
        </div>
    `;
    container.innerHTML = progressoHtml;
    
    // Adicionar perguntas
    perguntasRoteiro.forEach((pergunta, index) => {
        const perguntaHtml = criarHtmlPergunta(pergunta, index);
        container.innerHTML += perguntaHtml;
    });
    
    // Aplicar lógica condicional inicial
    aplicarCondicionalInicial();
    
    // Restaurar respostas salvas (se houver)
    restaurarRespostas();
    
    // Atualizar progresso inicial
    atualizarProgresso();
    
    if (modal) {
        modal.style.display = 'flex';
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
    
    // Remover seleção anterior
    const perguntaItem = elemento.closest('.pergunta-item');
    perguntaItem.querySelectorAll('.opcao-item').forEach(item => {
        item.classList.remove('selecionada');
    });
    
    // Selecionar nova opção
    elemento.classList.add('selecionada');
    
    // Mostrar/ocultar campos adicionais
    const opcaoIndex = Array.from(perguntaItem.querySelectorAll('.opcao-item')).indexOf(elemento);
    console.log('Índice da opção selecionada:', opcaoIndex);
    
    const camposAdicionais = perguntaItem.querySelector(`#campos_${perguntaId}_${opcaoIndex}`);
    console.log('Elemento campos adicionais encontrado:', camposAdicionais);
    
    if (camposAdicionais) {
        camposAdicionais.style.display = 'block';
        console.log('Campos adicionais exibidos para:', valor);
        
        // Se há campos adicionais, NÃO avançar automaticamente
        // O usuário deve preencher os campos primeiro
        return;
    } else {
        console.log('Nenhum campo adicional encontrado para:', valor);
    }
    
    // Ocultar outros campos adicionais
    perguntaItem.querySelectorAll('.campos-adicionais').forEach(campo => {
        if (campo !== camposAdicionais) {
            campo.style.display = 'none';
        }
    });
    
    // Salvar resposta no backend (que irá chamar verificarCondicional)
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
            
            // Armazenar resposta localmente
            if (!ligacaoAtual.respostas) {
                ligacaoAtual.respostas = {};
            }
            ligacaoAtual.respostas[perguntaId] = resposta;
            
            // Verificar lógica condicional e avançar
            verificarCondicional(perguntaId, resposta);
            
        } else {
            console.error('Erro ao salvar resposta:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao salvar resposta:', error);
    });
}

function atualizarProgresso() {
    // Usar o objeto de respostas armazenado em ligacaoAtual
    const respostas = ligacaoAtual.respostas || {};
    const respostaPrimeira = respostas[1];
    
    let totalPerguntasEsperadas = 0;
    let perguntasRespondidas = 0;
    
    if (respostaPrimeira) {
        console.log('Fluxo ativo:', respostaPrimeira);
        
        if (respostaPrimeira === 'Sim') {
            // Fluxo SIM: perguntas 1-9 (9 perguntas)
            totalPerguntasEsperadas = 9;
            
            // Contar respostas das perguntas 1-9
            for (let i = 1; i <= 9; i++) {
                if (respostas[i]) {
                    perguntasRespondidas++;
                }
            }
        } else if (respostaPrimeira === 'Não') {
            // Fluxo NÃO: perguntas 1 e 10 (2 perguntas)
            totalPerguntasEsperadas = 2;
            
            // Contar respostas das perguntas 1 e 10
            if (respostas[1]) perguntasRespondidas++;
            if (respostas[10]) perguntasRespondidas++;
        }
    } else {
        // Primeira pergunta ainda não respondida
        totalPerguntasEsperadas = 1;
        perguntasRespondidas = 0;
    }
    
    const percentual = totalPerguntasEsperadas > 0 ? (perguntasRespondidas / totalPerguntasEsperadas) * 100 : 0;
    
    // Atualizar elementos de progresso
    const progressoTexto = document.getElementById('progressoTexto');
    const progressoBarra = document.getElementById('progressoBarra');
    
    if (progressoTexto) {
        progressoTexto.textContent = `${perguntasRespondidas}/${totalPerguntasEsperadas}`;
    }
    if (progressoBarra) {
        progressoBarra.style.width = `${percentual}%`;
    }
    
    // Debug: mostrar informações no console
    console.log('Progresso atualizado:', {
        totalPerguntasEsperadas: totalPerguntasEsperadas,
        perguntasRespondidas: perguntasRespondidas,
        percentual: percentual,
        respostas: respostas
    });
    
    // Habilitar botão de finalizar se todas as perguntas do fluxo foram respondidas
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

function verificarCondicional(perguntaId, valor) {
    console.log(`Verificando condicional: pergunta ${perguntaId}, valor "${valor}"`);
    
    // Armazenar a resposta atual
    if (!ligacaoAtual.respostas) {
        ligacaoAtual.respostas = {};
    }
    ligacaoAtual.respostas[perguntaId] = valor;
    
    // Determinar qual fluxo está ativo baseado na primeira pergunta
    const respostaPrimeira = ligacaoAtual.respostas[1];
    
    if (!respostaPrimeira) {
        console.log('Primeira pergunta ainda não respondida');
        return;
    }
    
    console.log('Fluxo ativo:', respostaPrimeira);
    
    // Ocultar todas as perguntas primeiro
    perguntasRoteiro.forEach(pergunta => {
        const perguntaElement = document.querySelector(`[data-pergunta-id="${pergunta.id}"]`);
        if (perguntaElement) {
            perguntaElement.style.display = 'none';
        }
    });
    
    if (respostaPrimeira === 'Sim') {
        // FLUXO SIM: mostrar apenas a próxima pergunta sequencial
        const perguntasSim = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        const ultimaRespondida = Math.max(...perguntasSim.filter(id => ligacaoAtual.respostas[id]));
        const proximaPergunta = ultimaRespondida + 1;
        
        // Mostrar apenas a próxima pergunta (se não for a última)
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
        // FLUXO NÃO: mostrar apenas pergunta 10
        const pergunta10 = document.querySelector(`[data-pergunta-id="10"]`);
        if (pergunta10) {
            pergunta10.style.display = 'block';
        }
        console.log('Fluxo NÃO ativo - pergunta 10 exibida');
    }
    
    // Recalcular progresso e verificar se pode finalizar
    atualizarProgresso();
}

function salvarCampoAdicional(perguntaId, campo, valor) {
    if (!ligacaoAtual || !ligacaoAtual.id) {
        console.error('Ligação não iniciada');
        return;
    }
    
    // Buscar campos adicionais existentes
    const camposExistentes = JSON.parse(localStorage.getItem(`campos_adicional_${ligacaoAtual.id}_${perguntaId}`) || '{}');
    camposExistentes[campo] = valor;
    
    // Salvar no localStorage temporariamente
    localStorage.setItem(`campos_adicional_${ligacaoAtual.id}_${perguntaId}`, JSON.stringify(camposExistentes));
    
    // Enviar para o servidor
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
    
    // Salvar resposta da pergunta (sem chamar verificarCondicional novamente)
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
            
            // Armazenar resposta localmente
            if (!ligacaoAtual.respostas) {
                ligacaoAtual.respostas = {};
            }
            ligacaoAtual.respostas[perguntaId] = valor;
            
            // Verificar lógica condicional e avançar
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
    
    // Ocultar todas as perguntas exceto a primeira
    perguntasRoteiro.forEach(pergunta => {
        const perguntaElement = document.querySelector(`[data-pergunta-id="${pergunta.id}"]`);
        if (perguntaElement) {
            if (pergunta.id == 1) {
                // Primeira pergunta sempre visível
                perguntaElement.style.display = 'block';
            } else {
                // Demais perguntas ocultas inicialmente
                perguntaElement.style.display = 'none';
            }
        }
    });
    
    // Inicializar objeto de respostas se não existir
    if (!ligacaoAtual.respostas) {
        ligacaoAtual.respostas = {};
    }
    
    console.log('Condicional inicial aplicado - apenas pergunta 1 visível');
}

function restaurarRespostas() {
    console.log('Restaurando respostas salvas:', ligacaoAtual.respostas);
    
    // Se não há respostas salvas, não há nada para restaurar
    if (!ligacaoAtual.respostas || Object.keys(ligacaoAtual.respostas).length === 0) {
        console.log('Nenhuma resposta para restaurar');
        return;
    }
    
    // Restaurar cada resposta
    Object.keys(ligacaoAtual.respostas).forEach(perguntaId => {
        const resposta = ligacaoAtual.respostas[perguntaId];
        const perguntaElement = document.querySelector(`[data-pergunta-id="${perguntaId}"]`);
        
        if (perguntaElement) {
            // Marcar opção selecionada
            const opcaoElement = perguntaElement.querySelector(`[onclick*="'${resposta}'"]`);
            if (opcaoElement) {
                opcaoElement.classList.add('selecionada');
            }
            
            // Marcar radio button se existir
            const radioElement = perguntaElement.querySelector(`input[value="${resposta}"]`);
            if (radioElement) {
                radioElement.checked = true;
            }
            
            console.log(`Resposta restaurada: pergunta ${perguntaId} = "${resposta}"`);
        }
    });
    
    // Aplicar lógica condicional baseada nas respostas restauradas
    const respostaPrimeira = ligacaoAtual.respostas[1];
    if (respostaPrimeira) {
        verificarCondicional(1, respostaPrimeira);
    }
}

function atualizarTempo() {
    if (!tempoInicio) return;
    
    const agora = new Date();
    const diferenca = Math.floor((agora - tempoInicio) / 1000);
    const minutos = Math.floor(diferenca / 60);
    const segundos = diferenca % 60;
    
    const tempoFormatado = `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
    document.getElementById('tempoLigacao').textContent = tempoFormatado;
}

function finalizarLigacao() {
    console.log('Tentando finalizar ligação:', ligacaoAtual);
    
    if (!ligacaoAtual || !ligacaoAtual.id) {
        return;
    }
    
    // Se a ligação foi cancelada, mostrar aviso
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
    // Cancelar ligação apenas se não estiver já cancelada
    if (ligacaoAtual && ligacaoAtual.id && !ligacaoAtual.cancelada) {
        console.log('Cancelando ligação por fechamento do modal');
        cancelarLigacao('Fechamento do modal de questionário');
    }
    
    document.getElementById('modalRoteiroLigacao').style.display = 'none';
    
    // Parar timer
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    
    // Limpar variáveis
    ligacaoAtual = null;
    perguntasRoteiro = [];
    tempoInicio = null;
    
    console.log('Modal fechado e timer parado');
}

// Função para cancelar ligação
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
            // Marcar ligação como cancelada mas manter modal aberto
            ligacaoAtual.cancelada = true;
        } else {
            console.error('Erro ao cancelar ligação:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao processar cancelamento:', error);
    });
}

// Detectar fechamento da aba/janela
function configurarDetecaoCancelamento() {
    // Detectar fechamento da aba
    window.addEventListener('beforeunload', function(e) {
        if (ligacaoAtual && ligacaoAtual.id) {
            // Enviar requisição síncrona para cancelar a ligação
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'includes/gerenciar_ligacoes.php', false); // false = síncrono
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=cancelar_ligacao&ligacao_id=' + ligacaoAtual.id + '&motivo_cancelamento=Fechamento da aba/janela');
            
            console.log('Ligação cancelada por fechamento da aba');
        }
    });
    
    // Detectar quando o usuário sai da página (navegação)
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden' && ligacaoAtual && ligacaoAtual.id) {
            // Usuário saiu da página, mas não fechou a aba
            console.log('Usuário saiu da página - ligação pode ser cancelada');
        }
    });
    
    // Detectar clique no botão cancelar
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-roteiro-cancelar')) {
            if (ligacaoAtual && ligacaoAtual.id) {
                cancelarLigacao('Cancelamento manual pelo usuário');
            }
        }
    });
}

// Modal de Roteiro de Ligação
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded - ligacao.js carregado');
    
    // Configurar detecção de cancelamento
    configurarDetecaoCancelamento();
    
    // Adicionar modal de seleção de tipo de contato
    const modalTipoContatoHTML = `
        <div id="modalTipoContato" class="modal-overlay" style="display: none;">
            <div class="modal-content modal-tipo-contato">
                <div class="modal-header">
                    <h3><i class="fas fa-phone"></i> Tipo de Contato</h3>
                    <button class="modal-close" onclick="fecharModalTipoContato()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Como você está entrando em contato com o cliente?</p>
                    <div class="tipo-contato-options">
                        <button class="tipo-contato-btn" onclick="confirmarTipoContato('telefonica')">
                            <i class="fas fa-phone"></i>
                            <div class="btn-content">
                                <span>Ligação Telefônica</span>
                                <small>Inicia o ramal automaticamente</small>
                            </div>
                        </button>
                        <button class="tipo-contato-btn" onclick="confirmarTipoContato('presencial')">
                            <i class="fas fa-handshake"></i>
                            <div class="btn-content">
                                <span>Contato Presencial</span>
                                <small>Reunião ou visita ao cliente</small>
                            </div>
                        </button>
                        <button class="tipo-contato-btn" onclick="confirmarTipoContato('whatsapp')">
                            <i class="fab fa-whatsapp"></i>
                            <div class="btn-content">
                                <span>WhatsApp</span>
                                <small>Conversa via WhatsApp</small>
                            </div>
                        </button>
                        <button class="tipo-contato-btn" onclick="confirmarTipoContato('email')">
                            <i class="fas fa-envelope"></i>
                            <div class="btn-content">
                                <span>Email</span>
                                <small>Abrir Outlook Web</small>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal de roteiro
    const modalRoteiroHTML = `
        <div id="modalRoteiroLigacao" class="modal-roteiro" style="display: none;">
            <div class="modal-roteiro-content">
                <div class="modal-roteiro-header">
                    <div class="modal-roteiro-title">
                        <i class="fas fa-phone"></i>
                        <h3>Roteiro de Ligação</h3>
                    </div>
                    <button class="modal-roteiro-close" onclick="fecharModalRoteiro()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-roteiro-body">
                    <div class="roteiro-info">
                        <div class="roteiro-cliente">
                            <strong>Cliente:</strong> <span id="clienteInfo"></span>
                        </div>
                        <div class="roteiro-telefone">
                            <strong>Contato:</strong> <span id="telefoneInfo"></span>
                        </div>
                        <div class="roteiro-tipo">
                            <strong>Tipo:</strong> <span id="tipoContatoInfo"></span>
                        </div>
                        <div class="roteiro-tempo">
                            <i class="fas fa-clock"></i>
                            <span>Tempo: <span id="tempoLigacao">00:00</span></span>
                        </div>
                        <div class="roteiro-progresso">
                            <div class="roteiro-progresso-fill" id="progressoRoteiro" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div id="perguntasContainer">
                        <!-- As perguntas serão inseridas aqui dinamicamente -->
                    </div>
                    <div class="roteiro-acoes">
                        <button class="btn-roteiro btn-roteiro-cancelar" onclick="cancelarLigacao('Cancelamento manual pelo usuário')" id="btnCancelarLigacao">
                            <i class="fas fa-times"></i> Cancelar Ligação
                        </button>
                        <button class="btn-roteiro btn-roteiro-finalizar" onclick="finalizarLigacao()" id="btnFinalizarLigacao" disabled>
                            <i class="fas fa-check"></i> Finalizar Ligação
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Inserir modais no final do body
    document.body.insertAdjacentHTML('beforeend', modalTipoContatoHTML);
    document.body.insertAdjacentHTML('beforeend', modalRoteiroHTML);
    
    // Verificar se os modais foram criados corretamente
    const modalTipo = document.getElementById('modalTipoContato');
    const modalRoteiro = document.getElementById('modalRoteiroLigacao');
    
    if (modalTipo) {
        console.log('✅ Modal de tipo de contato criado com sucesso');
    } else {
        console.log('❌ Modal de tipo de contato não foi criado');
    }
    
    if (modalRoteiro) {
        console.log('✅ Modal de roteiro criado com sucesso');
    } else {
        console.log('❌ Modal de roteiro não foi criado');
    }
});
