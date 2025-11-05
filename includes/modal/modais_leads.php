<?php
// Carregar configurações dos modais
require_once __DIR__ . '/../utils/carregar_configuracoes_modais.php';
$configuracoes = carregarConfiguracoesModais($pdo);
?>

<!-- Modal de Edição de Lead -->
<div id="modalEdicaoLead" class="modal-overlay">
    <div class="modal-content modal-edicao">
        <div class="modal-edicao-header">
            <h3><i class="fas fa-edit"></i> Editar Lead</h3>
            <button class="modal-close-btn" onclick="fecharModalEdicaoLead()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formEdicaoLead" class="form-edicao-lead">
            <div class="form-group">
                <label for="editNomeLead">Nome/Razão Social:</label>
                <input type="text" id="editNomeLead" name="nome" required>
            </div>
            
            <div class="form-group">
                <label for="editEmailLead">Email:</label>
                <input type="email" id="editEmailLead" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="editTelefoneLead">Telefone:</label>
                <input type="text" id="editTelefoneLead" name="telefone" required>
            </div>
            
            <div class="form-group">
                <label for="editEnderecoLead">Endereço:</label>
                <textarea id="editEnderecoLead" name="endereco" rows="3"></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="btn-cancelar" onclick="fecharModalEdicaoLead()">Cancelar</button>
                <button type="submit" class="btn-confirmar">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div id="modalConfirmacaoLead" class="modal-overlay">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Exclusão</h3>
        <p><?php echo htmlspecialchars($configuracoes['modal']['texto_confirmacao']); ?> <strong id="nomeLead"></strong> da sua carteira?</p>
        <p>O lead a partir de agora será trabalhado internamente pela Autopel.</p>
        
        <!-- Campo obrigatório para motivo da exclusão -->
        <div class="motivo-exclusao-container">
            <label for="motivoExclusaoLead" class="motivo-label">
                <span class="required">*</span> Motivo da Exclusão:
            </label>
            <select id="motivoExclusaoLead" class="motivo-select" required>
                <option value="">Selecione um motivo</option>
                <?php foreach ($configuracoes['exclusao'] as $motivo): ?>
                    <?php $valor = strtolower(str_replace([' ', '/'], ['_', '_'], $motivo)); ?>
                    <option value="<?php echo $valor; ?>"><?php echo htmlspecialchars($motivo); ?></option>
                <?php endforeach; ?>
            </select>
            <div id="motivoErrorLead" class="motivo-error" style="display: none;">
                <i class="fas fa-exclamation-circle"></i>
                <span>Por favor, selecione um motivo para a exclusão.</span>
            </div>
        </div>
        
        <!-- Campo opcional para observações discursativas -->
        <div class="observacao-exclusao-container">
            <label for="observacaoExclusaoLead" class="observacao-label">
                <span class="required">*</span> Observações Adicionais:
            </label>
            <textarea id="observacaoExclusaoLead" class="observacao-textarea" 
                      placeholder="Descreva detalhes adicionais sobre a exclusão, contexto, ou informações relevantes... (mínimo 30 caracteres)"
                      rows="3" maxlength="1000" minlength="30" required aria-required="true"></textarea>
            
            <!-- Contador de caracteres e barra de progresso -->
            <div class="observacao-counter">
                <span class="char-count" id="charCountLead">0 / 1000</span>
                <span class="char-status" id="charStatusLead">Mínimo: 30 caracteres</span>
            </div>
            <div class="observacao-progress">
                <div class="observacao-progress-bar" id="progressBarLead"></div>
            </div>
        </div>
        
        <div class="modal-buttons">
            <button class="btn-cancelar" onclick="fecharModalLead()">Cancelar</button>
            <button class="btn-confirmar" onclick="excluirLead()">Sim, Remover da Carteira</button>
        </div>
    </div>
</div>

