// Orçamentos - JavaScript

// Variáveis globais
let orcamentosData = [];
let filtrosAplicados = {
    status: '',
    busca: ''
};
let itensOrcamento = [];
let contadorItens = 0;

document.addEventListener('DOMContentLoaded', function() {
    try {
        // Inicializar funcionalidades
        try {
            inicializarFiltros();
        } catch (e) {
            console.error('Erro ao inicializar filtros:', e);
        }
        
        try {
            inicializarTabela();
        } catch (e) {
            console.error('Erro ao inicializar tabela:', e);
        }
        
        try {
            inicializarModais();
        } catch (e) {
            console.error('Erro ao inicializar modais:', e);
        }
        
        // Aplicar filtros automaticamente quando a página carrega
        try {
            aplicarFiltros();
        } catch (e) {
            console.error('Erro ao aplicar filtros:', e);
        }
        
        // NÃO carregar formas de pagamento - as opções corretas já estão no HTML
        // carregarFormasPagamento(); // DESABILITADO
        
        // Monitorar e garantir que apenas opções corretas permaneçam
        const selectFormaPagamento = document.getElementById('forma-pagamento');
        if (selectFormaPagamento) {
            try {
                // Limpar imediatamente qualquer opção inválida que possa ter sido adicionada
                limparOpcoesInvalidasFormaPagamento();
            } catch (e) {
                console.warn('Erro ao limpar opções de forma de pagamento:', e);
            }
            
            // Verificar periodicamente e limpar opções inválidas
            setInterval(function() {
                try {
                    limparOpcoesInvalidasFormaPagamento();
                } catch (e) {
                    console.warn('Erro ao limpar opções de forma de pagamento (intervalo):', e);
                }
            }, 500); // Verificar a cada 0.5 segundos
            
            // Também verificar quando o modal é aberto
            const modal = document.getElementById('modal-orcamento');
            if (modal) {
                try {
                    const observer = new MutationObserver(function(mutations) {
                        try {
                            if (modal.classList.contains('active')) {
                                limparOpcoesInvalidasFormaPagamento();
                            }
                        } catch (e) {
                            console.warn('Erro no observer do modal:', e);
                        }
                    });
                    observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
                } catch (e) {
                    console.warn('Erro ao criar observer do modal:', e);
                }
            }
        }
        
        // Inicializar funcionalidade de forma de pagamento personalizada
        try {
            inicializarFormaPagamentoPersonalizada();
        } catch (e) {
            console.warn('Erro ao inicializar forma de pagamento personalizada:', e);
        }
        
        // Inicializar funcionalidade de cálculo de IPI
        try {
            inicializarCalculoIPI();
        } catch (e) {
            console.warn('Erro ao inicializar cálculo de IPI:', e);
        }
        
        // Adicionar listener para busca de cliente com Enter
        const campoBusca = document.getElementById('busca-cliente');
        if (campoBusca) {
            campoBusca.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    try {
                        buscarCliente();
                    } catch (err) {
                        console.error('Erro ao buscar cliente:', err);
                    }
                }
            });
        }
    } catch (error) {
        console.error('Erro crítico na inicialização da página:', error);
    }
});

// Inicializar filtros
function inicializarFiltros() {
    const filtroStatus = document.getElementById('filtro-status');
    const buscaOrcamento = document.getElementById('busca-orcamento');
    
    if (filtroStatus) {
        filtroStatus.addEventListener('change', function() {
            filtrosAplicados.status = this.value;
            aplicarFiltros();
        });
    }
    
    if (buscaOrcamento) {
        buscaOrcamento.addEventListener('input', debounce(function() {
            filtrosAplicados.busca = this.value.toLowerCase();
            aplicarFiltros();
        }, 300));
    }
}

// Inicializar tabela
function inicializarTabela() {
    const tabela = document.getElementById('tabela-orcamentos');
    if (tabela) {
        // Coletar dados da tabela
        const linhas = tabela.querySelectorAll('tbody tr');
        orcamentosData = Array.from(linhas).map(linha => {
            const celulas = linha.querySelectorAll('td');
            
            // Nova ordem: ID, Data Criação, Cliente, Produto, Valor, Pagamento, Validade, Vendedor/Criador, Aprovação Cliente, [Aprovação Gestor], Ações
            return {
                id: celulas[0].textContent.trim(),
                dataCriacao: celulas[1].textContent.trim(),
                cliente: celulas[2].textContent.trim(),
                produto: celulas[3].textContent.trim(),
                valor: celulas[4].textContent.trim(),
                pagamento: celulas[5].textContent.trim(),
                validade: celulas[6].textContent.trim(),
                vendedorCriador: celulas[7].textContent.trim(),
                status: linha.querySelector('.status-badge') ? linha.querySelector('.status-badge').textContent.trim().toLowerCase() : '',
                elemento: linha
            };
        });
    }
}

// Inicializar modais
function inicializarModais() {
    // Fechar modal ao clicar fora
    window.addEventListener('click', function(event) {
        const modalOrcamento = document.getElementById('modal-orcamento');
        const modalVisualizar = document.getElementById('modal-visualizar');
        const modalLinkAprovacao = document.getElementById('modal-link-aprovacao');
        
        if (event.target === modalOrcamento) {
            fecharModalOrcamento();
        }
        
        if (event.target === modalVisualizar) {
            fecharModalVisualizar();
        }
        
        if (event.target === modalLinkAprovacao) {
            fecharModalLinkAprovacao();
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            fecharModalOrcamento();
            fecharModalVisualizar();
            fecharModalLinkAprovacao();
        }
    });
}

// Aplicar filtros
function aplicarFiltros() {
    const tabela = document.getElementById('tabela-orcamentos');
    if (!tabela) return;
    
    const linhas = tabela.querySelectorAll('tbody tr');
    let linhasVisiveis = 0;
    
    linhas.forEach(linha => {
        const celulas = linha.querySelectorAll('td');
        
        // Detectar se a linha é a mensagem de sem resultados
        if (linha.id === 'mensagem-sem-resultados') {
            return;
        }
        
        // Nova ordem: ID[0], Data Criação[1], Cliente[2], Produto[3], Valor[4], Pagamento[5], Validade[6], Vendedor/Criador[7], Aprovação Cliente[8]
        const cliente = celulas[2] ? celulas[2].textContent.toLowerCase() : '';
        const produto = celulas[3] ? celulas[3].textContent.toLowerCase() : '';
        const vendedorCriador = celulas[7] ? celulas[7].textContent.toLowerCase() : '';
        const statusBadge = linha.querySelector('.status-badge');
        const status = statusBadge ? statusBadge.textContent.toLowerCase() : '';
        
        let mostrar = true;
        
        // Filtro por status
        if (filtrosAplicados.status && status !== filtrosAplicados.status) {
            mostrar = false;
        }
        
        // Filtro por busca
        if (filtrosAplicados.busca) {
            const textoCompleto = `${cliente} ${produto} ${vendedorCriador}`.toLowerCase();
            if (!textoCompleto.includes(filtrosAplicados.busca)) {
                mostrar = false;
            }
        }
        
        if (mostrar) {
            linha.style.display = '';
            linhasVisiveis++;
        } else {
            linha.style.display = 'none';
        }
    });
    
    // Mostrar mensagem se não houver resultados
    mostrarMensagemSemResultados(linhasVisiveis === 0);
}

// Mostrar mensagem quando não há resultados
function mostrarMensagemSemResultados(semResultados) {
    let mensagemExistente = document.getElementById('mensagem-sem-resultados');
    
    if (semResultados && !mensagemExistente) {
        const tabela = document.getElementById('tabela-orcamentos');
        const tbody = tabela.querySelector('tbody');
        
        // Contar número de colunas no cabeçalho
        const numColunas = tabela.querySelectorAll('thead th').length;
        
        const mensagem = document.createElement('tr');
        mensagem.id = 'mensagem-sem-resultados';
        mensagem.innerHTML = `
            <td colspan="${numColunas}" style="text-align: center; padding: 2rem; color: #64748b;">
                <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                <strong>Nenhum orçamento encontrado</strong><br>
                <small>Tente ajustar os filtros de busca</small>
            </td>
        `;
        
        tbody.appendChild(mensagem);
    } else if (!semResultados && mensagemExistente) {
        mensagemExistente.remove();
    }
}

// Limpar filtros
function limparFiltros() {
    document.getElementById('filtro-status').value = '';
    document.getElementById('busca-orcamento').value = '';
    
    filtrosAplicados = {
        status: '',
        busca: ''
    };
    
    aplicarFiltros();
}

