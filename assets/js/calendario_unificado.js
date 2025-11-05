// ===== CALENDÁRIO UNIFICADO - APENAS VISUALIZAÇÃO =====

// Variáveis globais para o calendário
let dataAtual = new Date();
let agendamentos = [];
let agendamentosLeads = [];
let filtroTipoAtivo = '';

// Inicialização do calendário
document.addEventListener('DOMContentLoaded', function() {
    // Event listeners para abas
    const calendarioTab = document.getElementById('calendario-tab');
    const calendarioLeadsTab = document.getElementById('calendario-leads-tab');
    
    if (calendarioTab) {
        calendarioTab.addEventListener('shown.bs.tab', function() {
            console.log('Aba calendário ativada');
            inicializarCalendario();
        });
    }
    
    if (calendarioLeadsTab) {
        calendarioLeadsTab.addEventListener('shown.bs.tab', function() {
            console.log('Aba calendário de leads ativada');
            inicializarCalendario();
        });
    }
    
    // Inicializar calendário
    inicializarCalendario();
});

// Função para inicializar o calendário
function inicializarCalendario() {
    console.log('Inicializando calendário unificado...');
    carregarTodosAgendamentos();
    renderizarCalendario();
}

// Função para carregar todos os agendamentos (clientes + leads)
function carregarTodosAgendamentos() {
    console.log('Iniciando carregamento de agendamentos...');
    
    // Carregar agendamentos de clientes
    fetch('/Site/includes/calendar/gerenciar_agendamentos.php?acao=buscar_agendamentos')
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta da API de clientes:', data);
        
        if (data.success && data.agendamentos && Array.isArray(data.agendamentos)) {
            agendamentos = data.agendamentos.map(ag => {
                const agendamentoProcessado = {
                    ...ag,
                    tipo: 'cliente',
                    cliente_nome: ag.cliente_nome || ag.nome || 'Cliente sem nome',
                    cliente_cnpj: ag.cliente_cnpj || ag.cnpj || 'CNPJ não informado',
                    data_agendamento: ag.data_agendamento || ag.data || '',
                    hora: ag.hora || ag.hora_agendamento || '00:00',
                    status: ag.status || ag.status_agendamento || 'agendado',
                    id: ag.id || ag.agendamento_id || 0
                };
                
                // Validar campos obrigatórios
                if (!agendamentoProcessado.cliente_nome || agendamentoProcessado.cliente_nome === 'Cliente sem nome') {
                    console.warn('Cliente sem nome válido:', ag);
                }
                if (!agendamentoProcessado.data_agendamento) {
                    console.warn('Cliente sem data válida:', ag);
                }
                
                return agendamentoProcessado;
            }).filter(ag => {
                // Filtrar apenas agendamentos com dados válidos
                return ag.cliente_nome && ag.cliente_nome !== 'Cliente sem nome' && 
                       ag.data_agendamento && ag.data_agendamento !== '';
            });
            
            console.log('Agendamentos de clientes processados:', agendamentos);
        } else {
            console.warn('API retornou sucesso mas sem agendamentos válidos ou com erro:', data);
            agendamentos = [];
        }
        
        carregarAgendamentosLeads();
    })
    .catch(error => {
        console.error('Erro ao carregar agendamentos de clientes:', error);
        agendamentos = [];
        carregarAgendamentosLeads();
    });
}

