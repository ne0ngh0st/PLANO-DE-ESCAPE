<?php
// Teste direto do PDF - sem autenticação
$orcamento_id = $_GET['id'] ?? '1';

// Dados de teste
$orcamento = [
    'id' => $orcamento_id,
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

// Incluir a função de geração de PDF
require_once 'gerar_pdf_orcamento.php';

// Gerar o PDF
gerarPDFOrcamento($orcamento);
?>
