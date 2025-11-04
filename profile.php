<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth();

$page_title = "My Profile";
$current_user = $auth->getCurrentUser();

// Get user details
$user_details = [];
$payroll_history = [];

try {
    $query = "SELECT u.*, e.*, d.department_name, et.type_name as employee_type
              FROM users u 
              JOIN employees e ON u.employee_id = e.employee_id 
              LEFT JOIN departments d ON e.department_id = d.department_id 
              LEFT JOIN employee_types et ON e.employee_type_id = et.employee_type_id 
              WHERE u.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $current_user['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent payroll history
    $query = "SELECT pm.*, pp.period_name, pp.start_date, pp.end_date
              FROM payroll_master pm
              JOIN payroll_periods pp ON pm.period_id = pp.period_id
              WHERE pm.employee_id = :employee_id
              ORDER BY pp.start_date DESC
              LIMIT 6";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':employee_id', $current_user['employee_id'], PDO::PARAM_INT);
    $stmt->execute();
    $payroll_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    $error = "Error loading profile data.";
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Personal Information</h6>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center" 
                         style="width: 100px; height: 100px;">
                        <span class="text-white fw-bold" style="font-size: 2rem;">
                            <?php echo strtoupper(substr($user_details['first_name'], 0, 1) . substr($user_details['last_name'], 0, 1)); ?>
                        </span>
                    </div>
                </div>
                
                <h5 class="font-weight-bold"><?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($user_details['employee_type']); ?></p>
                
                <div class="row text-start mt-4">
                    <div class="col-12 mb-2">
                        <strong>Employee Code:</strong> <?php echo htmlspecialchars($user_details['employee_code']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Department:</strong> <?php echo htmlspecialchars($user_details['department_name']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Email:</strong> <?php echo htmlspecialchars($user_details['email']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($user_details['phone_number']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Employment Date:</strong> <?php echo formatDate($user_details['employment_date']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Employment Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 text-center mb-3">
                        <div class="text-primary fw-bold" style="font-size: 1.5rem;">
                            <?php echo calculateAge($user_details['date_of_birth']); ?>
                        </div>
                        <small class="text-muted">Age</small>
                    </div>
                    <div class="col-6 text-center mb-3">
                        <div class="text-success fw-bold" style="font-size: 1.5rem;">
                            <?php echo calculateTenure($user_details['employment_date']); ?>
                        </div>
                        <small class="text-muted">Tenure</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="mb-2">
                    <strong>Bank Details:</strong><br>
                    <?php if ($user_details['bank_name'] && $user_details['account_number']): ?>
                        <?php echo htmlspecialchars($user_details['bank_name']); ?><br>
                        Account: <?php echo htmlspecialchars($user_details['account_number']); ?><br>
                        Name: <?php echo htmlspecialchars($user_details['account_name']); ?>
                    <?php else: ?>
                        <span class="text-muted">Not provided</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Salary Information -->
        <div class="card shadow mb-4 mt-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Salary Information</h6>
            </div>
            <div class="card-body">
                <?php
                // Get the latest salary information
                $salary_query = "SELECT 
                                    pm.basic_salary, 
                                    pm.housing_allowance, 
                                    pm.transport_allowance,
                                    pm.utility_allowance,
                                    pm.meal_allowance,
                                    pm.pension,
                                    pm.tax,
                                    pm.net_salary,
                                    pp.period_name
                                FROM payroll_master pm
                                JOIN payroll_periods pp ON pm.period_id = pp.period_id
                                WHERE pm.employee_id = :employee_id
                                ORDER BY pp.end_date DESC
                                LIMIT 1";
                
                try {
                    $stmt = $db->prepare($salary_query);
                    $stmt->bindValue(':employee_id', $current_user['employee_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $salary_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($salary_info):
                        $gross_salary = $salary_info['basic_salary'] + 
                                     $salary_info['housing_allowance'] + 
                                     $salary_info['transport_allowance'] + 
                                     $salary_info['utility_allowance'] + 
                                     $salary_info['meal_allowance'];
                ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Period:</strong> <?php echo htmlspecialchars($salary_info['period_name']); ?></p>
                        <p><strong>Basic Salary:</strong> <?php echo formatCurrency($salary_info['basic_salary']); ?></p>
                        <p><strong>Housing Allowance:</strong> <?php echo formatCurrency($salary_info['housing_allowance']); ?></p>
                        <p><strong>Transport Allowance:</strong> <?php echo formatCurrency($salary_info['transport_allowance']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Utility Allowance:</strong> <?php echo formatCurrency($salary_info['utility_allowance']); ?></p>
                        <p><strong>Meal Allowance:</strong> <?php echo formatCurrency($salary_info['meal_allowance']); ?></p>
                        <p><strong>Gross Salary:</strong> <?php echo formatCurrency($gross_salary); ?></p>
                        <p><strong>Pension (8%):</strong> <?php echo formatCurrency($salary_info['pension']); ?></p>
                        <p><strong>PAYE Tax:</strong> <?php echo formatCurrency($salary_info['tax']); ?></p>
                        <h5 class="mt-3"><strong>Net Salary:</strong> <span class="text-success"><?php echo formatCurrency($salary_info['net_salary']); ?></span></h5>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted">No salary information available.</p>
                <?php 
                    endif;
                } catch (PDOException $e) {
                    error_log("Error fetching salary info: " . $e->getMessage());
                    echo '<p class="text-danger">Error loading salary information.</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Payroll History -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Recent Payroll History</h6>
                <a href="modules/payroll/payslips.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($payroll_history)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Basic Salary</th>
                                <th>Gross Salary</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payroll_history as $payroll): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payroll['period_name']); ?></td>
                                <td><?php echo formatCurrency($payroll['basic_salary']); ?></td>
                                <td><?php echo formatCurrency($payroll['gross_salary']); ?></td>
                                <td><?php echo formatCurrency($payroll['total_deductions']); ?></td>
                                <td><strong><?php echo formatCurrency($payroll['net_salary']); ?></strong></td>
                                <td><?php echo getStatusBadge($payroll['payment_status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No payroll history available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Profile Form -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Update Personal Information</h6>
            </div>
            <div class="card-body">
                <form id="updateProfileForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($user_details['email']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone_number" 
                                       value="<?php echo htmlspecialchars($user_details['phone_number']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Residential Address</label>
                        <textarea class="form-control" name="residential_address" rows="3"><?php echo htmlspecialchars($user_details['residential_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Bank Name</label>
                                <select class="form-control" name="bank_name">
                                    <option value="">Select Bank</option>
                                    <?php foreach (getBankList() as $bank): ?>
                                    <option value="<?php echo $bank; ?>" 
                                        <?php echo ($user_details['bank_name'] == $bank) ? 'selected' : ''; ?>>
                                        <?php echo $bank; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number" 
                                       value="<?php echo htmlspecialchars($user_details['account_number'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input type="text" class="form-control" name="account_name" 
                               value="<?php echo htmlspecialchars($user_details['account_name'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Information
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/employees/update?id=<?php echo $current_user['employee_id']; ?>', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Profile updated successfully!');
            location.reload();
        } else {
            alert('Error updating profile: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating profile');
    });
});
</script>

<?php include 'includes/footer.php'; ?>