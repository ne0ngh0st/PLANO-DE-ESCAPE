<?php
/**
 * Classe para integração com a API do Bling ERP v3
 * Gerencia autenticação OAuth 2.0 e requisições
 */

require_once __DIR__ . '/../config/bling_config.php';

class BlingAPI {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $baseUrl;
    private $tokenFile;
    private $accessToken;
    private $refreshToken;
    
    public function __construct() {
        $this->clientId = BLING_CLIENT_ID;
        $this->clientSecret = BLING_CLIENT_SECRET;
        $this->redirectUri = BLING_REDIRECT_URI;
        $this->baseUrl = BLING_API_BASE_URL;
        $this->tokenFile = BLING_TOKEN_FILE;
        
        // Carregar tokens salvos
        $this->loadTokens();
    }
    
    /**
     * Verifica se está autenticado
     */
    public function isAuthenticated() {
        return !empty($this->accessToken) || !empty($this->refreshToken);
    }
    
    /**
     * Gera URL de autorização OAuth
     */
    public function getAuthorizationUrl() {
        if (empty($this->clientId)) {
            throw new Exception('Client ID não configurado. Configure BLING_CLIENT_ID no arquivo bling_config.php');
        }
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => BLING_OAUTH_STATE
        ];
        
        return BLING_AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Troca código de autorização por tokens
     */
    public function getAccessToken($code) {
        $data = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri
        ];
        
