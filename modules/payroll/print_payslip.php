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
    $stmt = $db->prepare("SELECT 
        pm.*, e.employee_id, e.first_name, e.last_name, e.employee_code,
        e.account_number, e.account_name, b.bank_name, b.bank_code,
        d.department_name, pp.period_name, pp.start_date, pp.end_date, pp.payment_date,
        c.company_name, c.phone, c.email
    FROM payroll_master pm
    JOIN employees e ON pm.employee_id = e.employee_id
    LEFT JOIN banks b ON e.bank_id = b.id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN payroll_periods pp ON pm.period_id = pp.period_id
    LEFT JOIN companies c ON e.company_id = c.company_id
    WHERE pm.payroll_id = :payroll_id");
    $stmt->execute([':payroll_id' => $payroll_id]);
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payslip) {
        throw new Exception("Payslip not found");
    }

    // Get all payroll details for this payslip
    $detailsStmt = $db->prepare("SELECT 
        sc.component_id, sc.component_name, sc.component_type, pd.amount 
    FROM payroll_details pd
    JOIN salary_components sc ON pd.component_id = sc.component_id
    WHERE pd.payroll_id = :payroll_id
    ORDER BY sc.component_type, sc.component_name");
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
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 1cm;
            }
            body {
                margin: 0;
                font-size: 12px;
            }
        }
        body {
            font-family: verdana;
            line-height: 1.5;
            color: #333;
        }
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            background: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            max-height: 80px;
        }
        .header h1 {
            font-size: 20px;
            margin: 10px 0;
        }
        .header p {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            margin-bottom: 10px;
            padding-bottom: 5px;
        }
        .table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background: #f9f9f9;
            font-weight: bold;
        }
        .summary {
            text-align: right;
        }
        .summary th, .summary td {
            padding: 8px;
        }
        .signature {
            margin-top: 40px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin: 0 auto;
        }
        .d-flex {
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="payslip-container">
        <div class="header">
            <?php if (!empty($payslip['company_logo'])): ?>
                <img src="<?php echo htmlspecialchars($payslip['company_logo']); ?>" alt="Company Logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($payslip['company_name'] ?? 'PAYSLIP'); ?></h1>
            <p><?php echo htmlspecialchars($payslip['company_address'] ?? ''); ?></p>
            <p>Phone: <?php echo htmlspecialchars($payslip['phone'] ?? ''); ?> | Email: <?php echo htmlspecialchars($payslip['email'] ?? ''); ?></p>
        </div>

        <div class="section">
            <div class="d-flex justify-content-between">
                <!-- Employee Information -->
                <div style="width: 48%;">
                    <div class="section-title">Employee Information</div>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></p>
                    <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($payslip['employee_code']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></p>
                </div>

                <!-- Payment Information -->
                <div style="width: 48%;">
                    <div class="section-title">Payment Information</div>
                    <p><strong>Bank:</strong> <?php echo htmlspecialchars($payslip['bank_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($payslip['bank_code'] ?? 'N/A'); ?>)</p>
                    <p><strong>Account Number:</strong> <?php echo htmlspecialchars($payslip['account_number'] ?? 'N/A'); ?></p>
                    <p><strong>Status:</strong> <?php echo ucfirst($payslip['payment_status']); ?></p>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Earnings</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalEarnings = 0; foreach ($earnings as $earning): $totalEarnings += $earning['amount']; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($earning['component_name']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($earning['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th>Total Earnings</th>
                        <th class="text-end"><?php echo formatCurrency($totalEarnings); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Deductions</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalDeductions = 0; foreach ($deductions as $deduction): $totalDeductions += $deduction['amount']; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($deduction['component_name']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($deduction['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th>Total Deductions</th>
                        <th class="text-end"><?php echo formatCurrency($totalDeductions); ?></th>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Summary</div>
            <table class="table summary">
                <tr>
                    <th>Gross Pay:</th>
                    <td><?php echo formatCurrency($payslip['gross_salary']); ?></td>
                </tr>
                <tr>
                    <th>Total Deductions:</th>
                    <td><?php echo formatCurrency($payslip['total_deductions']); ?></td>
                </tr>
                <tr>
                    <th>Net Pay:</th>
                    <td><?php echo formatCurrency($payslip['net_salary']); ?></td>
                </tr>
                <tr>
                    <th>Amount in Words:</th>
                    <td class="text-uppercase fst-italic">
                        <?php echo numberToWords($payslip['net_salary']); ?> NAIRA ONLY
                    </td>
                </tr>
            </table>
        </div>

        <div class="signature">
            <div class="signature-line"></div>
            <p>Authorized Signature</p>
        </div>
    </div>
</body>
</html>