// Abrir modal para novo orçamento
function abrirModalNovoOrcamento() {
    try {
        const modal = document.getElementById('modal-orcamento');
        if (!modal) {
            console.error('Modal não encontrado');
            mostrarNotificacao('Erro ao abrir modal. Recarregue a página.', 'error');
            return;
        }
        
        const titulo = document.getElementById('modal-titulo');
        const form = document.getElementById('form-orcamento');
        
        if (titulo) {
            titulo.textContent = 'Novo Orçamento';
        }
        
        // Reset do formulário
        if (form) {
            form.reset();
        }
        
        const orcamentoId = document.getElementById('orcamento-id');
        if (orcamentoId) {
            orcamentoId.value = '';
        }
        
        const status = document.getElementById('status');
        if (status) {
            status.value = 'pendente';
        }
        
        // Limpar campos de forma de pagamento personalizada
        const formaPagamento = document.getElementById('forma-pagamento');
        if (formaPagamento) {
            formaPagamento.value = '';
        }
        
        const formaPagamentoPersonalizada = document.getElementById('forma-pagamento-personalizada');
        if (formaPagamentoPersonalizada) {
            formaPagamentoPersonalizada.value = '';
        }
        
        const formaPagamentoPersonalizadaGroup = document.getElementById('forma-pagamento-personalizada-group');
        if (formaPagamentoPersonalizadaGroup) {
            formaPagamentoPersonalizadaGroup.style.display = 'none';
        }
        
        // Limpar tipo de faturamento
        const tipoServico = document.getElementById('tipo-servico');
        if (tipoServico) {
            tipoServico.checked = true;
        }
        
        const tipoProduto = document.getElementById('tipo-produto');
        if (tipoProduto) {
            tipoProduto.checked = false;
        }
        
        const ipiGroup = document.getElementById('ipi-calculo-group');
        if (ipiGroup) {
            ipiGroup.style.display = 'none';
        }
    
        // Aplicar classe inicial de cor (serviço por padrão)
        const tabelaContainer = document.querySelector('.tabela-itens-container');
        if (tabelaContainer) {
            tabelaContainer.classList.remove('tipo-servico', 'tipo-produto');
            tabelaContainer.classList.add('tipo-servico');
        }
        
        // Garantir que apenas opções válidas estão no select
        try {
            limparOpcoesInvalidasFormaPagamento();
        } catch (e) {
            console.warn('Erro ao limpar opções de forma de pagamento:', e);
        }
        
        // Limpar e inicializar tabela de itens
        try {
            limparTabelaItens();
        } catch (e) {
            console.error('Erro ao limpar tabela de itens:', e);
        }
        
        // Calcular data de validade (5 dias da emissão)
        try {
            calcularDataValidade();
        } catch (e) {
            console.warn('Erro ao calcular data de validade:', e);
        }
        
        // Preencher campos automaticamente com dados do usuário
        try {
            preencherDadosUsuario();
        } catch (e) {
            console.warn('Erro ao preencher dados do usuário:', e);
        }
        
        modal.classList.add('active');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Garantir que a tabela seja totalmente visível
        setTimeout(() => {
            const tabelaContainer = document.querySelector('.tabela-itens-container');
            if (tabelaContainer) {
                tabelaContainer.scrollLeft = 0;
            }
        }, 100);
        
        // Focar no primeiro campo
        setTimeout(() => {
            const clienteNome = document.getElementById('cliente-nome');
            if (clienteNome) {
                clienteNome.focus();
            }
        }, 100);
    } catch (error) {
        console.error('Erro ao abrir modal de novo orçamento:', error);
        mostrarNotificacao('Erro ao abrir modal. Verifique o console para mais detalhes.', 'error');
    }
}

// Calcular data de validade (5 dias da emissão)
function calcularDataValidade() {
    const hoje = new Date();
    const dataValidade = new Date(hoje);
    dataValidade.setDate(hoje.getDate() + 5);
    
    // Formatar para YYYY-MM-DD
    const ano = dataValidade.getFullYear();
    const mes = String(dataValidade.getMonth() + 1).padStart(2, '0');
    const dia = String(dataValidade.getDate()).padStart(2, '0');
    const dataFormatada = `${ano}-${mes}-${dia}`;
    
    // Definir a data no campo
    const campoDataValidade = document.getElementById('data-validade');
    if (campoDataValidade) {
        campoDataValidade.value = dataFormatada;
    }
}

// Preencher campos automaticamente com dados do usuário logado
function preencherDadosUsuario() {
    // Verificar se os dados do usuário estão disponíveis
    if (typeof window.dadosUsuario === 'undefined') {
        console.log('Dados do usuário não disponíveis');
        return;
    }
    
    const usuario = window.dadosUsuario;
    
    // Preencher código do vendedor se disponível
    if (usuario.cod_vendedor && usuario.cod_vendedor.trim() !== '') {
        const campoVendedor = document.getElementById('codigo-vendedor');
        if (campoVendedor) {
            campoVendedor.value = usuario.cod_vendedor;
            // Adicionar classe para indicar que foi preenchido automaticamente
            campoVendedor.classList.add('campo-auto-preenchido');
        }
    }
    
    // Preencher email do usuário se disponível (pode ser usado como referência)
    if (usuario.email && usuario.email.trim() !== '') {
        console.log('Email do usuário disponível:', usuario.email);
        
        // Opcional: Preencher email do cliente se for o mesmo usuário
        // const campoEmailCliente = document.getElementById('cliente-email');
        // if (campoEmailCliente && !campoEmailCliente.value) {
        //     campoEmailCliente.value = usuario.email;
        //     campoEmailCliente.classList.add('campo-auto-preenchido');
        // }
    }
    
    // Mostrar informações do usuário no modal
    mostrarInfoUsuario(usuario);
    
    // Log para debug
    console.log('Usuário logado:', usuario.nome, '| Código Vendedor:', usuario.cod_vendedor);
}

// Mostrar informações do usuário no modal
function mostrarInfoUsuario(usuario) {
    // Criar ou atualizar elemento de informações do usuário
    let infoUsuario = document.getElementById('info-usuario-modal');
    
    if (!infoUsuario) {
        // Criar elemento se não existir
        infoUsuario = document.createElement('div');
        infoUsuario.id = 'info-usuario-modal';
        infoUsuario.className = 'info-usuario-modal';
        
        // Adicionar após o cabeçalho do modal
        const modalHeader = document.querySelector('.modal-header');
        if (modalHeader) {
            modalHeader.appendChild(infoUsuario);
        }
    }
    
    // Preencher informações
    let infoText = '';
    if (usuario.nome) {
        infoText += `<span class="usuario-nome">👤 ${usuario.nome}</span>`;
    }
    if (usuario.cod_vendedor) {
        infoText += `<span class="codigo-vendedor">🏷️ ${usuario.cod_vendedor}</span>`;
    }
    if (usuario.perfil) {
        infoText += `<span class="perfil-usuario">🎯 ${usuario.perfil}</span>`;
    }
    
    infoUsuario.innerHTML = infoText + 
        `<button type="button" class="btn-limpar-campos" onclick="limparCamposAutoPreenchidos()" title="Limpar campos preenchidos automaticamente">
            🗑️ Limpar Auto-preenchimento
        </button>`;
}

// Limpar campos preenchidos automaticamente
function limparCamposAutoPreenchidos() {
    const camposAutoPreenchidos = document.querySelectorAll('.campo-auto-preenchido');
    
    camposAutoPreenchidos.forEach(campo => {
        campo.value = '';
        campo.classList.remove('campo-auto-preenchido');
    });
    
    mostrarNotificacao('Campos preenchidos automaticamente foram limpos', 'info');
}

// Editar orçamento
function editarOrcamento(id) {
    // Buscar dados do orçamento via AJAX
    fetch(`/Site/includes/ajax/orcamentos_ajax.php?action=buscar&id=${id}`, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const orcamento = data.data;
                
                const modal = document.getElementById('modal-orcamento');
                const titulo = document.getElementById('modal-titulo');
                
                titulo.textContent = 'Editar Orçamento';
                
                // Preencher formulário com dados existentes
                document.getElementById('orcamento-id').value = orcamento.id;
                document.getElementById('cliente-nome').value = orcamento.cliente_nome || '';
                document.getElementById('cliente-cnpj').value = orcamento.cliente_cnpj || '';
                document.getElementById('cliente-email').value = orcamento.cliente_email || '';
                document.getElementById('cliente-telefone').value = orcamento.cliente_telefone || '';
                document.getElementById('codigo-vendedor').value = orcamento.codigo_vendedor || '';
                // Verificar se a forma de pagamento é uma das opções padrão
                const opcoesPadrao = ['A VISTA', '7DDL', '14DDL', '21 DDL', '28 DDL', '28/35/42DDL', '28/42/56DDL', '30/45/60 DDL'];
                const formaPagamento = orcamento.forma_pagamento || '';
                
                if (opcoesPadrao.includes(formaPagamento)) {
                    document.getElementById('forma-pagamento').value = formaPagamento;
                    document.getElementById('forma-pagamento-personalizada-group').style.display = 'none';
                } else {
                    // Se não for uma opção padrão, usar OUTROS
                    document.getElementById('forma-pagamento').value = 'OUTROS';
                    document.getElementById('forma-pagamento-personalizada').value = formaPagamento;
                    document.getElementById('forma-pagamento-personalizada-group').style.display = 'block';
                }
                document.getElementById('valor-total').value = orcamento.valor_total || '0';
                document.getElementById('data-validade').value = orcamento.data_validade || '';
                document.getElementById('observacoes').value = orcamento.observacoes || '';
                
                // Configurar tipo de faturamento
                const tipoFaturamento = orcamento.tipo_faturamento || 'servico';
                if (tipoFaturamento === 'produto') {
                    document.getElementById('tipo-produto').checked = true;
                } else {
                    document.getElementById('tipo-servico').checked = true;
                }
                toggleCalculoIPI();
                
                // Carregar itens do orçamento
                if (orcamento.itens_orcamento) {
                    try {
                        const itens = JSON.parse(orcamento.itens_orcamento);
                        if (Array.isArray(itens) && itens.length > 0) {
                            itensOrcamento = itens.map((item, index) => ({
                                id: index + 1,
                                item: item.item || '',
                                descricao: item.descricao || '',
                                quantidade: item.quantidade || 1,
                                valor_unitario: item.valor_unitario || 0,
                                valor_total: item.valor_total || 0
                            }));
                            contadorItens = itensOrcamento.length;
                            renderizarTabelaItens();
                            calcularTotalGeral();
                        }
                    } catch (e) {
                        console.error('Erro ao carregar itens:', e);
                        limparTabelaItens();
                    }
                } else {
                    limparTabelaItens();
                }
                
                // Garantir que apenas opções válidas estão no select
                limparOpcoesInvalidasFormaPagamento();
                
                modal.classList.add('active');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                // Focar no primeiro campo
                setTimeout(() => {
                    document.getElementById('cliente-nome').focus();
                }, 100);
            } else {
                mostrarNotificacao(data.message || 'Erro ao carregar orçamento', 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar orçamento:', error);
            mostrarNotificacao('Erro ao carregar orçamento para edição', 'error');
        });
}

