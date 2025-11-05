<?php
// Verificar se a conexão PDO está disponível
if (!isset($pdo)) {
    require_once 'conexao.php';
}

// Obter estatísticas de ligações
try {
    // Preparar condições WHERE baseadas no perfil do usuário
    $where_conditions = [];
    $params = [];
    
    // Se for supervisor, buscar ligações da equipe
    if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
        
        if (!empty($vendedor_selecionado)) {
            // Se um vendedor específico foi selecionado, filtrar apenas suas ligações
            // Primeiro buscar o ID do usuário pelo COD_VENDEDOR
            $sql_buscar_id = "SELECT ID FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1";
            $stmt_buscar_id = $pdo->prepare($sql_buscar_id);
            $stmt_buscar_id->execute([$vendedor_selecionado]);
            $usuario_encontrado = $stmt_buscar_id->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_encontrado) {
                $where_conditions[] = "usuario_id = ?";
                $params[] = $usuario_encontrado['ID'];
            }
        } else {
            // Filtrar ligações de todos os vendedores da equipe
            $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.ID = LIGACOES.usuario_id AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params[] = $usuario['cod_vendedor'];
        }
    }
    // Se for diretor ou admin, verificar se há filtro de visão específica
    elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
        
        if (!empty($vendedor_selecionado)) {
            // Se um vendedor específico foi selecionado, filtrar apenas suas ligações
            // Primeiro buscar o ID do usuário pelo COD_VENDEDOR
            $sql_buscar_id = "SELECT ID FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1";
            $stmt_buscar_id = $pdo->prepare($sql_buscar_id);
            $stmt_buscar_id->execute([$vendedor_selecionado]);
            $usuario_encontrado = $stmt_buscar_id->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_encontrado) {
                $where_conditions[] = "usuario_id = ?";
                $params[] = $usuario_encontrado['ID'];
            }
        } elseif (!empty($supervisor_selecionado)) {
            // Se um supervisor foi selecionado, filtrar ligações dos vendedores sob sua supervisão
            $where_conditions[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.ID = LIGACOES.usuario_id AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params[] = $supervisor_selecionado;
        }
        // Se nenhum filtro for aplicado, diretores veem todas as ligações
    }
    // Para vendedor/representante individual
    else {
        if (isset($usuario['id'])) {
            $where_conditions[] = "usuario_id = ?";
            $params[] = $usuario['id'];
        }
    }
    
    // Sempre incluir o filtro de exclusão lógica
    $where_conditions[] = "status != 'excluida'";
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Buscar ligações por dia da semana (últimos 30 dias)
    $sql_dia_semana = "
        SELECT 
            DAYNAME(data_ligacao) as dia_semana,
            COUNT(*) as total_ligacoes
        FROM LIGACOES 
        $where_clause AND data_ligacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DAYNAME(data_ligacao)
        ORDER BY FIELD(DAYNAME(data_ligacao), 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
    ";
    
    $stmt_dia_semana = $pdo->prepare($sql_dia_semana);
    $stmt_dia_semana->execute($params);
    $ligacoes_dia_semana = $stmt_dia_semana->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar ligações por mês (últimos 12 meses)
    $sql_mes = "
        SELECT 
            DATE_FORMAT(data_ligacao, '%Y-%m') as mes_ano,
            DATE_FORMAT(data_ligacao, '%M/%Y') as mes_nome,
            COUNT(*) as total_ligacoes
        FROM LIGACOES 
        $where_clause AND data_ligacao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(data_ligacao, '%Y-%m')
        ORDER BY mes_ano DESC
        LIMIT 6
    ";
    
    $stmt_mes = $pdo->prepare($sql_mes);
    $stmt_mes->execute($params);
    $ligacoes_mes = $stmt_mes->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar total de ligações hoje
    $sql_hoje = "
        SELECT COUNT(*) as total_hoje
        FROM LIGACOES 
        $where_clause AND DATE(data_ligacao) = CURDATE()
    ";
    
    $stmt_hoje = $pdo->prepare($sql_hoje);
    $stmt_hoje->execute($params);
    $ligacoes_hoje = $stmt_hoje->fetch(PDO::FETCH_ASSOC);
    
    // Buscar total de ligações este mês
    $sql_mes_atual = "
        SELECT COUNT(*) as total_mes
        FROM LIGACOES 
        $where_clause AND YEAR(data_ligacao) = YEAR(CURDATE())
        AND MONTH(data_ligacao) = MONTH(CURDATE())
    ";
    
    $stmt_mes_atual = $pdo->prepare($sql_mes_atual);
    $stmt_mes_atual->execute($params);
    $ligacoes_mes_atual = $stmt_mes_atual->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar estatísticas de ligações: " . $e->getMessage());
    $ligacoes_dia_semana = [];
    $ligacoes_mes = [];
    $ligacoes_hoje = ['total_hoje' => 0];
    $ligacoes_mes_atual = ['total_mes' => 0];
}

