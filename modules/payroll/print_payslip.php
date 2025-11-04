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
            e.bank_name, e.account_number, e.account_name,
            d.department_name,
            pp.period_name, pp.start_date, pp.end_date, pp.payment_date,
            c.company_name, c.phone, c.email
        FROM payroll_master pm
        JOIN employees e ON pm.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN payroll_periods pp ON pm.period_id = pp.period_id
        LEFT JOIN companies c ON e.company_id = c.company_id
        WHERE pm.payroll_id = :payroll_id
    ");
    $stmt->execute([':payroll_id' => $payroll_id]);
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payslip) {
        throw new Exception("Payslip not found");
    }
    
    // Get all payroll details for this payslip
    $detailsStmt = $db->prepare("
        SELECT 
            sc.component_id,
            sc.component_name, 
            sc.component_type,
            pd.amount 
        FROM payroll_details pd
        JOIN salary_components sc ON pd.component_id = sc.component_id
        WHERE pd.payroll_id = :payroll_id
        ORDER BY sc.component_type, sc.component_name
    ");
    $detailsStmt->execute([':payroll_id' => $payroll_id]);
    $allComponents = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate into earnings and deductions
    $earnings = [];
    $deductions = [];
    
    foreach ($allComponents as $component) {
        if ($component['component_type'] === 'earning' || $component['component_type'] === 'allowance') {
            $earnings[] = [
                'component_name' => $component['component_name'],
                'amount' => $component['amount']
            ];
        } else if ($component['component_type'] === 'deduction') {
            $deductions[] = [
                'component_name' => $component['component_name'],
                'amount' => $component['amount']
            ];
        }
    }
    
} catch (Exception $e) {
    die('Error loading payslip: ' . htmlspecialchars($e->getMessage()));
}

// Set headers for PDF output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 0.5cm;
            }
            body {
                margin: 1.5cm;
            }
            .no-print {
                display: none !important;
            }
            .print-header {
                display: flex !important;
                justify-content: space-between;
                margin-bottom: 20px;
            }
            .print-footer {
                position: fixed;
                bottom: 0;
                width: 100%;
                text-align: center;
                font-size: 0.8em;
                color: #666;
            }
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }
        .payslip-header {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px 0;
            border-bottom: 2px solid #eee;
        }
        .company-logo {
            max-height: 80px;
            max-width: 200px;
        }
        .payslip-title {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .payslip-subtitle {
            margin: 5px 0 0;
            font-size: 16px;
            color: #666;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin: 40px auto 5px;
        }
        .text-amount {
            text-transform: capitalize;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Button -->
        <div class="text-end mb-3 no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <a href="payslips.php?period_id=<?php echo $payslip['period_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <!-- Header -->
        <div class="payslip-header">
            <?php if (!empty($payslip['company_logo'])): ?>
                <img src="<?php echo htmlspecialchars($payslip['company_logo']); ?>" alt="Company Logo" class="company-logo mb-3">
            <?php endif; ?>
            <h1 class="payslip-title"><?php echo htmlspecialchars($payslip['company_name'] ?? 'PAYSLIP'); ?></h1>
            <p class="payslip-subtitle"><?php echo htmlspecialchars($payslip['company_address'] ?? ''); ?></p>
            <p class="payslip-subtitle">
                Phone: <?php echo htmlspecialchars($payslip['phone'] ?? ''); ?> | 
                Email: <?php echo htmlspecialchars($payslip['email'] ?? ''); ?>
            </p>
            <h2 class="payslip-subtitle">PAYSLIP</h2>
            <p class="mb-0">
                <strong>Period:</strong> 
                <?php echo formatDate($payslip['start_date']); ?> - <?php echo formatDate($payslip['end_date']); ?>
            </p>
            <p class="mb-0">
                <strong>Payment Date:</strong> 
                <?php echo $payslip['payment_date'] ? formatDate($payslip['payment_date']) : 'N/A'; ?>
            </p>
        </div>

        <!-- Employee Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Employee Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></p>
                        <p class="mb-1"><strong>Employee ID:</strong> <?php echo htmlspecialchars($payslip['employee_code']); ?></p>
                        <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Payroll ID:</strong> <?php echo $payslip['payroll_id']; ?></p>
                        <p class="mb-1"><strong>Bank:</strong> <?php echo htmlspecialchars($payslip['bank_name'] ?? 'N/A'); ?></p>
                        <p class="mb-1"><strong>Account Number:</strong> <?php echo htmlspecialchars($payslip['account_number'] ?? 'N/A'); ?></p>
                        <p class="mb-0"><strong>Status:</strong> 
                            <span class="badge bg-<?php echo $payslip['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($payslip['payment_status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earnings -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Earnings</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
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
        </div>

        <!-- Deductions -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Deductions</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount (<?php echo $payslip['currency'] ?? 'NGN'; ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalDeductions = 0;
                        foreach ($deductions as $deduction): 
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
        </div>

        <!-- Summary -->
        <div class="row justify-content-end">
            <div class="col-md-5">
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
                        <td class="text-amount">
                            <?php echo numberToWords($payslip['net_salary']); ?> NAIRA ONLY
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="row mt-5">
            <div class="col-md-6">
                <div class="signature">
                    <div class="signature-line"></div>
                    <p class="text-center mb-0">Employee's Signature</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="signature">
                    <div class="signature-line"></div>
                    <p class="text-center mb-0">Authorized Signature</p>
                </div>
            </div>
        </div>

        <div class="text-center mt-5 text-muted small">
            <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?> by <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System'); ?></p>
            <p>This is a computer-generated document. No signature is required.</p>
        </div>
    </div>

    <script>
        // Auto-print when the page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
        
        // Close the window after printing
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };
    </script>
</body>
</html>
