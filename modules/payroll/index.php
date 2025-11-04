<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('payroll_master');

$page_title = "Payroll Management";
$body_class = "payroll-page";

// Get payroll periods
$periods = [];

try {
    $query = "SELECT pp.*, 
                     (SELECT COUNT(*) FROM payroll_master pm WHERE pm.period_id = pp.period_id) as employee_count,
                     (SELECT SUM(net_salary) FROM payroll_master pm WHERE pm.period_id = pp.period_id) as total_net
              FROM payroll_periods pp 
              ORDER BY pp.start_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Payroll periods error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading payroll periods.</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Payroll Management</h1>
    <div>
        <a href="periods.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Period
        </a>
        <a href="process.php" class="btn btn-success">
            <i class="fas fa-calculator me-2"></i>Process Payroll
        </a>
    </div>
</div>

<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Payroll Periods</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="periodsTable">
                <thead>
                    <tr>
                        <th>Period Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Payment Date</th>
                        <th>Employees</th>
                        <th>Total Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periods as $period): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($period['period_name']); ?></td>
                        <td><?php echo formatDate($period['start_date']); ?></td>
                        <td><?php echo formatDate($period['end_date']); ?></td>
                        <td><?php echo formatDate($period['payment_date']); ?></td>
                        <td><?php echo $period['employee_count']; ?></td>
                        <td><?php echo formatCurrency($period['total_net'] ?? 0); ?></td>
                        <td><?php echo getStatusBadge($period['status']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="payslips.php?period_id=<?php echo $period['period_id']; ?>" 
                                   class="btn btn-info" title="View Payslips">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                                <?php if ($period['status'] == 'draft'): ?>
                                <a href="process.php?period_id=<?php echo $period['period_id']; ?>" 
                                   class="btn btn-warning" title="Process">
                                    <i class="fas fa-calculator"></i>
                                </a>
                                <a href="process.php?action=delete&period_id=<?php echo $period['period_id']; ?>" 
                                   class="btn btn-danger" 
                                   title="Delete Payroll" 
                                   onclick="return confirm('Are you sure you want to delete this payroll period? This action cannot be undone.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#periodsTable').DataTable({
        pageLength: 25,
        order: [[1, 'desc']],
        columnDefs: [
            { orderable: false, targets: -1 } // Make the actions column not sortable
        ]
    });
});
</script>

<?php include '../../includes/footer.php'; ?>