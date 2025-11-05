/**
 * JavaScript para gerenciar marcação de clientes como "ligados"
 */

(function() {
    'use strict';

    // Função para marcar/desmarcar cliente como ligado
    function marcarClienteLigado(checkbox) {
        const raizCnpj = checkbox.dataset.raizCnpj;
        const cnpjCompleto = checkbox.dataset.cnpj;
        const clienteNome = checkbox.dataset.clienteNome;
        const estaMarcado = checkbox.checked;
        const acao = estaMarcado ? 'marcar' : 'desmarcar';

        // Desabilitar checkbox durante a requisição
        checkbox.disabled = true;
        const linhaOriginal = checkbox.closest('tr') || checkbox.closest('.cliente-card');
        
        // Adicionar indicador visual de carregamento
        const originalTitle = checkbox.title;
        checkbox.title = 'Processando...';

        // Fazer requisição AJAX
        fetch('/Site/includes/ajax/marcar_cliente_ligado_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                acao: acao,
                raiz_cnpj: raizCnpj,
                cnpj_completo: cnpjCompleto,
                cliente_nome: clienteNome
            })
        })
        .then(response => response.json())
        .then(data => {
            checkbox.disabled = false;
            
            if (data.success) {
                // Atualizar estado visual da linha/card
                if (linhaOriginal) {
                    if (estaMarcado) {
                        linhaOriginal.classList.add('cliente-marcado-ligado');
                        checkbox.title = 'Já ligado - Clique para desmarcar';
                    } else {
                        linhaOriginal.classList.remove('cliente-marcado-ligado');
                        checkbox.title = 'Clique para marcar como ligado';
                    }
                }

                // Mostrar notificação de sucesso
                if (typeof mostrarNotificacao === 'function') {
                    mostrarNotificacao(data.message, 'success');
                } else {
                    // Fallback para notificação simples
                    console.log(data.message);
                }
            } else {
                // Reverter estado do checkbox se houver erro
                checkbox.checked = !estaMarcado;
                checkbox.title = originalTitle;
                
                if (typeof mostrarNotificacao === 'function') {
                    mostrarNotificacao(data.message || 'Erro ao processar solicitação', 'error');
                } else {
                    alert(data.message || 'Erro ao processar solicitação');
                }
            }
        })
        .catch(error => {
            console.error('Erro ao marcar cliente:', error);
            checkbox.disabled = false;
            checkbox.checked = !estaMarcado;
            checkbox.title = originalTitle;
            
            if (typeof mostrarNotificacao === 'function') {
                mostrarNotificacao('Erro ao processar solicitação. Tente novamente.', 'error');
            } else {
                alert('Erro ao processar solicitação. Tente novamente.');
            }
        });
    }

    // Adicionar event listeners quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        // Adicionar listener para todos os checkboxes existentes
        document.querySelectorAll('.checkbox-ligado').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                marcarClienteLigado(this);
            });
        });

        // Usar MutationObserver para detectar novos checkboxes adicionados dinamicamente
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Verificar se o nó é um checkbox
                        if (node.classList && node.classList.contains('checkbox-ligado')) {
                            node.addEventListener('change', function() {
                                marcarClienteLigado(this);
                            });
                        }
                        // Verificar se contém checkboxes dentro dele
                        const checkboxes = node.querySelectorAll ? node.querySelectorAll('.checkbox-ligado') : [];
                        checkboxes.forEach(function(checkbox) {
                            checkbox.addEventListener('change', function() {
                                marcarClienteLigado(this);
                            });
                        });
                    }
                });
            });
        });

        // Observar mudanças no container de clientes
        const clientesContainer = document.getElementById('clientesListContainer');
        if (clientesContainer) {
            observer.observe(clientesContainer, {
                childList: true,
                subtree: true
            });
        }
    });

    // Expor função globalmente para uso em outros scripts se necessário
    window.marcarClienteLigado = marcarClienteLigado;
})();




