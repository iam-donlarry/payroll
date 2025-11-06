<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getLoanDetails($db, $_GET['id']);
            } else {
                listLoans($db);
            }
            break;
            
        case 'POST':
            applyForLoan($db, $auth);
            break;
            
        case 'PUT':
            parse_str(file_get_contents("php://input"), $_PUT);
            if (isset($_GET['id'])) {
                updateLoanStatus($db, $_GET['id'], $auth, $_PUT);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Loan API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'debug' => 'Check server error logs for details'
    ]);
}

function applyForLoan($db, $auth) {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }
    
    // Validate required fields
    $required = ['loan_type_id', 'employee_id', 'loan_amount', 'tenure_months', 'purpose'];
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields: ' . implode(', ', $missing)
        ]);
        return;
    }
    
    try {
        // Get loan type details
        $stmt = $db->prepare("SELECT * FROM loan_types WHERE loan_type_id = ? AND is_active = 1");
        $stmt->execute([$input['loan_type_id']]);
        $loan_type = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan_type) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid loan type']);
            return;
        }
        
        // Validate employee exists
        $stmt = $db->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $stmt->execute([$input['employee_id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid employee']);
            return;
        }
        
        // Calculate loan details
        $loan_amount = floatval($input['loan_amount']);
        $interest_rate = floatval($loan_type['interest_rate']);
        $tenure_months = intval($input['tenure_months']);
        
        $calculation = calculateLoanRepayment($loan_amount, $interest_rate, $tenure_months);
        
        // Insert loan application
        $stmt = $db->prepare("INSERT INTO employee_loans 
                             (employee_id, loan_type_id, loan_amount, interest_rate, tenure_months, 
                              monthly_repayment, total_repayable_amount, remaining_balance, purpose, application_date) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
        
        $result = $stmt->execute([
            $input['employee_id'],
            $input['loan_type_id'],
            $loan_amount,
            $interest_rate,
            $tenure_months,
            $calculation['monthly_repayment'],
            $calculation['total_repayable'],
            $calculation['total_repayable'], // initial remaining balance
            $input['purpose']
        ]);
        
        if (!$result) {
            throw new Exception('Failed to insert loan application');
        }
        
        $loan_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Loan application submitted successfully',
            'loan_id' => $loan_id,
            'calculation' => $calculation
        ]);
        
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}


function listLoans($db) {
    try {
        $stmt = $db->prepare("SELECT el.*, e.first_name, e.last_name, e.employee_code, 
                                     lt.loan_name, lt.interest_rate,
                                     CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                              FROM employee_loans el
                              JOIN employees e ON el.employee_id = e.employee_id
                              JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                              LEFT JOIN employees approver ON el.approved_by = approver.employee_id
                              ORDER BY el.application_date DESC");
        $stmt->execute();
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $loans]);
        
    } catch (PDOException $e) {
        error_log("List Loans Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function updateLoanStatus($db, $loan_id, $auth, $data) {
    if (!isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Status is required']);
        return;
    }
    
    $allowed_statuses = ['approved', 'rejected', 'disbursed'];
    if (!in_array($data['status'], $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    try {
        $query = "UPDATE employee_loans SET status = ?";
        $params = [$data['status']];
        
        if ($data['status'] === 'approved') {
            $query .= ", approval_date = CURDATE(), approved_by = ?";
            $params[] = $auth->getEmployeeId();
        } elseif ($data['status'] === 'disbursed') {
            $query .= ", disbursement_date = CURDATE(), start_repayment_date = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
        }
        
        $query .= " WHERE loan_id = ?";
        $params[] = $loan_id;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Loan status updated successfully']);
        
    } catch (PDOException $e) {
        error_log("Update Loan Status Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

?>