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
    // Fetch bank schedule data
    $stmt = $db->prepare("SELECT 
        CONCAT(e.first_name, ' ', e.last_name) AS BeneficiaryName,
        b.bank_code AS BankCode,
        e.account_number AS AccountNo,
        pm.net_salary AS Amount,
        DATE_FORMAT(pp.start_date, '%M %Y') AS Month
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

    // Set headers for Excel file download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="bank_schedule_' . date('Ymd') . '.xls"');

    // Output Excel file content
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

} catch (PDOException $e) {
    die('Error generating bank schedule: ' . htmlspecialchars($e->getMessage()));
}
