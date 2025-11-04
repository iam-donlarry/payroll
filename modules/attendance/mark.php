<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('hr_manager');

$page_title = "Mark Attendance";
$body_class = "attendance-page";

// Get employees and projects
$employees = [];
$projects = [];

try {
    $emp_query = "SELECT employee_id, first_name, last_name, employee_code 
                  FROM employees 
                  WHERE status = 'active' 
                  ORDER BY first_name, last_name";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute();
    $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);

    $proj_query = "SELECT project_id, project_name FROM projects WHERE project_status = 'ongoing' ORDER BY project_name";
    $proj_stmt = $db->prepare($proj_query);
    $proj_stmt->execute();
    $projects = $proj_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Attendance mark error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading data.</div>';
}

$message = '';

// Handle form submission
if ($_POST) {
    $attendance_data = [
        'employee_id' => (int)$_POST['employee_id'],
        'project_id' => (int)$_POST['project_id'],
        'attendance_date' => sanitizeInput($_POST['attendance_date']),
        'check_in_time' => sanitizeInput($_POST['check_in_time']),
        'check_out_time' => sanitizeInput($_POST['check_out_time']),
        'overtime_hours' => (float)$_POST['overtime_hours'],
        'status' => sanitizeInput($_POST['status'])
    ];

    // Calculate total hours
    $total_hours = 0;
    if ($attendance_data['check_in_time'] && $attendance_data['check_out_time']) {
        $start = new DateTime($attendance_data['check_in_time']);
        $end = new DateTime($attendance_data['check_out_time']);
        $diff = $start->diff($end);
        $total_hours = $diff->h + ($diff->i / 60);
    }
    $attendance_data['total_hours'] = $total_hours;

    $errors = [];

    if (empty($attendance_data['employee_id'])) $errors[] = "Employee is required.";
    if (empty($attendance_data['project_id'])) $errors[] = "Project is required.";
    if (empty($attendance_data['attendance_date'])) $errors[] = "Date is required.";
    if (empty($attendance_data['status'])) $errors[] = "Status is required.";

    if (empty($errors)) {
        try {
            $query = "INSERT INTO attendance_records SET 
                     employee_id = :employee_id,
                     project_id = :project_id,
                     attendance_date = :attendance_date,
                     check_in_time = :check_in_time,
                     check_out_time = :check_out_time,
                     total_hours = :total_hours,
                     overtime_hours = :overtime_hours,
                     status = :status
                     ON DUPLICATE KEY UPDATE
                     check_in_time = :check_in_time,
                     check_out_time = :check_out_time,
                     total_hours = :total_hours,
                     overtime_hours = :overtime_hours,
                     status = :status";
            
            $stmt = $db->prepare($query);
            $stmt->execute($attendance_data);

            $message = '<div class="alert alert-success">Attendance marked successfully!</div>';

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error marking attendance: ' . $e->getMessage() . '</div>';
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
    <h1 class="h3 mb-0 text-gray-800">Mark Attendance</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Attendance
    </a>
</div>

<?php echo $message; ?>

<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Attendance Information</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select class="form-control" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" 
                                <?php echo ($_POST['employee_id'] ?? '') == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Project <span class="text-danger">*</span></label>
                        <select class="form-control" name="project_id" required>
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['project_id']; ?>" 
                                <?php echo ($_POST['project_id'] ?? '') == $proj['project_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proj['project_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="attendance_date" 
                               value="<?php echo htmlspecialchars($_POST['attendance_date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Check In Time</label>
                        <input type="time" class="form-control" name="check_in_time" 
                               value="<?php echo htmlspecialchars($_POST['check_in_time'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Check Out Time</label>
                        <input type="time" class="form-control" name="check_out_time" 
                               value="<?php echo htmlspecialchars($_POST['check_out_time'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Overtime Hours</label>
                        <input type="number" class="form-control" name="overtime_hours" step="0.5" min="0"
                               value="<?php echo htmlspecialchars($_POST['overtime_hours'] ?? 0); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-control" name="status" required>
                            <option value="">Select Status</option>
                            <option value="present" <?php echo ($_POST['status'] ?? '') == 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="absent" <?php echo ($_POST['status'] ?? '') == 'absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="late" <?php echo ($_POST['status'] ?? '') == 'late' ? 'selected' : ''; ?>>Late</option>
                            <option value="half_day" <?php echo ($_POST['status'] ?? '') == 'half_day' ? 'selected' : ''; ?>>Half Day</option>
                            <option value="leave" <?php echo ($_POST['status'] ?? '') == 'leave' ? 'selected' : ''; ?>>Leave</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check me-2"></i>Mark Attendance
                </button>
                <button type="reset" class="btn btn-secondary">Reset</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>