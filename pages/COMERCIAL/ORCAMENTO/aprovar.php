<?php
/**
 * Página genérica de aprovação - sem expor URL do site de gestão
 * URL: https://aprovacao.autopel.com.br/aprovar.php?token=TOKEN
 */

require_once dirname(__DIR__, 3) . '/includes/config/config.php';
require_once dirname(__DIR__, 3) . '/includes/security/TokenSecurity.php';

$token = $_GET['token'] ?? '';
$erro = '';
$orcamento = null;
$sucesso = false;

// Inicializar classe de segurança
$tokenSecurity = new TokenSecurity($pdo);

if (empty($token)) {
    $erro = 'Token de aprovação não fornecido.';
} else {
    try {
        // Validar token com segurança
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $validation = $tokenSecurity->validateToken($token, $clientIp);
        
        if (!$validation['valid']) {
            $erro = $validation['error'];
        } else {
            // Buscar dados do orçamento
            $sql = "SELECT * FROM ORCAMENTOS WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $validation['orcamento_id']]);
            $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$orcamento) {
                $erro = 'Orçamento não encontrado.';
            }
        }
        
        // Processar aprovação se for POST
        if ($_POST && !$erro && $orcamento) {
            $acao = $_POST['acao'] ?? '';
            
            if ($acao === 'aprovar') {
                $sql_update = "UPDATE ORCAMENTOS SET 
                    data_aprovacao_cliente = CURRENT_TIMESTAMP,
                    status_cliente = 'aprovado',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $result = $stmt_update->execute(['id' => $orcamento['id']]);
                
                if ($result) {
                    // Marcar token como usado APENAS após sucesso
                    $tokenSecurity->markTokenAsUsed($orcamento['id'], $clientIp);
                    
                    $sucesso = true;
                    // Atualizar dados do orçamento
                    $orcamento['data_aprovacao_cliente'] = date('Y-m-d H:i:s');
                    $orcamento['status_cliente'] = 'aprovado';
                } else {
                    $erro = 'Erro ao processar aprovação. Tente novamente.';
                }
            } elseif ($acao === 'rejeitar') {
                $sql_update = "UPDATE ORCAMENTOS SET 
                    data_aprovacao_cliente = CURRENT_TIMESTAMP,
                    status_cliente = 'rejeitado',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $result = $stmt_update->execute(['id' => $orcamento['id']]);
                
                if ($result) {
                    // Marcar token como usado APENAS após sucesso
                    $tokenSecurity->markTokenAsUsed($orcamento['id'], $clientIp);
                    
                    $sucesso = true;
                    $orcamento['data_aprovacao_cliente'] = date('Y-m-d H:i:s');
                    $orcamento['status_cliente'] = 'rejeitado';
                } else {
                    $erro = 'Erro ao processar rejeição. Tente novamente.';
                }
            }
        }
        
    } catch (Exception $e) {
        $erro = 'Erro interno do servidor. Tente novamente mais tarde.';
        error_log("Erro em aprovar.php: " . $e->getMessage());
    }
}

