<?php
require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Check if the user is authenticated
if (!$auth->validateToken()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only PUT method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if the user has HR manager or admin permission
if (!$auth->hasPermission('hr_manager') && !$auth->hasPermission('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Get the input data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['loan_id', 'status'];
foreach ($required as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$loan_id = intval($data['loan_id']);
$status = $data['status'];

// Allowed statuses for update
$allowed_statuses = ['approved', 'rejected', 'disbursed', 'active', 'completed', 'defaulted'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Update the loan status
$query = "UPDATE employee_loans SET status = ?";
$params = [$status];

// If approving, set approval date and approved by
if ($status === 'approved') {
    $query .= ", approval_date = CURDATE(), approved_by = ?";
    $params[] = $auth->getEmployeeId();
}

// If disbursing, set disbursement date and update status to active
if ($status === 'disbursed') {
    $query .= ", disbursement_date = CURDATE(), status = 'active'";
} else {
    $query .= " WHERE loan_id = ?";
    $params[] = $loan_id;
}

$stmt = $db->prepare($query);

try {
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Loan status updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Loan not found or no changes made']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}