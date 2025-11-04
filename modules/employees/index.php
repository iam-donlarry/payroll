<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('hr_manager');

$page_title = "Employee Management";
$body_class = "employees-page";

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'delete' && isset($_GET['id'])) {
    $employee_id = (int)$_GET['id'];
    
    try {
        $query = "UPDATE employees SET status = 'inactive' WHERE employee_id = :employee_id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $message = '<div class="alert alert-success">Employee deactivated successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error deactivating employee: ' . $e->getMessage() . '</div>';
    }
}

// Get filter parameters
$department_id = $_GET['department_id'] ?? '';
$status = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($department_id) {
    $where_conditions[] = "e.department_id = :department_id";
    $params[':department_id'] = $department_id;
}

if ($status) {
    $where_conditions[] = "e.status = :status";
    $params[':status'] = $status;
}

if ($search) {
    $where_conditions[] = "(e.first_name LIKE :search OR e.last_name LIKE :search OR e.employee_code LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get employees
$employees = [];
$departments = [];

try {
    $query = "SELECT e.*, d.department_name, et.type_name as employee_type,
                     CONCAT(e.first_name, ' ', e.last_name) as full_name
              FROM employees e 
              LEFT JOIN departments d ON e.department_id = d.department_id 
              LEFT JOIN employee_types et ON e.employee_type_id = et.employee_type_id 
              $where_clause
              ORDER BY e.first_name, e.last_name";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get departments for filter
    $dept_query = "SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Employees list error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading employees.</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Employee Management</h1>
    <div>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Add Employee
        </a>
        <a href="imports.php" class="btn btn-outline-secondary">
            <i class="fas fa-upload me-2"></i>Import
        </a>
    </div>
</div>

<?php echo $message; ?>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select class="form-control" name="department_id">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department_id']; ?>" 
                        <?php echo ($department_id == $dept['department_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-control" name="status">
                    <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo ($status == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                    <option value="terminated" <?php echo ($status == 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                    <option value="">All Status</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by name or employee code...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Employees Table -->
<div class="card shadow">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Employees (<?php echo count($employees); ?>)</h6>
        <div>
            <a href="/api/employees/exports?format=csv" class="btn btn-sm btn-success">
                <i class="fas fa-download me-2"></i>Export CSV
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="employeesTable">
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Employee Type</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Employment Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($employee['employee_code']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                        <td><?php echo htmlspecialchars($employee['employee_type']); ?></td>
                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                        <td><?php echo htmlspecialchars($employee['phone_number']); ?></td>
                        <td><?php echo formatDate($employee['employment_date']); ?></td>
                        <td><?php echo getStatusBadge($employee['status']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view.php?id=<?php echo $employee['employee_id']; ?>" 
                                   class="btn btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $employee['employee_id']; ?>" 
                                   class="btn btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($employee['status'] == 'active'): ?>
                                <a href="?action=delete&id=<?php echo $employee['employee_id']; ?>" 
                                   class="btn btn-danger" title="Deactivate"
                                   onclick="return confirm('Are you sure you want to deactivate this employee?')">
                                    <i class="fas fa-user-slash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($employees)): ?>
        <div class="text-center py-4">
            <i class="fas fa-users fa-3x text-muted mb-3"></i>
            <p class="text-muted">No employees found matching your criteria.</p>
            <a href="add.php" class="btn btn-primary">Add First Employee</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#employeesTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        language: {
            search: "Search employees:",
            lengthMenu: "Show _MENU_ employees per page",
            info: "Showing _START_ to _END_ of _TOTAL_ employees"
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>