// Traduzir nomes dos dias da semana
$dias_traduzidos = [
    'Sunday' => 'Domingo',
    'Monday' => 'Segunda',
    'Tuesday' => 'Terça',
    'Wednesday' => 'Quarta',
    'Thursday' => 'Quinta',
    'Friday' => 'Sexta',
    'Saturday' => 'Sábado'
];

// Traduzir nomes dos meses
$meses_traduzidos = [
    'January' => 'Jan',
    'February' => 'Fev',
    'March' => 'Mar',
    'April' => 'Abr',
    'May' => 'Mai',
    'June' => 'Jun',
    'July' => 'Jul',
    'August' => 'Ago',
    'September' => 'Set',
    'October' => 'Out',
    'November' => 'Nov',
    'December' => 'Dez'
];
?>



<div class="card-ligacoes">
    <div class="card-ligacoes-header">
        <h3 class="card-ligacoes-title">
            <i class="fas fa-phone"></i>
            Estatísticas de Ligações
        </h3>
    </div>
    
    <!-- Resumo rápido -->
    <div class="card-ligacoes-resumo">
        <div class="card-ligacoes-resumo-item">
            <div class="card-ligacoes-resumo-valor">
                <?php echo number_format($ligacoes_hoje['total_hoje'], 0, ',', '.'); ?>
            </div>
            <div class="card-ligacoes-resumo-label">Hoje</div>
        </div>
        
        <div class="card-ligacoes-resumo-item">
            <div class="card-ligacoes-resumo-valor">
                <?php echo number_format($ligacoes_mes_atual['total_mes'], 0, ',', '.'); ?>
            </div>
            <div class="card-ligacoes-resumo-label">Este Mês</div>
        </div>
    </div>
    
    <!-- Gráfico por dia da semana -->
    <div class="card-ligacoes-secao">
        <h4 class="card-ligacoes-secao-titulo">
            <i class="fas fa-calendar-week"></i>
            Últimos 30 dias por dia da semana
        </h4>
        
        <div class="card-ligacoes-grafico">
            <?php if (!empty($ligacoes_dia_semana)): ?>
                <div class="card-ligacoes-barras">
                    <?php 
                    $max_ligacoes = max(array_column($ligacoes_dia_semana, 'total_ligacoes'));
                    foreach ($ligacoes_dia_semana as $dia): 
                        $percentual = $max_ligacoes > 0 ? ($dia['total_ligacoes'] / $max_ligacoes) * 100 : 0;
                        $dia_traduzido = $dias_traduzidos[$dia['dia_semana']] ?? $dia['dia_semana'];
                    ?>
                        <div class="card-ligacoes-barra-item">
                            <div class="card-ligacoes-barra-label"><?php echo $dia_traduzido; ?></div>
                            <div class="card-ligacoes-barra-container">
                                <div class="card-ligacoes-barra" style="width: <?php echo $percentual; ?>%"></div>
                            </div>
                            <div class="card-ligacoes-barra-valor"><?php echo $dia['total_ligacoes']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-ligacoes-vazio">
                    <i class="fas fa-info-circle"></i>
                    Nenhuma ligação registrada nos últimos 30 dias
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Gráfico por mês -->
    <div class="card-ligacoes-secao">
        <h4 class="card-ligacoes-secao-titulo">
            <i class="fas fa-calendar-alt"></i>
            Últimos 6 meses
        </h4>
        
        <div class="card-ligacoes-grafico">
            <?php if (!empty($ligacoes_mes)): ?>
                <div class="card-ligacoes-barras">
                    <?php 
                    $max_ligacoes_mes = max(array_column($ligacoes_mes, 'total_ligacoes'));
                    foreach ($ligacoes_mes as $mes): 
                        $percentual = $max_ligacoes_mes > 0 ? ($mes['total_ligacoes'] / $max_ligacoes_mes) * 100 : 0;
                        $mes_nome = $mes['mes_nome'];
                        // Traduzir o nome do mês
                        foreach ($meses_traduzidos as $en => $pt) {
                            $mes_nome = str_replace($en, $pt, $mes_nome);
                        }
                    ?>
                        <div class="card-ligacoes-barra-item">
                            <div class="card-ligacoes-barra-label"><?php echo $mes_nome; ?></div>
                            <div class="card-ligacoes-barra-container">
                                <div class="card-ligacoes-barra" style="width: <?php echo $percentual; ?>%"></div>
                            </div>
                            <div class="card-ligacoes-barra-valor"><?php echo $mes['total_ligacoes']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-ligacoes-vazio">
                    <i class="fas fa-info-circle"></i>
                    Nenhuma ligação registrada nos últimos 6 meses
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card-ligacoes {
    /* Removido background, border-radius, padding e box-shadow pois agora está dentro de um container */
    margin-bottom: 0;
}

