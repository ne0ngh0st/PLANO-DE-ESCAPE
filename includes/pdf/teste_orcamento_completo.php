<?php
// Teste completo da geração de PDF com dados de exemplo
require_once '../config.php';

// Dados de teste com itens
$orcamento_teste = [
    'id' => 999,
    'cliente_nome' => 'Empresa Teste Ltda',
    'cliente_email' => 'contato@empresateste.com.br',
    'cliente_telefone' => '(11) 99999-9999',
    'tipo_produto_servico' => 'produto',
    'produto_servico' => 'Sistema de Automação Industrial',
    'descricao' => 'Sistema completo de automação para linha de produção',
    'valor_total' => 15750.00,
    'status' => 'pendente',
    'forma_pagamento' => '28_ddl',
    'data_criacao' => date('Y-m-d H:i:s'),
    'data_validade' => date('Y-m-d', strtotime('+5 days')),
    'observacoes' => 'Orçamento válido por 5 dias. Entrega em 30 dias úteis.',
    'usuario_criador' => 'João Silva Vendedor',
    'codigo_vendedor' => '001',
    'itens_orcamento' => json_encode([
        [
            'item' => '001',
            'descricao' => 'Controlador PLC Siemens S7-1200',
            'quantidade' => 2,
            'embalagem' => 1,
            'valor_unitario' => 2500.00,
            'valor_total' => 5000.00
        ],
        [
            'item' => '002',
            'descricao' => 'HMI Touch Screen 10"',
            'quantidade' => 1,
            'embalagem' => 1,
            'valor_unitario' => 1200.00,
            'valor_total' => 1200.00
        ],
        [
            'item' => '003',
            'descricao' => 'Sensores de Proximidade Indutivos',
            'quantidade' => 10,
            'embalagem' => 5,
            'valor_unitario' => 85.00,
            'valor_total' => 850.00
        ],
        [
            'item' => '004',
            'descricao' => 'Cabo de Rede Industrial CAT6',
            'quantidade' => 100,
            'embalagem' => 305,
            'valor_unitario' => 8.50,
            'valor_total' => 850.00
        ],
        [
            'item' => '005',
            'descricao' => 'Serviço de Instalação e Configuração',
            'quantidade' => 1,
            'embalagem' => 1,
            'valor_unitario' => 7850.00,
            'valor_total' => 7850.00
        ]
    ])
];

