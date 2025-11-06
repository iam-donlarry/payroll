<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once '../../config/database.php';
    require_once '../../includes/auth.php';

    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    // Check authentication
    /*if (!$auth->validateToken()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }*/

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }

    // Validate required fields
    $required = ['amount', 'interest_rate', 'tenure_months'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }

    $amount = floatval($input['amount']);
    $interest_rate = floatval($input['interest_rate']);
    $tenure_months = intval($input['tenure_months']);

    // Validate inputs
    if ($amount <= 0 || $interest_rate < 0 || $tenure_months <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input values']);
        exit;
    }

    // Calculate loan details
    $monthly_rate = $interest_rate / 100 / 12;
    
    if ($monthly_rate > 0) {
        $monthly_repayment = ($amount * $monthly_rate * pow(1 + $monthly_rate, $tenure_months)) 
                            / (pow(1 + $monthly_rate, $tenure_months) - 1);
    } else {
        $monthly_repayment = $amount / $tenure_months;
    }

    $total_repayable = $monthly_repayment * $tenure_months;
    $total_interest = $total_repayable - $amount;

    echo json_encode([
        'success' => true,
        'monthly_repayment' => round($monthly_repayment, 2),
        'total_repayable' => round($total_repayable, 2),
        'total_interest' => round($total_interest, 2)
    ]);

} catch (Exception $e) {
    error_log("Calculate API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>