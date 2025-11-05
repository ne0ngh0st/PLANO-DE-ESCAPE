// ===== NAVEGAÇÃO MOBILE - FUNCIONALIDADES AVANÇADAS =====

class MobileNavigation {
    constructor() {
        this.mobileToggle = document.getElementById('mobileNavToggle');
        this.mobileNav = document.getElementById('mobileNav');
        this.mobileOverlay = document.getElementById('mobileNavOverlay');
        this.mobileClose = document.getElementById('mobileNavClose');
        this.isOpen = false;
        
        this.init();
    }
    
    init() {
        if (!this.mobileToggle || !this.mobileNav) {
            console.warn('Elementos da navegação mobile não encontrados');
            console.log('mobileToggle:', this.mobileToggle);
            console.log('mobileNav:', this.mobileNav);
            console.log('Tentando encontrar elementos...');
            
            // Tentar encontrar os elementos novamente
            this.mobileToggle = document.getElementById('mobileNavToggle');
            this.mobileNav = document.getElementById('mobileNav');
            this.mobileOverlay = document.getElementById('mobileNavOverlay');
            this.mobileClose = document.getElementById('mobileNavClose');
            
            if (!this.mobileToggle || !this.mobileNav) {
                console.error('Elementos ainda não encontrados após nova busca');
                return;
            }
        }
        
        console.log('Inicializando navegação mobile...');
        console.log('mobileToggle encontrado:', this.mobileToggle);
        console.log('mobileNav encontrado:', this.mobileNav);
        this.bindEvents();
        this.setupTouchGestures();
        this.setupKeyboardNavigation();
        this.setupAccessibility();
        console.log('Navegação mobile inicializada com sucesso');
    }
    
    bindEvents() {
        // Abrir menu
        this.mobileToggle.addEventListener('click', (e) => {
            e.preventDefault();
            this.openNav();
        });
        
        // Fechar menu
        this.mobileClose.addEventListener('click', (e) => {
            e.preventDefault();
            this.closeNav();
        });
        
        // Fechar ao clicar no overlay
        this.mobileOverlay.addEventListener('click', (e) => {
            if (e.target === this.mobileOverlay) {
                this.closeNav();
            }
        });
        
        // Fechar ao pressionar ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closeNav();
            }
        });
        
        // Fechar ao redimensionar (opcional - pode ser removido se quiser manter o menu aberto)
        window.addEventListener('resize', () => {
            // Comentado para permitir que o menu funcione em todas as telas
            // if (window.innerWidth > 768 && this.isOpen) {
            //     this.closeNav();
            // }
        });
        
        // Fechar ao clicar em links do menu
        const navLinks = this.mobileNav.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                // Pequeno delay para permitir a transição visual
                setTimeout(() => {
                    this.closeNav();
                }, 100);
            });
        });
    }
    
    setupTouchGestures() {
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        
        // Detectar gesto de swipe para fechar
        this.mobileNav.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
        });
        
        this.mobileNav.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            
            currentX = e.touches[0].clientX;
            const diffX = startX - currentX;
            
            // Se o usuário arrastar para a esquerda (fechar menu)
            if (diffX > 50) {
                this.closeNav();
                isDragging = false;
            }
        });
        
        this.mobileNav.addEventListener('touchend', () => {
            isDragging = false;
        });
    }
    
    setupKeyboardNavigation() {
        // Navegação por teclado dentro do menu
        const focusableElements = this.mobileNav.querySelectorAll(
            'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        this.mobileNav.addEventListener('keydown', (e) => {
            if (!this.isOpen) return;
            
            // Trap focus dentro do menu quando aberto
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });
    }
    
    setupAccessibility() {
        // Melhorar acessibilidade
        this.mobileToggle.setAttribute('aria-expanded', 'false');
        this.mobileToggle.setAttribute('aria-controls', 'mobileNav');
        
        this.mobileNav.setAttribute('aria-hidden', 'true');
        this.mobileNav.setAttribute('role', 'navigation');
        this.mobileNav.setAttribute('aria-label', 'Menu de navegação');
    }
    
    openNav() {
        console.log('Abrindo menu mobile...');
        this.isOpen = true;
        this.mobileNav.classList.add('show');
        this.mobileOverlay.classList.add('show');
        this.mobileToggle.classList.add('active');
        this.mobileToggle.setAttribute('aria-expanded', 'true');
        this.mobileNav.setAttribute('aria-hidden', 'false');
        
        // Adicionar classe no body para esconder sidebar
        document.body.classList.add('mobile-nav-open');
        console.log('Classe mobile-nav-open adicionada ao body');
        
        // Bloquear scroll do body
        document.body.style.overflow = 'hidden';
        
        // Focar no primeiro elemento do menu
        const firstFocusable = this.mobileNav.querySelector('a, button');
        if (firstFocusable) {
            setTimeout(() => {
                firstFocusable.focus();
            }, 300);
        }
        
        // Animar entrada
        this.animateIn();
    }
    
    closeNav() {
        console.log('Fechando menu mobile...');
        this.isOpen = false;
        this.mobileNav.classList.remove('show');
        this.mobileOverlay.classList.remove('show');
        this.mobileToggle.classList.remove('active');
        this.mobileToggle.setAttribute('aria-expanded', 'false');
        this.mobileNav.setAttribute('aria-hidden', 'true');
        
        // Remover classe do body para mostrar sidebar
        document.body.classList.remove('mobile-nav-open');
        console.log('Classe mobile-nav-open removida do body');
        
        // Restaurar scroll do body
        document.body.style.overflow = '';
        
        // Focar no botão de toggle
        this.mobileToggle.focus();
        
        // Animar saída
        this.animateOut();
    }
    
    animateIn() {
        // Adicionar animação de entrada
        const navItems = this.mobileNav.querySelectorAll('.mobile-nav-item');
        navItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, 100 + (index * 50));
        });
    }
    
    animateOut() {
        // Resetar animações
        const navItems = this.mobileNav.querySelectorAll('.mobile-nav-item');
        navItems.forEach(item => {
            item.style.transition = '';
            item.style.opacity = '';
            item.style.transform = '';
        });
    }
    
    // Método público para verificar se está aberto
    isNavOpen() {
        return this.isOpen;
    }
    
    // Método público para alternar
    toggle() {
        if (this.isOpen) {
            this.closeNav();
        } else {
            this.openNav();
        }
    }
}