<!-- Modal de Observações -->
<div id="modalObservacoes" class="modal-overlay">
    <div class="modal-observacoes">
        <div class="modal-observacoes-header">
            <div class="modal-observacoes-title">
                <i class="fas fa-comment-dots"></i>
                <h3>Observações - <span id="tituloObservacao"></span></h3>
            </div>
            <button class="modal-close-btn" onclick="fecharModalObservacoes()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-observacoes-body">
            <div id="listaObservacoes" class="observacoes-lista">
                <!-- Observações serão carregadas aqui -->
            </div>
            
            <div class="observacoes-nova">
                <div class="observacoes-input-container">
                    <textarea id="novaObservacao" placeholder="<?php echo htmlspecialchars($configuracoes['modal']['texto_observacoes']); ?>" class="observacoes-textarea"></textarea>
                    <div class="observacoes-acoes">
                        <button class="btn btn-success btn-sm" onclick="adicionarObservacao()">
                            <i class="fas fa-plus"></i> Adicionar Observação
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="fecharModalObservacoes()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Agendamento para Leads -->
<div id="modalAgendamentoLead" class="modal-overlay">
    <div class="modal-content modal-agendamento">
        <div class="modal-agendamento-header">
            <h3><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($configuracoes['modal']['texto_agendamento']); ?> - Lead</h3>
            <button class="modal-close-btn" onclick="fecharModalAgendamentoLead()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formAgendamentoLead" class="form-agendamento-lead">
            <div class="lead-info">
                <div class="lead-item">
                    <strong>Lead:</strong> <span id="agendamentoLeadNome"></span>
                </div>
                <div class="lead-item">
                    <strong>Email:</strong> <span id="agendamentoLeadEmail"></span>
                </div>
                <div class="lead-item">
                    <strong>Telefone:</strong> <span id="agendamentoLeadTelefone"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="agendamentoData">Data do Agendamento:</label>
                <input type="date" id="agendamentoData" name="data" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="agendamentoHora">Horário:</label>
                <input type="time" id="agendamentoHora" name="hora" required>
            </div>
            
            <div class="form-group">
                <label for="agendamentoObservacao">Observações (opcional):</label>
                <textarea id="agendamentoObservacao" name="observacao" rows="3" placeholder="Digite observações sobre o agendamento..."></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="btn-cancelar" onclick="fecharModalAgendamentoLead()">Cancelar</button>
                <button type="submit" class="btn-confirmar">Agendar Ligação</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Seleção de Tipo de Contato -->
<div id="modalTipoContato" class="modal-overlay">
    <div class="modal-content modal-tipo-contato">
        <div class="modal-tipo-contato-header">
            <h3><i class="fas fa-phone"></i> Escolher Tipo de Contato</h3>
            <button class="modal-close-btn" onclick="fecharModalTipoContato()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="lead-info-contato">
            <div class="lead-item">
                <strong>Lead:</strong> <span id="contatoLeadNome"></span>
            </div>
            <div class="lead-item">
                <strong>Telefone:</strong> <span id="contatoLeadTelefone"></span>
            </div>
        </div>
        
        <div class="tipos-contato-container">
            <div class="tipo-contato-option" onclick="confirmarTipoContato('telefonica')">
                <div class="tipo-contato-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="tipo-contato-info">
                    <h4>Ligação Telefônica</h4>
                    <p>Fazer uma ligação direta para o lead</p>
                </div>
            </div>
            
            <div class="tipo-contato-option" onclick="confirmarTipoContato('whatsapp')">
                <div class="tipo-contato-icon whatsapp">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div class="tipo-contato-info">
                    <h4>WhatsApp</h4>
                    <p>Enviar mensagem via WhatsApp</p>
                </div>
            </div>
            
            <div class="tipo-contato-option" onclick="confirmarTipoContato('presencial')">
                <div class="tipo-contato-icon presencial">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="tipo-contato-info">
                    <h4>Visita Presencial</h4>
                    <p>Agendar uma visita presencial</p>
                </div>
            </div>
            
            <div class="tipo-contato-option" onclick="confirmarTipoContato('email')">
                <div class="tipo-contato-icon email">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="tipo-contato-info">
                    <h4>E-mail</h4>
                    <p>Enviar e-mail para o lead</p>
                </div>
            </div>
        </div>
        
        <div class="modal-buttons">
            <button class="btn-cancelar" onclick="fecharModalTipoContato()">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal de Roteiro de Ligação -->
