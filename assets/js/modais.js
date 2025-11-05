/**
 * ========================================
 * FUNCIONALIDADES DOS MODAIS - CARTEIRA
 * ========================================
 */

// ========================================
// MODAL DE CONFIRMAÇÃO DE EXCLUSÃO
// ========================================

/**
 * Abre o modal de confirmação de exclusão
 * NOTA: A implementação principal está em carteira.js como confirmarExclusaoCliente()
 * Esta função é mantida apenas como referência/backup
 */
// function abrirModalConfirmacaoCliente() - REMOVIDA - usar confirmarExclusaoCliente() em carteira.js

/**
 * Fecha o modal de confirmação de exclusão
 */
function fecharModalCliente() {
    document.getElementById('modalConfirmacaoCliente').style.display = 'none';
}

/**
 * Executa a exclusão do cliente após validação
 * NOTA: A implementação principal está em carteira.js
 * Esta função é mantida apenas como referência/backup
 */
// function excluirCliente() - REMOVIDA - usar a implementação em carteira.js

// ========================================
// MODAL DE OBSERVAÇÕES
// ========================================

// ========================================
// MODAL DE OBSERVAÇÕES - REMOVIDO PARA EVITAR CONFLITO
// As funções de observações estão em carteira.js
// ========================================

// ========================================
// MODAL DE DETALHES DO CLIENTE
// ========================================

/**
 * Abre o modal de detalhes do cliente
 * @param {number} clienteId - ID do cliente
 */
function abrirModalDetalhes(clienteId) {
    document.getElementById('modalDetalhesCliente').style.display = 'flex';
    
    // Carregar detalhes do cliente
    carregarDetalhesCliente(clienteId);
}

/**
 * Fecha o modal de detalhes
 */
function fecharModalDetalhes() {
    document.getElementById('modalDetalhesCliente').style.display = 'none';
}

/**
 * Carrega os detalhes do cliente
 * @param {number} clienteId - ID do cliente
 */
function carregarDetalhesCliente(clienteId) {
    fetch(`/Site/includes/api/detalhes_cliente.php?cliente_id=${clienteId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detalhesClienteContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Erro ao carregar detalhes:', error);
            document.getElementById('detalhesClienteContent').innerHTML = '<p style="text-align: center; color: #dc3545;">Erro ao carregar detalhes do cliente.</p>';
        });
}

// ========================================
// MODAL DE ROTEIRO DE LIGAÇÃO
// ========================================

/**
 * Abre o modal de roteiro de ligação
 * @param {number} clienteId - ID do cliente
 * @param {string} nomeCliente - Nome do cliente
 */
function abrirModalRoteiro(clienteId, nomeCliente) {
    document.getElementById('modalRoteiroLigacao').style.display = 'flex';
    
    // Armazenar dados do cliente
    document.getElementById('modalRoteiroLigacao').dataset.clienteId = clienteId;
    document.getElementById('modalRoteiroLigacao').dataset.nomeCliente = nomeCliente;
    
    // Carregar roteiro
    carregarRoteiroLigacao(clienteId);
}

/**
 * Fecha o modal de roteiro
 */
function fecharModalRoteiro() {
    document.getElementById('modalRoteiroLigacao').style.display = 'none';
    resetarRoteiro();
}

/**
 * Carrega o roteiro de ligação
 * @param {number} clienteId - ID do cliente
 */
function carregarRoteiroLigacao(clienteId) {
    fetch(`includes/gerenciar_perguntas_ligacao.php?acao=carregar&cliente_id=${clienteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarRoteiro(data.roteiro);
            } else {
                mostrarNotificacao('Erro ao carregar roteiro: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar roteiro:', error);
            mostrarNotificacao('Erro ao carregar roteiro de ligação', 'error');
        });
}

/**
 * Renderiza o roteiro na interface
 * @param {Array} roteiro - Array com as etapas do roteiro
 */
