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

    // Fetch bank list for dropdown
    $bankList = getBankList($db);

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
        'bank_id' => (int)$_POST['bank_id'],
        'account_number' => sanitizeInput($_POST['account_number']),
        'account_name' => sanitizeInput($_POST['account_name']),
        'bvn' => sanitizeInput($_POST['bvn']),
        'pension_pin' => sanitizeInput($_POST['pension_pin']),
        'tax_id' => sanitizeInput($_POST['tax_id'] ?? ''),
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
                     bank_id = :bank_id,
                     account_number = :account_number,
                     account_name = :account_name,
                     bvn = :bvn,
                     pension_pin = :pension_pin,
                     tax_id = :tax_id,
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

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="middle_name" 
                               value="<?php echo htmlspecialchars($employee['middle_name']); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select class="form-control" name="gender">
                            <option value="">Select</option>
                            <option value="male" <?php echo ($employee['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($employee['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($employee['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Marital Status</label>
                        <select class="form-control" name="marital_status">
                            <option value="">Select</option>
                            <option value="single" <?php echo ($employee['marital_status'] ?? '') == 'single' ? 'selected' : ''; ?>>Single</option>
                            <option value="married" <?php echo ($employee['marital_status'] ?? '') == 'married' ? 'selected' : ''; ?>>Married</option>
                            <option value="divorced" <?php echo ($employee['marital_status'] ?? '') == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                            <option value="widowed" <?php echo ($employee['marital_status'] ?? '') == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_of_birth" 
                               value="<?php echo htmlspecialchars($employee['date_of_birth']); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Employment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="employment_date" 
                               value="<?php echo htmlspecialchars($employee['employment_date']); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Confirmation Date</label>
                        <input type="date" class="form-control" name="confirmation_date" 
                               value="<?php echo htmlspecialchars($employee['confirmation_date']); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="phone_number" 
                               value="<?php echo htmlspecialchars($employee['phone_number']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Alternate Phone</label>
                        <input type="text" class="form-control" name="alternate_phone" 
                               value="<?php echo htmlspecialchars($employee['alternate_phone']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Residential Address</label>
                        <input type="text" class="form-control" name="residential_address" 
                               value="<?php echo htmlspecialchars($employee['residential_address']); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">State of Origin</label>
                        <input type="text" class="form-control" name="state_of_origin" 
                               value="<?php echo htmlspecialchars($employee['state_of_origin']); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">LGA of Origin</label>
                        <input type="text" class="form-control" name="lga_of_origin" 
                               value="<?php echo htmlspecialchars($employee['lga_of_origin']); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Employee Type</label>
                        <select class="form-control" name="employee_type_id">
                            <option value="">Select Employee Type</option>
                            <?php foreach ($employee_types as $type): ?>
                                <option value="<?php echo $type['employee_type_id']; ?>" <?php echo ($employee['employee_type_id'] ?? '') == $type['employee_type_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-control" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo $department['department_id']; ?>" <?php echo ($employee['department_id'] ?? '') == $department['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($department['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Bank</label>
                        <select class="form-control" name="bank_id" required>
                            <option value="">Select Bank</option>
                            <?php foreach ($bankList as $bank): ?>
                                <option value="<?php echo $bank['id']; ?>" <?php echo ($employee['bank_id'] ?? '') == $bank['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bank['bank_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-control" name="account_number" 
                               value="<?php echo htmlspecialchars($employee['account_number']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input type="text" class="form-control" name="account_name" 
                               value="<?php echo htmlspecialchars($employee['account_name']); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">BVN</label>
                        <input type="text" class="form-control" name="bvn" maxlength="11"
                               value="<?php echo htmlspecialchars($employee['bvn']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Pension PIN</label>
                        <input type="text" class="form-control" name="pension_pin" 
                               value="<?php echo htmlspecialchars($employee['pension_pin']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Tax ID</label>
                        <input type="text" class="form-control" name="tax_id" 
                               value="<?php echo htmlspecialchars($employee['tax_id'] ?? ''); ?>">
                    </div>
                </div>
            </div>

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