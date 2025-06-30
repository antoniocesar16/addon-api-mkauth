<?php
/**
 * Arquivo principal da API - Ponto de entrada
 * Implementa as melhores práticas para APIs REST em PHP puro
 */

// Incluir configurações
require_once __DIR__ . '/config/config.php';

// Incluir classes principais
require_once __DIR__ . '/core/APIRouter.php';
require_once __DIR__ . '/core/Database.php';

// Incluir controllers
require_once __DIR__ . '/controllers/ClienteController.php';
require_once __DIR__ . '/controllers/ChamadoController.php';
require_once __DIR__ . '/controllers/TituloController.php';

try {
    // Inicializar componentes principais
    $api = new APIRouter();
    $db = new Database();
    
    // Inicializar controllers
    $clienteController = new ClienteController($db, $api);
    $chamadoController = new ChamadoController($db, $api);
    $tituloController = new TituloController($db, $api);
    
    // === ROTAS DE CLIENTES ===
    
    // GET /api/v1/clientes - Listar clientes com paginação
    $api->addRoute('GET', '/api/v1/clientes', [$clienteController, 'listar']);
    
    // GET /api/v1/clientes/buscar - Buscar cliente por código (compatibilidade)
    $api->addRoute('GET', '/api/v1/clientes/buscar', [$clienteController, 'buscarPorCodigo']);
    
    // GET /api/v1/clientes/{codigo} - Buscar cliente por código (RESTful)
    $api->addRoute('GET', '/api/v1/clientes/([^/]+)', [$clienteController, 'buscarPorCodigo']);
    
    // POST /api/v1/clientes - Criar novo cliente
    $api->addRoute('POST', '/api/v1/clientes', [$clienteController, 'criar']);
    
    $api->addRoute('PUT', '/api/v1/titulos/([^/]+)', [$tituloController, 'atualizar']);

    // === ROTAS DE CHAMADOS ===
    
    // GET /api/v1/chamados/abertos - Chamados abertos
    $api->addRoute('GET', '/api/v1/chamados/abertos', [$chamadoController, 'getChamadosAbertos']);
    
    // GET /api/v1/chamados/fechados - Chamados fechados por período
    $api->addRoute('GET', '/api/v1/chamados/fechados', [$chamadoController, 'getChamadosFechados']);
    
    // GET /api/v1/chamados/fechados/dia - Chamados fechados no dia
    $api->addRoute('GET', '/api/v1/chamados/fechados/dia', [$chamadoController, 'getChamadosFechadosDia']);
    
    // GET /api/v1/relatorios/grupos - Relatório por grupo
    $api->addRoute('GET', '/api/v1/relatorios/grupos', [$chamadoController, 'getRelatorioPorGrupo']);

    // === ROTAS DE TÍTULOS ===
    // GET /api/v1/titulos - Listar títulos (ainda não implementado)
    $api->addRoute('GET', '/api/v1/titulos', [$tituloController, 'index']);
    // buscar um titulo
    $api->addRoute('GET', '/api/v1/titulos/([^/]+)', [$tituloController, 'show']);

    // === ROTAS DE COMPATIBILIDADE (para não quebrar código existente) ===
    
    // GET /buscacliente.php - Compatibilidade com código antigo
    $api->addRoute('GET', 'buscacliente.php', [$clienteController, 'buscarPorCodigo']);
    
    // GET /chamadoaberto.php
    $api->addRoute('GET', 'chamadoaberto.php', [$chamadoController, 'getChamadosAbertos']);
    
    // GET /chamadofechado.php
    $api->addRoute('GET', 'chamadofechado.php', [$chamadoController, 'getChamadosFechados']);
    
    // GET /chamadofechadodia.php
    $api->addRoute('GET', 'chamadofechadodia.php', [$chamadoController, 'getChamadosFechadosDia']);
    


    // === ROTA DE INFORMAÇÕES DA API ===
    $api->addRoute('GET', '/api/v1/info', function() use ($api) {
        $api->sendResponse(200, [
            'api_name' => 'MK-Auth API',
            'version' => API_VERSION,
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints' => [
                'GET /api/v1/clientes' => 'Listar clientes',
                'GET /api/v1/clientes/{codigo}' => 'Buscar cliente por código',
                'POST /api/v1/clientes' => 'Criar novo cliente',
                'GET /api/v1/chamados/abertos' => 'Chamados abertos',
                'GET /api/v1/chamados/fechados' => 'Chamados fechados',
                'GET /api/v1/chamados/fechados/dia' => 'Chamados fechados no dia',
                'GET /api/v1/relatorios/grupos' => 'Relatório por grupo'
            ]
        ]);
    });
    
    // Processar a requisição
    $api->dispatch();
    
} catch (Exception $e) {
    // Log do erro (em produção, usar um sistema de log adequado)
    error_log("API Error: " . $e->getMessage());
    
    // Resposta de erro genérica
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status_code' => 500,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'error' => 'Internal Server Error',
            'message' => 'Erro interno do servidor'
        ],
        "debug" => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}



?>
