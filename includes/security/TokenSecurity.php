<?php
/**
 * Classe para gerenciar segurança de tokens de aprovação
 */
class TokenSecurity {
    
    private $pdo;
    private $maxAttempts = 5;
    private $tokenLength = 128; // Aumentado para 128 caracteres
    private $expirationDays = 7; // Reduzido para 7 dias
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Gerar token seguro usando múltiplas fontes de entropia
     */
    public function generateSecureToken() {
        // Combinar múltiplas fontes de entropia
        $entropy = [
            random_bytes(32), // 256 bits de entropia
            uniqid(mt_rand(), true), // Timestamp + microtime
            bin2hex(openssl_random_pseudo_bytes(16)), // OpenSSL
            hash('sha256', microtime(true) . mt_rand() . getmypid()) // Hash adicional
        ];
        
        // Combinar todas as fontes
        $combined = implode('', $entropy);
        
        // Gerar token final com hash SHA-512
        $token = hash('sha512', $combined);
        
        // Adicionar timestamp para tornar único
        $timestamp = base64_encode(pack('N', time()));
        $token = substr($token, 0, $this->tokenLength - strlen($timestamp)) . $timestamp;
        
        return $token;
    }
    
    /**
     * Criar token de aprovação com segurança
     */
    public function createApprovalToken($orcamentoId) {
        try {
            // Gerar token único
            do {
                $token = $this->generateSecureToken();
                $exists = $this->tokenExists($token);
            } while ($exists);
            
            // Calcular expiração
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->expirationDays} days"));
            
            // Inserir token no banco
            $sql = "UPDATE ORCAMENTOS SET 
                    token_aprovacao = :token,
                    token_expires_at = :expires_at,
                    token_attempts = 0,
                    token_ip_address = NULL,
                    token_used_at = NULL
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                'token' => $token,
                'expires_at' => $expiresAt,
                'id' => $orcamentoId
            ]);
            
            if ($result) {
                $this->logSecurityEvent('token_created', $orcamentoId, $token);
                return $token;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('token_creation_error', $orcamentoId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validar token com múltiplas verificações de segurança
     */
    public function validateToken($token, $clientIp = null) {
        try {
            // Buscar dados do token
            $sql = "SELECT id, token_expires_at, token_attempts, token_used_at, 
                           token_ip_address, status, data_validade
                    FROM ORCAMENTOS 
                    WHERE token_aprovacao = :token";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['token' => $token]);
            $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$orcamento) {
                $this->logSecurityEvent('token_not_found', null, $token);
                return ['valid' => false, 'error' => 'Token inválido'];
            }
            
            // Verificar se já foi usado
            if ($orcamento['token_used_at']) {
                $this->logSecurityEvent('token_already_used', $orcamento['id'], $token);
                return ['valid' => false, 'error' => 'Token já foi utilizado'];
            }
            
            // Verificar expiração
            if ($orcamento['token_expires_at'] && strtotime($orcamento['token_expires_at']) < time()) {
                $this->logSecurityEvent('token_expired', $orcamento['id'], $token);
                return ['valid' => false, 'error' => 'Token expirado'];
            }
            
            // Verificar tentativas excessivas
            if ($orcamento['token_attempts'] >= $this->maxAttempts) {
                $this->logSecurityEvent('token_max_attempts', $orcamento['id'], $token);
                return ['valid' => false, 'error' => 'Muitas tentativas. Token bloqueado'];
            }
            
            // Verificar se orçamento não foi cancelado
            if ($orcamento['status'] === 'cancelado') {
                $this->logSecurityEvent('orcamento_cancelled', $orcamento['id'], $token);
                return ['valid' => false, 'error' => 'Orçamento foi cancelado'];
            }
            
            // Verificar validade do orçamento
            if ($orcamento['data_validade'] && strtotime($orcamento['data_validade']) < time()) {
                $this->logSecurityEvent('orcamento_expired', $orcamento['id'], $token);
                return ['valid' => false, 'error' => 'Orçamento expirado'];
            }
            
            // Incrementar tentativas
            $this->incrementAttempts($orcamento['id'], $clientIp);
            
            return [
                'valid' => true, 
                'orcamento_id' => $orcamento['id'],
                'attempts' => $orcamento['token_attempts'] + 1
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent('token_validation_error', null, $e->getMessage());
            return ['valid' => false, 'error' => 'Erro interno de validação'];
        }
    }
    
    /**
     * Marcar token como usado
     */
    public function markTokenAsUsed($orcamentoId, $clientIp = null) {
        try {
            $sql = "UPDATE ORCAMENTOS SET 
                    token_used_at = CURRENT_TIMESTAMP,
                    token_ip_address = :ip
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                'ip' => $clientIp,
                'id' => $orcamentoId
            ]);
            
            if ($result) {
                $this->logSecurityEvent('token_used', $orcamentoId, $clientIp);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('token_usage_error', $orcamentoId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementar tentativas de uso
     */
    private function incrementAttempts($orcamentoId, $clientIp = null) {
        try {
            $sql = "UPDATE ORCAMENTOS SET 
                    token_attempts = token_attempts + 1,
                    token_ip_address = :ip
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'ip' => $clientIp,
                'id' => $orcamentoId
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao incrementar tentativas: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar se token já existe
     */
    private function tokenExists($token) {
        $sql = "SELECT COUNT(*) as count FROM ORCAMENTOS WHERE token_aprovacao = :token";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token' => $token]);
        return $stmt->fetch()['count'] > 0;
    }
    
    /**
     * Invalidar token (para casos de segurança)
     */
    public function invalidateToken($orcamentoId) {
        try {
            $sql = "UPDATE ORCAMENTOS SET 
                    token_aprovacao = NULL,
                    token_expires_at = NULL,
                    token_used_at = NULL,
                    token_attempts = 0
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(['id' => $orcamentoId]);
            
            if ($result) {
                $this->logSecurityEvent('token_invalidated', $orcamentoId, 'Security measure');
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('token_invalidation_error', $orcamentoId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpar tokens expirados
     */
    public function cleanupExpiredTokens() {
        try {
            $sql = "UPDATE ORCAMENTOS SET 
                    token_aprovacao = NULL,
                    token_expires_at = NULL,
                    token_used_at = NULL,
                    token_attempts = 0
                    WHERE token_expires_at < NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute();
            
            $this->logSecurityEvent('tokens_cleanup', null, "Cleaned expired tokens");
            
            return $result;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('cleanup_error', null, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log de eventos de segurança
     */
    private function logSecurityEvent($event, $orcamentoId, $details) {
        try {
            $logData = [
                'event' => $event,
                'orcamento_id' => $orcamentoId,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Log para arquivo
            $logDir = __DIR__ . '/../logs';
            $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
            
            // Criar diretório se não existir
            if (!is_dir($logDir)) {
                if (!mkdir($logDir, 0755, true)) {
                    error_log("Erro: Não foi possível criar o diretório de logs: {$logDir}");
                    return;
                }
            }
            
            $logEntry = date('Y-m-d H:i:s') . " - {$event} - Orçamento: {$orcamentoId} - IP: {$logData['ip_address']} - {$details}\n";
            
            // Tentar escrever no arquivo com tratamento de erro
            if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
                error_log("Erro: Não foi possível escrever no arquivo de log: {$logFile}");
            }
            
            // Log para banco (opcional)
            $this->logToDatabase($logData);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar log de segurança: " . $e->getMessage());
        }
    }
    
    /**
     * Log para banco de dados
     */
    private function logToDatabase($logData) {
        try {
            // Criar tabela de logs se não existir
            $sql = "CREATE TABLE IF NOT EXISTS security_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event VARCHAR(100) NOT NULL,
                orcamento_id INT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event (event),
                INDEX idx_orcamento (orcamento_id),
                INDEX idx_created_at (created_at)
            )";
            
            $this->pdo->exec($sql);
            
            // Inserir log
            $sql = "INSERT INTO security_logs (event, orcamento_id, details, ip_address, user_agent) 
                    VALUES (:event, :orcamento_id, :details, :ip_address, :user_agent)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($logData);
            
        } catch (Exception $e) {
            error_log("Erro ao salvar log no banco: " . $e->getMessage());
        }
    }
    
    /**
     * Obter estatísticas de segurança
     */
    public function getSecurityStats() {
        try {
            $stats = [];
            
            // Tokens ativos
            $sql = "SELECT COUNT(*) as count FROM ORCAMENTOS WHERE token_aprovacao IS NOT NULL AND token_expires_at > NOW()";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats['tokens_ativos'] = $stmt->fetch()['count'];
            
            // Tokens expirados
            $sql = "SELECT COUNT(*) as count FROM ORCAMENTOS WHERE token_expires_at < NOW()";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats['tokens_expirados'] = $stmt->fetch()['count'];
            
            // Tentativas bloqueadas
            $sql = "SELECT COUNT(*) as count FROM ORCAMENTOS WHERE token_attempts >= :max_attempts";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['max_attempts' => $this->maxAttempts]);
            $stats['tokens_bloqueados'] = $stmt->fetch()['count'];
            
            return $stats;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