        $response = $this->requestToken($data);
        
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->refreshToken = $response['refresh_token'] ?? null;
            $this->saveTokens($response);
            return true;
        }
        
        return false;
    }
    
    /**
     * Renova o access token usando refresh token
     */
    private function refreshAccessToken() {
        if (empty($this->refreshToken)) {
            throw new Exception('Refresh token não disponível. É necessário autenticar novamente.');
        }
        
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken
        ];
        
        $response = $this->requestToken($data);
        
        if (isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            if (isset($response['refresh_token'])) {
                $this->refreshToken = $response['refresh_token'];
            }
            $this->saveTokens($response);
            return true;
        }
        
        throw new Exception('Falha ao renovar token de acesso');
    }
    
    /**
     * Faz requisição para obter/renovar tokens
     */
    private function requestToken($data) {
        $ch = curl_init(BLING_TOKEN_URL);
        
        $auth = base64_encode($this->clientId . ':' . $this->clientSecret);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Erro cURL: ' . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $errorMsg = $result['error']['description'] ?? $result['error'] ?? 'Erro desconhecido';
            throw new Exception('Erro na autenticação: ' . $errorMsg . ' (HTTP ' . $httpCode . ')');
        }
        
        return $result;
    }
    
    /**
     * Carrega tokens salvos
     */
    private function loadTokens() {
        if (file_exists($this->tokenFile)) {
            $data = json_decode(file_get_contents($this->tokenFile), true);
            
            if ($data) {
                $this->accessToken = $data['access_token'] ?? null;
                $this->refreshToken = $data['refresh_token'] ?? null;
                
                // Verificar se o token expirou
                if (isset($data['expires_at']) && time() > $data['expires_at']) {
                    // Token expirado, tentar renovar
                    try {
                        $this->refreshAccessToken();
                    } catch (Exception $e) {
                        // Se falhar, limpar tokens
                        $this->accessToken = null;
                        $this->refreshToken = null;
                    }
                }
            }
        }
    }
    
    /**
     * Salva tokens
     */
    private function saveTokens($tokenData) {
        $data = [
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $this->refreshToken,
            'expires_in' => $tokenData['expires_in'] ?? 3600,
            'expires_at' => time() + ($tokenData['expires_in'] ?? 3600),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->tokenFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Faz requisição GET para a API
     */
    private function get($endpoint, $params = []) {
        return $this->request('GET', $endpoint, $params);
    }
    
    /**
     * Faz requisição para a API do Bling
     */
    private function request($method, $endpoint, $params = [], $retry = true) {
        if (!$this->isAuthenticated()) {
            throw new Exception('Não autenticado. Execute a autorização OAuth primeiro.');
        }
        
        $url = $this->baseUrl . $endpoint;
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($method !== 'GET' && !empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Erro cURL: ' . $error);
        }
        
        // Token expirado, tentar renovar e fazer requisição novamente
        if ($httpCode === 401 && $retry) {
            try {
                $this->refreshAccessToken();
                return $this->request($method, $endpoint, $params, false);
            } catch (Exception $e) {
                throw new Exception('Sessão expirada. É necessário autenticar novamente.');
            }
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $result['error']['description'] ?? 'Erro na requisição';
            throw new Exception($errorMsg . ' (HTTP ' . $httpCode . ')');
        }
        
        return $result;
    }
    
    /**
     * Busca pedidos de venda
     */
    public function getPedidos($dataInicial = null, $dataFinal = null, $pagina = 1, $limite = 100) {
        $params = [
            'pagina' => $pagina,
            'limite' => $limite
        ];
        
        if ($dataInicial) {
            $params['dataInicial'] = date('Y-m-d', strtotime($dataInicial));
        }
        
        if ($dataFinal) {
            $params['dataFinal'] = date('Y-m-d', strtotime($dataFinal));
        }
        
        return $this->get('/pedidos/vendas', $params);
    }
    
    /**
     * Busca detalhes de um pedido específico
     */
    public function getPedido($idPedido) {
        return $this->get('/pedidos/vendas/' . $idPedido);
    }
    
    /**
     * Busca produtos
     */
    public function getProdutos($pagina = 1, $limite = 100) {
        $params = [
            'pagina' => $pagina,
            'limite' => $limite
        ];
        
        return $this->get('/produtos', $params);
    }
    
    /**
     * Busca produto específico
     */
    public function getProduto($idProduto) {
        return $this->get('/produtos/' . $idProduto);
    }
    
    /**
     * Busca notas fiscais
     */
    public function getNotasFiscais($dataInicial = null, $dataFinal = null, $pagina = 1, $limite = 100) {
        $params = [
            'pagina' => $pagina,
            'limite' => $limite
        ];
        
        if ($dataInicial) {
            $params['dataEmissaoInicial'] = date('Y-m-d', strtotime($dataInicial));
        }
        
        if ($dataFinal) {
            $params['dataEmissaoFinal'] = date('Y-m-d', strtotime($dataFinal));
        }
        
        return $this->get('/notasfiscais', $params);
    }
    
    /**
     * Busca TODOS os pedidos (com paginação automática - SEM LIMITE)
     * Continua buscando até não haver mais páginas disponíveis
     */
    public function getAllPedidos($dataInicial = null, $dataFinal = null) {
        $todosPedidos = [];
        $pagina = 1;
        $limite = 100;
        
        do {
            try {
                $response = $this->getPedidos($dataInicial, $dataFinal, $pagina, $limite);
                
                if (isset($response['data']) && is_array($response['data'])) {
                    $todosPedidos = array_merge($todosPedidos, $response['data']);
                    
                    // Verificar se há mais páginas
                    $temMais = count($response['data']) === $limite;
                    $pagina++;
                } else {
                    break;
                }
                
                // Pequeno delay para não sobrecarregar a API
                usleep(200000); // 0.2 segundos
                
                // Log do progresso a cada 10 páginas
                if ($pagina % 10 === 0) {
                    error_log("BlingAPI: Buscadas {$pagina} páginas, total de " . count($todosPedidos) . " pedidos até agora...");
                }
                
            } catch (Exception $e) {
                error_log('Erro ao buscar pedidos página ' . $pagina . ': ' . $e->getMessage());
                break;
            }
            
        } while ($temMais); // SEM LIMITE - busca TODAS as páginas até acabar!
        
        error_log("BlingAPI: Sincronização completa! Total de " . count($todosPedidos) . " pedidos encontrados em {$pagina} páginas");
        return $todosPedidos;
    }
}


