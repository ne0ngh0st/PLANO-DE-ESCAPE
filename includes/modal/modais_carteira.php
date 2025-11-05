<?php
// Carregar configurações dos modais
require_once __DIR__ . '/../utils/carregar_configuracoes_modais.php';
$configuracoes = carregarConfiguracoesModais($pdo);
?>

<!-- Modal de Confirmação -->
<div id="modalConfirmacaoCliente" class="modal-overlay">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Exclusão</h3>
        <p><?php echo htmlspecialchars($configuracoes['modal']['texto_confirmacao']); ?> <strong id="nomeCliente"></strong> da sua carteira?</p>
        <p>O cliente a partir de agora será trabalhado internamente pela Autopel.</p>
        

        
        <!-- Campo obrigatório para motivo da exclusão -->
        <div class="motivo-exclusao-container">
            <label for="motivoExclusao" class="motivo-label">
                <span class="required">*</span> Motivo da Exclusão:
            </label>
            <select id="motivoExclusao" class="motivo-select" required>
                <option value="">Selecione um motivo</option>
                <?php foreach ($configuracoes['exclusao'] as $motivo): ?>
                    <?php $valor = strtolower(str_replace([' ', '/'], ['_', '_'], $motivo)); ?>
                    <option value="<?php echo $valor; ?>"><?php echo htmlspecialchars($motivo); ?></option>
                <?php endforeach; ?>
            </select>
            <div id="motivoError" class="motivo-error" style="display: none;">
                <i class="fas fa-exclamation-circle"></i>
                <span>Por favor, selecione um motivo para a exclusão.</span>
            </div>
        </div>
        
        <!-- Campo opcional para observações discursativas -->
        <div class="observacao-exclusao-container">
            <label for="observacaoExclusao" class="observacao-label">
                Observações Adicionais (opcional):
            </label>
            <textarea id="observacaoExclusao" class="observacao-textarea" 
                      placeholder="Descreva detalhes adicionais sobre a exclusão, contexto, ou informações relevantes... (mínimo 30 caracteres)"
                      rows="3" maxlength="1000"></textarea>
            
            <!-- Contador de caracteres e barra de progresso -->
            <div class="observacao-counter">
                <span class="char-count" id="charCountCliente">0 / 1000</span>
                <span class="char-status" id="charStatusCliente">Mínimo: 30 caracteres</span>
            </div>
            <div class="observacao-progress">
                <div class="observacao-progress-bar" id="progressBarCliente"></div>
            </div>
        </div>
        
        <div class="modal-buttons">
            <button class="btn-cancelar" onclick="fecharModalCliente()">Cancelar</button>
            <button id="btnConfirmarExclusao" class="btn-confirmar" onclick="excluirCliente()">Sim, Remover da Carteira</button>
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

<!-- Modal de Detalhes do Cliente -->
<div id="modalDetalhesCliente" class="modal-overlay">
    <div class="modal-detalhes">
        <div class="modal-detalhes-header">
            <div class="modal-detalhes-title">
                <i class="fas fa-user"></i>
                <h3>Detalhes do Cliente</h3>
            </div>
            <button class="modal-close-btn" onclick="fecharModalDetalhes()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-detalhes-body">
            <div id="detalhesClienteContent">
                <!-- Detalhes serão carregados aqui -->
            </div>
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
                    <strong>Cliente:</strong> <span id="clienteInfo"></span>
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

