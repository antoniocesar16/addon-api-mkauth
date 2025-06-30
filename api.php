<?php
/**
 * Exemplo de configuração de rotas para o TituloController
 * Este arquivo mostra como integrar o controller com o APIRouter
 */

require_once __DIR__ . '/core/APIRouter.php';
require_once __DIR__ . '/controllers/TituloController.php';
require_once __DIR__ . '/config/config.php';

// Configurar headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Inicializar o router
$router = new APIRouter();

// Inicializar o controller
$tituloController = new TituloController($router);

// Definir rotas para títulos

// GET /api/titulos - Listar títulos com paginação
$router->addRoute('GET', 'api/titulos', function() use ($tituloController) {
    $tituloController->index();
});

// GET /api/titulos/{id} - Buscar título específico
$router->addRoute('GET', 'api/titulos/([^/]+)', function($id) use ($tituloController) {
    $tituloController->show($id);
});

// GET /api/titulos/cliente/{cliente} - Buscar títulos por cliente
$router->addRoute('GET', 'api/titulos/cliente/([^/]+)', function($cliente) use ($tituloController) {
    $tituloController->getByCliente($cliente);
});

// POST /api/titulos/search - Buscar múltiplos títulos
$router->addRoute('POST', 'api/titulos/search', function() use ($tituloController) {
    $tituloController->search();
});

// PUT /api/titulos/{uuid}/receber - Receber título
$router->addRoute('PUT', 'api/titulos/([^/]+)/receber', function($uuid) use ($tituloController) {
    $tituloController->receber($uuid);
});

// PUT /api/titulos/{uuid}/estornar - Estornar título
$router->addRoute('PUT', 'api/titulos/([^/]+)/estornar', function($uuid) use ($tituloController) {
    $tituloController->estornar($uuid);
});

// PUT /api/titulos/{uuid} - Editar título
$router->addRoute('PUT', 'api/titulos/([^/]+)', function($uuid) use ($tituloController) {
    $tituloController->update($uuid);
});

// DELETE /api/titulos/{uuid} - Excluir título
$router->addRoute('DELETE', 'api/titulos/([^/]+)', function($uuid) use ($tituloController) {
    $tituloController->delete($uuid);
});

// Processar a requisição
$router->dispatch();
?>
