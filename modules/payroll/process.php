<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

session_start();

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['period_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $period_id = intval($_GET['period_id']);
    
    try {
        // Verify the period is still in draft status
        $stmt = $db->prepare("SELECT status FROM payroll_periods WHERE period_id = ?");
        $stmt->execute([$period_id]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) {
            $_SESSION['error'] = "Payroll period not found.";
        } elseif ($period['status'] !== 'draft') {
            $_SESSION['error'] = "Only draft payroll periods can be deleted.";
        } else {
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // First, delete the payroll details
                $stmt = $db->prepare("
                    DELETE pd FROM payroll_details pd
                    JOIN payroll_master pm ON pd.payroll_id = pm.payroll_id
                    WHERE pm.period_id = ?
                ");
                $stmt->execute([$period_id]);

                // Reset occasional payments linked to this period's payrolls
                $stmt = $db->prepare("
                    UPDATE employee_occasional_payments eop
                    JOIN payroll_master pm ON eop.payroll_id = pm.payroll_id
                    SET eop.status = 'pending', eop.payroll_id = NULL
                    WHERE pm.period_id = ?
                ");
                $stmt->execute([$period_id]);
                
                // Then delete the payroll master records
                $stmt = $db->prepare("DELETE FROM payroll_master WHERE period_id = ?");
                $stmt->execute([$period_id]);
                
                // Finally, delete the period
                $stmt = $db->prepare("DELETE FROM payroll_periods WHERE period_id = ?");
                $stmt->execute([$period_id]);
                
                // Commit the transaction
                $db->commit();
                
                $_SESSION['success'] = "Payroll period deleted successfully.";
            } catch (Exception $e) {
                // Rollback the transaction if something went wrong
                $db->rollBack();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting payroll period: " . $e->getMessage();
    }
    
    // Redirect back to the payroll periods list
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requirePermission('payroll_master');

$page_title = "Process Payroll";
$body_class = "payroll-process-page";

$success = $error = '';
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;

// Fetch payroll periods for selection
try {
    $stmt = $db->prepare("SELECT * FROM payroll_periods ORDER BY start_date DESC");
    $stmt->execute();
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching payroll periods: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['period_id'])) {
    $period_id = intval($_POST['period_id']);

    // Check if the selected period is locked
    $stmt = $db->prepare("SELECT status FROM payroll_periods WHERE period_id = ?");
    $stmt->execute([$period_id]);
    $period = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($period && strtolower($period['status']) === 'locked') {
        $error = "This payroll period is locked and cannot be processed.";
    } else {
        $success = processPayroll($db, $period_id);
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Process Payroll</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Payroll
    </a>
</div>

<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Payroll Processing</h6>
    </div>
    <div class="card-body">

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Select Payroll Period *</label>
                <select class="form-control" name="period_id" required>
                    <option value="">Select Period</option>
                    <?php foreach ($periods as $p): ?>
                        <?php if (strtolower($p['status']) === 'locked'): ?>
                            <option value="" disabled>
                                <?php echo htmlspecialchars($p['period_name']); ?> 
                                (<?php echo formatDate($p['start_date']); ?> - <?php echo formatDate($p['end_date']); ?>) - Locked
                            </option>
                        <?php else: ?>
                            <option value="<?php echo $p['period_id']; ?>" 
                                <?php echo ($period_id == $p['period_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['period_name']); ?> 
                                (<?php echo formatDate($p['start_date']); ?> - <?php echo formatDate($p['end_date']); ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-calculator me-2"></i>Process Payroll
            </button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php

/**
 * Main function to process payroll for all active employees in a given period.
 * FIX: Loan repayments are NOT recorded during processing - only when payroll is marked as paid
 */
function processPayroll($db, $period_id) {
    try {
        // 1. Ensure all required salary components exist
        ensureSalaryComponents($db);

        // 2. Fetch the selected period
        $stmt = $db->prepare("SELECT * FROM payroll_periods WHERE period_id = ?");
        $stmt->execute([$period_id]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$period) {
            return "Invalid payroll period selected.";
        }

        // 3. Update the payroll period status to 'processing'
        $stmt = $db->prepare("UPDATE payroll_periods SET status = 'processing' WHERE period_id = ?");
        $stmt->execute([$period_id]);

        // 4. Check if payroll records already exist for this period
        $stmt = $db->prepare("SELECT * FROM payroll_master WHERE period_id = ?");
        $stmt->execute([$period_id]);
        $existingPayroll = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Fetch all active employees
        $stmt = $db->prepare(
            "SELECT e.*, d.department_name, et.type_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN employee_types et ON e.employee_type_id = et.employee_type_id
            WHERE e.status = 'active'"
        );
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($employees)) {
            return "No active employees found to process.";
        }

        // Get component IDs from database for details insertion
        $componentStmt = $db->prepare("SELECT component_id, component_name, component_code, component_type FROM salary_components WHERE is_active = 1");
        $componentStmt->execute();
        $components = [];
        while ($row = $componentStmt->fetch(PDO::FETCH_ASSOC)) {
            $components[$row['component_name']] = $row['component_id'];
            $components[$row['component_code']] = $row['component_id'];
        }

        // 6. Process each employee
        foreach ($employees as $emp) {
            $employee_id = $emp['employee_id'];

            // Check if this employee already has a payroll record for this period
            $existingRecord = array_filter($existingPayroll, function ($record) use ($employee_id) {
                return $record['employee_id'] == $employee_id;
            });

            if ($existingRecord) {
                $existingPayrollId = current($existingRecord)['payroll_id'];
                // Reset occasional payments linked to this payroll so they can be re-calculated
                $stmt = $db->prepare("UPDATE employee_occasional_payments SET status = 'pending', payroll_id = NULL WHERE payroll_id = ?");
                $stmt->execute([$existingPayrollId]);
            }

            // Calculate salary data
            $salary_data = calculateGrossSalaryFromDB($db, $employee_id);
            $basic_salary = $salary_data['basic_salary'];
            $allowances_data = $salary_data['allowances_data'];
            $total_allowances = $allowances_data['total'];

            // Gather occasional taxable payments for this month
            $occasionalPayments = getOccasionalTaxablePayments($db, $employee_id, $period['end_date']);
            $occasionalPaymentIds = array_column($occasionalPayments, 'payment_id');
            $occasional_total = array_sum(array_column($occasionalPayments, 'amount'));

            // Determine 13th month eligibility (December only)
            $isDecember = date('m', strtotime($period['end_date'])) === '12';
            $thirteenthMonth = 0;
            if ($isDecember) {
                $thirteenthMonth = calculateThirteenthMonth($basic_salary, $emp['employment_date'], $period['end_date']);
            }

            $gross_salary = $basic_salary + $total_allowances + $occasional_total + $thirteenthMonth;
            $total_earnings = $gross_salary;

            if ($gross_salary <= 0) {
                continue; // Skip employees with no defined earnings
            }

            // Get deductions including loans and advances
            $deductions = getDeductions($db, $employee_id, $gross_salary, $period['start_date'], $period['end_date']);
            $total_deductions = $deductions['total'];
            $net_salary = $total_earnings - $total_deductions;

            if ($existingRecord) {
                // Update existing record
                $existingRecord = current($existingRecord);
                $stmt = $db->prepare(
                    "UPDATE payroll_master SET 
                    basic_salary = :basic_salary, 
                    total_earnings = :total_earnings, 
                    total_deductions = :total_deductions, 
                    gross_salary = :gross_salary, 
                    net_salary = :net_salary
                    WHERE payroll_id = :payroll_id"
                );
                $stmt->execute([
                    ':basic_salary' => $basic_salary,
                    ':total_earnings' => $total_earnings,
                    ':total_deductions' => $total_deductions,
                    ':gross_salary' => $gross_salary,
                    ':net_salary' => $net_salary,
                    ':payroll_id' => $existingRecord['payroll_id']
                ]);

                // Delete existing payroll details for this payroll record
                $stmt = $db->prepare("DELETE FROM payroll_details WHERE payroll_id = ?");
                $stmt->execute([$existingRecord['payroll_id']]);

                // Insert updated payroll details
                $payroll_id = $existingRecord['payroll_id'];
            } else {
                // Insert new record
                $stmt = $db->prepare(
                    "INSERT INTO payroll_master 
                    (period_id, employee_id, basic_salary, total_earnings, total_deductions, gross_salary, net_salary, payment_status)
                    VALUES (:period_id, :employee_id, :basic_salary, :total_earnings, :total_deductions, :gross_salary, :net_salary, 'pending')"
                );
                $stmt->execute([
                    ':period_id' => $period_id,
                    ':employee_id' => $employee_id,
                    ':basic_salary' => $basic_salary,
                    ':total_earnings' => $total_earnings,
                    ':total_deductions' => $total_deductions,
                    ':gross_salary' => $gross_salary,
                    ':net_salary' => $net_salary
                ]);

                $payroll_id = $db->lastInsertId();
            }

            // Insert earnings (Basic + Allowances + Occasional + 13th month)
            $earnings = [];
            $earnings[] = [
                'component_id' => $components['Basic Salary'] ?? $components['BASIC'] ?? 1,
                'component_name' => 'Basic Salary',
                'amount' => $basic_salary
            ];
            
            if (!empty($allowances_data['components'])) {
                foreach ($allowances_data['components'] as $allowance) {
                    $earnings[] = [
                        'component_id' => $components[$allowance['component_name']] ?? $allowance['component_id'] ?? 0,
                        'component_name' => $allowance['component_name'],
                        'amount' => $allowance['amount']
                    ];
                }
            }

            if (!empty($occasionalPayments)) {
                foreach ($occasionalPayments as $payment) {
                    $earnings[] = [
                        'component_id' => $components['OTP'] ?? $components['Occasional Taxable Payment'] ?? 0,
                        'component_name' => 'Occasional Taxable Payment',
                        'amount' => $payment['amount'],
                        'reference_id' => $payment['payment_id'],
                        'reference_type' => 'occasional_payment',
                        'notes' => $payment['title']
                    ];
                }
            }

            if ($thirteenthMonth > 0) {
                $earnings[] = [
                    'component_id' => $components['13TH_BONUS'] ?? $components['13th Month Salary'] ?? $components['13TH'] ?? 0,
                    'component_name' => '13th Month Salary',
                    'amount' => $thirteenthMonth,
                    'reference_type' => 'thirteenth_month'
                ];
            }
            
            insertPayrollDetails($db, $payroll_id, $earnings, 'earning');

            if (!empty($occasionalPaymentIds)) {
                markOccasionalPaymentsAsPaid($db, $occasionalPaymentIds, $payroll_id);
            }

            // Insert deductions including loans and advances
            if (!empty($deductions['components'])) {
                insertPayrollDetails($db, $payroll_id, $deductions['components'], 'deduction');
                
                // FIX: DO NOT record loan and advance repayments during processing
                // They will be recorded only when payroll is marked as paid
                // recordLoanAndAdvanceRepayments($db, $payroll_id, $employee_id, $period['end_date']);
            }
        }

        return "✅ Payroll processed successfully. Loan deductions are calculated but will only be applied when payroll is marked as paid.";

    } catch (PDOException $e) {
        return "❌ Error processing payroll: " . $e->getMessage();
    }
}

// ------------------------------
// NEW FUNCTION: Mark Payroll as Paid and Record Loan Repayments
// ------------------------------

/**
 * Mark payroll as paid and record loan/advance repayments
 * 
 * @param PDO $db Database connection
 * @param int $payroll_id The ID of the payroll to mark as paid
 * @return array Result of the operation with success status and message
 */
function markPayrollAsPaid($db, $payroll_id) {
    try {
        // Start transaction
        $db->beginTransaction();

        // Get payroll details with employee and period info
        $stmt = $db->prepare("
            SELECT 
                pm.*, 
                e.employee_id, 
                e.employee_code, 
                e.first_name, 
                e.last_name,
                pp.start_date, 
                pp.end_date,
                pp.period_name
            FROM payroll_master pm
            JOIN employees e ON pm.employee_id = e.employee_id
            JOIN payroll_periods pp ON pm.period_id = pp.period_id
            WHERE pm.payroll_id = ? 
            AND pm.payment_status IN ('pending', 'partial')
            FOR UPDATE
        ");
        $stmt->execute([$payroll_id]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payroll) {
            throw new Exception("Payroll not found or already fully paid");
        }

        // Record loan and advance repayments
        $loan_updates = recordLoanAndAdvanceRepayments(
            $db, 
            $payroll_id, 
            $payroll['employee_id'], 
            $payroll['end_date']
        );

        // Update payroll status to paid
        $stmt = $db->prepare("
            UPDATE payroll_master 
            SET 
                payment_status = 'paid',
                payment_date = CURDATE(),
                paid_amount = net_salary,  -- Set paid amount to net salary when fully paid
                updated_at = NOW()
            WHERE payroll_id = ?
        ");
        $stmt->execute([$payroll_id]);

        // Commit transaction
        $db->commit();

        // Log the payment
        error_log(sprintf(
            "Payroll marked as paid - Payroll ID: %d, Employee: %s %s, Period: %s, Net Salary: %s",
            $payroll_id,
            $payroll['first_name'],
            $payroll['last_name'],
            $payroll['period_name'],
            number_format($payroll['net_salary'], 2)
        ));

        return [
            'success' => true,
            'message' => 'Payroll marked as paid successfully',
            'loan_updates' => $loan_updates,
            'payroll' => [
                'employee_name' => $payroll['first_name'] . ' ' . $payroll['last_name'],
                'period' => $payroll['period_name'],
                'net_salary' => $payroll['net_salary'],
                'payment_date' => date('Y-m-d')
            ]
        ];

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        // Log the error
        error_log(sprintf(
            "Error marking payroll as paid - Payroll ID: %d, Error: %s",
            $payroll_id,
            $e->getMessage()
        ));
        
        return [
            'success' => false, 
            'message' => 'Failed to mark payroll as paid: ' . $e->getMessage()
        ];
    }
}

/**
 * Mark entire payroll period as paid
 */
function markPayrollPeriodAsPaid($db, $period_id) {
    try {
        // Get all payroll records for this period
        $stmt = $db->prepare("SELECT payroll_id, employee_id FROM payroll_master WHERE period_id = ? AND payment_status = 'pending'");
        $stmt->execute([$period_id]);
        $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($payrolls)) {
            return "No pending payroll records found for this period.";
        }

        // Get period end date
        $stmt = $db->prepare("SELECT end_date FROM payroll_periods WHERE period_id = ?");
        $stmt->execute([$period_id]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        $period_end = $period['end_date'];

        // Begin transaction
        $db->beginTransaction();

        try {
            $success_count = 0;
            $error_count = 0;

            foreach ($payrolls as $payroll) {
                // Update payroll status to paid
                $stmt = $db->prepare("UPDATE payroll_master SET payment_status = 'paid', payment_date = NOW() WHERE payroll_id = ?");
                $stmt->execute([$payroll['payroll_id']]);

                // Record loan and advance repayments
                $result = recordLoanAndAdvanceRepayments($db, $payroll['payroll_id'], $payroll['employee_id'], $period_end);
                
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }

            // Commit transaction
            $db->commit();
            
            return "✅ $success_count payroll records marked as paid and loan repayments recorded. $error_count errors.";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            throw $e;
        }

    } catch (PDOException $e) {
        return "❌ Error marking payroll period as paid: " . $e->getMessage();
    }
}

// ------------------------------
// Helper Functions (Keep the same as before but remove the call to recordLoanAndAdvanceRepayments from processPayroll)
// ------------------------------

/**
 * Calculates the Total Monthly Gross Salary amount by fetching and summing 
 * all active Basic and Allowance components from the database structure.
 */
function calculateGrossSalaryFromDB($db, $employee_id) {
    // Fetches all active components that are classified as 'earning' or 'allowance'
    $stmt = $db->prepare("
        SELECT es.amount, sc.component_name, sc.component_type, sc.component_id 
        FROM employee_salary_structure es
        JOIN salary_components sc ON es.component_id = sc.component_id
        WHERE es.employee_id = ? AND es.is_active = 1
        AND sc.component_type IN ('earning', 'allowance') 
        ORDER BY es.effective_date DESC
    ");
    $stmt->execute([$employee_id]);
    $components_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $basic_salary = 0;
    $total_allowances = 0;
    $allowance_components = [];
    
    foreach ($components_data as $row) {
        if ($row['component_id'] == 1 || $row['component_name'] == 'Basic Salary') {
            // Assign Basic Salary amount
            $basic_salary = $row['amount'];
        } else {
            // Sum Allowances
            $total_allowances += $row['amount'];
            $allowance_components[] = [
                'component_id' => $row['component_id'],
                'component_name' => $row['component_name'],
                'amount' => $row['amount']
            ];
        }
    }
    
    return [
        'basic_salary' => $basic_salary,
        'allowances_data' => [
            'total' => $total_allowances,
            'components' => $allowance_components
        ]
    ];
}

/**
 * Fetch occasional taxable payments for a specific employee/month.
 */
function getOccasionalTaxablePayments($db, $employee_id, $period_end) {
    try {
        $month = date('Y-m', strtotime($period_end));
        $stmt = $db->prepare("
            SELECT payment_id, title, amount
            FROM employee_occasional_payments
            WHERE employee_id = :employee_id
              AND status = 'pending'
              AND DATE_FORMAT(pay_month, '%Y-%m') = :month
        ");
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':month' => $month
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching occasional payments: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark occasional payments as paid once payroll is created.
 */
function markOccasionalPaymentsAsPaid($db, $paymentIds, $payroll_id) {
    if (empty($paymentIds)) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
    $params = $paymentIds;
    array_unshift($params, $payroll_id);

    try {
        $stmt = $db->prepare("
            UPDATE employee_occasional_payments
            SET status = 'paid',
                payroll_id = ?,
                paid_at = NOW(),
                updated_at = NOW()
            WHERE payment_id IN ($placeholders)
        ");
        $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error marking occasional payments as paid: " . $e->getMessage());
    }
}

/**
 * Calculate the 13th month salary (December only, prorated by months worked in the year).
 */
function calculateThirteenthMonth($basic_salary, $employment_date, $period_end) {
    if ($basic_salary <= 0 || empty($employment_date) || empty($period_end)) {
        return 0;
    }

    $periodDate = new DateTime($period_end);
    if ($periodDate->format('m') !== '12') {
        return 0;
    }

    try {
        $employmentDate = new DateTime($employment_date);
    } catch (Exception $e) {
        return 0;
    }

    $periodYear = (int)$periodDate->format('Y');
    $employmentYear = (int)$employmentDate->format('Y');

    if ($employmentYear > $periodYear) {
        return 0;
    }

    if ($employmentYear === $periodYear) {
        $monthsWorked = 12 - (int)$employmentDate->format('n') + 1;
        $monthsWorked = max(1, min(12, $monthsWorked));
    } else {
        $monthsWorked = 12;
    }

    $thirteenth = $basic_salary * ($monthsWorked / 12);
    return round($thirteenth, 2);
}

/**
 * Get loan and advance deductions for an employee
 * 
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @param string $period_start Period start date (Y-m-d)
 * @param string $period_end Period end date (Y-m-d)
 * @param float $gross_salary Employee's gross salary for the period
 * @return array Array containing total deductions and components
 */
function getLoanAndAdvanceDeductions($db, $employee_id, $period_start, $period_end, $gross_salary = 0) {
    $deductions = [
        'total' => 0,
        'components' => []
    ];

    try {
        // 1. Get active loan deductions where repayment should start in this period
        $loan_query = "
            SELECT 
                l.loan_id,
                l.monthly_repayment as amount,
                'LOAN' as component_code,
                'Loan Repayment' as component_name
            FROM employee_loans l
            JOIN loan_types lt ON l.loan_type_id = lt.loan_type_id
            WHERE l.employee_id = ? 
            AND l.status = 'approved'
            AND l.remaining_balance > 0
            AND (
                (YEAR(l.start_repayment_date) = YEAR(?) AND MONTH(l.start_repayment_date) = MONTH(?))
                OR 
                (l.start_repayment_date <= ? AND (l.end_repayment_date IS NULL OR l.end_repayment_date >= ?))
            )
            AND NOT EXISTS (
                SELECT 1 FROM loan_repayments 
                WHERE loan_id = l.loan_id 
                AND DATE_FORMAT(paid_date, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
                AND status = 'paid'
            )
        ";
        
        $stmt = $db->prepare($loan_query);
        $stmt->execute([
            $employee_id,
            $period_start,
            $period_start,
            $period_end,
            $period_end,
            $period_start
        ]);
        
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Get salary advance deductions that should be paid in this period
        $advance_query = "
            SELECT 
                a.advance_id,
                a.advance_amount as amount,  -- Use the full advance amount
                'ADVANCE' as component_code,
                'Salary Advance' as component_name,
                a.advance_amount as total_advance,
                0 as already_deducted  -- Since we're deducting the full amount, already_deducted is 0
            FROM salary_advances a
            WHERE a.employee_id = :employee_id
            AND a.status = 'approved'
            AND a.deduction_date IS NOT NULL
            AND DATE_FORMAT(a.deduction_date, '%Y-%m') = DATE_FORMAT(:period_date, '%Y-%m')
            AND (a.deduction_payroll_id IS NULL OR a.deduction_payroll_id = 0)
        ";
        
        $stmt = $db->prepare($advance_query);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':period_date' => $period_end
        ]);
        $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process loan deductions
        foreach ($loans as $loan) {
            $deduction = [
                'component_name' => $loan['component_name'],
                'component_code' => $loan['component_code'],
                'amount' => $loan['amount'],
                'reference_id' => $loan['loan_id']
            ];
            $deductions['components'][] = $deduction;
            $deductions['total'] += $loan['amount'];
        }

        // Process advance deductions
        foreach ($advances as $advance) {
            // Ensure amount is not negative and doesn't exceed remaining balance
            $remaining_balance = $advance['total_advance'] - $advance['already_deducted'];
            $amount = min($advance['amount'], $remaining_balance);
            
            if ($amount > 0) {
                $deduction = [
                    'component_name' => $advance['component_name'],
                    'component_code' => $advance['component_code'],
                    'amount' => $amount,
                    'reference_id' => $advance['advance_id'],
                    'total_advance' => $advance['total_advance'],
                    'already_deducted' => $advance['already_deducted']
                ];
                $deductions['components'][] = $deduction;
                $deductions['total'] += $amount;
                
                // Mark this advance to be updated when payroll is processed
                $deductions['advance_updates'][] = [
                    'advance_id' => $advance['advance_id'],
                    'amount' => $amount
                ];
            }
        }

        return $deductions;
        
    } catch (PDOException $e) {
        error_log("Error getting loan/advance deductions: " . $e->getMessage());
        return $deductions; // Return empty deductions if there's an error
    }
}

/**
 * Record loan and advance repayments after payroll is marked as paid
 */
function recordLoanAndAdvanceRepayments($db, $payroll_id, $employee_id, $period_end) {
    try {
        // Get component IDs for loan and advance
        $componentStmt = $db->prepare("SELECT component_id, component_code FROM salary_components WHERE component_code IN ('LOAN', 'ADVANCE')");
        $componentStmt->execute();
        $componentIds = [];
        while ($row = $componentStmt->fetch(PDO::FETCH_ASSOC)) {
            $componentIds[$row['component_code']] = $row['component_id'];
        }

        // Record loan repayments
        if (isset($componentIds['LOAN'])) {
            $loan_query = "
                INSERT INTO loan_repayments (
                    loan_id, payroll_id, amount_paid, 
                    paid_date, status, created_at
                )
                SELECT 
                    pd.reference_id as loan_id,
                    :payroll_id,
                    pd.amount as amount_paid,
                    :paid_date,
                    'paid',
                    NOW()
                FROM payroll_details pd
                JOIN payroll_master pm ON pd.payroll_id = pm.payroll_id
                WHERE pm.employee_id = :employee_id
                AND pm.payroll_id = :payroll_id_2
                AND pd.component_id = :loan_component_id
                AND pd.amount > 0
            ";
            
            $stmt = $db->prepare($loan_query);
            $stmt->execute([
                ':payroll_id' => $payroll_id,
                ':payroll_id_2' => $payroll_id,
                ':employee_id' => $employee_id,
                ':paid_date' => $period_end,
                ':loan_component_id' => $componentIds['LOAN']
            ]);
            
            // Update loan remaining balance and status
            $update_loan_balance = "
                UPDATE employee_loans l
                JOIN payroll_details pd ON l.loan_id = pd.reference_id
                JOIN payroll_master pm ON pd.payroll_id = pm.payroll_id
                SET 
                    l.remaining_balance = GREATEST(0, l.remaining_balance - pd.amount),
                    l.updated_at = NOW(),
                    l.status = CASE 
                        WHEN (l.remaining_balance - pd.amount) <= 0 THEN 'completed'
                        ELSE l.status 
                    END
                WHERE pm.employee_id = :employee_id
                AND pm.payroll_id = :payroll_id
                AND pd.component_id = :loan_component_id
                AND pd.amount > 0
            ";
            
            $stmt = $db->prepare($update_loan_balance);
            $stmt->execute([
                ':employee_id' => $employee_id,
                ':payroll_id' => $payroll_id,
                ':loan_component_id' => $componentIds['LOAN']
            ]);
        }

        // Record advance repayments
        if (isset($componentIds['ADVANCE'])) {
            // Update advance total paid and status directly in salary_advances
            $update_advance_status = "
                UPDATE salary_advances sa
                JOIN payroll_details pd ON sa.advance_id = pd.reference_id
                JOIN payroll_master pm ON pd.payroll_id = pm.payroll_id
                SET 
                    sa.total_paid = COALESCE(sa.total_paid, 0) + pd.amount,
                    sa.status = CASE 
                        WHEN (COALESCE(sa.total_paid, 0) + pd.amount) >= sa.advance_amount THEN 'repaid'
                        ELSE sa.status 
                    END,
                    sa.updated_at = NOW(),
                    sa.last_payment_date = :payment_date
                WHERE pm.employee_id = :employee_id
                AND pm.payroll_id = :payroll_id
                AND pd.component_id = :advance_component_id
                AND pd.amount > 0
            ";
            
            $stmt = $db->prepare($update_advance_status);
            $stmt->execute([
                ':employee_id' => $employee_id,
                ':payroll_id' => $payroll_id,
                ':payment_date' => $period_end,
                ':advance_component_id' => $componentIds['ADVANCE']
            ]);
            
            // Log the advance payment
            $log_advance_payment = "
                INSERT INTO salary_advance_payments (
                    advance_id, employee_id, amount_paid, 
                    payment_date, payment_method, reference,
                    status, created_at
                )
                SELECT 
                    pd.reference_id as advance_id,
                    :employee_id,
                    pd.amount as amount_paid,
                    :payment_date,
                    'salary_deduction',
                    CONCAT('PAYROLL-', :payroll_id_ref),
                    'completed',
                    NOW()
                FROM payroll_details pd
                JOIN payroll_master pm ON pd.payroll_id = pm.payroll_id
                WHERE pm.employee_id = :employee_id_2
                AND pm.payroll_id = :payroll_id_2
                AND pd.component_id = :advance_component_id_2
                AND pd.amount > 0
            ";
            
            try {
                $stmt = $db->prepare($log_advance_payment);
                $stmt->execute([
                    ':employee_id' => $employee_id,
                    ':employee_id_2' => $employee_id,
                    ':payroll_id_2' => $payroll_id,
                    ':payroll_id_ref' => $payroll_id,
                    ':payment_date' => $period_end,
                    ':advance_component_id_2' => $componentIds['ADVANCE']
                ]);
            } catch (PDOException $e) {
                // If salary_advance_payments table doesn't exist, just log the error and continue
                error_log("Notice: Could not log advance payment (table may not exist): " . $e->getMessage());
            }
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error recording loan/advance repayments: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all deductions including loans and advances
 */
function getDeductions($db, $employee_id, $gross_salary, $period_start = null, $period_end = null) {
    // Default to current month if dates not provided
    $period_start = $period_start ?: date('Y-m-01');
    $period_end = $period_end ?: date('Y-m-t');
    
    // First check if employee is monthly paid
    $stmt = $db->prepare("
        SELECT et.type_name 
        FROM employees e 
        JOIN employee_types et ON e.employee_type_id = et.employee_type_id 
        WHERE e.employee_id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee_type = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not monthly paid, return zero deductions
    if (!$employee_type || strtolower($employee_type['type_name']) !== 'monthly paid') {
        return [
            'total' => 0,
            'components' => []
        ];
    }
    
    // Get component IDs from database for proper mapping
    $componentStmt = $db->prepare("SELECT component_id, component_code, component_name FROM salary_components WHERE is_active = 1");
    $componentStmt->execute();
    $components = [];
    while ($row = $componentStmt->fetch(PDO::FETCH_ASSOC)) {
        $components[$row['component_code']] = $row['component_id'];
        $components[$row['component_name']] = $row['component_id'];
    }
    
    // Initialize deductions array
    $deductions = [
        'total' => 0,
        'components' => []
    ];
    
    // For monthly paid employees, calculate standard deductions
    $basic_monthly = $gross_salary * 0.6665;
    $housing = $gross_salary * 0.1875;
    $transport = $gross_salary * 0.08;
    $pension_basis = $basic_monthly + $housing + $transport;
    $pension = round($pension_basis * 0.08, 2);
    
    $paye = calculatePayrollPAYE($gross_salary);
    
    // Get loan and advance deductions
    $loan_advance_deductions = getLoanAndAdvanceDeductions($db, $employee_id, $period_start, $period_end, $gross_salary);
    
    // Add standard deductions first
    if ($pension > 0) {
        $deductions['components'][] = [
            'component_name' => 'Pension Contribution',
            'component_id' => $components['PENS'] ?? $components['Pension Contribution'] ?? 0,
            'amount' => $pension
        ];
        $deductions['total'] += $pension;
    }
    
    if ($paye > 0) {
        $deductions['components'][] = [
            'component_name' => 'PAYE Tax',
            'component_id' => $components['PAYE'] ?? $components['PAYE Tax'] ?? 0,
            'amount' => $paye
        ];
        $deductions['total'] += $paye;
    }
    
    // Add loan and advance deductions with proper component mapping
    if (!empty($loan_advance_deductions['components'])) {
        foreach ($loan_advance_deductions['components'] as $deduction) {
            $component_name = $deduction['component_name'];
            $component_code = $deduction['component_code'];
            
            // Get the correct component_id
            $component_id = $components[$component_name] ?? $components[$component_code] ?? 0;
            
            if ($component_id > 0) {
                $deductions['components'][] = [
                    'component_id' => $component_id,
                    'component_name' => $component_name,
                    'amount' => $deduction['amount'],
                    'reference_id' => $deduction['reference_id'] ?? null
                ];
                $deductions['total'] += $deduction['amount'];
            }
        }
    }
    
    return $deductions;
}

/**
 * Calculate PAYE tax based on Nigerian tax brackets for payroll processing
 */
function calculatePayrollPAYE($monthly_gross) {
    $MONTHS_IN_YEAR = 12;
    $annual_gross = $monthly_gross * $MONTHS_IN_YEAR;
    
    // 1. Calculate pension (8% of Basic + Housing + Transport)
    $basic_monthly = $monthly_gross * 0.6665;
    $housing = $monthly_gross * 0.1875;
    $transport = $monthly_gross * 0.08;
    $pension_basis = $basic_monthly + $housing + $transport;
    $pensionEmployeeMonthly = $pension_basis * 0.08;
    $pension_annual = $pensionEmployeeMonthly * $MONTHS_IN_YEAR;
    
    // 2. NHF is set to 0
    $nhf_annual = 0;
    
    // 3. Calculate Consolidated Relief Allowance (CRA)
    // CRA = Higher of (N200,000 or 1% of Gross) + 20% of Gross
    $one_percent_gross = $annual_gross * 0.01;
    $cra_fixed = max(200000, $one_percent_gross);
    $cra_percentage = $annual_gross * 0.20;
    $cra = $cra_fixed + $cra_percentage;
    
    // 4. Calculate taxable income
    // Taxable Income = Annual Gross - Allowable Deductions (Pension/NHF) - CRA
    $taxable_income = $annual_gross - $pension_annual - $nhf_annual - $cra;
    $taxable_income = max(0, $taxable_income);
    
    // 5. Apply tax brackets
    $tax_brackets = [
        ['limit' => 300000, 'rate' => 0.07],
        ['limit' => 300000, 'rate' => 0.11],
        ['limit' => 500000, 'rate' => 0.15],
        ['limit' => 500000, 'rate' => 0.19],
        ['limit' => 1600000, 'rate' => 0.21],
        ['limit' => PHP_FLOAT_MAX, 'rate' => 0.24]
    ];
    
    $remaining_income = $taxable_income;
    $annual_tax = 0;
    
    foreach ($tax_brackets as $bracket) {
        if ($remaining_income <= 0) break;
        
        $chargeable = min($remaining_income, $bracket['limit']);
        $annual_tax += $chargeable * $bracket['rate'];
        $remaining_income -= $chargeable;
    }
    
    // Round to 2 decimal places for currency
    $monthly_paye = round($annual_tax / $MONTHS_IN_YEAR, 2);
    
    return $monthly_paye;
}

/**
 * Ensure all required salary components exist in the database
 */
function ensureSalaryComponents($db) {
    $components = [
        // Earnings
        ['Basic Salary', 'earning', 'BASIC', true, false],
        
        // Allowances
        ['Housing Allowance', 'allowance', 'HOUSE', true, false],
        ['Transport Allowance', 'allowance', 'TRANS', true, false],
        ['Utility Allowance', 'allowance', 'UTIL', true, false],
        ['Meal Allowance', 'allowance', 'MEAL', true, false],
        
        // Deductions - MAKE SURE THESE EXIST
        ['PAYE Tax', 'deduction', 'PAYE', false, true],
        ['Pension Contribution', 'deduction', 'PENS', false, true],
        ['NHF Contribution', 'deduction', 'NHF', false, true],
        
        // CRITICAL: Ensure Loan and Advance components exist
        ['Loan Repayment', 'deduction', 'LOAN', false, false],
        ['Salary Advance', 'deduction', 'ADVANCE', false, false],

        // Additional earnings
        ['Occasional Taxable Payment', 'earning', 'OTP', true, false],
        ['13th Month Salary', 'earning', '13TH_BONUS', true, false],
    ];
    
    foreach ($components as $component) {
        list($name, $type, $code, $taxable, $statutory) = $component;
        
        // Check if component exists by code
        $stmt = $db->prepare("SELECT component_id FROM salary_components WHERE component_code = ?");
        $stmt->execute([$code]);
        
        if (!$stmt->fetch()) {
            // Component doesn't exist, insert it
            $insert = $db->prepare("
                INSERT INTO salary_components 
                (component_name, component_type, component_code, is_taxable, is_statutory, is_active) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $insert->execute([$name, $type, $code, $taxable ? 1 : 0, $statutory ? 1 : 0]);
            error_log("Inserted new component: $name ($code)");
        }
    }
}

/**
 * Insert payroll details
 */
function insertPayrollDetails($db, $payroll_id, $components, $type) {
    foreach ($components as $c) {
        // Skip if component_id is 0 or not set
        if (empty($c['component_id']) || $c['component_id'] == 0) {
            error_log("Skipping component with invalid ID: " . print_r($c, true));
            continue; 
        }
        
        $reference_id = $c['reference_id'] ?? null;
        $reference_type = $c['reference_type'] ?? null;
        $notes = $c['notes'] ?? null;
        
        try {
            $stmt = $db->prepare("
                INSERT INTO payroll_details 
                (payroll_id, component_id, amount, reference_id, reference_type, notes) 
                VALUES (:payroll_id, :component_id, :amount, :reference_id, :reference_type, :notes)
            ");
            $result = $stmt->execute([
                ':payroll_id' => $payroll_id,
                ':component_id' => $c['component_id'],
                ':amount' => $c['amount'],
                ':reference_id' => $reference_id,
                ':reference_type' => $reference_type,
                ':notes' => $notes
            ]);
            
            if (!$result) {
                error_log("Failed to insert payroll detail: " . print_r($c, true));
            }
            
        } catch (PDOException $e) {
            error_log("Error inserting payroll details: " . $e->getMessage());
            continue; 
        }
    }
}

?>