.card-ligacoes-header {
    margin-bottom: 20px;
}

.card-ligacoes-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-ligacoes-title i {
    color: #007bff;
}

.card-ligacoes-resumo {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.card-ligacoes-resumo-item {
    text-align: center;
    flex: 1;
}

.card-ligacoes-resumo-valor {
    font-size: 1.5rem;
    font-weight: 700;
    color: #007bff;
    margin-bottom: 5px;
}

.card-ligacoes-resumo-label {
    font-size: 0.9rem;
    color: #666;
    font-weight: 500;
}

.card-ligacoes-secao {
    margin-bottom: 25px;
}

.card-ligacoes-secao:last-child {
    margin-bottom: 0;
}

.card-ligacoes-secao-titulo {
    font-size: 1rem;
    font-weight: 600;
    color: #555;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-ligacoes-secao-titulo i {
    color: #28a745;
}

.card-ligacoes-barras {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.card-ligacoes-barra-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-ligacoes-barra-label {
    width: 60px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #666;
    text-align: right;
}

.card-ligacoes-barra-container {
    flex: 1;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.card-ligacoes-barra {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #0056b3);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.card-ligacoes-barra-valor {
    width: 40px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    text-align: right;
}

.card-ligacoes-vazio {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.card-ligacoes-vazio i {
    color: #ccc;
}

/* Modo Escuro */
[data-theme="dark"] .card-ligacoes-title {
    color: #ffb74d;
}

[data-theme="dark"] .card-ligacoes-title i {
    color: #ff8f00;
}

[data-theme="dark"] .card-ligacoes-resumo {
    background: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
}

[data-theme="dark"] .card-ligacoes-resumo-valor {
    color: #ffb74d;
}

[data-theme="dark"] .card-ligacoes-resumo-label {
    color: #ffcc80;
}

[data-theme="dark"] .card-ligacoes-secao-titulo {
    color: #ffb74d;
}

[data-theme="dark"] .card-ligacoes-secao-titulo i {
    color: #ff8f00;
}

[data-theme="dark"] .card-ligacoes-barra-label {
    color: #ffcc80;
}

[data-theme="dark"] .card-ligacoes-barra-container {
    background: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
}

[data-theme="dark"] .card-ligacoes-barra {
    background: linear-gradient(90deg, #ff8f00, #ff9800);
}

[data-theme="dark"] .card-ligacoes-barra-valor {
    color: #ffb74d;
}

[data-theme="dark"] .card-ligacoes-vazio {
    color: #ffcc80;
}

[data-theme="dark"] .card-ligacoes-vazio i {
    color: #ff8f00;
}

/* Responsividade */
@media (max-width: 768px) {
    .card-ligacoes {
        padding: 15px;
    }
    
    .card-ligacoes-resumo {
        flex-direction: column;
        gap: 15px;
    }
    
    .card-ligacoes-barra-label {
        width: 50px;
        font-size: 0.8rem;
    }
    
    .card-ligacoes-barra-valor {
        width: 35px;
        font-size: 0.8rem;
    }
}
</style>
