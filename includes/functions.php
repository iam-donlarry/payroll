<?php
// Ensure this is only defined once
function includeFile($path) {
    include BASE_PATH . '/' . $path;
}

function base_url($path = '') {
    // Adjust to your localhost or live server root
    return '/PAY/' . ltrim($path, '/');
}
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function formatCurrency($amount, $currency = 'NGN') {
    $symbols = [
        'NGN' => '₦',
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€'
    ];
    
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format($amount, 2);
}

function formatDate($date, $format = 'F j, Y') {
    if (empty($date) || $date == '0000-00-00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

function calculateAge($birth_date) {
    if (empty($birth_date)) return null;
    
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

function calculateTenure($employment_date) {
    if (empty($employment_date)) return null;
    
    $employment = new DateTime($employment_date);
    $today = new DateTime();
    $tenure = $today->diff($employment);
    
    $years = $tenure->y;
    $months = $tenure->m;
    
    if ($years == 0) {
        return $months . ' month' . ($months != 1 ? 's' : '');
    } else {
        return $years . ' year' . ($years != 1 ? 's' : '') . 
               ($months > 0 ? ' ' . $months . ' month' . ($months != 1 ? 's' : '') : '');
    }
}

function generateEmployeeCode($department_code, $employee_count) {
    $prefix = strtoupper($department_code);
    $number = str_pad($employee_count + 1, 4, '0', STR_PAD_LEFT);
    $year = date('y');
    return $prefix . $year . $number;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    // Nigerian phone number validation
    $pattern = '/^(?:\+234|0)[789][01]\d{8}$/';
    return preg_match($pattern, $phone);
}

function validateBVN($bvn) {
    return preg_match('/^\d{11}$/', $bvn);
}


function getNigerianStates() {
    return [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
        'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'Gombe', 'Imo',
        'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
        'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers',
        'Sokoto', 'Taraba', 'Yobe', 'Zamfara', 'FCT'
    ];
}

function getBankList($db) {
    try {
        $stmt = $db->prepare("SELECT bank_name FROM banks ORDER BY bank_name ASC");
        // 2. Execute the query
        $stmt->execute();  
        // 3. Fetch all results as an associative array
        $banks = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); 

        return $banks;
        
    } catch (PDOException $e) {
        error_log("Database error fetching bank list: " . $e->getMessage());
        return [];
    }
}

function logAction($action, $details = null) {
    // This would typically log to a file or database
    error_log("[" . date('Y-m-d H:i:s') . "] " . $action . ($details ? ": " . json_encode($details) : ""));
}

function sendEmail($to, $subject, $message, $headers = null) {
    // Basic email sending function
    $default_headers = "From: payroll@company.com\r\n";
    $default_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $headers = $headers ?: $default_headers;
    
    return mail($to, $subject, $message, $headers);
}

function generatePayslipPDF($payroll_data) {
    // This would generate a PDF payslip
    // For now, return a placeholder
    return "PDF Generation would happen here";
}

function getStatusBadge($status) {
    $statuses = [
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'completed' => 'info',
        'active' => 'primary',
        'disbursed' => 'info',
        'defaulted' => 'danger',
        'paid' => 'success',
        'overdue' => 'warning',
        'partial' => 'warning'
    ];
    
    $color = $statuses[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

function numberToWords($number) {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' point ';
    $dictionary  = array(
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'fourty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        1000000             => 'million',
        1000000000          => 'billion',
        1000000000000       => 'trillion',
        1000000000000000    => 'quadrillion',
        1000000000000000000 => 'quintillion'
    );

    if (!is_numeric($number)) {
        return false;
    }

    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        // overflow
        trigger_error(
            'numberToWords only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
            E_USER_WARNING
        );
        return false;
    }

    if ($number < 0) {
        return $negative . numberToWords(abs($number));
    }

    $string = $fraction = null;

    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . numberToWords($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= numberToWords($remainder);
            }
            break;
    }

    if (null !== $fraction && is_numeric($fraction)) {
        $string .= $decimal;
        $words = array();
        foreach (str_split((string) $fraction) as $number) {
            $words[] = $dictionary[$number];
        }
        $string .= implode(' ', $words);
    }

    return ucfirst($string);
}

//helper functions for loan

// [REPLACE] calculateLoanRepayment
function calculateLoanRepayment($amount, $tenure_months) {
    $monthly_repayment = $amount / $tenure_months;
    $total_repayable = $amount;
    $total_interest = 0;
    return [
        'monthly_repayment' => round($monthly_repayment, 2),
        'total_repayable' => round($total_repayable, 2),
        'total_interest' => 0
    ];
}

// [REPLACE] createRepaymentSchedule
function createRepaymentSchedule($db, $loan_id, $calculation, $tenure_months) {
    $monthly_repayment = $calculation['monthly_repayment'];
    for ($i = 1; $i <= $tenure_months; $i++) {
        $due_date = date('Y-m-d', strtotime("+$i months"));
        $stmt = $db->prepare("INSERT INTO loan_repayments 
                             (loan_id, installment_number, due_date, amount_due, 
                              principal_amount, interest_amount, status) 
                             VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([
            $loan_id,
            $i,
            $due_date,
            $monthly_repayment,
            round($monthly_repayment, 2), // all principal
            0 // interest is always 0
        ]);
    }
}

function getLoanDetails($db, $loan_id) {
    try {
        $stmt = $db->prepare("SELECT el.*, e.first_name, e.last_name, e.employee_code, 
                                     lt.loan_name, lt.interest_rate,
                                     CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                              FROM employee_loans el
                              JOIN employees e ON el.employee_id = e.employee_id
                              JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                              LEFT JOIN employees approver ON el.approved_by = approver.employee_id
                              WHERE el.loan_id = ?");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Loan not found']);
            return;
        }
        
        // Get repayment schedule
        $stmt = $db->prepare("SELECT * FROM loan_repayments WHERE loan_id = ? ORDER BY installment_number");
        $stmt->execute([$loan_id]);
        $repayment_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $loan['repayment_schedule'] = $repayment_schedule;
        
        echo json_encode(['success' => true, 'data' => $loan]);
        
    } catch (PDOException $e) {
        error_log("Get Loan Details Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
