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
     * Parsear URI removendo parâmetros GET
     */
    private function parseUri() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return trim($uri, '/');
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
        if ($this->api_key !== API_KEY) {
            $this->sendResponse(401, [
                'error' => 'Unauthorized',
                'message' => 'API Key inválida ou não fornecida'
            ]);
        }
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
        // Validar API Key para todas as rotas
        $this->validateApiKey();
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $this->request_method) {
                $pattern = '#^' . $route['pattern'] . '$#';
                
                if (preg_match($pattern, $this->request_uri, $matches)) {
                    array_shift($matches); // Remove o match completo
                    
                    try {
                        call_user_func_array($route['callback'], $matches);
                        return;
                    } catch (Exception $e) {
                        $this->sendResponse(500, [
                            'error' => 'Internal Server Error',
                            'message' => 'Erro interno do servidor'
                        ]);
                    }
                }
            }
        }
        
        // Rota não encontrada
        $this->sendResponse(404, [
            'error' => 'Not Found',
            'message' => 'Endpoint não encontrado'
        ]);
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
