<?php
require_once('../../includes/header.php');
require_once('../../includes/auth.php');
require_once('../../includes/db.php');

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit();
}

// Check if advance ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid advance ID.</div>';
    exit();
}

$advance_id = intval($_GET['id']);

// Check if user has permission to view this advance
$stmt = $db->prepare("SELECT sa.*, 
                      e.first_name, e.last_name, e.employee_code, e.email,
                      CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                      FROM salary_advances sa
                      JOIN employees e ON sa.employee_id = e.employee_id
                      LEFT JOIN employees approver ON sa.approved_by = approver.employee_id
                      WHERE sa.advance_id = ? 
                      AND (sa.employee_id = ? OR ? IN (SELECT user_id FROM users WHERE user_type IN ('admin', 'hr_manager', 'accountant')))");
$stmt->execute([$advance_id, $_SESSION['employee_id'], $_SESSION['user_id'] ?? 0]);
$advance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$advance) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Advance not found or you do not have permission to view it.</div>';
    exit();
}

// Format dates
$request_date = date('F j, Y', strtotime($advance['request_date']));
$deduction_date = date('F j, Y', strtotime($advance['deduction_date']));
$approval_date = $advance['approval_date'] ? date('F j, Y', strtotime($advance['approval_date'])) : 'N/A';

// Status badge
$status_class = [
    'pending' => 'bg-warning',
    'approved' => 'bg-success',
    'rejected' => 'bg-danger',
    'deducted' => 'bg-info'
][$advance['status']] ?? 'bg-secondary';
?>

<div class="advance-details">
    <div class="row mb-4">
        <div class="col-md-6">
            <h5>Advance Information</h5>
            <table class="table table-sm">
                <tr>
                    <th>Employee:</th>
                    <td><?php echo htmlspecialchars($advance['first_name'] . ' ' . $advance['last_name']); ?></td>
                </tr>
                <tr>
                    <th>Employee ID:</th>
                    <td><?php echo htmlspecialchars($advance['employee_code']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($advance['email']); ?></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($advance['status']); ?></span></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h5>Advance Details</h5>
            <table class="table table-sm">
                <tr>
                    <th>Advance Amount:</th>
                    <td>₦<?php echo number_format($advance['advance_amount'], 2); ?></td>
                </tr>
                <tr>
                    <th>Request Date:</th>
                    <td><?php echo $request_date; ?></td>
                </tr>
                <tr>
                    <th>Deduction Date:</th>
                    <td><?php echo $deduction_date; ?></td>
                </tr>
                <tr>
                    <th>Approved By:</th>
                    <td><?php echo $advance['approver_name'] ?? 'N/A'; ?></td>
                </tr>
                <tr>
                    <th>Approval Date:</th>
                    <td><?php echo $approval_date; ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php if (!empty($advance['reason'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">Reason for Advance</h6>
        </div>
        <div class="card-body">
            <?php echo nl2br(htmlspecialchars($advance['reason'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($advance['rejection_reason'])): ?>
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <h6 class="mb-0">Rejection Reason</h6>
        </div>
        <div class="card-body">
            <?php echo nl2br(htmlspecialchars($advance['rejection_reason'])); ?>
        </div>
    </div>
    <?php endif; ?>
</div>
