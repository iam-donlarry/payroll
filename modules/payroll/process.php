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
    $success = processPayroll($db, $period_id);
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
                        <option value="<?php echo $p['period_id']; ?>" 
                            <?php echo ($period_id == $p['period_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['period_name']); ?> 
                            (<?php echo formatDate($p['start_date']); ?> - <?php echo formatDate($p['end_date']); ?>)
                        </option>
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
 * FIX: Gross Salary is now correctly CALCULATED by summing Basic + Allowances fetched from the database.
 * * @param PDO $db Database connection object.
 * @param int $period_id The ID of the payroll period to process.
 * @return string Success or error message.
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

        // 3. Fetch all active employees
        $stmt = $db->prepare("
            SELECT e.*, d.department_name, et.type_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN employee_types et ON e.employee_type_id = et.employee_type_id
            WHERE e.status = 'active'
        ");
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($employees)) {
            return "No active employees found to process.";
        }

        // 4. Process each employee
        foreach ($employees as $emp) {
            $employee_id = $emp['employee_id'];

            // === CRITICAL FIX: CALCULATE GROSS SALARY FIRST ===
            // This retrieves and sums all active earnings components (Basic + Allowances).
            $salary_data = calculateGrossSalaryFromDB($db, $employee_id);
            
            $basic_salary = $salary_data['basic_salary'];
            $allowances_data = $salary_data['allowances_data'];
            $total_allowances = $allowances_data['total'];
            
            // Gross Salary is the derived total of all fetched earnings
            $gross_salary = $basic_salary + $total_allowances;
            $total_earnings = $gross_salary; 
            
            if ($gross_salary <= 0) {
                 // Skip employees with no defined earnings
                continue; 
            }

            // 5. Calculate deductions based on the CALCULATED Gross Salary
            $deductions = getDeductions($db, $employee_id, $gross_salary);

            // 6. Calculate Net Salary
            $total_deductions = $deductions['total'];
            $net_salary = $total_earnings - $total_deductions;

            // --- Database Insert Operations ---
            
            // Insert master record
            $stmt = $db->prepare("
                INSERT INTO payroll_master 
                (period_id, employee_id, basic_salary, total_earnings, total_deductions, gross_salary, net_salary, payment_status)
                VALUES (:period_id, :employee_id, :basic_salary, :total_earnings, :total_deductions, :gross_salary, :net_salary, 'pending')
            ");
            $stmt->execute([
                ':period_id' => $period_id,
                ':employee_id' => $employee_id,
                ':basic_salary' => $basic_salary, // Calculated from DB fetch
                ':total_earnings' => $total_earnings,
                ':total_deductions' => $total_deductions,
                ':gross_salary' => $gross_salary,
                ':net_salary' => $net_salary
            ]);

            $payroll_id = $db->lastInsertId();

            // Get component IDs from database for details insertion
            $componentStmt = $db->prepare("SELECT component_id, component_name FROM salary_components");
            $componentStmt->execute();
            $components = [];
            while ($row = $componentStmt->fetch(PDO::FETCH_ASSOC)) {
                $components[$row['component_name']] = $row['component_id'];
            }

            // Prepare and insert all earnings (Basic + Allowances fetched from DB)
            $earnings = [];
            
            // Add fetched basic salary as an earning
            $earnings[] = [
                'component_id' => $components['Basic'] ?? 1,
                'component_name' => 'Basic',
                'amount' => $basic_salary
            ];
            
            // Add all fetched allowances as earnings
            if (!empty($allowances_data['components'])) {
                foreach ($allowances_data['components'] as $allowance) {
                    $earnings[] = [
                        'component_id' => $components[$allowance['component_name']] ?? 0,
                        'component_name' => $allowance['component_name'],
                        'amount' => $allowance['amount']
                    ];
                }
            }
            
            insertPayrollDetails($db, $payroll_id, $earnings, 'earning');

            // Insert all deductions with their component IDs
            if (!empty($deductions['components'])) {
                $deduction_components = [];
                foreach ($deductions['components'] as $deduction) {
                    // Map the component name to match the database
                    $component_name = $deduction['component_name'];
                    if ($component_name === 'PAYE') {
                        $component_name = 'PAYE Tax'; // Match the name in salary_components
                    }
                    
                    $deduction_components[] = [
                        'component_id' => $components[$component_name] ?? 0,
                        'component_name' => $component_name,
                        'amount' => $deduction['amount']
                    ];
                }
                if (!empty($deduction_components)) {
                    insertPayrollDetails($db, $payroll_id, $deduction_components, 'deduction');
                }
            }

            // Update payroll period status
            $stmt = $db->prepare("UPDATE payroll_periods SET status = 'processing' WHERE period_id = ?");
            $stmt->execute([$period_id]);
        }

        return "✅ Payroll processed successfully for " . count($employees) . " employees.";

    } catch (PDOException $e) {
        return "❌ Error processing payroll: " . $e->getMessage();
    }
}
// ------------------------------
// Helper Functions (Modified)
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
        if ($row['component_id'] == 1 || $row['component_name'] == 'Basic') {
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
 * Calculates deductions based on the total Gross Salary. 
 * NOTE: This function must derive Pension basis (B+H+T) using the hardcoded percentages 
 * for compliance, even if B+H+T are stored separately in the DB.
 */
function getDeductions($db, $employee_id, $gross_salary) {
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
    
    // For monthly paid employees, calculate deductions
    // 1. Calculate pension (8% of Basic + Housing + Transport, derived from Gross percentages)
    $basic_monthly = $gross_salary * 0.6665;
    $housing = $gross_salary * 0.1875;
    $transport = $gross_salary * 0.08;
    $pension_basis = $basic_monthly + $housing + $transport;
    $pension = round($pension_basis * 0.08, 2);
    
    // 2. NHF is set to 0
    $nhf = 0;
    
    // 3. Calculate PAYE using the gross salary
    $paye = calculatePayrollPAYE($gross_salary);
    
    // Prepare components array
    $components = [];
    
    if ($pension > 0) {
        $components[] = [
            'component_name' => 'Pension Contribution',
            'amount' => $pension
        ];
    }
    
    if ($paye > 0) {
        $components[] = [
            'component_name' => 'PAYE',
            'amount' => $paye
        ];
    }
    
    $total_deductions = $pension + $nhf + $paye;
    
    return [
        'total' => $total_deductions,
        'components' => $components
    ];
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
        [1, 'Basic', 'Basic Salary', 'earning', 1],
        
        // Allowances
        [2, 'Housing', 'Housing Allowance', 'allowance', 1],
        [3, 'Transport', 'Transport Allowance', 'allowance', 1],
        [11, 'Utility', 'Utility Allowance', 'allowance', 1],
        [12, 'Meal', 'Meal Allowance', 'allowance', 1],
        
        // Deductions
        [6, 'PAYE Tax', 'PAYE Tax', 'deduction', 0],  // Changed from 'PAYE' to 'PAYE Tax'
        [7, 'PENS', 'Pension Contribution', 'deduction', 0],
        [8, 'NHF', 'National Housing Fund', 'deduction', 0],
        [9, 'Loan', 'Staff Loan', 'deduction', 0],
        [10, 'Other', 'Other Deductions', 'deduction', 0],
    ];
    
    foreach ($components as $component) {
        list($id, $code, $name, $type, $taxable) = $component;
        
        // Check if component exists
        $stmt = $db->prepare("SELECT component_id FROM salary_components WHERE component_id = ?");
        $stmt->execute([$id]);
        
        if (!$stmt->fetch()) {
            // Component doesn't exist, insert it
            $insert = $db->prepare("
                INSERT IGNORE INTO salary_components 
                (component_id, component_code, component_name, display_name, component_type, is_taxable) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([$id, $code, $name, $name, $type, $taxable]);
        }
    }
}

function insertPayrollDetails($db, $payroll_id, $components, $type) {
    foreach ($components as $c) {
        // Skip if component_id is 0 or not set
        if (empty($c['component_id'])) {
            error_log("Skipping component with missing ID: " . print_r($c, true));
            continue;
        }
        
        try {
            // Directly insert the component with the provided type
            $stmt = $db->prepare("
                INSERT INTO payroll_details 
                (payroll_id, component_id, amount, component_type)
                VALUES (:payroll_id, :component_id, :amount, :component_type)
            ");
            
            $stmt->execute([
                ':payroll_id' => $payroll_id,
                ':component_id' => $c['component_id'],
                ':amount' => $c['amount'],
                ':component_type' => $type
            ]);
            
        } catch (PDOException $e) {
            error_log("Error inserting payroll details: " . $e->getMessage());
            // Continue with next component even if one fails
            continue;
        }
    }
}

?>