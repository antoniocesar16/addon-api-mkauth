<?php
/**
 * Controller para operações de chamados/suporte
 */
class ChamadoController {
    private $db;
    private $api;
    
    public function __construct($database, $api_router) {
        $this->db = $database;
        $this->api = $api_router;
    }
    
    /**
     * Obter chamados abertos
     */
    public function getChamadosAbertos() {
        try {
            $query = "SELECT COUNT(*) as total FROM vtab_suportes WHERE status='aberto' AND cli_ativado = 's'";
            $result = $this->db->fetchOne($query);
            
            $this->api->sendResponse(200, [
                'chamados_abertos' => $result['total'] ?? 0
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar chamados abertos'
            ]);
        }
    }
    
    /**
     * Obter chamados fechados por período
     */
    public function getChamadosFechados() {
        $data = $_GET['data'] ?? date('Y-m');
        $grupo = $_GET['grupo'] ?? null;
        
        try {
            $params = ["%{$data}%"];
            $where_clause = "WHERE fechamento LIKE ? AND status='fechado'";
            
            if ($grupo) {
                $where_clause .= " AND grupo = ?";
                $params[] = $this->api->sanitizeString($grupo);
            }
            
            $query = "SELECT COUNT(*) as total FROM vtab_suportes $where_clause";
            $result = $this->db->fetchOne($query, $params);
            
            $this->api->sendResponse(200, [
                'chamados_fechados' => $result['total'] ?? 0,
                'periodo' => $data,
                'grupo' => $grupo
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar chamados fechados'
            ]);
        }
    }
    
    /**
     * Obter chamados fechados no dia
     */
    public function getChamadosFechadosDia() {
        $dia = $_GET['dia'] ?? date('d');
        $mes = $_GET['mes'] ?? date('m');
        $ano = $_GET['ano'] ?? date('Y');
        
        try {
            $data_pattern = "%{$ano}-{$mes}-{$dia}%";
            
            $query = "SELECT COUNT(*) as total FROM vtab_suportes 
                     WHERE fechamento LIKE ? AND status='fechado' AND cli_ativado = 's'";
            
            $result = $this->db->fetchOne($query, [$data_pattern]);
            
            $this->api->sendResponse(200, [
                'chamados_fechados_dia' => $result['total'] ?? 0,
                'data' => "{$dia}/{$mes}/{$ano}"
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao buscar chamados do dia'
            ]);
        }
    }
    
    /**
     * Relatório de chamados por grupo
     */
    public function getRelatorioPorGrupo() {
        $data = $_GET['data'] ?? date('Y-m');
        
        try {
            // Buscar todos os grupos
            $grupos_query = "SELECT DISTINCT grupo FROM sis_cliente WHERE grupo IS NOT NULL AND grupo != ''";
            $grupos = $this->db->executeQuery($grupos_query);
            
            $relatorio = [];
            
            foreach ($grupos as $grupo_row) {
                $grupo = $grupo_row['grupo'];
                
                // Chamados fechados por grupo
                $chamados_query = "SELECT COUNT(*) as total FROM vtab_suportes 
                                 WHERE fechamento LIKE ? AND status='fechado' AND grupo = ?";
                $chamados = $this->db->fetchOne($chamados_query, ["%{$data}%", $grupo]);
                
                // Instalações por grupo
                $inst_query = "SELECT COUNT(*) as total FROM sis_cliente 
                              WHERE data_ins LIKE ? AND grupo = ?";
                $instalacoes = $this->db->fetchOne($inst_query, ["%{$data}%", $grupo]);
                
                // Desativações por grupo
                $desativ_query = "SELECT COUNT(*) as total FROM sis_cliente 
                                 WHERE data_desativacao LIKE ? AND grupo = ?";
                $desativacoes = $this->db->fetchOne($desativ_query, ["%{$data}%", $grupo]);
                
                $relatorio[] = [
                    'grupo' => $grupo,
                    'chamados_fechados' => (int)($chamados['total'] ?? 0),
                    'instalacoes' => (int)($instalacoes['total'] ?? 0),
                    'desativacoes' => (int)($desativacoes['total'] ?? 0)
                ];
            }
            
            $this->api->sendResponse(200, [
                'relatorio_por_grupo' => $relatorio,
                'periodo' => $data
            ]);
            
        } catch (Exception $e) {
            $this->api->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Erro ao gerar relatório por grupo'
            ]);
        }
    }
}
?>
