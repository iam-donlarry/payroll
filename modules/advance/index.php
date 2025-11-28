<?php
// Make sure this is the very first line with no whitespace before it
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Start output buffering to prevent headers already sent error
ob_start();

// Initialize database connection
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAuth();

$page_title = "Salary Advance Management";
$body_class = "advance";

// Check if user has permission to view advances
if (!in_array($_SESSION['user_type'], ['admin', 'hr_manager', 'accountant'])) {
    $_SESSION['error'] = "You don't have permission to access this module.";
    // Use JavaScript redirect as fallback
    echo '<script>window.location.href = "' . BASE_URL . '/dashboard";</script>';
    exit();
}

// Handle advance actions
if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
    $advance_id = intval($_GET['id']);
    $stmt = $db->prepare("UPDATE salary_advances SET status = 'approved', approved_by = ?, approval_date = NOW() 
                          WHERE advance_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['employee_id'], $advance_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Advance approved successfully.";
    } else {
        $_SESSION['error'] = "Failed to approve advance or already processed.";
    }
    
    // Flush output buffer and redirect
    ob_end_clean();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['id'])) {
    $advance_id = intval($_GET['id']);
    $stmt = $db->prepare("UPDATE salary_advances SET status = 'rejected', approved_by = ?, approval_date = NOW() 
                          WHERE advance_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['employee_id'], $advance_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Advance rejected successfully.";
    } else {
        $_SESSION['error'] = "Failed to reject advance or already processed.";
    }
    
    // Flush output buffer and redirect
    ob_end_clean();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Include header after all potential redirects
include '../../includes/header.php';


// Get all advances for the current user or all if admin/hr
if (in_array($_SESSION['user_type'], ['admin', 'hr_manager', 'accountant'])) {
    $stmt = $db->prepare("SELECT sa.*, e.first_name, e.last_name, e.employee_code 
                          FROM salary_advances sa 
                          JOIN employees e ON sa.employee_id = e.employee_id 
                          ORDER BY sa.request_date DESC");
    $stmt->execute();
} else {
    $stmt = $db->prepare("SELECT sa.*, e.first_name, e.last_name, e.employee_code 
                          FROM salary_advances sa 
                          JOIN employees e ON sa.employee_id = e.employee_id 
                          WHERE sa.employee_id = ? 
                          ORDER BY sa.request_date DESC");
    $stmt->execute([$_SESSION['employee_id']]);
}

$advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestAdvanceModal">
            <i class="fas fa-plus me-2"></i>Request Advance
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="advancesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Employee ID</th>
                            <th>Amount</th>
                            <th>Request Date</th>
                            <th>Deduction Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($advances as $advance): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($advance['first_name'] . ' ' . $advance['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($advance['employee_code']); ?></td>
                            <td><?php echo number_format($advance['advance_amount'], 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($advance['request_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($advance['deduction_date'])); ?></td>
                            <td>
                                <?php 
                                $status_class = '';
                                switch($advance['status']) {
                                    case 'pending':
                                        $status_class = 'bg-warning';
                                        break;
                                    case 'approved':
                                        $status_class = 'bg-success';
                                        break;
                                    case 'rejected':
                                        $status_class = 'bg-danger';
                                        break;
                                    case 'deducted':
                                        $status_class = 'bg-info';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($advance['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info view-advance" 
                                        data-id="<?php echo $advance['advance_id']; ?>"
                                        data-bs-toggle="tooltip" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if (in_array($_SESSION['user_type'], ['admin', 'hr_manager', 'accountant']) && $advance['status'] === 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $advance['advance_id']; ?>" 
                                       class="btn btn-sm btn-success"
                                       onclick="return confirm('Are you sure you want to approve this advance?')"
                                       data-bs-toggle="tooltip" 
                                       title="Approve">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="?action=reject&id=<?php echo $advance['advance_id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to reject this advance?')"
                                       data-bs-toggle="tooltip" 
                                       title="Reject">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Request Advance Modal -->
<div class="modal fade" id="requestAdvanceModal" tabindex="-1" aria-labelledby="requestAdvanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestAdvanceModalLabel">Request Salary Advance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="requestAdvanceForm" method="POST" action="process.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="request_advance">
                    
                    <?php if (in_array($_SESSION['user_type'], ['admin', 'hr_manager', 'accountant'])): ?>
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" id="employee_id" name="employee_id" required>
                            <option value="">-- Select Employee --</option>
                            <?php
                            $empStmt = $db->query("SELECT employee_id, first_name, last_name, employee_code FROM employees ORDER BY first_name, last_name");
                            while ($emp = $empStmt->fetch(PDO::FETCH_ASSOC)) {
                                echo sprintf(
                                    '<option value="%d">%s %s (%s)</option>',
                                    $emp['employee_id'],
                                    htmlspecialchars($emp['first_name']),
                                    htmlspecialchars($emp['last_name']),
                                    htmlspecialchars($emp['employee_code'])
                                );
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="advance_amount" class="form-label">Amount (NGN)</label>
                        <input type="number" class="form-control" id="advance_amount" name="advance_amount" 
                             min="0" step="0.01" required>
                        <div class="form-text">
                            <div>Minimum amount: ₦1,000.00</div>
                            <div id="max_advance_info" class="text-muted">Please select an employee first</div>
                        </div>
                    </div>
                    
                    <!-- Borrowing Limit Information -->
                    <div class="alert alert-info d-none" id="advanceBorrowingLimitInfo">
                        <h6 class="alert-heading mb-2"><i class="fas fa-info-circle me-2"></i>Borrowing Limit</h6>
                        <div class="row small">
                            <div class="col-md-6">
                                <div class="mb-1"><strong>Gross Salary:</strong> <span id="advanceDisplayGrossSalary">-</span></div>
                                <div class="mb-1"><strong>Max Limit (33%):</strong> <span id="advanceDisplayMaxLimit">-</span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-1"><strong>Monthly Loan Repayment:</strong> <span id="advanceDisplayOutstanding">-</span></div>
                                <div class="mb-1"><strong class="text-success">Available Amount:</strong> <span id="advanceDisplayAvailable" class="text-success fw-bold">-</span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Advance</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Advance Details Modal -->
<div class="modal fade" id="viewAdvanceModal" tabindex="-1" aria-labelledby="viewAdvanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAdvanceModalLabel">Advance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="advanceDetails">
                <!-- Details will be loaded via AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once('../../includes/footer.php'); ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#advancesTable').DataTable({
        order: [[3, 'desc']], // Sort by request date by default
        responsive: true,
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle view advance details
    $('.view-advance').on('click', function() {
        var advanceId = $(this).data('id');
        $('#viewAdvanceModal').modal('show');
        
        // Load advance details via AJAX
        $.ajax({
            url: 'get_advance_details.php',
            type: 'GET',
            data: { id: advanceId },
            success: function(response) {
                $('#advanceDetails').html(response);
            },
            error: function() {
                $('#advanceDetails').html('<div class="alert alert-danger">Error loading advance details.</div>');
            }
        });
    });
    
    // Set default deduction date to next payday or end of month
    var today = new Date();
    var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    var nextPayday = new Date(today);
    
    // If today is after the 25th, set payday to last day of month
    // Otherwise set to 25th of current month or next business day if 25th is weekend
    if (today.getDate() > 25) {
        nextPayday = lastDay;
    } else {
        nextPayday.setDate(25);
        // Adjust if 25th is weekend
        if (nextPayday.getDay() === 0) { // Sunday
            nextPayday.setDate(23); // Previous Friday
        } else if (nextPayday.getDay() === 6) { // Saturday
            nextPayday.setDate(24); // Previous Friday
        }
    }
    
    // Format as YYYY-MM-DD
    var formattedDate = nextPayday.toISOString().split('T')[0];
    $('#deduction_date').val(formattedDate);
    
    // Set max date to last day of current month
    $('#deduction_date').attr('max', lastDay.toISOString().split('T')[0]);
    
    // Function to get max advance amount for an employee
    function getMaxAdvance(employeeId, callback) {
        if (!employeeId) {
            callback(0);
            return;
        }
        
        $.ajax({
            url: 'get_employee_salary.php',
            type: 'GET',
            data: { employee_id: employeeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var maxAdvance = response.net_salary * 0.33; // 1/3 of net salary
                    callback(maxAdvance);
                } else {
                    console.error('Error getting employee salary:', response.message);
                    callback(0);
                }
            },
            error: function() {
                console.error('AJAX error getting employee salary');
                callback(0);
            }
        });
    }
    
    // Update max advance when employee changes - fetch borrowing limit
    $('#employee_id').on('change', function() {
        var employeeId = $(this).val();
        if (!employeeId) {
            $('#advance_amount').attr('max', '').removeAttr('max');
            $('#max_advance_info').text('Please select an employee first');
            $('#advanceBorrowingLimitInfo').addClass('d-none');
            return;
        }
        
        // Fetch borrowing limit from API
        $.ajax({
            url: '../../api/loans/limit.php',
            type: 'GET',
            data: { employee_id: employeeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    $('#advanceDisplayGrossSalary').text('₦' + parseFloat(data.gross_salary).toLocaleString('en-NG', {minimumFractionDigits: 2}));
                    $('#advanceDisplayMaxLimit').text('₦' + parseFloat(data.max_limit).toLocaleString('en-NG', {minimumFractionDigits: 2}));
                    $('#advanceDisplayOutstanding').text('₦' + parseFloat(data.current_outstanding).toLocaleString('en-NG', {minimumFractionDigits: 2}));
                    $('#advanceDisplayAvailable').text('₦' + parseFloat(data.available_amount).toLocaleString('en-NG', {minimumFractionDigits: 2}));
                    $('#advanceBorrowingLimitInfo').removeClass('d-none');
                    
                    // Set max advance amount to available amount
                    $('#advance_amount').attr('max', data.available_amount);
                    $('#max_advance_info').text('Maximum allowed: ₦' + parseFloat(data.available_amount).toLocaleString('en-NG', {minimumFractionDigits: 2}));
                } else {
                    console.error('Error fetching limit:', response.message);
                    $('#advanceBorrowingLimitInfo').addClass('d-none');
                }
            },
            error: function() {
                console.error('AJAX error fetching borrowing limit');
                $('#advanceBorrowingLimitInfo').addClass('d-none');
            }
        });
    });
    
    // Form validation
    $('#requestAdvanceForm').on('submit', function(e) {
        var amount = parseFloat($('#advance_amount').val());
        var employeeId = $('#employee_id').val() || '<?php echo $_SESSION['employee_id']; ?>';
        
        if (!employeeId) {
            e.preventDefault();
            alert('Please select an employee first');
            return false;
        }
        
        // Prevent form submission until we get the max advance
        e.preventDefault();
        
        getMaxAdvance(employeeId, function(maxAdvance) {
            if (isNaN(amount) || amount <= 0) {
                alert('Please enter a valid amount');
                return false;
            }
            
            if (amount > maxAdvance) {
                alert('Advance amount cannot exceed 40% of the employee\'s net salary: ₦' + 
                     maxAdvance.toLocaleString('en-NG', {minimumFractionDigits: 2}));
                return false;
            }
            
            // If validation passes, submit the form
            this.submit();
        }.bind(this));
        
        return false;
    });
});
</script>