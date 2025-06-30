<?php
/**
 * Classe principal da API
 */
class APIRouter {
    private $routes = [];
    private $request_method;
    private $request_uri;
    private $api_key;
    
    public function __construct() {
        $this->request_method = $_SERVER['REQUEST_METHOD'];
        $this->request_uri = $this->parseUri();
        $this->api_key = $this->getApiKey();
        
        // Tratar OPTIONS para CORS
        if ($this->request_method === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Parsear URI removendo parâmetros GET e prefixo da pasta
     */
    private function parseUri() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remover o prefixo da pasta base das URIs
        $basePath = '/addon-api-mkauth';
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Garantir que sempre comece com /
        if (substr($uri, 0, 1) !== '/') {
            $uri = '/' . $uri;
        }
        
        // Remover / final se existir e não for a raiz
        if (strlen($uri) > 1 && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }
        
        // Debug: log da URI processada
        error_log("APIRouter Debug - Original URI: " . $_SERVER['REQUEST_URI'] . " | Processed URI: " . $uri);
        
        return $uri;
    }
    
    /**
     * Obter URI limpa para debug
     */
    public function getCleanUri() {
        return $this->request_uri;
    }
    
    /**
     * Obter API Key do header ou GET
     */
    private function getApiKey() {
        // Primeiro tenta o header
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }
        
        // Depois tenta o GET (compatibilidade com código atual)
        if (isset($_GET['api'])) {
            return $_GET['api'];
        }
        
        return null;
    }
    
    /**
     * Validar API Key
     */
    private function validateApiKey() {
        // Pular validação para rota de info
        if (strpos($this->request_uri, '/api/v1/info') !== false) {
            return;
        }
        
        if (empty($this->api_key)) {
            $this->sendResponse(401, [
                'error' => 'Unauthorized',
                'message' => 'API Key é obrigatória'
            ]);
        }
        
        // Aqui você pode implementar validação mais robusta da API Key
        // Por enquanto aceita qualquer key não vazia
        if (defined('API_KEY') && $this->api_key !== API_KEY) {
            $this->sendResponse(401, [
                'error' => 'Unauthorized',
                'message' => 'API Key inválida'
            ]);
        }
    }
    
    /**
     * Adicionar uma rota GET
     */
    public function get($pattern, $callback) {
        $this->addRoute('GET', $pattern, $callback);
    }
    
    /**
     * Adicionar uma rota POST
     */
    public function post($pattern, $callback) {
        $this->addRoute('POST', $pattern, $callback);
    }
    
    /**
     * Adicionar uma rota PUT
     */
    public function put($pattern, $callback) {
        $this->addRoute('PUT', $pattern, $callback);
    }
    
    /**
     * Adicionar uma rota DELETE
     */
    public function delete($pattern, $callback) {
        $this->addRoute('DELETE', $pattern, $callback);
    }
    
    /**
     * Adicionar uma rota
     */
    public function addRoute($method, $pattern, $callback) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }
    
    /**
     * Processar a requisição
     */
    public function dispatch() {
        // Validar API Key para todas as rotas (exceto info)
        $this->validateApiKey();
        
        // Debug: mostrar informações da requisição
        error_log("APIRouter Debug - Method: {$this->request_method}, URI: {$this->request_uri}");
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $this->request_method) {
                $pattern = '#^' . $route['pattern'] . '$#';
                
                error_log("APIRouter Debug - Checking pattern: {$route['pattern']} against URI: {$this->request_uri}");
                
                if (preg_match($pattern, $this->request_uri, $matches)) {
                    array_shift($matches); // Remove o match completo
                    
                    error_log("APIRouter Debug - Route matched! Calling callback");
                    
                    try {
                        call_user_func_array($route['callback'], $matches);
                        return;
                    } catch (Exception $e) {
                        error_log("APIRouter Error: " . $e->getMessage());
                        $this->sendResponse(500, [
                            'error' => 'Internal Server Error',
                            'message' => 'Erro interno do servidor',
                            'debug' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // Rota não encontrada
        $this->sendResponse(404, [
            'error' => 'Not Found',
            'message' => 'Endpoint não encontrado',
            'debug' => [
                'requested_uri' => $this->request_uri,
                'method' => $this->request_method,
                'available_routes' => $this->getAvailableRoutes()
            ]
        ]);
    }
    
    /**
     * Obter rotas disponíveis para debug
     */
    private function getAvailableRoutes() {
        $routes = [];
        foreach ($this->routes as $route) {
            $routes[] = $route['method'] . ' ' . $route['pattern'];
        }
        return $routes;
    }
    
    /**
     * Enviar resposta JSON
     */
    public function sendResponse($status_code, $data) {
        http_response_code($status_code);
        
        $response = [
            'success' => $status_code >= 200 && $status_code < 300,
            'status_code' => $status_code,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Obter dados do POST/PUT
     */
    public function getInputData() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    /**
     * Validar parâmetros obrigatórios
     */
    public function validateRequiredParams($params, $required_fields) {
        $missing = [];
        
        foreach ($required_fields as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetros obrigatórios ausentes: ' . implode(', ', $missing)
            ]);
        }
    }
    
    /**
     * Sanitizar string para prevenir SQL Injection
     */
    public function sanitizeString($string) {
        return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
    }
}
?>
