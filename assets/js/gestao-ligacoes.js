/**
 * Gestão de Ligações - JavaScript
 * Funcionalidades específicas para a página de gestão de ligações
 */

// Função para ver detalhes de uma ligação
function verDetalhesLigacao(ligacaoId) {
    const modal = document.getElementById('modalDetalhesLigacao');
    const modalBody = document.getElementById('modalDetalhesBody');
    
    // Mostrar loading
    modalBody.innerHTML = `
        <div class="loading">
            <i class="fas fa-spinner"></i>
            <p>Carregando detalhes...</p>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    // Buscar detalhes da ligação
    fetch(`/Site/includes/management/gerenciar_ligacoes.php?action=buscar_selecoes_ligacao&ligacao_id=${ligacaoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                exibirDetalhesLigacao(data.selecoes, ligacaoId);
            } else {
                modalBody.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle error-icon"></i>
                        <p>Erro ao carregar detalhes: ${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            modalBody.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle error-icon"></i>
                    <p>Erro ao carregar detalhes da ligação</p>
                </div>
            `;
        });
}

// Função para exibir detalhes da ligação
function exibirDetalhesLigacao(selecoes, ligacaoId) {
    const modalBody = document.getElementById('modalDetalhesBody');
    
    if (!selecoes || selecoes.length === 0) {
        modalBody.innerHTML = `
            <div class="info-message">
                <i class="fas fa-info-circle info-icon"></i>
                <p>Nenhuma resposta encontrada para esta ligação.</p>
            </div>
        `;
        return;
    }
    
    // Contar respostas com campos condicionais
    const respostasComCampos = selecoes.filter(s => s.campos_adicionais && Object.keys(s.campos_adicionais).length > 0);
    
    let html = `
        <div class="detalhes-info">
            <div class="detalhes-info-grid">
                <div class="detalhes-info-item">
                    <strong>ID da Ligação:</strong>
                    <span>#${ligacaoId}</span>
                </div>
                <div class="detalhes-info-item">
                    <strong>Total de Respostas:</strong>
                    <span>${selecoes.length}</span>
                </div>
                <div class="detalhes-info-item">
                    <strong>Com Campos Condicionais:</strong>
                    <span style="color: ${respostasComCampos.length > 0 ? '#28a745' : '#6c757d'}">
                        ${respostasComCampos.length} resposta${respostasComCampos.length !== 1 ? 's' : ''}
                    </span>
                </div>
                <div class="detalhes-info-item">
                    <strong>Data da Consulta:</strong>
                    <span>${new Date().toLocaleDateString('pt-BR')}</span>
                </div>
            </div>
        </div>
        
        <div class="respostas-container">
            <h4 class="respostas-header">
                <i class="fas fa-list"></i> Respostas do Questionário
            </h4>
    `;
    
    selecoes.forEach((selecao, index) => {
        const temCamposAdicionais = selecao.campos_adicionais && Object.keys(selecao.campos_adicionais).length > 0;
        
        html += `
            <div class="resposta-item ${temCamposAdicionais ? 'com-campos-condicionais' : ''}">
                <div class="resposta-pergunta">
                    <strong>${index + 1}.</strong> ${selecao.pergunta}
                    ${temCamposAdicionais ? '<span class="campo-condicional-indicator"><i class="fas fa-info-circle"></i> Com campos condicionais</span>' : ''}
                </div>
                <div class="resposta-valor">
                    ${selecao.resposta}
                </div>
        `;
        
        // Adicionar campos adicionais se existirem
        if (temCamposAdicionais) {
            html += `
                <div class="campos-adicionais">
                    <h5 class="campos-condicionais-header">
                        <i class="fas fa-plus-circle"></i> Campos Condicionais
                    </h5>
                    <div class="campos-condicionais-content">
            `;
            
            Object.entries(selecao.campos_adicionais).forEach(([campo, valor]) => {
                // Formatar o nome do campo para ficar mais legível
                const campoFormatado = campo
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, l => l.toUpperCase());
                
                html += `
                    <div class="campo-adicional-row">
                        <div class="campo-adicional-flex">
                            <span class="campo-adicional-label-wrapper">
                                <i class="fas fa-tag campo-adicional-label-icon"></i>
                                ${campoFormatado}:
                            </span>
                            <span class="campo-adicional-valor-wrapper">
                                ${valor || '<em style="color: #6c757d;">Não informado</em>'}
                            </span>
                        </div>
                    </div>
                `;
            });
            
            // Adicionar visualização JSON bruta (colapsável)
            html += `
                    </div>
                    <details class="json-toggle">
                        <summary>
                            <i class="fas fa-code"></i> Ver JSON Bruto
                        </summary>
                        <pre class="json-content">
${JSON.stringify(selecao.campos_adicionais, null, 2)}
                        </pre>
                    </details>
                </div>
            `;
        }
        
        html += `</div>`;
    });
    
    // Adicionar seção de resumo dos campos condicionais
    if (respostasComCampos.length > 0) {
        html += `
            <div class="resumo-campos">
                <h4 class="resumo-campos-header">
                    <i class="fas fa-chart-bar"></i> Resumo dos Campos Condicionais
                </h4>
                <div class="resumo-campos-grid">
        `;
        
        // Agrupar campos por tipo
        const camposAgrupados = {};
        respostasComCampos.forEach(resposta => {
            Object.entries(resposta.campos_adicionais).forEach(([campo, valor]) => {
                if (!camposAgrupados[campo]) {
                    camposAgrupados[campo] = [];
                }
                camposAgrupados[campo].push(valor);
            });
        });
        
        Object.entries(camposAgrupados).forEach(([campo, valores]) => {
            const campoFormatado = campo.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            const valoresUnicos = [...new Set(valores)];
            
            html += `
                <div class="resumo-campo-item">
                    <h5 class="resumo-campo-header">
                        <i class="fas fa-tag resumo-campo-icon"></i> ${campoFormatado}
                    </h5>
                    <p class="resumo-campo-count">
                        ${valoresUnicos.length} valor${valoresUnicos.length !== 1 ? 'es' : ''} único${valoresUnicos.length !== 1 ? 's' : ''}
                    </p>
                    <div class="resumo-campo-values">
                        ${valoresUnicos.map(valor => `<span class="resumo-campo-badge">${valor}</span>`).join('')}
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    html += `</div>`;
    
    modalBody.innerHTML = html;
}

// Função para fechar modal de detalhes
function fecharModalDetalhes() {
    document.getElementById('modalDetalhesLigacao').style.display = 'none';
}

// Função para gerar relatório
function gerarRelatorio(ligacaoId) {
    // Implementar geração de relatório em PDF ou Excel
    alert('Funcionalidade de relatório será implementada em breve!');
}

// Função para excluir ligação
function excluirLigacao(ligacaoId) {
    if (!confirm('Tem certeza que deseja excluir esta ligação? A ligação será marcada como excluída e não aparecerá mais na lista.')) {
        return;
    }
    
    // Mostrar loading
    const btn = document.querySelector(`[data-ligacao-id="${ligacaoId}"].btn-excluir`);
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Excluindo...';
    btn.disabled = true;
    
    // Fazer requisição para excluir
    fetch('/Site/includes/management/gerenciar_ligacoes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=excluir_ligacao&ligacao_id=${ligacaoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remover a linha da tabela
            const row = btn.closest('tr');
            row.style.backgroundColor = '#ffebee';
            row.style.transition = 'background-color 0.5s ease';
            
            setTimeout(() => {
                row.remove();
                // Verificar se a tabela ficou vazia
                const tbody = document.querySelector('.ligacoes-table tbody');
                if (tbody.children.length === 0) {
                    // Mostrar mensagem de "nenhuma ligação" sem recarregar a página
                    const tableWrapper = document.querySelector('.table-wrapper');
                    if (tableWrapper) {
                        tableWrapper.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-phone-slash empty-icon"></i>
                                <p class="empty-text">Nenhuma ligação encontrada.</p>
                            </div>
                        `;
                    }
                }
            }, 500);
            
            // Mostrar mensagem de sucesso
            mostrarNotificacao('Ligação excluída com sucesso!', 'success');
        } else {
            throw new Error(data.message || 'Erro ao excluir ligação');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mostrarNotificacao('Erro ao excluir ligação: ' + error.message, 'error');
        
        // Restaurar botão
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Função para mostrar notificações
function mostrarNotificacao(mensagem, tipo) {
    // Remover notificação existente
    const notificacaoExistente = document.querySelector('.notificacao');
    if (notificacaoExistente) {
        notificacaoExistente.remove();
    }
    
    // Criar nova notificação
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao notificacao-${tipo}`;
    notificacao.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
        <span>${mensagem}</span>
        <button class="notificacao-fechar" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Adicionar ao body
    document.body.appendChild(notificacao);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (notificacao.parentElement) {
            notificacao.remove();
        }
    }, 5000);
}

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Fechar modal ao clicar fora dele
    const modal = document.getElementById('modalDetalhesLigacao');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalDetalhes();
            }
        });
    }
    
    // Event listeners para botões de detalhes
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-detalhes')) {
            const ligacaoId = e.target.closest('.btn-detalhes').getAttribute('data-ligacao-id');
            verDetalhesLigacao(ligacaoId);
        }
        
        if (e.target.closest('.btn-relatorio')) {
            const ligacaoId = e.target.closest('.btn-relatorio').getAttribute('data-ligacao-id');
            gerarRelatorio(ligacaoId);
        }
        
        if (e.target.closest('.btn-excluir')) {
            const ligacaoId = e.target.closest('.btn-excluir').getAttribute('data-ligacao-id');
            excluirLigacao(ligacaoId);
        }
        
        if (e.target.closest('.btn-fechar-modal')) {
            fecharModalDetalhes();
        }
    });
    
    // Adicionar listeners para filtros automáticos (opcional)
    const filtros = document.querySelectorAll('.filtro-grupo select, .filtro-grupo input');
    filtros.forEach(filtro => {
        filtro.addEventListener('change', function() {
            // Auto-submit do formulário quando filtros mudarem
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // Definir largura das barras de progresso
    const barrasProgresso = document.querySelectorAll('.progresso-fill');
    barrasProgresso.forEach(barra => {
        const largura = barra.getAttribute('data-width');
        if (largura) {
            barra.style.width = largura + '%';
        }
    });
});
