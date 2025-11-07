<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('hr_manager');

$page_title = "Edit Employee";
$body_class = "employees-page";

$employee_id = $_GET['id'] ?? 0;

if (!$employee_id) {
    header("Location: index.php");
    exit;
}

// Get employee data
$employee = [];
$departments = [];
$employee_types = [];

try {
    $query = "SELECT * FROM employees WHERE employee_id = :employee_id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header("Location: index.php");
        exit;
    }

    $dept_query = "SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    $type_query = "SELECT employee_type_id, type_name FROM employee_types WHERE is_active = 1 ORDER BY type_name";
    $type_stmt = $db->prepare($type_query);
    $type_stmt->execute();
    $employee_types = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Edit employee error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading employee data.</div>';
}

$message = '';

// Handle form submission
if ($_POST) {
    $update_data = [
        'title' => sanitizeInput($_POST['title']),
        'first_name' => sanitizeInput($_POST['first_name']),
        'middle_name' => sanitizeInput($_POST['middle_name']),
        'last_name' => sanitizeInput($_POST['last_name']),
        'gender' => sanitizeInput($_POST['gender']),
        'date_of_birth' => sanitizeInput($_POST['date_of_birth']),
        'marital_status' => sanitizeInput($_POST['marital_status']),
        'email' => sanitizeInput($_POST['email']),
        'phone_number' => sanitizeInput($_POST['phone_number']),
        'alternate_phone' => sanitizeInput($_POST['alternate_phone']),
        'residential_address' => sanitizeInput($_POST['residential_address']),
        'state_of_origin' => sanitizeInput($_POST['state_of_origin']),
        'lga_of_origin' => sanitizeInput($_POST['lga_of_origin']),
        'employee_type_id' => (int)$_POST['employee_type_id'],
        'department_id' => (int)$_POST['department_id'],
        'employment_date' => sanitizeInput($_POST['employment_date']),
        'confirmation_date' => sanitizeInput($_POST['confirmation_date']),
        'bank_name' => sanitizeInput($_POST['bank_name']),
        'account_number' => sanitizeInput($_POST['account_number']),
        'account_name' => sanitizeInput($_POST['account_name']),
        'bvn' => sanitizeInput($_POST['bvn']),
        'pension_pin' => sanitizeInput($_POST['pension_pin']),
        'status' => sanitizeInput($_POST['status'])
    ];

    $errors = [];

    // Validation
    if (empty($update_data['first_name'])) $errors[] = "First name is required.";
    if (empty($update_data['last_name'])) $errors[] = "Last name is required.";
    if (empty($update_data['email']) || !validateEmail($update_data['email'])) $errors[] = "Valid email is required.";
    if (empty($update_data['phone_number']) || !validatePhone($update_data['phone_number'])) $errors[] = "Valid phone number is required.";
    if (empty($update_data['date_of_birth'])) $errors[] = "Date of birth is required.";
    if (empty($update_data['employment_date'])) $errors[] = "Employment date is required.";

    if ($update_data['bvn'] && !validateBVN($update_data['bvn'])) {
        $errors[] = "BVN must be 11 digits.";
    }

    if (empty($errors)) {
        try {
            $query = "UPDATE employees SET 
                     title = :title,
                     first_name = :first_name,
                     middle_name = :middle_name,
                     last_name = :last_name,
                     gender = :gender,
                     date_of_birth = :date_of_birth,
                     marital_status = :marital_status,
                     email = :email,
                     phone_number = :phone_number,
                     alternate_phone = :alternate_phone,
                     residential_address = :residential_address,
                     state_of_origin = :state_of_origin,
                     lga_of_origin = :lga_of_origin,
                     employee_type_id = :employee_type_id,
                     department_id = :department_id,
                     employment_date = :employment_date,
                     confirmation_date = :confirmation_date,
                     bank_name = :bank_name,
                     account_number = :account_number,
                     account_name = :account_name,
                     bvn = :bvn,
                     pension_pin = :pension_pin,
                     status = :status
                     WHERE employee_id = :employee_id";
            
            $stmt = $db->prepare($query);
            $update_data['employee_id'] = $employee_id;
            $stmt->execute($update_data);

            $message = '<div class="alert alert-success">Employee updated successfully!</div>';

            // Refresh employee data
            $query = "SELECT * FROM employees WHERE employee_id = :employee_id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt->execute();
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error updating employee: ' . $e->getMessage() . '</div>';
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
    <h1 class="h3 mb-0 text-gray-800">Edit Employee</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to List
    </a>
</div>

<?php echo $message; ?>

<div class="card shadow">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Employee Information</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <!-- Form fields similar to add.php but with existing values -->
            <div class="row">
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <select class="form-control" name="title">
                            <option value="">Select</option>
                            <option value="Mr" <?php echo ($employee['title'] ?? '') == 'Mr' ? 'selected' : ''; ?>>Mr</option>
                            <option value="Mrs" <?php echo ($employee['title'] ?? '') == 'Mrs' ? 'selected' : ''; ?>>Mrs</option>
                            <option value="Miss" <?php echo ($employee['title'] ?? '') == 'Miss' ? 'selected' : ''; ?>>Miss</option>
                            <option value="Dr" <?php echo ($employee['title'] ?? '') == 'Dr' ? 'selected' : ''; ?>>Dr</option>
                            <option value="Prof" <?php echo ($employee['title'] ?? '') == 'Prof' ? 'selected' : ''; ?>>Prof</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" 
                               value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" 
                               value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                    </div>
                </div>
            </div>

            <!-- ... (other form fields) ... -->
             

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <option value="active" <?php echo ($employee['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($employee['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo ($employee['status'] ?? '') == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="terminated" <?php echo ($employee['status'] ?? '') == 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Update Employee
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>