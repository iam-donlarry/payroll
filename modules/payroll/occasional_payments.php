<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('payroll_master');

$page_title = "Occasional Taxable Payments";
$body_class = "payroll-occasional-page";

$message = '';

// Fetch employees for dropdown
$employees = [];
try {
    $stmt = $db->prepare("
        SELECT employee_id, employee_code, first_name, last_name
        FROM employees
        WHERE status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Unable to load employees: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payments'])) {
    $payments = $_POST['payments'];
    $inserted = 0;
    $errors = [];

    foreach ($payments as $payment) {
        $employee_id = isset($payment['employee_id']) ? intval($payment['employee_id']) : 0;
        $title = sanitizeInput($payment['title'] ?? '');
        $amount = isset($payment['amount']) ? floatval(str_replace(',', '', $payment['amount'])) : 0;
        $pay_month_raw = sanitizeInput($payment['pay_month'] ?? '');

        if (!$employee_id || empty($title) || $amount <= 0 || empty($pay_month_raw)) {
            $errors[] = "All fields are required for each payment entry.";
            continue;
        }

        $payMonthDate = DateTime::createFromFormat('Y-m', $pay_month_raw);
        if (!$payMonthDate) {
            $errors[] = "Invalid month selected for {$title}.";
            continue;
        }
        $pay_month = $payMonthDate->format('Y-m-01');

        try {
            $stmt = $db->prepare("
                INSERT INTO employee_occasional_payments
                (employee_id, title, amount, pay_month, status)
                VALUES (:employee_id, :title, :amount, :pay_month, 'pending')
            ");
            $stmt->execute([
                ':employee_id' => $employee_id,
                ':title' => $title,
                ':amount' => $amount,
                ':pay_month' => $pay_month
            ]);
            $inserted++;
        } catch (PDOException $e) {
            $errors[] = "Failed to save {$title}: " . $e->getMessage();
        }
    }

    if ($inserted > 0) {
        $message = '<div class="alert alert-success">' . $inserted . ' payment(s) added successfully.</div>';
    }
    if (!empty($errors)) {
        $message .= '<div class="alert alert-warning"><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $errors)) . '</li></ul></div>';
    }
}

// Filters
$status_filter = $_GET['status'] ?? 'pending';
$month_filter = $_GET['month'] ?? '';
$filter_sql = "WHERE 1=1";
$filter_params = [];

if (!empty($status_filter) && in_array($status_filter, ['pending', 'paid'])) {
    $filter_sql .= " AND eop.status = :status";
    $filter_params[':status'] = $status_filter;
}

if (!empty($month_filter)) {
    $monthDate = DateTime::createFromFormat('Y-m', $month_filter);
    if ($monthDate) {
        $filter_sql .= " AND DATE_FORMAT(eop.pay_month, '%Y-%m') = :month";
        $filter_params[':month'] = $monthDate->format('Y-m');
    }
}

// Fetch payments
$paymentsList = [];
try {
    $query = "
        SELECT eop.*, e.employee_code, e.first_name, e.last_name, pm.period_id
        FROM employee_occasional_payments eop
        JOIN employees e ON e.employee_id = eop.employee_id
        LEFT JOIN payroll_master pm ON pm.payroll_id = eop.payroll_id
        {$filter_sql}
        ORDER BY eop.pay_month DESC, eop.created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($filter_params);
    $paymentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Unable to load occasional payments: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Occasional Taxable Payments</h1>
        <p class="text-muted mb-0">Capture once-off taxable earnings (bonus, incentive, commissions) that apply to a specific payroll month.</p>
    </div>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Payroll
    </a>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Add Payments</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="paymentsForm">
                    <div id="paymentRows">
                        <div class="payment-row border rounded p-3 mb-3">
                            <div class="mb-3">
                                <label class="form-label">Employee *</label>
                                <select name="payments[0][employee_id]" class="form-control" required>
                                    <option value="">Select employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['employee_id']; ?>">
                                            <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Title *</label>
                                <input type="text" name="payments[0][title]" class="form-control" placeholder="e.g. Performance Bonus" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Amount (NGN) *</label>
                                    <input type="number" step="0.01" min="0" name="payments[0][amount]" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Payroll Month *</label>
                                    <input type="month" name="payments[0][pay_month]" class="form-control" required>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-row d-none">Remove</button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-light border" id="addRowBtn">
                            <i class="fas fa-plus me-2"></i>Add Another Payment
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Payments
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Scheduled Payments</h6>
                <form class="d-flex gap-2" method="GET">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                    <input type="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($month_filter); ?>" onchange="this.form.submit()">
                    <noscript><button class="btn btn-sm btn-primary">Filter</button></noscript>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Title</th>
                                <th class="text-end">Amount</th>
                                <th>Month</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paymentsList)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No payments found for the selected filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paymentsList as $payment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['employee_code']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['title']); ?>
                                            <?php if ($payment['status'] === 'paid' && $payment['payroll_id']): ?>
                                                <br><small class="text-muted">Payroll ID: <?php echo $payment['payroll_id']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo formatDate($payment['pay_month'], 'F Y'); ?></td>
                                        <td><?php echo getStatusBadge($payment['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$page_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    const addRowBtn = document.getElementById('addRowBtn');
    const paymentRows = document.getElementById('paymentRows');
    let rowIndex = 1;

    addRowBtn.addEventListener('click', function() {
        const template = paymentRows.querySelector('.payment-row').cloneNode(true);
        template.querySelectorAll('input, select').forEach(function(input) {
            const name = input.getAttribute('name');
            const newName = name.replace(/\[\d+\]/, '[' + rowIndex + ']');
            input.setAttribute('name', newName);
            if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            } else {
                input.value = '';
            }
        });

        const removeBtn = template.querySelector('.remove-row');
        removeBtn.classList.remove('d-none');
        removeBtn.addEventListener('click', function() {
            template.remove();
        });

        paymentRows.appendChild(template);
        rowIndex++;
    });
});
JS;
?>

<?php include '../../includes/footer.php'; ?>

