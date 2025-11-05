<?php
/**
 * Helper para caminhos - define variável JavaScript global BASE_PATH
 * Use este arquivo em todas as páginas que precisam de caminhos dinâmicos
 */
if (!function_exists('base_url')) {
    require_once __DIR__ . '/../config/config.php';
}
?>
<script>
    window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
    window.baseUrl = function(path) {
        path = path || '';
        path = path.startsWith('/') ? path.substring(1) : path;
        return window.BASE_PATH + (path ? '/' + path : '');
    };
    // Alias para compatibilidade
    window.getBasePath = function() {
        return window.BASE_PATH;
    };
</script>



