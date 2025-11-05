// Detalhes do Contrato - Scripts Específicos

/**
 * Calcular valores automaticamente
 */
function calcularValores() {
    const valorGlobal = parseFloat(document.getElementById('valor_global').value) || 0;
    const valorConsumido = parseFloat(document.getElementById('valor_consumido').value) || 0;
    
    const saldo = valorGlobal - valorConsumido;
    const percentual = valorGlobal > 0 ? (valorConsumido / valorGlobal) * 100 : 0;
    
    // Atualizar campos calculados
    document.getElementById('saldo_disponivel').value = `R$ ${formatarMoeda(saldo)}`;
    document.getElementById('percentual_consumido').value = `${percentual.toFixed(1)}%`;
    
    // Atualizar cores do percentual
    const percentualElement = document.getElementById('percentual_consumido');
    if (percentual >= 90) {
        percentualElement.style.color = '#f44336';
        percentualElement.style.fontWeight = 'bold';
    } else if (percentual >= 70) {
        percentualElement.style.color = '#ff9800';
        percentualElement.style.fontWeight = 'bold';
    } else {
        percentualElement.style.color = '#4caf50';
        percentualElement.style.fontWeight = 'normal';
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
 * Formatar CNPJ
 */
function formatarCNPJ(input) {
    let valor = input.value.replace(/\D/g, '');
    
    if (valor.length <= 14) {
        valor = valor.replace(/^(\d{2})(\d)/, '$1.$2');
        valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        valor = valor.replace(/\.(\d{3})(\d)/, '.$1/$2');
        valor = valor.replace(/(\d{4})(\d)/, '$1-$2');
    }
    
    input.value = valor;
}

/**
 * Formatar telefone
 */
function formatarTelefone(input) {
    let valor = input.value.replace(/\D/g, '');
    
    if (valor.length <= 11) {
        if (valor.length <= 2) {
            valor = valor.replace(/^(\d{0,2})/, '($1');
        } else if (valor.length <= 6) {
            valor = valor.replace(/^(\d{2})(\d{0,4})/, '($1) $2');
        } else if (valor.length <= 10) {
            valor = valor.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        } else {
            valor = valor.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        }
    }
    
    input.value = valor;
}

/**
 * Salvar contrato
 */
async function salvarContrato() {
    const form = document.getElementById('formContrato');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Validar campos obrigatórios
    if (!data.razao_social || !data.gerenciador || !data.valor_global) {
        mostrarNotificacao('Por favor, preencha todos os campos obrigatórios.', 'error');
        return;
    }
    
    // Validar valor global
    if (parseFloat(data.valor_global) <= 0) {
        mostrarNotificacao('O valor global deve ser maior que zero.', 'error');
        return;
    }
    
    // Validar datas
    if (data.data_inicio_vigencia && data.data_termino_vigencia) {
        const inicio = new Date(data.data_inicio_vigencia);
        const termino = new Date(data.data_termino_vigencia);
        
        if (inicio >= termino) {
            mostrarNotificacao('A data de término deve ser posterior à data de início.', 'error');
            return;
        }
    }
    
    // Mostrar loading
    const btnSalvar = document.querySelector('.btn-primary');
    const originalText = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    btnSalvar.disabled = true;
    
    try {
        const formDataPost = new FormData();
        formDataPost.append('action', 'update');
        
        // Adicionar todos os campos
        Object.keys(data).forEach(key => {
            formDataPost.append(key, data[key]);
        });
        
        const response = await fetch('../includes/ajax/contratos_crud_robusto.php', {
            method: 'POST',
            body: formDataPost
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostrarNotificacao(result.message, 'success');
            
            // Atualizar informações de auditoria
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            mostrarNotificacao(result.message, 'error');
        }
    } catch (error) {
        mostrarNotificacao('Erro de conexão: ' + error.message, 'error');
    } finally {
        // Restaurar botão
        btnSalvar.innerHTML = originalText;
        btnSalvar.disabled = false;
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
        const response = await fetch('../includes/ajax/contratos_crud_robusto.php', {
            method: 'POST',
            body: form
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            setTimeout(() => {
                window.location.href = window.baseUrl ? window.baseUrl('contratos') : 'contratos.php';
            }, 1500);
        } else {
            mostrarNotificacao(data.message, 'error');
        }
    } catch (error) {
        mostrarNotificacao('Erro de conexão: ' + error.message, 'error');
    }
}

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
    
    // Adicionar estilos se não existirem
    if (!document.getElementById('notificacao-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notificacao-styles';
        styles.textContent = `
            .notificacao {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                padding: 1rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                z-index: 1100;
                max-width: 400px;
                transform: translateX(100%);
                opacity: 0;
                transition: all 0.3s ease;
            }
            
            .notificacao.show {
                transform: translateX(0);
                opacity: 1;
            }
            
            .notificacao-content {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex: 1;
            }
            
            .notificacao-content i {
                font-size: 1.125rem;
            }
            
            .notificacao-success .notificacao-content i {
                color: #28a745;
            }
            
            .notificacao-error .notificacao-content i {
                color: #dc3545;
            }
            
            .notificacao-info .notificacao-content i {
                color: #17a2b8;
            }
            
            .notificacao-close {
                background: none;
                border: none;
                color: #6c757d;
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 4px;
                transition: all 0.2s ease;
            }
            
            .notificacao-close:hover {
                background: #f8f9fa;
                color: #495057;
            }
        `;
        document.head.appendChild(styles);
    }
    
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
 * Event Listeners
 */
document.addEventListener('DOMContentLoaded', function() {
    // Calcular valores automaticamente
    document.getElementById('valor_global').addEventListener('input', calcularValores);
    document.getElementById('valor_consumido').addEventListener('input', calcularValores);
    
    // Formatação de campos
    document.getElementById('cnpj').addEventListener('input', function() {
        formatarCNPJ(this);
    });
    
    document.getElementById('telefone').addEventListener('input', function() {
        formatarTelefone(this);
    });
    
    // Validação em tempo real
    document.getElementById('valor_global').addEventListener('blur', function() {
        const valor = parseFloat(this.value);
        if (valor <= 0) {
            this.style.borderColor = '#f44336';
            mostrarNotificacao('O valor global deve ser maior que zero.', 'error');
        } else {
            this.style.borderColor = '#4caf50';
        }
    });
    
    // Validação de datas
    document.getElementById('data_inicio_vigencia').addEventListener('change', function() {
        const inicio = new Date(this.value);
        const termino = document.getElementById('data_termino_vigencia').value;
        
        if (termino) {
            const dataTermino = new Date(termino);
            if (inicio >= dataTermino) {
                document.getElementById('data_termino_vigencia').style.borderColor = '#f44336';
                mostrarNotificacao('A data de término deve ser posterior à data de início.', 'error');
            } else {
                document.getElementById('data_termino_vigencia').style.borderColor = '#4caf50';
            }
        }
    });
    
    document.getElementById('data_termino_vigencia').addEventListener('change', function() {
        const termino = new Date(this.value);
        const inicio = document.getElementById('data_inicio_vigencia').value;
        
        if (inicio) {
            const dataInicio = new Date(inicio);
            if (dataInicio >= termino) {
                this.style.borderColor = '#f44336';
                mostrarNotificacao('A data de término deve ser posterior à data de início.', 'error');
            } else {
                this.style.borderColor = '#4caf50';
            }
        }
    });
    
    // Calcular valores iniciais
    calcularValores();
    
    // Salvar com Ctrl+S
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            salvarContrato();
        }
    });
    
    // Aviso de alterações não salvas
    let hasChanges = false;
    const form = document.getElementById('formContrato');
    
    form.addEventListener('input', function() {
        hasChanges = true;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = 'Você tem alterações não salvas. Deseja realmente sair?';
        }
    });
    
    // Marcar como salvo quando salvar
    const originalSalvarContrato = salvarContrato;
    salvarContrato = async function() {
        await originalSalvarContrato();
        hasChanges = false;
    };
});
