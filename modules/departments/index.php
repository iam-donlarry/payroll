<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('admin');

$page_title = "Departments Management";
$body_class = "departments-page";

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'delete' && isset($_GET['id'])) {
    $department_id = (int)$_GET['id'];
    
    try {
        // Check if department has employees
        $check_query = "SELECT COUNT(*) as employee_count FROM employees WHERE department_id = :department_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindValue(':department_id', $department_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['employee_count'] > 0) {
            $message = '<div class="alert alert-danger">Cannot delete department with assigned employees.</div>';
        } else {
            $query = "UPDATE departments SET is_active = 0 WHERE department_id = :department_id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':department_id', $department_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = '<div class="alert alert-success">Department deleted successfully.</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error deleting department: ' . $e->getMessage() . '</div>';
    }
}

// Get departments
$departments = [];

try {
    $query = "SELECT d.*, 
                     (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.department_id AND e.status = 'active') as employee_count
              FROM departments d 
              WHERE d.is_active = 1
              ORDER BY d.department_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Departments error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading departments.</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Departments Management</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
        <i class="fas fa-plus me-2"></i>Add Department
    </button>
</div>

<?php echo $message; ?>

<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Departments (<?php echo count($departments); ?>)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="departmentsTable">
                <thead>
                    <tr>
                        <th>Department Code</th>
                        <th>Department Name</th>
                        <th>Description</th>
                        <th>Employee Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dept['department_code']); ?></td>
                        <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                        <td><?php echo htmlspecialchars($dept['description']); ?></td>
                        <td>
                            <span class="badge bg-primary"><?php echo $dept['employee_count']; ?> employees</span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-warning edit-department" 
                                        data-id="<?php echo $dept['department_id']; ?>"
                                        data-code="<?php echo htmlspecialchars($dept['department_code']); ?>"
                                        data-name="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                        data-desc="<?php echo htmlspecialchars($dept['description']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $dept['department_id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this department?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/departments/">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department Name</label>
                        <input type="text" class="form-control" name="department_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department Code</label>
                        <input type="text" class="form-control" name="department_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#departmentsTable').DataTable({
        pageLength: 25
    });

    // Edit department functionality
    $('.edit-department').on('click', function() {
        const id = $(this).data('id');
        const code = $(this).data('code');
        const name = $(this).data('name');
        const desc = $(this).data('desc');

        // Populate edit modal (similar to add modal but with values)
        // For brevity, we'll just alert
        alert(`Edit Department: ${name}\nThis would open an edit modal.`);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>