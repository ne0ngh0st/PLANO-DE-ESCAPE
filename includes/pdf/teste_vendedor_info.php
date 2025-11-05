<?php
// Teste rápido para verificar informações do vendedor
$orcamento_teste = [
    'id' => 123,
    'cliente_nome' => 'Cliente Teste',
    'cliente_email' => 'cliente@teste.com',
    'cliente_telefone' => '(11) 99999-9999',
    'tipo_produto_servico' => 'produto',
    'produto_servico' => 'Produto de Teste',
    'descricao' => 'Descrição do produto',
    'valor_total' => 1000.00,
    'status' => 'pendente',
    'forma_pagamento' => 'a_vista',
    'data_criacao' => date('Y-m-d H:i:s'),
    'data_validade' => date('Y-m-d', strtotime('+5 days')),
    'observacoes' => 'Teste de informações do vendedor',
    'usuario_criador' => 'João Silva Vendedor',
    'itens_orcamento' => json_encode([
        [
            'item' => '001',
            'descricao' => 'Produto Teste',
            'quantidade' => 1,
            'embalagem' => 1,
            'valor_unitario' => 1000.00,
            'valor_total' => 1000.00
        ]
    ])
];

// Simular informações do vendedor
$vendedor_info = [
    'nome' => 'João Silva Vendedor',
    'email' => 'joao.silva@autopel.com.br',
    'telefone' => '(11) 98765-4321'
];

$data_criacao = date('d/m/Y', strtotime($orcamento_teste['data_criacao']));
$data_validade = $orcamento_teste['data_validade'] ? date('d/m/Y', strtotime($orcamento_teste['data_validade'])) : 'Não definida';

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
    <title>Teste - Informações do Vendedor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
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
        .company-details {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
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
        .info-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-title {
            font-weight: bold;
            color: #1a237e;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 5px 0;
            font-size: 14px;
        }
        .highlight {
            background: #e0f2fe;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #1a237e;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Teste - Informações do Vendedor no PDF</h1>
        
        <div class="info-section">
            <div class="info-title">📋 Dados do Orçamento de Teste:</div>
            <div class="info-item"><strong>ID:</strong> <?php echo $orcamento_teste['id']; ?></div>
            <div class="info-item"><strong>Cliente:</strong> <?php echo $orcamento_teste['cliente_nome']; ?></div>
            <div class="info-item"><strong>Vendedor:</strong> <?php echo $orcamento_teste['usuario_criador']; ?></div>
        </div>

        <div class="highlight">
            <div class="info-title">📞 Informações do Vendedor (Simuladas):</div>
            <div class="info-item"><strong>Nome:</strong> <?php echo $vendedor_info['nome']; ?></div>
            <div class="info-item"><strong>Email:</strong> <?php echo $vendedor_info['email']; ?></div>
            <div class="info-item"><strong>Telefone:</strong> <?php echo $vendedor_info['telefone']; ?></div>
        </div>

        <div class="header">
            <div class="logo-section">
                <?php if ($logo_exists): ?>
                    <img src="<?php echo $logo_path; ?>" alt="Autopel" class="logo-img">
                <?php endif; ?>
                <div class="company-info">
                    <div class="company-details">
                        Soluções em Automação Industrial<br>
                        CNPJ: 06.698.091/0001-67<br>
                        <strong>Vendedor:</strong> <?php echo htmlspecialchars($orcamento_teste['usuario_criador']); ?><br>
                        <strong>Tel:</strong> <?php echo $vendedor_info['telefone']; ?> | <strong>Email:</strong> <?php echo $vendedor_info['email']; ?>
                    </div>
                </div>
            </div>
            <div class="orcamento-header">
                <h1 class="orcamento-title">ORÇAMENTO</h1>
                <div class="orcamento-number">Nº <?php echo $orcamento_teste['id']; ?></div>
                <div class="orcamento-date">Data: <?php echo $data_criacao; ?></div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-title">📄 Como deve aparecer no PDF:</div>
            <div class="info-item">✅ <strong>Cabeçalho:</strong> Logo + Informações da empresa + Nome, telefone e email do vendedor</div>
            <div class="info-item">✅ <strong>Rodapé:</strong> Seção "VENDEDOR RESPONSÁVEL" com nome, telefone e email</div>
            <div class="info-item">✅ <strong>CNPJ:</strong> 06.698.091/0001-67 (correto)</div>
        </div>

        <div class="info-section">
            <div class="info-title">🔗 Links para Teste:</div>
            <div class="info-item">
                <a href="teste_pdf_direto.php?id=123" target="_blank">📄 Teste PDF Direto</a> |
                <a href="teste_orcamento_completo.php" target="_blank">📋 Teste Completo</a> |
                <a href="gerar_pdf_orcamento.php?id=123" target="_blank">🎯 PDF Real</a>
            </div>
        </div>
    </div>
</body>
</html>