// Visualizar orçamento
function visualizarOrcamento(id) {
    const modal = document.getElementById('modal-visualizar');
    const conteudo = document.getElementById('conteudo-visualizar');
    
    // Mostrar loading
    conteudo.innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #3b82f6;"></i>
            <p>Carregando dados do orçamento...</p>
        </div>
    `;
    
    modal.classList.add('active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Configurar botão de PDF no modal
    const btnPdfModal = document.getElementById('btn-pdf-modal');
    if (btnPdfModal) {
        btnPdfModal.onclick = function() {
            gerarPDFOrcamento(id);
        };
    }
    
    // Buscar dados do orçamento via AJAX
    fetch(`/Site/includes/ajax/orcamentos_ajax.php?action=buscar&id=${id}`, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const orcamento = data.data;
            
            // Mapear status para classes CSS
            const statusMap = {
                'pendente': 'status-pendente',
                'aprovado': 'status-aprovado',
                'rejeitado': 'status-rejeitado',
                'cancelado': 'status-cancelado'
            };
            
            const statusLabels = {
                'pendente': 'Pendente',
                'aprovado': 'Aprovado',
                'rejeitado': 'Rejeitado',
                'cancelado': 'Cancelado'
            };
            
            // Processar itens do orçamento
            let itensHtml = '';
            if (orcamento.itens_orcamento) {
                try {
                    const itens = JSON.parse(orcamento.itens_orcamento);
                    if (Array.isArray(itens) && itens.length > 0) {
                        itensHtml = `
                            <div class="detalhes-section">
                                <h4><i class="fas fa-list"></i> Itens do Orçamento</h4>
                                <div class="tabela-itens-visualizacao">
                                    <table class="tabela-itens-detalhes">
                                        <thead>
                                            <tr>
                                                <th>ITEM</th>
                                                <th>DESCRIÇÃO</th>
                                                <th>QTD</th>
                                                <th>VLR UNITÁRIO</th>
                                                <th>VLR TOTAL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${itens.map(item => `
                                                <tr>
                                                    <td>${item.item || '-'}</td>
                                                    <td>${item.descricao || '-'}</td>
                                                    <td>${item.quantidade || 0}</td>
                                                    <td>R$ ${parseFloat(item.valor_unitario || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                                    <td>R$ ${parseFloat(item.valor_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    }
                } catch (e) {
                    console.error('Erro ao processar itens:', e);
                }
            }
            
            // Função para formatar forma de pagamento
            // Converte apenas valores antigos (a_vista, 28_ddl) para legível
            // Valores novos da TABLE 74 passam direto
            const formatarFormaPagamento = (forma) => {
                if (!forma) return 'Não informado';
                if (forma === 'a_vista') return 'À Vista';
                if (forma === '28_ddl') return '28 DDL';
                return forma; // Retorna direto o valor da TABLE 74
            };
            
            // Criar conteúdo do modal
            conteudo.innerHTML = `
                <div class="orcamento-detalhes" data-orcamento-id="${orcamento.id}">
                    <div class="detalhes-header">
                        <h3>Orçamento #${orcamento.id}</h3>
                        <span class="status-badge ${statusMap[orcamento.status]}">
                            ${statusLabels[orcamento.status]}
                        </span>
                    </div>
                    
                    <div class="detalhes-grid">
                        <div class="detalhes-section">
                            <h4><i class="fas fa-user"></i> Dados do Cliente</h4>
                            <div class="detalhes-item">
                                <label>Nome:</label>
                                <span>${orcamento.cliente_nome}</span>
                            </div>
                            <div class="detalhes-item">
                                <label>E-mail:</label>
                                <span>${orcamento.cliente_email || 'Não informado'}</span>
                            </div>
                            <div class="detalhes-item">
                                <label>Telefone:</label>
                                <span>${orcamento.cliente_telefone || 'Não informado'}</span>
                            </div>
                        </div>
                        
                        <div class="detalhes-section">
                            <h4><i class="fas fa-box"></i> Produto/Serviço</h4>
                            <div class="detalhes-item">
                                <label>Tipo:</label>
                                <span>${orcamento.tipo_produto_servico === 'produto' ? 'Produto' : orcamento.tipo_produto_servico === 'servico' ? 'Serviço' : 'Não informado'}</span>
                            </div>
                            <div class="detalhes-item">
                                <label>Descrição:</label>
                                <span>${orcamento.produto_servico}</span>
                            </div>
                            <div class="detalhes-item">
                                <label>Valor Total:</label>
                                <span class="valor-destaque">R$ ${parseFloat(orcamento.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="detalhes-item">
                                <label>Forma de Pagamento:</label>
                                <span>${formatarFormaPagamento(orcamento.forma_pagamento)}</span>
                            </div>
                            ${orcamento.descricao ? `
                            <div class="detalhes-item">
                                <label>Detalhes:</label>
                                <span>${orcamento.descricao}</span>
                            </div>
                            ` : ''}
                        </div>
                        
                        <div class="detalhes-section">
                            <h4><i class="fas fa-calendar"></i> Datas</h4>
                            <div class="detalhes-item">
                                <label>Data de Criação:</label>
                                <span>${new Date(orcamento.data_criacao).toLocaleDateString('pt-BR')}</span>
                            </div>
                            <div class="detalhes-item">
                                <label>Validade:</label>
                                <span>${orcamento.data_validade ? new Date(orcamento.data_validade).toLocaleDateString('pt-BR') : 'Não definida'}</span>
                            </div>
                            <div class="detalhes-item">
                                <label>Criado por:</label>
                                <span>${orcamento.usuario_criador}</span>
                            </div>
                        </div>
                        
                        ${orcamento.observacoes ? `
                        <div class="detalhes-section">
                            <h4><i class="fas fa-sticky-note"></i> Observações</h4>
                            <div class="detalhes-item">
                                <span>${orcamento.observacoes}</span>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${itensHtml}
                </div>
            `;
        } else {
            conteudo.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #dc2626;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        conteudo.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #dc2626;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <p>Erro ao carregar dados do orçamento.</p>
            </div>
        `;
    });
}

// Excluir orçamento
function excluirOrcamento(id) {
    if (confirm('Tem certeza que deseja excluir este orçamento?')) {
        const formData = new FormData();
        formData.append('action', 'excluir');
        formData.append('id', id);
        
        // Usar caminho dinâmico baseado no ambiente
        const ajaxUrl = (window.baseUrl || ((path) => {
            const base = window.BASE_PATH || '/Site';
            return base + (path.startsWith('/') ? path : '/' + path);
        }))('includes/ajax/orcamentos_ajax.php');
        
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            // Verificar se a resposta é JSON
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return response.json();
            } else {
                // Se não for JSON, tentar obter como texto primeiro
                return response.text().then(text => {
                    console.error('Resposta não é JSON:', text.substring(0, 500));
                    // Tentar fazer parse do JSON mesmo assim
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
                    }
                });
            }
        })
        .then(data => {
            if (data.success) {
                const linha = document.querySelector(`tr[data-id="${id}"]`);
                if (linha) {
                    linha.remove();
                }
                mostrarNotificacao(data.message, 'success');
            } else {
                mostrarNotificacao(data.message || 'Erro ao excluir orçamento', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarNotificacao('Erro ao excluir orçamento. Tente novamente.', 'error');
        });
    }
}

// Salvar orçamento
function salvarOrcamento() {
    const form = document.getElementById('form-orcamento');
    const formData = new FormData(form);
    
    // Validações básicas
    const clienteNome = formData.get('cliente_nome');
    const tipoProdutoServico = formData.get('tipo_produto_servico');
    const produtoServico = formData.get('produto_servico');
    const valorTotal = formData.get('valor_total');
    const codigoVendedor = formData.get('codigo_vendedor');
    const formaPagamento = formData.get('forma_pagamento');
    const formaPagamentoPersonalizada = formData.get('forma_pagamento_personalizada');
    const tipoFaturamento = formData.get('tipo_faturamento');
    
    // Determinar a forma de pagamento final
    let formaPagamentoFinal = formaPagamento;
    if (formaPagamento === 'OUTROS') {
        if (!formaPagamentoPersonalizada || formaPagamentoPersonalizada.trim() === '') {
            mostrarNotificacao('Por favor, preencha a descrição discertativa da forma de pagamento.', 'error');
            return;
        }
        formaPagamentoFinal = formaPagamentoPersonalizada.trim();
    }
    
    if (!clienteNome || !tipoProdutoServico || !produtoServico || !valorTotal || !formaPagamentoFinal || !tipoFaturamento) {
        mostrarNotificacao('Por favor, preencha todos os campos obrigatórios, incluindo o tipo de faturamento.', 'error');
        return;
    }
    
    // Verificar se o código do vendedor foi preenchido automaticamente
    if (!codigoVendedor) {
        mostrarNotificacao('Erro: Código do vendedor não foi preenchido automaticamente. Recarregue a página e tente novamente.', 'error');
        return;
    }
    
    // Atualizar o formData com a forma de pagamento final
    formData.set('forma_pagamento', formaPagamentoFinal);
    
    // Validar se há itens na tabela
    if (itensOrcamento.length === 0) {
        mostrarNotificacao('Adicione pelo menos um item ao orçamento.', 'error');
        return;
    }
    
    // Garantir que produto_servico esteja preenchido
    const campoProdutoServico = document.getElementById('produto-servico');
    if (campoProdutoServico && (!campoProdutoServico.value || campoProdutoServico.value.trim() === '')) {
        // Atualizar produto_servico com base nos itens
        calcularTotalGeral();
    }
    
    // Atualizar tipo_produto_servico baseado no tipo de faturamento (já obtido acima)
    if (tipoFaturamento) {
        formData.set('tipo_produto_servico', tipoFaturamento);
    }
    
    // Debug: Log dos dados dos itens
    const dadosItens = obterDadosItens();
    console.log('Dados dos itens antes do envio:', dadosItens);
    console.log('JSON dos itens:', JSON.stringify(dadosItens));
    
    // Adicionar dados dos itens
    formData.append('itens_orcamento', JSON.stringify(dadosItens));
    
    // Determinar ação (criar ou editar)
    const orcamentoId = formData.get('id');
    const action = orcamentoId ? 'editar' : 'criar';
    formData.append('action', action);
    
    // Debug: Log completo do FormData antes do envio
    console.log('=== ENVIANDO ORÇAMENTO ===');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + (pair[0] === 'itens_orcamento' ? '[JSON]' : pair[1]));
    }
    console.log('========================');
    
    // Fazer requisição AJAX - usar caminho dinâmico
    const ajaxUrl = (window.baseUrl || ((path) => {
        const base = window.BASE_PATH || '/Site';
        return base + (path.startsWith('/') ? path : '/' + path);
    }))('includes/ajax/orcamentos_ajax.php');
    
    fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return response.json();
        } else {
            // Se não for JSON, tentar obter como texto primeiro
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text.substring(0, 500));
                // Tentar fazer parse do JSON mesmo assim
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
                }
            });
        }
    })
    .then(data => {
        console.log('=== RESPOSTA DO SERVIDOR ===');
        console.log('Dados recebidos:', data);
        console.log('===========================');
        
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            fecharModalOrcamento();
            // Recarregar página para mostrar os dados atualizados
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Mostrar mensagem de erro mais detalhada
            const mensagemErro = data.message || 'Erro desconhecido ao salvar orçamento';
            console.error('Erro ao salvar:', mensagemErro);
            mostrarNotificacao(mensagemErro, 'error');
        }
    })
    .catch(error => {
        console.error('=== ERRO NA REQUISIÇÃO ===');
        console.error('Erro completo:', error);
        console.error('Mensagem:', error.message);
        console.error('Stack:', error.stack);
        console.error('========================');
        
        // Mostrar erro mais detalhado
        let mensagemErro = 'Erro ao salvar orçamento. ';
        if (error.message) {
            mensagemErro += error.message;
        } else {
            mensagemErro += 'Verifique o console para mais detalhes.';
        }
        mostrarNotificacao(mensagemErro, 'error');
    });
}

// Fechar modal de orçamento
function fecharModalOrcamento() {
    const modal = document.getElementById('modal-orcamento');
    modal.classList.remove('active');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Fechar modal de visualização
function fecharModalVisualizar() {
    const modal = document.getElementById('modal-visualizar');
    modal.classList.remove('active');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Controlar exibição do campo de descrição da origem
function toggleOrigemDescricao() {
    const origemSelect = document.getElementById('origem-cliente');
    const origemDescricaoGroup = document.getElementById('origem-descricao-group');
    
    if (origemSelect && origemDescricaoGroup) {
        const valor = origemSelect.value;
        
        // Mostrar campo de descrição para "outros"
        if (valor === 'outros') {
            origemDescricaoGroup.style.display = 'block';
            document.getElementById('origem-descricao').required = true;
        } else {
            origemDescricaoGroup.style.display = 'none';
            document.getElementById('origem-descricao').required = false;
            document.getElementById('origem-descricao').value = '';
        }
    }
}

// Gerar PDF do orçamento
function gerarPDFOrcamento(id) {
    if (!id) {
        mostrarNotificacao('ID do orçamento não encontrado', 'error');
        return;
    }
    
    try {
        // Abrir PDF em nova aba
        const url = `/Site/includes/pdf/gerar_pdf_orcamento.php?id=${id}`;
        console.log('Abrindo PDF:', url);
        
        const newWindow = window.open(url, '_blank');
        
        if (!newWindow) {
            mostrarNotificacao('Pop-up bloqueado. Permita pop-ups para este site.', 'error');
            return;
        }
        
        // Verificar se a janela foi aberta com sucesso
        setTimeout(() => {
            if (newWindow.closed) {
                mostrarNotificacao('Erro ao abrir PDF. Verifique se o orçamento existe.', 'error');
            }
        }, 2000);
        
    } catch (error) {
        console.error('Erro ao gerar PDF:', error);
        mostrarNotificacao('Erro ao gerar PDF: ' + error.message, 'error');
    }
}

// Aprovar orçamento
function aprovarOrcamento(id) {
    if (confirm('Tem certeza que deseja aprovar este orçamento?')) {
        const formData = new FormData();
        formData.append('action', 'aprovar');
        formData.append('id', id);
        
        // Usar caminho dinâmico baseado no ambiente
        const ajaxUrl = (window.baseUrl || ((path) => {
            const base = window.BASE_PATH || '/Site';
            return base + (path.startsWith('/') ? path : '/' + path);
        }))('includes/ajax/orcamentos_ajax.php');
        
        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            // Verificar se a resposta é JSON
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return response.json();
            } else {
                // Se não for JSON, tentar obter como texto primeiro
                return response.text().then(text => {
                    console.error('Resposta não é JSON:', text.substring(0, 500));
                    // Tentar fazer parse do JSON mesmo assim
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
                    }
                });
            }
        })
        .then(data => {
            if (data.success) {
                mostrarNotificacao(data.message, 'success');
                // Recarregar página para atualizar status
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                mostrarNotificacao(data.message || 'Erro ao aprovar orçamento', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarNotificacao('Erro ao aprovar orçamento. Tente novamente.', 'error');
        });
    }
}

// Rejeitar orçamento
function rejeitarOrcamento(id) {
    // Mostrar modal para capturar motivo da recusa
    mostrarModalRejeicao(id);
}

// Mostrar modal de rejeição com campo obrigatório para motivo
function mostrarModalRejeicao(id) {
    // Criar modal dinamicamente
    const modalHtml = `
        <div id="modal-rejeicao" class="modal active">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Rejeitar Orçamento</h2>
                    <button class="modal-close" onclick="fecharModalRejeicao()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="motivo-recusa">Motivo da Recusa *</label>
                        <textarea id="motivo-recusa" name="motivo_recusa" rows="4" 
                                  placeholder="Digite o motivo da recusa do orçamento..." 
                                  required style="width: 100%; resize: vertical;"></textarea>
                        <small class="form-help">Este campo é obrigatório para rejeitar o orçamento.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalRejeicao()">
                        Cancelar
                    </button>
                    <button type="button" class="btn-danger" onclick="confirmarRejeicao(${id})">
                        <i class="fas fa-times"></i>
                        Rejeitar Orçamento
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal ao body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Focar no campo de motivo
    setTimeout(() => {
        const campoMotivo = document.getElementById('motivo-recusa');
        if (campoMotivo) {
            campoMotivo.focus();
        }
    }, 100);
}

// Fechar modal de rejeição
function fecharModalRejeicao() {
    const modal = document.getElementById('modal-rejeicao');
    if (modal) {
        modal.remove();
    }
}

// Confirmar rejeição do orçamento
function confirmarRejeicao(id) {
    const campoMotivo = document.getElementById('motivo-recusa');
    if (!campoMotivo) {
        mostrarNotificacao('Erro: Campo de motivo não encontrado.', 'error');
        return;
    }
    
    const motivoRecusa = campoMotivo.value.trim();
    
    if (!motivoRecusa) {
        mostrarNotificacao('Por favor, informe o motivo da recusa.', 'error');
        campoMotivo.focus();
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'rejeitar');
    formData.append('id', id);
    formData.append('motivo_recusa', motivoRecusa);
    
    console.log('=== REJEITANDO ORÇAMENTO ===');
    console.log('ID:', id);
    console.log('Motivo:', motivoRecusa);
    console.log('===========================');
    
    // Usar caminho dinâmico baseado no ambiente
    const ajaxUrl = (window.baseUrl || ((path) => {
        const base = window.BASE_PATH || '/Site';
        return base + (path.startsWith('/') ? path : '/' + path);
    }))('includes/ajax/orcamentos_ajax.php');
    
    fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        console.log('=== RESPOSTA DO SERVIDOR (Rejeitar) ===');
        console.log('Status:', response.status);
        console.log('Status Text:', response.statusText);
        console.log('OK:', response.ok);
        console.log('Content-Type:', response.headers.get('content-type'));
        console.log('========================================');
        
        // Verificar content-type
        const contentType = response.headers.get('content-type') || '';
        
        if (contentType.includes('application/json')) {
            return response.json().catch(err => {
                // Se falhar ao fazer parse do JSON, tentar obter como texto primeiro
                return response.text().then(text => {
                    console.error('Erro ao fazer parse do JSON. Resposta recebida:', text.substring(0, 500));
                    throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
                });
            });
        } else {
            // Se não for JSON, tentar obter como texto primeiro
            return response.text().then(text => {
                console.log('Resposta recebida (primeiros 500 chars):', text.substring(0, 500));
                
                // Verificar se é HTML (geralmente indica redirecionamento para login)
                if (text.trim().startsWith('<!DOCTYPE html>') || text.trim().startsWith('<html')) {
                    console.error('⚠️ Resposta HTML detectada (provavelmente sessão expirada)');
                    return {
                        success: false,
                        session_expired: true,
                        message: 'Sessão expirada. Faça login novamente.'
                    };
                }
                
                // Tentar fazer parse do JSON mesmo que o content-type esteja errado
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Resposta não é JSON válido:', text.substring(0, 500));
                    throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
                }
            });
        }
    })
    .then(data => {
        console.log('=== DADOS RECEBIDOS ===');
        console.log('Dados:', JSON.stringify(data, null, 2));
        console.log('========================');
        
        // Verificar se a sessão expirou
        if (data.session_expired === true) {
            mostrarNotificacao('Sessão expirada. Redirecionando para login...', 'error');
            setTimeout(() => {
                window.location.href = '/Site/index.php';
            }, 1500);
            return;
        }
        
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            fecharModalRejeicao();
            // Recarregar página para atualizar status
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            mostrarNotificacao(data.message || 'Erro ao rejeitar orçamento', 'error');
        }
    })
    .catch(error => {
        console.error('=== ERRO NA REQUISIÇÃO ===');
        console.error('Erro completo:', error);
        console.error('Mensagem:', error.message);
        console.error('Stack:', error.stack);
        console.error('==========================');
        
        if (error.message.includes('Sessão expirada') || error.message.includes('401')) {
            mostrarNotificacao('Sessão expirada. Redirecionando para login...', 'error');
            setTimeout(() => {
                window.location.href = '/Site/index.php';
            }, 1500);
        } else {
            mostrarNotificacao('Erro ao rejeitar orçamento: ' + error.message, 'error');
        }
    });
}

// Gerar link de aprovação do cliente
function gerarLinkAprovacao(id) {
    const formData = new FormData();
    formData.append('action', 'gerar_link_aprovacao');
    formData.append('id', id);
    
    fetch('/Site/includes/ajax/orcamentos_ajax.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        console.log('=== RESPOSTA DO SERVIDOR (Gerar Link) ===');
        console.log('Status:', response.status);
        console.log('Status Text:', response.statusText);
        console.log('OK:', response.ok);
        console.log('Content-Type:', response.headers.get('content-type'));
        console.log('==========================================');
        
        // Verificar content-type
        const contentType = response.headers.get('content-type') || '';
        
        if (contentType.includes('application/json')) {
            return response.json();
        } else {
            // Se não for JSON, tentar obter como texto primeiro
            return response.text().then(text => {
                console.log('Resposta recebida (primeiros 500 chars):', text.substring(0, 500));
                
                // Verificar se é HTML (geralmente indica redirecionamento para login)
                if (text.trim().startsWith('<!DOCTYPE html>') || text.trim().startsWith('<html')) {
                    console.error('⚠️ Resposta HTML detectada (provavelmente sessão expirada)');
                    return {
                        success: false,
                        session_expired: true,
                        message: 'Sessão expirada. Faça login novamente.'
                    };
                }
                
                // Tentar fazer parse do JSON mesmo que o content-type esteja errado
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Resposta não é JSON válido:', text.substring(0, 500));
                    throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
                }
            });
        }
    })
    .then(data => {
        console.log('=== DADOS RECEBIDOS ===');
        console.log('Dados:', JSON.stringify(data, null, 2));
        console.log('========================');
        
        // Verificar se a sessão expirou
        if (data.session_expired === true) {
            mostrarNotificacao('Sessão expirada. Redirecionando para login...', 'error');
            setTimeout(() => {
                window.location.href = '/Site/index.php';
            }, 1500);
            return;
        }
        
        if (data.success) {
            // Mostrar modal com o link
            mostrarModalLinkAprovacao(data.link, data.orcamento);
        } else {
            mostrarNotificacao(data.message || 'Erro ao gerar link de aprovação', 'error');
        }
    })
    .catch(error => {
        console.error('=== ERRO NA REQUISIÇÃO ===');
        console.error('Erro completo:', error);
        console.error('Mensagem:', error.message);
        console.error('Stack:', error.stack);
        console.error('==========================');
        
        if (error.message.includes('Sessão expirada') || error.message.includes('401')) {
            mostrarNotificacao('Sessão expirada. Redirecionando para login...', 'error');
            setTimeout(() => {
                window.location.href = '/Site/index.php';
            }, 1500);
        } else {
            mostrarNotificacao('Erro ao gerar link de aprovação: ' + error.message, 'error');
        }
    });
}

