<?php
/**
 * Controller para operações de títulos
 */
class TituloController {
    private $db;
    private $api;
    
    public function __construct($database, $api_router) {
        $this->db = $database;
        $this->api = $api_router;
    }
    
    /**
     * Método privado para buscar QR Code PIX (uso interno)
     */
    private function getPixQrCode($titulo) {
        try {
            $sql = "SELECT qrcode FROM sis_qrpix WHERE titulo = ? LIMIT 1";
            $result = $this->db->fetchOne($sql, [$titulo]);
            
            return $result ? $result['qrcode'] : null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Adicionar dados PIX ao título
     */
    private function addPixData($titulo) {
        if (isset($titulo['uuid_lanc'])) {
            $qrcode = $this->getPixQrCode($titulo['uuid_lanc']);
            
            if ($qrcode) {
                $titulo['pix'] = $qrcode;
                $titulo['pix_link'] = $qrcode;
                $titulo['pix_qr'] = $qrcode;
            }
        }
        
        return $titulo;
    }
    
    /**
     * Buscar título específico por ID ou UUID
     */
    public function buscarPorId($id = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($id === null) {
            $id = $_GET['id'] ?? null;
        }
        
        // Validar parâmetro obrigatório
        if (empty($id)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetro "id" é obrigatório'
            ]);
        }
        
        // Sanitizar ID
        $id = $this->api->sanitizeString($id);
        
        try {
            $query = "SELECT sis_lanc.*, sis_qrpix.qrcode AS pix
                     FROM sis_lanc
                     LEFT JOIN sis_qrpix ON sis_lanc.uuid_lanc = sis_qrpix.titulo
                     WHERE sis_lanc.id = ? OR sis_lanc.uuid_lanc = ?
                     LIMIT 1";
            
            $titulo = $this->db->fetchOne($query, [$id, $id]);
            
            if (!$titulo) {
                $this->api->sendResponse(404, [
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
            
            $this->api->sendResponse(200, [
                'titulo' => $titulo
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar título'
            ]);
        }
    }
    
    /**
     * Listar títulos com paginação
     */
    public function listar() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $status = $_GET['status'] ?? null;
        $cliente = $_GET['cliente'] ?? null;
        
        try {
            $whereConditions = [];
            $params = [];
            
            if ($status) {
                $whereConditions[] = "vtab_titulos.status = ?";
                $params[] = $this->api->sanitizeString($status);
            }
            
            if ($cliente) {
                $whereConditions[] = "(vtab_titulos.login = ? OR vtab_titulos.cpf_cnpj = ?)";
                $params[] = $this->api->sanitizeString($cliente);
                $params[] = $this->api->sanitizeString($cliente);
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Query para contar total
            $countQuery = "SELECT COUNT(*) as total FROM vtab_titulos $whereClause";
            $totalResult = $this->db->fetchOne($countQuery, $params);
            $total = $totalResult['total'] ?? 0;
            
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
            
            // Adicionar dados PIX para cada título
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            $this->api->sendResponse(200, [
                'titulos' => $titulos,
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
                'message' => 'Erro ao listar títulos'
            ]);
        }
    }
    
    /**
     * Buscar títulos por cliente
     */
    public function buscarPorCliente($cliente = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($cliente === null) {
            $cliente = $_GET['cliente'] ?? null;
        }
        
        // Validar parâmetro obrigatório
        if (empty($cliente)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetro "cliente" é obrigatório'
            ]);
        }
        
        $cliente = $this->api->sanitizeString($cliente);
        $status = $_GET['status'] ?? null;
        
        try {
            $query = "SELECT uuid_lanc AS uuid, login, nome, vtab_titulos.titulo, status, 
                            cli_ativado, tipo, valor, linhadig, datavenc, login, cpf_cnpj, 
                            sis_qrpix.qrcode AS pix
                     FROM vtab_titulos
                     LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     WHERE (login = ? OR cpf_cnpj = ?) AND deltitulo = 0";
            
            $params = [$cliente, $cliente];
            
            if ($status) {
                $query .= " AND status = ?";
                $params[] = $this->api->sanitizeString($status);
            }
            
            $query .= " ORDER BY vtab_titulos.titulo";
            
            $titulos = $this->db->executeQuery($query, $params);
            
            // Adicionar dados PIX
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            if (empty($titulos)) {
                $this->api->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Nenhum título encontrado para este cliente'
                ]);
            }
            
            $this->api->sendResponse(200, [
                'total' => count($titulos),
                'titulos' => $titulos
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar títulos por cliente'
            ]);
        }
    }
    
    /**
     * Buscar títulos em aberto por cliente
     */
    public function buscarAbertos($cliente = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($cliente === null) {
            $cliente = $_GET['cliente'] ?? null;
        }
        
        // Validar parâmetro obrigatório
        if (empty($cliente)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetro "cliente" é obrigatório'
            ]);
        }
        
        $cliente = $this->api->sanitizeString($cliente);
        
        try {
            $query = "SELECT uuid_lanc AS uuid, login, nome, vtab_titulos.titulo, status, 
                            cli_ativado, tipo, valor, linhadig, datavenc, login, cpf_cnpj, 
                            sis_qrpix.qrcode AS pix
                     FROM vtab_titulos
                     LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     WHERE (login = ? OR cpf_cnpj = ?) AND status = 'aberto' AND deltitulo = 0
                     ORDER BY vtab_titulos.titulo";
            
            $titulos = $this->db->executeQuery($query, [$cliente, $cliente]);
            
            // Adicionar dados PIX
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            $this->api->sendResponse(200, [
                'total' => count($titulos),
                'titulos' => $titulos
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar títulos em aberto'
            ]);
        }
    }
    
    /**
     * Buscar títulos vencidos por cliente
     */
    public function buscarVencidos($cliente = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($cliente === null) {
            $cliente = $_GET['cliente'] ?? null;
        }
        
        // Validar parâmetro obrigatório
        if (empty($cliente)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetro "cliente" é obrigatório'
            ]);
        }
        
        $cliente = $this->api->sanitizeString($cliente);
        
        try {
            $query = "SELECT uuid_lanc AS uuid, login, nome, vtab_titulos.titulo, status, 
                            cli_ativado, tipo, valor, linhadig, datavenc, login, cpf_cnpj, 
                            sis_qrpix.qrcode AS pix
                     FROM vtab_titulos
                     LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     WHERE (login = ? OR cpf_cnpj = ?) AND status = 'vencido' AND deltitulo = 0
                     ORDER BY vtab_titulos.titulo";
            
            $titulos = $this->db->executeQuery($query, [$cliente, $cliente]);
            
            // Adicionar dados PIX
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            $this->api->sendResponse(200, [
                'total' => count($titulos),
                'titulos' => $titulos
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar títulos vencidos'
            ]);
        }
    }
    
    /**
     * Buscar títulos pagos por cliente
     */
    public function buscarPagos($cliente = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($cliente === null) {
            $cliente = $_GET['cliente'] ?? null;
        }
        
        // Validar parâmetro obrigatório
        if (empty($cliente)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetro "cliente" é obrigatório'
            ]);
        }
        
        $cliente = $this->api->sanitizeString($cliente);
        
        try {
            $query = "SELECT uuid_lanc AS uuid, login, nome, vtab_titulos.titulo, status, 
                            cli_ativado, tipo, valor, linhadig, datavenc, login, cpf_cnpj, 
                            sis_qrpix.qrcode AS pix
                     FROM vtab_titulos
                     LEFT JOIN sis_qrpix ON vtab_titulos.uuid_lanc = sis_qrpix.titulo
                     WHERE (login = ? OR cpf_cnpj = ?) AND status = 'pago' AND deltitulo = 0
                     ORDER BY vtab_titulos.titulo";
            
            $titulos = $this->db->executeQuery($query, [$cliente, $cliente]);
            
            // Adicionar dados PIX
            foreach ($titulos as &$titulo) {
                $titulo = $this->addPixData($titulo);
            }
            
            $this->api->sendResponse(200, [
                'total' => count($titulos),
                'titulos' => $titulos
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar títulos pagos'
            ]);
        }
    }
    
    /**
     * Buscar múltiplos títulos via POST
     */
    public function buscarMultiplos() {
        $input = $this->api->getInputData();
        
        if (!$input) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Dados JSON inválidos'
            ]);
        }
        
        $logins = isset($input['login']) && is_array($input['login']) ? $input['login'] : [];
        $cpfs = isset($input['cpf_cnpj']) && is_array($input['cpf_cnpj']) ? $input['cpf_cnpj'] : [];
        $status = isset($input['status']) ? $this->api->sanitizeString($input['status']) : null;
        
        if (empty($logins) && empty($cpfs)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Pelo menos um login ou CPF/CNPJ deve ser fornecido'
            ]);
        }
        
        try {
            $allParams = array_merge($logins, $cpfs);
            $loginPlaceholders = !empty($logins) ? str_repeat('?,', count($logins)) : '';
            $cpfPlaceholders = !empty($cpfs) ? str_repeat('?,', count($cpfs)) : '';
            
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
            
            $this->api->sendResponse(200, [
                'total' => count($titulos),
                'titulos' => $titulos
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar títulos'
            ]);
        }
    }
    
    /**
     * Receber título
     */
    public function receber($uuid = null) {
        $input = $this->api->getInputData();
        
        if (!$input) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Dados JSON inválidos'
            ]);
        }
        
        // Se UUID não foi passado na URL, tenta pegar do JSON
        if ($uuid === null) {
            $uuid = $input['uuid'] ?? null;
        }
        
        // Campos obrigatórios
        $required_fields = ['uuid', 'valor', 'forma'];
        $this->api->validateRequiredParams($input, $required_fields);
        
        $uuid = $this->api->sanitizeString($uuid ?: $input['uuid']);
        $coletor = isset($input['coletor']) && !empty($input['coletor']) ? 
                  $this->api->sanitizeString($input['coletor']) : 'API';
        $valorPago = floatval($input['valor']);
        $formaPag = $this->api->sanitizeString($input['forma']);
        
        try {
            // Verificar se o título existe
            $consultaQuery = "SELECT id, login, valor FROM sis_lanc WHERE uuid_lanc = ?";
            $titulo = $this->db->fetchOne($consultaQuery, [$uuid]);
            
            if (!$titulo) {
                $this->api->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            $this->db->beginTransaction();
            
            // Atualizar título
            $updateQuery = "UPDATE sis_lanc
                           SET coletor = ?, valorpag = ?, datapag = NOW(), status = 'pago', formapag = ?
                           WHERE uuid_lanc = ? AND status <> 'pago'";
            
            $affected = $this->db->executeQuery($updateQuery, [$coletor, $valorPago, $formaPag, $uuid]);
            
            if ($affected === 0) {
                $this->db->rollback();
                $this->api->sendResponse(400, [
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
            
            $this->api->sendResponse(200, [
                'message' => 'Título recebido com sucesso',
                'titulo' => [
                    'uuid' => $uuid,
                    'valor_pago' => $valorPago,
                    'forma_pagamento' => $formaPag,
                    'coletor' => $coletor
                ]
            ]);
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao receber título'
            ]);
        }
    }
    
    /**
     * Estornar título
     */
    public function estornar($uuid = null) {
        $input = $this->api->getInputData();
        
        // Se UUID não foi passado na URL, tenta pegar do JSON
        if ($uuid === null) {
            $uuid = $input['uuid'] ?? null;
        }
        
        if (empty($uuid)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'UUID do título é obrigatório'
            ]);
        }
        
        $uuid = $this->api->sanitizeString($uuid);
        $usuario = isset($input['usuario']) && !empty($input['usuario']) ? 
                  $this->api->sanitizeString($input['usuario']) : 'API';
        
        try {
            // Verificar se o título existe
            $consultaQuery = "SELECT id, valorpag, login FROM sis_lanc WHERE uuid_lanc = ?";
            $titulo = $this->db->fetchOne($consultaQuery, [$uuid]);
            
            if (!$titulo) {
                $this->api->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            $this->db->beginTransaction();
            
            // Estornar título
            $updateQuery = "UPDATE sis_lanc
                           SET datapag = NULL, status = 'aberto', valorpag = NULL
                           WHERE uuid_lanc = ? AND status = 'pago'";
            
            $affected = $this->db->executeQuery($updateQuery, [$uuid]);
            
            if ($affected === 0) {
                $this->db->rollback();
                $this->api->sendResponse(400, [
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
            
            $this->api->sendResponse(200, [
                'message' => 'Título estornado com sucesso',
                'titulo' => [
                    'uuid' => $uuid,
                    'valor_estornado' => $titulo['valorpag'],
                    'usuario' => $usuario
                ]
            ]);
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao estornar título'
            ]);
        }
    }
    
    /**
     * Editar título
     */
    public function editar($uuid = null) {
        $input = $this->api->getInputData();
        
        if (!$input) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Dados JSON inválidos'
            ]);
        }
        
        // Se UUID não foi passado na URL, tenta pegar do JSON
        if ($uuid === null) {
            $uuid = $input['uuid'] ?? null;
        }
        
        if (empty($uuid)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'UUID do título é obrigatório'
            ]);
        }
        
        $uuid = $this->api->sanitizeString($uuid);
        
        try {
            // Verificar se o título existe
            $consultaQuery = "SELECT * FROM sis_lanc WHERE uuid_lanc = ? LIMIT 1";
            $titulo = $this->db->fetchOne($consultaQuery, [$uuid]);
            
            if (!$titulo) {
                $this->api->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            // Campos permitidos para edição
            $allowedFields = array_keys($titulo);
            $updateFields = [];
            $updateParams = [];
            
            foreach ($input as $field => $value) {
                if (in_array($field, $allowedFields) && $field !== 'id' && $field !== 'uuid_lanc') {
                    $updateFields[] = "$field = ?";
                    $updateParams[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                $this->api->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => 'Nenhum campo válido fornecido para atualização'
                ]);
            }
            
            $updateParams[] = $uuid;
            
            $updateQuery = "UPDATE sis_lanc SET " . implode(', ', $updateFields) . " WHERE uuid_lanc = ?";
            $this->db->executeQuery($updateQuery, $updateParams);
            
            $this->api->sendResponse(200, [
                'message' => 'Título atualizado com sucesso',
                'titulo' => [
                    'uuid' => $uuid,
                    'campos_atualizados' => array_keys($input)
                ]
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao editar título'
            ]);
        }
    }
    
    /**
     * Excluir título
     */
    public function excluir($uuid = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($uuid === null) {
            $uuid = $_GET['uuid'] ?? null;
        }
        
        if (empty($uuid)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'UUID do título é obrigatório'
            ]);
        }
        
        $uuid = $this->api->sanitizeString($uuid);
        
        try {
            $deleteQuery = "DELETE FROM sis_lanc WHERE uuid_lanc = ?";
            $affected = $this->db->executeQuery($deleteQuery, [$uuid]);
            
            if ($affected === 0) {
                $this->api->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Título não encontrado'
                ]);
            }
            
            $this->api->sendResponse(200, [
                'message' => 'Título excluído com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao excluir título'
            ]);
        }
    }
    
    /**
     * Buscar QR Code PIX
     */
    public function buscarPixQrCode($titulo = null) {
        // Se não foi passado na URL, tenta pegar do GET
        if ($titulo === null) {
            $titulo = $_GET['titulo'] ?? null;
        }
        
        if (empty($titulo)) {
            $this->api->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Parâmetro "titulo" é obrigatório'
            ]);
        }
        
        $titulo = $this->api->sanitizeString($titulo);
        
        try {
            $sql = "SELECT qrcode FROM sis_qrpix WHERE titulo = ? LIMIT 1";
            $result = $this->db->fetchOne($sql, [$titulo]);
            
            if ($result && !empty($result['qrcode'])) {
                $this->api->sendResponse(200, [
                    'titulo' => $titulo,
                    'qrcode' => $result['qrcode'],
                    'link_pix' => $result['qrcode']
                ]);
            } else {
                $this->api->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'QR Code PIX não encontrado para este título'
                ]);
            }
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar QR Code PIX'
            ]);
        }
    }
}
?>
