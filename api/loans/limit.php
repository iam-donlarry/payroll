<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once '../../config/database.php';
    require_once '../../includes/auth.php';
    require_once '../../includes/functions.php';
    require_once '../../includes/LoanManager.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    
    // Check authentication
    /*if (!$auth->validateToken()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }*/
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    
    if ($employee_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
        exit;
    }

    $loanManager = new LoanManager($db);
    
    // Get limit details (pass 0 as requested amount to just get status)
    $limitInfo = $loanManager->checkBorrowingLimit($employee_id, 0);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'gross_salary' => $limitInfo['gross_salary'],
            'max_limit' => $limitInfo['max_limit'],
            'current_outstanding' => $limitInfo['current_outstanding'],
            'available_amount' => $limitInfo['available_amount']
        ]
    ]);

} catch (Exception $e) {
    error_log("Loan Limit API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
