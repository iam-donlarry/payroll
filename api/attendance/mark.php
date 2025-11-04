<?php
require_once '../common/response.php';
require_once '../common/database.php';

class AttendanceMarker {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        
        $this->markAttendance();
    }
    
    private function markAttendance() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('Invalid JSON input', 400);
        }
        
        $required_fields = ['employee_id', 'project_id', 'attendance_date', 'status'];
        
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                Response::error("Missing required field: $field", 400);
            }
        }
        
        try {
            $query = "INSERT INTO attendance_records SET 
                     employee_id = :employee_id,
                     project_id = :project_id,
                     attendance_date = :attendance_date,
                     check_in_time = :check_in_time,
                     check_out_time = :check_out_time,
                     total_hours = :total_hours,
                     overtime_hours = :overtime_hours,
                     status = :status
                     ON DUPLICATE KEY UPDATE
                     check_in_time = :check_in_time,
                     check_out_time = :check_out_time,
                     total_hours = :total_hours,
                     overtime_hours = :overtime_hours,
                     status = :status";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':employee_id', $input['employee_id']);
            $stmt->bindValue(':project_id', $input['project_id']);
            $stmt->bindValue(':attendance_date', $input['attendance_date']);
            $stmt->bindValue(':check_in_time', $input['check_in_time'] ?? null);
            $stmt->bindValue(':check_out_time', $input['check_out_time'] ?? null);
            $stmt->bindValue(':total_hours', $input['total_hours'] ?? 0);
            $stmt->bindValue(':overtime_hours', $input['overtime_hours'] ?? 0);
            $stmt->bindValue(':status', $input['status']);
            
            $stmt->execute();
            
            Response::success(['attendance_id' => $this->db->lastInsertId()], 'Attendance marked successfully', 201);
            
        } catch (PDOException $e) {
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
}

$marker = new AttendanceMarker();
$marker->handleRequest();
?>