<!-- Modal de Edição de Cliente -->
<div id="modalEdicaoCliente" class="modal-overlay">
    <div class="modal-content modal-edicao">
        <div class="modal-edicao-header">
            <h3><i class="fas fa-edit"></i> Editar Cliente</h3>
            <button class="modal-close-btn" onclick="fecharModalEdicaoCliente()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formEdicaoCliente" class="form-edicao-cliente">
            <div class="form-row">
                <div class="form-group">
                    <label for="editClienteNome">Nome da Empresa:</label>
                    <input type="text" id="editClienteNome" name="cliente" required>
                </div>
                <div class="form-group">
                    <label for="editClienteFantasia">Nome Fantasia:</label>
                    <input type="text" id="editClienteFantasia" name="nome_fantasia" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editClienteSegmento">Segmento:</label>
                    <select id="editClienteSegmento" name="segmento">
                        <option value="">Selecione o segmento</option>
                        <!-- As opções serão carregadas dinamicamente -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="editClienteContato">Nome do Contato:</label>
                    <input type="text" id="editClienteContato" name="nome_contato">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="editClienteTelefone">Telefone:</label>
                    <input type="tel" id="editClienteTelefone" name="telefone">
                </div>
                <div class="form-group">
                    <label for="editClienteEmail">E-mail:</label>
                    <input type="email" id="editClienteEmail" name="email">
                </div>
            </div>
            
            <div class="form-group">
                <label for="editClienteEndereco">Endereço Completo:</label>
                <textarea id="editClienteEndereco" name="endereco" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="editClienteEstado">Estado:</label>
                <select id="editClienteEstado" name="estado" required>
                    <option value="">Selecione o estado</option>
                    <option value="AC">Acre</option>
                    <option value="AL">Alagoas</option>
                    <option value="AP">Amapá</option>
                    <option value="AM">Amazonas</option>
                    <option value="BA">Bahia</option>
                    <option value="CE">Ceará</option>
                    <option value="DF">Distrito Federal</option>
                    <option value="ES">Espírito Santo</option>
                    <option value="GO">Goiás</option>
                    <option value="MA">Maranhão</option>
                    <option value="MT">Mato Grosso</option>
                    <option value="MS">Mato Grosso do Sul</option>
                    <option value="MG">Minas Gerais</option>
                    <option value="PA">Pará</option>
                    <option value="PB">Paraíba</option>
                    <option value="PR">Paraná</option>
                    <option value="PE">Pernambuco</option>
                    <option value="PI">Piauí</option>
                    <option value="RJ">Rio de Janeiro</option>
                    <option value="RN">Rio Grande do Norte</option>
                    <option value="RS">Rio Grande do Sul</option>
                    <option value="RO">Rondônia</option>
                    <option value="RR">Roraima</option>
                    <option value="SC">Santa Catarina</option>
                    <option value="SP">São Paulo</option>
                    <option value="SE">Sergipe</option>
                    <option value="TO">Tocantins</option>
                </select>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="btn-cancelar" onclick="fecharModalEdicaoCliente()">Cancelar</button>
                <button type="submit" class="btn-confirmar">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Agendamento para Clientes -->
<div id="modalAgendamentoCliente" class="modal-overlay">
    <div class="modal-content modal-agendamento">
        <div class="modal-agendamento-header">
            <h3><i class="fas fa-calendar-plus"></i> <?php echo htmlspecialchars($configuracoes['modal']['texto_agendamento']); ?></h3>
            <button class="modal-close-btn" onclick="fecharModalAgendamentoCliente()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formAgendamentoCliente" class="form-agendamento-cliente">
            <div class="form-group">
                <label for="agendamentoClienteNome">Cliente:</label>
                <input type="text" id="agendamentoClienteNome" readonly>
                <input type="hidden" id="agendamentoClienteCnpj" name="cliente_cnpj">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="agendamentoData">Data:</label>
                    <input type="date" id="agendamentoData" name="data" required>
                </div>
                <div class="form-group">
                    <label for="agendamentoHora">Hora:</label>
                    <input type="time" id="agendamentoHora" name="hora" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="agendamentoObservacoes">Observações:</label>
                <textarea id="agendamentoObservacoes" name="observacoes" rows="3" 
                          placeholder="Adicione observações sobre o agendamento..."></textarea>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="btn-cancelar" onclick="fecharModalAgendamentoCliente()">Cancelar</button>
                <button type="submit" class="btn-confirmar">Agendar Ligação</button>
            </div>
        </form>
    </div>
</div>

<!-- CSS dos modais está em assets/css/modais.css -->
<style>
/* Estilos específicos para modal de edição de cliente */
.form-edicao-cliente {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Estilos para modal de agendamento */
.modal-agendamento {
    max-width: 500px;
    width: 90%;
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
    color: #007bff;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-agendamento-header h3 i {
    color: #28a745;
}

.form-agendamento-cliente {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .modal-agendamento {
        width: 95%;
        margin: 10px;
    }
    
    .modal-agendamento-header h3 {
        font-size: 1.1rem;
    }
}
</style>

