<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('payroll_master');

// Get payroll_id from URL
$payroll_id = isset($_GET['payroll_id']) ? intval($_GET['payroll_id']) : 0;

if (!$payroll_id) {
    header('Location: index.php');
    exit();
}

try {
    // Get payroll details
    $stmt = $db->prepare("
        SELECT 
            pm.*,
            e.employee_id, e.first_name, e.last_name, e.employee_code,
            e.account_number, e.account_name,
            b.bank_name, b.bank_code,
            d.department_name,
            pp.period_name, pp.start_date, pp.end_date, pp.payment_date
        FROM payroll_master pm
        JOIN employees e ON pm.employee_id = e.employee_id
        LEFT JOIN banks b ON e.bank_id = b.id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN payroll_periods pp ON pm.period_id = pp.period_id
        WHERE pm.payroll_id = :payroll_id
    ");
    $stmt->execute([':payroll_id' => $payroll_id]);
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payslip) {
        throw new Exception("Payslip not found");
    }
    
    // Get all payroll details for this payslip including loans and advances
    $detailsStmt = $db->prepare("
        SELECT 
            sc.component_id,
            sc.component_name, 
            sc.component_type,
            sc.component_code,
            pd.amount,
            pd.reference_id
        FROM payroll_details pd
        JOIN salary_components sc ON pd.component_id = sc.component_id
        WHERE pd.payroll_id = :payroll_id
        ORDER BY 
            CASE 
                WHEN sc.component_type = 'earning' THEN 1
                WHEN sc.component_type = 'allowance' THEN 2
                WHEN sc.component_type = 'deduction' AND sc.component_code NOT IN ('LOAN', 'ADVANCE') THEN 3
                WHEN sc.component_code = 'LOAN' THEN 4
                WHEN sc.component_code = 'ADVANCE' THEN 5
                ELSE 6
            END,
            sc.component_name
    ");
    $detailsStmt->execute([':payroll_id' => $payroll_id]);
    $allComponents = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate into earnings and deductions
    $earnings = [];
    $deductions = [];
    $loans = [];
    $advances = [];
    
    foreach ($allComponents as $component) {
        if ($component['component_type'] === 'earning' || $component['component_type'] === 'allowance') {
            $earnings[] = [
                'component_name' => $component['component_name'],
                'amount' => $component['amount']
            ];
        } else if ($component['component_type'] === 'deduction') {
            if ($component['component_code'] === 'LOAN') {
                $loanName = 'Loan Repayment';
                
                // Only try to get loan details if reference_id is available
                if (!empty($component['reference_id'])) {
                    $loanStmt = $db->prepare("
                        SELECT CONCAT('Loan - ', COALESCE(lt.loan_name, 'Repayment')) as display_name 
                        FROM employee_loans el
                        LEFT JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                        WHERE el.loan_id = :loan_id
                    ");
                    $loanStmt->execute([':loan_id' => $component['reference_id']]);
                    $result = $loanStmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $loanName = $result['display_name'];
                    }
                }
                
                $loans[] = [
                    'component_name' => $loanName,
                    'amount' => $component['amount']
                ];
            } else if ($component['component_code'] === 'ADVANCE') {
                $advanceName = 'Salary Advance';
                
                // Only try to get advance details if reference_id is available
                if (!empty($component['reference_id'])) {
                    $advanceStmt = $db->prepare("
                        SELECT CONCAT('Advance - ', COALESCE('Repayment')) as display_name 
                        FROM salary_advances 
                        WHERE advance_id = :advance_id
                    ");
                    $advanceStmt->execute([':advance_id' => $component['reference_id']]);
                    $result = $advanceStmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $advanceName = $result['display_name'];
                    }
                }
                
                // Add to the advances array
                $advances[] = [
                    'component_name' => $advanceName,
                    'amount' => $component['amount']
                ];
            } else {
                // Regular deductions
                $deductions[] = [
                    'component_name' => $component['component_name'],
                    'amount' => $component['amount']
                ];
            }
        }
    }
    
    // Merge all deductions together with loans and advances
    $allDeductions = array_merge($deductions, $loans, $advances);
    
} catch (Exception $e) {
    error_log("Payslip view error: " . $e->getMessage());
    die('<div class="alert alert-danger">Error loading payslip: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

$page_title = "Payslip - " . $payslip['first_name'] . ' ' . $payslip['last_name'];
$body_class = "payslip-page";

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <a href="payslips.php?period_id=<?php echo $payslip['period_id']; ?>" class="text-decoration-none text-gray-600">
            <i class="fas fa-arrow-left me-2"></i>
        </a>
        Employee Payslip
    </h1>
    <div>
        <a href="print_payslip.php?payroll_id=<?php echo $payroll_id; ?>" class="btn btn-primary" target="_blank">
            <i class="fas fa-print me-2"></i>Print
        </a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <div class="row">
            <div class="col-md-6">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?php echo htmlspecialchars($payslip['period_name']); ?>
                </h6>
                <div class="text-muted small">
                    <?php echo formatDate($payslip['start_date']); ?> - <?php echo formatDate($payslip['end_date']); ?>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="badge bg-<?php echo $payslip['payment_status'] === 'paid' ? 'success' : 'warning'; ?> text-white p-2">
                    <?php echo ucfirst($payslip['payment_status']); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Employee and Company Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Employee Details</h5>
                <p class="mb-1">
                    <strong>Name:</strong> 
                    <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?>
                </p>
                <p class="mb-1">
                    <strong>Employee ID:</strong> 
                    <?php echo htmlspecialchars($payslip['employee_code']); ?>
                </p>
                <p class="mb-1">
                    <strong>Department:</strong> 
                    <?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <h5>Payment Details</h5>
                <p class="mb-1">
                    <strong>Payment Date:</strong> 
                    <?php echo $payslip['payment_date'] ? formatDate($payslip['payment_date']) : 'N/A'; ?>
                </p>
                <p class="mb-1">
                    <strong>Bank:</strong> 
                    <?php echo htmlspecialchars($payslip['bank_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($payslip['bank_code'] ?? 'N/A'); ?>)
                </p>
                <p class="mb-1">
                    <strong>Account Number:</strong> 
                    <?php echo htmlspecialchars($payslip['account_number'] ?? 'N/A'); ?>
                </p>
                <p class="mb-1">
                    <strong>Account Name:</strong> 
                    <?php echo htmlspecialchars($payslip['account_name'] ?? 'N/A'); ?>
                </p>
            </div>
        </div>

        <!-- Earnings -->
        <div class="table-responsive mb-4">
            <h5>Earnings</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount (<?php echo $payslip['currency'] ?? 'NGN'; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalEarnings = 0;
                    foreach ($earnings as $earning): 
                        $totalEarnings += $earning['amount'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($earning['component_name']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($earning['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active">
                        <td class="text-end"><strong>Total Earnings:</strong></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalEarnings); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Deductions -->
        <div class="table-responsive mb-4">
            <h5>Deductions</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount (<?php echo $payslip['currency'] ?? 'NGN'; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalDeductions = 0;
                    foreach ($allDeductions as $deduction): 
                        $totalDeductions += $deduction['amount'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($deduction['component_name']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($deduction['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active">
                        <td class="text-end"><strong>Total Deductions:</strong></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalDeductions); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="row justify-content-end">
            <div class="col-md-4">
                <table class="table table-bordered">
                    <tr>
                        <th>Gross Pay:</th>
                        <td class="text-end"><?php echo formatCurrency($payslip['gross_salary']); ?></td>
                    </tr>
                    <tr>
                        <th>Total Deductions:</th>
                        <td class="text-end"><?php echo formatCurrency($payslip['total_deductions']); ?></td>
                    </tr>
                    <tr class="table-active">
                        <th>Net Pay:</th>
                        <th class="text-end"><?php echo formatCurrency($payslip['net_salary']); ?></th>
                    </tr>
                    <tr>
                        <th>Amount in Words:</th>
                        <td class="text-uppercase fst-italic">
                            <?php echo numberToWords($payslip['net_salary']); ?> NAIRA ONLY
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="row mt-4">
            <div class="col-md-6">
                <p class="mb-1">Date Generated: <?php echo date('F j, Y'); ?></p>
                <p class="mb-1">Generated By: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System'); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="signature mt-4">
                    <div class="border-top border-dark w-50 mx-auto"></div>
                    <p class="mb-0 text-center">Authorized Signature</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
