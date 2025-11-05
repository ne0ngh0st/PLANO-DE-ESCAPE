/**
 * IMPORTAR BASES - JavaScript
 * Gerencia a interface de importação de bases de dados
 */

$(document).ready(function() {
    
    // Configuração de processamento
    let isProcessing = false;
    let currentProcess = null;

    /**
     * Adiciona linha ao console de logs
     */
    function addLog(message, type = 'info') {
        const consoleLog = $('#consoleLog');
        
        // Remover mensagem inicial se existir
        if (consoleLog.find('p.text-muted').length > 0) {
            consoleLog.empty();
        }

        // Definir ícone e classe baseado no tipo
        let icon = '';
        let className = 'log-info';
        
        switch(type) {
            case 'success':
                icon = '✅';
                className = 'log-success';
                break;
            case 'error':
                icon = '❌';
                className = 'log-error';
                break;
            case 'warning':
                icon = '⚠️';
                className = 'log-warning';
                break;
            case 'progress':
                icon = '🔄';
                className = 'log-progress';
                break;
            case 'info':
            default:
                icon = 'ℹ️';
                className = 'log-info';
                break;
        }

        // Adicionar linha ao console
        const timestamp = new Date().toLocaleTimeString('pt-BR');
        const logLine = $(`<p class="${className}"><span class="log-icon">${icon}</span>[${timestamp}] ${message}</p>`);
        consoleLog.append(logLine);

        // Auto-scroll para o final
        consoleLog.scrollTop(consoleLog[0].scrollHeight);
    }

    /**
     * Limpa o console de logs
     */
    $('#btnLimparLogs').on('click', function() {
        $('#consoleLog').html('<p class="text-muted mb-0">Console limpo. Aguardando nova importação...</p>');
    });

    /**
     * Atualiza status de uma planilha
     */
    function updatePlanilhaStatus(planilhaId, status, message = '') {
        const card = $(`.planilha-card[data-planilha-id="${planilhaId}"]`);
        const statusBadge = $(`#status-${planilhaId}`);
        const progressBar = $(`#progress-${planilhaId}`);
        const btnImportar = $(`.btn-importar[data-planilha-id="${planilhaId}"]`);

        // Remover classes anteriores
        card.removeClass('processing success error');
        statusBadge.removeClass('badge-aguardando badge-processando badge-sucesso badge-erro');

        // Aplicar novo status
        switch(status) {
            case 'processing':
                card.addClass('processing');
                statusBadge.addClass('badge-processando').text('Processando...');
                progressBar.show();
                btnImportar.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processando...');
                break;
            
            case 'success':
                card.addClass('success');
                statusBadge.addClass('badge-sucesso').text('✓ Concluído');
                progressBar.hide();
                btnImportar.prop('disabled', false).html('<i class="fas fa-check me-1"></i> Concluído');
                break;
            
            case 'error':
                card.addClass('error');
                statusBadge.addClass('badge-erro').text('✗ Erro');
                progressBar.hide();
                btnImportar.prop('disabled', false).html('<i class="fas fa-times me-1"></i> Erro');
                break;
            
            default:
                statusBadge.addClass('badge-aguardando').text('Aguardando');
                progressBar.hide();
                btnImportar.prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Importar');
                break;
        }

        if (message) {
            addLog(message, status === 'success' ? 'success' : (status === 'error' ? 'error' : 'info'));
        }
    }

    /**
     * Mostra alerta na página
     */
    function showAlert(message, type = 'info') {
        const alertContainer = $('#alertContainer');
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const alert = $(`
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <strong>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ️'}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);

        alertContainer.append(alert);

        // Auto-remover após 5 segundos
        setTimeout(() => {
            alert.alert('close');
        }, 5000);
    }

    /**
     * Processa logs recebidos do Python
     */
    function processLogs(logs) {
        if (!logs || !Array.isArray(logs)) return;

        logs.forEach(log => {
            // Detectar tipo de log baseado em emojis/símbolos
            if (log.includes('✅') || log.includes('sucesso')) {
                addLog(log, 'success');
            } else if (log.includes('❌') || log.includes('Erro') || log.includes('erro')) {
                addLog(log, 'error');
            } else if (log.includes('⚠️') || log.includes('Aviso')) {
                addLog(log, 'warning');
            } else if (log.includes('🔄') || log.includes('Processando') || log.includes('Enviando')) {
                addLog(log, 'progress');
            } else {
                addLog(log, 'info');
            }
        });
    }

    /**
     * Executa importação de uma planilha específica
     */
    function importarPlanilha(planilhaId) {
        if (isProcessing) {
            showAlert('Já existe uma importação em andamento. Aguarde a conclusão.', 'warning');
            return;
        }

        isProcessing = true;
        updatePlanilhaStatus(planilhaId, 'processing', `Iniciando importação da planilha ${planilhaId}...`);

        $.ajax({
            url: 'includes/ajax/importar_base_ajax.php',
            type: 'POST',
            data: {
                tipo: planilhaId
            },
            dataType: 'json',
            timeout: 600000, // 10 minutos
            success: function(response) {
                if (response.success) {
                    updatePlanilhaStatus(planilhaId, 'success', 'Importação concluída com sucesso!');
                    showAlert(response.message || 'Importação concluída!', 'success');
                    
                    if (response.logs) {
                        processLogs(response.logs);
                    }
                } else {
                    updatePlanilhaStatus(planilhaId, 'error', 'Erro na importação: ' + (response.message || 'Erro desconhecido'));
                    showAlert(response.message || 'Erro ao importar planilha', 'error');
                    
                    if (response.logs) {
                        processLogs(response.logs);
                    }
                    
                    if (response.errors) {
                        addLog('Erros: ' + response.errors, 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                updatePlanilhaStatus(planilhaId, 'error', 'Erro de conexão: ' + error);
                showAlert('Erro ao conectar com o servidor: ' + error, 'error');
                addLog('Erro AJAX: ' + error, 'error');
                addLog('Status: ' + status, 'error');
            },
            complete: function() {
                isProcessing = false;
            }
        });
    }

    /**
     * Importa múltiplas planilhas sequencialmente
     */
    function importarMultiplas(ids) {
        if (ids.length === 0) {
            showAlert('Todas as importações foram concluídas!', 'success');
            return;
        }

        const planilhaId = ids.shift();
        addLog(`Iniciando importação ${planilhaId}...`, 'info');
        
        updatePlanilhaStatus(planilhaId, 'processing');

        $.ajax({
            url: 'includes/ajax/importar_base_ajax.php',
            type: 'POST',
            data: {
                tipo: planilhaId
            },
            dataType: 'json',
            timeout: 600000,
            success: function(response) {
                if (response.success) {
                    updatePlanilhaStatus(planilhaId, 'success', `Planilha ${planilhaId} importada com sucesso!`);
                    
                    if (response.logs) {
                        processLogs(response.logs);
                    }
                } else {
                    updatePlanilhaStatus(planilhaId, 'error', `Erro ao importar planilha ${planilhaId}`);
                    
                    if (response.logs) {
                        processLogs(response.logs);
                    }
                }
                
                // Continuar com a próxima
                setTimeout(() => importarMultiplas(ids), 1000);
            },
            error: function(xhr, status, error) {
                updatePlanilhaStatus(planilhaId, 'error', 'Erro de conexão');
                addLog(`Erro ao importar planilha ${planilhaId}: ${error}`, 'error');
                
                // Continuar mesmo com erro
                setTimeout(() => importarMultiplas(ids), 1000);
            }
        });
    }

    /**
     * Botão: Importar planilha individual
     */
    $('.btn-importar').on('click', function() {
        const planilhaId = $(this).data('planilha-id');
        importarPlanilha(planilhaId);
    });

    /**
     * Botão: Rotina (Rápida)
     */
    $('#btnRotina').on('click', function() {
        if (isProcessing) {
            showAlert('Já existe uma importação em andamento.', 'warning');
            return;
        }

        if (!confirm('Deseja executar a ROTINA de importação?\n\nSerão importadas:\n• Último Faturamento\n• Faturamento\n• Pedidos em Aberto\n\nEsta operação pode levar alguns minutos.')) {
            return;
        }

        isProcessing = true;
        addLog('Iniciando ROTINA de importação...', 'info');
        showAlert('ROTINA iniciada. Aguarde...', 'info');

        // Rotina: IDs 5, 1, 3
        const rotina = [5, 1, 3];
        
        importarMultiplas([...rotina]); // Clonar array
    });

    /**
     * Botão: Importar Todas
     */
    $('#btnImportarTodas').on('click', function() {
        if (isProcessing) {
            showAlert('Já existe uma importação em andamento.', 'warning');
            return;
        }

        if (!confirm('Deseja importar TODAS as bases de dados?\n\nEsta operação pode levar vários minutos.\n\nContinuar?')) {
            return;
        }

        isProcessing = true;
        addLog('Iniciando importação de todas as bases...', 'info');
        showAlert('Importação de todas as bases iniciada. Aguarde...', 'info');

        // Todas: IDs 1 a 9
        const todas = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        
        importarMultiplas([...todas]); // Clonar array
    });

    /**
     * Click no card para importar
     */
    $('.planilha-card').on('click', function() {
        const planilhaId = $(this).data('planilha-id');
        const btn = $(this).find('.btn-importar');
        
        if (!btn.prop('disabled')) {
            importarPlanilha(planilhaId);
        }
    });

    /**
     * Logs iniciais
     */
    addLog('Sistema de importação carregado e pronto!', 'success');
    addLog('Python necessário: certifique-se de ter Python 3.x instalado', 'info');
    addLog('Dependências: pandas, sqlalchemy, mysql-connector-python, openpyxl', 'info');
    addLog('Comando de instalação: pip install -r requirements.txt', 'info');

    // ============================================
    // GERENCIAMENTO DE ARQUIVOS
    // ============================================

    let filesQueue = [];

    /**
     * Carrega lista de arquivos disponíveis
     */
    function carregarArquivos() {
        console.log('🔵 Iniciando carregamento de arquivos...');
        console.log('URL:', 'includes/ajax/gerenciar_arquivos_ajax.php?acao=listar');
        
        $.ajax({
            url: 'includes/ajax/gerenciar_arquivos_ajax.php',
            type: 'GET',
            data: { acao: 'listar' },
            dataType: 'text', // Mudado de 'json' para 'text' para parsear manualmente
            beforeSend: function(xhr) {
                console.log('📤 Enviando requisição...');
            },
            success: function(data, textStatus, xhr) {
                console.log('✅ Resposta recebida (raw):', data);
                console.log('Status:', xhr.status);
                console.log('Content-Type:', xhr.getResponseHeader('Content-Type'));
                
                // Parsear JSON manualmente
                let response;
                try {
                    response = JSON.parse(data);
                    console.log('✅ JSON parseado com sucesso:', response);
                } catch (e) {
                    console.error('❌ Erro ao parsear JSON:', e);
                    console.error('Data recebido:', data);
                    $('#listaArquivos').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times me-2"></i>
                            Erro ao processar resposta do servidor<br>
                            <small>${e.message}</small>
                        </div>
                    `);
                    return;
                }
                
                console.log('✅ Resposta parseada:', response);
                
                if (response.success) {
                    console.log('📁 Arquivos:', response.arquivos);
                    console.log('📂 Pasta:', response.pasta);
                    renderizarListaArquivos(response.arquivos);
                    $('#pastaAtual').text(response.pasta);
                } else {
                    console.warn('⚠️ Resposta com sucesso = false:', response.message);
                    $('#listaArquivos').html(`
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${response.message}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ ERRO AJAX COMPLETO:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    responseLength: xhr.responseText ? xhr.responseText.length : 0,
                    contentType: xhr.getResponseHeader('Content-Type')
                });
                
                console.error('📄 Response Text (primeiros 500 chars):');
                console.error(xhr.responseText ? xhr.responseText.substring(0, 500) : 'VAZIO');
                
                console.error('📄 Response Text (completo):');
                console.error(xhr.responseText);
                
                let errorMsg = 'Erro ao carregar lista de arquivos';
                
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    if (errorData.message) {
                        errorMsg = errorData.message;
                    }
                    if (errorData.error) {
                        errorMsg += '<br><small>' + errorData.error + '</small>';
                    }
                } catch (e) {
                    if (xhr.responseText) {
                        errorMsg += '<br><small>Resposta: ' + xhr.responseText.substring(0, 200) + '</small>';
                    }
                }
                
                $('#listaArquivos').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-times me-2"></i>
                        ${errorMsg}
                    </div>
                `);
            }
        });
    }

    /**
     * Renderiza lista de arquivos
     */
    function renderizarListaArquivos(arquivos) {
        const lista = $('#listaArquivos');
        
        if (arquivos.length === 0) {
            lista.html(`
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Nenhum arquivo encontrado. Faça upload de planilhas Excel (.xlsx ou .xls).
                </div>
            `);
            return;
        }
        
        lista.empty();
        
        arquivos.forEach(arquivo => {
            const icone = arquivo.extensao === 'xlsx' ? 'file-excel' : 'file';
            const item = $(`
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-${icone} text-success me-2"></i>
                        <strong>${arquivo.nome}</strong>
                        <br>
                        <small class="text-muted">
                            <i class="fas fa-hdd me-1"></i> ${arquivo.tamanho_formatado} | 
                            <i class="fas fa-clock me-1"></i> ${arquivo.modificado_formatado}
                        </small>
                    </div>
                    <button class="btn btn-sm btn-danger btn-deletar-arquivo" data-arquivo="${arquivo.nome}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `);
            
            lista.append(item);
        });
    }

    /**
     * Botão: Atualizar lista
     */
    $('#btnAtualizarLista').on('click', function() {
        $(this).find('i').addClass('fa-spin');
        carregarArquivos();
        setTimeout(() => {
            $(this).find('i').removeClass('fa-spin');
        }, 1000);
    });

    /**
     * Botão: Deletar arquivo
     */
    $(document).on('click', '.btn-deletar-arquivo', function() {
        const arquivo = $(this).data('arquivo');
        
        if (!confirm(`Tem certeza que deseja deletar "${arquivo}"?`)) {
            return;
        }
        
        $.ajax({
            url: 'includes/ajax/gerenciar_arquivos_ajax.php',
            type: 'POST',
            data: {
                acao: 'deletar',
                arquivo: arquivo
            },
            dataType: 'text',
            success: function(data) {
                try {
                    const response = JSON.parse(data);
                    if (response.success) {
                        showAlert(response.message, 'success');
                        carregarArquivos();
                    } else {
                        showAlert(response.message, 'error');
                    }
                } catch (e) {
                    console.error('Erro ao parsear resposta:', e, data);
                    showAlert('Erro ao processar resposta do servidor', 'error');
                }
            },
            error: function() {
                showAlert('Erro ao deletar arquivo', 'error');
            }
        });
    });

    // ============================================
    // UPLOAD DE ARQUIVOS
    // ============================================

    /**
     * Botão: Selecionar arquivos
     */
    $('#btnSelecionarArquivos').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#fileInput')[0].click(); // Usar [0].click() para evitar loop
        return false;
    });
    
    /**
     * DropZone: Click para selecionar (ignorar cliques no botão)
     */
    $('#dropZone').on('click', function(e) {
        // Se clicou no botão ou dentro dele, não fazer nada
        if ($(e.target).is('#btnSelecionarArquivos') || $(e.target).closest('#btnSelecionarArquivos').length > 0) {
            return;
        }
        
        e.preventDefault();
        $('#fileInput')[0].click(); // Usar [0].click() para evitar loop
    });

    /**
     * Input: Seleção de arquivos
     */
    $('#fileInput').on('change', function(e) {
        e.stopPropagation(); // Evitar propagação para o dropZone
        const files = Array.from(this.files);
        adicionarArquivosNaFila(files);
    });

    /**
     * Drag and Drop
     */
    $('#dropZone')
        .on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('border-success bg-light');
        })
        .on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('border-success bg-light');
        })
        .on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('border-success bg-light');
            
            const files = Array.from(e.originalEvent.dataTransfer.files);
            adicionarArquivosNaFila(files);
        });

    /**
     * Adiciona arquivos na fila de upload
     */
    function adicionarArquivosNaFila(files) {
        filesQueue = [...filesQueue, ...files];
        renderizarFilaUpload();
        $('#uploadQueue').show();
    }

    /**
     * Renderiza fila de upload
     */
    function renderizarFilaUpload() {
        const lista = $('#uploadList');
        lista.empty();
        
        filesQueue.forEach((file, index) => {
            const icone = file.name.endsWith('.xlsx') || file.name.endsWith('.xls') ? 'file-excel' : 'file';
            const tamanho = formatBytes(file.size);
            
            const item = $(`
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-${icone} text-primary me-2"></i>
                        <strong>${file.name}</strong>
                        <br>
                        <small class="text-muted">${tamanho}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger btn-remover-fila" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
            
            lista.append(item);
        });
    }

    /**
     * Remover arquivo da fila
     */
    $(document).on('click', '.btn-remover-fila', function() {
        const index = $(this).data('index');
        filesQueue.splice(index, 1);
        
        if (filesQueue.length === 0) {
            $('#uploadQueue').hide();
            $('#fileInput').val('');
        } else {
            renderizarFilaUpload();
        }
    });

    /**
     * Iniciar upload
     */
    $('#btnIniciarUpload').on('click', function() {
        if (filesQueue.length === 0) {
            showAlert('Nenhum arquivo selecionado', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('acao', 'upload');
        
        filesQueue.forEach(file => {
            formData.append('arquivos[]', file);
        });
        
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...');
        
        $.ajax({
            url: 'includes/ajax/gerenciar_arquivos_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'text',
            success: function(data) {
                let response;
                try {
                    response = JSON.parse(data);
                } catch (e) {
                    console.error('Erro ao parsear resposta de upload:', e, data);
                    showAlert('Erro ao processar resposta do servidor', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-upload me-2"></i>Fazer Upload de Todos os Arquivos');
                    return;
                }
                
                if (response.success) {
                    showAlert(`${response.total_enviados} arquivo(s) enviado(s) com sucesso!`, 'success');
                    
                    if (response.erros && response.erros.length > 0) {
                        response.erros.forEach(erro => {
                            addLog(erro, 'error');
                        });
                    }
                    
                    // Limpar fila
                    filesQueue = [];
                    $('#uploadQueue').hide();
                    $('#fileInput').val('');
                    
                    // Atualizar lista
                    carregarArquivos();
                    
                    // Voltar para aba de arquivos
                    $('#arquivos-tab').click();
                } else {
                    showAlert(response.message || 'Erro ao fazer upload', 'error');
                }
            },
            error: function() {
                showAlert('Erro ao enviar arquivos', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-upload me-2"></i>Fazer Upload de Todos os Arquivos');
            }
        });
    });

    // ============================================
    // PASTA CUSTOMIZADA
    // ============================================

    /**
     * Carregar configuração de pasta
     */
    function carregarConfiguracaoPasta() {
        $.ajax({
            url: 'includes/ajax/gerenciar_arquivos_ajax.php',
            type: 'GET',
            data: { acao: 'obter_pasta' },
            dataType: 'text',
            success: function(data) {
                try {
                    const response = JSON.parse(data);
                    if (response.success && response.eh_customizada) {
                        $('#inputPastaCustomizada').val(response.pasta);
                    }
                } catch (e) {
                    console.error('Erro ao carregar configuração de pasta:', e);
                }
            }
        });
    }

    /**
     * Form: Salvar pasta customizada
     */
    $('#formPastaCustomizada').on('submit', function(e) {
        e.preventDefault();
        
        const caminho = $('#inputPastaCustomizada').val().trim();
        
        $.ajax({
            url: 'includes/ajax/gerenciar_arquivos_ajax.php',
            type: 'POST',
            data: {
                acao: 'configurar_pasta',
                caminho: caminho
            },
            dataType: 'text',
            success: function(data) {
                try {
                    const response = JSON.parse(data);
                    if (response.success) {
                        showAlert(response.message, 'success');
                        $('#pastaAtual').text(response.pasta);
                        carregarArquivos();
                    } else {
                        showAlert(response.message, 'error');
                    }
                } catch (e) {
                    console.error('Erro ao parsear resposta:', e, data);
                    showAlert('Erro ao processar resposta do servidor', 'error');
                }
            },
            error: function() {
                showAlert('Erro ao salvar configuração', 'error');
            }
        });
    });

    /**
     * Botão: Resetar pasta
     */
    $('#btnResetPasta').on('click', function() {
        $('#inputPastaCustomizada').val('');
        $('#formPastaCustomizada').submit();
    });

    /**
     * Botão: Testar pasta
     */
    $('#btnTestarPasta').on('click', function() {
        const caminho = $('#inputPastaCustomizada').val().trim();
        
        if (!caminho) {
            showAlert('Digite um caminho para testar', 'warning');
            return;
        }
        
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Testando...');
        
        $.ajax({
            url: 'includes/ajax/gerenciar_arquivos_ajax.php',
            type: 'POST',
            data: {
                acao: 'testar_pasta',
                caminho: caminho
            },
            dataType: 'text',
            success: function(data) {
                let response;
                try {
                    response = JSON.parse(data);
                } catch (e) {
                    console.error('Erro ao parsear resposta:', e, data);
                    $('#resultadoTestePasta').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            Erro ao processar resposta do servidor
                        </div>
                    `).show();
                    btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Testar Caminho');
                    return;
                }
                
                const resultado = $('#resultadoTestePasta');
                
                if (response.success) {
                    resultado.html(`
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>${response.message}</h6>
                            <ul class="mb-0 mt-2">
                                <li>Caminho absoluto: <code>${response.info.caminho_absoluto}</code></li>
                                <li>Legível: <strong>${response.info.legivel ? 'Sim ✅' : 'Não ❌'}</strong></li>
                                <li>Gravável: <strong>${response.info.gravavel ? 'Sim ✅' : 'Não ❌'}</strong></li>
                                <li>Arquivos Excel: <strong>${response.info.arquivos_excel}</strong></li>
                            </ul>
                        </div>
                    `).show();
                } else {
                    resultado.html(`
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-times-circle me-2"></i>${response.message}</h6>
                            ${response.info ? `<small>Caminho testado: <code>${response.info.caminho_absoluto || caminho}</code></small>` : ''}
                        </div>
                    `).show();
                }
            },
            error: function() {
                $('#resultadoTestePasta').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        Erro ao testar caminho
                    </div>
                `).show();
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Testar Caminho');
            }
        });
    });

    /**
     * Função auxiliar: Format bytes
     */
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // ============================================
    // INICIALIZAÇÃO
    // ============================================

    // Carregar lista de arquivos ao carregar a página
    carregarArquivos();
    
    // Carregar configuração de pasta
    carregarConfiguracaoPasta();
});

