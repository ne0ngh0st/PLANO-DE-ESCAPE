<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit;
}

require_once 'conexao.php';

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar permissões - APENAS ADMIN pode editar clientes
$is_admin = strtolower(trim($usuario['perfil'])) === 'admin';

if (!$is_admin) {
    header('Location: ../carteira.php?erro=sem_permissao');
    exit;
}

// Verificar se o CNPJ foi fornecido
$cnpj = $_GET['cnpj'] ?? '';
if (empty($cnpj)) {
    header('Location: ../carteira.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novo_endereco = trim($_POST['endereco'] ?? '');
    $novo_estado = trim($_POST['estado'] ?? '');
    $novo_nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $novo_cliente = trim($_POST['cliente'] ?? '');
    $novo_segmento = trim($_POST['segmento'] ?? '');
    $novo_telefone = trim($_POST['telefone'] ?? '');
    $novo_email = trim($_POST['email'] ?? '');
    $novo_nome_contato = trim($_POST['nome_contato'] ?? '');
    
    // Validar dados obrigatórios
    if (empty($novo_endereco) || empty($novo_estado) || empty($novo_nome_fantasia) || empty($novo_cliente)) {
        $mensagem = 'Todos os campos obrigatórios devem ser preenchidos.';
        $tipo_mensagem = 'erro';
    } else {
        try {
            // Verificar se a coluna NOME_CONTATO existe, se não existir, criar
            if (!empty($novo_nome_contato)) {
                $check_column = "SHOW COLUMNS FROM ultimo_faturamento LIKE 'NOME_CONTATO'";
                $stmt_check = $pdo->query($check_column);
                if ($stmt_check->rowCount() == 0) {
                    // Coluna não existe, criar
                    $create_column = "ALTER TABLE ultimo_faturamento ADD COLUMN NOME_CONTATO VARCHAR(255)";
                    $pdo->exec($create_column);
                }
            }
            
            // Ao invés de atualizar diretamente a ultimo_faturamento,
            // vamos inserir/atualizar na tabela CLIENTES_EDICOES para preservar dados originais
            
            // Verificar se já existe uma edição para este CNPJ
            $sql_verificar_edicao = "SELECT COUNT(*) as total FROM CLIENTES_EDICOES WHERE cnpj_original = ? AND ativo = 1";
            $stmt_verificar_edicao = $pdo->prepare($sql_verificar_edicao);
            $stmt_verificar_edicao->execute([$cnpj]);
            $edicao_existe = $stmt_verificar_edicao->fetch(PDO::FETCH_ASSOC)['total'] > 0;
            
            if ($edicao_existe) {
                // Atualizar edição existente
                $sql_atualizar_edicao = "UPDATE CLIENTES_EDICOES SET 
                                         endereco_editado = ?, 
                                         estado_editado = ?, 
                                         nome_fantasia_editado = ?, 
                                         cliente_editado = ?, 
                                         segmento_editado = ?, 
                                         telefone_editado = ?, 
                                         email_editado = ?, 
                                         nome_contato_editado = ?, 
                                         usuario_editor = ?,
                                         data_edicao = NOW()
                                         WHERE cnpj_original = ? AND ativo = 1";
                
                $stmt_edicao = $pdo->prepare($sql_atualizar_edicao);
                $resultado = $stmt_edicao->execute([
                    $novo_endereco,
                    $novo_estado, 
                    $novo_nome_fantasia,
                    $novo_cliente,
                    $novo_segmento,
                    $novo_telefone,
                    $novo_email,
                    $novo_nome_contato,
                    $usuario['email'],
                    $cnpj
                ]);
            } else {
                // Inserir nova edição
                $sql_inserir_edicao = "INSERT INTO CLIENTES_EDICOES 
                                       (cnpj_original, endereco_editado, estado_editado, nome_fantasia_editado, 
                                        cliente_editado, segmento_editado, telefone_editado, email_editado, 
                                        nome_contato_editado, usuario_editor) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt_edicao = $pdo->prepare($sql_inserir_edicao);
                $resultado = $stmt_edicao->execute([
                    $cnpj,
                    $novo_endereco,
                    $novo_estado, 
                    $novo_nome_fantasia,
                    $novo_cliente,
                    $novo_segmento,
                    $novo_telefone,
                    $novo_email,
                    $novo_nome_contato,
                    $usuario['email']
                ]);
            }
            
            if ($resultado) {
                $mensagem = 'Informações do cliente atualizadas com sucesso!';
                $tipo_mensagem = 'sucesso';
            } else {
                $mensagem = 'Erro ao atualizar as informações.';
                $tipo_mensagem = 'erro';
            }
        } catch (PDOException $e) {
            $mensagem = 'Erro no banco de dados: ' . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }
}

// Verificar se a coluna NOME_CONTATO existe
$check_column = "SHOW COLUMNS FROM ultimo_faturamento LIKE 'NOME_CONTATO'";
$stmt_check = $pdo->query($check_column);
$nome_contato_exists = $stmt_check->rowCount() > 0;

// Buscar informações atuais do cliente
$sql = "SELECT 
            CNPJ as cnpj,
            CLIENTE as cliente,
            NOME_FANTASIA as nome_fantasia,
            ESTADO as estado,
            ENDERECO as endereco,
            COD_VENDEDOR as cod_vendedor,
            NOME_VENDEDOR as nome_vendedor,
            COD_SUPER as cod_supervisor,
            FANT_SUPER as nome_supervisor,
            TELEFONE as telefone,
            EMailNFe as email,
            Descricao1 as segmento";

if ($nome_contato_exists) {
    $sql .= ", NOME_CONTATO as nome_contato";
} else {
    $sql .= ", '' as nome_contato";
}

$sql .= " FROM ultimo_faturamento WHERE CNPJ = ? LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$cnpj]);
$cliente_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente_info) {
    header('Location: ../carteira.php');
    exit;
}

// Buscar segmentos disponíveis
$sql_segmentos = "SELECT DISTINCT Descricao1 FROM ultimo_faturamento WHERE Descricao1 IS NOT NULL AND Descricao1 != '' ORDER BY Descricao1";
$stmt_segmentos = $pdo->query($sql_segmentos);
$segmentos_disponiveis = $stmt_segmentos->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/img/logo_site.png" type="image/png">
    <link rel="shortcut icon" href="../assets/img/logo_site.png" type="image/png">
    <link rel="apple-touch-icon" href="../assets/img/logo_site.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/estilo.css?v=<?php echo time(); ?>">
    <style>
        .editar-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .editar-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .editar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .editar-header h1 {
            color: #1a237e;
            margin: 0;
            font-size: 1.8rem;
        }
        
        .btn-voltar {
            background: #1a237e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-voltar:hover {
            background: #0d47a1;
            transform: translateY(-1px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a237e;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-acoes {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-salvar {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 3px 12px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-salvar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-salvar:hover::before {
            left: 100%;
        }
        
        .btn-salvar:hover {
            background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        
        .btn-cancelar {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 3px 12px rgba(108, 117, 125, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-cancelar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-cancelar:hover::before {
            left: 100%;
        }
        
        .btn-cancelar:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }
        
        .mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .mensagem.sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .mensagem.erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-cliente {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .info-cliente h3 {
            color: #1a237e;
            margin-bottom: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-item strong {
            color: #333;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-acoes {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        <?php include 'nav_mobile.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                <div class="editar-container">
                    <!-- Header -->
                    <div class="editar-header">
                        <h1><i class="fas fa-edit"></i> Editar Cliente</h1>
                        <a href="../carteira.php" class="btn-voltar">
                            <i class="fas fa-arrow-left"></i> Voltar à Carteira
                        </a>
                    </div>
                    
                    <!-- Mensagem de feedback -->
                    <?php if (!empty($mensagem)): ?>
                        <div class="mensagem <?php echo $tipo_mensagem; ?>">
                            <?php echo htmlspecialchars($mensagem); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Informações do cliente -->
                    <div class="info-cliente">
                        <h3><i class="fas fa-info-circle"></i> Informações do Cliente</h3>
                        <div class="info-item">
                            <strong>CNPJ:</strong> <?php echo htmlspecialchars($cliente_info['cnpj']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Vendedor:</strong> <?php echo htmlspecialchars($cliente_info['nome_vendedor']); ?> (<?php echo htmlspecialchars($cliente_info['cod_vendedor']); ?>)
                        </div>
                        <?php if (!empty($cliente_info['nome_supervisor'])): ?>
                        <div class="info-item">
                            <strong>Supervisor:</strong> <?php echo htmlspecialchars($cliente_info['nome_supervisor']); ?> (<?php echo htmlspecialchars($cliente_info['cod_supervisor']); ?>)
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Formulário de edição -->
                    <div class="editar-card">
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="cliente">Nome da Empresa <span class="required">*</span></label>
                                    <input type="text" id="cliente" name="cliente" value="<?php echo htmlspecialchars($cliente_info['cliente']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="nome_fantasia">Nome Fantasia <span class="required">*</span></label>
                                    <input type="text" id="nome_fantasia" name="nome_fantasia" value="<?php echo htmlspecialchars($cliente_info['nome_fantasia']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="segmento">Segmento</label>
                                    <select id="segmento" name="segmento">
                                        <option value="">Selecione o segmento</option>
                                        <?php foreach ($segmentos_disponiveis as $segmento): ?>
                                            <option value="<?php echo htmlspecialchars($segmento); ?>" <?php echo ($cliente_info['segmento'] === $segmento) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($segmento); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="nome_contato">Nome do Contato</label>
                                    <input type="text" id="nome_contato" name="nome_contato" value="<?php echo htmlspecialchars($cliente_info['nome_contato']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="telefone">Telefone</label>
                                    <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($cliente_info['telefone']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email">E-mail</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($cliente_info['email']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="endereco">Endereço Completo <span class="required">*</span></label>
                                <textarea id="endereco" name="endereco" rows="3" required><?php echo htmlspecialchars($cliente_info['endereco']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="estado">Estado <span class="required">*</span></label>
                                <select id="estado" name="estado" required>
                                    <option value="">Selecione o estado</option>
                                    <option value="AC" <?php echo ($cliente_info['estado'] === 'AC') ? 'selected' : ''; ?>>Acre</option>
                                    <option value="AL" <?php echo ($cliente_info['estado'] === 'AL') ? 'selected' : ''; ?>>Alagoas</option>
                                    <option value="AP" <?php echo ($cliente_info['estado'] === 'AP') ? 'selected' : ''; ?>>Amapá</option>
                                    <option value="AM" <?php echo ($cliente_info['estado'] === 'AM') ? 'selected' : ''; ?>>Amazonas</option>
                                    <option value="BA" <?php echo ($cliente_info['estado'] === 'BA') ? 'selected' : ''; ?>>Bahia</option>
                                    <option value="CE" <?php echo ($cliente_info['estado'] === 'CE') ? 'selected' : ''; ?>>Ceará</option>
                                    <option value="DF" <?php echo ($cliente_info['estado'] === 'DF') ? 'selected' : ''; ?>>Distrito Federal</option>
                                    <option value="ES" <?php echo ($cliente_info['estado'] === 'ES') ? 'selected' : ''; ?>>Espírito Santo</option>
                                    <option value="GO" <?php echo ($cliente_info['estado'] === 'GO') ? 'selected' : ''; ?>>Goiás</option>
                                    <option value="MA" <?php echo ($cliente_info['estado'] === 'MA') ? 'selected' : ''; ?>>Maranhão</option>
                                    <option value="MT" <?php echo ($cliente_info['estado'] === 'MT') ? 'selected' : ''; ?>>Mato Grosso</option>
                                    <option value="MS" <?php echo ($cliente_info['estado'] === 'MS') ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?php echo ($cliente_info['estado'] === 'MG') ? 'selected' : ''; ?>>Minas Gerais</option>
                                    <option value="PA" <?php echo ($cliente_info['estado'] === 'PA') ? 'selected' : ''; ?>>Pará</option>
                                    <option value="PB" <?php echo ($cliente_info['estado'] === 'PB') ? 'selected' : ''; ?>>Paraíba</option>
                                    <option value="PR" <?php echo ($cliente_info['estado'] === 'PR') ? 'selected' : ''; ?>>Paraná</option>
                                    <option value="PE" <?php echo ($cliente_info['estado'] === 'PE') ? 'selected' : ''; ?>>Pernambuco</option>
                                    <option value="PI" <?php echo ($cliente_info['estado'] === 'PI') ? 'selected' : ''; ?>>Piauí</option>
                                    <option value="RJ" <?php echo ($cliente_info['estado'] === 'RJ') ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                    <option value="RN" <?php echo ($cliente_info['estado'] === 'RN') ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?php echo ($cliente_info['estado'] === 'RS') ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?php echo ($cliente_info['estado'] === 'RO') ? 'selected' : ''; ?>>Rondônia</option>
                                    <option value="RR" <?php echo ($cliente_info['estado'] === 'RR') ? 'selected' : ''; ?>>Roraima</option>
                                    <option value="SC" <?php echo ($cliente_info['estado'] === 'SC') ? 'selected' : ''; ?>>Santa Catarina</option>
                                    <option value="SP" <?php echo ($cliente_info['estado'] === 'SP') ? 'selected' : ''; ?>>São Paulo</option>
                                    <option value="SE" <?php echo ($cliente_info['estado'] === 'SE') ? 'selected' : ''; ?>>Sergipe</option>
                                    <option value="TO" <?php echo ($cliente_info['estado'] === 'TO') ? 'selected' : ''; ?>>Tocantins</option>
                                </select>
                            </div>
                            
                            <div class="btn-acoes">
                                <button type="submit" class="btn-salvar">
                                    <i class="fas fa-save"></i> Salvar Alterações
                                </button>
                                <a href="../carteira.php" class="btn-cancelar">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
            <footer class="dashboard-footer">
                <p>© 2025 Autopel - Todos os direitos reservados</p>
                <p class="system-info">Sistema BI v1.0</p>
            </footer>
        </div>
    </div>
</body>
</html> 