// Processar itens do orçamento
$itens = [];
if ($orcamento && $orcamento['itens_orcamento']) {
    try {
        $itens = json_decode($orcamento['itens_orcamento'], true) ?: [];
    } catch (Exception $e) {
        $itens = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprovação de Orçamento - Autopel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            padding: 20px;
            color: #334155;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .header {
            background: #1e293b;
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: 3px solid #3b82f6;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .logo img {
            max-width: 40px;
            max-height: 40px;
        }
        
        .header h1 {
            font-size: 1.75rem;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1rem;
            color: #cbd5e1;
        }
        
        .content {
            padding: 40px;
        }
        
        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }
        
        .success-message {
            background: #f0fdf4;
            color: #166534;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #bbf7d0;
        }
        
        .orcamento-info {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .orcamento-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .orcamento-id {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e293b;
        }
        
        .valor-total {
            font-size: 1.8rem;
            font-weight: bold;
            color: #059669;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 600;
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #1e293b;
            font-size: 1rem;
        }
        
        .itens-section {
            margin-top: 25px;
        }
        
        .itens-section h3 {
            color: #374151;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tabela-itens {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .tabela-itens th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .tabela-itens td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .tabela-itens tbody tr:hover {
            background: #f8fafc;
        }
        
        .tabela-itens tbody tr:last-child td {
            border-bottom: none;
        }
        
        .valor-item {
            font-weight: 600;
            color: #059669;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-approve {
            background: #059669;
            color: white;
            border: 1px solid #047857;
        }
        
        .btn-approve:hover {
            background: #047857;
            border-color: #065f46;
        }
        
        .btn-reject {
            background: #dc2626;
            color: white;
            border: 1px solid #b91c1c;
        }
        
        .btn-reject:hover {
            background: #b91c1c;
            border-color: #991b1b;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .footer {
            background: #f1f5f9;
            padding: 25px;
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
            border-top: 1px solid #e2e8f0;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 6px;
            }
            
            .header {
                padding: 25px 20px;
            }
            
            .logo-section {
                flex-direction: column;
                gap: 10px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .content {
                padding: 25px 20px;
            }
            
            .orcamento-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .tabela-itens {
                font-size: 0.875rem;
            }
            
            .tabela-itens th,
            .tabela-itens td {
                padding: 10px 8px;
            }
            
            .footer {
                padding: 20px;
            }
        }
        
        /* Estilos do Botão e Visualizador de PDF */
        .btn-pdf {
            background: #1e40af;
            color: white;
            margin-bottom: 20px;
            width: 100%;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            display: block;
        }
        
        .btn-pdf:hover {
            background: #1e3a8a;
        }
        
        .pdf-viewer {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            padding: 20px;
        }
        
        .pdf-viewer.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pdf-container {
            background: white;
            border-radius: 12px;
            width: 90%;
            height: 90%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .pdf-header {
            background: #1e40af;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pdf-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .btn-close-pdf {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .btn-close-pdf:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .pdf-frame {
            flex: 1;
            border: none;
            width: 100%;
            height: 100%;
        }
        
        /* Estilos do Modal de Rejeição */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90%;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: #dc2626;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #374151;
        }
        
        .btn-cancel {
            background: #6b7280;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        
        .btn-cancel:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-section">
                <div class="logo">
                    <img src="<?php echo base_url('assets/img/logo_site.png'); ?>" alt="Autopel Logo">
                </div>
                <div>
                    <h1>Aprovação de Orçamento</h1>
                    <p>Autopel - Soluções em Automação</p>
                </div>
            </div>
        </div>
        
        <div class="content">
            <?php if ($erro): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Erro:</strong> <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php elseif ($sucesso): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <strong>Sucesso!</strong> 
                    <?php if ($orcamento['status_cliente'] === 'aprovado'): ?>
                        Orçamento aprovado com sucesso! Entraremos em contato em breve.
                    <?php else: ?>
                        Orçamento rejeitado. Obrigado pelo seu retorno.
                    <?php endif; ?>
                </div>
            <?php elseif ($orcamento): ?>
                <!-- Botão para visualizar PDF -->
                <button type="button" class="btn btn-pdf" onclick="abrirPDF(<?php echo $orcamento['id']; ?>)">
                    <i class="fas fa-file-pdf"></i>
                    Visualizar Orçamento em PDF
                </button>
                
                <div class="orcamento-info">
                    <div class="orcamento-header">
                        <div class="orcamento-id">Orçamento #<?php echo $orcamento['id']; ?></div>
                        <div class="valor-total">R$ <?php echo number_format($orcamento['valor_total'], 2, ',', '.'); ?></div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Cliente</div>
                            <div class="info-value"><?php echo htmlspecialchars($orcamento['cliente_nome']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Produto/Serviço</div>
                            <div class="info-value"><?php echo htmlspecialchars($orcamento['produto_servico']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Forma de Pagamento</div>
                            <div class="info-value">
                                <?php 
                                    // Mapear formas de pagamento comuns
                                    $forma_pag = $orcamento['forma_pagamento'] ?? 'Não informado';
                                    $forma_pag_display = '';
                                    
                                    if ($forma_pag === 'a_vista') {
                                        $forma_pag_display = 'À Vista';
                                    } elseif ($forma_pag === '28_ddl') {
                                        $forma_pag_display = '28 DDL';
                                    } else {
                                        // Mostrar o valor exato que o usuário preencheu
                                        $forma_pag_display = htmlspecialchars($forma_pag);
                                    }
                                    
                                    echo $forma_pag_display;
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Validade</div>
                            <div class="info-value">
                                <?php echo $orcamento['data_validade'] ? date('d/m/Y', strtotime($orcamento['data_validade'])) : 'Não definida'; ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Data de Criação</div>
                            <div class="info-value"><?php echo date('d/m/Y', strtotime($orcamento['data_criacao'])); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Vendedor</div>
                            <div class="info-value"><?php echo htmlspecialchars($orcamento['codigo_vendedor']); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($orcamento['descricao']): ?>
                        <div class="info-item">
                            <div class="info-label">Descrição</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($orcamento['descricao'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($itens)): ?>
                        <div class="itens-section">
                            <h3><i class="fas fa-list"></i> Itens do Orçamento</h3>
                            <table class="tabela-itens">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Descrição</th>
                                        <th>Qtd</th>
                                        <th>Embalagem</th>
                                        <th>Vlr Unitário</th>
                                        <th>Vlr Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($item['descricao'] ?? '-'); ?></td>
                                            <td><?php echo $item['quantidade'] ?? 0; ?></td>
                                            <td><?php echo $item['embalagem'] ?? 0; ?></td>
                                            <td>R$ <?php echo number_format($item['valor_unitario'] ?? 0, 2, ',', '.'); ?></td>
                                            <td class="valor-item">R$ <?php echo number_format($item['valor_total'] ?? 0, 2, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($orcamento['observacoes']): ?>
                        <div class="info-item" style="margin-top: 20px;">
                            <div class="info-label">Observações</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($orcamento['observacoes'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Outras Informações -->
                    <?php if (!empty($orcamento['variacao_produtos']) || !empty($orcamento['prazo_producao']) || !empty($orcamento['garantia_imagem']) || !empty($orcamento['texto_importante'])): ?>
                        <div style="margin-top: 25px; padding: 20px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <h3 style="color: #374151; margin-bottom: 15px; font-size: 1.1rem;">
                                <i class="fas fa-info-circle"></i> Outras Informações
                            </h3>
                            
                            <?php if (!empty($orcamento['variacao_produtos'])): ?>
                                <div class="info-item" style="margin-bottom: 10px;">
                                    <div class="info-label">Variação nos Produtos Personalizados</div>
                                    <div class="info-value"><?php echo htmlspecialchars($orcamento['variacao_produtos']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($orcamento['prazo_producao'])): ?>
                                <div class="info-item" style="margin-bottom: 10px;">
                                    <div class="info-label">Prazo de Produção</div>
                                    <div class="info-value"><?php echo htmlspecialchars($orcamento['prazo_producao']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($orcamento['garantia_imagem'])): ?>
                                <div class="info-item" style="margin-bottom: 10px;">
                                    <div class="info-label">Garantia de Imagem</div>
                                    <div class="info-value"><?php echo htmlspecialchars($orcamento['garantia_imagem']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($orcamento['texto_importante'])): ?>
                                <div style="margin-top: 15px; padding: 15px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px;">
                                    <div class="info-label" style="color: #92400e;"><i class="fas fa-exclamation-triangle"></i> Importante</div>
                                    <div class="info-value" style="color: #92400e; margin-top: 5px;"><?php echo nl2br(htmlspecialchars($orcamento['texto_importante'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="acao" value="aprovar">
                        <button type="submit" class="btn btn-approve" onclick="return confirm('Tem certeza que deseja aprovar este orçamento?')">
                            <i class="fas fa-check"></i>
                            Aprovar Orçamento
                        </button>
                    </form>
                    
                    <button type="button" class="btn btn-reject" onclick="mostrarModalRejeicao()">
                        <i class="fas fa-times"></i>
                        Rejeitar Orçamento
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p><strong>Autopel</strong> - Soluções em Automação Industrial</p>
            <p>&copy; <?php echo date('Y'); ?> Todos os direitos reservados.</p>
            <p>Para dúvidas, entre em contato conosco através do seu vendedor.</p>
        </div>
    </div>
    
    <!-- Visualizador de PDF -->
    <div class="pdf-viewer" id="pdf-viewer">
        <div class="pdf-container">
            <div class="pdf-header">
                <h3><i class="fas fa-file-pdf"></i> Orçamento em PDF</h3>
                <button type="button" class="btn-close-pdf" onclick="fecharPDF()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <iframe class="pdf-frame" id="pdf-frame"></iframe>
        </div>
    </div>
    
    <!-- Modal de Rejeição -->
    <div id="modal-rejeicao" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Rejeitar Orçamento</h3>
                <button type="button" class="modal-close" onclick="fecharModalRejeicao()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Por favor, informe o motivo da rejeição do orçamento:</p>
                <form id="form-rejeicao" method="POST">
                    <input type="hidden" name="acao" value="rejeitar">
                    <div class="form-group">
                        <label for="motivo_recusa">Motivo da Rejeição *</label>
                        <textarea id="motivo_recusa" name="motivo_recusa" rows="4" 
                                  placeholder="Descreva o motivo da rejeição do orçamento..." 
                                  required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="fecharModalRejeicao()">
                    Cancelar
                </button>
                <button type="button" class="btn-reject" onclick="confirmarRejeicao()">
                    <i class="fas fa-times"></i>
                    Confirmar Rejeição
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function mostrarModalRejeicao() {
            document.getElementById('modal-rejeicao').style.display = 'flex';
        }
        
        function fecharModalRejeicao() {
            document.getElementById('modal-rejeicao').style.display = 'none';
            document.getElementById('motivo_recusa').value = '';
        }
        
        function confirmarRejeicao() {
            const motivo = document.getElementById('motivo_recusa').value.trim();
            if (!motivo) {
                alert('Por favor, informe o motivo da rejeição.');
                return;
            }
            
            if (confirm('Tem certeza que deseja rejeitar este orçamento?')) {
                document.getElementById('form-rejeicao').submit();
            }
        }
        
        function abrirPDF(id) {
            const pdfViewer = document.getElementById('pdf-viewer');
            const pdfFrame = document.getElementById('pdf-frame');
            
            // Definir a URL do PDF
            pdfFrame.src = 'includes/pdf/gerar_pdf_orcamento.php?id=' + id;
            
            // Mostrar o visualizador
            pdfViewer.classList.add('active');
            
            // Prevenir scroll do body
            document.body.style.overflow = 'hidden';
        }
        
        function fecharPDF() {
            const pdfViewer = document.getElementById('pdf-viewer');
            const pdfFrame = document.getElementById('pdf-frame');
            
            // Ocultar o visualizador
            pdfViewer.classList.remove('active');
            
            // Limpar iframe
            pdfFrame.src = '';
            
            // Restaurar scroll do body
            document.body.style.overflow = 'auto';
        }
        
        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharPDF();
            }
        });
        
        // Fechar clicando fora do container
        document.getElementById('pdf-viewer').addEventListener('click', function(e) {
            if (e.target.id === 'pdf-viewer') {
                fecharPDF();
            }
        });
    </script>
</body>
</html>