// Simular função de geração de PDF
function gerarHTMLOrcamentoTeste($orcamento) {
    $data_criacao = date('d/m/Y', strtotime($orcamento['data_criacao']));
    $data_validade = $orcamento['data_validade'] ? date('d/m/Y', strtotime($orcamento['data_validade'])) : 'Não definida';
    
    // Mapear status
    $status_labels = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'rejeitado' => 'Rejeitado',
        'cancelado' => 'Cancelado'
    ];
    
    $status = $status_labels[$orcamento['status']] ?? 'Pendente';
    
    // Mapear forma de pagamento
    $forma_pagamento_labels = [
        'a_vista' => 'À Vista',
        '28_ddl' => '28 DDL'
    ];
    $forma_pagamento = $forma_pagamento_labels[$orcamento['forma_pagamento']] ?? $orcamento['forma_pagamento'] ?? 'Não informado';
    
    // Processar itens do orçamento
    $itens_html = '';
    if (!empty($orcamento['itens_orcamento'])) {
        try {
            $itens = json_decode($orcamento['itens_orcamento'], true);
            if (is_array($itens) && count($itens) > 0) {
                $itens_html = gerarTabelaItensTeste($itens);
            }
        } catch (Exception $e) {
            error_log("Erro ao processar itens do orçamento: " . $e->getMessage());
        }
    }
    
    // Caminho do logo
    $logo_path = file_exists('../assets/img/LOGO AUTOPEL VETOR-01.png') 
        ? '../assets/img/LOGO AUTOPEL VETOR-01.png' 
        : '../../assets/img/LOGO AUTOPEL VETOR-01.png';
    $logo_exists = file_exists($logo_path);
    
    return '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Orçamento #' . $orcamento['id'] . ' - Autopel</title>
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
            .section-subtitle {
                font-size: 14px;
                font-weight: bold;
                color: #1a237e;
                margin: 20px 0 10px 0;
                padding-bottom: 5px;
                border-bottom: 1px solid #e2e8f0;
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
            .itens-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                overflow: hidden;
                table-layout: fixed;
            }
            .itens-table th {
                background: #1a237e;
                color: white;
                padding: 12px 8px;
                text-align: left;
                font-size: 11px;
                font-weight: bold;
                border: 1px solid #1a237e;
            }
            .itens-table td {
                padding: 10px 8px;
                border-bottom: 1px solid #e2e8f0;
                border-left: 1px solid #e2e8f0;
                border-right: 1px solid #e2e8f0;
                font-size: 10px;
                vertical-align: top;
            }
            .itens-table tbody tr:hover {
                background: #f8fafc;
            }
            .itens-table tbody tr:last-child td {
                border-bottom: none;
            }
            .itens-table .text-right {
                text-align: right;
            }
            .itens-table .text-center {
                text-align: center;
            }
            .itens-table .valor-cell {
                font-weight: bold;
                color: #059669;
            }
            .itens-table th:nth-child(1),
            .itens-table td:nth-child(1) {
                width: 10%;
            }
            .itens-table th:nth-child(2),
            .itens-table td:nth-child(2) {
                width: 35%;
            }
            .itens-table th:nth-child(3),
            .itens-table td:nth-child(3) {
                width: 12%;
            }
            .itens-table th:nth-child(4),
            .itens-table td:nth-child(4) {
                width: 12%;
            }
            .itens-table th:nth-child(5),
            .itens-table td:nth-child(5) {
                width: 15%;
            }
            .itens-table th:nth-child(6),
            .itens-table td:nth-child(6) {
                width: 16%;
            }
            .itens-total-row {
                background: #f8fafc;
                font-weight: bold;
            }
            .itens-total-row td {
                border-top: 2px solid #1a237e;
                padding: 12px 8px;
                font-size: 12px;
            }
            .itens-total-label {
                text-align: right;
                color: #1a237e;
            }
            .itens-total-value {
                color: #059669;
                font-size: 14px;
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
                ' . ($logo_exists ? '<img src="' . $logo_path . '" alt="Autopel" class="logo-img">' : '') . '
                <div class="company-info">
                    <div class="company-details">
                        Soluções em Automação Industrial<br>
                        CNPJ: 06.698.091/0001-67<br>
                        Vendedor: João Silva Vendedor (001)<br>
                        Tel: (11) 98765-4321 | Email: joao.silva@autopel.com.br
                    </div>
                </div>
            </div>
            <div class="orcamento-header">
                <h1 class="orcamento-title">ORÇAMENTO</h1>
                <div class="orcamento-number">Nº ' . $orcamento['id'] . '</div>
                <div class="orcamento-date">Data: ' . $data_criacao . '</div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <div class="card-title">DADOS DO CLIENTE</div>
                <div class="info-row">
                    <span class="info-label">Nome:</span>
                    <span class="info-value">' . htmlspecialchars($orcamento['cliente_nome']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">E-mail:</span>
                    <span class="info-value">' . htmlspecialchars($orcamento['cliente_email'] ?: 'Não informado') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Telefone:</span>
                    <span class="info-value">' . htmlspecialchars($orcamento['cliente_telefone'] ?: 'Não informado') . '</span>
                </div>
            </div>
            
            <div class="info-card">
                <div class="card-title">INFORMAÇÕES DO ORÇAMENTO</div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-' . $orcamento['status'] . '">' . $status . '</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Forma de Pagamento:</span>
                    <span class="info-value">' . htmlspecialchars($forma_pagamento) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Validade:</span>
                    <span class="info-value">' . $data_validade . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vendedor:</span>
                    <span class="info-value">' . htmlspecialchars($orcamento['usuario_criador']) . '</span>
                </div>
            </div>
        </div>
        
        <div class="produto-section">
            <h2 class="section-title">PRODUTO/SERVIÇO</h2>
            <div class="produto-descricao">
                <div class="info-row">
                    <span class="info-label">Tipo:</span>
                    <span class="info-value">' . ($orcamento['tipo_produto_servico'] === 'produto' ? 'Produto' : ($orcamento['tipo_produto_servico'] === 'servico' ? 'Serviço' : 'Não informado')) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Descrição:</span>
                    <span class="info-value"><strong>' . htmlspecialchars($orcamento['produto_servico']) . '</strong></span>
                </div>
                ' . ($orcamento['descricao'] ? '<div class="info-row"><span class="info-label">Detalhes:</span><span class="info-value">' . htmlspecialchars($orcamento['descricao']) . '</span></div>' : '') . '
            </div>
            
            ' . $itens_html . '
        </div>
        
        <div class="valor-total">
            VALOR TOTAL: R$ ' . number_format($orcamento['valor_total'], 2, ',', '.') . '
        </div>
        
        ' . ($orcamento['observacoes'] ? '
        <div class="observacoes">
            <h3>Observações:</h3>
            <p>' . htmlspecialchars($orcamento['observacoes']) . '</p>
        </div>
        ' : '') . '
        
        <div class="footer">
            <div class="footer-grid">
                <div class="footer-section">
                    <div class="footer-title">VENDEDOR RESPONSÁVEL</div>
                    <div class="vendedor-info">
                        ' . htmlspecialchars($orcamento['usuario_criador']) . '<br>
                        Telefone: (11) 99999-9999<br>
                        Email: vendedor@autopel.com.br
                    </div>
                </div>
                <div class="footer-section">
                    <div class="footer-title">CONDIÇÕES</div>
                    <div class="vendedor-info">
                        Validade: ' . $data_validade . '<br>
                        Forma de Pagamento: ' . htmlspecialchars($forma_pagamento) . '<br>
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
            <p><strong>Este orçamento foi gerado automaticamente pelo sistema Autopel em ' . date('d/m/Y H:i') . '</strong></p>
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
    </html>';
}

