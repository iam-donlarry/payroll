<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth();

$page_title = "Attendance Management";
$body_class = "attendance-page";

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$department_id = $_GET['department_id'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';

// Get attendance records
$attendance_records = [];
$departments = [];
$employees = [];

try {
    // Build query with filters
    $where_conditions = ["ar.attendance_date BETWEEN :start_date AND :end_date"];
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];

    if ($department_id) {
        $where_conditions[] = "e.department_id = :department_id";
        $params[':department_id'] = $department_id;
    }

    if ($employee_id) {
        $where_conditions[] = "ar.employee_id = :employee_id";
        $params[':employee_id'] = $employee_id;
    }

    $where_clause = implode(" AND ", $where_conditions);

    $query = "SELECT ar.*, e.first_name, e.last_name, e.employee_code,
                     d.department_name, p.project_name,
                     DATE_FORMAT(ar.attendance_date, '%W, %M %e, %Y') as formatted_date
              FROM attendance_records ar
              JOIN employees e ON ar.employee_id = e.employee_id
              LEFT JOIN departments d ON e.department_id = d.department_id
              LEFT JOIN projects p ON ar.project_id = p.project_id
              WHERE $where_clause
              ORDER BY ar.attendance_date DESC, e.first_name, e.last_name";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get departments for filter
    $dept_query = "SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get employees for filter
    $emp_query = "SELECT employee_id, first_name, last_name, employee_code 
                  FROM employees 
                  WHERE status = 'active' 
                  ORDER BY first_name, last_name";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute();
    $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Attendance management error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading attendance data.</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Attendance Management</h1>
    <div>
        <a href="mark.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Mark Attendance
        </a>
        <a href="reports.php" class="btn btn-success">
            <i class="fas fa-chart-bar me-2"></i>Reports
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select class="form-control" name="department_id">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department_id']; ?>" 
                        <?php echo $department_id == $dept['department_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Employee</label>
                <select class="form-control" name="employee_id">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['employee_id']; ?>" 
                        <?php echo $employee_id == $emp['employee_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_code'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
                <a href="index.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Summary -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Records</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($attendance_records); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Present</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo count(array_filter($attendance_records, function($r) { return $r['status'] == 'present'; })); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Absent</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo count(array_filter($attendance_records, function($r) { return $r['status'] == 'absent'; })); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-times fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Late</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo count(array_filter($attendance_records, function($r) { return $r['status'] == 'late'; })); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Hours</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format(array_sum(array_column($attendance_records, 'total_hours')), 1); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-secondary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                            Overtime</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format(array_sum(array_column($attendance_records, 'overtime_hours')), 1); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Records -->
<div class="card shadow">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            Attendance Records (<?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?>)
        </h6>
        <div>
            <a href="/api/attendance/exports?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=csv" 
               class="btn btn-sm btn-success">
                <i class="fas fa-download me-2"></i>Export CSV
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="attendanceTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Project</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Total Hours</th>
                        <th>Overtime</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_records as $record): ?>
                    <tr>
                        <td>
                            <strong><?php echo formatDate($record['attendance_date']); ?></strong><br>
                            <small class="text-muted"><?php echo $record['formatted_date']; ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($record['employee_code']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($record['department_name']); ?></td>
                        <td><?php echo htmlspecialchars($record['project_name']); ?></td>
                        <td>
                            <?php if ($record['check_in_time']): ?>
                                <?php echo date('h:i A', strtotime($record['check_in_time'])); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['check_out_time']): ?>
                                <?php echo date('h:i A', strtotime($record['check_out_time'])); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['total_hours']): ?>
                                <?php echo number_format($record['total_hours'], 1); ?> hrs
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['overtime_hours']): ?>
                                <?php echo number_format($record['overtime_hours'], 1); ?> hrs
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo getStatusBadge($record['status']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-warning edit-attendance" 
                                        data-id="<?php echo $record['attendance_id']; ?>"
                                        data-employee="<?php echo $record['employee_id']; ?>"
                                        data-date="<?php echo $record['attendance_date']; ?>"
                                        data-checkin="<?php echo $record['check_in_time']; ?>"
                                        data-checkout="<?php echo $record['check_out_time']; ?>"
                                        data-overtime="<?php echo $record['overtime_hours']; ?>"
                                        data-status="<?php echo $record['status']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $record['attendance_id']; ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this attendance record?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($attendance_records)): ?>
        <div class="text-center py-4">
            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
            <p class="text-muted">No attendance records found for the selected period.</p>
            <a href="mark.php" class="btn btn-primary">Mark First Attendance</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Attendance Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAttendanceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Check In Time</label>
                        <input type="time" class="form-control" name="check_in_time">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Check Out Time</label>
                        <input type="time" class="form-control" name="check_out_time">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Overtime Hours</label>
                        <input type="number" class="form-control" name="overtime_hours" step="0.5" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                            <option value="half_day">Half Day</option>
                            <option value="leave">Leave</option>
                        </select>
                    </div>
                    <input type="hidden" name="attendance_id" id="editAttendanceId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: "Search attendance:",
            lengthMenu: "Show _MENU_ records per page",
            info: "Showing _START_ to _END_ of _TOTAL_ records"
        }
    });

    // Edit attendance modal
    $('.edit-attendance').on('click', function() {
        const attendanceId = $(this).data('id');
        const checkIn = $(this).data('checkin');
        const checkOut = $(this).data('checkout');
        const overtime = $(this).data('overtime');
        const status = $(this).data('status');

        $('#editAttendanceId').val(attendanceId);
        $('input[name="check_in_time"]').val(checkIn);
        $('input[name="check_out_time"]').val(checkOut);
        $('input[name="overtime_hours"]').val(overtime);
        $('select[name="status"]').val(status);

        $('#editAttendanceModal').modal('show');
    });

    // Handle edit form submission
    $('#editAttendanceForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '/api/attendance/update',
            type: 'PUT',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Attendance updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error updating attendance');
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>