<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth();

$page_title = "Dashboard";
$body_class = "dashboard-page";

// Get dashboard statistics
$stats = [];
$recent_employees = [];
$upcoming_birthdays = [];
$pending_actions = [];

try {
    // Total employees
    $query = "SELECT COUNT(*) as total FROM employees WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_employees'] = $stmt->fetchColumn();

    // Total departments
    $query = "SELECT COUNT(*) as total FROM departments WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_departments'] = $stmt->fetchColumn();

    // Pending payroll
    $query = "SELECT COUNT(*) as total FROM payroll_periods WHERE status = 'draft'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_payroll'] = $stmt->fetchColumn();

    // Active projects
    $query = "SELECT COUNT(*) as total FROM projects WHERE project_status = 'ongoing'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['active_projects'] = $stmt->fetchColumn();

    // Pending loan applications
    $query = "SELECT COUNT(*) as total FROM employee_loans WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_loans'] = $stmt->fetchColumn();

    // Pending salary advances
    $query = "SELECT COUNT(*) as total FROM salary_advances WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_advances'] = $stmt->fetchColumn();

    // Recent employees
    $query = "SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, 
                     d.department_name, e.employment_date, e.status
              FROM employees e 
              LEFT JOIN departments d ON e.department_id = d.department_id 
              ORDER BY e.created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Upcoming birthdays
    $query = "SELECT first_name, last_name, date_of_birth 
              FROM employees 
              WHERE MONTH(date_of_birth) = MONTH(CURDATE()) 
              AND DAY(date_of_birth) >= DAY(CURDATE())
              AND status = 'active'
              ORDER BY DAY(date_of_birth) ASC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $upcoming_birthdays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Department distribution
    $query = "SELECT d.department_name, COUNT(e.employee_id) as employee_count
              FROM departments d
              LEFT JOIN employees e ON d.department_id = e.department_id AND e.status = 'active'
              WHERE d.is_active = 1
              GROUP BY d.department_id, d.department_name
              ORDER BY employee_count DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "Error loading dashboard data. Please try again.";
}

include 'includes/header.php';
?>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_employees']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Departments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_departments']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-building fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending Payroll</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_payroll']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Active Projects</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_projects']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hard-hat fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Employees -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Recent Employees</h6>
                <a href="modules/employees/add.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i>Add New
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Employment Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                                <td><?php echo formatDate($employee['employment_date']); ?></td>
                                <td><?php echo getStatusBadge($employee['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Department Distribution Chart -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Department Distribution</h6>
            </div>
            <div class="card-body">
                <canvas id="departmentChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="modules/employees/add.php" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus me-2"></i>Add Employee
                    </a>
                    <a href="modules/payroll/process.php" class="btn btn-success btn-block">
                        <i class="fas fa-calculator me-2"></i>Process Payroll
                    </a>
                    <a href="modules/attendance/mark.php" class="btn btn-warning btn-block">
                        <i class="fas fa-clock me-2"></i>Mark Attendance
                    </a>
                    <a href="modules/reports/" class="btn btn-info btn-block">
                        <i class="fas fa-chart-pie me-2"></i>Generate Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Upcoming Birthdays -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Upcoming Birthdays</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($upcoming_birthdays)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_birthdays as $birthday): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo formatDate($birthday['date_of_birth'], 'F j'); ?>
                                </small>
                            </div>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo calculateAge($birthday['date_of_birth']); ?> yrs
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No upcoming birthdays this month.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Actions -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Pending Actions</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php if ($stats['pending_loans'] > 0): ?>
                    <a href="modules/loans/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Loan Applications
                        <span class="badge bg-warning rounded-pill"><?php echo $stats['pending_loans']; ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($stats['pending_advances'] > 0): ?>
                    <a href="modules/loans/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Salary Advances
                        <span class="badge bg-warning rounded-pill"><?php echo $stats['pending_advances']; ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($stats['pending_payroll'] > 0): ?>
                    <a href="modules/payroll/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Payroll Processing
                        <span class="badge bg-warning rounded-pill"><?php echo $stats['pending_payroll']; ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Department Distribution Chart
    const deptCtx = document.getElementById('departmentChart').getContext('2d');
    const deptChart = new Chart(deptCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($department_stats, 'department_name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($department_stats, 'employee_count')); ?>,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#858796', '#5a5c69', '#6f42c1', '#e83e8c', '#fd7e14'
                ]
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>