<?php
// Tabela otimizada para diretores/admins
if (empty($clientes)): ?>
    <div class="alert alert-info text-center">
        <i class="fas fa-info-circle"></i>
        <strong>Nenhum cliente encontrado</strong> com os filtros aplicados.
    </div>
<?php else: ?>
    <!-- Informações da página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <span class="badge bg-primary">
                Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
            </span>
            <span class="badge bg-secondary ms-2">
                <?php echo number_format($total_registros); ?> clientes no total
            </span>
        </div>
        <div>
            <a href="carteira.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Versão Original
            </a>
        </div>
    </div>
    
    <!-- Tabela otimizada -->
    <div class="table-responsive">
        <table class="table table-striped table-hover table-optimized">
            <thead class="table-dark">
                <tr>
                    <th style="width: 8%;">CNPJ</th>
                    <th style="width: 20%;">Cliente</th>
                    <th style="width: 12%;">Estado</th>
                    <th style="width: 10%;">Vendedor</th>
                    <th style="width: 8%;">Pedidos</th>
                    <th style="width: 10%;">Valor Total</th>
                    <th style="width: 8%;">Última Compra</th>
                    <th style="width: 8%;">Faturamento Mês</th>
                    <th style="width: 8%;">Telefone</th>
                    <th style="width: 8%;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($cliente['cnpj_representativo']); ?>
                            </small>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($cliente['cliente']); ?></strong>
                                <?php if (!empty($cliente['nome_fantasia']) && $cliente['nome_fantasia'] != $cliente['cliente']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($cliente['nome_fantasia']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo htmlspecialchars($cliente['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div>
                                <small class="text-muted"><?php echo htmlspecialchars($cliente['cod_vendedor']); ?></small><br>
                                <strong><?php echo htmlspecialchars($cliente['nome_vendedor']); ?></strong>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary">
                                <?php echo number_format($cliente['total_pedidos']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <strong>R$ <?php echo number_format($cliente['valor_total'], 2, ',', '.'); ?></strong>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($cliente['ultima_compra'])): ?>
                                <small><?php echo htmlspecialchars($cliente['ultima_compra']); ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($cliente['faturamento_mes'] > 0): ?>
                                <span class="text-success">
                                    <strong>R$ <?php echo number_format($cliente['faturamento_mes'], 2, ',', '.'); ?></strong>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">R$ 0,00</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cliente['telefone'] != 'N/A'): ?>
                                <small><?php echo htmlspecialchars($cliente['telefone']); ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="verDetalhes('<?php echo htmlspecialchars($cliente['raiz_cnpj']); ?>')"
                                        title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" 
                                        onclick="iniciarLigacao('<?php echo htmlspecialchars($cliente['raiz_cnpj']); ?>', '<?php echo htmlspecialchars($cliente['cliente']); ?>')"
                                        title="Ligar">
                                    <i class="fas fa-phone"></i>
                                </button>
                                <?php if (!empty($cliente['telefone']) && $cliente['telefone'] != 'N/A'): ?>
                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                            onclick="agendarLigacao('<?php echo htmlspecialchars($cliente['raiz_cnpj']); ?>', '<?php echo htmlspecialchars($cliente['cliente']); ?>')"
                                            title="Agendar">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginação otimizada -->
    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginação da carteira">
            <ul class="pagination justify-content-center">
                <!-- Primeira página -->
                <?php if ($pagina_atual > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- Página anterior -->
                <?php if ($pagina_atual > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- Páginas próximas -->
                <?php
                $inicio = max(1, $pagina_atual - 2);
                $fim = min($total_paginas, $pagina_atual + 2);
                
                for ($i = $inicio; $i <= $fim; $i++):
                ?>
                    <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <!-- Próxima página -->
                <?php if ($pagina_atual < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- Última página -->
                <?php if ($pagina_atual < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- Informações da paginação -->
        <div class="text-center text-muted">
            <small>
                Mostrando <?php echo number_format(($pagina_atual - 1) * $itens_por_pagina + 1); ?> a 
                <?php echo number_format(min($pagina_atual * $itens_por_pagina, $total_registros)); ?> 
                de <?php echo number_format($total_registros); ?> clientes
            </small>
        </div>
    <?php endif; ?>
    
    <!-- Resumo do faturamento -->
    <?php if ($total_faturamento_mes > 0): ?>
        <div class="alert alert-success mt-3">
            <i class="fas fa-chart-line"></i>
            <strong>Faturamento Total do Período:</strong> 
            R$ <?php echo number_format($total_faturamento_mes, 2, ',', '.'); ?>
        </div>
    <?php endif; ?>
    
<?php endif; ?>

<script>
// Funções otimizadas para ações
function verDetalhes(raizCnpj) {
    // Implementar modal de detalhes otimizado
    console.log('Ver detalhes:', raizCnpj);
    // TODO: Implementar modal de detalhes
}

function iniciarLigacao(raizCnpj, nomeCliente) {
    // Implementar sistema de ligação otimizado
    console.log('Iniciar ligação:', raizCnpj, nomeCliente);
    // TODO: Implementar sistema de ligação
}

function agendarLigacao(raizCnpj, nomeCliente) {
    // Implementar agendamento otimizado
    console.log('Agendar ligação:', raizCnpj, nomeCliente);
    // TODO: Implementar agendamento
}

// Otimizações de performance
document.addEventListener('DOMContentLoaded', function() {
    // Lazy loading para tabela
    const table = document.querySelector('.table-optimized');
    if (table) {
        // Adicionar classe para animação suave
        table.style.opacity = '0';
        table.style.transition = 'opacity 0.3s ease-in-out';
        
        setTimeout(() => {
            table.style.opacity = '1';
        }, 100);
    }
    
    // Preload de páginas próximas
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            const href = this.href;
            if (href && !document.querySelector(`link[href="${href}"]`)) {
                const preloadLink = document.createElement('link');
                preloadLink.rel = 'prefetch';
                preloadLink.href = href;
                document.head.appendChild(preloadLink);
            }
        });
    });
});
</script>

