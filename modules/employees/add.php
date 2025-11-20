<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php'; 
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();   
$auth = new Auth($db);
$bankList = getBankList($db);

$auth->requirePermission('hr_manager');

$page_title = "Add Employee";
$body_class = "employees-page";

// Get data for dropdowns
$departments = [];
$employee_types = [];
$companies = [];

try {
    $dept_query = "SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    $type_query = "SELECT employee_type_id, type_name FROM employee_types WHERE is_active = 1 ORDER BY type_name";
    $type_stmt = $db->prepare($type_query);
    $type_stmt->execute();
    $employee_types = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

    $company_query = "SELECT company_id, company_name FROM companies ORDER BY company_name";
    $company_stmt = $db->prepare($company_query);
    $company_stmt->execute();
    $companies = $company_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Form data error: " . $e->getMessage());
}

$message = '';
$errors = [];

// Handle form submission
if ($_POST) {
    $employee_data = [
        'employee_code' => sanitizeInput($_POST['employee_code']),
        'company_id' => (int)$_POST['company_id'],
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
        'create_user_account' => isset($_POST['create_user_account']),
        'username' => sanitizeInput($_POST['username']),
        'user_type' => sanitizeInput($_POST['user_type']),
        'manual_basic_salary' => isset($_POST['manual_basic_salary']) ? (float)$_POST['manual_basic_salary'] : null
    ];

    // Validation
    if (empty($employee_data['first_name'])) $errors[] = "First name is required.";
    if (empty($employee_data['last_name'])) $errors[] = "Last name is required.";
    if (empty($employee_data['email']) || !validateEmail($employee_data['email'])) $errors[] = "Valid email is required.";
    if (empty($employee_data['phone_number']) || !validatePhone($employee_data['phone_number'])) $errors[] = "Valid phone number is required.";
    if (empty($employee_data['employee_code'])) $errors[] = "Employee code is required.";
    if (empty($employee_data['date_of_birth'])) $errors[] = "Date of birth is required.";
    if (empty($employee_data['employment_date'])) $errors[] = "Employment date is required.";

    if ($employee_data['bvn'] && !validateBVN($employee_data['bvn'])) {
        $errors[] = "BVN must be 11 digits.";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Insert employee
            $query = "INSERT INTO employees SET 
                         employee_code = :employee_code,
                         company_id = :company_id,
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
                         status = 'active'";
            
            $stmt = $db->prepare($query);
            
            // 💡 FIX: Create a dedicated array for binding that only includes the necessary parameters
            $employee_bind_data = [
                ':employee_code' => $employee_data['employee_code'],
                ':company_id' => $employee_data['company_id'],
                ':title' => $employee_data['title'],
                ':first_name' => $employee_data['first_name'],
                ':middle_name' => $employee_data['middle_name'],
                ':last_name' => $employee_data['last_name'],
                ':gender' => $employee_data['gender'],
                ':date_of_birth' => $employee_data['date_of_birth'],
                ':marital_status' => $employee_data['marital_status'],
                ':email' => $employee_data['email'],
                ':phone_number' => $employee_data['phone_number'],
                ':alternate_phone' => $employee_data['alternate_phone'],
                ':residential_address' => $employee_data['residential_address'],
                ':state_of_origin' => $employee_data['state_of_origin'],
                ':lga_of_origin' => $employee_data['lga_of_origin'],
                ':employee_type_id' => $employee_data['employee_type_id'],
                ':department_id' => $employee_data['department_id'],
                ':employment_date' => $employee_data['employment_date'],
                ':confirmation_date' => $employee_data['confirmation_date'],
                ':bank_id' => $employee_data['bank_id'],
                ':account_number' => $employee_data['account_number'],
                ':account_name' => $employee_data['account_name'],
                ':bvn' => $employee_data['bvn'],
                ':pension_pin' => $employee_data['pension_pin'],
            ];
            
            $stmt->execute($employee_bind_data); // Execute with the clean array
            $employee_id = $db->lastInsertId();

            // Create user account if requested
            if ($employee_data['create_user_account'] && $employee_data['username']) {
                $password_hash = password_hash('password123', PASSWORD_DEFAULT);
                
                $user_query = "INSERT INTO users SET 
                                 employee_id = :employee_id,
                                 username = :username,
                                 email = :email,
                                 password_hash = :password_hash,
                                 user_type = :user_type,
                                 is_active = 1";
                
                $user_stmt = $db->prepare($user_query);
                $user_stmt->execute([
                    ':employee_id' => $employee_id,
                    ':username' => $employee_data['username'],
                    ':email' => $employee_data['email'],
                    ':password_hash' => $password_hash,
                    ':user_type' => $employee_data['user_type'] ?: 'employee'
                ]);
            }

            // Save salary information based on employee type
            // Get the employee type name
            $type_stmt = $db->prepare("SELECT type_name FROM employee_types WHERE employee_type_id = ?");
            $type_stmt->execute([$employee_data['employee_type_id']]);
            $type_row = $type_stmt->fetch(PDO::FETCH_ASSOC);
            $type_name = strtolower($type_row['type_name'] ?? '');

            if ($type_name === 'monthly paid' && !empty($_POST['basic_salary'])) {
                // Auto-calculate and save all components for monthly-paid
                try {
                    $structureQuery = "INSERT INTO employee_salary_structure 
                                     (employee_id, component_id, amount, currency, effective_date, is_active)
                                     SELECT :employee_id, component_id, :amount, 'NGN', CURDATE(), 1
                                     FROM salary_components 
                                     WHERE component_code = :component_code";
                    $components = [
                        ['code' => 'BASIC', 'amount' => (float)$_POST['basic_salary']],
                        ['code' => 'HOUSE', 'amount' => (float)$_POST['housing_allowance']],
                        ['code' => 'TRANS', 'amount' => (float)$_POST['transport_allowance']],
                        ['code' => 'UTIL', 'amount' => (float)$_POST['utility_allowance']],
                        ['code' => 'MEAL', 'amount' => (float)$_POST['meal_allowance']],
                        ['code' => 'PEN', 'amount' => (float)$_POST['pension']],
                        ['code' => 'TAX', 'amount' => (float)$_POST['tax']]
                    ];
                    foreach ($components as $component) {
                        $structureStmt = $db->prepare($structureQuery);
                        $structureStmt->execute([
                            ':employee_id' => $employee_id,
                            ':amount' => $component['amount'],
                            ':component_code' => $component['code']
                        ]);
                    }
                    error_log("Saved salary structure for employee ID: " . $employee_id);
                } catch (PDOException $e) {
                    error_log("Error saving salary information: " . $e->getMessage());
                }
            } elseif ($type_name !== 'monthly paid' && !empty($_POST['manual_basic_salary'])) {
                // For non-monthly, save only the manually entered basic salary
                try {
                    $structureQuery = "INSERT INTO employee_salary_structure 
                                     (employee_id, component_id, amount, currency, effective_date, is_active)
                                     SELECT :employee_id, component_id, :amount, 'NGN', CURDATE(), 1
                                     FROM salary_components 
                                     WHERE component_code = 'BASIC'";
                    $structureStmt = $db->prepare($structureQuery);
                    $structureStmt->execute([
                        ':employee_id' => $employee_id,
                        ':amount' => (float)$_POST['manual_basic_salary']
                    ]);
                    error_log("Saved manual basic salary for employee ID: " . $employee_id);
                } catch (PDOException $e) {
                    error_log("Error saving manual salary: " . $e->getMessage());
                }
            }
            
            $db->commit();

            $message = '<div class="alert alert-success">Employee added successfully! 
                         <a href="view.php?id=' . $employee_id . '" class="alert-link">View Employee</a></div>';

            // Clear form data
            $_POST = [];

        } catch (PDOException $e) {
            $db->rollBack();
            // The $e->getMessage() now correctly prints the error if a *different* PDO error occurs
            $message = '<div class="alert alert-danger">Error adding employee: ' . $e->getMessage() . '</div>';
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
    <h1 class="h3 mb-0 text-gray-800">Add New Employee</h1>
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
        <form method="POST" id="employeeForm">
            <h5 class="mb-3 text-primary">Personal Information</h5>
            <div class="row">
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <select class="form-control" name="title">
                            <option value="">Select</option>
                            <option value="Mr" <?php echo ($_POST['title'] ?? '') == 'Mr' ? 'selected' : ''; ?>>Mr</option>
                            <option value="Mrs" <?php echo ($_POST['title'] ?? '') == 'Mrs' ? 'selected' : ''; ?>>Mrs</option>
                            <option value="Miss" <?php echo ($_POST['title'] ?? '') == 'Miss' ? 'selected' : ''; ?>>Miss</option>
                            <option value="Dr" <?php echo ($_POST['title'] ?? '') == 'Dr' ? 'selected' : ''; ?>>Dr</option>
                            <option value="Prof" <?php echo ($_POST['title'] ?? '') == 'Prof' ? 'selected' : ''; ?>>Prof</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="middle_name" 
                               value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <select class="form-control" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_of_birth" 
                               value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Marital Status</label>
                        <select class="form-control" name="marital_status">
                            <option value="">Select</option>
                            <option value="Single" <?php echo ($_POST['marital_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                            <option value="Married" <?php echo ($_POST['marital_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                            <option value="Divorced" <?php echo ($_POST['marital_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            <option value="Widowed" <?php echo ($_POST['marital_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                </div>
            </div>

            <h5 class="mb-3 mt-4 text-primary">Contact Information</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" name="phone_number" 
                               value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Alternate Phone</label>
                        <input type="tel" class="form-control" name="alternate_phone" 
                               value="<?php echo htmlspecialchars($_POST['alternate_phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">State of Origin</label>
                        <select class="form-control" name="state_of_origin">
                            <option value="">Select State</option>
                            <?php foreach (getNigerianStates() as $state): ?>
                            <option value="<?php echo $state; ?>" 
                                <?php echo ($_POST['state_of_origin'] ?? '') == $state ? 'selected' : ''; ?>>
                                <?php echo $state; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Residential Address</label>
                <textarea class="form-control" name="residential_address" rows="3"><?php echo htmlspecialchars($_POST['residential_address'] ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">LGA of Origin</label>
                        <input type="text" class="form-control" name="lga_of_origin" 
                               value="<?php echo htmlspecialchars($_POST['lga_of_origin'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <h5 class="mb-3 mt-4 text-primary">Employment Information</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Employee Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="employee_code" 
                               value="<?php echo htmlspecialchars($_POST['employee_code'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Company <span class="text-danger">*</span></label>
                        <select class="form-control" name="company_id" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['company_id']; ?>" 
                                <?php echo ($_POST['company_id'] ?? '') == $company['company_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-control" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo ($_POST['department_id'] ?? '') == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Employee Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="employee_type_id" required>
                            <option value="">Select Type</option>
                            <?php foreach ($employee_types as $type): ?>
                            <option value="<?php echo $type['employee_type_id']; ?>" 
                                <?php echo ($_POST['employee_type_id'] ?? '') == $type['employee_type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Employment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="employment_date" 
                               value="<?php echo htmlspecialchars($_POST['employment_date'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Confirmation Date</label>
                        <input type="date" class="form-control" name="confirmation_date" 
                               value="<?php echo htmlspecialchars($_POST['confirmation_date'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <h5 class="mb-3 mt-4 text-primary">Bank Information</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Bank Name</label>
                        <select class="form-control" name="bank_id" required>
                            <option value="">Select Bank</option>
                            <?php foreach ($bankList as $bank): ?>
                                <option value="<?php echo $bank['id']; ?>" 
                                    <?php echo (($_POST['bank_id'] ?? '') == $bank['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bank['bank_name']); ?> 
                                    (<?php echo htmlspecialchars($bank['bank_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-control" name="account_number" 
                               value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input type="text" class="form-control" name="account_name" 
                               value="<?php echo htmlspecialchars($_POST['account_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">BVN</label>
                        <input type="text" class="form-control" name="bvn" maxlength="11"
                               value="<?php echo htmlspecialchars($_POST['bvn'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Pension PIN</label>
                        <input type="text" class="form-control" name="pension_pin" 
                               value="<?php echo htmlspecialchars($_POST['pension_pin'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <h5 class="mb-3 mt-4 text-primary">System Account</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_user_account" id="createUserAccount"
                                <?php echo isset($_POST['create_user_account']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="createUserAccount">
                                Create system user account
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div id="userAccountFields" style="display: <?php echo isset($_POST['create_user_account']) ? 'block' : 'none'; ?>;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" 
                                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">User Type</label>
                            <select class="form-control" name="user_type">
                                <option value="employee">Employee</option>
                                <option value="project_manager">Project Manager</option>
                                <option value="hr_manager">HR Manager</option>
                                <option value="payroll_master">Payroll Master</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info">
                </div>
            </div>

            <!-- Salary Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <h5 class="mb-3 text-primary">Salary Information</h5>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div id="monthlySalarySection">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="annual_gross_salary">Annual Gross Salary (₦)</label>
                                            <input type="number" class="form-control" id="annual_gross_salary" 
                                                   name="annual_gross_salary" step="0.01" min="0" 
                                                   onchange="calculateSalary()">
                                        </div>
                                    </div>
                                </div>
                                <div id="salary_breakdown" style="display: none;">
                                    <h6 class="mt-4 mb-3">Salary Breakdown (Monthly)</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Basic Salary:</strong> <span id="basic_salary">₦0.00</span></p>
                                            <p><strong>Housing Allowance:</strong> <span id="housing_allowance">₦0.00</span></p>
                                            <p><strong>Transport Allowance:</strong> <span id="transport_allowance">₦0.00</span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Utility Allowance:</strong> <span id="utility_allowance">₦0.00</span></p>
                                            <p><strong>Meal Allowance:</strong> <span id="meal_allowance">₦0.00</span></p>
                                        </div>
                                    </div>
                                    <h6 class="mt-4 mb-3">Deductions (Monthly)</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Pension (8%):</strong> <span id="pension">₦0.00</span></p>
                                            <p><strong>PAYE Tax:</strong> <span id="tax">₦0.00</span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Total Deductions:</strong> <span id="total_deductions">₦0.00</span></p>
                                            <p class="h5"><strong>Net Salary:</strong> <span id="net_salary" class="text-success">₦0.00</span></p>
                                        </div>
                                    </div>
                                    <!-- Hidden fields to store calculated values -->
                                    <input type="hidden" name="basic_salary" id="basic_salary_input">
                                    <input type="hidden" name="housing_allowance" id="housing_allowance_input">
                                    <input type="hidden" name="transport_allowance" id="transport_allowance_input">
                                    <input type="hidden" name="utility_allowance" id="utility_allowance_input">
                                    <input type="hidden" name="meal_allowance" id="meal_allowance_input">
                                    <input type="hidden" name="pension" id="pension_input">
                                    <input type="hidden" name="tax" id="tax_input">
                                    <input type="hidden" name="net_salary" id="net_salary_input">
                                </div>
                            </div>
                            <div id="manualSalarySection" style="display:none;">
                                <div class="form-group">
                                    <label for="manual_basic_salary">Basic Salary (₦)</label>
                                    <input type="number" class="form-control" id="manual_basic_salary" name="manual_basic_salary" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Add Employee
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('createUserAccount').addEventListener('change', function() {
    document.getElementById('userAccountFields').style.display = this.checked ? 'block' : 'none';
});

// Show/hide salary sections based on employee type
const employeeTypeSelect = document.querySelector('select[name="employee_type_id"]');
function toggleSalarySection() {
    const selectedType = employeeTypeSelect.options[employeeTypeSelect.selectedIndex]?.text?.toLowerCase() || '';
    if (selectedType === 'monthly paid') {
        document.getElementById('monthlySalarySection').style.display = 'block';
        document.getElementById('manualSalarySection').style.display = 'none';
        document.getElementById('annual_gross_salary').required = true;
        document.getElementById('manual_basic_salary').required = false;
    } else {
        document.getElementById('monthlySalarySection').style.display = 'none';
        document.getElementById('manualSalarySection').style.display = 'block';
        document.getElementById('annual_gross_salary').required = false;
        document.getElementById('manual_basic_salary').required = true;
    }
}
employeeTypeSelect.addEventListener('change', toggleSalarySection);
window.addEventListener('DOMContentLoaded', toggleSalarySection);

// Auto-generate employee code based on employment date
document.querySelector('input[name="employment_date"]').addEventListener('change', function() {
    const employmentDate = new Date(this.value);
    if (!isNaN(employmentDate.getTime())) { // Check if date is valid
        // Get last 2 digits of year
        const year = employmentDate.getFullYear().toString().substr(-2);
        // Get month (add 1 because getMonth() is 0-indexed) and pad with leading zero
        const month = (employmentDate.getMonth() + 1).toString().padStart(2, '0');
        
        // Get the next sequential number for this month
        fetch(`../../api/employees/get_employee_count.php?year=${employmentDate.getFullYear()}&month=${employmentDate.getMonth() + 1}`)
            .then(response => response.json())
            .then(data => {
                const sequence = (data.count + 1).toString().padStart(2, '0');
                document.querySelector('input[name="employee_code"]').value = `${year}${month}${sequence}`;
            })
            .catch(error => {
                console.error('Error fetching employee count:', error);
                // Fallback to just the year and month if there's an error
                document.querySelector('input[name="employee_code"]').value = `${year}${month}01`;
            });
    }
});


function formatNaira(amount) {
    return '₦' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function calculateNigerianPAYE(annualGrossSalary) {
    const MONTHS_IN_YEAR = 12;
    const monthlyGrossSalary = annualGrossSalary / MONTHS_IN_YEAR;
    const BASIC_PERCENT = 0.6665;
    const HOUSING_PERCENT = 0.1875;
    const TRANSPORT_PERCENT = 0.0800;
    const UTILITY_PERCENT = 0.0375;
    const MEAL_PERCENT = 0.0285;
    const basicMonthly = monthlyGrossSalary * BASIC_PERCENT;
    const housingMonthly = monthlyGrossSalary * HOUSING_PERCENT;
    const transportMonthly = monthlyGrossSalary * TRANSPORT_PERCENT;
    const utilityMonthly = monthlyGrossSalary * UTILITY_PERCENT;
    const mealMonthly = monthlyGrossSalary * MEAL_PERCENT;
    const pensionBasisMonthly = basicMonthly + housingMonthly + transportMonthly;
    const pensionEmployeeMonthly = pensionBasisMonthly * 0.08;
    const pensionAnnual = pensionEmployeeMonthly * MONTHS_IN_YEAR;
    const nhfEmployeeMonthly = 0.00; 
    const nhfAnnual = 0.00;
    const onePercentGross = annualGrossSalary * 0.01;
    const craFixed = Math.max(200000, onePercentGross);
    const craPercentage = annualGrossSalary * 0.20;
    const consolidatedReliefAllowance = craFixed + craPercentage;
    let taxableIncomeAnnual = (
        annualGrossSalary - pensionAnnual - consolidatedReliefAllowance
    );
    taxableIncomeAnnual = Math.max(0, taxableIncomeAnnual);
    let payeAnnual = 0;
    let remainingTaxable = taxableIncomeAnnual;
    const brackets = [
        { limit: 300000, rate: 0.07 },
        { limit: 300000, rate: 0.11 },
        { limit: 500000, rate: 0.15 },
        { limit: 500000, rate: 0.19 },
        { limit: 1600000, rate: 0.21 },
        { limit: Infinity, rate: 0.24 }
    ];
    for (const bracket of brackets) {
        if (remainingTaxable <= 0) break;
        const chargeable = Math.min(remainingTaxable, bracket.limit);
        payeAnnual += chargeable * bracket.rate;
        remainingTaxable -= chargeable;
    }
    const payeMonthly = payeAnnual / MONTHS_IN_YEAR;
    const totalDeductionsMonthly = pensionEmployeeMonthly + nhfEmployeeMonthly + payeMonthly;
    return {
        monthly: {
            gross: monthlyGrossSalary,
            basic: basicMonthly,
            housing: housingMonthly,
            transport: transportMonthly,
            utility: utilityMonthly,
            meal: mealMonthly,
            pension: pensionEmployeeMonthly,
            nhf: nhfEmployeeMonthly,
            tax: payeMonthly,
            totalDeductions: totalDeductionsMonthly,
            netPay: monthlyGrossSalary - totalDeductionsMonthly
        }
    };
}

function calculateSalary() {
    const annualGross = parseFloat(document.getElementById('annual_gross_salary').value);
    if (isNaN(annualGross) || annualGross <= 0) {
        document.getElementById('salary_breakdown').style.display = 'none';
        return;
    }
    const result = calculateNigerianPAYE(annualGross);
    const monthly = result.monthly;
    document.getElementById('basic_salary').textContent = formatNaira(monthly.basic);
    document.getElementById('housing_allowance').textContent = formatNaira(monthly.housing);
    document.getElementById('transport_allowance').textContent = formatNaira(monthly.transport);
    document.getElementById('utility_allowance').textContent = formatNaira(monthly.utility);
    document.getElementById('meal_allowance').textContent = formatNaira(monthly.meal);
    document.getElementById('pension').textContent = formatNaira(monthly.pension);
    document.getElementById('tax').textContent = formatNaira(monthly.tax);
    document.getElementById('total_deductions').textContent = formatNaira(monthly.totalDeductions);
    document.getElementById('net_salary').textContent = formatNaira(monthly.netPay);
    document.getElementById('basic_salary_input').value = monthly.basic.toFixed(2);
    document.getElementById('housing_allowance_input').value = monthly.housing.toFixed(2);
    document.getElementById('transport_allowance_input').value = monthly.transport.toFixed(2);
    document.getElementById('utility_allowance_input').value = monthly.utility.toFixed(2);
    document.getElementById('meal_allowance_input').value = monthly.meal.toFixed(2);
    document.getElementById('pension_input').value = monthly.pension.toFixed(2);
    document.getElementById('tax_input').value = monthly.tax.toFixed(2);
    document.getElementById('net_salary_input').value = monthly.netPay.toFixed(2);
    document.getElementById('salary_breakdown').style.display = 'block';
}

document.querySelector('form').addEventListener('submit', function(e) {
    if (document.getElementById('annual_gross_salary').value && document.getElementById('monthlySalarySection').style.display !== 'none') {
        calculateSalary();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>