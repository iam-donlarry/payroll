<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('payroll_master');

$page_title = "Create Payroll Period";
$body_class = "payroll-page";

$message = '';

// Handle form submission
if ($_POST) {
    $period_data = [
        'company_id' => 1, // Default company
        'period_name' => sanitizeInput($_POST['period_name']),
        'start_date' => sanitizeInput($_POST['start_date']),
        'end_date' => sanitizeInput($_POST['end_date']),
        'payment_date' => sanitizeInput($_POST['payment_date']),
        'period_type' => sanitizeInput($_POST['period_type']),
        'created_by' => $_SESSION['employee_id']
    ];

    $errors = [];

    // Validation
    if (empty($period_data['period_name'])) $errors[] = "Period name is required.";
    if (empty($period_data['start_date'])) $errors[] = "Start date is required.";
    if (empty($period_data['end_date'])) $errors[] = "End date is required.";
    if (empty($period_data['payment_date'])) $errors[] = "Payment date is required.";
    if (empty($period_data['period_type'])) $errors[] = "Period type is required.";

    if ($period_data['start_date'] > $period_data['end_date']) {
        $errors[] = "Start date cannot be after end date.";
    }

   $day = date('d', strtotime($period_data['payment_date']));
    $payMonth = date('m', strtotime($period_data['payment_date']));

    if ($payMonth !== '12') { 
        // Normal months rule
        if ($day < 23 || $day > 27) {
            $errors[] = "Payment date must be between the 23rd and 27th of the month.";
        }
    }



    if (empty($errors)) {
        try {
            $query = "INSERT INTO payroll_periods SET 
                     company_id = :company_id,
                     period_name = :period_name,
                     start_date = :start_date,
                     end_date = :end_date,
                     payment_date = :payment_date,
                     period_type = :period_type,
                     created_by = :created_by,
                     status = 'draft'";
            
            $stmt = $db->prepare($query);
            $stmt->execute($period_data);
            
            $period_id = $db->lastInsertId();
            
            $message = '<div class="alert alert-success">Payroll period created successfully! 
                       <a href="process.php?period_id=' . $period_id . '" class="alert-link">Process Payroll Now</a></div>';

            // Clear form
            $_POST = [];

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error creating payroll period: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $error) {
            $message .= '<li>' . $error . '</li>';
        }
        $message .= '</ul></div>';
    }
}

// --- NEW LOGIC: Determine Default Dates/Name based on most recent locked period ---
$default_start_date = null;
$default_end_date = null;
$default_payment_date = null;
$default_period_name = null;

/**
 * Returns the correct payroll payment date for a given DateTime that is inside the target month.
 * Rules:
 *  - Normal months: 27th of that month, move back to Friday if 27th is weekend.
 *  - December: 20th of December, move back to Friday if weekend.
 */
function getPayrollPaymentDateForMonth(DateTime $anyDateInMonth): DateTime {
    $year = $anyDateInMonth->format('Y');
    $month = $anyDateInMonth->format('m');

    if ($month === '12') {
        $payment = new DateTime("$year-12-20");
        $dow = (int)$payment->format('N'); // 6=Sat,7=Sun
        if ($dow === 6) $payment->modify('-1 day');   // Sat -> Fri 19
        elseif ($dow === 7) $payment->modify('-2 days'); // Sun -> Fri 18
    } else {
        $payment = new DateTime("$year-$month-27");
        $dow = (int)$payment->format('N');
        if ($dow === 6) $payment->modify('-1 day');   // Sat -> Fri 26
        elseif ($dow === 7) $payment->modify('-2 days'); // Sun -> Fri 25
    }

    return $payment;
}

