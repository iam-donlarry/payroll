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
    // Fetch payroll data with component details
    $stmt = $db->prepare("
        SELECT 
            CONCAT(e.first_name, ' ', e.last_name) as name,
            e.tax_id as tax_payer_number,
            'Nigerian' as nationality,
            d.department_name as department,
            1 as no_of_months,
            -- Basic Salary
            COALESCE(SUM(CASE WHEN sc.component_code = 'BASIC' OR sc.component_name LIKE '%Basic%' THEN pd.amount ELSE 0 END), 0) as basic_salary,
            -- Housing
            COALESCE(SUM(CASE WHEN sc.component_code = 'HOUS' OR sc.component_name LIKE '%Housing%' THEN pd.amount ELSE 0 END), 0) as housing,
            -- Transport
            COALESCE(SUM(CASE WHEN sc.component_code = 'TRANS' OR sc.component_name LIKE '%Transport%' THEN pd.amount ELSE 0 END), 0) as transport,
            -- Other components (set to 0 if not in your system)
            0 as furniture,
            0 as education,
            -- Meal (mapped from lunch)
            COALESCE(SUM(CASE WHEN sc.component_code = 'MEAL' OR sc.component_name LIKE '%Meal%' OR sc.component_name LIKE '%Lunch%' THEN pd.amount ELSE 0 END), 0) as lunch,
            0 as passage,
            0 as leave_pay,
            -- Bonus (Occasional payments except 13th month)
            COALESCE(SUM(CASE 
                WHEN (sc.component_code = 'OTP' OR sc.component_name LIKE '%Bonus%' OR sc.component_name LIKE '%Incentive%' OR sc.component_name LIKE '%Commission%')
                AND sc.component_code != '13TH_BONUS' 
                AND sc.component_name NOT LIKE '%13th Month%'
                THEN pd.amount 
                ELSE 0 
            END), 0) as bonus,
            COALESCE(SUM(CASE WHEN sc.component_code = '13TH_BONUS' OR sc.component_name LIKE '%13th Month%' THEN pd.amount ELSE 0 END), 0) as thirteenth_month,
            -- Utility
            COALESCE(SUM(CASE WHEN sc.component_code = 'UTIL' OR sc.component_name LIKE '%Utility%' THEN pd.amount ELSE 0 END), 0) as utility,
            -- Other allowances (sum of all other earning components not already included)
            COALESCE(SUM(CASE 
                WHEN sc.component_type = 'earning' 
                AND sc.component_code NOT IN ('BASIC', 'HOUS', 'TRANS', 'MEAL', 'PENS', 'PAYE')
                AND sc.component_name NOT LIKE '%Basic%'
                AND sc.component_name NOT LIKE '%Housing%'
                AND sc.component_name NOT LIKE '%Transport%'
                AND sc.component_name NOT LIKE '%Meal%'
                AND sc.component_name NOT LIKE '%Lunch%'
                AND sc.component_name NOT LIKE '%Pension%'
                AND sc.component_name NOT LIKE '%PAYE%'
                AND sc.component_name NOT LIKE '%Tax%'
                THEN pd.amount 
                ELSE 0 
            END), 0) as other_allowances,
            0 as nhf,
            0 as nhis,
            -- National Pension Scheme (set to 0 as requested)
            0 as pension,
            0 as life_assurance,
            -- Gross Income (from payroll_master)
            pm.gross_salary as gross_income,
            -- PAYE Tax
            COALESCE(SUM(CASE 
                WHEN sc.component_type = 'deduction' 
                AND (sc.component_code = 'PAYE' OR sc.component_name = 'PAYE Tax') 
                THEN pd.amount 
                ELSE 0 
            END), 0) as tax_payable
        FROM payroll_master pm
        JOIN employees e ON pm.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN payroll_details pd ON pm.payroll_id = pd.payroll_id
        LEFT JOIN salary_components sc ON pd.component_id = sc.component_id
        WHERE pm.period_id = :period_id
        AND pm.payment_status = 'paid'
        GROUP BY pm.payroll_id, e.employee_id, e.first_name, e.last_name, e.tax_id, d.department_name, pm.gross_salary
    ");

    $stmt->execute([':period_id' => $period_id]);
    $payeData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($payeData)) {
        die('No data available for the selected payroll period.');
    }

    // Set headers for CSV file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="paye_schedule_' . date('Ymd') . '.csv"');

    // Output CSV header
    $header = [
        'name', 'tax_payer_number', 'nationality', 'designation', 'no_of_months',
        '1-Basic Salary', '2-Housing', '3-Transport', '4-Furniture', '5-Education',
        '6-Lunch', '7-Passage', '8-Leave', '9-Bonus', '10-13th Month', '11-Utility',
        '12-Other Allowances', '13-NHF', '14-NHIS', '15-National Pension Scheme',
        '16-Life Assurance', 'gross_income', 'tax_payable'
    ];

    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fputs($output, "\xEF\xBB\xBF");
    
    // Write header
    fputcsv($output, $header);

    // Write data rows
    foreach ($payeData as $row) {
        fputcsv($output, [
            $row['name'],
            $row['tax_payer_number'] ?: 'N/A',
            $row['nationality'],
            $row['department'] ?: 'N/A',
            $row['no_of_months'],
            number_format($row['basic_salary'], 2, '.', ''),
            number_format($row['housing'], 2, '.', ''),
            number_format($row['transport'], 2, '.', ''),
            number_format($row['furniture'], 2, '.', ''),
            number_format($row['education'], 2, '.', ''),
            number_format($row['lunch'], 2, '.', ''),
            number_format($row['passage'], 2, '.', ''),
            number_format($row['leave_pay'], 2, '.', ''),
            number_format($row['bonus'], 2, '.', ''),
            number_format($row['thirteenth_month'], 2, '.', ''),
            number_format($row['utility'], 2, '.', ''),
            number_format($row['other_allowances'], 2, '.', ''),
            number_format($row['nhf'], 2, '.', ''),
            number_format($row['nhis'], 2, '.', ''),
            number_format($row['pension'], 2, '.', ''),
            number_format($row['life_assurance'], 2, '.', ''),
            number_format($row['gross_income'], 2, '.', ''),
            number_format($row['tax_payable'], 2, '.', '')
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die('Error generating PAYE schedule: ' . htmlspecialchars($e->getMessage()));
}