// Função para carregar agendamentos de leads
function carregarAgendamentosLeads() {
    console.log('Carregando agendamentos de leads...');
    
    fetch('/Site/includes/calendar/gerenciar_agendamentos_leads.php?acao=buscar_agendamentos_leads')
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta da API de leads:', data);
        
        if (data.success && data.agendamentos && Array.isArray(data.agendamentos)) {
            agendamentosLeads = data.agendamentos.map(ag => {
                // Garantir que todos os campos necessários existam
                const agendamentoProcessado = {
                    ...ag,
                    tipo: 'lead',
                    lead_nome: ag.lead_nome || ag.nome || ag.lead_nome_original || 'Lead sem nome',
                    lead_email: ag.lead_email || ag.email || ag.lead_email_original || 'Email não informado',
                    lead_telefone: ag.lead_telefone || ag.telefone || ag.lead_telefone_original || 'Telefone não informado',
                    data_agendamento: ag.data_agendamento || ag.data || ag.data_agendamento_original || '',
                    hora: ag.hora || ag.hora_agendamento || '00:00',
                    status: ag.status || ag.status_agendamento || 'agendado',
                    id: ag.id || ag.agendamento_id || 0
                };
                
                                 // Log de campos incompletos para acompanhamento
                 if (!agendamentoProcessado.lead_nome || agendamentoProcessado.lead_nome === 'Lead sem nome') {
                     console.log('Lead sem nome - usando email como identificador:', ag.lead_email || ag.email);
                 }
                 if (!agendamentoProcessado.data_agendamento) {
                     console.log('Lead sem data de agendamento:', ag);
                 }
                
                return agendamentoProcessado;
            });
            
            console.log('Agendamentos de leads processados:', agendamentosLeads);
        } else {
            console.warn('API retornou sucesso mas sem agendamentos válidos ou com erro:', data);
            agendamentosLeads = [];
        }
        
        // Renderizar calendário após carregar ambos
        renderizarCalendario();
        carregarListaAgendamentos();
    })
    .catch(error => {
        console.error('Erro ao carregar agendamentos de leads:', error);
        agendamentosLeads = [];
        renderizarCalendario();
        carregarListaAgendamentos();
    });
}

// Função para renderizar o calendário
function renderizarCalendario() {
    const calendarioGrid = document.getElementById('calendarioGrid');
    const mesAtualElement = document.getElementById('mesAtual');
    
    if (!calendarioGrid || !mesAtualElement) {
        console.error('Elementos do calendário não encontrados');
        return;
    }
    
    const mesAtual = dataAtual.getMonth();
    const anoAtual = dataAtual.getFullYear();
    
    // Atualizar título do mês
    const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    mesAtualElement.textContent = `${meses[mesAtual]} ${anoAtual}`;
    
    // Limpar grid
    calendarioGrid.innerHTML = '';
    
    // Criar cabeçalho dos dias da semana
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    diasSemana.forEach(dia => {
        const diaHeader = document.createElement('div');
        diaHeader.className = 'calendario-header-dia';
        diaHeader.textContent = dia;
        calendarioGrid.appendChild(diaHeader);
    });
    
    // Calcular primeiro dia do mês e quantos dias tem
    const primeiroDia = new Date(anoAtual, mesAtual, 1);
    const ultimoDia = new Date(anoAtual, mesAtual + 1, 0);
    const diasNoMes = ultimoDia.getDate();
    const diaSemanaInicio = primeiroDia.getDay();
    
    // Adicionar dias do mês anterior
    for (let i = diaSemanaInicio - 1; i >= 0; i--) {
        const dia = new Date(anoAtual, mesAtual, -i);
        criarDiaCalendario(dia, true);
    }
    
    // Adicionar dias do mês atual
    for (let dia = 1; dia <= diasNoMes; dia++) {
        const data = new Date(anoAtual, mesAtual, dia);
        criarDiaCalendario(data, false);
    }
    
    // Adicionar dias do próximo mês
    const diasAdicionais = 42 - (diaSemanaInicio + diasNoMes);
    for (let dia = 1; dia <= diasAdicionais; dia++) {
        const data = new Date(anoAtual, mesAtual + 1, dia);
        criarDiaCalendario(data, true);
    }
}