try {
    // Fetch the most recent period (based on start_date, assuming newer periods have later start dates)
    $stmt = $db->prepare("SELECT start_date, end_date, status FROM payroll_periods ORDER BY start_date DESC LIMIT 1");
    $stmt->execute();
    $most_recent_period = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($most_recent_period && $most_recent_period['status'] === 'locked') {
        // If the most recent period is locked, calculate next month's dates
        $locked_end_date = new DateTime($most_recent_period['end_date']);

        // Calculate next month's start (first day)
        $next_start = clone $locked_end_date;
        $next_start->modify('first day of next month');
        $default_start_date = $next_start->format('Y-m-d');

        // Calculate next month's end (last day)
        $next_end = clone $next_start;
        $next_end->modify('last day of this month');
        $default_end_date = $next_end->format('Y-m-d');

        // Use centralized payment date logic for next month
        $payment_date = getPayrollPaymentDateForMonth($next_end);
        $default_payment_date = $payment_date->format('Y-m-d');

        // Calculate next month's name
        $default_period_name = $next_start->format('F Y') . ' Payroll';

    } else {
        // If no locked period exists or the most recent one isn't locked, fallback to current month defaults
        $today = new DateTime();

        // Use current month's first & last day as the period
        $default_start_date = $today->format('Y-m-01'); // First day of current month
        $default_end_date = $today->format('Y-m-t');   // Last day of current month

        // Use the same centralized payment logic (27th or December rule) for the current month
        $payment_date = getPayrollPaymentDateForMonth($today);
        $default_payment_date = $payment_date->format('Y-m-d');

        $default_period_name = $today->format('F Y') . ' Payroll';
    }
} catch (PDOException $e) {
    // Fallback to current month defaults on error, but still use correct payment logic
    $today = new DateTime();
    $default_start_date = $today->format('Y-m-01');
    $default_end_date = $today->format('Y-m-t');
    $payment_date = getPayrollPaymentDateForMonth($today);
    $default_payment_date = $payment_date->format('Y-m-d');
    $default_period_name = $today->format('F Y') . ' Payroll';
    error_log("Error fetching most recent payroll period for defaults: " . $e->getMessage());
}


include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Create Payroll Period</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Payroll
    </a>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Period Information</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="periodForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Period Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="period_name" 
                                       value="<?php echo htmlspecialchars($_POST['period_name'] ?? $default_period_name); ?>" 
                                       placeholder="e.g., January 2024 Payroll" required readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Period Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="period_type" required readonly>
                                    <option value="monthly" <?php echo ($_POST['period_type'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="weekly" <?php echo ($_POST['period_type'] ?? '') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="daily" <?php echo ($_POST['period_type'] ?? '') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo htmlspecialchars($_POST['start_date'] ?? $default_start_date); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo htmlspecialchars($_POST['end_date'] ?? $default_end_date); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="payment_date" 
                                       value="<?php echo htmlspecialchars($_POST['payment_date'] ?? $default_payment_date); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        The payroll period defines the timeframe for which employees will be paid. 
                        Ensure dates align with your company's payroll schedule.
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn">
                            <i class="fas fa-save me-2"></i>Create Period
                        </button>
                        <button type="reset" class="btn btn-secondary">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Quick Help -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Payroll Period Guide</h6>
            </div>
            <div class="card-body">
                <h6>Period Types:</h6>
                <ul class="list-unstyled">
                    <li><strong>Monthly:</strong> Covers full calendar month</li>
                    <li><strong>Weekly:</strong> 7-day periods</li>
                    <li><strong>Daily:</strong> Single day periods</li>
                </ul>

                <hr>

                <h6>Best Practices:</h6>
                <ul>
                    <li>Use descriptive period names</li>
                    <li>Ensure payment date is after period end</li>
                    <li>Maintain consistent period lengths</li>
                    <li>Plan for public holidays</li>
                </ul>

                <hr>

                <h6>Nigeria Specific:</h6>
                <ul>
                    <li>Consider month-end closing dates</li>
                    <li>Account for bank processing times</li>
                    <li>Include statutory deduction deadlines</li>
                </ul>
            </div>
        </div>

        <!-- Recent Periods -->
        <div class="card shadow mt-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Periods</h6>
            </div>
            <div class="card-body">
                <?php
                try {
                    $recent_query = "SELECT period_name, start_date, end_date, status 
                                    FROM payroll_periods 
                                    ORDER BY created_at DESC 
                                    LIMIT 5";
                    $recent_stmt = $db->prepare($recent_query);
                    $recent_stmt->execute();
                    $recent_periods = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($recent_periods as $recent):
                ?>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <div>
                        <strong><?php echo htmlspecialchars($recent['period_name']); ?></strong><br>
                        <small class="text-muted">
                            <?php echo formatDate($recent['start_date'], 'M j'); ?> - 
                            <?php echo formatDate($recent['end_date'], 'M j, Y'); ?>
                        </small>
                    </div>
                    <span class="badge bg-<?php 
                        switch($recent['status']) {
                            case 'draft': echo 'secondary'; break;
                            case 'processing': echo 'warning'; break;
                            case 'approved': echo 'success'; break;
                            case 'locked': echo 'dark'; break; // Added a style for locked
                            default: echo 'secondary';
                        }
                    ?>">
                        <?php echo ucfirst($recent['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php } catch (PDOException $e) { ?>
                <p class="text-muted">Unable to load recent periods.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // The JS logic for setting defaults is now handled by PHP, 
    // so the original JS that set current month defaults is no longer needed.
    // The form fields already have the correct values from PHP.
});
</script>

<?php include '../../includes/footer.php'; ?>