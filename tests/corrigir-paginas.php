<?php
/**
 * Script para corrigir caminhos hardcoded em todas as páginas
 * Este script faz substituições em lote nos arquivos PHP da pasta pages/
 * 
 * ATENÇÃO: Este é um script de correção em lote. Use com cuidado!
 * Sempre faça backup antes de executar.
 */

$baseDir = __DIR__ . '/pages';

// Padrões de substituição
$patterns = [
    // CSS e Assets
    '/href="\/Site\/assets\//' => 'href="<?php echo base_url(\'',
    '/src="\/Site\/assets\//' => 'src="<?php echo base_url(\'',
    '/href="\/Site\/includes\//' => 'href="<?php echo base_url(\'',
    '/src="\/Site\/includes\//' => 'src="<?php echo base_url(\'',
    
    // Includes PHP
    '/\$_SERVER\[\'DOCUMENT_ROOT\'\]\s*\.\s*[\'"]\/Site\//' => 'dirname(__DIR__, 2) . \'/',
    
    // Headers Location
    '/header\([\'"]Location:\s*\/Site\//' => 'header(\'Location: \' . base_url(\'',
    
    // Fechamento de base_url nos href/src
    '/href="\<\?php echo base_url\(\'assets\/([^\']+)\'\);/' => 'href="<?php echo base_url(\'assets/$1\'); ?>',
    '/src="\<\?php echo base_url\(\'assets\/([^\']+)\'\);/' => 'src="<?php echo base_url(\'assets/$1\'); ?>',
];

function processFile($filePath) {
    global $patterns;
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Adicionar helper JavaScript se ainda não existe
    if (strpos($content, 'window.BASE_PATH') === false && strpos($content, '<head>') !== false) {
        $jsHelper = '    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = \'<?php echo defined(\'SITE_BASE_PATH\') ? rtrim(SITE_BASE_PATH, \'/\') : \'/Site\'; ?>\';
        window.baseUrl = function(path) {
            path = path || \'\';
            path = path.startsWith(\'/\') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? \'/\' + path : \'\');
        };
    </script>
    ';
        $content = str_replace('<head>', '<head>' . "\n" . $jsHelper, $content);
    }
    
    // Aplicar padrões
    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    // Corrigir fechamento de base_url
    $content = preg_replace('/href="\<\?php echo base_url\(\'([^\']+)\'\);/', 'href="<?php echo base_url(\'$1\'); ?>', $content);
    $content = preg_replace('/src="\<\?php echo base_url\(\'([^\']+)\'\);/', 'src="<?php echo base_url(\'$1\'); ?>', $content);
    
    // Corrigir fetch('/Site/
    $content = preg_replace('/fetch\([\'"]\/Site\//', 'fetch(window.baseUrl(\'', $content);
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return true;
    }
    return false;
}

echo "Script de correção de caminhos\n";
echo "===============================\n\n";

$filesProcessed = 0;
$filesModified = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filesProcessed++;
        $relativePath = str_replace(__DIR__ . '\\', '', $file->getPathname());
        
        if (processFile($file->getPathname())) {
            $filesModified++;
            echo "✓ Corrigido: $relativePath\n";
        } else {
            echo "- Sem alterações: $relativePath\n";
        }
    }
}

echo "\n";
echo "Processados: $filesProcessed arquivos\n";
echo "Modificados: $filesModified arquivos\n";
echo "\nConcluído!\n";
?>

