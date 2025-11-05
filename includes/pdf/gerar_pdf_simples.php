<?php
// Versão simplificada para teste - sem autenticação
$orcamento_id = $_GET['id'] ?? '1';

// Dados de teste
$orcamento = [
    'id' => $orcamento_id,
    'cliente_nome' => 'Cliente Teste',
    'cliente_email' => 'cliente@teste.com',
    'cliente_telefone' => '(11) 99999-9999',
    'produto_servico' => 'Produto de Teste',
    'descricao' => 'Descrição do produto de teste',
    'valor_total' => 1000.00,
    'status' => 'pendente',
    'data_criacao' => date('Y-m-d H:i:s'),
    'data_validade' => date('Y-m-d', strtotime('+30 days')),
    'observacoes' => 'Observações de teste',
    'usuario_criador' => 'Vendedor Teste'
];

$data_criacao = date('d/m/Y', strtotime($orcamento['data_criacao']));
$data_validade = $orcamento['data_validade'] ? date('d/m/Y', strtotime($orcamento['data_validade'])) : 'Não definida';

// Caminho do logo
$logo_path = file_exists('../assets/img/LOGO AUTOPEL VETOR-01.png') 
    ? '../assets/img/LOGO AUTOPEL VETOR-01.png' 
    : '../../assets/img/LOGO AUTOPEL VETOR-01.png';
$logo_exists = file_exists($logo_path);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Orçamento #<?php echo $orcamento['id']; ?> - Autopel</title>
    <style>
        @page {
            margin: 1cm;
            size: A4;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.6;
            font-size: 12px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #1a237e;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-img {
            max-height: 60px;
            max-width: 150px;
        }
        .company-info {
            text-align: left;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1a237e;
            margin: 0;
        }
        .company-details {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        .orcamento-header {
            text-align: right;
        }
        .orcamento-title {
            font-size: 28px;
            font-weight: bold;
            color: #1a237e;
            margin: 0;
        }
        .orcamento-number {
            font-size: 18px;
            color: #666;
            margin: 5px 0;
        }
        .orcamento-date {
            font-size: 12px;
            color: #666;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
        }
        .card-title {
            font-size: 14px;
            font-weight: bold;
            color: #1a237e;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 4px 0;
            font-size: 11px;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            font-weight: bold;
            color: #374151;
            min-width: 80px;
        }
        .info-value {
            color: #1f2937;
            flex: 1;
            text-align: right;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pendente { background: #fef3c7; color: #92400e; }
        .status-aprovado { background: #d1fae5; color: #065f46; }
        .status-rejeitado { background: #fee2e2; color: #991b1b; }
        .status-cancelado { background: #f3f4f6; color: #374151; }
        .produto-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1a237e;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        .produto-descricao {
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .valor-total {
            background: linear-gradient(135deg, #1a237e 0%, #3b82f6 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .observacoes {
            background: #f9fafb;
            border-left: 4px solid #1a237e;
            padding: 15px;
            margin-top: 20px;
            border-radius: 0 8px 8px 0;
        }
        .observacoes h3 {
            margin: 0 0 10px 0;
            color: #1a237e;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .footer-section {
            text-align: center;
        }
        .footer-title {
            font-weight: bold;
            color: #1a237e;
            margin-bottom: 5px;
        }
        .vendedor-info {
            font-size: 11px;
            line-height: 1.4;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1a237e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
        }
        .print-button:hover {
            background: #3b82f6;
        }
        @media print {
            body { margin: 0; padding: 15px; }
            .print-button { display: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Imprimir/Salvar PDF</button>
    
    <div class="header">
        <div class="logo-section">
            <?php if ($logo_exists): ?>
                <img src="<?php echo $logo_path; ?>" alt="Autopel" class="logo-img">
            <?php endif; ?>
            <div class="company-info">
                <div class="company-details">
                    Soluções em Automação Industrial<br>
                    CNPJ: 06.698.091/0001-67
                </div>
            </div>
        </div>
        <div class="orcamento-header">
            <h1 class="orcamento-title">ORÇAMENTO</h1>
            <div class="orcamento-number">Nº <?php echo $orcamento['id']; ?></div>
            <div class="orcamento-date">Data: <?php echo $data_criacao; ?></div>
        </div>
    </div>
    
    <div class="info-grid">
        <div class="info-card">
            <div class="card-title">DADOS DO CLIENTE</div>
            <div class="info-row">
                <span class="info-label">Nome:</span>
                <span class="info-value"><?php echo htmlspecialchars($orcamento['cliente_nome']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">E-mail:</span>
                <span class="info-value"><?php echo htmlspecialchars($orcamento['cliente_email'] ?: 'Não informado'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Telefone:</span>
                <span class="info-value"><?php echo htmlspecialchars($orcamento['cliente_telefone'] ?: 'Não informado'); ?></span>
            </div>
        </div>
        
        <div class="info-card">
            <div class="card-title">INFORMAÇÕES DO ORÇAMENTO</div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value">
                    <span class="status-badge status-<?php echo $orcamento['status']; ?>"><?php echo ucfirst($orcamento['status']); ?></span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Validade:</span>
                <span class="info-value"><?php echo $data_validade; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Vendedor:</span>
                <span class="info-value"><?php echo htmlspecialchars($orcamento['usuario_criador']); ?></span>
            </div>
        </div>
    </div>
    
    <div class="produto-section">
        <h2 class="section-title">PRODUTO/SERVIÇO</h2>
        <div class="produto-descricao">
            <strong><?php echo htmlspecialchars($orcamento['produto_servico']); ?></strong>
            <?php if ($orcamento['descricao']): ?>
                <br><br><?php echo htmlspecialchars($orcamento['descricao']); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="valor-total">
        VALOR TOTAL: R$ <?php echo number_format($orcamento['valor_total'], 2, ',', '.'); ?>
    </div>
    
    <?php if ($orcamento['observacoes']): ?>
    <div class="observacoes">
        <h3>Observações:</h3>
        <p><?php echo htmlspecialchars($orcamento['observacoes']); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <div class="footer-grid">
            <div class="footer-section">
                <div class="footer-title">VENDEDOR RESPONSÁVEL</div>
                <div class="vendedor-info">
                    <?php echo htmlspecialchars($orcamento['usuario_criador']); ?><br>
                    Telefone: (11) 99999-9999<br>
                    Email: vendedor@autopel.com.br
                </div>
            </div>
            <div class="footer-section">
                <div class="footer-title">CONDIÇÕES</div>
                <div class="vendedor-info">
                    Validade: <?php echo $data_validade; ?><br>
                    Forma de Pagamento: A combinar<br>
                    Prazo de Entrega: A combinar
                </div>
            </div>
            <div class="footer-section">
                <div class="footer-title">AUTOPEL</div>
                <div class="vendedor-info">
                    Soluções em Automação Industrial<br>
                    CNPJ: 06.698.091/0001-67
                </div>
            </div>
        </div>
        <p><strong>Este orçamento foi gerado automaticamente pelo sistema Autopel em <?php echo date('d/m/Y H:i'); ?></strong></p>
        <p>Para mais informações, entre em contato conosco.</p>
    </div>
    
    <script>
        // Auto-print após carregar a página
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>


