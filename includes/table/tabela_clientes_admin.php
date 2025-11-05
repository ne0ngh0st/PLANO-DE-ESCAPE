<?php 
// DEBUG: Verificar se a variável total_faturamento_mes está definida
error_log("DEBUG TABELA ADMIN - total_faturamento_mes: " . (isset($total_faturamento_mes) ? $total_faturamento_mes : 'NÃO DEFINIDA'));
error_log("DEBUG TABELA ADMIN - total_faturamento_mes >= 0: " . (isset($total_faturamento_mes) && $total_faturamento_mes >= 0 ? 'SIM' : 'NÃO'));
?>
<div id="clientesListContainer">
<?php if (empty($clientes)): ?>
<div class="no-clientes">
    <i class="fas fa-users"></i>
    <h3>Nenhum cliente encontrado</h3>
    <p>Não há clientes disponíveis na sua carteira no momento.</p>
</div>
<?php else: ?>
<!-- Tabela de clientes -->
<?php if (!empty($supervisor_selecionado)): ?>
<div class="visao-indicator-table">
    <i class="fas fa-eye"></i>
    <span>Visualizando clientes da equipe selecionada</span>
</div>
<?php endif; ?>

<!-- Layout Desktop - Tabela -->
<div class="table-responsive" style="max-width: 100%; width: 100%; overflow-x: auto;">
    <div class="table-container" style="max-width: 100%; width: 100%;">
        <table class="clientes-table" id="clientesTable" style="width: 100%; min-width: 1400px;">
        <thead>
            <tr>
                <th style="width: 20%;">Cliente</th>
                <th style="width: 12%;">CNPJ</th>
                <th style="width: 5%;">UF</th>
                <th style="width: 15%;">Segmento</th>
                <th style="width: 12%;">Vendedor</th>
                <th style="width: 12%;">Supervisor</th>
                <th style="width: 7%;">Status</th>
                <th style="width: 8%;">Últ. Compra</th>
                <th style="width: 8%;">Últ. Ligação</th>
                <th style="width: 8%;" data-col="faturamento">
                    <?php 
                    // Determinar título da coluna baseado no filtro
                    if (isset($usar_acumulado_ano) && $usar_acumulado_ano) {
                        echo 'Fat. Ano';
                    } else {
                        echo 'Fat. Mês';
                    }
                    ?>
                </th>
                <th style="width: 8%;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cliente): 
                // Determinar status do cliente
                $is_ativo = false;
                $is_inativando = false;
                $is_inativo = false;
                $dias_inativo = 0;
                if (!empty($cliente['ultima_compra'])) {
                    // Converter data brasileira para timestamp
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $cliente['ultima_compra'])) {
                        $data_ultima = DateTime::createFromFormat('d/m/Y', $cliente['ultima_compra'])->getTimestamp();
                    } else {
                        $data_ultima = strtotime($cliente['ultima_compra']);
                    }
                    $data_atual = time();
                    $dias_inativo = round(($data_atual - $data_ultima) / (60 * 60 * 24));
                    $is_ativo = $dias_inativo <= 290;
                    $is_inativando = $dias_inativo > 290 && $dias_inativo <= 365;
                    $is_inativo = $dias_inativo > 365;
                } else {
                    $is_inativo = true;
                }
                
                // Determinar status e cor
                if ($is_ativo) {
                    $status_class = 'status-ativo';
                    $status_text = 'Ativo';
                } elseif ($is_inativando) {
                    $status_class = 'status-inativando';
                    $status_text = 'Ativos...';
                } elseif ($is_inativo) {
                    $status_class = 'status-inativo';
                    $status_text = 'Inativo';
                } else {
                    $status_class = 'status-sem-compras';
                    $status_text = 'Sem compras';
                }
            ?>
                <tr class="<?php echo $is_inativo ? 'cliente-inativo' : ($is_inativando ? 'cliente-inativando' : ''); ?>" data-cliente="<?php echo htmlspecialchars($cliente['cliente'] ?? ''); ?>" data-cnpj="<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? ''); ?>" data-vendedor="<?php echo htmlspecialchars($cliente['nome_vendedor'] ?? ''); ?>" data-cliente-id="<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>">
                    <td>
                        <div class="cliente-nome">
                            <?php echo htmlspecialchars($cliente['cliente'] ?? ''); ?>
                        </div>
                        <div class="cliente-fantasia"><?php echo htmlspecialchars($cliente['nome_fantasia'] ?? ''); ?></div>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? ''); ?>
                        <?php if (($cliente['total_cnpjs_diferentes'] ?? 0) > 1): ?>
                            <div class="cnpj-unificado-info">
                                <i class="fas fa-link"></i> 
                                <?php echo $cliente['total_cnpjs_diferentes']; ?> CNPJs unificados
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="estado-badge"><?php echo htmlspecialchars($cliente['estado'] ?? ''); ?></span>
                    </td>
                    <td>
                        <div class="segmento-info"><?php echo htmlspecialchars($cliente['segmento_atuacao'] ?? ''); ?></div>
                    </td>
                    <td>
                        <div class="vendor-info">
                            <span class="vendor-name"><?php echo htmlspecialchars($cliente['nome_vendedor'] ?? ''); ?></span>
                            <?php if (!empty($cliente['cod_vendedor'])): ?>
                                <span class="vendor-code">Cód: <?php echo htmlspecialchars($cliente['cod_vendedor']); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="supervisor-info"><?php echo htmlspecialchars($cliente['nome_supervisor'] ?? ''); ?></span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        if (!empty($cliente['ultima_compra'])) {
                            // Verificar se a data já está no formato brasileiro
                            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $cliente['ultima_compra'])) {
                                echo '<span class="data-ultima-compra">' . $cliente['ultima_compra'] . '</span>';
                            } else {
                                // Converter para formato brasileiro se necessário
                                echo '<span class="data-ultima-compra">' . date('d/m/Y', strtotime($cliente['ultima_compra'])) . '</span>';
                            }
                        } else {
                            echo '<span class="data-ultima-compra">-</span>';
                        }
                        ?>
                    </td>

                    <td>
                        <?php 
                        if (!empty($cliente['ultima_ligacao'])) {
                            echo '<span class="data-ultima-ligacao">' . date('d/m/Y H:i', strtotime($cliente['ultima_ligacao'])) . '</span>';
                        } else {
                            echo '<span class="data-ultima-ligacao">-</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        $fat_mes = floatval($cliente['faturamento_mes'] ?? 0);
                        echo 'R$ ' . number_format($fat_mes, 2, ',', '.');
                        ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <?php
                            // Verificar se a data de último faturamento é anterior a 1º de janeiro de 2025
                            $data_limite = '01/01/2025'; // 1º de janeiro de 2025
                            $ultima_compra = $cliente['ultima_compra'] ?? '';
                            $tem_dados_recentes = false;
                            
                            if (!empty($ultima_compra)) {
                                // Converter data brasileira para timestamp
                                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $ultima_compra)) {
                                    $data_ultima = DateTime::createFromFormat('d/m/Y', $ultima_compra);
                                    $data_limite_obj = DateTime::createFromFormat('d/m/Y', $data_limite);
                                    $tem_dados_recentes = $data_ultima >= $data_limite_obj;
                                } else {
                                    // Se não estiver no formato brasileiro, tentar formato americano
                                    $data_ultima = DateTime::createFromFormat('Y-m-d', $ultima_compra);
                                    $data_limite_obj = DateTime::createFromFormat('d/m/Y', $data_limite);
                                    $tem_dados_recentes = $data_ultima >= $data_limite_obj;
                                }
                            }
                            
                            // Preparar dados do cliente para ligação
                            $clienteCnpj = $cliente['cnpj_representativo'] ?? '';
                            $clienteNome = $cliente['cliente'] ?? 'Cliente sem nome';
                            $clienteTelefone = $cliente['telefone'] ?? '';
                            
                            // Validar se os dados estão completos
                            $cnpjValido = !empty($clienteCnpj) && $clienteCnpj !== '-';
                            $nomeValido = !empty($clienteNome) && $clienteNome !== 'Cliente sem nome';
                            $telefoneValido = !empty($clienteTelefone) && $clienteTelefone !== 'N/A' && $clienteTelefone !== '(-) -';
                            
                            $dadosCompletos = $cnpjValido && $nomeValido && $telefoneValido;
                            
                            // Verificar se pode ligar
                            $numeroLimpo = preg_replace('/\D/', '', $clienteTelefone);
                            $podeLigar = !empty($clienteTelefone) && $clienteTelefone !== 'N/A' && strlen($numeroLimpo) >= 10;
                            ?>
                            
                            <!-- Coluna Verde - Visualização e Contato -->
                            <div class="actions-column actions-green">
                                <?php if ($tem_dados_recentes): ?>
                                <a href="/Site/includes/api/detalhes_cliente.php?cnpj=<?php echo urlencode($cliente['cnpj_representativo']); ?>&from_optimized=<?php echo (basename($_SERVER['PHP_SELF']) === 'carteira_admin_diretor.php') ? '1' : '0'; ?>" 
                                   class="btn btn-sm btn-eye" title="Ver detalhes">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php else: ?>
                                <span class="btn btn-eye btn-sm disabled" title="DT_FAT < 1º de janeiro de 2025 - botão desabilitado" style="cursor: not-allowed;">
                                    <i class="fas fa-eye-slash"></i>
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($podeLigar && $dadosCompletos): ?>
                                    <button class="btn btn-sm btn-ligar" onclick="selecionarTipoContato('<?php echo htmlspecialchars($clienteCnpj, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($clienteNome, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($clienteTelefone, ENT_QUOTES); ?>')" title="Iniciar Contato">
                                        <i class="fas fa-phone"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-ligar disabled" style="cursor: not-allowed;" title="<?php echo !$podeLigar ? 'Telefone não disponível' : 'Dados do cliente incompletos'; ?>">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                <?php endif; ?>

                                <button class="btn btn-success btn-sm" title="Agendar ligação"
                                        onclick="agendarLigacao('<?php echo htmlspecialchars($cliente['raiz_cnpj'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                                    <i class="fas fa-calendar-plus"></i>
                                </button>
                            </div>
                            
                            <!-- Coluna Amarela - Observações e Exportação -->
                            <div class="actions-column actions-yellow">
                                <button class="btn btn-warning btn-sm" title="Observações" 
                                        onclick="abrirObservacoes('cliente', '<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                                    <i class="fas fa-comment"></i>
                                </button>
                                
                            </div>
                            
                            <!-- Coluna Vermelha - Edição e Exclusão -->
                            <div class="actions-column actions-red">
                                <button class="btn btn-danger btn-sm" title="Editar Cliente" 
                                        onclick="abrirEdicaoCliente('<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>'); return false;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <a href="#" class="btn btn-danger btn-sm" title="Excluir Cliente" 
                                   onclick="confirmarExclusaoCliente('<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
    </div>