// Função para criar um dia no calendário
function criarDiaCalendario(data, outroMes) {
    const calendarioGrid = document.getElementById('calendarioGrid');
    const hoje = new Date();
    
    const diaElement = document.createElement('div');
    diaElement.className = 'calendario-dia';
    
    if (outroMes) {
        diaElement.classList.add('outro-mes');
    }
    
    if (data.toDateString() === hoje.toDateString()) {
        diaElement.classList.add('hoje');
    }
    
    // Número do dia
    const numeroDia = document.createElement('div');
    numeroDia.className = 'calendario-dia-numero';
    numeroDia.textContent = data.getDate();
    diaElement.appendChild(numeroDia);
    
    // Buscar agendamentos para este dia
    const dataStr = data.toISOString().split('T')[0];
    
    // Combinar agendamentos de clientes e leads
    const todosAgendamentos = [...agendamentos, ...agendamentosLeads];
    const agendamentosDoDia = todosAgendamentos.filter(agendamento => {
        try {
            // Verificar se o agendamento tem data válida
            if (!agendamento.data_agendamento) {
                // Para leads, log mas não excluir - pode ser um agendamento sem data definida
                if (agendamento.tipo === 'lead') {
                    console.log('Lead sem data de agendamento (pode ser válido):', agendamento);
                    return false; // Não mostrar no calendário se não tem data
                }
                console.warn('Agendamento sem data:', agendamento);
                return false;
            }
            
            const dataAgendamento = agendamento.data_agendamento.split(' ')[0];
            return dataAgendamento === dataStr;
        } catch (error) {
            console.error('Erro ao filtrar agendamento por data:', error, agendamento);
            return false;
        }
    });
    
    // Aplicar filtro por tipo se ativo
    const agendamentosFiltrados = filtroTipoAtivo ? 
        agendamentosDoDia.filter(ag => ag.tipo === filtroTipoAtivo) : 
        agendamentosDoDia;
    
    // Adicionar agendamentos ao dia
    agendamentosFiltrados.forEach(agendamento => {
        try {
            const agendamentoElement = document.createElement('div');
            agendamentoElement.className = `agendamento-${agendamento.tipo || 'cliente'}`;
            
            let hora, nome;
            
            if (agendamento.tipo === 'cliente') {
                hora = agendamento.hora || agendamento.hora_agendamento || 'N/A';
                nome = agendamento.cliente_nome || agendamento.nome || 'Cliente sem nome';
            } else if (agendamento.tipo === 'lead') {
                hora = agendamento.hora || agendamento.hora_agendamento || 'N/A';
                nome = agendamento.lead_nome || agendamento.nome || 'Lead sem nome';
            } else {
                // Fallback para agendamentos sem tipo definido
                hora = agendamento.hora || agendamento.hora_agendamento || 'N/A';
                nome = agendamento.cliente_nome || agendamento.lead_nome || agendamento.nome || 'Nome não informado';
            }
            
                         // Para leads, permitir nomes incompletos mas marcar visualmente
             if (agendamento.tipo === 'lead' && (nome === 'Lead sem nome' || nome === 'Nome não informado')) {
                 nome = `[Lead] ${agendamento.lead_email || agendamento.email || 'Email não informado'}`;
                 agendamentoElement.classList.add('lead-incompleto');
             } else if (nome === 'Cliente sem nome' || nome === 'Nome não informado') {
                 console.warn('Agendamento com nome inválido:', agendamento);
                 nome = `[${agendamento.tipo || 'Tipo não definido'}] ${nome}`;
             }
            
            agendamentoElement.textContent = `${hora} - ${nome}`;
            agendamentoElement.title = `${agendamento.tipo === 'cliente' ? 'Cliente' : agendamento.tipo === 'lead' ? 'Lead' : 'Agendamento'}: ${nome} - ${hora}`;
            
            agendamentoElement.onclick = () => mostrarDetalhesAgendamento(agendamento);
            diaElement.appendChild(agendamentoElement);
            
            // Debug: log do agendamento criado
            console.log(`Agendamento criado para ${dataStr}:`, {
                tipo: agendamento.tipo,
                nome: nome,
                hora: hora,
                dados_completos: agendamento
            });
        } catch (error) {
            console.error('Erro ao criar elemento de agendamento:', error, agendamento);
        }
    });
    
    calendarioGrid.appendChild(diaElement);
}

