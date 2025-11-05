/**
 * Scripts para a página de cadastro de clientes
 * Funcionalidades: verificação de CNPJ em tempo real
 */

document.addEventListener('DOMContentLoaded', function() {
    const cnpjInput = document.getElementById('cnpj_faturamento');
    const formFields = document.querySelectorAll('input, select, textarea');
    const submitButton = document.querySelector('.btn-cadastrar');
    let cnpjExiste = false;
    
    // Função para extrair raiz do CNPJ
    function extrairRaizCNPJ(cnpj) {
        if (!cnpj) return '';
        const cnpjLimpo = cnpj.replace(/[^0-9]/g, '');
        return cnpjLimpo.substring(0, 8);
    }
    
    // Função para verificar CNPJ via AJAX
    async function verificarCNPJ(cnpj) {
        if (!cnpj || cnpj.length < 8) {
            return { existe: false, mensagem: '' };
        }
        
        try {
            const response = await fetch(`cadastro.php?ajax=verificar_cnpj&cnpj=${encodeURIComponent(cnpj)}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Erro ao verificar CNPJ:', error);
            return { existe: false, mensagem: '' };
        }
    }
    
    // Função para bloquear/desbloquear campos
    function toggleCampos(bloquear) {
        formFields.forEach(field => {
            if (field.id !== 'cnpj_faturamento') {
                field.disabled = bloquear;
            }
        });
        
        submitButton.disabled = bloquear;
    }
    
    // Função para mostrar/esconder aviso
    function toggleAviso(mostrar, mensagem = '') {
        let aviso = document.getElementById('aviso-cnpj');
        
        if (mostrar) {
            if (!aviso) {
                aviso = document.createElement('div');
                aviso.id = 'aviso-cnpj';
                aviso.className = 'aviso-cnpj-existente';
                aviso.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>${mensagem}</span>
                `;
                cnpjInput.parentNode.appendChild(aviso);
            }
            aviso.style.display = 'block';
        } else if (aviso) {
            aviso.style.display = 'none';
        }
    }
    
    // Função para aplicar estilos de erro no campo CNPJ
    function aplicarEstiloErro(aplicar) {
        if (aplicar) {
            cnpjInput.classList.add('error');
        } else {
            cnpjInput.classList.remove('error');
        }
    }
    
    // Event listener para mudanças no campo CNPJ
    let timeoutId;
    cnpjInput.addEventListener('input', function() {
        clearTimeout(timeoutId);
        
        const cnpj = this.value;
        const raizCNPJ = extrairRaizCNPJ(cnpj);
        
        // Limpar avisos anteriores
        toggleAviso(false);
        aplicarEstiloErro(false);
        
        // Se CNPJ tem pelo menos 8 dígitos, verificar
        if (raizCNPJ.length >= 8) {
            timeoutId = setTimeout(async () => {
                const resultado = await verificarCNPJ(cnpj);
                
                if (resultado.existe) {
                    cnpjExiste = true;
                    toggleCampos(true);
                    toggleAviso(true, resultado.mensagem);
                    aplicarEstiloErro(true);
                } else {
                    cnpjExiste = false;
                    toggleCampos(false);
                    aplicarEstiloErro(false);
                }
            }, 500); // Delay de 500ms para evitar muitas requisições
        } else {
            cnpjExiste = false;
            toggleCampos(false);
            aplicarEstiloErro(false);
        }
    });
    
    // Event listener para envio do formulário
    document.querySelector('form').addEventListener('submit', function(e) {
        if (cnpjExiste) {
            e.preventDefault();
            alert('Não é possível cadastrar um CNPJ que já existe na carteira de clientes.');
            return false;
        }
    });
});

// Função global para copiar conteúdo para a área de transferência
function copyToClipboard() {
    const textarea = document.getElementById('email-content');
    if (textarea) {
        textarea.select();
        textarea.setSelectionRange(0, 99999); // Para dispositivos móveis
        
        try {
            // Método moderno
            navigator.clipboard.writeText(textarea.value).then(function() {
                showCopySuccess();
            }).catch(function(err) {
                // Fallback para navegadores mais antigos
                fallbackCopyTextToClipboard(textarea.value);
            });
        } catch (err) {
            // Fallback para navegadores mais antigos
            fallbackCopyTextToClipboard(textarea.value);
        }
    }
}

// Função fallback para copiar texto
function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess();
        } else {
            showCopyError();
        }
    } catch (err) {
        showCopyError();
    }
    
    document.body.removeChild(textArea);
}

// Função para mostrar sucesso na cópia
function showCopySuccess() {
    const button = document.querySelector('button[onclick="copyToClipboard()"]');
    if (button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copiado!';
        button.style.background = '#28a745';
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.style.background = '#6c757d';
        }, 2000);
    }
}

// Função para mostrar erro na cópia
function showCopyError() {
    const button = document.querySelector('button[onclick="copyToClipboard()"]');
    if (button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-times"></i> Erro!';
        button.style.background = '#dc3545';
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.style.background = '#6c757d';
        }, 2000);
    }
}
