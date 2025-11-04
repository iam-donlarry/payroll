<?php
require_once '../common/response.php';
require_once '../common/database.php';

class DepartmentsHandler {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getDepartments();
                break;
            case 'POST':
                $this->createDepartment();
                break;
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function getDepartments() {
        $query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($departments);
            
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    private function createDepartment() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('Invalid JSON input', 400);
        }
        
        $required_fields = ['department_name', 'department_code'];
        
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                Response::error("Missing required field: $field", 400);
            }
        }
        
        try {
            $query = "INSERT INTO departments SET 
                     company_id = :company_id,
                     department_name = :department_name,
                     department_code = :department_code,
                     description = :description";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':company_id', $input['company_id'] ?? 1);
            $stmt->bindValue(':department_name', $input['department_name']);
            $stmt->bindValue(':department_code', $input['department_code']);
            $stmt->bindValue(':description', $input['description'] ?? '');
            
            $stmt->execute();
            
            Response::success(['department_id' => $this->db->lastInsertId()], 'Department created successfully', 201);
            
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}

$handler = new DepartmentsHandler();
$handler->handleRequest();
?>