<div id="modalRoteiroLigacao" class="modal-roteiro">
    <div class="modal-roteiro-content">
        <div class="modal-roteiro-header">
            <div class="modal-roteiro-title">
                <i class="fas fa-phone"></i>
                <h3><?php echo htmlspecialchars($configuracoes['modal']['titulo_ligacao']); ?></h3>
            </div>
            <button class="modal-roteiro-close" onclick="fecharModalRoteiro()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-roteiro-body">
            <div class="roteiro-info">
                <div class="roteiro-cliente">
                    <strong>Lead:</strong> <span id="clienteInfo"></span>
                </div>
                <div class="roteiro-telefone">
                    <strong>Telefone:</strong> <span id="telefoneInfo"></span>
                </div>
                <div class="roteiro-tempo">
                    <i class="fas fa-clock"></i>
                    <span>Tempo: <span id="tempoLigacao">00:00</span></span>
                </div>
                <div class="roteiro-progresso">
                    <div class="roteiro-progresso-fill" id="progressoRoteiro" style="width: 0%"></div>
                </div>
            </div>
            
            <div id="perguntasContainer">
                <!-- As perguntas serão inseridas aqui dinamicamente -->
            </div>
            
            <div class="roteiro-acoes">
                <button class="btn-roteiro btn-roteiro-cancelar" onclick="cancelarLigacao('Cancelamento manual pelo usuário')" id="btnCancelarLigacao">
                    <i class="fas fa-times"></i> Cancelar Ligação
                </button>
                <button class="btn-roteiro btn-roteiro-finalizar" onclick="finalizarLigacao()" id="btnFinalizarLigacao" disabled>
                    <i class="fas fa-check"></i> Finalizar Ligação
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para modais */
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
    padding: 30px;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal-content h3 {
    color: #dc3545;
    margin-bottom: 15px;
    font-size: 1.5rem;
}

.modal-content p {
    margin-bottom: 25px;
    color: #666;
    line-height: 1.5;
}

.modal-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.btn-cancelar, .btn-confirmar {
    padding: 12px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
}

.btn-cancelar {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    color: white;
    box-shadow: 0 3px 12px rgba(108, 117, 125, 0.3);
}

.btn-confirmar {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    box-shadow: 0 3px 12px rgba(220, 53, 69, 0.3);
}

.btn-cancelar:hover, .btn-confirmar:hover {
    transform: translateY(-2px);
}

/* Estilos para campo de motivo da exclusão */
.motivo-exclusao-container {
    margin: 20px 0;
    text-align: left;
}

.motivo-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.required {
    color: #dc3545;
    font-weight: bold;
}

.motivo-select {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    transition: border-color 0.3s ease;
}

.motivo-select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.motivo-select.error {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
}

.motivo-error {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding: 8px 12px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
    color: #721c24;
    font-size: 13px;
}

.motivo-error i {
    color: #dc3545;
}

