/**
 * GESTÃO DE CONTRATOS - JAVASCRIPT
 * Interatividade para a página de gestão de contratos
 */

// ============================================
// TOGGLE CONTRATOS POR GERENCIADOR
// ============================================

function toggleContratos(gerenciador) {
    const card = document.querySelector(`[data-gerenciador="${gerenciador}"]`);
    const contratosFilhos = card.querySelector('.contratos-filhos');
    const btnToggle = card.querySelector('.btn-toggle i');
    
    if (contratosFilhos.style.display === 'none' || !contratosFilhos.style.display) {
        contratosFilhos.style.display = 'block';
        card.classList.add('expanded');
        btnToggle.style.transform = 'rotate(180deg)';
    } else {
        contratosFilhos.style.display = 'none';
        card.classList.remove('expanded');
        btnToggle.style.transform = 'rotate(0deg)';
    }
}

// ============================================
// FORMULÁRIO INLINE
// ============================================

function mostrarFormularioNovoContrato() {
    const formulario = document.getElementById('formulario-novo-contrato');
    formulario.style.display = 'block';
    formulario.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Focar no primeiro campo
    setTimeout(() => {
        document.getElementById('razao_social').focus();
    }, 400);
}

function fecharFormulario() {
    const formulario = document.getElementById('formulario-novo-contrato');
    formulario.style.display = 'none';
    
    // Limpar formulário
    document.getElementById('form-novo-contrato').reset();
    document.getElementById('novo-gerenciador-group').style.display = 'none';
}

// ============================================
// GERENCIADOR SELECT HANDLER
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const gerenciadorSelect = document.getElementById('gerenciador');
    const novoGerenciadorGroup = document.getElementById('novo-gerenciador-group');
    
    if (gerenciadorSelect) {
        gerenciadorSelect.addEventListener('change', function() {
            if (this.value === '__novo__') {
                novoGerenciadorGroup.style.display = 'block';
                document.getElementById('novo_gerenciador').required = true;
            } else {
                novoGerenciadorGroup.style.display = 'none';
                document.getElementById('novo_gerenciador').required = false;
            }
        });
    }
    
    // Máscara de CNPJ
    const cnpjInput = document.getElementById('cnpj');
    if (cnpjInput) {
        cnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });
    }
});

// ============================================
// SALVAR NOVO CONTRATO
// ============================================

async function salvarNovoContrato(event) {
    event.preventDefault();
    
    const form = document.getElementById('form-novo-contrato');
    const formData = new FormData(form);
    
    // Se selecionou "novo gerenciador", usar o valor do input
    if (formData.get('gerenciador') === '__novo__') {
        const novoGerenciador = formData.get('novo_gerenciador');
        if (!novoGerenciador) {
            mostrarMensagem('Por favor, informe o nome do novo gerenciador.', 'erro');
            return;
        }
        formData.set('gerenciador', novoGerenciador);
    }
    
    // Mostrar loading
    const btnSalvar = event.target.querySelector('.btn-salvar');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    btnSalvar.disabled = true;
    
    try {
        const response = await fetch('../includes/ajax/gestao_contratos_crud.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostrarMensagem('Contrato adicionado com sucesso!', 'sucesso');
            
            // Recarregar página após 1.5 segundos
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            mostrarMensagem(result.message || 'Erro ao salvar contrato.', 'erro');
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    } catch (error) {
        console.error('Erro:', error);
        mostrarMensagem('Erro ao conectar com o servidor.', 'erro');
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
    }
}

// ============================================
// MENSAGENS DE FEEDBACK
// ============================================

function mostrarMensagem(mensagem, tipo = 'info') {
    // Remover mensagem anterior se existir
    const mensagemExistente = document.querySelector('.mensagem-feedback');
    if (mensagemExistente) {
        mensagemExistente.remove();
    }
    
    // Criar nova mensagem
    const div = document.createElement('div');
    div.className = `mensagem-feedback mensagem-${tipo}`;
    div.innerHTML = `
        <div class="mensagem-conteudo">
            <i class="fas ${tipo === 'sucesso' ? 'fa-check-circle' : tipo === 'erro' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${mensagem}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="btn-fechar-mensagem">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Adicionar ao body
    document.body.appendChild(div);
    
    // Adicionar estilos se não existirem
    if (!document.getElementById('mensagem-feedback-styles')) {
        const style = document.createElement('style');
        style.id = 'mensagem-feedback-styles';
        style.textContent = `
            .mensagem-feedback {
                position: fixed;
                top: 20px;
                right: 20px;
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 12px;
                padding: 1rem 1.5rem;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 1rem;
                min-width: 300px;
                animation: slideInRight 0.4s ease;
            }
            
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            .mensagem-conteudo {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: white;
                flex: 1;
            }
            
            .mensagem-conteudo i {
                font-size: 1.25rem;
            }
            
            .mensagem-sucesso .mensagem-conteudo i {
                color: #38ef7d;
            }
            
            .mensagem-erro .mensagem-conteudo i {
                color: #f5576c;
            }
            
            .mensagem-info .mensagem-conteudo i {
                color: #667eea;
            }
            
            .btn-fechar-mensagem {
                background: rgba(255, 255, 255, 0.1);
                border: none;
                border-radius: 50%;
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .btn-fechar-mensagem:hover {
                background: rgba(255, 255, 255, 0.2);
            }
        `;
        document.head.appendChild(style);
    }
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (div.parentElement) {
            div.style.animation = 'slideOutRight 0.4s ease';
            setTimeout(() => div.remove(), 400);
        }
    }, 5000);
}

// ============================================
// ANIMAÇÃO DE ENTRADA DOS CARDS
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.gerenciador-card-glass');
    
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// ============================================
// ANIMAÇÃO DE PROGRESSO
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.progress-fill, .contrato-mini-progress-fill');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const width = entry.target.style.width;
                entry.target.style.width = '0%';
                setTimeout(() => {
                    entry.target.style.width = width;
                }, 100);
                observer.unobserve(entry.target);
            }
        });
    });
    
    progressBars.forEach(bar => observer.observe(bar));
});

// ============================================
// KEYBOARD SHORTCUTS
// ============================================

document.addEventListener('keydown', function(e) {
    // ESC para fechar formulário
    if (e.key === 'Escape') {
        const formulario = document.getElementById('formulario-novo-contrato');
        if (formulario && formulario.style.display !== 'none') {
            fecharFormulario();
        }
    }
    
    // Ctrl/Cmd + N para novo contrato
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        mostrarFormularioNovoContrato();
    }
});

// ============================================
// EXPANDIR TODOS / COLAPSAR TODOS
// ============================================

function expandirTodos() {
    const cards = document.querySelectorAll('.gerenciador-card-glass');
    cards.forEach(card => {
        const gerenciador = card.getAttribute('data-gerenciador');
        const contratosFilhos = card.querySelector('.contratos-filhos');
        const btnToggle = card.querySelector('.btn-toggle i');
        
        contratosFilhos.style.display = 'block';
        card.classList.add('expanded');
        btnToggle.style.transform = 'rotate(180deg)';
    });
}

function colapsarTodos() {
    const cards = document.querySelectorAll('.gerenciador-card-glass');
    cards.forEach(card => {
        const contratosFilhos = card.querySelector('.contratos-filhos');
        const btnToggle = card.querySelector('.btn-toggle i');
        
        contratosFilhos.style.display = 'none';
        card.classList.remove('expanded');
        btnToggle.style.transform = 'rotate(0deg)';
    });
}

console.log('Gestão de Contratos JS carregado! 🚀');
console.log('Atalhos: ESC = Fechar formulário | Ctrl+N = Novo contrato');

