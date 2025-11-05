/**
 * ============================================
 * POPUP DE LEMBRETE DO BOBINITO
 * ============================================
 * Controla a exibição do popup de lembrete
 * sobre pressionar CTRL+F5 quando algo não funciona
 */

(function() {
    'use strict';

    // Configurações
    const CONFIG = {
        storageKey: 'bobinito_reminder_dismissed',
        showDelay: 1000, // Atraso de 1 segundo antes de mostrar
        autoCloseDelay: null // null = não fecha automaticamente, ou número em ms
    };

    // Elementos do DOM
    let popup = null;
    let closeBtn = null;
    let okBtn = null;
    let dontShowCheckbox = null;

    /**
     * Inicializa o popup quando o DOM estiver carregado
     */
    function init() {
        // Aguardar o DOM carregar completamente
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupPopup);
        } else {
            setupPopup();
        }
    }

    /**
     * Configura o popup e seus event listeners
     */
    function setupPopup() {
        // Obter elementos
        popup = document.getElementById('bobinito-reminder-popup');
        closeBtn = document.getElementById('close-bobinito-popup');
        okBtn = document.getElementById('bobinito-btn-ok');
        dontShowCheckbox = document.getElementById('dont-show-again');

        // Verificar se os elementos existem
        if (!popup || !closeBtn || !okBtn || !dontShowCheckbox) {
            console.warn('Bobinito Reminder: Elementos do popup não encontrados');
            return;
        }

        // Adicionar event listeners
        closeBtn.addEventListener('click', handleClose);
        okBtn.addEventListener('click', handleOk);
        
        // Fechar ao clicar fora do popup
        popup.addEventListener('click', function(e) {
            if (e.target === popup) {
                handleClose();
            }
        });

        // Fechar com tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && popup.classList.contains('show')) {
                handleClose();
            }
        });

        // Verificar se deve mostrar o popup
        checkAndShowPopup();
    }

    /**
     * Verifica se deve mostrar o popup e exibe se necessário
     */
    function checkAndShowPopup() {
        // Verificar se o usuário já dispensou permanentemente
        const dismissed = localStorage.getItem(CONFIG.storageKey);
        
        if (dismissed === 'true') {
            return; // Não mostrar
        }

        // SEMPRE mostrar o popup quando carregar a página (obrigatório a cada login)
        // Não verifica mais o sessionStorage para permitir exibição toda vez
        setTimeout(showPopup, CONFIG.showDelay);
    }

    /**
     * Exibe o popup com animação
     */
    function showPopup() {
        if (!popup) return;

        popup.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevenir scroll do body

        // Auto-fechar se configurado
        if (CONFIG.autoCloseDelay) {
            setTimeout(handleClose, CONFIG.autoCloseDelay);
        }

        // Animação suave já está no CSS
        const character = popup.querySelector('.bobinito-character');
        if (character) {
            character.style.animation = 'floatGentle 4s ease-in-out infinite';
        }
    }

    /**
     * Fecha o popup com animação
     */
    function closePopup() {
        if (!popup) return;

        popup.classList.remove('show');
        document.body.style.overflow = ''; // Restaurar scroll do body
    }

    /**
     * Handler para o botão de fechar
     */
    function handleClose() {
        // Verificar se marcou "não mostrar novamente"
        if (dontShowCheckbox && dontShowCheckbox.checked) {
            localStorage.setItem(CONFIG.storageKey, 'true');
        }

        closePopup();
    }

    /**
     * Handler para o botão OK
     */
    function handleOk() {
        // Verificar se marcou "não mostrar novamente"
        if (dontShowCheckbox && dontShowCheckbox.checked) {
            localStorage.setItem(CONFIG.storageKey, 'true');
        }

        closePopup();
    }

    /**
     * Força a exibição do popup (útil para debug ou chamadas manuais)
     */
    function forceShow() {
        showPopup();
    }

    /**
     * Reseta as configurações do popup (útil para testes)
     */
    function resetSettings() {
        localStorage.removeItem(CONFIG.storageKey);
        console.log('Bobinito Reminder: Configurações resetadas - o popup voltará a aparecer toda vez que logar');
    }

    // Expor funções globalmente para uso em console/debug
    window.BobinitoReminder = {
        show: forceShow,
        close: closePopup,
        reset: resetSettings
    };

    // Inicializar
    init();

})();

/**
 * ============================================
 * INSTRUÇÕES DE USO NO CONSOLE
 * ============================================
 * 
 * Para testar o popup no console:
 * - BobinitoReminder.show()    -> Mostra o popup
 * - BobinitoReminder.close()   -> Fecha o popup
 * - BobinitoReminder.reset()   -> Reseta as configurações
 * 
 */

