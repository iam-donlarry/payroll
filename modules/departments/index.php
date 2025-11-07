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

// Message placeholder - now primarily populated by AJAX responses
$message = '';

// The old GET deletion logic is REMOVED as we now use AJAX/DELETE method.

// Get departments (still needed for initial page load)
$departments = [];
try {
    // This complex query is still needed here to display the employee count
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

<!-- Add this at the top of your file -->
<div id="message-area"></div>

<!-- Add Department Button -->
<button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
    <i class="fas fa-plus"></i> Add Department
</button>

<!-- Departments Table -->
<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table id="departmentsTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Will be populated by JavaScript -->
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
            <form id="addDepartmentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department Code *</label>
                        <input type="text" class="form-control" id="department_code" name="department_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDepartmentForm">
                <input type="hidden" id="edit-department-id" name="department_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="edit-department-name" name="department_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department Code *</label>
                        <input type="text" class="form-control" id="edit-department-code" name="department_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include required JavaScript libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
// Base API URL
const API_BASE_URL = '../../api/departments/';

// Display message function
function displayMessage(type, message) {
    const alert = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#message-area').html(alert);
}

// Load departments
function loadDepartments() {
    $.ajax({
        url: API_BASE_URL,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                renderDepartments(response.data);
            }
        },
        error: function(xhr) {
            displayMessage('danger', 'Failed to load departments');
            console.error('Error:', xhr.responseText);
        }
    });
}

// Render departments table
function renderDepartments(departments) {
    const tbody = $('#departmentsTable tbody');
    tbody.empty();
    
    departments.forEach(dept => {
        const row = `
            <tr>
                <td>${dept.department_code}</td>
                <td>${dept.department_name}</td>
                <td>${dept.description || '-'}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-warning edit-department" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editDepartmentModal"
                                data-id="${dept.department_id}"
                                data-code="${dept.department_code}"
                                data-name="${dept.department_name}"
                                data-desc="${dept.description}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger delete-department" 
                                data-id="${dept.department_id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Handle add department
$('#addDepartmentForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        department_name: $('#department_name').val().trim(),
        department_code: $('#department_code').val().trim(),
        description: $('#description').val().trim()
    };

    $.ajax({
        url: API_BASE_URL,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        success: function(response) {
            if (response.success) {
                displayMessage('success', 'Department created successfully!');
                $('#addDepartmentModal').modal('hide');
                $('#addDepartmentForm')[0].reset();
                loadDepartments();
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            const errorMessage = response && response.message ? response.message : 'Failed to create department';
            displayMessage('danger', errorMessage);
        }
    });
});

// Handle edit department
$(document).on('click', '.edit-department', function() {
    const id = $(this).data('id');
    const code = $(this).data('code');
    const name = $(this).data('name');
    const desc = $(this).data('desc');
    
    $('#edit-department-id').val(id);
    $('#edit-department-code').val(code);
    $('#edit-department-name').val(name);
    $('#edit-description').val(desc);
});

$('#editDepartmentForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        department_id: $('#edit-department-id').val(),
        department_name: $('#edit-department-name').val().trim(),
        department_code: $('#edit-department-code').val().trim(),
        description: $('#edit-description').val().trim()
    };

    $.ajax({
        url: API_BASE_URL,
        type: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        success: function(response) {
            if (response.success) {
                displayMessage('success', 'Department updated successfully!');
                $('#editDepartmentModal').modal('hide');
                loadDepartments();
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            const errorMessage = response && response.message ? response.message : 'Failed to update department';
            displayMessage('danger', errorMessage);
        }
    });
});

// Handle delete department
$(document).on('click', '.delete-department', function() {
    const id = $(this).data('id');
    
    if (confirm('Are you sure you want to delete this department?')) {
        $.ajax({
            url: API_BASE_URL,
            type: 'DELETE',
            contentType: 'application/json',
            data: JSON.stringify({ department_id: id }),
            success: function(response) {
                if (response.success) {
                    displayMessage('success', 'Department deleted successfully!');
                    loadDepartments();
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                const errorMessage = response && response.message ? response.message : 'Failed to delete department';
                displayMessage('danger', errorMessage);
            }
        });
    }
});

// Initialize
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#departmentsTable').DataTable({
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: [3] } // Disable sorting on action column
        ]
    });

    // Load initial data
    loadDepartments();
});
</script>

<?php include '../../includes/footer.php'; ?>