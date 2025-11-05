<div class="calendario-container">
    <div class="calendario-header">
        <h3><i class="fas fa-calendar-alt"></i> Calendário de Ligações</h3>
        <p>Visualize ligações com seus clientes</p>
    </div>
    
    <div class="calendario-controls">
        <div class="calendario-filtros">
            <select id="filtroStatusCalendario" onchange="filtrarAgendamentos()" aria-label="Filtrar por status de agendamento">
                <option value="">Todos os Status</option>
                <option value="agendado">Agendado</option>
                <option value="realizado">Realizado</option>
                <option value="cancelado">Cancelado</option>
            </select>
        </div>
    </div>
    
    <!-- Calendário Visual -->
    <div class="calendario-visual-container">
        <div class="calendario-header-controls">
            <button class="btn btn-sm btn-outline-secondary" onclick="mesAnterior()" aria-label="Mês anterior">
                <i class="fas fa-chevron-left"></i>
            </button>
            <h4 id="mesAtual"><?php echo date('F Y'); ?></h4>
            <button class="btn btn-sm btn-outline-secondary" onclick="mesProximo()" aria-label="Próximo mês">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="calendario-grid" id="calendarioGrid" role="grid" aria-label="Calendário mensal">
            <!-- O calendário será carregado aqui via JavaScript -->
        </div>
    </div>
    
    <div class="agendamentos-lista" id="agendamentosLista" aria-live="polite" aria-busy="false">
        <!-- Lista de agendamentos será carregada aqui -->
    </div>
</div>

