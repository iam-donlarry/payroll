<?php
require_once '../common/response.php';
require_once '../common/database.php';

class PayrollHandler {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getPayrolls();
                break;
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function getPayrolls() {
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 50;
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT p.*, pp.period_name, pp.start_date, pp.end_date, 
                         e.first_name, e.last_name, e.employee_code
                  FROM payroll_master p
                  JOIN payroll_periods pp ON p.period_id = pp.period_id
                  JOIN employees e ON p.employee_id = e.employee_id
                  ORDER BY pp.start_date DESC, e.first_name, e.last_name
                  LIMIT :limit OFFSET :offset";
        
        $count_query = "SELECT COUNT(*) as total FROM payroll_master";
        
        try {
            // Get total count
            $count_stmt = $this->db->prepare($count_query);
            $count_stmt->execute();
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get payrolls
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $pagination = [
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ];
            
            Response::paginated($payrolls, $pagination);
            
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}

$handler = new PayrollHandler();
$handler->handleRequest();
?>