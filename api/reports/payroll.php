<?php
require_once '../common/response.php';
require_once '../common/database.php';

class PayrollReporter {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method !== 'GET') {
            Response::error('Method not allowed', 405);
        }
        
        $this->generateReport();
    }
    
    private function generateReport() {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        $query = "SELECT pp.period_name, pp.start_date, pp.end_date, 
                         COUNT(pm.payroll_id) as employee_count,
                         SUM(pm.gross_salary) as total_gross,
                         SUM(pm.total_deductions) as total_deductions,
                         SUM(pm.net_salary) as total_net
                  FROM payroll_periods pp 
                  LEFT JOIN payroll_master pm ON pp.period_id = pm.period_id 
                  WHERE pp.start_date BETWEEN :start_date AND :end_date
                  GROUP BY pp.period_id 
                  ORDER BY pp.start_date DESC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':start_date', $start_date);
            $stmt->bindValue(':end_date', $end_date);
            $stmt->execute();
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success([
                'report_data' => $report_data,
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]
            ]);
            
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}

$reporter = new PayrollReporter();
$reporter->handleRequest();
?>