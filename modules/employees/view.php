<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth();

$page_title = "View Employee";
$body_class = "employees-page";

$employee_id = $_GET['id'] ?? 0;

if (!$employee_id) {
    header("Location: index.php");
    exit;
}

// Get employee data
$employee = [];
$salary_structure = [];
$attendance_summary = [];
$payroll_history = [];

try {
    // Update the query to join the banks table
    $query = "SELECT e.*, d.department_name, d.department_code,
                     et.type_name as employee_type, et.payment_frequency,
                     ee.passport_number, ee.passport_expiry, ee.country_of_origin,
                     ee.work_permit_number, ee.work_permit_expiry, ee.base_currency,
                     ee.conversion_rate,
                     b.bank_name,
                     CONCAT(e.first_name, ' ', e.last_name) as full_name,
                     DATE_FORMAT(e.date_of_birth, '%Y-%m-%d') as date_of_birth,
                     DATE_FORMAT(e.employment_date, '%Y-%m-%d') as employment_date,
                     TIMESTAMPDIFF(YEAR, e.date_of_birth, CURDATE()) as age,
                     TIMESTAMPDIFF(YEAR, e.employment_date, CURDATE()) as years_of_service
              FROM employees e 
              LEFT JOIN departments d ON e.department_id = d.department_id 
              LEFT JOIN employee_types et ON e.employee_type_id = et.employee_type_id 
              LEFT JOIN expatriate_employees ee ON e.employee_id = ee.employee_id 
              LEFT JOIN banks b ON e.bank_id = b.id
              WHERE e.employee_id = :employee_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header("Location: index.php");
        exit;
    }

    // Get salary structure
    $salary_query = "SELECT ess.*, sc.component_name, sc.component_type, sc.component_code,
                            sc.is_taxable, sc.is_statutory
                     FROM employee_salary_structure ess
                     JOIN salary_components sc ON ess.component_id = sc.component_id
                     WHERE ess.employee_id = :employee_id AND ess.is_active = 1
                     ORDER BY sc.component_type, sc.component_name";
    $salary_stmt = $db->prepare($salary_query);
    $salary_stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
    $salary_stmt->execute();
    $salary_structure = $salary_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get attendance summary for current month
    $attendance_query = "SELECT 
                         COUNT(*) as total_days,
                         SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                         SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                         SUM(total_hours) as total_hours,
                         SUM(overtime_hours) as total_overtime
                         FROM attendance_records 
                         WHERE employee_id = :employee_id 
                         AND MONTH(attendance_date) = MONTH(CURDATE()) 
                         AND YEAR(attendance_date) = YEAR(CURDATE())";
    $attendance_stmt = $db->prepare($attendance_query);
    $attendance_stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
    $attendance_stmt->execute();
    $attendance_summary = $attendance_stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent payroll history
    $payroll_query = "SELECT pm.*, pp.period_name, pp.start_date, pp.end_date
                      FROM payroll_master pm
                      JOIN payroll_periods pp ON pm.period_id = pp.period_id
                      WHERE pm.employee_id = :employee_id
                      ORDER BY pp.start_date DESC
                      LIMIT 6";
    $payroll_stmt = $db->prepare($payroll_query);
    $payroll_stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
    $payroll_stmt->execute();
    $payroll_history = $payroll_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("View employee error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading employee data.</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Employee Details</h1>
    <div>
        <a href="edit.php?id=<?php echo $employee_id; ?>" class="btn btn-warning">
            <i class="fas fa-edit me-2"></i>Edit
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
    </div>
</div>

<div class="row">
    <!-- Personal Information -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Personal Information</h6>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center" 
                         style="width: 100px; height: 100px;">
                        <span class="text-white fw-bold" style="font-size: 2rem;">
                            <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                        </span>
                    </div>
                </div>
                
                <h4 class="font-weight-bold"><?php echo htmlspecialchars($employee['full_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($employee['employee_type']); ?></p>
                
                <div class="row text-start mt-4">
                    <div class="col-12 mb-2">
                        <strong>Employee Code:</strong> <?php echo htmlspecialchars($employee['employee_code']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Department:</strong> <?php echo htmlspecialchars($employee['department_name']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($employee['phone_number']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Date of Birth:</strong> <?php echo formatDate($employee['date_of_birth']); ?> (<?php echo $employee['age']; ?> years)
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Gender:</strong> <?php echo htmlspecialchars($employee['gender']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Marital Status:</strong> <?php echo htmlspecialchars($employee['marital_status']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employment Details -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Employment Details</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Employment Date:</strong> <?php echo formatDate($employee['employment_date']); ?>
                </div>
                <div class="mb-3">
                    <strong>Years of Service:</strong> <?php echo $employee['years_of_service']; ?> years
                </div>
                <div class="mb-3">
                    <strong>Status:</strong> <?php echo getStatusBadge($employee['status']); ?>
                </div>
                
                <?php if ($employee['confirmation_date']): ?>
                <div class="mb-3">
                    <strong>Confirmation Date:</strong> <?php echo formatDate($employee['confirmation_date']); ?>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="mb-3">
                    <strong>Residential Address:</strong><br>
                    <?php echo nl2br(htmlspecialchars($employee['residential_address'] ?? 'Not provided')); ?>
                </div>
                
                <?php if ($employee['state_of_origin']): ?>
                <div class="mb-3">
                    <strong>State of Origin:</strong> <?php echo htmlspecialchars($employee['state_of_origin']); ?>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <strong>LGA of Origin:</strong> <?php echo htmlspecialchars($employee['lga_of_origin']);?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Bank & Statutory Information -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Bank & Statutory Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Bank Name:</strong><br>
                            <?php echo htmlspecialchars($employee['bank_name'] ?? 'Not provided'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Account Number:</strong><br>
                            <?php echo htmlspecialchars($employee['account_number'] ?? 'Not provided'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Account Name:</strong><br>
                            <?php echo htmlspecialchars($employee['account_name'] ?? 'Not provided'); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>BVN:</strong><br>
                            <?php echo htmlspecialchars($employee['bvn'] ?? 'Not provided'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Pension PIN:</strong><br>
                            <?php echo htmlspecialchars($employee['pension_pin'] ?? 'Not provided'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Tax ID:</strong><br>
                            <?php echo htmlspecialchars($employee['tax_id'] ?? 'Not provided'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Insurance Number:</strong><br>
                            <?php echo htmlspecialchars($employee['insurance_number'] ?? 'Not provided'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Structure -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Salary Structure</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addComponentModal">
                    <i class="fas fa-plus me-1"></i>Add Component
                </button>
            </div>
            <div class="card-body">
                <?php if (!empty($salary_structure)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Taxable</th>
                                <th>Effective Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_earnings = 0;
                            $total_deductions = 0;
                            $total_allowances = 0;
                            ?>
                            <?php foreach ($salary_structure as $component): ?>
                            <?php
                            if ($component['component_type'] == 'earning') {
                                $total_earnings += $component['amount'];
                            } elseif ($component['component_type'] == 'deduction') {
                                $total_deductions += $component['amount'];
                            } elseif ($component['component_type'] == 'allowance') {
                                $total_allowances += $component['amount'];
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($component['component_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $component['component_type'] == 'earning' ? 'success' : ($component['component_type'] == 'deduction' ? 'danger' : 'info'); ?>">
                                        <?php echo ucfirst($component['component_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatCurrency($component['amount']); ?></td>
                                <td><?php echo $component['is_taxable'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo formatDate($component['effective_date']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"><strong>Total Earnings</strong></td>
                                <td><strong><?php echo formatCurrency($total_earnings); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Total Allowances</strong></td>
                                <td><strong><?php echo formatCurrency($total_allowances); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                            <!--<tr>
                                <td colspan="2"><strong>Total Deductions</strong></td>
                                <td><strong><?php echo formatCurrency($total_deductions); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>-->
                            <tr class="table-primary">
                                <td colspan="2"><strong>Gross Salary</strong></td>
                                <td><strong><?php echo formatCurrency($total_earnings + $total_allowances); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                            <!--<tr class="table-success">
                                <td colspan="2"><strong>Net Salary</strong></td>
                                <td><strong><?php echo formatCurrency(($total_earnings + $total_allowances) - $total_deductions); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>-->
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No salary components defined.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance & Payroll Summary -->
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Attendance Summary (This Month)</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($attendance_summary): ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-primary fw-bold" style="font-size: 1.5rem;">
                                    <?php echo $attendance_summary['present_days']; ?>
                                </div>
                                <small class="text-muted">Present</small>
                            </div>
                            <div class="col-4">
                                <div class="text-warning fw-bold" style="font-size: 1.5rem;">
                                    <?php echo $attendance_summary['absent_days']; ?>
                                </div>
                                <small class="text-muted">Absent</small>
                            </div>
                            <div class="col-4">
                                <div class="text-info fw-bold" style="font-size: 1.5rem;">
                                    <?php echo number_format($attendance_summary['total_hours'] ?? 0, 1); ?>
                                </div>
                                <small class="text-muted">Total Hours</small>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                Overtime: <?php echo number_format($attendance_summary['total_overtime'] ?? 0, 1); ?> hours
                            </small>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">No attendance records for this month.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Payroll</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($payroll_history)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($payroll_history as $payroll): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($payroll['period_name']); ?></strong><br>
                                    <small class="text-muted">Net: <?php echo formatCurrency($payroll['net_salary']); ?></small>
                                </div>
                                <span class="badge bg-<?php echo $payroll['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($payroll['payment_status']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">No payroll history available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expatriate Information (if applicable) -->
        <?php if ($employee['passport_number']): ?>
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Expatriate Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Passport Number:</strong><br>
                            <?php echo htmlspecialchars($employee['passport_number']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Passport Expiry:</strong><br>
                            <?php echo formatDate($employee['passport_expiry']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Country of Origin:</strong><br>
                            <?php echo htmlspecialchars($employee['country_of_origin']); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php if ($employee['work_permit_number']): ?>
                        <div class="mb-3">
                            <strong>Work Permit Number:</strong><br>
                            <?php echo htmlspecialchars($employee['work_permit_number']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Work Permit Expiry:</strong><br>
                            <?php echo formatDate($employee['work_permit_expiry']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <strong>Base Currency:</strong><br>
                            <?php echo htmlspecialchars($employee['base_currency']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Conversion Rate:</strong><br>
                            <?php echo number_format($employee['conversion_rate'], 4); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Salary Component Modal -->
<div class="modal fade" id="addComponentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Salary Component</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addComponentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Component</label>
                        <select class="form-control" name="component_id" required>
                            <option value="">Select Component</option>
                            <!-- Options would be loaded via AJAX -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Effective Date</label>
                        <input type="date" class="form-control" name="effective_date" required>
                    </div>
                    <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Component</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load salary components for dropdown
    fetch('/api/salary-components/')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.querySelector('select[name="component_id"]');
                select.innerHTML = '<option value="">Select Component</option>';
                data.data.forEach(component => {
                    const option = document.createElement('option');
                    option.value = component.component_id;
                    option.textContent = component.component_name + ' (' + component.component_type + ')';
                    select.appendChild(option);
                });
            }
        });

    // Handle form submission
    document.getElementById('addComponentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('/api/employees/add_salary_component', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(Object.fromEntries(formData))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Salary component added successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding salary component');
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>