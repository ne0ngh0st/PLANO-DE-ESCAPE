// Variáveis globais para o calendário
let dataAtual = new Date();
let agendamentos = [];

// Inicialização do calendário
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar calendário
    inicializarCalendario();
});

// Funções do Calendário Visual
function inicializarCalendario() {
    renderizarCalendario();
    carregarAgendamentos();
}

function renderizarCalendario() {
    const container = document.getElementById('calendarioGrid');
    const mesAtual = document.getElementById('mesAtual');
    
    if (!container) return;
    
    const ano = dataAtual.getFullYear();
    const mes = dataAtual.getMonth();
    
    // Atualizar título do mês
    const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
                   'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    mesAtual.textContent = `${meses[mes]} ${ano}`;
    
    // Criar cabeçalho dos dias da semana
    const diasSemana = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    let html = '<div class="calendario-dias-semana">';
    diasSemana.forEach(dia => {
        html += `<div class="dia-semana">${dia}</div>`;
    });
    html += '</div>';
    
    // Calcular primeiro dia do mês e último dia
    const primeiroDia = new Date(ano, mes, 1);
    const ultimoDia = new Date(ano, mes + 1, 0);
    const diaSemanaInicio = primeiroDia.getDay();
    const totalDias = ultimoDia.getDate();
    
    // Criar grid do calendário
    html += '<div class="calendario-dias">';
    
    // Adicionar dias vazios no início
    for (let i = 0; i < diaSemanaInicio; i++) {
        html += '<div class="dia vazio"></div>';
    }
    
    // Adicionar dias do mês
    for (let dia = 1; dia <= totalDias; dia++) {
        const dataCompleta = new Date(ano, mes, dia);
        const hoje = new Date();
        const isHoje = dataCompleta.toDateString() === hoje.toDateString();
        const isPassado = dataCompleta < hoje;
        
        let classes = 'dia';
        if (isHoje) classes += ' hoje';
        if (isPassado) classes += ' passado';
        
        // Verificar se há agendamentos neste dia
        const agendamentosDia = agendamentos.filter(ag => {
            const dataAg = new Date(ag.data_agendamento);
            return dataAg.getDate() === dia && dataAg.getMonth() === mes && dataAg.getFullYear() === ano;
        });
        
        if (agendamentosDia.length > 0) {
            classes += ' tem-agendamento';
        }
        
        html += `<div class="${classes}" onclick="selecionarDia(${dia}, ${mes}, ${ano}, ${agendamentosDia.length > 0})">
                    <span class="numero-dia">${dia}</span>
                    ${agendamentosDia.length > 0 ? `<span class="badge-agendamento">${agendamentosDia.length}</span>` : ''}
                 </div>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

function mesAnterior() {
    dataAtual.setMonth(dataAtual.getMonth() - 1);
    renderizarCalendario();
    carregarAgendamentos();
}

function mesProximo() {
    dataAtual.setMonth(dataAtual.getMonth() + 1);
    renderizarCalendario();
    carregarAgendamentos();
}

function selecionarDia(dia, mes, ano, temAgendamento) {
    const dataSelecionada = new Date(ano, mes, dia);
    
    if (temAgendamento) {
        // Mostrar detalhes dos agendamentos existentes
        mostrarDetalhesAgendamentos(dataSelecionada);
    }
}

function mostrarDetalhesAgendamentos(data) {
    const agendamentosDia = agendamentos.filter(ag => {
        const dataAg = new Date(ag.data_agendamento);
        return dataAg.toDateString() === data.toDateString();
    });
    
    if (agendamentosDia.length === 0) {
        alert('Nenhum agendamento encontrado para esta data');
        return;
    }
    
    const modal = document.getElementById('modalDetalhes');
    const container = document.getElementById('detalhesAgendamento');
    
    let html = `<h4>Agendamentos para ${data.toLocaleDateString('pt-BR')}</h4>`;
    
    agendamentosDia.forEach((agendamento, index) => {
        const statusClass = `status-${agendamento.status}`;
        const statusText = agendamento.status === 'agendado' ? 'Agendado' : 
                         agendamento.status === 'realizado' ? 'Realizado' : 'Cancelado';
        
        html += `
            <div class="agendamento-detalhe" data-id="${agendamento.id}">
                <p><strong>Cliente:</strong> ${agendamento.cliente_nome}</p>
                <p><strong>Horário:</strong> ${agendamento.hora}</p>
                <p><strong>Observação:</strong> ${agendamento.observacao || 'Nenhuma observação'}</p>
                <span class="status ${statusClass}">${statusText}</span>
            </div>
        `;
        
        if (index < agendamentosDia.length - 1) {
            html += '<hr style="margin: 1rem 0; border: none; border-top: 1px solid #dee2e6;">';
        }
    });
    
    container.innerHTML = html;
    
    // Mostrar/esconder botões baseado no status
    const btnMarcar = document.getElementById('btnMarcarRealizado');
    const btnCancelar = document.getElementById('btnCancelar');
    
    const temAgendamentosPendentes = agendamentosDia.some(ag => ag.status === 'agendado');
    
    if (temAgendamentosPendentes) {
        btnMarcar.style.display = 'inline-block';
        btnCancelar.style.display = 'inline-block';
        // Armazenar IDs dos agendamentos para as ações
        btnMarcar.setAttribute('data-ids', agendamentosDia.filter(ag => ag.status === 'agendado').map(ag => ag.id).join(','));
        btnCancelar.setAttribute('data-ids', agendamentosDia.filter(ag => ag.status === 'agendado').map(ag => ag.id).join(','));
    } else {
        btnMarcar.style.display = 'none';
        btnCancelar.style.display = 'none';
    }
    
    modal.style.display = 'flex';
}

function fecharModalDetalhesCalendario() {
    const modal = document.getElementById('modalDetalhes');
    if (modal) modal.style.display = 'none';
}

function marcarComoRealizadoFromModal() {
    const ids = document.getElementById('btnMarcarRealizado').getAttribute('data-ids').split(',');
    if (confirm('Confirmar que todos os agendamentos foram realizados?')) {
        Promise.all(ids.map(id => marcarComoRealizado(id)))
            .then(() => {
                fecharModalDetalhes();
                carregarAgendamentos();
            });
    }
}









function carregarAgendamentos() {
    const usuarioId = document.querySelector('meta[name="usuario-id"]')?.content || '';
    const filtro = document.getElementById('filtroStatusCalendario')?.value || '';
    
    let url = `includes/gerenciar_agendamentos.php?acao=buscar_agendamentos&usuario_id=${usuarioId}`;
    if (filtro) {
        url += `&filtro_status=${filtro}`;
    }
    
    fetch(url)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            agendamentos = data.agendamentos;
            exibirAgendamentos(data.agendamentos);
            renderizarCalendario(); // Atualizar calendário com novos agendamentos
        } else {
            console.error('Erro ao carregar agendamentos:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
    });
}

function exibirAgendamentos(agendamentos) {
    const container = document.getElementById('agendamentosLista');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (agendamentos.length === 0) {
        container.innerHTML = '<div class="no-agendamentos"><p>Nenhum agendamento encontrado.</p></div>';
        return;
    }
    
    agendamentos.forEach(agendamento => {
        const item = document.createElement('div');
        item.className = 'agendamento-item';
        
        const statusClass = `status-${agendamento.status}`;
        const statusText = agendamento.status === 'agendado' ? 'Agendado' : 
                         agendamento.status === 'realizado' ? 'Realizado' : 'Cancelado';
        
        item.innerHTML = `
            <div class="agendamento-info">
                <div class="agendamento-cliente">${agendamento.cliente_nome}</div>
                <div class="agendamento-detalhes">
                    ${agendamento.data} às ${agendamento.hora} - ${agendamento.observacao || 'Sem observação'}
                </div>
            </div>
            <div class="agendamento-acoes">
                <span class="${statusClass}">${statusText}</span>
                <button class="btn btn-sm btn-success" onclick="marcarComoRealizado(${agendamento.id})" ${agendamento.status !== 'agendado' ? 'disabled' : ''}>
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="cancelarAgendamento(${agendamento.id})" ${agendamento.status !== 'agendado' ? 'disabled' : ''}>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        container.appendChild(item);
    });
}

function filtrarAgendamentos() {
    carregarAgendamentos();
}

function marcarComoRealizado(id) {
    return new Promise((resolve, reject) => {
    if (confirm('Confirmar que a ligação foi realizada?')) {
        const formData = new FormData();
        formData.append('acao', 'marcar_realizado');
        formData.append('agendamento_id', id);
        
        fetch('includes/gerenciar_agendamentos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                    resolve();
            } else {
                alert('Erro ao marcar como realizado: ' + data.message);
                    reject();
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar a requisição');
                reject();
            });
        } else {
            reject();
        }
        });
}

function cancelarAgendamento(id) {
    return new Promise((resolve, reject) => {
    if (confirm('Confirmar cancelamento do agendamento?')) {
        const formData = new FormData();
        formData.append('acao', 'cancelar_agendamento');
        formData.append('agendamento_id', id);
        
        fetch('includes/gerenciar_agendamentos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                    resolve();
            } else {
                alert('Erro ao cancelar agendamento: ' + data.message);
                    reject();
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar a requisição');
                reject();
            });
        } else {
            reject();
        }
    });
}