function renderizarRoteiro(roteiro) {
    const container = document.getElementById('roteiroContainer');
    const progresso = document.getElementById('roteiroProgresso');
    
    let html = '';
    let etapasConcluidas = 0;
    
    roteiro.forEach((etapa, index) => {
        const status = etapa.resposta ? 'concluida' : (index === 0 ? 'ativa' : 'pendente');
        if (etapa.resposta) etapasConcluidas++;
        
        html += `
            <div class="roteiro-etapa ${status}" data-etapa="${index}">
                <div class="roteiro-etapa-header">
                    <div class="roteiro-etapa-numero">${index + 1}</div>
                    <h4 class="roteiro-etapa-titulo">${etapa.pergunta}</h4>
                    <span class="roteiro-etapa-status ${status}">
                        ${status === 'concluida' ? 'Concluída' : status === 'ativa' ? 'Ativa' : 'Pendente'}
                    </span>
                </div>
                <p class="roteiro-etapa-descricao">${etapa.descricao || ''}</p>
                ${etapa.resposta ? `<div class="roteiro-etapa-resposta">${etapa.resposta}</div>` : ''}
                ${status === 'ativa' ? `
                    <div class="roteiro-etapa-input">
                        <textarea id="respostaEtapa${index}" placeholder="Digite sua resposta..." class="form-control"></textarea>
                        <button onclick="salvarRespostaEtapa(${index})" class="btn btn-primary btn-sm mt-2">
                            Salvar Resposta
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Atualizar progresso
    const percentual = (etapasConcluidas / roteiro.length) * 100;
    progresso.style.width = percentual + '%';
}

/**
 * Salva a resposta de uma etapa
 * @param {number} etapaIndex - Índice da etapa
 */
function salvarRespostaEtapa(etapaIndex) {
    const clienteId = document.getElementById('modalRoteiroLigacao').dataset.clienteId;
    const resposta = document.getElementById(`respostaEtapa${etapaIndex}`).value.trim();
    
    if (!resposta) {
        mostrarNotificacao('Por favor, digite uma resposta.', 'warning');
        return;
    }
    
    fetch('includes/gerenciar_perguntas_ligacao.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `acao=salvar_resposta&cliente_id=${clienteId}&etapa=${etapaIndex}&resposta=${encodeURIComponent(resposta)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarregar roteiro
            carregarRoteiroLigacao(clienteId);
            mostrarNotificacao('Resposta salva com sucesso!', 'success');
        } else {
            mostrarNotificacao('Erro ao salvar resposta: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao processar a requisição', 'error');
    });
}

/**
 * Finaliza o roteiro de ligação
 */
function finalizarRoteiro() {
    const clienteId = document.getElementById('modalRoteiroLigacao').dataset.clienteId;
    
    if (confirm('Tem certeza que deseja finalizar o roteiro de ligação?')) {
        fetch('includes/gerenciar_perguntas_ligacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `acao=finalizar&cliente_id=${clienteId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fecharModalRoteiro();
                mostrarNotificacao('Roteiro finalizado com sucesso!', 'success');
                
                // Recarregar a página ou atualizar a tabela
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                mostrarNotificacao('Erro ao finalizar roteiro: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarNotificacao('Erro ao processar a requisição', 'error');
        });
    }
}

/**
 * Reseta o estado do roteiro
 */
function resetarRoteiro() {
    document.getElementById('roteiroContainer').innerHTML = '';
    document.getElementById('roteiroProgresso').style.width = '0%';
}

// ========================================
// UTILITÁRIOS
// ========================================

/**
 * Formata uma data para exibição
 * @param {string} dataString - Data em formato string
 * @returns {string} Data formatada
 */
function formatarData(dataString) {
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Mostra uma notificação
 * @param {string} mensagem - Mensagem a ser exibida
 * @param {string} tipo - Tipo da notificação (success, error, warning, info)
 */
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Verificar se já existe uma implementação melhor
    if (typeof window.mostrarNotificacaoAvancada === 'function') {
        window.mostrarNotificacaoAvancada(mensagem, tipo);
        return;
    }
    
    // Implementação básica como fallback
    const notificacao = document.createElement('div');
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
    notificacao.textContent = mensagem;
    
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

// ========================================
// EVENT LISTENERS
// ========================================

// Fechar modais ao clicar fora deles
document.addEventListener('DOMContentLoaded', function() {
    // Modal de confirmação
    document.getElementById('modalConfirmacaoCliente').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalCliente();
        }
    });
    
    // Modal de observações
    document.getElementById('modalObservacoes').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalObservacoes();
        }
    });
    
    // Modal de detalhes
    document.getElementById('modalDetalhesCliente').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalDetalhes();
        }
    });
    
    // Modal de roteiro
    document.getElementById('modalRoteiroLigacao').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalRoteiro();
        }
    });
    
    // Fechar modais com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModalCliente();
            fecharModalObservacoes();
            fecharModalDetalhes();
            fecharModalRoteiro();
        }
    });
    
    // Enter para adicionar observação
    document.getElementById('novaObservacao')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.ctrlKey) {
            adicionarObservacao();
        }
    });
});