/* Modal de Observações */
.modal-observacoes {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 700px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-observacoes-header {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-observacoes-title {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.modal-observacoes-title i {
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.modal-observacoes-title h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: white;
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
    backdrop-filter: blur(10px);
}

.modal-close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.modal-observacoes-body {
    padding: 0;
    max-height: calc(80vh - 100px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.observacoes-lista {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem 2rem;
    max-height: 400px;
}

.observacoes-nova {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid #dee2e6;
    padding: 1.5rem 2rem;
}

.observacoes-input-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.observacoes-textarea {
    width: 100%;
    min-height: 100px;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-family: inherit;
    font-size: 0.95rem;
    line-height: 1.5;
    resize: vertical;
    transition: all 0.3s ease;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.observacoes-textarea:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
    transform: translateY(-1px);
}

.observacoes-acoes {
    display: flex;
    gap: 0.8rem;
    justify-content: flex-end;
    align-items: center;
}

/* Modal de Roteiro */
.modal-roteiro {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
}

.modal-roteiro-content {
    background: white;
    border-radius: 16px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

.modal-roteiro-header {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-roteiro-title {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.modal-roteiro-title i {
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.modal-roteiro-title h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: white;
}

.modal-roteiro-close {
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
    backdrop-filter: blur(10px);
}

.modal-roteiro-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.modal-roteiro-body {
    padding: 2rem;
    max-height: calc(90vh - 120px);
    overflow-y: auto;
}

.roteiro-info {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid #17a2b8;
}

.roteiro-cliente {
    font-weight: 600;
    color: #495057;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.roteiro-telefone {
    color: #6c757d;
    font-size: 0.95rem;
}

.roteiro-tempo {
    background: #e9ecef;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.roteiro-progresso {
    background: #e9ecef;
    border-radius: 10px;
    height: 8px;
    margin: 1rem 0;
    overflow: hidden;
}

.roteiro-progresso-fill {
    height: 100%;
    background: linear-gradient(90deg, #17a2b8, #138496);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.roteiro-acoes {
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.btn-roteiro {
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-roteiro-cancelar {
    background: #6c757d;
    color: white;
}

.btn-roteiro-finalizar {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.btn-roteiro:hover {
    transform: translateY(-1px);
}

.btn-roteiro:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Responsividade */
@media (max-width: 768px) {
    .modal-content {
        padding: 20px;
        max-width: 95%;
    }
    
    .modal-observacoes {
        width: 95%;
        max-height: 90vh;
    }
    
    .modal-observacoes-header {
        padding: 1rem 1.5rem;
    }
    
    .modal-observacoes-title h3 {
        font-size: 1.1rem;
    }
    
    .observacoes-lista {
        padding: 1rem 1.5rem;
    }
    
    .observacoes-nova {
        padding: 1rem 1.5rem;
    }
    
    .observacoes-acoes {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .observacoes-acoes .btn {
        width: 100%;
        justify-content: center;
    }
    
    .modal-roteiro-content {
        width: 95%;
        max-height: 95vh;
    }
    
    .modal-roteiro-header {
        padding: 1rem 1.5rem;
    }
    
    .modal-roteiro-title h3 {
        font-size: 1.1rem;
    }
    
    .modal-roteiro-body {
        padding: 1.5rem;
    }
    
    .roteiro-acoes {
        flex-direction: column;
        gap: 0.8rem;
    }
    
    .btn-roteiro {
        width: 100%;
        justify-content: center;
    }
}

/* Estilos para perguntas do roteiro */
.pergunta-item {
    margin-bottom: 2rem;
    padding: 2rem;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.pergunta-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.pergunta-header {
    display: flex;
    align-items: flex-start;
    margin-bottom: 2rem;
    gap: 1.5rem;
}

.pergunta-texto {
    margin: 0;
    font-size: 1.3rem;
    color: #2d3748;
    flex: 1;
    font-weight: 600;
    line-height: 1.6;
    text-align: left;
    word-wrap: break-word;
    hyphens: auto;
    letter-spacing: 0.01em;
}

.pergunta-opcoes {
    margin-left: 0;
    padding-left: 0;
    margin-top: 1rem;
}

.opcao-item {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    cursor: pointer;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    background: #f8f9fa;
}

.opcao-item:hover {
    background: #e9ecef;
    border-color: #17a2b8;
    transform: translateX(5px);
}

.opcao-item.selecionada {
    background: #e3f2fd;
    border-color: #17a2b8;
    box-shadow: 0 2px 8px rgba(23, 162, 184, 0.2);
}

.opcao-item input[type="radio"] {
    margin-right: 1rem;
    width: 20px;
    height: 20px;
    accent-color: #17a2b8;
}

.opcao-item label {
    font-size: 1.1rem;
    color: #4a5568;
    transition: all 0.3s ease;
    font-weight: 500;
    line-height: 1.5;
    cursor: pointer;
    margin: 0;
}

.opcao-item.selecionada label {
    color: #17a2b8;
    font-weight: 600;
}

.pergunta-texto-livre,
.pergunta-select {
    width: 100%;
    padding: 1.25rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1.1rem;
    margin-top: 1rem;
    transition: all 0.3s ease;
    background: #ffffff;
    color: #2d3748;
    font-weight: 500;
}

.pergunta-texto-livre:focus,
.pergunta-select:focus {
    outline: none;
    border-color: #17a2b8;
    box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.1);
    transform: translateY(-1px);
}

.campos-adicionais {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #17a2b8;
}

.campos-adicionais-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.campos-adicionais-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: #17a2b8;
    font-weight: 600;
}

.campos-adicionais-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.campo-adicional-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.campo-adicional-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.campo-adicional-input {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9rem;
}

.campo-adicional-input:focus {
    outline: none;
    border-color: #17a2b8;
    box-shadow: 0 0 0 2px rgba(23, 162, 184, 0.1);
}

.campos-adicionais-footer {
    margin-top: 1rem;
    text-align: right;
}

.btn-continuar {
    background: #17a2b8;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-continuar:hover {
    background: #138496;
    transform: translateY(-1px);
}

.progresso-texto {
    text-align: center;
    margin-bottom: 1rem;
    font-weight: 600;
    color: #495057;
}

.progresso-roteiro {
    background: #e9ecef;
    border-radius: 10px;
    height: 8px;
    margin: 1rem 0;
    overflow: hidden;
}

.progresso-barra {
    height: 100%;
    background: linear-gradient(90deg, #17a2b8, #138496);
    border-radius: 10px;
    transition: width 0.3s ease;
}

/* Responsividade para perguntas */
@media (max-width: 768px) {
    .pergunta-item {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .pergunta-texto {
        font-size: 1.1rem;
        line-height: 1.5;
    }
    
    .opcao-item {
        padding: 0.75rem 1rem;
    }
    
    .opcao-item label {
        font-size: 1rem;
    }
    
    .pergunta-texto-livre,
    .pergunta-select {
        padding: 1rem;
        font-size: 1rem;
    }
}

/* Estilos para modal de edição */
.modal-edicao {
    max-width: 500px;
    text-align: left;
}

.modal-edicao-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.modal-edicao-header h3 {
    margin: 0;
    color: #007bff;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-edicao-header h3 i {
    color: #007bff;
}

.form-edicao-lead {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    background: white;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

/* Responsividade para modal de edição */
@media (max-width: 768px) {
    .modal-edicao {
        max-width: 95%;
        margin: 10px;
    }
    
    .modal-edicao-header h3 {
        font-size: 1.2rem;
    }
    
    .form-group input,
    .form-group textarea {
        padding: 10px;
        font-size: 16px; /* Evita zoom no iOS */
    }
}

/* Estilos para modal de agendamento */
.modal-agendamento {
    max-width: 500px;
    text-align: left;
}

.modal-agendamento-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.modal-agendamento-header h3 {
    margin: 0;
    color: #28a745;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-agendamento-header h3 i {
    color: #28a745;
}

.form-agendamento-lead {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.lead-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #28a745;
    margin-bottom: 10px;
}

.lead-item {
    margin-bottom: 8px;
    font-size: 14px;
    color: #495057;
}

.lead-item:last-child {
    margin-bottom: 0;
}

.lead-item strong {
    color: #28a745;
    font-weight: 600;
}

/* Responsividade para modal de agendamento */
@media (max-width: 768px) {
    .modal-agendamento {
        max-width: 95%;
        margin: 10px;
    }
    
    .modal-agendamento-header h3 {
        font-size: 1.2rem;
    }
    
    .lead-info {
        padding: 12px;
    }
    
    .lead-item {
        font-size: 13px;
    }
}

/* Estilos para modal de tipo de contato */
.modal-tipo-contato {
    max-width: 600px;
    text-align: left;
}

.modal-tipo-contato-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.modal-tipo-contato-header h3 {
    margin: 0;
    color: #007bff;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-tipo-contato-header h3 i {
    color: #007bff;
}

.lead-info-contato {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #007bff;
    margin-bottom: 20px;
}

.tipos-contato-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.tipo-contato-option {
    display: flex;
    align-items: center;
    padding: 20px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.tipo-contato-option:hover {
    border-color: #007bff;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 123, 255, 0.15);
}

.tipo-contato-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.5rem;
    color: white;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.tipo-contato-icon.whatsapp {
    background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
}

.tipo-contato-icon.presencial {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
}

.tipo-contato-icon.email {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
}

.tipo-contato-info h4 {
    margin: 0 0 5px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.tipo-contato-info p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
    line-height: 1.4;
}

/* Responsividade para modal de tipo de contato */
@media (max-width: 768px) {
    .modal-tipo-contato {
        max-width: 95%;
        margin: 10px;
    }
    
    .modal-tipo-contato-header h3 {
        font-size: 1.2rem;
    }
    
    .tipos-contato-container {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .tipo-contato-option {
        padding: 15px;
    }
    
    .tipo-contato-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
        margin-right: 12px;
    }
    
    .tipo-contato-info h4 {
        font-size: 1rem;
    }
    
    .tipo-contato-info p {
        font-size: 0.85rem;
    }
}
</style>

