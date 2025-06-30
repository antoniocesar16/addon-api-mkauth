<?php
/**
 * Classe para operações de banco de dados
 */
class Database {
    private $connection;
    
    public function __construct() {
        $this->connect();
    }
    
    /**
     * Conectar ao banco de dados
     */
    private function connect() {
        require_once __DIR__ . '/../conectar.php';
        $this->connection = $conexao;
        
        if (!$this->connection) {
            throw new Exception('Erro ao conectar com o banco de dados', mysqli_connect_error());
        }
    }
    
    /**
     * Executar query preparada
     */
    public function executeQuery($query, $params = []) {
        $stmt = mysqli_prepare($this->connection, $query);
        
        if (!$stmt) {
            throw new Exception('Erro ao preparar a query: ' . mysqli_error($this->connection));
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result === false) {
            // Para queries que não retornam resultado (INSERT, UPDATE, DELETE)
            return mysqli_stmt_affected_rows($stmt);
        }
        
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $this->utf8_converter($row);
        }
        
        mysqli_stmt_close($stmt);
        return $data;
    }
    
    /**
     * Buscar um único registro
     */
    public function fetchOne($query, $params = []) {
        $result = $this->executeQuery($query, $params);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Converter para UTF-8
     */
    private function utf8_converter($array) {
        if (is_array($array)) {
            array_walk_recursive($array, function(&$item, $key) {
                if (is_string($item) && !mb_detect_encoding($item, 'utf-8', true)) {
                    $item = utf8_encode($item);
                }
            });
        }
        return $array;
    }
    
    /**
     * Escapar string para prevenir SQL Injection (fallback)
     */
    public function escapeString($string) {
        return mysqli_real_escape_string($this->connection, $string);
    }
    
    /**
     * Obter último ID inserido
     */
    public function getLastInsertId() {
        return mysqli_insert_id($this->connection);
    }
    
    /**
     * Iniciar transação
     */
    public function beginTransaction() {
        mysqli_begin_transaction($this->connection);
    }
    
    /**
     * Confirmar transação
     */
    public function commit() {
        mysqli_commit($this->connection);
    }
    
    /**
     * Reverter transação
     */
    public function rollback() {
        mysqli_rollback($this->connection);
    }
}
?>