// Função para carregar lista de agendamentos
function carregarListaAgendamentos() {
    const lista = document.getElementById('agendamentosLista');
    if (!lista) return;
    
    const todosAgendamentos = [...agendamentos, ...agendamentosLeads];
    
    if (todosAgendamentos.length === 0) {
        lista.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #6c757d;">
                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-style: italic; margin: 0;">Nenhum agendamento encontrado.</p>
            </div>
        `;
        return;
    }
    
    // Filtrar agendamentos futuros e ordená-los
    const agendamentosFuturos = todosAgendamentos
        .filter(agendamento => {
            const dataAgendamento = new Date(agendamento.data_agendamento);
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            return dataAgendamento >= hoje;
        })
        .filter(agendamento => !filtroTipoAtivo || agendamento.tipo === filtroTipoAtivo)
        .sort((a, b) => new Date(a.data_agendamento) - new Date(b.data_agendamento))
        .slice(0, 10);
    
    if (agendamentosFuturos.length === 0) {
        lista.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #6c757d;">
                <i class="fas fa-calendar-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-style: italic; margin: 0;">Nenhum agendamento futuro.</p>
            </div>
        `;
        return;
    }
    
    lista.innerHTML = '<h4 style="margin-bottom: 1rem; color: #007bff;">Próximos Agendamentos</h4>';
    
    agendamentosFuturos.forEach(agendamento => {
        const agendamentoElement = document.createElement('div');
        agendamentoElement.className = `agendamento-item status-${agendamento.status}`;
        
        if (agendamento.tipo === 'cliente') {
            agendamentoElement.style.borderLeft = '4px solid #28a745';
            agendamentoElement.innerHTML = `
                <div class="agendamento-info">
                    <h4><i class="fas fa-user"></i> ${agendamento.cliente_nome} <span class="badge bg-success">Cliente</span></h4>
                    <p><i class="fas fa-building"></i> CNPJ: ${agendamento.cliente_cnpj}</p>
                    <p><i class="fas fa-calendar"></i> ${agendamento.data} às ${agendamento.hora}</p>
                    ${agendamento.observacao ? `<p><i class="fas fa-comment"></i> ${agendamento.observacao}</p>` : ''}
                    <span class="badge badge-${agendamento.status}">${agendamento.status.toUpperCase()}</span>
                </div>
                <div class="agendamento-acoes">
                    ${agendamento.status === 'agendado' ? `
                        <button class="btn btn-sm btn-success" onclick="marcarRealizado(${agendamento.id})" title="Marcar como realizado">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="cancelarAgendamento(${agendamento.id})" title="Cancelar">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </div>
            `;
        } else {
            agendamentoElement.style.borderLeft = '4px solid #007bff';
                         agendamentoElement.innerHTML = `
                 <div class="agendamento-info">
                     <h4><i class="fas fa-user-plus"></i> ${agendamento.lead_nome || agendamento.lead_email || 'Lead sem nome'} <span class="badge bg-primary">Lead</span>
                     ${(!agendamento.lead_nome || agendamento.lead_nome === 'Lead sem nome') ? '<span class="badge bg-warning">Incompleto</span>' : ''}
                     </h4>
                     <p><i class="fas fa-envelope"></i> ${agendamento.lead_email || 'Email não informado'}</p>
                     <p><i class="fas fa-phone"></i> ${agendamento.lead_telefone || 'Telefone não informado'}</p>
                     <p><i class="fas fa-calendar"></i> ${agendamento.data || agendamento.data_agendamento} às ${agendamento.hora || agendamento.hora_agendamento}</p>
                     ${agendamento.observacao ? `<p><i class="fas fa-comment"></i> ${agendamento.observacao}</p>` : ''}
                     <span class="badge badge-${agendamento.status}">${agendamento.status.toUpperCase()}</span>
                 </div>
                <div class="agendamento-acoes">
                    ${agendamento.status === 'agendado' ? `
                        <button class="btn btn-sm btn-success" onclick="marcarRealizadoLead(${agendamento.id})" title="Marcar como realizado">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="cancelarAgendamentoLead(${agendamento.id})" title="Cancelar">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </div>
            `;
        }
        
        lista.appendChild(agendamentoElement);
    });
}

// Funções de navegação
function mesAnterior() {
    dataAtual.setMonth(dataAtual.getMonth() - 1);
    renderizarCalendario();
}

function mesProximo() {
    dataAtual.setMonth(dataAtual.getMonth() + 1);
    renderizarCalendario();
}

// Função para filtrar por tipo
function filtrarAgendamentosPorTipo() {
    const filtro = document.getElementById('filtroTipoAgendamento');
    if (filtro) {
        filtroTipoAtivo = filtro.value;
        renderizarCalendario();
        carregarListaAgendamentos();
    }
}

// Função para mostrar detalhes do agendamento
function mostrarDetalhesAgendamento(agendamento) {
    if (!agendamento) {
        console.error('Agendamento não fornecido');
        alert('Erro: Agendamento não encontrado');
        return;
    }
    
    let detalhes = '';
    try {
        console.log('Mostrando detalhes do agendamento:', agendamento);
        
        if (agendamento.tipo === 'cliente') {
            detalhes = `Tipo: Cliente\nNome: ${agendamento.cliente_nome || 'N/A'}\nCNPJ: ${agendamento.cliente_cnpj || 'N/A'}\nData: ${agendamento.data || agendamento.data_agendamento || 'N/A'} às ${agendamento.hora || 'N/A'}\nStatus: ${agendamento.status || 'N/A'}`;
        } else if (agendamento.tipo === 'lead') {
            // Verificar se os dados do lead estão disponíveis
            const nome = agendamento.lead_nome || agendamento.nome || 'N/A';
            const email = agendamento.lead_email || agendamento.email || 'N/A';
            const telefone = agendamento.lead_telefone || agendamento.telefone || 'Não informado';
            const data = agendamento.data || agendamento.data_agendamento || 'N/A';
            const hora = agendamento.hora || agendamento.hora_agendamento || 'N/A';
            const status = agendamento.status || agendamento.status_agendamento || 'N/A';
            
            detalhes = `Tipo: Lead\nNome: ${nome}\nEmail: ${email}\nTelefone: ${telefone}\nData: ${data} às ${hora}\nStatus: ${status}`;
            
                         // Adicionar informações sobre dados incompletos
             if (nome === 'N/A' || email === 'N/A') {
                 detalhes += '\n\n⚠️ DADOS INCOMPLETOS';
                 detalhes += '\n\nEste lead ainda não tem todas as informações necessárias.';
                 detalhes += '\nO vendedor deve preencher gradualmente conforme obtém as informações.';
                 console.warn('Lead com dados incompletos:', agendamento);
             }
        } else {
            // Fallback para agendamentos sem tipo definido
            const nome = agendamento.cliente_nome || agendamento.lead_nome || agendamento.nome || 'N/A';
            const data = agendamento.data || agendamento.data_agendamento || 'N/A';
            const hora = agendamento.hora || agendamento.hora_agendamento || 'N/A';
            const status = agendamento.status || agendamento.status_agendamento || 'N/A';
            
            detalhes = `Agendamento (Tipo não definido)\nNome: ${nome}\nData: ${data} às ${hora}\nStatus: ${status}`;
            console.warn('Agendamento sem tipo definido:', agendamento);
        }
        
        if (agendamento.observacao) {
            detalhes += `\nObservação: ${agendamento.observacao}`;
        }
        
        // Adicionar ID para debug
        if (agendamento.id) {
            detalhes += `\nID: ${agendamento.id}`;
        }
        
        alert(detalhes);
    } catch (error) {
        console.error('Erro ao mostrar detalhes do agendamento:', error);
        console.log('Agendamento que causou erro:', agendamento);
        alert(`Erro ao mostrar detalhes do agendamento: ${error.message}\n\nVerifique o console para mais informações.`);
    }
}











// ===== FUNÇÕES DE AÇÃO NOS AGENDAMENTOS =====

function marcarRealizado(agendamentoId) {
    if (!confirm('Marcar este agendamento como realizado?')) return;
    
    const formData = new FormData();
    formData.append('acao', 'marcar_realizado');
    formData.append('agendamento_id', agendamentoId);
    
    fetch('/Site/includes/calendar/gerenciar_agendamentos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Agendamento marcado como realizado!');
            carregarTodosAgendamentos();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar agendamento');
    });
}

