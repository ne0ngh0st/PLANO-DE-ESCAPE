<?php
/**
 * Script de teste para verificar se os caminhos estão corretos
 * Acesse: localhost/Site/testar-caminhos.php ou seu-domínio.com/testar-caminhos.php
 */

// Carregar config
require_once __DIR__ . '/includes/config/config.php';

// Verificar se a função base_url existe
$base_url_exists = function_exists('base_url');
$site_base_path = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : 'NÃO DEFINIDO';

// Detectar ambiente
$host = $_SERVER['HTTP_HOST'] ?? 'N/A';
$script_name = $_SERVER['SCRIPT_NAME'] ?? 'N/A';
$request_uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'N/A';

$is_localhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
$expected_base = $is_localhost ? '/Site/' : '/';
$detected_base = detectarCaminhoBase();

// Testes de caminhos
$test_paths = [
    'assets/css/estilo.css' => ['type' => 'file', 'description' => 'Arquivo CSS'],
    'assets/js/app.js' => ['type' => 'file', 'description' => 'Arquivo JS (pode não existir)'],
    'assets/img/logo_site.png' => ['type' => 'file', 'description' => 'Imagem'],
    'home' => ['type' => 'url', 'description' => 'URL limpa (roteada via .htaccess)'],
    'leads' => ['type' => 'url', 'description' => 'URL limpa (roteada via .htaccess)'],
    'includes/api/buscar.php' => ['type' => 'file', 'description' => 'API (permitida via .htaccess)'],
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Caminhos - Autopel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 2rem;
        }
        h1 {
            color: #333;
            margin-bottom: 2rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 1rem;
        }
        .section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        .status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #333;
            word-break: break-all;
        }
        .test-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .test-table th,
        .test-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .test-table th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        .test-table tr:hover {
            background: #f8f9fa;
        }
        .path-result {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #495057;
        }
        .check-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .check-link:hover {
            text-decoration: underline;
        }
        .summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .summary-card {
            flex: 1;
            min-width: 200px;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-top: 4px solid #667eea;
        }
        .summary-card h3 {
            color: #667eea;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        .summary-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de Caminhos - Sistema Autopel</h1>
        
        <!-- Resumo -->
        <div class="summary">
            <div class="summary-card">
                <h3>Ambiente Detectado</h3>
                <div class="number" style="font-size: 1.2rem;">
                    <?php echo $is_localhost ? '🖥️ Localhost' : '🌐 Produção'; ?>
                </div>
            </div>
            <div class="summary-card">
                <h3>Caminho Base</h3>
                <div class="number" style="font-size: 1.2rem;">
                    <?php echo htmlspecialchars($detected_base); ?>
                </div>
            </div>
            <div class="summary-card">
                <h3>Função base_url()</h3>
                <div class="number" style="font-size: 1.2rem;">
                    <?php echo $base_url_exists ? '✅ Existe' : '❌ Não encontrada'; ?>
                </div>
            </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="section">
            <h2>📋 Informações do Sistema</h2>
            <div class="info-grid">
                <div class="info-label">Host:</div>
                <div class="info-value"><?php echo htmlspecialchars($host); ?></div>
                
                <div class="info-label">Script Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($script_name); ?></div>
                
                <div class="info-label">Request URI:</div>
                <div class="info-value"><?php echo htmlspecialchars($request_uri); ?></div>
                
                <div class="info-label">Document Root:</div>
                <div class="info-value"><?php echo htmlspecialchars($document_root); ?></div>
                
                <div class="info-label">SITE_BASE_PATH:</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($site_base_path); ?>
                    <?php if ($site_base_path === $expected_base): ?>
                        <span class="status success">✓ Correto</span>
                    <?php else: ?>
                        <span class="status error">✗ Esperado: <?php echo $expected_base; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="info-label">Caminho Esperado:</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($expected_base); ?>
                    <span class="status <?php echo $detected_base === $expected_base ? 'success' : 'warning'; ?>">
                        <?php echo $detected_base === $expected_base ? '✓ Detectado corretamente' : '⚠ Diferente do esperado'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Testes de Caminhos -->
        <div class="section">
            <h2>🧪 Testes de Caminhos com base_url()</h2>
            <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
                <strong>ℹ️ Nota Importante:</strong> URLs como <code>/Site/home</code> e <code>/Site/leads</code> são <strong>rotas do .htaccess</strong>, não arquivos físicos. Elas funcionam corretamente mesmo mostrando "⚠ Arquivo não encontrado" - isso é normal! O importante é que <code>base_url()</code> está gerando os caminhos corretos.
            </div>
            <table class="test-table">
                <thead>
                    <tr>
                        <th>Path Original</th>
                        <th>Descrição</th>
                        <th>Resultado base_url()</th>
                        <th>Status</th>
                        <th>Teste</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($test_paths as $test_path => $info): ?>
                        <?php
                        $generated_url = $base_url_exists ? base_url($test_path) : 'ERRO: Função não existe';
                        $file_exists = false;
                        $full_path = '';
                        $is_url_route = ($info['type'] === 'url');
                        
                        if ($base_url_exists && !$is_url_route) {
                            // Verificar se o arquivo existe (apenas para arquivos físicos)
                            $full_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $generated_url;
                            $file_exists = file_exists($full_path);
                        }
                        
                        // Status baseado no tipo
                        if (!$base_url_exists) {
                            $status_class = 'error';
                            $status_text = '✗ Erro: Função não existe';
                        } elseif ($is_url_route) {
                            $status_class = 'success';
                            $status_text = '✓ URL limpa (funciona via .htaccess)';
                        } elseif ($file_exists) {
                            $status_class = 'success';
                            $status_text = '✓ Arquivo existe';
                        } else {
                            $status_class = 'warning';
                            $status_text = '⚠ Arquivo não encontrado';
                        }
                        ?>
                        <tr>
                            <td class="path-result"><?php echo htmlspecialchars($test_path); ?></td>
                            <td style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($info['description']); ?></td>
                            <td class="path-result"><?php echo htmlspecialchars($generated_url); ?></td>
                            <td>
                                <span class="status <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_url_route || $file_exists): ?>
                                    <a href="<?php echo htmlspecialchars($generated_url); ?>" 
                                       target="_blank" 
                                       class="check-link">
                                        Testar →
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 0.85rem;">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Teste JavaScript -->
        <div class="section">
            <h2>🌐 Teste JavaScript</h2>
            <div id="js-test-results" style="margin-top: 1rem;">
                <p>Carregando teste JavaScript...</p>
            </div>
        </div>

        <!-- Links de Navegação -->
        <div class="section">
            <h2>🔗 Links de Teste</h2>
            <p>Teste se os seguintes links funcionam corretamente:</p>
            <div style="margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="<?php echo base_url(''); ?>" class="btn">Home (Index)</a>
                <a href="<?php echo base_url('home'); ?>" class="btn">Home</a>
                <a href="<?php echo base_url('leads'); ?>" class="btn">Leads</a>
                <a href="<?php echo base_url('cadastro'); ?>" class="btn">Cadastro</a>
                <a href="<?php echo base_url('assets/css/estilo.css'); ?>" class="btn">CSS Estilo</a>
            </div>
        </div>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #dee2e6; text-align: center; color: #666;">
            <p><strong>Nota:</strong> Este arquivo pode ser removido em produção após os testes.</p>
            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                Acesse: <code><?php echo htmlspecialchars($script_name); ?></code>
            </p>
        </div>
    </div>

    <!-- Helper JavaScript -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };

        // Teste JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const resultsDiv = document.getElementById('js-test-results');
            const tests = [
                { name: 'window.BASE_PATH', value: window.BASE_PATH },
                { name: 'baseUrl("assets/css/estilo.css")', value: window.baseUrl('assets/css/estilo.css') },
                { name: 'baseUrl("home")', value: window.baseUrl('home') },
                { name: 'baseUrl("")', value: window.baseUrl('') }
            ];

            let html = '<table class="test-table"><thead><tr><th>Teste</th><th>Valor</th><th>Status</th></tr></thead><tbody>';
            
            tests.forEach(test => {
                const isValid = test.value && test.value.length > 0;
                html += `
                    <tr>
                        <td><strong>${test.name}</strong></td>
                        <td class="path-result">${test.value}</td>
                        <td>
                            <span class="status ${isValid ? 'success' : 'error'}">
                                ${isValid ? '✓ OK' : '✗ Erro'}
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
            resultsDiv.innerHTML = html;
        });
    </script>
</body>
</html>

