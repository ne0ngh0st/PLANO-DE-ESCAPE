// Aguardar DOM estar completamente carregado
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔔 DOM carregado, iniciando script de notificações...');
    
    // Aguardar um pouco mais para garantir que todos os elementos estejam prontos
    setTimeout(() => {
        const bell = document.getElementById('notifBell');
        console.log('🔔 Sino encontrado:', bell);
        if (!bell) {
            console.log('❌ Sino não encontrado, saindo...');
            return; // Only on pages with bell
        }

    const dropdown = document.getElementById('notifDropdown');
    const listContainer = document.getElementById('notifList');
    const badge = document.getElementById('notifBadge');
    const markAllBtn = document.getElementById('notifMarkAll');
    const createTestBtn = document.getElementById('notifCreateTest');
    
    console.log('🔔 Elementos encontrados:', {
        dropdown: !!dropdown,
        listContainer: !!listContainer,
        badge: !!badge,
        markAllBtn: !!markAllBtn,
        createTestBtn: !!createTestBtn
    });

    // Endpoint provided by PHP in navbar
    const ENDPOINT = window.NOTIF_ENDPOINT || 'includes/gerenciar_notificacoes.php';
    console.log('🔔 Endpoint:', ENDPOINT);

    let lastItems = [];
    let pollingIntervalId = null;

    function formatDateTime(dtString) {
        try {
            const dt = new Date(dtString.replace(' ', 'T'));
            return dt.toLocaleString('pt-BR', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
        } catch {
            return dtString;
        }
    }

    function buildItemHtml(item) {
        let icon = 'fa-comment'; // ícone padrão
        
        // Definir ícone baseado no tipo
        switch (item.type) {
            case 'agendamento':
                icon = 'fa-calendar-check';
                break;
            case 'resposta':
                icon = 'fa-reply';
                break;
            case 'status':
                icon = 'fa-exchange-alt';
                break;
            case 'lead':
                icon = 'fa-user-plus';
                break;
            case 'opportunity':
                icon = 'fa-fire';
                break;
            case 'reminder':
                icon = 'fa-bell';
                break;
            case 'target':
                icon = 'fa-bullseye';
                break;
            case 'custom':
                icon = 'fa-cog';
                break;
            default:
                icon = 'fa-comment';
        }
        
        const title = item.title || 'Notificação';
        const when = formatDateTime(item.time);
        const url = item.url || '#';
        return `
            <a href="${url}" class="notif-item" data-key="${item.key}">
                <div class="notif-icon"><i class="fas ${icon}"></i></div>
                <div class="notif-content">
                    <div class="notif-title">${title}</div>
                    <div class="notif-message">${item.message || ''}</div>
                    <div class="notif-time">${when}</div>
                </div>
            </a>
        `;
    }

    async function fetchNotifications() {
        try {
            const res = await fetch(`${ENDPOINT}?acao=listar`, { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Erro ao buscar notificações');
            lastItems = Array.isArray(data.items) ? data.items : [];

            // Update badge
            const count = data.count || lastItems.length;
            if (badge) {
                badge.textContent = count > 9 ? '9+' : String(count);
                badge.style.display = count > 0 ? 'inline-flex' : 'none';
            }

            // Render list
            if (listContainer) {
                if (lastItems.length === 0) {
                    listContainer.innerHTML = '<div class="notif-empty">Sem notificações de hoje</div>';
                } else {
                    listContainer.innerHTML = lastItems.map(buildItemHtml).join('');
                }
            }
        } catch (e) {
            // Soft-fail: do not spam console in production
            // console.error(e);
        }
    }

    async function markAllRead() {
        if (!lastItems.length) return;
        const form = new FormData();
        form.append('acao', 'marcar_todas');
        lastItems.forEach(it => form.append('keys[]', it.key));
        try {
            await fetch(ENDPOINT, { method: 'POST', body: form, credentials: 'same-origin' });
            await fetchNotifications();
        } catch {}
    }

    async function createTest() {
        const form = new FormData();
        form.append('acao', 'criar_teste');
        form.append('titulo', 'Teste rápido');
        form.append('mensagem', 'Notificação criada pelo botão de teste');
        try {
            await fetch(ENDPOINT, { method: 'POST', body: form, credentials: 'same-origin' });
            await fetchNotifications();
            // Open dropdown so admin sees it
            dropdown?.classList.remove('hidden');
            dropdown?.classList.add('show');
        } catch {}
    }

    // Toggle dropdown
    console.log('🔔 Adicionando event listener ao sino...');
    bell.addEventListener('click', (e) => {
        console.log('🔔 SINO CLICADO!');
        e.preventDefault();
        if (dropdown) {
            console.log('🔔 Alternando dropdown...');
            dropdown.classList.toggle('hidden');
            dropdown.classList.toggle('show');
            console.log('🔔 Classes do dropdown:', dropdown.className);
        } else {
            console.log('❌ Dropdown não encontrado!');
        }
    });

    // Outside click closes
    document.addEventListener('click', (e) => {
        if (!dropdown || dropdown.classList.contains('hidden')) return;
        const target = e.target;
        if (!(target instanceof Element)) return;
        if (!dropdown.contains(target) && !bell.contains(target)) {
            dropdown.classList.add('hidden');
            dropdown.classList.remove('show');
        }
    });

    // Delegate item click to mark read before navigating
    listContainer?.addEventListener('click', async (e) => {
        const link = e.target.closest('.notif-item');
        if (!link) return;
        const key = link.getAttribute('data-key');
        const href = link.getAttribute('href') || '#';
        e.preventDefault();
        if (key) {
            const form = new FormData();
            form.append('acao', 'marcar_lida');
            form.append('evento_chave', key);
            try { await fetch(ENDPOINT, { method: 'POST', body: form, credentials: 'same-origin' }); } catch {}
        }
        window.location.href = href;
    });

    markAllBtn?.addEventListener('click', (e) => { e.preventDefault(); markAllRead(); });
    createTestBtn?.addEventListener('click', (e) => { e.preventDefault(); createTest(); });

        // Initial load and polling
        fetchNotifications();
        pollingIntervalId = window.setInterval(fetchNotifications, 30000);
        
    }, 500); // Aguardar 500ms após DOM carregado
});