// ===== FUNÇÕES AUXILIARES =====

// Detectar se é dispositivo mobile
function isMobileDevice() {
    // Verificar user agent
    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
    const mobileRegex = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i;
    
    // Verificar se é mobile por user agent
    const isMobileByUserAgent = mobileRegex.test(userAgent);
    
    // Verificar se é mobile por tamanho da tela
    const isMobileByScreen = window.innerWidth <= 768;
    
    // Verificar se é mobile por touch capabilities
    const isMobileByTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    return isMobileByUserAgent || isMobileByScreen || isMobileByTouch;
}

// Detectar orientação do dispositivo
function getDeviceOrientation() {
    return window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
}

// Ajustar layout baseado na orientação
function adjustLayoutForOrientation() {
    const orientation = getDeviceOrientation();
    const isMobile = isMobileDevice();
    
    if (isMobile) {
        document.body.classList.add('mobile-device');
        document.body.classList.add(`orientation-${orientation}`);
    } else {
        document.body.classList.remove('mobile-device');
        document.body.classList.remove('orientation-portrait');
        document.body.classList.remove('orientation-landscape');
    }
}

// ===== INICIALIZAÇÃO =====

let mobileNav;

// Função para inicializar a navegação mobile
function initializeMobileNav() {
    // Verificar se os elementos necessários existem
    const mobileToggleEl = document.getElementById('mobileNavToggle');
    const mobileNavEl = document.getElementById('mobileNav');
    
    if (!mobileToggleEl || !mobileNavEl) {
        console.warn('Elementos da navegação mobile não encontrados. Tentando novamente em 100ms...');
        setTimeout(initializeMobileNav, 100);
        return;
    }
    
    // Inicializar navegação mobile
    mobileNav = new MobileNavigation();
    window.mobileNav = mobileNav;
    
    // Ajustar layout inicial
    adjustLayoutForOrientation();
    
    // Monitorar mudanças de orientação
    window.addEventListener('orientationchange', () => {
        setTimeout(adjustLayoutForOrientation, 100);
    });
    
    // Monitorar redimensionamento
    window.addEventListener('resize', () => {
        adjustLayoutForOrientation();
    });
    
    // Adicionar indicador de carregamento
    document.body.classList.add('mobile-nav-ready');
    
    console.log('Navegação mobile inicializada com sucesso');
}

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMobileNav);
} else {
    // DOM já está pronto
    initializeMobileNav();
}

// ===== FUNÇÕES GLOBAIS =====

// Função global para abrir menu (pode ser chamada de outros scripts)
window.openMobileNav = function() {
    if (mobileNav) {
        mobileNav.openNav();
    }
};

// Função global para fechar menu
window.closeMobileNav = function() {
    if (mobileNav) {
        mobileNav.closeNav();
    }
};

// Função global para alternar menu
window.toggleMobileNav = function() {
    if (mobileNav) {
        mobileNav.toggle();
    }
};

// ===== MELHORIAS DE PERFORMANCE =====

// Debounce para eventos de resize
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

// Aplicar debounce ao redimensionamento
const debouncedResize = debounce(() => {
    adjustLayoutForOrientation();
}, 250);

window.addEventListener('resize', debouncedResize);

// ===== DETECÇÃO DE GESTOS AVANÇADA =====

class GestureDetector {
    constructor() {
        this.startX = 0;
        this.startY = 0;
        this.currentX = 0;
        this.currentY = 0;
        this.isTracking = false;
        
        this.init();
    }
    
    init() {
        document.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
        document.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        document.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: true });
    }
    
    handleTouchStart(e) {
        if (e.touches.length === 1) {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
            this.isTracking = true;
        }
    }
    
    handleTouchMove(e) {
        if (!this.isTracking) return;
        
        this.currentX = e.touches[0].clientX;
        this.currentY = e.touches[0].clientY;
        
        const diffX = this.startX - this.currentX;
        const diffY = this.startY - this.currentY;
        
        // Detectar swipe horizontal
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
            if (diffX > 0 && mobileNav && mobileNav.isNavOpen()) {
                // Swipe para esquerda - fechar menu
                e.preventDefault();
                mobileNav.closeNav();
                this.isTracking = false;
            }
        }
    }
    
    handleTouchEnd() {
        this.isTracking = false;
    }
}

// Inicializar detector de gestos
document.addEventListener('DOMContentLoaded', function() {
    new GestureDetector();
}); 