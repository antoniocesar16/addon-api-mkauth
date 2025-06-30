<?php
/**
 * Controller para operações de clientes
 */
class ClienteController {
    private $db;
    private $api;
    
    public function __construct($database, $api_router) {
        $this->db = $database;
        $this->api = $api_router;
    }
    
    /**
     * Buscar cliente por código
     */
    public function buscarPorCodigo($codigo = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($codigo === null) {
            $codigo = $_GET['codigo'] ?? null;
        }
        
        // Validar parâmetro obrigatório
        if (empty($codigo)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetro "codigo" é obrigatório'
            ]);
        }
        
        // Sanitizar código
        $codigo = $this->api->sanitizeString($codigo);
        
        try {
            // Usar query preparada
            $query = "SELECT * FROM sis_cliente WHERE codigo = ? LIMIT 1";
            $cliente = $this->db->fetchOne($query, [$codigo]);
            
            if (!$cliente) {
                $this->api->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Cliente não encontrado'
                ]);
            }
            
            $this->api->sendResponse(200, [
                'cliente' => $cliente
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar cliente'
            ]);
        }
    }
    
    /**
     * Listar clientes com paginação
     */
    public function listar() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        $grupo = $_GET['grupo'] ?? null;
        $ativo = $_GET['ativo'] ?? null;
        
        try {
            $where_conditions = [];
            $params = [];
            
            if ($grupo) {
                $where_conditions[] = "grupo = ?";
                $params[] = $this->api->sanitizeString($grupo);
            }
            
            if ($ativo !== null) {
                $where_conditions[] = "cli_ativado = ?";
                $params[] = $ativo === '1' ? 's' : 'n';
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Query para contar total
            $count_query = "SELECT COUNT(*) as total FROM sis_cliente $where_clause";
            $total_result = $this->db->fetchOne($count_query, $params);
            $total = $total_result['total'] ?? 0;
            
            // Query para buscar dados
            $query = "SELECT * FROM sis_cliente $where_clause ORDER BY codigo DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $clientes = $this->db->executeQuery($query, $params);
            
            $this->api->sendResponse(200, [
                'clientes' => $clientes,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao listar clientes'
            ]);
        }
    }
    
    /**
     * Criar novo cliente
     */
    public function criar() {
        $input = $this->api->getInputData();
        
        if (!$input) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Dados JSON inválidos'
            ]);
        }
        
        // Campos obrigatórios
        $required_fields = ['codigo', 'nome'];
        $this->api->validateRequiredParams($input, $required_fields);
        
        try {
            // Verificar se código já existe
            $existing = $this->db->fetchOne("SELECT codigo FROM sis_cliente WHERE codigo = ?", [$input['codigo']]);
            
            if ($existing) {
                $this->api->sendResponse(409, [
                    'error' => 'Conflict',
                    'message' => 'Cliente com este código já existe'
                ]);
            }
            
            // Preparar dados para inserção
            $data = [
                'codigo' => $this->api->sanitizeString($input['codigo']),
                'nome' => $this->api->sanitizeString($input['nome']),
                'grupo' => $this->api->sanitizeString($input['grupo'] ?? ''),
                'cli_ativado' => isset($input['ativo']) && $input['ativo'] ? 's' : 'n',
                'data_ins' => date('Y-m-d H:i:s')
            ];
            
            $query = "INSERT INTO sis_cliente (codigo, nome, grupo, cli_ativado, data_ins) VALUES (?, ?, ?, ?, ?)";
            $result = $this->db->executeQuery($query, array_values($data));
            
            if ($result > 0) {
                $this->api->sendResponse(201, [
                    'message' => 'Cliente criado com sucesso',
                    'cliente_id' => $this->db->getLastInsertId()
                ]);
            } else {
                throw new Exception('Erro ao inserir cliente');
            }
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao criar cliente'
            ]);
        }
    }
}
?>
