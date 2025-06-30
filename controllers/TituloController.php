<?php
/**
 * TituloController - Controlador para operações de títulos
 * Implementa as melhores práticas para APIs REST em PHP puro
 */

require_once __DIR__ . '/../core/Database.php';

function api_linkpix($dbConnection, $titulo) {
    // Função para gerar link PIX
    $titulo = $dbConnection->real_escape_string($titulo);
    $stmt = $dbConnection->prepare("SELECT qrcode FROM sis_qrpix WHERE titulo = ? LIMIT 1");
    $stmt->bind_param("s", $titulo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'link_pix' => $row['qrcode']
        ];
    }

    return [
        'error' => 'Título não encontrado ou sem link PIX'
    ];
}

class TituloController {
    private $db;
    private $router;
    
    public function __construct($router) {
        $this->db = new Database();
        $this->router = $router;
    }

    /**
     * Método privado para buscar QR Code PIX (uso interno)
     */
    private function api_link_pix($titulo) {
        try {
            $sql = "SELECT qrcode FROM sis_qrpix WHERE titulo = ? LIMIT 1";
            $result = $this->db->fetchOne($sql, [$titulo]);
            
            return $result ? $result['qrcode'] : null;
            
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * GET /titulos/{id} - Buscar título específico
     */
    public function show($id) {
        try {
            // Validar parâmetro
            if (empty($id)) {
                $this->router->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'ID do título é obrigatório'
                ]);
            }
            
            $id = $this->router->sanitizeString($id);
            
            $query = "SELECT sis_lanc.*, sis_qrpix.qrcode AS pix
                     FROM sis_lanc
                     LEFT JOIN sis_qrpix ON sis_lanc.uuid_lanc = sis_qrpix.titulo
                     WHERE sis_lanc.id = ? OR sis_lanc.uuid_lanc = ?
                     LIMIT 1";
            
            $titulo = $this->db->fetchOne($query, [$id, $id]);
            
            if (!$titulo) {
                $this->router->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            // Adicionar PIX se disponível
            $titulo = $this->addPixData($titulo);
            
            // Buscar títulos atrelados
            $queryAtrelados = "SELECT * FROM sis_mlanc WHERE idlanc = ? ORDER BY id";
            $atrelados = $this->db->executeQuery($queryAtrelados, [$titulo['id']]);
            
            if (!empty($atrelados)) {
                $titulo['atrelados'] = $atrelados;
            }
            
            $this->router->sendResponse(200, [
                'titulo' => $titulo
            ]);
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }
    
    /**
     * GET /titulos - Listar títulos com paginação
     */
    public function index() {
        try {
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
            $status = isset($_GET['status']) ? $this->router->sanitizeString($_GET['status']) : null;
            $cliente = isset($_GET['cliente']) ? $this->router->sanitizeString($_GET['cliente']) : null;
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if ($status) {
                $whereConditions[] = "vtab_titulos.status = ?";
                $params[] = $status;
            }
            
            if ($cliente) {
                $whereConditions[] = "(vtab_titulos.login = ? OR vtab_titulos.cpf_cnpj = ?)";
                $params[] = $cliente;
                $params[] = $cliente;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Query para contar total
            $countQuery = "SELECT COUNT(*) as total FROM vtab_titulos $whereClause";
            $totalResult = $this->db->fetchOne($countQuery, $params);
            $total = $totalResult['total'];
            
            // Query principal
            $query = "SELECT uuid_lanc AS uuid, vtab_titulos.titulo, valor, valorpag, datavenc, 
                            nossonum, linhadig, nome, login, cpf_cnpj, tipo, email, endereco, 
                            numero, bairro, complemento, cidade, estado, cep, status, cli_ativado, 
                            uuid_lanc, sis_qrpix.qrcode AS pix
                     FROM vtab_titulos
                     LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     $whereClause
                     ORDER BY vtab_titulos.titulo
                     LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $titulos = $this->db->executeQuery($query, $params);
            
            // Adicionar dados PIX
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            $totalPages = ceil($total / $limit);
            
            $this->router->sendResponse(200, [
                'titulos' => $titulos,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $total,
                    'per_page' => $limit,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ]);
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }
    
    /**
     * GET /titulos/cliente/{cliente} - Buscar títulos por cliente
     */
    public function getByCliente($cliente) {
        try {
            if (empty($cliente)) {
                $this->router->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'Identificação do cliente é obrigatória'
                ]);
            }
            
            $cliente = $this->router->sanitizeString($cliente);
            $status = isset($_GET['status']) ? $this->router->sanitizeString($_GET['status']) : null;
            
            $query = "SELECT uuid_lanc AS uuid, login, nome, vtab_titulos.titulo, status, 
                            cli_ativado, tipo, valor, linhadig, datavenc, login, cpf_cnpj, 
                            sis_qrpix.qrcode AS pix
                     FROM vtab_titulos
                     LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     WHERE (login = ? OR cpf_cnpj = ?) AND deltitulo = 0";
            
            $params = [$cliente, $cliente];
            
            if ($status) {
                $query .= " AND status = ?";
                $params[] = $status;
            }
            
            $query .= " ORDER BY vtab_titulos.titulo";
            
            $titulos = $this->db->executeQuery($query, $params);
            
            // Adicionar dados PIX
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            if (empty($titulos)) {
                $this->router->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Nenhum título encontrado para este cliente'
                ]);
            }
            
            $this->router->sendResponse(200, [
                'total' => count($titulos),
                'titulos' => $titulos
            ]);
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }
    
    /**
     * POST /titulos/search - Buscar múltiplos títulos
     */
    public function search() {
        try {
            $data = $this->router->getInputData();
            
            if (!$data) {
                $this->router->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'Dados de busca são obrigatórios'
                ]);
            }
            
            $logins = isset($data['login']) && is_array($data['login']) ? $data['login'] : [];
            $cpfs = isset($data['cpf_cnpj']) && is_array($data['cpf_cnpj']) ? $data['cpf_cnpj'] : [];
            $status = isset($data['status']) ? $this->router->sanitizeString($data['status']) : null;
            
            if (empty($logins) && empty($cpfs)) {
                $this->router->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'Pelo menos um login ou CPF/CNPJ deve ser fornecido'
                ]);
            }
            
            $allParams = array_merge($logins, $cpfs);
            $loginPlaceholders = str_repeat('?,', count($logins));
            $cpfPlaceholders = str_repeat('?,', count($cpfs));
            
            $loginPlaceholders = rtrim($loginPlaceholders, ',');
            $cpfPlaceholders = rtrim($cpfPlaceholders, ',');
            
            $whereConditions = [];
            if (!empty($logins)) {
                $whereConditions[] = "login IN ($loginPlaceholders)";
            }
            if (!empty($cpfs)) {
                $whereConditions[] = "cpf_cnpj IN ($cpfPlaceholders)";
            }
            
            $query = "SELECT uuid_lanc AS uuid, login, nome, vtab_titulos.titulo, status, 
                            cli_ativado, tipo, valor, linhadig, datavenc, login, cpf_cnpj, 
                            sis_qrpix.qrcode AS pix
                     FROM vtab_titulos
                     LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     WHERE (" . implode(' OR ', $whereConditions) . ") AND deltitulo = 0";
            
            if ($status) {
                $query .= " AND status = ?";
                $allParams[] = $status;
            }
            
            $query .= " ORDER BY vtab_titulos.titulo";
            
            $titulos = $this->db->executeQuery($query, $allParams);
            
            // Adicionar dados PIX
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            $this->router->sendResponse(200, [
                'total' => count($titulos),
                'titulos' => $titulos
            ]);
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }
    
    /**
     * PUT /titulos/{uuid}/receber - Receber título
     */
    public function receber($uuid) {
        try {
            $data = $this->router->getInputData();
            
            if (!$data) {
                $this->router->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'Dados são obrigatórios'
                ]);
            }
            
            $this->router->validateRequiredParams($data, ['valor', 'forma']);
            
            $uuid = $this->router->sanitizeString($uuid);
            $coletor = isset($data['coletor']) && !empty($data['coletor']) ? 
                      $this->router->sanitizeString($data['coletor']) : 'API';
            $valorPago = floatval($data['valor']);
            $formaPag = $this->router->sanitizeString($data['forma']);
            
            // Verificar se o título existe
            $consultaQuery = "SELECT id, login, valor FROM sis_lanc WHERE uuid_lanc = ?";
            $titulo = $this->db->fetchOne($consultaQuery, [$uuid]);
            
            if (!$titulo) {
                $this->router->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            $this->db->beginTransaction();
            
            try {
                // Atualizar título
                $updateQuery = "UPDATE sis_lanc
                               SET coletor = ?, valorpag = ?, datapag = NOW(), status = 'pago', formapag = ?
                               WHERE uuid_lanc = ? AND status <> 'pago'";
                
                $affected = $this->db->executeQuery($updateQuery, [$coletor, $valorPago, $formaPag, $uuid]);
                
                if ($affected === 0) {
                    $this->db->rollback();
                    $this->router->sendResponse(400, [
                        'error' => 'Bad Request',
                        'message' => 'Título já foi pago ou não encontrado'
                    ]);
                }
                
                // Inserir no caixa
                $caixaQuery = "INSERT INTO sis_caixa
                              (uuid_caixa, usuario, data, historico, entrada, tipomov, planodecontas)
                              VALUES (UUID(), ?, NOW(), ?, ?, 'aut', 'Outros')";
                
                $historico = "Recebimento do titulo {$titulo['id']} via API / {$titulo['login']}";
                $this->db->executeQuery($caixaQuery, [$coletor, $historico, $valorPago]);
                
                $this->db->commit();
                
                $this->router->sendResponse(200, [
                    'message' => 'Título recebido com sucesso',
                    'titulo' => [
                        'uuid' => $uuid,
                        'valor_pago' => $valorPago,
                        'forma_pagamento' => $formaPag,
                        'coletor' => $coletor
                    ]
                ]);
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }
    
    /**
     * PUT /titulos/{uuid}/estornar - Estornar título
     */
    public function estornar($uuid) {
        try {
            $data = $this->router->getInputData();
            $uuid = $this->router->sanitizeString($uuid);
            $usuario = isset($data['usuario']) && !empty($data['usuario']) ? 
                      $this->router->sanitizeString($data['usuario']) : 'API';
            
            // Verificar se o título existe
            $consultaQuery = "SELECT id, valorpag, login FROM sis_lanc WHERE uuid_lanc = ?";
            $titulo = $this->db->fetchOne($consultaQuery, [$uuid]);
            
            if (!$titulo) {
                $this->router->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            $this->db->beginTransaction();
            
            try {
                // Estornar título
                $updateQuery = "UPDATE sis_lanc
                               SET datapag = NULL, status = 'aberto', valorpag = NULL
                               WHERE uuid_lanc = ? AND status = 'pago'";
                
                $affected = $this->db->executeQuery($updateQuery, [$uuid]);
                
                if ($affected === 0) {
                    $this->db->rollback();
                    $this->router->sendResponse(400, [
                        'error' => 'Bad Request',
                        'message' => 'Título não está pago ou não encontrado'
                    ]);
                }
                
                // Inserir estorno no caixa
                $caixaQuery = "INSERT INTO sis_caixa 
                              (uuid_caixa, usuario, data, historico, saida, tipomov, planodecontas)
                              VALUES (UUID(), ?, NOW(), ?, ?, 'aut', 'Outros')";
                
                $historico = "Titulo {$titulo['id']} estornado via API / {$titulo['login']}";
                $this->db->executeQuery($caixaQuery, [$usuario, $historico, $titulo['valorpag']]);
                
                $this->db->commit();
                
                $this->router->sendResponse(200, [
                    'message' => 'Título estornado com sucesso',
                    'titulo' => [
                        'uuid' => $uuid,
                        'valor_estornado' => $titulo['valorpag'],
                        'usuario' => $usuario
                    ]
                ]);
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }
    
    /**
     * PUT /titulos/{uuid} - Editar título
     */
    public function update($uuid) {
        try {
            $data = $this->router->getInputData();
            
            if (!$data) {
                $this->router->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'Dados para atualização são obrigatórios'
                ]);
            }
            
            $uuid = $this->router->sanitizeString($uuid);
            
            // Verificar se o título existe
            $consultaQuery = "SELECT * FROM sis_lanc WHERE uuid_lanc = ? LIMIT 1";
            $titulo = $this->db->fetchOne($consultaQuery, [$uuid]);
            
            if (!$titulo) {
                $this->router->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            // Campos permitidos para edição
            $allowedFields = array_keys($titulo);
            $updateFields = [];
            $updateParams = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields) && $field !== 'id' && $field !== 'uuid_lanc') {
                    $updateFields[] = "$field = ?";
                    $updateParams[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                $this->router->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'Nenhum campo válido fornecido para atualização'
                ]);
            }
            
            $updateParams[] = $uuid;
            
            $updateQuery = "UPDATE sis_lanc SET " . implode(', ', $updateFields) . " WHERE uuid_lanc = ?";
            $this->db->executeQuery($updateQuery, $updateParams);
            
            $this->router->sendResponse(200, [
                'message' => 'Título atualizado com sucesso',
                'titulo' => [
                    'uuid' => $uuid,
                    'campos_atualizados' => array_keys($data)
                ]
            ]);
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }
    
    /**
     * DELETE /titulos/{uuid} - Excluir título
     */
    public function delete($uuid) {
        try {
            $uuid = $this->router->sanitizeString($uuid);
            
            $deleteQuery = "DELETE FROM sis_lanc WHERE uuid_lanc = ?";
            $affected = $this->db->executeQuery($deleteQuery, [$uuid]);
            
            if ($affected === 0) {
                $this->router->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            $this->router->sendResponse(200, [
                'message' => 'Título excluído com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->router->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Erro interno do servidor'
            ]);
        }
    }

    
    /**
     * Adicionar dados PIX ao título
     */
    private function addPixData($titulo) {
        $this->api_link_pix($titulo);
        return $titulo;
    }

}
?>
