<div class="calendario-container">
    <div class="calendario-header">
        <h3><i class="fas fa-calendar-alt"></i> Calendário de Agendamentos</h3>
        <p>Visualize agendamentos de clientes e leads</p>
    </div>
    
    <div class="calendario-controls">
        <div class="calendario-filtros">
            <select id="filtroTipoAgendamento" onchange="filtrarAgendamentosPorTipo()" aria-label="Filtrar por tipo">
                <option value="">Todos os Tipos</option>
                <option value="cliente">Apenas Clientes</option>
                <option value="lead">Apenas Leads</option>
            </select>
            <select id="filtroStatusCalendario" onchange="filtrarAgendamentos()" aria-label="Filtrar por status de agendamento">
                <option value="">Todos os Status</option>
                <option value="agendado">Agendado</option>
                <option value="realizado">Realizado</option>
                <option value="cancelado">Cancelado</option>
            </select>
        </div>
        
        <div class="calendario-legenda">
            <span class="legenda-item">
                <span class="legenda-cor cliente-cor"></span>
                Clientes
            </span>
            <span class="legenda-item">
                <span class="legenda-cor lead-cor"></span>
                Leads
            </span>
            <!-- Botão de debug temporário -->
            <button class="btn btn-sm btn-outline-warning" onclick="debugAgendamentos()" title="Debug - Verificar dados">
                <i class="fas fa-bug"></i> Debug
            </button>
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



<style>
/* ===== ESTILOS COMPLETOS PARA CALENDÁRIO UNIFICADO ===== */

/* Container principal */
.calendario-container {
    padding: 24px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
}

.calendario-header {
    text-align: center;
    margin-bottom: 2rem;
}

.calendario-header h3 {
    color: #007bff;
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.calendario-header p {
    color: #6c757d;
    font-size: 1rem;
    margin: 0;
}

/* Estilos adicionais para o calendário unificado */
.calendario-botoes {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.calendario-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.calendario-filtros {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.calendario-filtros select {
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: white;
    color: #495057;
    min-width: 150px;
}

.calendario-legenda {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.legenda-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #495057;
}

.legenda-cor {
    width: 16px;
    height: 16px;
    border-radius: 50%;
}

.cliente-cor {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.lead-cor {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

/* Estilo para leads incompletas */
.agendamento-lead.lead-incompleto {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    border: 2px dashed #ff9800;
    opacity: 0.9;
}

.agendamento-lead.lead-incompleto:hover {
    opacity: 1;
    transform: scale(1.05);
}

/* Estilos para agendamentos no calendário */
.agendamento-cliente {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-bottom: 2px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.agendamento-lead {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-bottom: 2px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.agendamento-cliente:hover,
.agendamento-lead:hover {
    transform: scale(1.05);
}

/* Calendário visual */
.calendario-visual-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.calendario-header-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.calendario-header-controls h4 {
    margin: 0;
    color: #495057;
    font-size: 1.3rem;
    font-weight: 600;
}

.calendario-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.calendario-header-dia {
    background: #e9ecef;
    padding: 0.5rem;
    text-align: center;
    font-weight: bold;
    font-size: 0.8rem;
    color: #495057;
}

.calendario-dia {
    background: white;
    padding: 0.75rem;
    min-height: 100px;
    position: relative;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.calendario-dia:hover {
    background: #f8f9fa;
}

.calendario-dia.outro-mes {
    background: #f8f9fa;
    color: #adb5bd;
}

.calendario-dia.hoje {
    background: #e3f2fd;
    font-weight: bold;
}

.calendario-dia-numero {
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

/* Lista de agendamentos */
.agendamentos-lista {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    max-height: 500px;
    overflow-y: auto;
}

.agendamento-item {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: box-shadow 0.2s ease;
    border-left: 4px solid #007bff;
}

.agendamento-item:hover {
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
}

.agendamento-info h4 {
    margin: 0 0 0.5rem 0;
    color: #007bff;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.agendamento-info p {
    margin: 0.25rem 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.agendamento-acoes {
    display: flex;
    gap: 0.5rem;
}

.agendamento-acoes .btn {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
}

.status-agendado {
    border-left-color: #007bff;
}

.status-realizado {
    border-left-color: #28a745;
}

.status-cancelado {
    border-left-color: #dc3545;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 0.75em;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.bg-success {
    background-color: #28a745 !important;
    color: white !important;
}

.bg-primary {
    background-color: #007bff !important;
    color: white !important;
}

.badge-agendado {
    background-color: #17a2b8;
    color: white;
}

.badge-realizado {
    background-color: #28a745;
    color: white;
}

.badge-cancelado {
    background-color: #dc3545;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.bg-warning {
    background-color: #ffc107 !important;
    color: #212529 !important;
}

/* Botões do calendário */
.btn-calendario-action {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.btn-success:hover,
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Modal styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-compact {
    max-width: 600px;
}

.modal-header {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.modal-close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.modal-body {
    padding: 2rem;
}

.modal-buttons {
    padding: 1.5rem 2rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    border-top: 1px solid #e9ecef;
}

/* Form styles */
.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #495057;
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.input-group {
    display: flex;
    align-items: stretch;
}

.input-group input {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    flex: 1;
}

.input-group .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-left: none;
}

.btn-outline-secondary {
    background: white;
    border: 1px solid #dee2e6;
    color: #6c757d;
    padding: 0.75rem 1rem;
}

.btn-outline-secondary:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

.resultado-busca,
.cliente-selecionado {
    margin-top: 0.5rem;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 0.9rem;
}

.cliente-selecionado {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.clientes-encontrados .cliente-resultado {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.clientes-encontrados .cliente-resultado:hover {
    background: #f8f9fa;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

/* Responsividade */
@media (max-width: 768px) {
    .calendario-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .calendario-botoes {
        justify-content: center;
    }
    
    .calendario-filtros {
        justify-content: center;
    }
    
    .calendario-legenda {
        justify-content: center;
    }
    
    .calendario-filtros select {
        min-width: 120px;
    }
}
</style>
