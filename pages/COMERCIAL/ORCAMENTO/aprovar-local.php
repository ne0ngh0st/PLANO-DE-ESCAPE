<?php
/**
 * Página de aprovação para teste local
 * URL: http://localhost/Site/aprovar-local.php?token=TOKEN
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
                // Marcar token como usado primeiro
                $tokenSecurity->markTokenAsUsed($orcamento['id'], $clientIp);
                
                $sql_update = "UPDATE ORCAMENTOS SET 
                    data_aprovacao_cliente = CURRENT_TIMESTAMP,
                    status = 'aprovado',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $result = $stmt_update->execute(['id' => $orcamento['id']]);
                
                if ($result) {
                    $sucesso = true;
                    // Atualizar dados do orçamento
                    $orcamento['data_aprovacao_cliente'] = date('Y-m-d H:i:s');
                    $orcamento['status'] = 'aprovado';
                } else {
                    $erro = 'Erro ao processar aprovação. Tente novamente.';
                }
            } elseif ($acao === 'rejeitar') {
                // Marcar token como usado primeiro
                $tokenSecurity->markTokenAsUsed($orcamento['id'], $clientIp);
                
                $sql_update = "UPDATE ORCAMENTOS SET 
                    data_aprovacao_cliente = CURRENT_TIMESTAMP,
                    status = 'rejeitado',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $result = $stmt_update->execute(['id' => $orcamento['id']]);
                
                if ($result) {
                    $sucesso = true;
                    $orcamento['data_aprovacao_cliente'] = date('Y-m-d H:i:s');
                    $orcamento['status'] = 'rejeitado';
                } else {
                    $erro = 'Erro ao processar rejeição. Tente novamente.';
                }
            }
        }
        
    } catch (Exception $e) {
        $erro = 'Erro interno do servidor. Tente novamente mais tarde.';
        error_log("Erro em aprovar-local.php: " . $e->getMessage());
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #ff8f00 0%, #ff6f00 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .content {
            padding: 40px;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .orcamento-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
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
        }
        
        .btn-approve:hover {
            background: #047857;
            transform: translateY(-2px);
        }
        
        .btn-reject {
            background: #dc2626;
            color: white;
        }
        
        .btn-reject:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .debug-info {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .orcamento-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .tabela-itens {
                font-size: 0.875rem;
            }
            
            .tabela-itens th,
            .tabela-itens td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Aprovação de Orçamento</h1>
            <p>Autopel - Soluções em Automação</p>
        </div>
        
        <div class="content">
            <!-- Debug Info -->
            <div class="debug-info">
                <strong>🔧 Modo Debug:</strong> Testando localmente<br>
                <strong>Token:</strong> <?php echo htmlspecialchars($token); ?><br>
                <strong>URL:</strong> http://localhost/Site/aprovar-local.php?token=<?php echo htmlspecialchars($token); ?>
            </div>
            
            <?php if ($erro): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Erro:</strong> <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php elseif ($sucesso): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <strong>Sucesso!</strong> 
                    <?php if ($orcamento['status'] === 'aprovado'): ?>
                        Orçamento aprovado com sucesso! Entraremos em contato em breve.
                    <?php else: ?>
                        Orçamento rejeitado. Obrigado pelo seu retorno.
                    <?php endif; ?>
                </div>
            <?php elseif ($orcamento): ?>
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
                                <?php echo $orcamento['forma_pagamento'] === 'a_vista' ? 'À Vista' : '28 DDL'; ?>
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
                </div>
                
                <div class="actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="acao" value="aprovar">
                        <button type="submit" class="btn btn-approve" onclick="return confirm('Tem certeza que deseja aprovar este orçamento?')">
                            <i class="fas fa-check"></i>
                            Aprovar Orçamento
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="acao" value="rejeitar">
                        <button type="submit" class="btn btn-reject" onclick="return confirm('Tem certeza que deseja rejeitar este orçamento?')">
                            <i class="fas fa-times"></i>
                            Rejeitar Orçamento
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Autopel. Todos os direitos reservados.</p>
            <p>Para dúvidas, entre em contato conosco.</p>
        </div>
    </div>
</body>
</html>




