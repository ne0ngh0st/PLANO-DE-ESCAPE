<?php
// Incluir configuração usando caminho absoluto
require_once __DIR__ . '/../config/config.php';

// Iniciar sessão apenas se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    // Para PDF, sempre retornar HTML mesmo sem autenticação (para teste)
    // Em produção, você pode querer redirecionar para login
    $usuario = ['nome' => 'Usuário Teste', 'perfil' => 'admin'];
} else {
    $usuario = $_SESSION['usuario'];
}
$orcamento_id = $_GET['id'] ?? '';

if (empty($orcamento_id) || !is_numeric($orcamento_id)) {
    // Retornar página de erro em HTML
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erro - Orçamento</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error { color: #dc2626; font-size: 18px; }
        </style>
    </head>
    <body>
        <h1 class="error">ID do orçamento inválido</h1>
        <p>Por favor, verifique o ID do orçamento e tente novamente.</p>
    </body>
    </html>';
    exit;
}

try {
    // Buscar dados do orçamento
    $sql = "SELECT 
                id, cliente_nome, cliente_email, cliente_telefone,
                tipo_produto_servico, produto_servico, descricao, valor_total, status, forma_pagamento,
                data_criacao, data_validade, observacoes, usuario_criador, codigo_vendedor, itens_orcamento,
                tipo_faturamento, variacao_produtos, prazo_producao, garantia_imagem, texto_importante
            FROM ORCAMENTOS 
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $orcamento_id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orcamento) {
        // Retornar página de erro em HTML
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Erro - Orçamento</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { color: #dc2626; font-size: 18px; }
            </style>
        </head>
        <body>
            <h1 class="error">Orçamento não encontrado</h1>
            <p>O orçamento solicitado não foi encontrado no sistema.</p>
        </body>
        </html>';
        exit;
    }
    
    // Gerar PDF usando HTML simples
    gerarPDFOrcamento($orcamento);
    
} catch (Exception $e) {
    error_log("Erro ao gerar PDF do orçamento: " . $e->getMessage());
    // Retornar página de erro em HTML
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erro - Orçamento</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error { color: #dc2626; font-size: 18px; }
        </style>
    </head>
    <body>
        <h1 class="error">Erro ao gerar PDF</h1>
        <p>Ocorreu um erro ao gerar o PDF do orçamento. Tente novamente mais tarde.</p>
    </body>
    </html>';
}

function gerarPDFOrcamento($orcamento) {
    // Configurar headers para impressão
    header('Content-Type: text/html; charset=utf-8');
    
    // HTML para conversão em PDF
    $html = gerarHTMLOrcamento($orcamento);
    
    // Exibir HTML com script de impressão
    echo $html;
}

function gerarHTMLOrcamento($orcamento) {
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
        // Debug: Log dos dados dos itens
        error_log("PDF - Dados dos itens recebidos: " . $orcamento['itens_orcamento']);
        
        try {
            $itens = json_decode($orcamento['itens_orcamento'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("PDF - Erro JSON: " . json_last_error_msg());
            } else {
                error_log("PDF - Itens decodificados: " . print_r($itens, true));
                
                if (is_array($itens) && count($itens) > 0) {
                    $itens_html = gerarTabelaItens($itens);
                } else {
                    error_log("PDF - Array de itens vazio ou não é array");
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao processar itens do orçamento: " . $e->getMessage());
        }
    } else {
        error_log("PDF - Campo itens_orcamento está vazio");
    }
    
    // Buscar informações do vendedor pelo código
    $codigo_vendedor = $orcamento['codigo_vendedor'] ?? '';
    $vendedor_info = buscarInfoVendedor($codigo_vendedor);
    
    // Caminho do logo (para verificação se existe)
    $logo_path_server = __DIR__ . '/../../assets/img/LOGO AUTOPEL VETOR-01.png';
    $logo_url = '/Site/assets/img/LOGO AUTOPEL VETOR-01.png';
    
    // Verificar se o arquivo existe, caso contrário usar caminho alternativo
    if (!file_exists($logo_path_server)) {
        $logo_path_server = __DIR__ . '/../../assets/img/logo_site.png';
        $logo_url = '/Site/assets/img/logo_site.png';
    }
    $logo_exists = file_exists($logo_path_server);
    
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
                padding: 15px;
                color: #333;
                line-height: 1.4;
                font-size: 11px;
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 2px solid #1a237e;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .logo-section {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .logo-img {
                max-height: 45px;
                max-width: 120px;
            }
            .company-info {
                text-align: left;
            }
            .company-name {
                font-size: 20px;
                font-weight: bold;
                color: #1a237e;
                margin: 0;
            }
            .company-details {
                font-size: 9px;
                color: #666;
                margin-top: 3px;
            }
            .orcamento-header {
                text-align: right;
            }
            .orcamento-title {
                font-size: 24px;
                font-weight: bold;
                color: #1a237e;
                margin: 0;
            }
            .orcamento-number {
                font-size: 16px;
                color: #666;
                margin: 3px 0;
            }
            .orcamento-date {
                font-size: 11px;
                color: #666;
            }
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }
            .info-card {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 10px;
            }
            .card-title {
                font-size: 12px;
                font-weight: bold;
                color: #1a237e;
                margin-bottom: 8px;
                padding-bottom: 3px;
                border-bottom: 1px solid #e2e8f0;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                padding: 2px 0;
                font-size: 10px;
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
                border-radius: 6px;
                padding: 15px;
                margin-bottom: 15px;
            }
            .section-title {
                font-size: 14px;
                font-weight: bold;
                color: #1a237e;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 2px solid #e2e8f0;
            }
            .section-subtitle {
                font-size: 12px;
                font-weight: bold;
                color: #1a237e;
                margin: 15px 0 8px 0;
                padding-bottom: 3px;
                border-bottom: 1px solid #e2e8f0;
            }
            .produto-descricao {
                font-size: 11px;
                line-height: 1.4;
                margin-bottom: 10px;
            }
            .valor-total {
                background: linear-gradient(135deg, #1a237e 0%, #3b82f6 100%);
                color: white;
                padding: 15px;
                border-radius: 6px;
                text-align: center;
                font-size: 20px;
                font-weight: bold;
                margin: 15px 0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .resumo-ipi {
                background: #ffffff;
                border: 2px solid #f59e0b;
                border-radius: 8px;
                padding: 15px;
                margin: 15px 0;
                box-shadow: 0 4px 8px rgba(245, 158, 11, 0.15);
            }
            .resumo-ipi-titulo {
                font-size: 14px;
                font-weight: bold;
                color: #92400e;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 2px solid #f59e0b;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .resumo-ipi-titulo i {
                font-size: 16px;
                color: #f59e0b;
            }
            .resumo-ipi-linha {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                font-size: 12px;
                border-bottom: 1px solid #fef3c7;
            }
            .resumo-ipi-linha:last-of-type {
                border-bottom: none;
            }
            .resumo-ipi-linha-total {
                display: flex;
                justify-content: space-between;
                padding: 12px 0;
                margin-top: 8px;
                border-top: 2px solid #f59e0b;
                font-size: 18px;
                font-weight: bold;
                color: #92400e;
            }
            .resumo-ipi-label {
                color: #374151;
                font-weight: 600;
            }
            .resumo-ipi-valor {
                color: #1f2937;
                font-weight: 500;
            }
            .resumo-ipi-valor-ipi {
                color: #f59e0b;
                font-weight: bold;
                font-size: 13px;
            }
            .resumo-ipi-valor-total {
                color: #92400e;
                font-weight: bold;
            }
            .valor-total-sem-ipi {
                background: linear-gradient(135deg, #1a237e 0%, #3b82f6 100%);
                color: white;
                padding: 15px;
                border-radius: 6px;
                text-align: center;
                font-size: 18px;
                font-weight: bold;
                margin: 15px 0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .observacoes {
                background: #f9fafb;
                border-left: 3px solid #1a237e;
                padding: 10px;
                margin-top: 15px;
                border-radius: 0 6px 6px 0;
            }
            .observacoes h3 {
                margin: 0 0 8px 0;
                color: #1a237e;
                font-size: 12px;
            }
            .outras-informacoes {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 15px;
                margin-top: 15px;
            }
            .outras-informacoes h3 {
                margin: 0 0 12px 0;
                color: #1a237e;
                font-size: 12px;
                font-weight: bold;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 5px;
            }
            .info-lista {
                margin-bottom: 15px;
            }
            .info-item {
                margin-bottom: 8px;
                font-size: 10px;
                line-height: 1.4;
                color: #374151;
            }
            .info-item strong {
                color: #1a237e;
                font-weight: 600;
            }
            .info-importante {
                background: #fef3c7;
                border: 1px solid #f59e0b;
                border-radius: 4px;
                padding: 10px;
                margin-top: 10px;
            }
            .info-importante p {
                margin: 0;
                font-size: 10px;
                color: #92400e;
                line-height: 1.4;
            }
            .info-importante strong {
                color: #92400e;
                font-weight: 600;
            }
            .footer {
                margin-top: 25px;
                padding-top: 15px;
                border-top: 2px solid #e2e8f0;
                text-align: center;
                color: #6b7280;
                font-size: 9px;
            }
            .footer-grid {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 15px;
                margin-bottom: 15px;
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
            .tabela-itens-container {
                margin-top: 1rem;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                overflow: visible;
                position: relative;
                width: 100%;
            }
            .tabela-itens {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.75rem;
                table-layout: fixed;
            }
            .tabela-itens th {
                background: #f8fafc;
                color: #374151;
                font-weight: 600;
                padding: 0.4rem 0.3rem;
                text-align: left;
                border-bottom: 1px solid #e2e8f0;
                font-size: 0.7rem;
            }
            .tabela-itens td {
                padding: 0.3rem;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: middle;
                font-size: 0.7rem;
            }
            .tabela-itens tbody tr:hover {
                background: #f8fafc;
            }
            .tabela-itens tbody tr:last-child td {
                border-bottom: none;
            }
            .tabela-itens th:nth-child(1),
            .tabela-itens td:nth-child(1) {
                width: 12%;
                word-wrap: break-word;
            }
            .tabela-itens th:nth-child(2),
            .tabela-itens td:nth-child(2) {
                width: 35%;
                word-wrap: break-word;
            }
            .tabela-itens th:nth-child(3),
            .tabela-itens td:nth-child(3) {
                width: 10%;
                text-align: center;
            }
            .tabela-itens th:nth-child(4),
            .tabela-itens td:nth-child(4) {
                width: 10%;
                text-align: center;
            }
            .tabela-itens th:nth-child(5),
            .tabela-itens td:nth-child(5) {
                width: 16%;
                text-align: right;
            }
            .tabela-itens th:nth-child(6),
            .tabela-itens td:nth-child(6) {
                width: 17%;
                text-align: right;
            }
            .valor-total-item {
                font-weight: 600;
                color: #059669;
            }
            .total-row {
                background: #f8fafc;
                font-weight: 600;
            }
            .total-row td {
                border-top: 2px solid #e2e8f0;
                padding: 0.4rem 0.3rem;
            }
            .total-label {
                text-align: right;
                color: #374151;
            }
            .total-value {
                color: #059669;
                font-size: 1.1rem;
            }
            @media print {
                body { 
                    margin: 0; 
                    padding: 10px; 
                    font-size: 10px;
                    line-height: 1.3;
                }
                .print-button { display: none; }
                .no-print { display: none; }
                .header { margin-bottom: 15px; padding-bottom: 10px; }
                .info-grid { margin-bottom: 15px; gap: 10px; }
                .produto-section { margin-bottom: 10px; padding: 10px; }
                .valor-total { margin: 10px 0; padding: 10px; font-size: 18px; }
                .resumo-ipi { margin: 10px 0; padding: 12px; border-width: 2px; }
                .resumo-ipi-titulo { font-size: 12px; margin-bottom: 8px; padding-bottom: 6px; }
                .resumo-ipi-linha { padding: 6px 0; font-size: 11px; }
                .resumo-ipi-linha-total { padding: 10px 0; margin-top: 6px; font-size: 16px; }
                .footer { margin-top: 15px; padding-top: 10px; }
                .tabela-itens { font-size: 0.65rem; }
                .tabela-itens th, .tabela-itens td { padding: 0.25rem 0.2rem; }
                .section-title { font-size: 12px; margin-bottom: 8px; }
                .section-subtitle { font-size: 10px; margin: 10px 0 5px 0; }
                .outras-informacoes { margin-top: 10px; padding: 10px; }
                .outras-informacoes h3 { font-size: 11px; margin-bottom: 8px; }
                .info-item { margin-bottom: 5px; font-size: 9px; }
                .info-importante { margin-top: 8px; padding: 8px; }
                .info-importante p { font-size: 9px; }
                .page-break { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <button class="print-button" onclick="window.print()">🖨️ Imprimir/Salvar PDF</button>
        
        <div class="header">
            <div class="logo-section">
                ' . ($logo_exists ? '<img src="' . $logo_url . '" alt="Autopel" class="logo-img">' : '') . '
                <div class="company-info">
                    <div class="company-details">
                        Soluções em Automação Industrial<br>
                        CNPJ: 06.698.091/0001-67<br>
                        Vendedor: ' . htmlspecialchars($vendedor_info['nome'] ?? 'Não informado') . ' (' . htmlspecialchars($vendedor_info['codigo'] ?? 'N/A') . ')<br>
                        ' . ($vendedor_info['telefone'] ? 'Tel: ' . $vendedor_info['telefone'] : 'Tel: Não informado') . ' | ' . ($vendedor_info['email'] ? 'Email: ' . $vendedor_info['email'] : 'Email: Não informado') . '
                    </div>
                </div>
            </div>
            <div class="orcamento-header">
                <h1 class="orcamento-title">ORÇAMENTO</h1>
                <div class="orcamento-number">Nº ' . $orcamento['id'] . '</div>
                <div class="orcamento-date">Data: ' . $data_criacao . '</div>
            </div>
        </div>
        
        <div class="info-grid page-break">
            <div class="info-card">
                <div class="card-title">DADOS DO CLIENTE</div>
                <div class="info-row">
                    <span class="info-label">Nome:</span>
                    <span class="info-value">' . htmlspecialchars($orcamento['cliente_nome'] ?? 'Não informado') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">E-mail:</span>
                    <span class="info-value">' . htmlspecialchars($orcamento['cliente_email'] ?? 'Não informado') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Telefone:</span>
                    <span class="info-value">' . htmlspecialchars($orcamento['cliente_telefone'] ?? 'Não informado') . '</span>
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
                    <span class="info-value">' . htmlspecialchars($vendedor_info['nome'] ?? 'Não informado') . ' (' . htmlspecialchars($vendedor_info['codigo'] ?? 'N/A') . ')</span>
                </div>
            </div>
        </div>
        
        <div class="produto-section page-break">
            <h2 class="section-title">PRODUTO/SERVIÇO</h2>
            <div class="produto-descricao">
                <div class="info-row">
                    <span class="info-label">Tipo:</span>
                    <span class="info-value">' . (($orcamento['tipo_produto_servico'] ?? '') === 'produto' ? 'Produto' : (($orcamento['tipo_produto_servico'] ?? '') === 'servico' ? 'Serviço' : 'Não informado')) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Descrição:</span>
                    <span class="info-value"><strong>' . htmlspecialchars(trim($orcamento['produto_servico'] ?? '') ?: 'Não informado') . '</strong></span>
                </div>
                ' . (!empty(trim($orcamento['descricao'] ?? '')) ? '<div class="info-row"><span class="info-label">Detalhes:</span><span class="info-value">' . htmlspecialchars($orcamento['descricao']) . '</span></div>' : '') . '
            </div>
            
            ' . $itens_html . '
        </div>
        
        ' . calcularEResumoIPI($orcamento) . '
        
        ' . (!empty(trim($orcamento['observacoes'] ?? '')) ? '
        <div class="observacoes">
            <h3>Observações:</h3>
            <p>' . htmlspecialchars($orcamento['observacoes']) . '</p>
        </div>
        ' : '') . '
        
        <div class="outras-informacoes">
            <h3>Outras Informações:</h3>
            <div class="info-lista">
                <div class="info-item">
                    <strong>Variação nos produtos Personalizados:</strong> ' . htmlspecialchars($orcamento['variacao_produtos'] ?? '(+ ou - 10% da quantidade produzida)') . '
                </div>
                <div class="info-item">
                    <strong>Prazo de PRODUÇÃO:</strong> ' . htmlspecialchars($orcamento['prazo_producao'] ?? '10 dias após a aprovação do LAYOUT') . '
                </div>
                <div class="info-item">
                    <strong>Garantia de Imagem:</strong> ' . htmlspecialchars($orcamento['garantia_imagem'] ?? '5 anos') . '
                </div>
                <div class="info-item">
                    <strong>Tipo de Faturamento:</strong> ' . htmlspecialchars($orcamento['tipo_faturamento'] ?? '________________') . '
                </div>
            </div>
            <div class="info-importante">
                <p><strong>IMPORTANTE:</strong> ' . htmlspecialchars($orcamento['texto_importante'] ?? 'Os preços são válidos para 05 dias, podendo ser realinhados até o fechamento do mesmo em detrimento de forte desequilíbrio econômico, e/ou de aumentos acima das expectativas habituais de insumos e matéria prima.') . '</p>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-grid">
                <div class="footer-section">
                    <div class="footer-title">VENDEDOR RESPONSÁVEL</div>
                    <div class="vendedor-info">
                        ' . htmlspecialchars($vendedor_info['nome'] ?? 'Não informado') . '<br>
                        Código: ' . htmlspecialchars($vendedor_info['codigo'] ?? 'N/A') . '<br>
                        ' . ($vendedor_info['telefone'] ?? 'Telefone não informado') . '<br>
                        ' . ($vendedor_info['email'] ?? 'Email não informado') . '
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

function buscarInfoVendedor($codigo_vendedor) {
    global $pdo;
    
    // Se o código do vendedor estiver vazio, retornar informações padrão
    if (empty($codigo_vendedor)) {
        return [
            'nome' => 'Vendedor não informado',
            'email' => 'vendedor@autopel.com.br',
            'telefone' => '(11) 99999-9999',
            'codigo' => 'N/A'
        ];
    }
    
    try {
        // Verificar se o PDO está disponível
        if (!$pdo) {
            throw new Exception("Conexão com banco não disponível");
        }
        
        // Buscar informações do vendedor na tabela USUARIOS pelo código
        $sql = "SELECT NOME_COMPLETO, EMAIL, TELEFONE, COD_VENDEDOR FROM USUARIOS WHERE COD_VENDEDOR = :codigo_vendedor AND ATIVO = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['codigo_vendedor' => $codigo_vendedor]);
        $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vendedor) {
            return [
                'nome' => $vendedor['NOME_COMPLETO'] ?? 'Nome não informado',
                'email' => $vendedor['EMAIL'] ?? 'vendedor@autopel.com.br',
                'telefone' => $vendedor['TELEFONE'] ?? '(11) 99999-9999',
                'codigo' => $vendedor['COD_VENDEDOR'] ?? $codigo_vendedor
            ];
        }
        
        // Se não encontrar, retornar informações básicas
        return [
            'nome' => 'Vendedor ' . $codigo_vendedor,
            'email' => 'vendedor@autopel.com.br',
            'telefone' => '(11) 99999-9999',
            'codigo' => $codigo_vendedor
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar informações do vendedor: " . $e->getMessage());
        // Retornar informações padrão em caso de erro
        return [
            'nome' => 'Vendedor ' . $codigo_vendedor,
            'email' => 'vendedor@autopel.com.br',
            'telefone' => '(11) 99999-9999',
            'codigo' => $codigo_vendedor
        ];
    }
}

function gerarTabelaItens($itens) {
    // Debug: Log dos itens recebidos
    error_log("gerarTabelaItens - Itens recebidos: " . print_r($itens, true));
    
    if (empty($itens) || !is_array($itens)) {
        error_log("gerarTabelaItens - Itens vazios ou não é array");
        return '';
    }
    
    $html = '
        <h3 class="section-subtitle">ITENS DO ORÇAMENTO</h3>
        <div class="tabela-itens-container">
            <table class="tabela-itens">
                <thead>
                    <tr>
                        <th>ITEM</th>
                        <th>DESCRIÇÃO</th>
                        <th>QTD</th>
                        <th>EMBALAGEM</th>
                        <th>VLR UNITÁRIO</th>
                        <th>VLR TOTAL</th>
                    </tr>
                </thead>
                <tbody>';
    
    $total_geral = 0;
    
    foreach ($itens as $index => $item) {
        // Debug: Log de cada item
        error_log("gerarTabelaItens - Processando item $index: " . print_r($item, true));
        
        $valor_unitario = floatval($item['valor_unitario'] ?? 0);
        $quantidade = floatval($item['quantidade'] ?? 0);
        $valor_total = floatval($item['valor_total'] ?? 0);
        
        $total_geral += $valor_total;
        
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['item'] ?? '-') . '</td>
                        <td>' . htmlspecialchars($item['descricao'] ?? '-') . '</td>
                        <td>' . number_format($quantidade, 0, ',', '.') . '</td>
                        <td>' . number_format($item['embalagem'] ?? 0, 0, ',', '.') . '</td>
                        <td>R$ ' . number_format($valor_unitario, 2, ',', '.') . '</td>
                        <td class="valor-total-item">R$ ' . number_format($valor_total, 2, ',', '.') . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="total-label">TOTAL GERAL:</td>
                        <td class="total-value">R$ ' . number_format($total_geral, 2, ',', '.') . '</td>
                    </tr>
                </tfoot>
            </table>
        </div>';
    
    return $html;
}

function calcularEResumoIPI($orcamento) {
    $tipo_faturamento = $orcamento['tipo_faturamento'] ?? '';
    $valor_total = floatval($orcamento['valor_total'] ?? 0);
    
    // Se for serviço, mostrar apenas o valor total
    if ($tipo_faturamento !== 'produto') {
        return '
        <div class="valor-total">
            VALOR TOTAL: R$ ' . number_format($valor_total, 2, ',', '.') . '
        </div>';
    }
    
    // Calcular IPI para produtos (3,25%)
    $percentual_ipi = 3.25;
    
    // O valor_total já vem com IPI incluído, então precisamos calcular o valor base
    // Se o valor_total contém IPI: valor_total = valor_base * 1.0325
    // valor_base = valor_total / 1.0325
    $valor_base = $valor_total / (1 + ($percentual_ipi / 100));
    $valor_ipi = $valor_total - $valor_base;
    
    // Gerar HTML destacado do IPI
    return '
        <div class="resumo-ipi">
            <div class="resumo-ipi-titulo">
                <i class="fas fa-calculator"></i>
                <span>CÁLCULO DE IMPOSTOS (IPI)</span>
            </div>
            <div class="resumo-ipi-linha">
                <span class="resumo-ipi-label">Valor Base (sem IPI):</span>
                <span class="resumo-ipi-valor">R$ ' . number_format($valor_base, 2, ',', '.') . '</span>
            </div>
            <div class="resumo-ipi-linha">
                <span class="resumo-ipi-label">IPI (' . $percentual_ipi . '%):</span>
                <span class="resumo-ipi-valor-ipi">R$ ' . number_format($valor_ipi, 2, ',', '.') . '</span>
            </div>
            <div class="resumo-ipi-linha-total">
                <span class="resumo-ipi-label">VALOR TOTAL (com IPI):</span>
                <span class="resumo-ipi-valor-total">R$ ' . number_format($valor_total, 2, ',', '.') . '</span>
            </div>
        </div>
        <div class="valor-total">
            VALOR TOTAL DO ORÇAMENTO: R$ ' . number_format($valor_total, 2, ',', '.') . '
        </div>';
}
?>