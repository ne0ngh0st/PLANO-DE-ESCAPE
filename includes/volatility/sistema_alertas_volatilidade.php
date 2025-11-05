<?php
// Sistema de alertas de volatilidade em tempo real
require_once 'conexao.php';

class SistemaAlertasVolatilidade {
    private $pdo;
    private $limites_alertas;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->limites_alertas = [
            'contatos_whatsapp' => 20, // 20% de variação
            'contatos_presencial' => 30,
            'contatos_ligacao' => 25,
            'clientes_ativos' => 5,
            'clientes_inativos' => 10,
            'faturamento' => 15,
            'atingimento_meta' => 10 // 10 pontos percentuais
        ];
    }
    
    // Verificar alertas em tempo real
    public function verificarAlertasTempoReal() {
        try {
            $hoje = date('Y-m-d');
            $ontem = date('Y-m-d', strtotime('-1 day'));
            
            // Buscar métricas de hoje e ontem
            $stmt = $this->pdo->prepare("
                SELECT * FROM metricas_volatilidade_diaria 
                WHERE data_metrica IN (?, ?) 
                ORDER BY data_metrica DESC
            ");
            $stmt->execute([$hoje, $ontem]);
            $metricas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($metricas) < 2) {
                return ['status' => 'info', 'message' => 'Dados insuficientes para comparação'];
            }
            
            $metricas_hoje = $metricas[0];
            $metricas_ontem = $metricas[1];
            
            $alertas = [];
            
            // Verificar cada métrica
            $metricas_para_verificar = [
                'contatos_whatsapp' => 'total_contatos_whatsapp',
                'contatos_presencial' => 'total_contatos_presencial',
                'contatos_ligacao' => 'total_contatos_ligacao',
                'clientes_ativos' => 'total_clientes_ativos',
                'clientes_inativos' => 'total_clientes_inativos',
                'faturamento' => 'faturamento_dia',
                'atingimento_meta' => 'percentual_atingimento'
            ];
            
            foreach ($metricas_para_verificar as $tipo => $campo) {
                $valor_hoje = $metricas_hoje[$campo];
                $valor_ontem = $metricas_ontem[$campo];
                
                if ($valor_ontem > 0) {
                    $percentual_mudanca = (($valor_hoje - $valor_ontem) / $valor_ontem) * 100;
                } else {
                    $percentual_mudanca = $valor_hoje > 0 ? 100 : 0;
                }
                
                // Para atingimento de meta, usar diferença em pontos percentuais
                if ($tipo === 'atingimento_meta') {
                    $percentual_mudanca = $valor_hoje - $valor_ontem;
                }
                
                $limite = $this->limites_alertas[$tipo];
                
                if (abs($percentual_mudanca) >= $limite) {
                    $severidade = $this->calcularSeveridade($percentual_mudanca, $limite);
                    $tendencia = $percentual_mudanca > 0 ? 'positiva' : 'negativa';
                    
                    $alertas[] = [
                        'tipo' => $tipo,
                        'severidade' => $severidade,
                        'percentual_mudanca' => $percentual_mudanca,
                        'valor_hoje' => $valor_hoje,
                        'valor_ontem' => $valor_ontem,
                        'tendencia' => $tendencia,
                        'descricao' => $this->gerarDescricaoAlerta($tipo, $percentual_mudanca, $valor_hoje, $valor_ontem)
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'alertas' => $alertas,
                'total_alertas' => count($alertas),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao verificar alertas: ' . $e->getMessage()
            ];
        }
    }
    
    // Calcular severidade do alerta
    private function calcularSeveridade($percentual_mudanca, $limite) {
        $abs_mudanca = abs($percentual_mudanca);
        
        if ($abs_mudanca >= $limite * 4) {
            return 'critica';
        } elseif ($abs_mudanca >= $limite * 3) {
            return 'alta';
        } elseif ($abs_mudanca >= $limite * 2) {
            return 'media';
        } else {
            return 'baixa';
        }
    }
    
    // Gerar descrição do alerta
    private function gerarDescricaoAlerta($tipo, $percentual_mudanca, $valor_hoje, $valor_ontem) {
        $nomes = [
            'contatos_whatsapp' => 'Contatos WhatsApp',
            'contatos_presencial' => 'Contatos Presencial',
            'contatos_ligacao' => 'Contatos por Ligação',
            'clientes_ativos' => 'Clientes Ativos',
            'clientes_inativos' => 'Clientes Inativos',
            'faturamento' => 'Faturamento',
            'atingimento_meta' => 'Atingimento de Meta'
        ];
        
        $nome = $nomes[$tipo] ?? $tipo;
        $tendencia = $percentual_mudanca > 0 ? 'aumentou' : 'diminuiu';
        $sufixo = $tipo === 'atingimento_meta' ? ' pontos percentuais' : '%';
        
        return "{$nome} {$tendencia} " . number_format(abs($percentual_mudanca), 1) . 
               "{$sufixo} (de " . number_format($valor_ontem, 0) . 
               " para " . number_format($valor_hoje, 0) . ")";
    }
    
    // Obter alertas recentes
    public function obterAlertasRecentes($limite = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM alertas_volatilidade 
                WHERE data_metrica >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ORDER BY data_criacao DESC, severidade DESC
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Marcar alerta como lido
    public function marcarAlertaComoLido($alerta_id, $usuario_id) {
        try {
            // Criar tabela de alertas lidos se não existir
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS alertas_volatilidade_lidos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    alerta_id INT,
                    usuario_id INT,
                    data_leitura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_alerta_usuario (alerta_id, usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO alertas_volatilidade_lidos (alerta_id, usuario_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$alerta_id, $usuario_id]);
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Obter estatísticas de alertas
    public function obterEstatisticasAlertas($dias = 7) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    severidade,
                    COUNT(*) as total,
                    AVG(percentual_mudanca) as media_mudanca
                FROM alertas_volatilidade 
                WHERE data_metrica >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY severidade
                ORDER BY 
                    CASE severidade 
                        WHEN 'critica' THEN 1 
                        WHEN 'alta' THEN 2 
                        WHEN 'media' THEN 3 
                        WHEN 'baixa' THEN 4 
                    END
            ");
            $stmt->execute([$dias]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Configurar limites de alertas
    public function configurarLimites($novos_limites) {
        $this->limites_alertas = array_merge($this->limites_alertas, $novos_limites);
    }
    
    // Obter limites atuais
    public function obterLimites() {
        return $this->limites_alertas;
    }
}

// API endpoints - só executar se for uma requisição AJAX
if (isset($_GET['action']) || isset($_POST['action'])) {
    // Verificar se é uma requisição AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        
        // API endpoint para verificar alertas
        if (isset($_GET['action']) && $_GET['action'] === 'verificar_alertas') {
            header('Content-Type: application/json');
            
            $sistema = new SistemaAlertasVolatilidade($pdo);
            $resultado = $sistema->verificarAlertasTempoReal();
            
            echo json_encode($resultado);
            exit;
        }

        // API endpoint para obter alertas recentes
        if (isset($_GET['action']) && $_GET['action'] === 'alertas_recentes') {
            header('Content-Type: application/json');
            
            $limite = intval($_GET['limite'] ?? 10);
            $sistema = new SistemaAlertasVolatilidade($pdo);
            $alertas = $sistema->obterAlertasRecentes($limite);
            
            echo json_encode(['status' => 'success', 'alertas' => $alertas]);
            exit;
        }

        // API endpoint para marcar alerta como lido
        if (isset($_POST['action']) && $_POST['action'] === 'marcar_lido') {
            header('Content-Type: application/json');
            
            $alerta_id = intval($_POST['alerta_id'] ?? 0);
            $usuario_id = intval($_SESSION['usuario']['id'] ?? 0);
            
            if ($alerta_id > 0 && $usuario_id > 0) {
                $sistema = new SistemaAlertasVolatilidade($pdo);
                $sucesso = $sistema->marcarAlertaComoLido($alerta_id, $usuario_id);
                
                echo json_encode(['status' => $sucesso ? 'success' : 'error']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos']);
            }
            exit;
        }
    }
}
?>