function cancelarAgendamento(agendamentoId) {
    if (!confirm('Cancelar este agendamento?')) return;
    
    const formData = new FormData();
    formData.append('acao', 'cancelar_agendamento');
    formData.append('agendamento_id', agendamentoId);
    
    fetch('/Site/includes/calendar/gerenciar_agendamentos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Agendamento cancelado!');
            carregarTodosAgendamentos();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao cancelar agendamento');
    });
}

function marcarRealizadoLead(agendamentoId) {
    if (!confirm('Marcar este agendamento como realizado?')) return;
    
    const formData = new FormData();
    formData.append('acao', 'marcar_realizado_lead');
    formData.append('agendamento_id', agendamentoId);
    
    fetch('/Site/includes/calendar/gerenciar_agendamentos_leads.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Agendamento marcado como realizado!');
            carregarTodosAgendamentos();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar agendamento');
    });
}

function cancelarAgendamentoLead(agendamentoId) {
    if (!confirm('Cancelar este agendamento?')) return;
    
    const formData = new FormData();
    formData.append('acao', 'cancelar_agendamento_lead');
    formData.append('agendamento_id', agendamentoId);
    
    fetch('/Site/includes/calendar/gerenciar_agendamentos_leads.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Agendamento cancelado!');
            carregarTodosAgendamentos();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao cancelar agendamento');
    });
}

