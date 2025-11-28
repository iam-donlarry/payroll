<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requirePermission('payroll_master');
$payroll_id = isset($_GET['payroll_id']) ? intval($_GET['payroll_id']) : 0;
if (!$payroll_id) {
    header('Location: index.php');
    exit();
}
try {
    // --- Fetch payslip / payroll master + employee + period details ---
    $stmt = $db->prepare("
        SELECT 
            pm.*,
            e.employee_id, e.first_name, e.last_name, e.employee_code,
            e.account_number, e.account_name,
            b.bank_name, b.bank_code,
            d.department_name,
            pp.period_id, pp.period_name, pp.start_date, pp.end_date, pp.payment_date,
            c.company_name, c.phone, c.email, c.address as company_address
        FROM payroll_master pm
        JOIN employees e ON pm.employee_id = e.employee_id
        LEFT JOIN banks b ON e.bank_id = b.id
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
    $employeeId = $payslip['employee_id'];
    $periodEndDate = $payslip['end_date'];
    $currentYear = date('Y', strtotime($payslip['start_date']));
    $periodEnd = new DateTime($periodEndDate);
    // Last calendar month relative to the current period end
    $lastMonthStart = (clone $periodEnd)->modify('first day of this month')->modify('-1 month')->format('Y-m-01');
    $lastMonthEnd   = (clone $periodEnd)->modify('first day of this month')->modify('-1 month')->format('Y-m-t');

    // --- Fetch current payroll details ---
    $detailsStmt = $db->prepare("
        SELECT 
            sc.component_id,
            sc.component_name, 
            sc.component_type,
            sc.component_code,
            pd.amount,
            pd.reference_id,
            pd.notes
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
    $currentComponents = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Fetch YTD and Last month aggregates ---
    $aggStmt = $db->prepare("
        SELECT
            sc.component_id,
            sc.component_name,
            sc.component_type,
            sc.component_code,
            pd.reference_id,
            SUM(pd.amount) AS total_ytd,
            SUM(CASE WHEN pp.end_date BETWEEN :last_start AND :last_end THEN pd.amount ELSE 0 END) AS total_last_month
        FROM payroll_details pd
        JOIN payroll_master pm ON pd.payroll_id = pm.payroll_id
        JOIN payroll_periods pp ON pm.period_id = pp.period_id
        JOIN salary_components sc ON pd.component_id = sc.component_id
        WHERE pm.employee_id = :employee_id
          AND YEAR(pp.end_date) = :year
          AND pp.end_date <= :end_date
        GROUP BY sc.component_id, pd.reference_id
    ");
    $aggStmt->execute([
        ':employee_id' => $employeeId,
        ':year' => $currentYear,
        ':end_date' => $periodEndDate,
        ':last_start' => $lastMonthStart,
        ':last_end' => $lastMonthEnd
    ]);
    $aggRows = $aggStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build lookup maps
    $ytdLookup = [];
    $lastMonthLookup = [];
    $componentMeta = [];
    foreach ($aggRows as $r) {
        $refId = $r['reference_id'] === null ? '0' : $r['reference_id'];
        $key = $r['component_code'] . '_' . $refId;
        $ytdLookup[$key] = (float)$r['total_ytd'];
        $lastMonthLookup[$key] = (float)$r['total_last_month'];
        $componentMeta[$key] = [
            'component_id' => $r['component_id'],
            'component_name' => $r['component_name'],
            'component_type' => $r['component_type'],
            'component_code' => $r['component_code'],
            'reference_id' => $r['reference_id']
        ];
    }

    // --- Prepare arrays to render: earnings, deductions ---
    $earnings = [];
    $deductions = [];
    $processedKeys = [];

    foreach ($currentComponents as $component) {
        $code = $component['component_code'] ?? '';
        $refId = $component['reference_id'] ?? null;
        $refKey = $refId === null ? '0' : $refId;
        $key = $code . '_' . $refKey;
        $ytd = isset($ytdLookup[$key]) ? (float)$ytdLookup[$key] : 0.0;
        $lastMonthAmount = isset($lastMonthLookup[$key]) ? (float)$lastMonthLookup[$key] : 0.0;

        if ($component['component_type'] === 'earning' || $component['component_type'] === 'allowance') {
            $earnings[] = [
                'component_name' => $component['component_name'],
                'current' => (float)$component['amount'],
                'last_month' => $lastMonthAmount,
                'ytd' => $ytd,
                'notes' => $component['notes'] ?? null
            ];
        } else if ($component['component_type'] === 'deduction') {
            if ($code === 'LOAN' && !empty($refId)) {
                $loanStmt = $db->prepare("
                    SELECT COALESCE(CONCAT('Loan - ', lt.loan_name), 'Loan Repayment') AS display_name
                    FROM employee_loans el
                    LEFT JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                    WHERE el.loan_id = :loan_id
                    LIMIT 1
                ");
                $loanStmt->execute([':loan_id' => $refId]);
                $loanRow = $loanStmt->fetch(PDO::FETCH_ASSOC);
                $name = $loanRow ? $loanRow['display_name'] : 'Loan Repayment';
            } else if ($code === 'ADVANCE' && !empty($refId)) {
                $advanceName = 'Salary Advance';
                try {
                    $advStmt = $db->prepare("
                        SELECT COALESCE(reason, CONCAT('Advance - ', el.advance_id)) as display_name
                        FROM employee_advances el
                        WHERE el.advance_id = :advance_id
                        LIMIT 1
                    ");
                    $advStmt->execute([':advance_id' => $refId]);
                    $advRow = $advStmt->fetch(PDO::FETCH_ASSOC);
                    if ($advRow && !empty($advRow['display_name'])) {
                        $advanceName = $advRow['display_name'];
                    }
                } catch (Exception $ex) {
                    $advanceName = 'Salary Advance';
                }
                $name = $advanceName;
            } else {
                $name = $component['component_name'];
            }
            $deductions[] = [
                'component_name' => $name,
                'current' => (float)$component['amount'],
                'last_month' => $lastMonthAmount,
                'ytd' => $ytd,
                'component_code' => $code,
                'reference_id' => $refId,
                'notes' => $component['notes'] ?? null
            ];
        }
        $processedKeys[$key] = true;
    }

    // Include deductions that exist in YTD lookup but were NOT part of current payroll
    foreach ($ytdLookup as $key => $totalYTD) {
        $meta = $componentMeta[$key] ?? null;
        if (!$meta) continue;
        if ($meta['component_type'] !== 'deduction') continue;
        if (isset($processedKeys[$key])) continue;

        $code = $meta['component_code'];
        $refId = $meta['reference_id'];
        $name = $meta['component_name'];

        if ($code === 'LOAN' && !empty($refId)) {
            $loanStmt = $db->prepare("
                SELECT COALESCE(CONCAT('Loan - ', lt.loan_name), 'Loan Repayment') AS display_name
                FROM employee_loans el
                LEFT JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                WHERE el.loan_id = :loan_id
                LIMIT 1
            ");
            $loanStmt->execute([':loan_id' => $refId]);
            $loanRow = $loanStmt->fetch(PDO::FETCH_ASSOC);
            if ($loanRow && !empty($loanRow['display_name'])) {
                $name = $loanRow['display_name'];
            } else {
                $name = 'Loan Repayment';
            }
        } else if ($code === 'ADVANCE' && !empty($refId)) {
            $advanceName = 'Salary Advance';
            try {
                $advStmt = $db->prepare("
                    SELECT COALESCE(reason, CONCAT('Advance - ', el.advance_id)) as display_name
                    FROM employee_advances el
                    WHERE el.advance_id = :advance_id
                    LIMIT 1
                ");
                $advStmt->execute([':advance_id' => $refId]);
                $advRow = $advStmt->fetch(PDO::FETCH_ASSOC);
                if ($advRow && !empty($advRow['display_name'])) {
                    $advanceName = $advRow['display_name'];
                }
            } catch (Exception $ex) {
                $advanceName = 'Salary Advance';
            }
            $name = $advanceName;
        }

        $lastMonthAmount = isset($lastMonthLookup[$key]) ? (float)$lastMonthLookup[$key] : 0.0;
        $deductions[] = [
            'component_name' => $name,
            'current' => 0.0,
            'last_month' => $lastMonthAmount,
            'ytd' => (float)$totalYTD,
            'component_code' => $code,
            'reference_id' => $refId,
            'notes' => null
        ];
    }

    // Sort deductions
    usort($deductions, function ($a, $b) {
        $order = ['deduction' => 1, 'LOAN' => 2, 'ADVANCE' => 3];
        $aKey = strtoupper($a['component_code'] ?? '');
        $bKey = strtoupper($b['component_code'] ?? '');
        $aOrder = $order[$aKey] ?? ($order['deduction'] ?? 1);
        $bOrder = $order[$bKey] ?? ($order['deduction'] ?? 1);
        if ($aOrder === $bOrder) {
            return strcmp($a['component_name'], $b['component_name']);
        }
        return $aOrder <=> $bOrder;
    });

    // Compute totals
    $totalEarnings = 0.0;
    $totalYTDEarnings = 0.0;
    foreach ($earnings as $e) {
        $totalEarnings += $e['current'];
        $totalYTDEarnings += $e['ytd'];
    }
    $totalDeductions = 0.0;
    $totalYTDDeductions = 0.0;
    foreach ($deductions as $d) {
        $totalDeductions += $d['current'];
        $totalYTDDeductions += $d['ytd'];
    }

    // --- Loan Data with Enhanced Visibility Logic ---
    $loanTypes = $db->query("SELECT loan_type_id, loan_name FROM loan_types ORDER BY loan_name")->fetchAll(PDO::FETCH_ASSOC);

    $loanQuery = "
        SELECT 
            el.loan_id,
            lt.loan_name AS type_name,
            el.loan_amount AS principal,
            el.monthly_repayment,
            el.application_date,
            el.start_repayment_date,
            el.end_repayment_date,
            el.updated_at,
            COALESCE(
                (SELECT SUM(amount_paid)
                FROM loan_repayments 
                WHERE loan_id = el.loan_id
                AND paid_date <= :period_end), 0
            ) AS total_paid,
            (
                SELECT MIN(due_date)
                FROM loan_repayments
                WHERE loan_id = el.loan_id
                AND (paid_date IS NULL OR paid_date > :period_end_2)
                AND due_date > :period_end_3
            ) AS next_due_date,
            COALESCE(
                (SELECT amount_due
                FROM loan_repayments
                WHERE loan_id = el.loan_id
                AND (paid_date IS NULL OR paid_date > :period_end_4)
                AND due_date > :period_end_5
                ORDER BY due_date ASC
                LIMIT 1), 0
            ) AS next_payment_amount,
            (
                SELECT MAX(paid_date)
                FROM loan_repayments
                WHERE loan_id = el.loan_id
                AND amount_paid > 0
                HAVING SUM(amount_paid) >= el.loan_amount
            ) AS actual_completion_date
        FROM employee_loans el
        JOIN loan_types lt ON lt.loan_type_id = el.loan_type_id
        WHERE el.employee_id = :employee_id
        ORDER BY el.application_date DESC
    ";
    $stmt = $db->prepare($loanQuery);
    $stmt->execute([
        ':employee_id'    => $payslip['employee_id'],
        ':period_end'     => $payslip['end_date'],
        ':period_end_2'   => $payslip['end_date'],
        ':period_end_3'   => $payslip['end_date'],
        ':period_end_4'   => $payslip['end_date'],
        ':period_end_5'   => $payslip['end_date']
    ]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $loanTypesShown = [];  
    $totalPrincipal = 0;
    $totalPaid = 0;
    $totalOutstanding = 0;
    $totalNextPayment = 0;

    foreach ($loans as $loan) {
        $principal = (float)$loan['principal'];
        $paid = (float)$loan['total_paid'];
        $outstanding = max(0, $principal - $paid);
        $nextPayment = (float)$loan['next_payment_amount'];
        $periodEnd = new DateTime($payslip['end_date']);
        $repayStart = new DateTime($loan['start_repayment_date']);
        $repayEnd = !empty($loan['end_repayment_date']) ? new DateTime($loan['end_repayment_date']) : null;
        $completionDate = !empty($loan['actual_completion_date']) ? new DateTime($loan['actual_completion_date']) : null;
        $updatedAt = !empty($loan['updated_at']) ? new DateTime($loan['updated_at']) : null;

        // ENHANCED STATUS & VISIBILITY LOGIC
        $showLoan = true;
        $status = 'Active';
        $statusClass = 'primary';

        // 1. Check if repayment hasn't started
        if ($periodEnd < $repayStart) {
            $status = 'Not Started';
            $statusClass = 'info';
        }
        // 2. Check if loan is completed
        elseif ($paid >= $principal) {
            $status = 'Completed';
            $statusClass = 'success';
            // Hide completed loans except in the month they were completed
            if ($completionDate) {
                $completionMonth = new DateTime($completionDate->format('Y-m-t')); // End of completion month
                if ($periodEnd > $completionMonth) {
                    $showLoan = false;
                }
            } elseif ($repayEnd && $periodEnd > $repayEnd) {
                // Fallback: if no completion date, use repayment end date
                $showLoan = false;
            }
        }
        // 3. Check if loan period has ended but not fully paid (unusual case)
        elseif ($repayEnd && $periodEnd > $repayEnd) {
            $status = 'Ended';
            $statusClass = 'warning';
        }
        // 4. Check if this is a new loan (recently updated/created)
        if ($updatedAt && $showLoan) {
            $updatedMonth = new DateTime($updatedAt->format('Y-m-t')); // End of update month
            if ($periodEnd->format('Y-m') === $updatedAt->format('Y-m')) {
                $showLoan = true;
            }
        }

        if (!$showLoan) {
            continue;
        }

        $loanTypesShown[] = $loan['type_name'];
        $totalPrincipal += $principal;
        $totalPaid += $paid;
        $totalOutstanding += $outstanding;
        $totalNextPayment += $nextPayment;
    }

} catch (Exception $e) {
    die('Error loading payslip: ' . htmlspecialchars($e->getMessage()));
}
// Set headers for print
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
                margin: 0.5cm;
            }
            body {
                margin: 0;
                font-size: 10px;
                background: white !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
            }
            .page-break {
                page-break-before: always;
            }
            .no-print {
                display: none !important;
            }
            .payslip-container {
                border: none !important;
                box-shadow: none !important;
                padding: 10px !important;
            }
            .table {
                font-size: 9px;
            }
            .section-title {
                font-size: 11px !important;
            }
        }
        @media screen {
            body {
                background: #f8f9fa;
                padding: 20px;
            }
            .payslip-container {
                max-width: 800px;
                margin: 0 auto;
                padding: 15px;
                border: 1px solid #ddd;
                background: #fff;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
        }
        body {
            font-family: verdana, arial, sans-serif;
            line-height: 1.3;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 16px;
            margin: 5px 0;
            font-weight: bold;
        }
        .header p {
            margin: 2px 0;
            font-size: 10px;
            color: #666;
        }
        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            margin-bottom: 8px;
            padding-bottom: 3px;
            background: #f8f9fa;
            padding: 5px;
        }
        .table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
            font-size: 9px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 4px;
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
            padding: 4px;
        }
        .signature {
            margin-top: 20px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 150px;
            margin: 0 auto;
        }
        .d-flex {
            display: flex;
            justify-content: space-between;
        }
        .text-xs {
            font-size: 8px !important;
        }
        .badge {
            font-size: 8px;
            padding: 2px 5px;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: rgba(0,0,0,0.1);
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="watermark">PAYSLIP</div>
    <!-- Print Controls -->
    <div class="text-center no-print mb-3">
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print Payslip
        </button>
        <button class="btn btn-secondary btn-sm" onclick="window.close()">
            <i class="fas fa-times me-1"></i>Close
        </button>
    </div>
    <div class="payslip-container">
        <!-- Page 1: Header and Basic Information -->
        <div class="header">
            <h1><?php echo htmlspecialchars($payslip['company_name'] ?? 'COMPANY NAME'); ?></h1>
            <p><?php echo htmlspecialchars($payslip['company_address'] ?? 'Company Address'); ?></p>
            <p>Phone: <?php echo htmlspecialchars($payslip['phone'] ?? 'N/A'); ?> | Email: <?php echo htmlspecialchars($payslip['email'] ?? 'N/A'); ?></p>
            <h2>EMPLOYEE PAYSLIP</h2>
        </div>
        <div class="section">
            <div class="d-flex">
                <div style="width: 48%;">
                    <div class="section-title">Employee Information</div>
                    <table class="table table-sm table-borderless">
                        <tr><td><strong>Name:</strong></td><td><?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></td></tr>
                        <tr><td><strong>Employee ID:</strong></td><td><?php echo htmlspecialchars($payslip['employee_code']); ?></td></tr>
                        <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></td></tr>
                    </table>
                </div>
                <div style="width: 48%;">
                    <div class="section-title">Payment Information</div>
                    <table class="table table-sm table-borderless">
                        <tr><td><strong>Pay Period:</strong></td><td><?php echo htmlspecialchars($payslip['period_name']); ?></td></tr>
                        <tr><td><strong>Payment Date:</strong></td><td><?php echo $payslip['payment_date'] ? formatDate($payslip['payment_date']) : 'N/A'; ?></td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="badge bg-<?php echo ($payslip['payment_status'] === 'paid' ? 'success' : 'warning'); ?>"><?php echo ucfirst($payslip['payment_status']); ?></span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Earnings Section -->
        <div class="section">
            <div class="section-title">Earnings (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</div>
            <table class="table table-sm">
                <thead><tr><th>Description</th><th class="text-end">Current</th><th class="text-end">Last Month</th><th class="text-end">YTD</th></tr></thead>
                <tbody>
                    <?php foreach ($earnings as $earning): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($earning['component_name']); ?>
                            <?php if (!empty($earning['notes'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($earning['notes']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo formatCurrency($earning['current']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($earning['last_month']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($earning['ytd']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active"><th class="text-end">Total Earnings:</th><th class="text-end"><?php echo formatCurrency($totalEarnings); ?></th><th class="text-end"></th><th class="text-end"><?php echo formatCurrency($totalYTDEarnings); ?></th></tr>
                </tbody>
            </table>
        </div>

        <!-- Deductions Section -->
        <div class="section">
            <div class="section-title">Deductions (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</div>
            <table class="table table-sm">
                <thead><tr><th>Description</th><th class="text-end">Current</th><th class="text-end">Last Month</th><th class="text-end">YTD</th></tr></thead>
                <tbody>
                    <?php foreach ($deductions as $deduction): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($deduction['component_name']); ?>
                            <?php if (!empty($deduction['notes'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($deduction['notes']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo formatCurrency($deduction['current']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($deduction['last_month']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($deduction['ytd']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active"><th class="text-end">Total Deductions:</th><th class="text-end"><?php echo formatCurrency($totalDeductions); ?></th><th class="text-end"></th><th class="text-end"><?php echo formatCurrency($totalYTDDeductions); ?></th></tr>
                </tbody>
            </table>
        </div>

        <!-- Summary Section -->
        <div class="section">
            <div class="section-title">Summary</div>
            <table class="table summary table-sm">
                <tr><th>Gross Pay:</th><td class="text-end"><?php echo formatCurrency($payslip['gross_salary']); ?></td></tr>
                <tr><th>Total Deductions:</th><td class="text-end"><?php echo formatCurrency($payslip['total_deductions']); ?></td></tr>
                <tr class="table-active"><th>Net Pay:</th><th class="text-end"><?php echo formatCurrency($payslip['net_salary']); ?></th></tr>
                <tr><td colspan="2" class="fst-italic text-xs"><strong>Amount in Words:</strong><br><?php echo numberToWords($payslip['net_salary']); ?> NAIRA ONLY</td></tr>
            </table>
        </div>

        <!-- Page Break -->
        <div class="page-break"></div>

        <!-- Page 2: Loan Details -->
        <div class="section">
            <div class="section-title text-center">
                LOAN DETAILS AS OF <?php echo date('F Y', strtotime($payslip['end_date'])); ?>
            </div>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Loan Type</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Total Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th class="text-end">Next Deduction</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): 
                        // Reconstruct from enhanced logic
                        $principal = (float)$loan['principal'];
                        $paid = (float)$loan['total_paid'];
                        $outstanding = max(0, $principal - $paid);
                        $nextPayment = (float)$loan['next_payment_amount'];
                        $periodEnd = new DateTime($payslip['end_date']);
                        $repayStart = new DateTime($loan['start_repayment_date']);
                        $repayEnd = !empty($loan['end_repayment_date']) ? new DateTime($loan['end_repayment_date']) : null;
                        $completionDate = !empty($loan['actual_completion_date']) ? new DateTime($loan['actual_completion_date']) : null;
                        $updatedAt = !empty($loan['updated_at']) ? new DateTime($loan['updated_at']) : null;

                        // Replicate status logic exactly
                        $showLoan = true;
                        $status = 'Active';
                        $statusClass = 'primary';

                        if ($periodEnd < $repayStart) {
                            $status = 'Not Started';
                            $statusClass = 'info';
                        } elseif ($paid >= $principal) {
                            $status = 'Completed';
                            $statusClass = 'success';
                        } elseif ($repayEnd && $periodEnd > $repayEnd) {
                            $status = 'Ended';
                            $statusClass = 'warning';
                        }

                        // Hide completed loan if past completion month
                        if ($status === 'Completed') {
                            if ($completionDate) {
                                $completionMonth = new DateTime($completionDate->format('Y-m-t'));
                                if ($periodEnd > $completionMonth) {
                                    continue; // skip rendering
                                }
                            } elseif ($repayEnd && $periodEnd > $repayEnd) {
                                continue;
                            }
                        }

                        // Re-include if updated this month (always show new/updated loans)
                        if ($updatedAt && $periodEnd->format('Y-m') === $updatedAt->format('Y-m')) {
                            $showLoan = true;
                        }
                        if (!$showLoan) continue;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($loan['type_name']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($principal); ?></td>
                        <td class="text-end"><?php echo formatCurrency($paid); ?></td>
                        <td class="text-end"><?php echo formatCurrency($outstanding); ?></td>
                        <td class="text-end">
                            <?php if ($nextPayment > 0 && $status !== 'Completed'): ?>
                                <?php echo formatCurrency($nextPayment); ?>
                                <?php if (!empty($loan['next_due_date'])): ?>
                                    <br><small class="text-muted">Due: <?php echo date('M j, Y', strtotime($loan['next_due_date'])); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $status; ?></span>
                            <?php if ($status === 'Completed' && $completionDate): ?>
                                <br><small class="text-muted"><?php echo $completionDate->format('M Y'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php foreach ($loanTypes as $lt): ?>
                        <?php if (!in_array($lt['loan_name'], array_column($loans, 'type_name'))): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lt['loan_name']); ?> (No Loan)</td>
                                <td class="text-end">0.00</td>
                                <td class="text-end">0.00</td>
                                <td class="text-end">0.00</td>
                                <td class="text-end">-</td>
                                <td><span class="badge bg-secondary">Not Taken</span></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($loans) && $totalPrincipal > 0): ?>
                <tfoot class="table-active">
                    <tr>
                        <th class="text-end">Totals:</th>
                        <th class="text-end"><?php echo formatCurrency($totalPrincipal); ?></th>
                        <th class="text-end"><?php echo formatCurrency($totalPaid); ?></th>
                        <th class="text-end"><?php echo formatCurrency($totalOutstanding); ?></th>
                        <th class="text-end"><?php echo formatCurrency($totalNextPayment); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Pension Fund Details -->
        <div class="section">
            <div class="section-title text-center">
                PENSION FUND DETAILS AS OF <?php echo date('F Y', strtotime($payslip['end_date'])); ?>
            </div>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Contribution Type</th>
                        <th class="text-end">Current Period</th>
                        <th class="text-end">YTD (<?php echo date('Y', strtotime($payslip['end_date'])); ?>)</th>
                        <th class="text-end">To Date (All Time)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $employeePensionDeduction = 0;
                    $pensionComponentName = '';
                    
                    foreach ($deductions as $deduction) {
                        if (stripos($deduction['component_name'], 'pension') !== false || 
                            ($deduction['component_code'] ?? '') === 'PENSION') {
                            $employeePensionDeduction = (float)$deduction['current'];
                            $pensionComponentName = $deduction['component_name'];
                            break;
                        }
                    }

                    if ($employeePensionDeduction <= 0) {
                        foreach ($currentComponents as $component) {
                            if ($component['component_type'] === 'deduction' && 
                                (stripos($component['component_name'], 'pension') !== false || 
                                ($component['component_code'] ?? '') === 'PENSION')) {
                                $employeePensionDeduction = (float)$component['amount'];
                                $pensionComponentName = $component['component_name'];
                                break;
                            }
                        }
                    }

                    if ($employeePensionDeduction > 0) {
                        $pensionableSalary = $employeePensionDeduction / 0.08;
                        $employerContribution = $pensionableSalary * 0.10;
                        $totalContribution = $employeePensionDeduction + $employerContribution;

                        $employeePensionYTD = 0;
                        foreach ($deductions as $deduction) {
                            if (stripos($deduction['component_name'], 'pension') !== false || 
                                ($deduction['component_code'] ?? '') === 'PENSION') {
                                $employeePensionYTD = (float)$deduction['ytd'];
                                break;
                            }
                        }
                        $employerPensionYTD = $employeePensionYTD / 0.08 * 0.10;

                        $totalEmployeePension = 0;
                        $totalEmployerPension = 0;
                        try {
                            $pensionTotalStmt = $db->prepare("
                                SELECT SUM(pd.amount) as total_employee_pension
                                FROM payroll_details pd
                                JOIN payroll_master pm ON pd.payroll_id = pm.payroll_id
                                JOIN payroll_periods pp ON pm.period_id = pp.period_id
                                JOIN salary_components sc ON pd.component_id = sc.component_id
                                WHERE pm.employee_id = :employee_id
                                AND sc.component_type = 'deduction'
                                AND (LOWER(sc.component_name) LIKE '%pension%' OR sc.component_code = 'PENSION')
                                AND pp.end_date <= :period_end
                            ");
                            $pensionTotalStmt->execute([
                                ':employee_id' => $payslip['employee_id'],
                                ':period_end' => $payslip['end_date']
                            ]);
                            $pensionTotal = $pensionTotalStmt->fetch(PDO::FETCH_ASSOC);
                            $totalEmployeePension = (float)($pensionTotal['total_employee_pension'] ?? 0);
                            $totalEmployerPension = $totalEmployeePension / 0.08 * 0.10;
                        } catch (Exception $e) {
                            $totalEmployeePension = $employeePensionYTD;
                            $totalEmployerPension = $employerPensionYTD;
                            error_log("Pension total query error: " . $e->getMessage());
                        }

                        echo '<tr>';
                        echo '<td>Employee Contribution (8%)</td>';
                        echo '<td class="text-end">' . formatCurrency($employeePensionDeduction) . '</td>';
                        echo '<td class="text-end">' . formatCurrency($employeePensionYTD) . '</td>';
                        echo '<td class="text-end">' . formatCurrency($totalEmployeePension) . '</td>';
                        echo '<td><span class="badge bg-success">Active</span></td>';
                        echo '</tr>';

                        echo '<tr>';
                        echo '<td>Employer Contribution (10%)</td>';
                        echo '<td class="text-end">' . formatCurrency($employerContribution) . '</td>';
                        echo '<td class="text-end">' . formatCurrency($employerPensionYTD) . '</td>';
                        echo '<td class="text-end">' . formatCurrency($totalEmployerPension) . '</td>';
                        echo '<td><span class="badge bg-success">Active</span></td>';
                        echo '</tr>';

                        echo '<tr class="table-active">';
                        echo '<td><strong>Total Pension Contribution</strong></td>';
                        echo '<td class="text-end"><strong>' . formatCurrency($totalContribution) . '</strong></td>';
                        echo '<td class="text-end"><strong>' . formatCurrency($employeePensionYTD + $employerPensionYTD) . '</strong></td>';
                        echo '<td class="text-end"><strong>' . formatCurrency($totalEmployeePension + $totalEmployerPension) . '</strong></td>';
                        echo '<td></td>';
                        echo '</tr>';

                        echo '<tr class="small text-muted">';
                        echo '<td colspan="5" class="text-center">To Date represents total contributions from employment start to ' . date('F Y', strtotime($payslip['end_date'])) . '</td>';
                        echo '</tr>';
                    } else {
                        echo '<tr><td colspan="5" class="text-center text-muted">No pension deduction found for this period</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Page 3: Advance Eligibility -->
        <div class="section">
            <div class="section-title text-center">ADVANCE ELIGIBILITY FOR NEXT MONTH</div>
            <?php
            require_once '../../includes/LoanManager.php';
            $loanManager = new LoanManager($db);
            $limitInfo = $loanManager->checkAdvanceBorrowingLimit($payslip['employee_id'], 0);
            
            $grossSalary = $limitInfo['gross_salary'];
            $maxLimit = $limitInfo['max_limit'];
            $monthlyRepayment = $limitInfo['monthly_loan_repayment'];
            $availableAmount = $limitInfo['available_amount'];
            
            $statusClass = 'success';
            if ($availableAmount <= 0) {
                $statusClass = 'danger';
            } elseif ($availableAmount < ($maxLimit * 0.2)) {
                $statusClass = 'warning';
            }
            ?>
            <table class="table table-sm mb-3">
                <thead>
                    <tr>
                        <th>Gross Salary</th>
                        <th class="text-end">Max Advance Limit (33%)</th>
                        <th class="text-end">Monthly Loan Repayments</th>
                        <th class="text-end">Available Advance Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo formatCurrency($grossSalary); ?></td>
                        <td class="text-end"><?php echo formatCurrency($maxLimit); ?></td>
                        <td class="text-end text-danger"><?php echo formatCurrency($monthlyRepayment); ?></td>
                        <td class="text-end"><strong><?php echo formatCurrency($availableAmount); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <div class="alert alert-info p-2 text-xs mb-3">
                <strong>Note:</strong> Your maximum advance limit is 33% of your gross salary (<?php echo formatCurrency($maxLimit); ?>). Any active monthly loan repayments (<?php echo formatCurrency($monthlyRepayment); ?>) are deducted from this limit to determine your available advance amount. Loans are not restricted by this limit.
            </div>
            <?php if ($availableAmount > 0): ?>
                <div class="alert alert-success p-2 text-xs text-center mb-3">
                    <strong>✅ You are eligible to request an advance up to: <?php echo formatCurrency($availableAmount); ?></strong>
                </div>
            <?php else: ?>
                <div class="alert alert-warning p-2 text-xs text-center mb-3">
                    <strong>⚠️ You are currently not eligible for an advance due to high monthly loan repayments.</strong>
                </div>
            <?php endif; ?>

            <!-- Footer Section -->
            <div class="row mt-4">
                <div class="col-6">
                    <p class="mb-1 text-xs"><strong>Date Generated:</strong> <?php echo date('F j, Y'); ?></p>
                    <p class="mb-1 text-xs"><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System'); ?></p>
                </div>
                <div class="col-6 text-end">
                    <div class="signature-line"></div>
                    <p class="mb-0 text-xs"><strong>Authorized Signature</strong></p>
                </div>
            </div>
            <div class="text-center mt-3">
                <p class="text-muted text-xs mb-0">This is a computer-generated document. No signature is required.</p>
                <p class="text-muted text-xs mb-0">Page 3 of 3</p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>