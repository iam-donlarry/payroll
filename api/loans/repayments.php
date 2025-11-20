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
            listRepayments($db);
            break;
            
        case 'PUT':
            if (isset($_GET['id'])) {
                updateRepayment($db, $_GET['id']);
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

function listRepayments($db) {
    $stmt = $db->prepare("SELECT lr.*, el.loan_amount, el.loan_id,
                                 e.first_name, e.last_name, e.employee_code,
                                 lt.loan_name
                          FROM loan_repayments lr
                          JOIN employee_loans el ON lr.loan_id = el.loan_id
                          JOIN employees e ON el.employee_id = e.employee_id
                          JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                          ORDER BY lr.due_date DESC, lr.installment_number");
    $stmt->execute();
    $repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $repayments]);
}

function updateRepayment($db, $repayment_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['amount_paid']) || !isset($data['payment_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Amount paid and payment date are required']);
        return;
    }
    
    $amount_paid = floatval($data['amount_paid']);
    $payment_date = $data['payment_date'];
    
    // Get repayment details
    $stmt = $db->prepare("SELECT * FROM loan_repayments WHERE repayment_id = ?");
    $stmt->execute([$repayment_id]);
    $repayment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$repayment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Repayment not found']);
        return;
    }
    
    // Determine status based on amount paid
    $status = 'paid';
    if ($amount_paid < $repayment['amount_due']) {
        $status = 'partial';
    }
    
    // Update repayment
    $stmt = $db->prepare("UPDATE loan_repayments 
                         SET amount_paid = ?, paid_date = ?, status = ?
                         WHERE repayment_id = ?");
    $stmt->execute([$amount_paid, $payment_date, $status, $repayment_id]);
    
    // Update loan remaining balance
    updateLoanBalance($db, $repayment['loan_id']);
    
    echo json_encode(['success' => true, 'message' => 'Repayment updated successfully']);
}

function updateLoanBalance($db, $loan_id) {
    // Calculate total paid and update remaining balance
    $stmt = $db->prepare("SELECT SUM(amount_paid) as total_paid FROM loan_repayments WHERE loan_id = ?");
    $stmt->execute([$loan_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_paid = $result['total_paid'] ?? 0;
    
    $stmt = $db->prepare("SELECT total_repayable_amount FROM employee_loans WHERE loan_id = ?");
    $stmt->execute([$loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $remaining_balance = $loan['total_repayable_amount'] - $total_paid;
    
    // Update loan
    $stmt = $db->prepare("UPDATE employee_loans SET remaining_balance = ? WHERE loan_id = ?");
    $stmt->execute([$remaining_balance, $loan_id]);
    
    // Update loan status if fully paid
    if ($remaining_balance <= 0) {
        $stmt = $db->prepare("UPDATE employee_loans SET status = 'completed' WHERE loan_id = ?");
        $stmt->execute([$loan_id]);
    }
}
?>