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

    if ($period_data['payment_date'] < $period_data['end_date']) {
        $errors[] = "Payment date cannot be before end date.";
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
                                       value="<?php echo htmlspecialchars($_POST['period_name'] ?? ''); ?>" 
                                       placeholder="e.g., January 2024 Payroll" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Period Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="period_type" required>
                                    <option value="">Select Type</option>
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
                                       value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="payment_date" 
                                       value="<?php echo htmlspecialchars($_POST['payment_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        The payroll period defines the timeframe for which employees will be paid. 
                        Ensure dates align with your company's payroll schedule.
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
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
    // Set default dates
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    // Format dates as YYYY-MM-DD
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    // Set default values if not already set
    if (!document.querySelector('input[name="start_date"]').value) {
        document.querySelector('input[name="start_date"]').value = formatDate(firstDay);
    }
    if (!document.querySelector('input[name="end_date"]').value) {
        document.querySelector('input[name="end_date"]').value = formatDate(lastDay);
    }
    if (!document.querySelector('input[name="payment_date"]').value) {
        const paymentDate = new Date(lastDay);
        paymentDate.setDate(paymentDate.getDate() + 3); // 3 days after period end
        document.querySelector('input[name="payment_date"]').value = formatDate(paymentDate);
    }
    if (!document.querySelector('input[name="period_name"]').value) {
        const monthNames = ["January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"];
        document.querySelector('input[name="period_name"]').value = 
            monthNames[today.getMonth()] + ' ' + today.getFullYear() + ' Payroll';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>