<?php
require_once('../../config/database.php');
require_once('../../includes/auth.php');
require_once('../../includes/functions.php');

// Start output buffering
ob_start();

// Initialize database connection
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAuth();

// Set default response
$response = [
    'success' => false, 
    'message' => 'An unknown error occurred.'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    try {
        // Validate action
        $action = $_POST['action'];
        $valid_actions = ['request_advance', 'approve_advance', 'reject_advance'];
        
        if (!in_array($action, $valid_actions)) {
            throw new Exception("Invalid action specified.");
        }

        // Handle the action
        switch ($action) {
            case 'request_advance':
                $response = handleAdvanceRequest($db);
                break;
                
            case 'approve_advance':
                $advance_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$advance_id) {
                    throw new Exception("Invalid advance ID.");
                }
                $response = handleAdvanceApproval($db, $advance_id);
                break;
                
            case 'reject_advance':
                $advance_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $rejection_reason = filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_STRING);
                if (!$advance_id) {
                    throw new Exception("Invalid advance ID.");
                }
                $response = handleAdvanceRejection($db, $advance_id, $rejection_reason);
                break;
        }
    } catch (Exception $e) {
        $response = [
            'success' => false, 
            'message' => $e->getMessage()
        ];
    }
}

// Ensure response is properly formatted
if (!is_array($response) || !isset($response['success'])) {
    $response = [
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ];
}

// Handle AJAX response
if (isAjaxRequest()) {
    header('Content-Type: application/json');
    ob_end_clean();
    echo json_encode($response);
    exit();
}

// Handle non-AJAX response
if ($response['success']) {
    $_SESSION['success'] = $response['message'];
} else {
    $_SESSION['error'] = $response['message'];
}

$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $redirect_url);
exit();

/**
 * Check if the request is an AJAX request
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Handle advance request submission
 */
function handleAdvanceRequest($db) {
    // Validate input
    $amount = filter_input(INPUT_POST, 'advance_amount', FILTER_VALIDATE_FLOAT);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $employee_id = null;
    
    // Basic validation
    if (!$amount || $amount <= 0) {
        throw new Exception("Please enter a valid advance amount.");
    }
    
    if (empty($reason)) {
        throw new Exception("Please provide a reason for the advance.");
    }
    
    // Determine employee ID
    if (in_array($_SESSION['user_type'], ['admin', 'hr_manager', 'accountant']) && !empty($_POST['employee_id'])) {
        $employee_id = (int)$_POST['employee_id'];
    } else {
        $employee_id = (int)$_SESSION['employee_id'];
    }
    
    if (!$employee_id) {
        throw new Exception("Could not determine employee information.");
    }

    // Check borrowing limit using the new logic (33% of gross - monthly loan repayments)
    require_once '../../includes/LoanManager.php';
    $loanManager = new LoanManager($db);
    $limitResult = $loanManager->checkAdvanceBorrowingLimit($employee_id, $amount);
    
    if (!$limitResult['allowed']) {
        throw new Exception($limitResult['message']);
    }
    
    // Check for pending advances
    $stmt = $db->prepare("SELECT COUNT(*) as pending_advances 
                         FROM salary_advances 
                         WHERE employee_id = ? 
                         AND status IN ('pending', 'approved')
                         AND DATE_FORMAT(deduction_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $stmt->execute([$employee_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['pending_advances'] > 0) {
        throw new Exception("You already have a pending or approved advance for this month.");
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Insert advance request
        $stmt = $db->prepare("INSERT INTO salary_advances 
                             (employee_id, advance_amount, request_date, deduction_date, reason, status) 
                             VALUES (?, ?, NOW(), LAST_DAY(CURDATE()), ?, 'pending')");
        
        $stmt->execute([$employee_id, $amount, $reason]);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Your advance request has been submitted successfully and is pending approval.'
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error processing advance request: " . $e->getMessage());
        throw new Exception("An error occurred while processing your request. Please try again.");
    }
}

/**
 * Handle advance approval
 */
function handleAdvanceApproval($db, $advance_id) {
    // Check if user has permission
    $allowed_roles = ['admin', 'hr_manager', 'accountant'];
    if (!in_array($_SESSION['user_type'], $allowed_roles)) {
        throw new Exception("You do not have permission to approve advances.");
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Get advance details
        $stmt = $db->prepare("SELECT * FROM salary_advances WHERE advance_id = ?");
        $stmt->execute([$advance_id]);
        $advance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$advance) {
            throw new Exception("Advance not found.");
        }
        
        if ($advance['status'] !== 'pending') {
            throw new Exception("This advance cannot be approved in its current status.");
        }
        
        // Update advance status
        $stmt = $db->prepare("UPDATE salary_advances 
                             SET status = 'approved',
                                 approved_by = ?,
                                 approval_date = NOW()
                             WHERE advance_id = ?");
        $stmt->execute([$_SESSION['employee_id'], $advance_id]);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Advance approved successfully.'
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error approving advance: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle advance rejection
 */
function handleAdvanceRejection($db, $advance_id, $rejection_reason) {
    // Check if user has permission
    $allowed_roles = ['admin', 'hr_manager', 'accountant'];
    if (!in_array($_SESSION['user_type'], $allowed_roles)) {
        throw new Exception("You do not have permission to reject advances.");
    }
    
    if (empty($rejection_reason)) {
        throw new Exception("Please provide a reason for rejection.");
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Get advance details
        $stmt = $db->prepare("SELECT * FROM salary_advances WHERE advance_id = ?");
        $stmt->execute([$advance_id]);
        $advance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$advance) {
            throw new Exception("Advance not found.");
        }
        
        if ($advance['status'] !== 'pending') {
            throw new Exception("This advance cannot be rejected in its current status.");
        }
        
        // Update advance status
        $stmt = $db->prepare("UPDATE salary_advances 
                             SET status = 'rejected',
                                 approved_by = ?,
                                 approval_date = NOW(),
                                 rejection_reason = ?
                             WHERE advance_id = ?");
        $stmt->execute([
            $_SESSION['employee_id'],
            $rejection_reason,
            $advance_id
        ]);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Advance rejected successfully.'
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error rejecting advance: " . $e->getMessage());
        throw $e;
    }
}
?>