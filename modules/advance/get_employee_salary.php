<?php
require_once('../../config/database.php');
require_once('../../includes/functions.php');

// Set JSON header
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request',
    'net_salary' => 0
];

try {
    // Check if employee_id is provided and valid
    if (!isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
        throw new Exception('Invalid employee ID');
    }

    $employee_id = (int)$_GET['employee_id'];
    
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get employee's net salary by summing up all salary components
    $stmt = $db->prepare("SELECT SUM(amount) as net_salary 
                         FROM employee_salary_structure 
                         WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['net_salary'])) {
        $net_salary = (float)$result['net_salary'];
        $response = [
            'success' => true,
            'message' => 'Salary retrieved successfully',
            'net_salary' => $net_salary > 0 ? $net_salary : 0
        ];
    } else {
        $response['message'] = 'No salary information found for this employee';
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Return JSON response
echo json_encode($response);
?>
