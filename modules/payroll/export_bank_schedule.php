<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('payroll_master');

// Get payroll period ID from URL
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;

if (!$period_id) {
    die('Invalid payroll period ID.');
}

try {
    // Get the pension component ID
    $pensionStmt = $db->prepare("SELECT component_id FROM salary_components WHERE component_code = 'PENS' OR component_name LIKE '%Pension%' LIMIT 1");
    $pensionStmt->execute();
    $pensionComponent = $pensionStmt->fetch(PDO::FETCH_ASSOC);
    $pensionComponentId = $pensionComponent['component_id'] ?? 0;

    // Fetch bank schedule data
    $stmt = $db->prepare("SELECT 
        CONCAT(e.first_name, ' ', e.last_name) AS BeneficiaryName,
        b.bank_code AS BankCode,
        e.account_number AS AccountNo,
        pm.net_salary AS Amount,
        DATE_FORMAT(pp.start_date, '%M %Y') AS Month,
        e.pension_pin,
        e.employee_id,
        pm.payroll_id
    FROM payroll_master pm
    JOIN employees e ON pm.employee_id = e.employee_id
    LEFT JOIN banks b ON e.bank_id = b.id
    LEFT JOIN payroll_periods pp ON pm.period_id = pp.period_id
    WHERE pm.period_id = :period_id AND pm.payment_status = 'paid'");

    $stmt->execute([':period_id' => $period_id]);
    $bankSchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bankSchedule)) {
        die('No data available for the selected payroll period.');
    }

    // Fetch pension details for each employee
    $pensionSchedule = [];
    
    if ($pensionComponentId) {
        foreach ($bankSchedule as $employee) {
            // Get employee's pension deduction from payroll details
            $pensionStmt = $db->prepare("
                SELECT pd.amount 
                FROM payroll_details pd
                WHERE pd.payroll_id = :payroll_id 
                AND pd.component_id = :component_id
            ");
            $pensionStmt->execute([
                ':payroll_id' => $employee['payroll_id'],
                ':component_id' => $pensionComponentId
            ]);
            
            $pensionDeduction = $pensionStmt->fetch(PDO::FETCH_ASSOC);
            $employeePension = $pensionDeduction ? floatval($pensionDeduction['amount']) : 0;
            
            // Calculate employer contribution (10% of pensionable salary)
            // Pensionable salary = employee pension / 0.08 (since employee contributes 8%)
            $pensionableSalary = $employeePension > 0 ? ($employeePension / 0.08) : 0;
            $employerContribution = $pensionableSalary * 0.10;
            
            $pensionSchedule[] = [
                'Name' => $employee['BeneficiaryName'],
                'RSA_ID' => $employee['pension_pin'] ?: 'N/A',
                'PFA_Code' => '031',
                'Employee_Amount' => number_format($employeePension, 2),
                'Employer_Amount' => number_format($employerContribution, 2),
                'Total' => number_format($employeePension + $employerContribution, 2),
                'Month' => $employee['Month']
            ];
        }
    }

    // Check which export is requested (default to bank schedule if not specified)
    $export_type = $_GET['type'] ?? 'bank';

    if ($export_type === 'pension') {
        if (empty($pensionSchedule)) {
            die('No pension data available for the selected payroll period.');
        }

        // Set headers for Excel file download
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="pension_schedule_' . date('Ymd') . '.xls"');

        // Output Excel file content for pension schedule
        echo "Name\tRSA ID\tPFA Code\tEmployee Amount (8%)\tEmployer Amount (10%)\tTotal\tMonth\n";

        foreach ($pensionSchedule as $row) {
            echo implode("\t", [
                $row['Name'],
                $row['RSA_ID'],
                $row['PFA_Code'],
                $row['Employee_Amount'],
                $row['Employer_Amount'],
                $row['Total'],
                $row['Month']
            ]) . "\n";
        }
    } else {
        // Original bank schedule export
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="bank_schedule_' . date('Ymd') . '.xls"');

        // Output Excel file content for bank schedule
        echo "BeneficiaryName\tBankCode\tAccountNo\tAmount\tMonth\n";

        foreach ($bankSchedule as $row) {
            echo implode("\t", [
                $row['BeneficiaryName'],
                $row['BankCode'],
                $row['AccountNo'],
                number_format($row['Amount'], 2),
                $row['Month']
            ]) . "\n";
        }
    }

} catch (PDOException $e) {
    die('Error generating export: ' . htmlspecialchars($e->getMessage()));
}
