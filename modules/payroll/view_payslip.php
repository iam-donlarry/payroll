<?php
// view_payslips.php

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
            pp.period_id, pp.period_name, pp.start_date, pp.end_date, pp.payment_date
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

    $employeeId = $payslip['employee_id'];
    $periodEndDate = $payslip['end_date'];
    $currentYear = date('Y', strtotime($payslip['start_date'])); // Use start_date year
    // Use end_date to determine last month
    $periodEnd = new DateTime($periodEndDate);

    // Last calendar month relative to the current period end
    $lastMonthStart = (clone $periodEnd)->modify('first day of this month')->modify('-1 month')->format('Y-m-01');
    $lastMonthEnd   = (clone $periodEnd)->modify('first day of this month')->modify('-1 month')->format('Y-m-t');

    // --- Fetch current payroll details (components for this payroll) ---
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
    $currentComponents = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Fetch YTD and Last month aggregates (for the employee, for the year up to current end_date) ---
    // We aggregate by component_id and reference_id (to separate different loans/advances)
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

    // Build lookup maps keyed by "componentCode_referenceId" for easy access
    $ytdLookup = [];       // total_ytd
    $lastMonthLookup = []; // total_last_month
    $componentMeta = [];   // store meta like component_name, type, code

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

    // Keep track of processed keys (components that appear in the current payroll)
    $processedKeys = [];

    // First, process current payroll components (display Current and pull YTD/Last from lookups)
    foreach ($currentComponents as $component) {
        $code = $component['component_code'] ?? '';
        $refId = $component['reference_id'] ?? null;
        $refKey = $refId === null ? '0' : $refId;
        $key = $code . '_' . $refKey;

        $ytd = isset($ytdLookup[$key]) ? (float)$ytdLookup[$key] : 0.0;
        $lastMonthAmount = isset($lastMonthLookup[$key]) ? (float)$lastMonthLookup[$key] : 0.0;

        // Components: earnings / allowance
        if ($component['component_type'] === 'earning' || $component['component_type'] === 'allowance') {
            $earnings[] = [
                'component_name' => $component['component_name'],
                'current' => (float)$component['amount'],
                'last_month' => $lastMonthAmount,
                'ytd' => $ytd
            ];
        } 
        // Deductions (including LOAN and ADVANCE)
        else if ($component['component_type'] === 'deduction') {
            // Special naming for LOAN and ADVANCE to show meaningful labels
            if ($code === 'LOAN' && !empty($refId)) {
                // Attempt to fetch loan display name
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
                // Try to fetch advance reason/name if such a table exists (optional)
                // If you have a table like employee_advances, replace accordingly.
                $advanceName = 'Salary Advance';
                // Optional: attempt to read from employee_advances (if exists)
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
                    // If employee_advances table doesn't exist, ignore silently
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
                'reference_id' => $refId
            ];
        }

        $processedKeys[$key] = true;
    }

    // --- Now include deductions that exist in YTD lookup but were NOT part of current payroll ---
    // This is important for showing prior Advances/Loans in YTD even if they don't appear this month.
    foreach ($ytdLookup as $key => $totalYTD) {
        // Only add if it's a deduction-type component and not already processed
        $meta = $componentMeta[$key] ?? null;
        if (!$meta) continue;

        if ($meta['component_type'] !== 'deduction') {
            // earnings/allowances already handled by currentComponents loop (if present).
            continue;
        }

        if (isset($processedKeys[$key])) {
            continue; // already present in current payroll; skip
        }

        // Build name similar to logic above
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
            'reference_id' => $refId
        ];
    }

    // Sort deductions as desired: keep original ordering by putting regular deductions first, then loans, then advances
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

    // Compute totals for earnings & deductions for display summary (if needed)
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
        <a href="payslips.php?period_id=<?php echo htmlspecialchars($payslip['period_id']); ?>" class="text-decoration-none text-gray-600">
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
                <div class="badge bg-<?php echo ($payslip['payment_status'] === 'paid' ? 'success' : 'warning'); ?> text-white p-2">
                    <?php echo ucfirst(htmlspecialchars($payslip['payment_status'])); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Employee and Company Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Employee Details</h5>
                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></p>
                <p class="mb-1"><strong>Employee ID:</strong> <?php echo htmlspecialchars($payslip['employee_code']); ?></p>
                <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <h5>Payment Details</h5>
                <p class="mb-1"><strong>Payment Date:</strong> <?php echo $payslip['payment_date'] ? formatDate($payslip['payment_date']) : 'N/A'; ?></p>
                <p class="mb-1"><strong>Bank:</strong> <?php echo htmlspecialchars($payslip['bank_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($payslip['bank_code'] ?? 'N/A'); ?>)</p>
                <p class="mb-1"><strong>Account Number:</strong> <?php echo htmlspecialchars($payslip['account_number'] ?? 'N/A'); ?></p>
                <p class="mb-1"><strong>Account Name:</strong> <?php echo htmlspecialchars($payslip['account_name'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <!-- Earnings -->
        <div class="table-responsive mb-4">
            <h5>Earnings</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Current (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</th>
                        <th class="text-end">Last Month (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</th>
                        <th class="text-end">YTD (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($earnings as $earning): 
                        $totalEarnings += $earning['current']; // already accumulated above but safe
                        $totalYTDEarnings += $earning['ytd'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($earning['component_name']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($earning['current']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($earning['last_month']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($earning['ytd']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active">
                        <td class="text-end"><strong>Total Earnings:</strong></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalEarnings); ?></strong></td>
                        <td class="text-end"></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalYTDEarnings); ?></strong></td>
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
                        <th class="text-end">Current (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</th>
                        <th class="text-end">Last Month (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</th>
                        <th class="text-end">YTD (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalDeductions = 0.0;
                    $totalYTDDeductions = 0.0;
                    foreach ($deductions as $deduction): 
                        $totalDeductions += $deduction['current'];
                        $totalYTDDeductions += $deduction['ytd'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($deduction['component_name']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($deduction['current']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($deduction['last_month']); ?></td>
                        <td class="text-end"><?php echo formatCurrency($deduction['ytd']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active">
                        <td class="text-end"><strong>Total Deductions:</strong></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalDeductions); ?></strong></td>
                        <td class="text-end"></td>
                        <td class="text-end"><strong><?php echo formatCurrency($totalYTDDeductions); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Loan Details Report -->
        <div class="table-responsive mb-4">
            <h5>
                Loan Details as of <?php echo date('F Y', strtotime($payslip['end_date'])); ?>
            </h5>
        
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Loan Type</th>
                        <th class="text-end">Principal (<?php echo htmlspecialchars($payslip['currency'] ?? 'NGN'); ?>)</th>
                        <th class="text-end">Total Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th class="text-end">Next Deduction</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                    // 1. Fetch all loan types
                    $loanTypes = $db->query("SELECT loan_type_id, loan_name FROM loan_types ORDER BY loan_name")
                                    ->fetchAll(PDO::FETCH_ASSOC);

                    // 2. Fetch employee loans with completion date tracking
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

                            -- Get the actual completion date (when loan was fully paid)
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

                        /** ENHANCED STATUS & VISIBILITY LOGIC **/
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
                            // If loan was updated in current period, always show it
                            if ($periodEnd->format('Y-m') === $updatedAt->format('Y-m')) {
                                $showLoan = true;
                            }
                        }

                        // Skip this loan if it shouldn't be shown
                        if (!$showLoan) {
                            continue;
                        }

                        $loanTypesShown[] = $loan['type_name'];

                        // Add to totals only if loan is shown
                        $totalPrincipal += $principal;
                        $totalPaid += $paid;
                        $totalOutstanding += $outstanding;
                        $totalNextPayment += $nextPayment;
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
                                    <br><small class="text-muted">Due: 
                                        <?php echo date('M j, Y', strtotime($loan['next_due_date'])); ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge bg-<?php echo $statusClass; ?>">
                                <?php echo $status; ?>
                            </span>
                            <?php if ($status === 'Completed' && $completionDate): ?>
                                <br><small class="text-muted"><?php echo $completionDate->format('M Y'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php
                    } // end foreach loan

                    /** SHOW LOAN TYPES NOT TAKEN **/
                    foreach ($loanTypes as $lt) {
                        if (!in_array($lt['loan_name'], $loanTypesShown)) {
                            echo "
                                <tr>
                                    <td>{$lt['loan_name']} (No Loan)</td>
                                    <td class='text-end'>0.00</td>
                                    <td class='text-end'>0.00</td>
                                    <td class='text-end'>0.00</td>
                                    <td class='text-end'>-</td>
                                    <td><span class='badge bg-secondary'>Not Taken</span></td>
                                </tr>
                            ";
                        }
                    }
                ?>
                </tbody>

                <?php if (!empty($loans) && $totalPrincipal > 0) : ?>
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