// Função para filtrar agendamentos (mantida compatibilidade)
function filtrarAgendamentos() {
    // Esta função pode ser expandida para filtrar por status
    carregarTodosAgendamentos();
}

// Função de debug para verificar dados dos agendamentos
function debugAgendamentos() {
    console.log('=== DEBUG AGENDAMENTOS ===');
    console.log('Agendamentos de clientes:', agendamentos);
    console.log('Agendamentos de leads:', agendamentosLeads);
    console.log('Filtro ativo:', filtroTipoAtivo);
    console.log('Data atual:', dataAtual);
    
    // Verificar estrutura dos leads
    if (agendamentosLeads.length > 0) {
        console.log('Estrutura do primeiro lead:', agendamentosLeads[0]);
        console.log('Campos disponíveis:', Object.keys(agendamentosLeads[0]));
        
        // Analisar leads incompletas
        const leadsIncompletas = agendamentosLeads.filter(lead => 
            !lead.lead_nome || lead.lead_nome === 'Lead sem nome' || 
            !lead.lead_email || lead.lead_email === 'Email não informado'
        );
        
        if (leadsIncompletas.length > 0) {
            console.log('=== LEADS INCOMPLETAS ENCONTRADAS ===');
            console.log('Total de leads incompletas:', leadsIncompletas.length);
            leadsIncompletas.forEach((lead, index) => {
                console.log(`Lead ${index + 1} incompleta:`, {
                    id: lead.id,
                    nome: lead.lead_nome,
                    email: lead.lead_email,
                    telefone: lead.lead_telefone,
                    data: lead.data_agendamento,
                    hora: lead.hora,
                    status: lead.status
                });
            });
        } else {
            console.log('✅ Todas as leads têm informações completas');
        }
    }
    
    // Verificar elementos do DOM
    const calendarioGrid = document.getElementById('calendarioGrid');
    const mesAtualElement = document.getElementById('mesAtual');
    const listaElement = document.getElementById('agendamentosLista');
    
    console.log('Elementos DOM:', {
        calendarioGrid: !!calendarioGrid,
        mesAtualElement: !!mesAtualElement,
        listaElement: !!listaElement
    });
    
    alert('Debug executado. Verifique o console para mais informações.');
}
