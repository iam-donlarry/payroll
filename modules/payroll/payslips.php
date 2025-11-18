<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('payroll_master');

// Get period_id from URL
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;

if (!$period_id) {
    header('Location: index.php');
    exit();
}

$message = '';

// Handle Mark as Paid action
if (isset($_POST['mark_paid']) && $period_id) {
    try {
        $db->beginTransaction();

        // Update payroll_master payment_status
        $update = $db->prepare(
            "UPDATE payroll_master 
            SET payment_status = 'paid', 
                payment_date = NOW() 
            WHERE period_id = :period_id"
        );
        $update->execute([':period_id' => $period_id]);

        // Update payroll_periods status
        $updatePeriod = $db->prepare(
            "UPDATE payroll_periods 
            SET status = 'locked' 
            WHERE period_id = :period_id"
        );
        $updatePeriod->execute([':period_id' => $period_id]);

        $db->commit();
        $message = '<div class="alert alert-success">Payroll marked as paid and the period is now locked!</div>';

        // Refresh the page to show updated status
        header("Location: payslips.php?period_id=" . $period_id . "&success=Payroll+marked+as+paid+and+locked+successfully");
        exit();

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error marking payroll as paid: " . $e->getMessage());
        $message = '<div class="alert alert-danger">Error marking payroll as paid: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Get period details
try {
    $periodStmt = $db->prepare("
        SELECT pp.*, 
               COUNT(pm.employee_id) as employee_count,
               SUM(pm.gross_salary) as total_gross,
               SUM(pm.total_deductions) as total_deductions,
               SUM(pm.net_salary) as total_net
        FROM payroll_periods pp
        LEFT JOIN payroll_master pm ON pp.period_id = pm.period_id
        WHERE pp.period_id = :period_id
        GROUP BY pp.period_id
    ");
    $periodStmt->execute([':period_id' => $period_id]);
    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$period) {
        throw new Exception("Payroll period not found");
    }
    
    // Get all employee payslips for this period
    $payslipsStmt = $db->prepare("
        SELECT 
            pm.payroll_id,
            pm.employee_id,
            pm.period_id,
            pm.basic_salary,
            pm.total_earnings,
            pm.total_deductions,
            pm.gross_salary,
            pm.net_salary,
            pm.payment_status as status,
            e.employee_code,
            e.first_name,
            e.last_name,
            d.department_name
        FROM payroll_master pm
        JOIN employees e ON pm.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE pm.period_id = :period_id
        ORDER BY e.first_name, e.last_name
    ");
    $payslipsStmt->execute([':period_id' => $period_id]);
    $payslips = $payslipsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Payslips error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading payroll data: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Prevent re-processing of locked periods
if (strtolower($period['status']) === 'locked') {
    $message = '<div class="alert alert-warning">This payroll period has already been processed and locked and cannot be modified.</div>';
}

$page_title = "Payslips - " . ($period['period_name'] ?? 'Payroll Period');
$body_class = "payslips-page";

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <a href="index.php" class="text-decoration-none text-gray-600">
            <i class="fas fa-arrow-left me-2"></i>
        </a>
        <?php echo htmlspecialchars($period['period_name'] ?? 'Payslips'); ?>
    </h1>
    <div>
        <a href="export_bank_schedule.php?period_id=<?php echo htmlspecialchars($period_id); ?>" class="btn btn-success mb-3">
            <i class="fas fa-file-excel me-2"></i>Export Bank Schedule
        </a>
        <?php if (in_array(strtolower($period['status']), ['processing', 'pending'])): ?>
        <form method="POST" onsubmit="return confirm('Are you sure you want to mark this payroll as paid? This action cannot be undone.');">
            <button type="submit" name="mark_paid" class="btn btn-success">
                <i class="fas fa-check-circle me-2"></i>Mark as Paid
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php echo $message; ?>

<!-- Period Summary -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                    Period Status
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo getStatusBadge($period['status'] ?? 'draft'); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-success h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                    Employees
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo number_format($period['employee_count'] ?? 0); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-info h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                    Total Gross
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo formatCurrency($period['total_gross'] ?? 0); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-warning h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                    Total Net Pay
                </div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?php echo formatCurrency($period['total_net'] ?? 0); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payslips Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Employee Payslips</h6>
        <div>
            <a href="export_payslips.php?period_id=<?php echo $period_id; ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-file-export me-1"></i> Export to Excel
            </a>
            <button class="btn btn-sm btn-primary" id="printPayslips">
                <i class="fas fa-print me-1"></i> Print All
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="payslipsTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Employee ID</th>
                        <th>Department</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payslips as $payslip): ?>
                    <tr>
                        <td> 
                            <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($payslip['employee_code']); ?></td>
                        <td><?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></td>
                        <td class="text-end"><?php echo formatCurrency($payslip['gross_salary'] ?? 0); ?></td>
                        <td class="text-end"><?php echo formatCurrency($payslip['total_deductions'] ?? 0); ?></td>
                        <td class="text-end font-weight-bold"><?php echo formatCurrency($payslip['net_salary'] ?? 0); ?></td>
                        <td><?php echo getStatusBadge($payslip['status'] ?? 'draft'); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_payslip.php?payroll_id=<?php echo $payslip['payroll_id']; ?>" 
                                   class="btn btn-info btn-sm" title="View Payslip">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="print_payslip.php?payroll_id=<?php echo $payslip['payroll_id']; ?>" 
                                   class="btn btn-secondary btn-sm" title="Print Payslip" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a href="email_payslip.php?payroll_id=<?php echo $payslip['payroll_id']; ?>" 
                                   class="btn btn-success btn-sm" title="Email Payslip">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="4" class="text-end">TOTALS:</td>
                        <td class="text-end">Total Gross: <?php echo formatCurrency($period['total_gross'] ?? 0); ?></td>
                        <td class="text-end">Total Deductions: <?php echo formatCurrency($period['total_deductions'] ?? 0); ?></td>
                        <td class="text-end">Total Net Pay: <?php echo formatCurrency($period['total_net'] ?? 0); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Print All Payslips Modal -->
<div class="modal fade" id="printAllModal" tabindex="-1" role="dialog" aria-labelledby="printAllModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printAllModalLabel">Print All Payslips</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This will open all payslips in a new window for printing.
                </div>
                <div class="form-group">
                    <label>Select Payslip Style:</label>
                    <select class="form-control" id="payslipStyle">
                        <option value="detailed">Detailed View</option>
                        <option value="compact">Compact View</option>
                        <option value="bank">Bank Transfer Slip</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="confirmPrintAll">
                    <i class="fas fa-print me-2"></i>Print All
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#payslipsTable').DataTable({
        responsive: true,
        order: [[0, 'asc']],
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        pageLength: 25
    });
    
    // Handle Print All button
    $('#printPayslips').click(function() {
        $('#printAllModal').modal('show');
    });
    
    // Handle confirm print all
    $('#confirmPrintAll').click(function() {
        const style = $('#payslipStyle').val();
        window.open(`print_all_payslips.php?period_id=<?php echo $period_id; ?>&style=${style}`, '_blank');
        $('#printAllModal').modal('hide');
    });
    
    // Show success message if redirected from process.php
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        const message = urlParams.get('success');
        if (message) {
            alert(message);
            // Remove the success parameter from URL
            const newUrl = window.location.pathname + '?period_id=<?php echo $period_id; ?>';
            window.history.replaceState({}, document.title, newUrl);
        }
    }
});
</script>