/**
 * Home - JavaScript
 * Funcionalidades específicas para a página inicial
 */



// Função para gerar o heatmap
function gerarHeatmap(heatmapData) {
    const container = document.getElementById('heatmap-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    // Criar array com os últimos 7 dias
    const ultimos7Dias = [];
    for (let i = 6; i >= 0; i--) {
        const data = new Date();
        data.setDate(data.getDate() - i);
        ultimos7Dias.push(data.toISOString().split('T')[0]);
    }
    
    // Encontrar o máximo de ligações para normalizar as cores
    const maxLigacoes = Math.max(...heatmapData.map(item => item.total_ligacoes || 0), 1);
    
    ultimos7Dias.forEach(data => {
        const dadosDia = heatmapData.find(item => item.data_ligacao === data) || { total_ligacoes: 0 };
        const ligacoes = dadosDia.total_ligacoes || 0;
        const intensidade = Math.min(ligacoes / maxLigacoes, 1);
        
        // Calcular cor baseada na intensidade
        const r = Math.round(76 + (179 - 76) * intensidade);
        const g = Math.round(175 + (255 - 175) * intensidade);
        const b = Math.round(80 + (255 - 80) * intensidade);
        
        const dia = new Date(data);
        const nomeDia = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][dia.getDay()];
        const diaMes = dia.getDate();
        
        const heatmapItem = document.createElement('div');
        heatmapItem.className = 'heatmap-item';
        heatmapItem.style.backgroundColor = `rgb(${r}, ${g}, ${b})`;
        
        heatmapItem.innerHTML = `
            <div class="heatmap-dia">${nomeDia}</div>
            <div class="heatmap-data">${diaMes}</div>
            <div class="heatmap-ligacoes">${ligacoes}</div>
        `;
        
        heatmapItem.title = `${nomeDia}, ${diaMes} - ${ligacoes} ligações`;
        
        heatmapItem.addEventListener('mouseenter', () => {
            heatmapItem.style.transform = 'scale(1.1)';
        });
        
        heatmapItem.addEventListener('mouseleave', () => {
            heatmapItem.style.transform = 'scale(1)';
        });
        
        container.appendChild(heatmapItem);
    });
}

// Função para aplicar classes de status dinâmicas
function aplicarClassesStatus() {
    // Aplicar classes de status para o card de metas
    const statusElement = document.querySelector('.card-metas-status');
    if (statusElement) {
        const percentual = parseFloat(statusElement.getAttribute('data-percentual') || '0');
        
        // Remover classes existentes
        statusElement.classList.remove('atingida', 'proxima', 'baixa');
        
        // Adicionar classe apropriada
        if (percentual >= 100) {
            statusElement.classList.add('atingida');
        } else if (percentual >= 80) {
            statusElement.classList.add('proxima');
        } else {
            statusElement.classList.add('baixa');
        }
    }
    
    // Aplicar classes de status para o texto
    const statusTextoElement = document.querySelector('.card-metas-status-texto');
    if (statusTextoElement) {
        const percentual = parseFloat(statusTextoElement.getAttribute('data-percentual') || '0');
        
        // Remover classes existentes
        statusTextoElement.classList.remove('atingida', 'proxima', 'baixa');
        
        // Adicionar classe apropriada
        if (percentual >= 100) {
            statusTextoElement.classList.add('atingida');
        } else if (percentual >= 80) {
            statusTextoElement.classList.add('proxima');
        } else {
            statusTextoElement.classList.add('baixa');
        }
    }
}

// Função para mudar a visão do diretor
function mudarVisao() {
    const supervisorSelect = document.getElementById('visao_supervisor');
    const vendedorSelect = document.getElementById('visao_vendedor');
    
    // Para supervisores, pode não ter o seletor de supervisor
    if (!vendedorSelect) {
        return;
    }
    
    const supervisorSelecionado = supervisorSelect ? supervisorSelect.value : '';
    const vendedorSelecionado = vendedorSelect.value;
    
    // Construir URL com parâmetros
    const urlParams = new URLSearchParams();
    
    if (supervisorSelecionado) {
        urlParams.set('visao_supervisor', supervisorSelecionado);
    }
    
    if (vendedorSelecionado) {
        urlParams.set('visao_vendedor', vendedorSelecionado);
    }
    
    const novaURL = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
    
    // Recarregar a página com os novos parâmetros
    window.location.href = novaURL;
}



// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Verificar se os seletores de visão existem
        const supervisorSelector = document.getElementById('visao_supervisor');
        const vendedorSelector = document.getElementById('visao_vendedor');
        
        if (supervisorSelector) {
            supervisorSelector.addEventListener('change', function() {
                mudarVisao();
            });
        }
        
        if (vendedorSelector) {
            vendedorSelector.addEventListener('change', function() {
                mudarVisao();
            });
        }
    
    // Aplicar classes de status dinâmicas
    aplicarClassesStatus();
    
    // Adicionar event listeners para interações
    const cardMetas = document.querySelector('.card-metas');
    if (cardMetas) {
        cardMetas.addEventListener('click', function() {
            // Adicionar funcionalidade de clique se necessário
            console.log('Card de metas clicado');
        });
    }
    
    // Adicionar animações suaves para elementos que aparecem
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observar elementos para animação
    const elementosParaAnimar = document.querySelectorAll('.card-metas, .card, .avisos-container');
    elementosParaAnimar.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
    } catch (error) {
        console.error('Erro na inicialização do home.js:', error);
    }
});
