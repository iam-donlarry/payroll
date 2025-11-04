<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('project_manager');

$page_title = "Projects Management";
$body_class = "projects-page";

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'delete' && isset($_GET['id'])) {
    $project_id = (int)$_GET['id'];
    
    try {
        // Check if project has assigned employees
        $check_query = "SELECT COUNT(*) as employee_count FROM employee_project_assignments WHERE project_id = :project_id AND is_active = 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['employee_count'] > 0) {
            $message = '<div class="alert alert-danger">Cannot delete project with active employee assignments.</div>';
        } else {
            $query = "UPDATE projects SET project_status = 'on_hold' WHERE project_id = :project_id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':project_id', $project_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = '<div class="alert alert-success">Project status set to on-hold successfully.</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error updating project: ' . $e->getMessage() . '</div>';
    }
}

// Get projects
$projects = [];
$employees = [];

try {
    $query = "SELECT p.*, 
                     CONCAT(e.first_name, ' ', e.last_name) as project_manager_name,
                     (SELECT COUNT(*) FROM employee_project_assignments epa WHERE epa.project_id = p.project_id AND epa.is_active = 1) as assigned_employees
              FROM projects p
              LEFT JOIN employees e ON p.project_manager_id = e.employee_id
              ORDER BY p.project_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get employees for project manager dropdown
    $emp_query = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE status = 'active' ORDER BY full_name";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute();
    $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Projects error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading projects.</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Projects Management</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
        <i class="fas fa-plus me-2"></i>Add Project
    </button>
</div>

<?php echo $message; ?>

<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Projects (<?php echo count($projects); ?>)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="projectsTable">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <th>Location</th>
                        <th>Project Manager</th>
                        <th>Assigned Employees</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($project['project_code']); ?></td>
                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                        <td><?php echo htmlspecialchars($project['city'] . ', ' . $project['state']); ?></td>
                        <td><?php echo htmlspecialchars($project['project_manager_name']); ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo $project['assigned_employees']; ?> employees</span>
                        </td>
                        <td><?php echo getStatusBadge($project['project_status']); ?></td>
                        <td><?php echo formatDate($project['start_date']); ?></td>
                        <td><?php echo formatDate($project['expected_end_date']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-warning edit-project" 
                                        data-id="<?php echo $project['project_id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $project['project_id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Are you sure you want to set this project to on-hold?')">
                                    <i class="fas fa-pause-circle"></i>
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

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/api/projects/">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project Name</label>
                            <input type="text" class="form-control" name="project_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project Code</label>
                            <input type="text" class="form-control" name="project_code" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location Address</label>
                        <textarea class="form-control" name="location_address" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected End Date</label>
                            <input type="date" class="form-control" name="expected_end_date">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project Manager</label>
                            <select class="form-control" name="project_manager_id">
                                <option value="">Select Manager</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['employee_id']; ?>">
                                    <?php echo htmlspecialchars($employee['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project Budget</label>
                            <input type="number" class="form-control currency-input" name="project_budget" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#projectsTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']]
    });

    // Edit project functionality
    $('.edit-project').on('click', function() {
        const id = $(this).data('id');
        // In a real application, you would fetch project data via API and populate an edit modal
        alert(`Edit Project ID: ${id}\nThis would open an edit modal with project data.`);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