</div>

<!-- Layout Mobile - Cards -->
<div class="mobile-layout-indicator">
    <i class="fas fa-mobile-alt"></i>
    Visualização otimizada para dispositivos móveis
</div>
<div class="mobile-cards-container">
    <?php foreach ($clientes as $cliente): 
        // Determinar status do cliente (mesma lógica da tabela)
        $is_ativo = false;
        $is_inativando = false;
        $is_inativo = false;
        $dias_inativo = 0;
        if (!empty($cliente['ultima_compra'])) {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $cliente['ultima_compra'])) {
                $data_ultima = DateTime::createFromFormat('d/m/Y', $cliente['ultima_compra'])->getTimestamp();
            } else {
                $data_ultima = strtotime($cliente['ultima_compra']);
            }
            $data_atual = time();
            $dias_inativo = round(($data_atual - $data_ultima) / (60 * 60 * 24));
            $is_ativo = $dias_inativo <= 290;
            $is_inativando = $dias_inativo > 290 && $dias_inativo <= 365;
            $is_inativo = $dias_inativo > 365;
        } else {
            $is_inativo = true;
        }
        
        // Determinar status e cor
        if ($is_ativo) {
            $status_class = 'status-ativo';
            $status_text = 'Ativo';
        } elseif ($is_inativando) {
            $status_class = 'status-inativando';
            $status_text = 'Ativos...';
        } elseif ($is_inativo) {
            $status_class = 'status-inativo';
            $status_text = 'Inativo';
        } else {
            $status_class = 'status-sem-compras';
            $status_text = 'Sem compras';
        }
        
        // Preparar dados para ações
        $clienteCnpj = $cliente['cnpj_representativo'] ?? '';
        $clienteNome = $cliente['cliente'] ?? 'Cliente sem nome';
        $clienteTelefone = $cliente['telefone'] ?? '';
        
        $cnpjValido = !empty($clienteCnpj) && $clienteCnpj !== '-';
        $nomeValido = !empty($clienteNome) && $clienteNome !== 'Cliente sem nome';
        $telefoneValido = !empty($clienteTelefone) && $clienteTelefone !== 'N/A' && $clienteTelefone !== '(-) -';
        $dadosCompletos = $cnpjValido && $nomeValido && $telefoneValido;
        
        $numeroLimpo = preg_replace('/\D/', '', $clienteTelefone);
        $podeLigar = !empty($clienteTelefone) && $clienteTelefone !== 'N/A' && strlen($numeroLimpo) >= 10;
        
        // Verificar se tem dados recentes
        $data_limite = '01/01/2025';
        $ultima_compra = $cliente['ultima_compra'] ?? '';
        $tem_dados_recentes = false;
        
        if (!empty($ultima_compra)) {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $ultima_compra)) {
                $data_ultima = DateTime::createFromFormat('d/m/Y', $ultima_compra);
                $data_limite_obj = DateTime::createFromFormat('d/m/Y', $data_limite);
                $tem_dados_recentes = $data_ultima >= $data_limite_obj;
            } else {
                $data_ultima = DateTime::createFromFormat('Y-m-d', $ultima_compra);
                $data_limite_obj = DateTime::createFromFormat('d/m/Y', $data_limite);
                $tem_dados_recentes = $data_ultima >= $data_limite_obj;
            }
        }
    ?>
    <div class="cliente-card <?php echo $is_inativo ? 'cliente-inativo' : ($is_inativando ? 'cliente-inativando' : ''); ?>" 
         data-cliente="<?php echo htmlspecialchars($cliente['cliente'] ?? ''); ?>" 
         data-cnpj="<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? ''); ?>">
        
        <!-- Header do Card -->
        <div class="cliente-card-header">
            <div class="cliente-card-nome">
                <h4><?php echo htmlspecialchars($cliente['cliente'] ?? ''); ?></h4>
                <span class="cnpj-badge"><?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? ''); ?></span>
                <?php if (($cliente['total_cnpjs_diferentes'] ?? 0) > 1): ?>
                <span class="cnpj-agregados-info">
                    <i class="fas fa-link"></i> 
                    <?php echo $cliente['total_cnpjs_diferentes']; ?> CNPJs agregados
                </span>
                <?php endif; ?>
            </div>
            <p class="cliente-card-fantasia"><?php echo htmlspecialchars($cliente['nome_fantasia'] ?? ''); ?></p>
            <div class="cliente-card-status">
                <span class="status-badge <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                </span>
                <span class="estado-badge"><?php echo htmlspecialchars($cliente['estado'] ?? ''); ?></span>
            </div>
        </div>
        
        <!-- Informações do Cliente - Design Profissional -->
        <div class="cliente-card-info">
            <div class="cliente-info-grid">
                <div class="cliente-info-item">
                    <span class="cliente-info-label">Segmento</span>
                    <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['segmento_atuacao'] ?? ''); ?></span>
                </div>
                
                <div class="cliente-info-item">
                    <span class="cliente-info-label">Vendedor</span>
                    <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['nome_vendedor'] ?? ''); ?></span>
                </div>
                
                <div class="cliente-info-item">
                    <span class="cliente-info-label">Supervisor</span>
                    <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['nome_supervisor'] ?? ''); ?></span>
                </div>
                
                <div class="cliente-info-item">
                    <span class="cliente-info-label">Últ. Compra</span>
                    <span class="cliente-info-value data">
                        <?php 
                        if (!empty($cliente['ultima_compra'])) {
                            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $cliente['ultima_compra'])) {
                                echo $cliente['ultima_compra'];
                            } else {
                                echo date('d/m/Y', strtotime($cliente['ultima_compra']));
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="cliente-info-item">
                    <span class="cliente-info-label">Últ. Ligação</span>
                    <span class="cliente-info-value data">
                        <?php 
                        if (!empty($cliente['ultima_ligacao'])) {
                            echo date('d/m/Y H:i', strtotime($cliente['ultima_ligacao']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="cliente-info-item">
                    <span class="cliente-info-label">Faturamento</span>
                    <span class="cliente-info-value faturamento">
                        <?php 
                        $fat_mes = floatval($cliente['faturamento_mes'] ?? 0);
                        echo 'R$ ' . number_format($fat_mes, 2, ',', '.');
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        
        <!-- Ações do Card - Design Profissional -->
        <div class="cliente-card-actions">
            <!-- Todos os botões em uma linha -->
            <div class="cliente-actions-single-row">
                <?php if ($tem_dados_recentes): ?>
                <a href="/Site/includes/api/detalhes_cliente.php?cnpj=<?php echo urlencode($cliente['cnpj_representativo']); ?>&from_optimized=<?php echo (basename($_SERVER['PHP_SELF']) === 'carteira_admin_diretor.php') ? '1' : '0'; ?>" 
                   class="btn btn-primary-action" title="Ver detalhes">
                    <i class="fas fa-eye"></i>
                </a>
                <?php else: ?>
                <span class="btn btn-primary-action disabled" title="DT_FAT < 1º de janeiro de 2025 - botão desabilitado">
                    <i class="fas fa-eye-slash"></i>
                </span>
                <?php endif; ?>
                
                <?php if ($podeLigar && $dadosCompletos): ?>
                <button class="btn btn-primary-action" onclick="selecionarTipoContato('<?php echo htmlspecialchars($clienteCnpj, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($clienteNome, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($clienteTelefone, ENT_QUOTES); ?>')" title="Iniciar Contato">
                    <i class="fas fa-phone"></i>
                </button>
                <?php else: ?>
                <span class="btn btn-primary-action disabled" title="<?php echo !$podeLigar ? 'Telefone não disponível' : 'Dados do cliente incompletos'; ?>">
                    <i class="fas fa-phone"></i>
                </span>
                <?php endif; ?>
                
                <button class="btn btn-primary-action" title="Agendar ligação"
                        onclick="agendarLigacao('<?php echo htmlspecialchars($cliente['raiz_cnpj'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                    <i class="fas fa-calendar-plus"></i>
                </button>
                
                <button class="btn btn-warning-action" title="Observações" 
                        onclick="abrirObservacoes('cliente', '<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                    <i class="fas fa-comment"></i>
                </button>
                
                <button class="btn btn-danger-action" title="Editar Cliente" 
                        onclick="abrirEdicaoCliente('<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>'); return false;">
                    <i class="fas fa-edit"></i>
                </button>
                
                <a href="#" class="btn btn-danger-action" title="Excluir Cliente" 
                   onclick="confirmarExclusaoCliente('<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Paginação -->
<?php if (isset($total_paginas) && $total_paginas > 1): ?>
    <div class="paginacao-container">
        <div class="paginacao-info">
            <span>
                <i class="fas fa-list"></i>
                Mostrando <?php echo (($pagina_atual - 1) * $itens_por_pagina + 1); ?> a <?php echo min($pagina_atual * $itens_por_pagina, $total_registros); ?> de <?php echo number_format($total_registros); ?> clientes
                <span class="paginacao-pagina-atual">(Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>)</span>
            </span>
        </div>
        
        <div class="paginacao-controles">
            <?php if ($pagina_atual > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" class="btn btn-secondary btn-sm paginacao-link">
                    <i class="fas fa-angle-double-left"></i> Primeira
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>" class="btn btn-secondary btn-sm paginacao-link">
                    <i class="fas fa-angle-left"></i> Anterior
                </a>
            <?php endif; ?>
            
            <div class="paginacao-numeros">
                <?php
                $inicio = max(1, $pagina_atual - 2);
                $fim = min($total_paginas, $pagina_atual + 2);
                
                if ($inicio > 1): ?>
                    <span class="paginacao-ellipsis">...</span>
                <?php endif; ?>
                
                <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                       class="btn <?php echo $i == $pagina_atual ? 'btn-primary' : 'btn-secondary'; ?> btn-sm paginacao-link">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($fim < $total_paginas): ?>
                    <span class="paginacao-ellipsis">...</span>
                <?php endif; ?>
            </div>
            
            <?php if ($pagina_atual < $total_paginas): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>" class="btn btn-secondary btn-sm paginacao-link">
                    Próxima <i class="fas fa-angle-right"></i>
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>" class="btn btn-secondary btn-sm paginacao-link">
                    Última <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Informação quando há apenas uma página -->
    <div class="paginacao-container">
        <div class="paginacao-info">
            <span>
                <i class="fas fa-list"></i>
                Mostrando todos os <?php echo number_format($total_registros ?? 0); ?> clientes
            </span>
        </div>
    </div>
<?php endif; ?>

<style>
.paginacao-container {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.paginacao-info {
    font-size: 0.9rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.paginacao-info i {
    color: #007bff;
}

.paginacao-pagina-atual {
    font-weight: 600;
    color: #495057;
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.paginacao-controles {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.paginacao-numeros {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.paginacao-link {
    min-width: 40px;
    text-align: center;
    transition: all 0.2s ease;
}

.paginacao-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.paginacao-ellipsis {
    padding: 0.375rem 0.5rem;
    color: #6c757d;
    font-weight: bold;
}

@media (max-width: 768px) {
    .paginacao-container {
        flex-direction: column;
        text-align: center;
    }
    
    .paginacao-controles {
        justify-content: center;
    }
    
    .paginacao-numeros {
        order: -1;
        margin-bottom: 0.5rem;
    }
}
</style>
<?php endif; ?>
</div>