// Mostrar modal com link de aprovação
function mostrarModalLinkAprovacao(link, orcamento) {
    // Criar modal se não existir
    let modal = document.getElementById('modal-link-aprovacao');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal-link-aprovacao';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content modal-medium">
                <div class="modal-header">
                    <h2><i class="fas fa-link"></i> Link de Aprovação</h2>
                    <button class="modal-close" onclick="fecharModalLinkAprovacao()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="link-aprovacao-container">
                        <div class="orcamento-info">
                            <h4>Orçamento #${orcamento.id}</h4>
                            <p><strong>Cliente:</strong> ${orcamento.cliente_nome}</p>
                            <p><strong>Valor:</strong> R$ ${parseFloat(orcamento.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                        </div>
                        
                        <div class="link-container">
                            <label for="link-aprovacao">Link para aprovação do cliente:</label>
                            <div class="input-group">
                                <input type="text" id="link-aprovacao" value="${link}" readonly>
                                <button class="btn btn-secondary" onclick="copiarLink()" title="Copiar link">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="instrucoes">
                            <h5><i class="fas fa-info-circle"></i> Instruções:</h5>
                            <ul>
                                <li>Envie este link para o cliente por e-mail ou WhatsApp</li>
                                <li>O cliente poderá visualizar e aprovar o orçamento</li>
                                <li>Você receberá uma notificação quando o cliente aprovar</li>
                                <li>O link expira em 7 dias</li>
                            </ul>
                        </div>
                        
                        <div class="acoes-link">
                            <button class="btn btn-primary" onclick="enviarPorEmail(${orcamento.id})">
                                <i class="fas fa-envelope"></i> Enviar por E-mail
                            </button>
                            <button class="btn btn-success" onclick="enviarPorWhatsApp('${link}')">
                                <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalLinkAprovacao()">
                        Fechar
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Atualizar conteúdo
    const linkInput = modal.querySelector('#link-aprovacao');
    const orcamentoInfo = modal.querySelector('.orcamento-info h4');
    const clienteInfo = modal.querySelector('.orcamento-info p:nth-child(2)');
    const valorInfo = modal.querySelector('.orcamento-info p:nth-child(3)');
    
    linkInput.value = link;
    orcamentoInfo.textContent = `Orçamento #${orcamento.id}`;
    clienteInfo.innerHTML = `<strong>Cliente:</strong> ${orcamento.cliente_nome}`;
    valorInfo.innerHTML = `<strong>Valor:</strong> R$ ${parseFloat(orcamento.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
    
    modal.classList.add('active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Focar no input do link
    setTimeout(() => {
        linkInput.select();
    }, 100);
}

// Fechar modal de link de aprovação
function fecharModalLinkAprovacao() {
    const modal = document.getElementById('modal-link-aprovacao');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Copiar link para área de transferência
function copiarLink() {
    const linkInput = document.getElementById('link-aprovacao');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999); // Para dispositivos móveis
    
    try {
        document.execCommand('copy');
        mostrarNotificacao('Link copiado para a área de transferência!', 'success');
    } catch (err) {
        // Fallback para navegadores modernos
        navigator.clipboard.writeText(linkInput.value).then(() => {
            mostrarNotificacao('Link copiado para a área de transferência!', 'success');
        }).catch(() => {
            mostrarNotificacao('Erro ao copiar link. Selecione e copie manualmente.', 'error');
        });
    }
}

// Enviar link por e-mail
function enviarPorEmail(orcamentoId) {
    const formData = new FormData();
    formData.append('action', 'enviar_email_aprovacao');
    formData.append('id', orcamentoId);
    
    fetch('../includes/ajax/orcamentos_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                mostrarNotificacao(data.message, 'success');
            } else {
                mostrarNotificacao(data.message, 'error');
            }
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Texto recebido:', text);
            mostrarNotificacao('Erro no formato da resposta do servidor.', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao enviar e-mail. Tente novamente.', 'error');
    });
}

// Enviar link por WhatsApp
function enviarPorWhatsApp(link) {
    const mensagem = `Olá! Você recebeu um orçamento para aprovação. Clique no link abaixo para visualizar e aprovar:\n\n${link}`;
    const urlWhatsApp = `https://wa.me/?text=${encodeURIComponent(mensagem)}`;
    window.open(urlWhatsApp, '_blank');
}

// Imprimir orçamento
function imprimirOrcamento() {
    const conteudo = document.getElementById('conteudo-visualizar').innerHTML;
    const janelaImpressao = window.open('', '_blank');
    
    janelaImpressao.document.write(`
        <html>
            <head>
                <title>Orçamento</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .detalhes-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                    .detalhes-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                    .detalhes-section { border: 1px solid #ddd; padding: 15px; border-radius: 8px; }
                    .detalhes-section h4 { margin: 0 0 15px 0; color: #333; }
                    .detalhes-item { margin-bottom: 10px; }
                    .detalhes-item label { font-weight: bold; display: inline-block; width: 120px; }
                    .valor-destaque { font-size: 1.2em; font-weight: bold; color: #059669; }
                    .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8em; font-weight: bold; }
                    .status-pendente { background: #fef3c7; color: #92400e; }
                    .status-aprovado { background: #d1fae5; color: #065f46; }
                    .status-rejeitado { background: #fee2e2; color: #991b1b; }
                    .status-cancelado { background: #f3f4f6; color: #374151; }
                </style>
            </head>
            <body>
                ${conteudo}
            </body>
        </html>
    `);
    
    janelaImpressao.document.close();
    janelaImpressao.print();
}

// Mostrar notificação
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Remover notificação existente
    const notificacaoExistente = document.querySelector('.notificacao');
    if (notificacaoExistente) {
        notificacaoExistente.remove();
    }
    
    // Criar nova notificação
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao notificacao-${tipo}`;
    notificacao.innerHTML = `
        <div class="notificacao-conteudo">
            <i class="fas fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${mensagem}</span>
        </div>
    `;
    
    // Adicionar estilos
    notificacao.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${tipo === 'success' ? '#d1fae5' : tipo === 'error' ? '#fee2e2' : '#dbeafe'};
        color: ${tipo === 'success' ? '#065f46' : tipo === 'error' ? '#991b1b' : '#1d4ed8'};
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notificacao);
    
    // Remover após 3 segundos
    setTimeout(() => {
        notificacao.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notificacao.parentNode) {
                notificacao.remove();
            }
        }, 300);
    }, 3000);
}

// Função debounce para otimizar busca
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

// Adicionar estilos CSS para animações
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .notificacao-conteudo {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
    }
    
    .orcamento-detalhes {
        max-width: 100%;
    }
    
    .detalhes-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .detalhes-header h3 {
        margin: 0;
        color: #1e293b;
        font-size: 1.5rem;
    }
    
    .detalhes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    .detalhes-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.5rem;
    }
    
    .detalhes-section h4 {
        margin: 0 0 1rem 0;
        color: #374151;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .detalhes-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .detalhes-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .detalhes-item label {
        font-weight: 600;
        color: #64748b;
        font-size: 0.875rem;
    }
    
    .detalhes-item span {
        color: #1e293b;
        font-weight: 500;
    }
    
    .valor-destaque {
        font-size: 1.25rem;
        font-weight: 700;
        color: #059669;
    }
    
    .tabela-itens-visualizacao {
        margin-top: 1rem;
        overflow-x: auto;
    }
    
    .tabela-itens-detalhes {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    
    .tabela-itens-detalhes th,
    .tabela-itens-detalhes td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .tabela-itens-detalhes th {
        background: #f8fafc;
        font-weight: 600;
        color: #374151;
    }
    
    .tabela-itens-detalhes tbody tr:hover {
        background: #f8fafc;
    }
    
    .tabela-itens-detalhes td:last-child {
        font-weight: 600;
        color: #059669;
    }
    
    /* Estilos para badges de aprovação */
    .aprovacao-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
    }
    
    .aprovacao-aprovado {
        background: #d1fae5;
        color: #065f46;
    }
    
    .aprovacao-pendente {
        background: #fef3c7;
        color: #92400e;
    }
    
    .aprovacao-nao-enviado {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* Estilos para modal de link de aprovação */
    .link-aprovacao-container {
        max-width: 100%;
    }
    
    .orcamento-info {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .orcamento-info h4 {
        margin: 0 0 0.5rem 0;
        color: #1e293b;
        font-size: 1.125rem;
    }
    
    .orcamento-info p {
        margin: 0.25rem 0;
        color: #64748b;
        font-size: 0.875rem;
    }
    
    .link-container {
        margin-bottom: 1.5rem;
    }
    
    .link-container label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #374151;
    }
    
    .input-group {
        display: flex;
        gap: 0.5rem;
    }
    
    .input-group input {
        flex: 1;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.875rem;
        background: #f9fafb;
    }
    
    .input-group button {
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #f3f4f6;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .input-group button:hover {
        background: #e5e7eb;
    }
    
    .instrucoes {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .instrucoes h5 {
        margin: 0 0 0.75rem 0;
        color: #1d4ed8;
        font-size: 0.875rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .instrucoes ul {
        margin: 0;
        padding-left: 1.25rem;
        color: #1e40af;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .instrucoes li {
        margin-bottom: 0.25rem;
    }
    
    .acoes-link {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .acoes-link .btn {
        flex: 1;
        min-width: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    @media (max-width: 768px) {
        .detalhes-grid {
            grid-template-columns: 1fr;
        }
        
        .detalhes-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .detalhes-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
        }
        
        .tabela-itens-detalhes {
            font-size: 0.75rem;
        }
        
        .tabela-itens-detalhes th,
        .tabela-itens-detalhes td {
            padding: 0.5rem;
        }
        
        .acoes-link {
            flex-direction: column;
        }
        
        .acoes-link .btn {
            min-width: auto;
        }
        
        .input-group {
            flex-direction: column;
        }
    }
`;
document.head.appendChild(style);

// ===== FUNÇÕES DA TABELA DE ITENS =====

// Adicionar novo item à tabela
function adicionarItem() {
    contadorItens++;
    
    const novoItem = {
        id: contadorItens,
        item: '',
        descricao: '',
        quantidade: 1,
        valor_unitario: 0,
        valor_total: 0
    };
    
    itensOrcamento.push(novoItem);
    
    // Debug: Log do novo item
    console.log('Novo item adicionado:', novoItem);
    console.log('Array de itens atual:', itensOrcamento);
    
    renderizarTabelaItens();
    
    // Focar no primeiro campo do novo item
    setTimeout(() => {
        const inputItem = document.querySelector(`input[name="item_${contadorItens}"]`);
        if (inputItem) {
            inputItem.focus();
        }
    }, 100);
}

// Remover item da tabela
function removerItem(id) {
    itensOrcamento = itensOrcamento.filter(item => item.id !== id);
    renderizarTabelaItens();
    calcularTotalGeral();
}

// Renderizar tabela de itens
function renderizarTabelaItens() {
    const tbody = document.getElementById('itens-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    itensOrcamento.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="text" name="item_${item.id}" value="${item.item}" 
                       onchange="atualizarItem(${item.id}, 'item', this.value)" 
                       onblur="buscarProdutoPorCodigo(${item.id}, this.value)"
                       placeholder="Código do produto" class="input-item">
            </td>
            <td>
                <input type="text" name="descricao_${item.id}" value="${item.descricao}" 
                       onchange="atualizarItem(${item.id}, 'descricao', this.value)" 
                       placeholder="Descrição do item" class="input-descricao">
            </td>
            <td>
                <input type="number" name="quantidade_${item.id}" value="${item.quantidade}" 
                       onchange="atualizarItem(${item.id}, 'quantidade', this.value)" 
                       min="1" step="1" class="input-quantidade">
            </td>
            <td>
                <input type="number" name="valor_unitario_${item.id}" value="${item.valor_unitario}" 
                       onchange="atualizarItem(${item.id}, 'valor_unitario', this.value)" 
                       min="0" step="0.01" placeholder="0,00" class="input-valor-unitario">
            </td>
            <td>
                <span class="valor-total-item" id="total_${item.id}">R$ 0,00</span>
            </td>
            <td>
                <button type="button" class="btn-remover-item" onclick="removerItem(${item.id})" title="Remover item">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Se não há itens, mostrar mensagem
    if (itensOrcamento.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                <i class="fas fa-plus-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                <strong style="cursor: pointer; color: #3b82f6; text-decoration: underline; transition: all 0.3s ease;" 
                        onclick="adicionarItem()" 
                        onmouseover="this.style.color='#1d4ed8'; this.style.transform='scale(1.05)'" 
                        onmouseout="this.style.color='#3b82f6'; this.style.transform='scale(1)'" 
                        title="Clique para adicionar um item">Nenhum item adicionado</strong><br>
                <small>Clique em "Adicionar Item" ou no texto acima para começar</small>
            </td>
        `;
        tbody.appendChild(row);
    }
}

// Atualizar item específico
function atualizarItem(id, campo, valor) {
    const item = itensOrcamento.find(i => i.id === id);
    if (!item) {
        console.error('Item não encontrado com ID:', id);
        return;
    }
    
    // Debug: Log da atualização
    console.log(`Atualizando item ${id}, campo: ${campo}, valor: ${valor}`);
    
    // Atualizar campo específico
    if (campo === 'quantidade' || campo === 'valor_unitario') {
        item[campo] = parseFloat(valor) || 0;
    } else {
        item[campo] = valor;
    }
    
    // Calcular valor total do item
    item.valor_total = item.quantidade * item.valor_unitario;
    
    // Debug: Log do item atualizado
    console.log('Item atualizado:', item);
    
    // Atualizar exibição do valor total do item
    const elementoTotal = document.getElementById(`total_${id}`);
    if (elementoTotal) {
        elementoTotal.textContent = `R$ ${item.valor_total.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
    }
    
    // Calcular total geral
    calcularTotalGeral();
}

// Calcular total geral
function calcularTotalGeral() {
    const totalGeral = itensOrcamento.reduce((soma, item) => soma + item.valor_total, 0);
    
    // Atualizar exibição do total geral
    const elementoTotalGeral = document.getElementById('total-geral');
    if (elementoTotalGeral) {
        elementoTotalGeral.textContent = `R$ ${totalGeral.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
    }
    
    // Atualizar campo valor total do formulário
    const campoValorTotal = document.getElementById('valor-total');
    if (campoValorTotal) {
        campoValorTotal.value = totalGeral.toFixed(2);
    }
    
    // Atualizar campo produto_servico com descrição resumida dos itens
    const campoProdutoServico = document.getElementById('produto-servico');
    if (campoProdutoServico) {
        if (itensOrcamento.length > 0) {
            // Criar lista resumida dos produtos (máximo 3)
            const produtosResumidos = itensOrcamento
                .slice(0, 3)
                .map(item => item.descricao || item.item)
                .filter(desc => desc && desc.trim() !== '')
                .join(', ');
            
            // Se houver mais de 3 itens, adicionar "e mais X"
            const totalItens = itensOrcamento.length;
            const descricaoFinal = totalItens > 3 
                ? `${produtosResumidos} e mais ${totalItens - 3} item(ns)`
                : produtosResumidos || 'Produto';
            
            campoProdutoServico.value = descricaoFinal;
        } else {
            // Se não houver itens, manter o valor padrão ou definir um valor genérico
            const tipoFaturamento = document.querySelector('input[name="tipo_faturamento"]:checked');
            if (tipoFaturamento) {
                campoProdutoServico.value = tipoFaturamento.value === 'produto' ? 'Produto' : 'Serviço';
            } else {
                campoProdutoServico.value = 'Produto';
            }
        }
    }
    
    // Recalcular IPI se necessário
    calcularIPI();
}

// Limpar tabela de itens
function limparTabelaItens() {
    itensOrcamento = [];
    contadorItens = 0;
    renderizarTabelaItens();
    calcularTotalGeral();
}

// Obter dados dos itens para envio
function obterDadosItens() {
    return itensOrcamento.map(item => ({
        item: item.item,
        descricao: item.descricao,
        quantidade: item.quantidade,
        valor_unitario: item.valor_unitario,
        valor_total: item.valor_total
    }));
}

// Buscar cliente por CNPJ ou nome
function buscarCliente() {
    const termoBusca = document.getElementById('busca-cliente').value.trim();
    
    if (!termoBusca) {
        mostrarNotificacao('Digite um CNPJ ou nome do cliente', 'error');
        return;
    }
    
    // Mostrar loading
    mostrarNotificacao('Buscando cliente...', 'info');
    
    console.log('=== INICIANDO BUSCA DE CLIENTE ===');
    console.log('Termo de busca:', termoBusca);
    
    // Usar caminho dinâmico baseado no ambiente
    const apiUrl = (window.baseUrl || ((path) => {
        const base = window.BASE_PATH || '/Site';
        return base + (path.startsWith('/') ? path : '/' + path);
    }))('includes/api/buscar_cliente_orcamento.php');
    
    const urlFinal = `${apiUrl}?termo=${encodeURIComponent(termoBusca)}`;
    console.log('URL:', urlFinal);
    
    fetch(urlFinal, {
        credentials: 'same-origin', // Garantir que cookies de sessão sejam enviados
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            console.log('=== RESPOSTA DO SERVIDOR ===');
            console.log('Status:', response.status);
            console.log('Status Text:', response.statusText);
            console.log('OK:', response.ok);
            console.log('Content-Type:', response.headers.get('content-type'));
            console.log('============================');
            
            // Tentar obter como JSON primeiro
            const contentType = response.headers.get('content-type') || '';
            
            if (contentType.includes('application/json')) {
                return response.json().catch(err => {
                    // Se falhar ao fazer parse do JSON, tentar obter como texto primeiro
                    return response.text().then(text => {
                        console.error('Erro ao fazer parse do JSON. Resposta recebida:', text.substring(0, 500));
                        throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
                    });
                });
            } else {
                // Se não for JSON, verificar o que foi retornado
                return response.text().then(text => {
                    console.log('Resposta recebida (primeiros 500 chars):', text.substring(0, 500));
                    
                    // Verificar se é HTML (geralmente indica redirecionamento para login)
                    if (text.trim().startsWith('<!DOCTYPE html>') || text.trim().startsWith('<html')) {
                        console.error('⚠️ Resposta HTML detectada (provavelmente sessão expirada)');
                        return {
                            success: false,
                            session_expired: true,
                            message: 'Sessão expirada. Faça login novamente.'
                        };
                    }
                    
                    // Tentar fazer parse do JSON mesmo que o content-type esteja errado
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Resposta não é JSON válido:', text.substring(0, 500));
                        throw new Error('Resposta do servidor não é JSON válido');
                    }
                });
            }
        })
        .then(data => {
            // Debug: Log completo da resposta
            console.log('=== DEBUG BUSCA CLIENTE ===');
            console.log('Resposta completa:', JSON.stringify(data, null, 2));
            console.log('session_expired:', data.session_expired);
            console.log('success:', data.success);
            console.log('message:', data.message);
            console.log('data:', data.data);
            console.log('==========================');
            
            // Verificar se a sessão expirou (apenas se for explicitamente marcado)
            if (data.session_expired === true) {
                console.log('⚠️ Sessão expirada detectada');
                mostrarNotificacao('Sessão expirada. Redirecionando para login...', 'error');
                setTimeout(() => {
                    window.location.href = '/Site/index.php';
                }, 1500);
                return;
            }
            
            if (data.success && data.data && Array.isArray(data.data)) {
                // Se houver múltiplos resultados, mostrar modal de seleção
                if (data.multiple && data.data.length > 1) {
                    mostrarModalSelecaoCliente(data.data);
                } else {
                    // Apenas um resultado, preencher automaticamente
                    const cliente = data.data[0];
                    if (cliente) {
                        preencherDadosCliente(cliente);
                        mostrarNotificacao(`Cliente encontrado: ${cliente.cliente_nome}`, 'success');
                    } else {
                        mostrarNotificacao('Cliente não encontrado', 'error');
                    }
                }
            } else {
                // Mostrar mensagem de erro apenas se não for sessão expirada
                mostrarNotificacao(data.message || 'Cliente não encontrado', 'error');
            }
        })
        .catch(error => {
            console.error('=== ERRO NA BUSCA DE CLIENTE ===');
            console.error('Erro completo:', error);
            console.error('Mensagem:', error.message);
            console.error('Stack:', error.stack);
            console.error('================================');
            
            if (error.message.includes('Sessão expirada') || error.message.includes('401')) {
                mostrarNotificacao('Sessão expirada. Redirecionando para login...', 'error');
                setTimeout(() => {
                    window.location.href = '/Site/index.php';
                }, 1500);
            } else {
                mostrarNotificacao('Erro ao buscar cliente: ' + error.message, 'error');
            }
        });
}

// Preencher dados do cliente nos campos do formulário
function preencherDadosCliente(cliente) {
    document.getElementById('cliente-nome').value = cliente.cliente_nome || '';
    document.getElementById('cliente-cnpj').value = cliente.cnpj || '';
    document.getElementById('cliente-email').value = cliente.email || '';
    document.getElementById('cliente-telefone').value = cliente.telefone || '';
    
    // Adicionar classes de campo preenchido automaticamente
    document.getElementById('cliente-nome').classList.add('campo-auto-preenchido');
    document.getElementById('cliente-cnpj').classList.add('campo-auto-preenchido');
    document.getElementById('cliente-email').classList.add('campo-auto-preenchido');
    document.getElementById('cliente-telefone').classList.add('campo-auto-preenchido');
}

// Mostrar modal de seleção de cliente
function mostrarModalSelecaoCliente(clientes) {
    // Criar modal se não existir
    let modal = document.getElementById('modal-selecao-cliente');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal-selecao-cliente';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h2><i class="fas fa-users"></i> Selecionar Cliente</h2>
                    <button class="modal-close" onclick="fecharModalSelecaoCliente()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Encontrados ${clientes.length} clientes. Selecione o cliente desejado:</p>
                    <div class="lista-clientes" id="lista-clientes">
                        <!-- Lista será preenchida aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalSelecaoCliente()">
                        Cancelar
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Preencher lista de clientes
    const listaClientes = document.getElementById('lista-clientes');
    listaClientes.innerHTML = '';
    
    clientes.forEach((cliente, index) => {
        const cardCliente = document.createElement('div');
        cardCliente.className = 'card-cliente';
        cardCliente.onclick = () => {
            preencherDadosCliente(cliente);
            fecharModalSelecaoCliente();
            const origem = cliente.origem === 'lead' ? 'Lead' : 'Cliente';
            mostrarNotificacao(`${origem} selecionado: ${cliente.cliente_nome}`, 'success');
        };
        
        // Badge de origem (Lead ou Cliente)
        const origemBadge = cliente.origem === 'lead' 
            ? '<span class="badge-origem badge-lead"><i class="fas fa-user-tie"></i> LEAD</span>'
            : '<span class="badge-origem badge-cliente"><i class="fas fa-building"></i> CLIENTE</span>';
        
        cardCliente.innerHTML = `
            <div class="card-header-with-badge">
                <div class="cliente-nome">${cliente.cliente_nome || ''}</div>
                ${origemBadge}
            </div>
            ${cliente.nome_fantasia ? `<div class="cliente-fantasia">${cliente.nome_fantasia}</div>` : ''}
            ${cliente.cnpj ? `<div class="cliente-cnpj"><i class="fas fa-id-card"></i> ${cliente.cnpj}</div>` : ''}
            <div class="cliente-info">
                ${cliente.email ? `<div><i class="fas fa-envelope"></i> ${cliente.email}</div>` : ''}
                ${cliente.telefone ? `<div><i class="fas fa-phone"></i> ${cliente.telefone}</div>` : ''}
                ${cliente.endereco ? `<div><i class="fas fa-map-marker-alt"></i> ${cliente.endereco}, ${cliente.estado || ''}</div>` : ''}
            </div>
        `;
        
        listaClientes.appendChild(cardCliente);
    });
    
    // Mostrar modal
    modal.classList.add('active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Fechar modal de seleção de cliente
function fecharModalSelecaoCliente() {
    const modal = document.getElementById('modal-selecao-cliente');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Buscar produto por código na tabela FATURAMENTO
function buscarProdutoPorCodigo(itemId, codigoProduto) {
    if (!codigoProduto || codigoProduto.trim() === '') {
        return;
    }
    
    // Buscar nome do produto via API - usar caminho dinâmico
    const apiUrl = (window.baseUrl || ((path) => {
        const base = window.BASE_PATH || '/Site';
        return base + (path.startsWith('/') ? path : '/' + path);
    }))('includes/api/buscar_produto.php');
    
    fetch(`${apiUrl}?codigo=${encodeURIComponent(codigoProduto)}`, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const produto = data.data;
                
                // Atualizar descrição do item com o nome do produto
                const descricaoInput = document.querySelector(`input[name="descricao_${itemId}"]`);
                if (descricaoInput) {
                    descricaoInput.value = produto.nome;
                    atualizarItem(itemId, 'descricao', produto.nome);
                }
                
                // Mostrar notificação de sucesso
                mostrarNotificacao(`Produto encontrado: ${produto.nome}`, 'success');
            } else {
                mostrarNotificacao(data.message || 'Produto não encontrado', 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar produto:', error);
            mostrarNotificacao('Erro ao buscar produto', 'error');
        });
}

// Limpar opções inválidas de forma de pagamento
function limparOpcoesInvalidasFormaPagamento() {
    const selectFormaPagamento = document.getElementById('forma-pagamento');
    if (!selectFormaPagamento) return;
    
    // Lista EXATA de opções permitidas
    const opcoesPermitidas = ['', 'A VISTA', '7DDL', '14DDL', '21 DDL', '28 DDL', '28/35/42DDL', '28/42/56DDL', '30/45/60 DDL', 'OUTROS'];
    
    // Capturar valor selecionado
    const valorSelecionado = selectFormaPagamento.value;
    
    // Debug: Log das opções atuais
    const opcoesAtuais = Array.from(selectFormaPagamento.options).map(opt => opt.value);
    console.log('Opções atuais antes da limpeza:', opcoesAtuais);
    
    // Verificar cada opção e remover as que não estão na lista permitida
    const opcoes = Array.from(selectFormaPagamento.options);
    let opcoesRemovidas = false;
    
    opcoes.forEach(option => {
        if (!opcoesPermitidas.includes(option.value)) {
            console.log('Removendo opção inválida:', option.value, option.textContent);
            option.remove();
            opcoesRemovidas = true;
        }
    });
    
    // Se opções foram removidas, restaurar valor selecionado se ainda for válido
    if (opcoesRemovidas && valorSelecionado && opcoesPermitidas.includes(valorSelecionado)) {
        selectFormaPagamento.value = valorSelecionado;
    }
    
    // Debug: Log das opções após limpeza
    const opcoesFinais = Array.from(selectFormaPagamento.options).map(opt => opt.value);
    console.log('Opções finais após limpeza:', opcoesFinais);
}

// Carregar formas de pagamento disponíveis
function carregarFormasPagamento() {
    // NÃO buscar dados da TABLE 74 - usar apenas as opções fixas
    const selectFormaPagamento = document.getElementById('forma-pagamento');
    if (selectFormaPagamento) {
        // Lista EXATA de opções permitidas
        const opcoesCorretas = [
            { value: '', text: 'Selecione a forma de pagamento' },
            { value: 'A VISTA', text: 'A VISTA' },
            { value: '7DDL', text: '7DDL' },
            { value: '14DDL', text: '14DDL' },
            { value: '21 DDL', text: '21 DDL' },
            { value: '28 DDL', text: '28 DDL' },
            { value: '28/35/42DDL', text: '28/35/42DDL' },
            { value: '28/42/56DDL', text: '28/42/56DDL' },
            { value: '30/45/60 DDL', text: '30/45/60 DDL' },
            { value: 'OUTROS', text: 'OUTROS (DISCERTATIVO)' }
        ];
        
        // Capturar valor selecionado antes de limpar
        const valorSelecionado = selectFormaPagamento.value;
        
        // Limpar TODAS as opções e recriar apenas as corretas
        selectFormaPagamento.innerHTML = '';
        
        opcoesCorretas.forEach(opcao => {
            const option = document.createElement('option');
            option.value = opcao.value;
            option.textContent = opcao.text;
            selectFormaPagamento.appendChild(option);
        });
        
        // Restaurar valor selecionado se ainda for válido
        if (valorSelecionado && opcoesCorretas.some(op => op.value === valorSelecionado)) {
            selectFormaPagamento.value = valorSelecionado;
        }
        
        // Prevenir adição de opções não autorizadas
        // Interceptar tentativas de adicionar opções dinamicamente
        const originalAppendChild = selectFormaPagamento.appendChild;
        selectFormaPagamento.appendChild = function(node) {
            if (node.tagName === 'OPTION') {
                const valor = node.value;
                const texto = node.textContent || node.innerText;
                const opcaoValida = opcoesCorretas.some(op => op.value === valor && op.text === texto);
                if (!opcaoValida) {
                    console.warn('Tentativa de adicionar opção não autorizada bloqueada:', valor, texto);
                    return node; // Bloquear adição
                }
            }
            return originalAppendChild.call(this, node);
        };
    }
}

// Inicializar funcionalidade de forma de pagamento personalizada
function inicializarFormaPagamentoPersonalizada() {
    const selectFormaPagamento = document.getElementById('forma-pagamento');
    const campoPersonalizada = document.getElementById('forma-pagamento-personalizada');
    
    if (selectFormaPagamento && campoPersonalizada) {
        // Adicionar listener para mudanças no select
        selectFormaPagamento.addEventListener('change', function() {
            toggleFormaPagamentoPersonalizada();
        });
        
        // Adicionar listener para mudanças no campo personalizado
        campoPersonalizada.addEventListener('input', function() {
            if (this.value.trim()) {
                // Atualizar o valor do select para refletir a opção personalizada
                selectFormaPagamento.value = 'OUTROS';
            }
        });
    }
}

// Toggle do campo de forma de pagamento personalizada
function toggleFormaPagamentoPersonalizada() {
    const selectFormaPagamento = document.getElementById('forma-pagamento');
    const grupoPersonalizada = document.getElementById('forma-pagamento-personalizada-group');
    const campoPersonalizada = document.getElementById('forma-pagamento-personalizada');
    
    if (selectFormaPagamento && grupoPersonalizada && campoPersonalizada) {
        if (selectFormaPagamento.value === 'OUTROS') {
            grupoPersonalizada.style.display = 'block';
            campoPersonalizada.required = true;
            campoPersonalizada.focus();
        } else {
            grupoPersonalizada.style.display = 'none';
            campoPersonalizada.required = false;
            campoPersonalizada.value = '';
        }
    }
}

// Inicializar funcionalidade de cálculo de IPI
function inicializarCalculoIPI() {
    // Adicionar listener para mudanças no valor total
    const valorTotalInput = document.getElementById('valor-total');
    if (valorTotalInput) {
        valorTotalInput.addEventListener('input', calcularIPI);
    }
    
    // Adicionar listener para mudanças na tabela de itens
    const tabelaItens = document.getElementById('tabela-itens');
    if (tabelaItens) {
        tabelaItens.addEventListener('input', function(e) {
            if (e.target.classList.contains('input-valor-unitario') || 
                e.target.classList.contains('input-quantidade')) {
                setTimeout(calcularIPI, 100); // Delay para permitir que o cálculo da tabela termine
            }
        });
    }
}

// Toggle do cálculo de IPI
function toggleCalculoIPI() {
    const tipoProduto = document.getElementById('tipo-produto');
    const tipoServico = document.getElementById('tipo-servico');
    const ipiGroup = document.getElementById('ipi-calculo-group');
    const tabelaContainer = document.querySelector('.tabela-itens-container');
    
    if (tipoProduto && tipoServico && ipiGroup && tabelaContainer) {
        // Remover classes de cor existentes
        tabelaContainer.classList.remove('tipo-servico', 'tipo-produto');
        
        if (tipoProduto.checked) {
            ipiGroup.style.display = 'block';
            calcularIPI();
            // Aplicar classe de cor para produto
            tabelaContainer.classList.add('tipo-produto');
        } else {
            ipiGroup.style.display = 'none';
            // Aplicar classe de cor para serviço
            tabelaContainer.classList.add('tipo-servico');
        }
    }
}

// Calcular IPI
function calcularIPI() {
    const tipoProduto = document.getElementById('tipo-produto');
    const ipiGroup = document.getElementById('ipi-calculo-group');
    
    if (!tipoProduto || !tipoProduto.checked || !ipiGroup) {
        return;
    }
    
    // Obter valor total da tabela de itens
    const valorTotalElement = document.getElementById('total-geral');
    let valorBase = 0;
    
    if (valorTotalElement) {
        const valorTexto = valorTotalElement.textContent.replace('R$ ', '').replace('.', '').replace(',', '.');
        valorBase = parseFloat(valorTexto) || 0;
    }
    
    // Calcular IPI (3,25%)
    const percentualIPI = 3.25;
    const valorIPI = (valorBase * percentualIPI) / 100;
    const valorTotalComIPI = valorBase + valorIPI;
    
    // Atualizar elementos da interface
    const valorBaseElement = document.getElementById('ipi-valor-base');
    const valorImpostoElement = document.getElementById('ipi-valor-imposto');
    const valorTotalIPIElement = document.getElementById('ipi-valor-total');
    
    if (valorBaseElement) {
        valorBaseElement.textContent = formatarMoeda(valorBase);
    }
    
    if (valorImpostoElement) {
        valorImpostoElement.textContent = formatarMoeda(valorIPI);
    }
    
    if (valorTotalIPIElement) {
        valorTotalIPIElement.textContent = formatarMoeda(valorTotalComIPI);
    }
    
    // Atualizar o valor total do orçamento com IPI
    const valorTotalInput = document.getElementById('valor-total');
    if (valorTotalInput) {
        valorTotalInput.value = valorTotalComIPI.toFixed(2);
    }
}

// Função auxiliar para formatar moeda
function formatarMoeda(valor) {
    return 'R$ ' + valor.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

