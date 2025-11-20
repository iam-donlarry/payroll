<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check authentication
/*if (!$auth->validateToken()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}*/

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            listAdvances($db);
            break;
            
        case 'POST':
            requestAdvance($db, $auth);
            break;
            
        case 'PUT':
            if (isset($_GET['id'])) {
                updateAdvanceStatus($db, $_GET['id'], $auth);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function requestAdvance($db, $auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['employee_id', 'advance_amount', 'repayment_period_months', 'reason'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    $advance_amount = floatval($data['advance_amount']);
    $repayment_period = intval($data['repayment_period_months']);
    $monthly_repayment = $advance_amount / $repayment_period;
    
    // Insert advance request
    $stmt = $db->prepare("INSERT INTO salary_advances 
                         (employee_id, advance_amount, request_date, repayment_period_months, 
                          monthly_repayment_amount, total_repayment_amount, reason) 
                         VALUES (?, ?, CURDATE(), ?, ?, ?, ?)");
    
    $stmt->execute([
        $data['employee_id'],
        $advance_amount,
        $repayment_period,
        $monthly_repayment,
        $advance_amount, // total repayment is same as advance (no interest)
        $data['reason']
    ]);
    
    $advance_id = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Salary advance requested successfully',
        'advance_id' => $advance_id
    ]);
}

function listAdvances($db) {
    $stmt = $db->prepare("SELECT sa.*, e.first_name, e.last_name, e.employee_code,
                                 CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                          FROM salary_advances sa
                          JOIN employees e ON sa.employee_id = e.employee_id
                          LEFT JOIN employees approver ON sa.approved_by = approver.employee_id
                          ORDER BY sa.request_date DESC");
    $stmt->execute();
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $advances]);
}

function updateAdvanceStatus($db, $advance_id, $auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Status is required']);
        return;
    }
    
    $allowed_statuses = ['approved', 'rejected'];
    if (!in_array($data['status'], $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    $query = "UPDATE salary_advances SET status = ?";
    $params = [$data['status']];
    
    if ($data['status'] === 'approved') {
        $query .= ", approval_date = CURDATE(), approved_by = ?, repayment_start_date = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
        $params[] = $auth->getEmployeeId();
    }
    
    $query .= " WHERE advance_id = ?";
    $params[] = $advance_id;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Advance status updated successfully']);
}
?>