function gerarTabelaItensTeste($itens) {
    if (empty($itens) || !is_array($itens)) {
        return '';
    }
    
    $html = '
        <h3 class="section-subtitle">ITENS DO ORÇAMENTO</h3>
        <table class="itens-table">
            <thead>
                <tr>
                    <th>ITEM</th>
                    <th>DESCRIÇÃO</th>
                    <th class="text-center">QTD</th>
                    <th class="text-center">EMBALAGEM</th>
                    <th class="text-right">VLR UNITÁRIO</th>
                    <th class="text-right">VLR TOTAL</th>
                </tr>
            </thead>
            <tbody>';
    
    $total_geral = 0;
    
    foreach ($itens as $item) {
        $valor_unitario = floatval($item['valor_unitario'] ?? 0);
        $quantidade = floatval($item['quantidade'] ?? 0);
        $valor_total = floatval($item['valor_total'] ?? 0);
        
        $total_geral += $valor_total;
        
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['item'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($item['descricao'] ?? '-') . '</td>
                    <td class="text-center">' . number_format($quantidade, 0, ',', '.') . '</td>
                    <td class="text-center">' . number_format($item['embalagem'] ?? 0, 0, ',', '.') . '</td>
                    <td class="text-right valor-cell">R$ ' . number_format($valor_unitario, 2, ',', '.') . '</td>
                    <td class="text-right valor-cell">R$ ' . number_format($valor_total, 2, ',', '.') . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
            <tfoot>
                <tr class="itens-total-row">
                    <td colspan="5" class="itens-total-label">TOTAL GERAL:</td>
                    <td class="text-right itens-total-value">R$ ' . number_format($total_geral, 2, ',', '.') . '</td>
                </tr>
            </tfoot>
        </table>';
    
    return $html;
}

// Gerar o HTML de teste
echo gerarHTMLOrcamentoTeste($orcamento_teste);
?>
