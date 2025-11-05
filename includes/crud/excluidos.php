<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit;
}

// Verificar se é admin ou supervisor
$usuario = $_SESSION['usuario'];
if (!in_array($usuario['perfil'], ['admin', 'supervisor'])) {
    header('Location: home.php');
    exit;
}

require_once 'conexao.php';

$tipo = $_GET['tipo'] ?? 'clientes'; // clientes ou leads
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Buscar registros excluídos
if ($tipo === 'clientes') {
    $sql = "SELECT * FROM clientes_excluidos ORDER BY data_exclusao DESC LIMIT $por_pagina OFFSET $offset";
    $count_sql = "SELECT COUNT(*) as total FROM clientes_excluidos";
} else {
    $sql = "SELECT * FROM leads_excluidos ORDER BY data_exclusao DESC LIMIT $por_pagina OFFSET $offset";
    $count_sql = "SELECT COUNT(*) as total FROM leads_excluidos";
}

$stmt = $pdo->prepare($sql);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare($count_sql);
$stmt->execute();
$total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $por_pagina);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Registros Excluídos - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/img/logo_site.png" type="image/png">
    <link rel="shortcut icon" href="../assets/img/logo_site.png" type="image/png">
    <link rel="apple-touch-icon" href="../assets/img/logo_site.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/estilo.css?v=<?php echo time(); ?>">
    <style>
        .excluidos-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header-excluidos {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            background: #f8f9fa;
            color: #666;
        }
        
        .tab.active {
            background: #1a237e;
            color: white;
        }
        
        .tab:hover {
            background: #0d47a1;
            color: white;
        }
        
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .data-exclusao {
            color: #dc3545;
            font-weight: 500;
        }
        
        .paginacao {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }
        
        .paginacao a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .paginacao a.atual {
            background: #1a237e;
            color: white;
            border-color: #1a237e;
        }
        
        .paginacao a:hover {
            background: #0d47a1;
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="excluidos-container">
                <div class="header-excluidos">
                    <h1><i class="fas fa-trash"></i> Registros Excluídos</h1>
                    <p>Visualize e gerencie registros que foram movidos para exclusão</p>
                    
                    <div class="tabs">
                        <button class="tab <?php echo $tipo === 'clientes' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?tipo=clientes'">
                            <i class="fas fa-briefcase"></i> Clientes (<?php echo $total_registros; ?>)
                        </button>
                        <button class="tab <?php echo $tipo === 'leads' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?tipo=leads'">
                            <i class="fas fa-users"></i> Leads
                        </button>
                    </div>
                </div>

                <div class="stats-card">
                    <h3>Estatísticas</h3>
                    <p>Total de registros excluídos: <strong><?php echo $total_registros; ?></strong></p>
                    <p>Página atual: <strong><?php echo $pagina; ?></strong> de <strong><?php echo $total_paginas; ?></strong></p>
                </div>

                <div class="table-container">
                    <?php if (empty($registros)): ?>
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-trash" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                            <h3>Nenhum registro excluído encontrado</h3>
                            <p>Não há registros na lixeira no momento.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <?php if ($tipo === 'clientes'): ?>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>CNPJ</th>
                                        <th>Vendedor</th>
                                        <th>Valor Total</th>
                                        <th>Data de Exclusão</th>
                                        <th>Usuário</th>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Telefone</th>
                                        <th>Status</th>
                                        <th>Data de Exclusão</th>
                                        <th>Usuário</th>
                                    </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $registro): ?>
                                    <tr>
                                        <?php if ($tipo === 'clientes'): ?>
                                            <td>
                                                <div class="cliente-info"><?php echo htmlspecialchars($registro['cliente'] ?? ''); ?></div>
                                                <div class="cliente-cnpj"><?php echo htmlspecialchars($registro['nome_fantasia'] ?? ''); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($registro['cnpj'] ?? ''); ?></td>
                                            <td>
                                                <div class="cliente-info"><?php echo htmlspecialchars($registro['nome_vendedor'] ?? ''); ?></div>
                                                <div class="cliente-cnpj">Código: <?php echo htmlspecialchars($registro['cod_vendedor'] ?? ''); ?></div>
                                            </td>
                                            <td class="valor-carteira">
                                                R$ <?php echo number_format($registro['valor_total'] ?? 0, 2, ',', '.'); ?>
                                            </td>
                                            <td class="data-exclusao">
                                                <?php echo date('d/m/Y H:i', strtotime($registro['data_exclusao'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($registro['usuario_exclusao'] ?? 'N/A'); ?></td>
                                        <?php else: ?>
                                            <td>
                                                <div class="cliente-info"><?php echo htmlspecialchars($registro['nome_fantasia'] ?? $registro['razao_social'] ?? ''); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($registro['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($registro['telefone'] ?? ''); ?></td>
                                            <td>
                                                <span class="status-badge status-lead">
                                                    <?php echo htmlspecialchars($registro['marcao_prospect'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td class="data-exclusao">
                                                <?php echo date('d/m/Y H:i', strtotime($registro['data_exclusao'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($registro['usuario_exclusao'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                            <div class="paginacao">
                                <?php if ($pagina > 1): ?>
                                    <a href="?tipo=<?php echo $tipo; ?>&pagina=<?php echo $pagina - 1; ?>">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                                    <a href="?tipo=<?php echo $tipo; ?>&pagina=<?php echo $i; ?>" 
                                       class="<?php echo $i == $pagina ? 'atual' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($pagina < $total_paginas): ?>
                                    <a href="?tipo=<?php echo $tipo; ?>&pagina=<?php echo $pagina + 1; ?>">
                                        Próxima <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <footer class="dashboard-footer">
            <p>© 2025 Autopel - Todos os direitos reservados</p>
            <p class="system-info">Sistema BI v1.0</p>
        </footer>
    </div>
</body>
</html>