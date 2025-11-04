<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('admin');

$page_title = "System Settings";
$body_class = "settings-page";

// Handle form submissions
$message = '';

if ($_POST) {
    if (isset($_POST['update_company'])) {
        try {
            $query = "UPDATE companies SET 
                     company_name = :company_name,
                     rc_number = :rc_number,
                     tax_identification_number = :tax_identification_number,
                     address = :address,
                     city = :city,
                     state = :state,
                     phone = :phone,
                     email = :email,
                     website = :website,
                     industry_type = :industry_type
                     WHERE company_id = 1";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':company_name' => sanitizeInput($_POST['company_name']),
                ':rc_number' => sanitizeInput($_POST['rc_number']),
                ':tax_identification_number' => sanitizeInput($_POST['tax_identification_number']),
                ':address' => sanitizeInput($_POST['address']),
                ':city' => sanitizeInput($_POST['city']),
                ':state' => sanitizeInput($_POST['state']),
                ':phone' => sanitizeInput($_POST['phone']),
                ':email' => sanitizeInput($_POST['email']),
                ':website' => sanitizeInput($_POST['website']),
                ':industry_type' => sanitizeInput($_POST['industry_type'])
            ]);
            
            $message = '<div class="alert alert-success">Company information updated successfully!</div>';
            
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error updating company information: ' . $e->getMessage() . '</div>';
        }
    }
    
    if (isset($_POST['update_tax'])) {
        try {
            $query = "INSERT INTO tax_configurations SET 
                     company_id = 1,
                     tax_year = :tax_year,
                     pension_employee_rate = :pension_employee_rate,
                     pension_employer_rate = :pension_employer_rate,
                     nhf_employee_rate = :nhf_employee_rate,
                     itf_rate = :itf_rate
                     ON DUPLICATE KEY UPDATE
                     pension_employee_rate = VALUES(pension_employee_rate),
                     pension_employer_rate = VALUES(pension_employer_rate),
                     nhf_employee_rate = VALUES(nhf_employee_rate),
                     itf_rate = VALUES(itf_rate)";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':tax_year' => sanitizeInput($_POST['tax_year']),
                ':pension_employee_rate' => sanitizeInput($_POST['pension_employee_rate']),
                ':pension_employer_rate' => sanitizeInput($_POST['pension_employer_rate']),
                ':nhf_employee_rate' => sanitizeInput($_POST['nhf_employee_rate']),
                ':itf_rate' => sanitizeInput($_POST['itf_rate'])
            ]);
            
            $message = '<div class="alert alert-success">Tax configuration updated successfully!</div>';
            
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error updating tax configuration: ' . $e->getMessage() . '</div>';
        }
    }
}

// Get current settings
$company = [];
$tax_config = [];
$system_stats = [];

try {
    $company_query = "SELECT * FROM companies WHERE company_id = 1";
    $company_stmt = $db->prepare($company_query);
    $company_stmt->execute();
    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);
    
    $tax_query = "SELECT * FROM tax_configurations WHERE company_id = 1 ORDER BY tax_year DESC LIMIT 1";
    $tax_stmt = $db->prepare($tax_query);
    $tax_stmt->execute();
    $tax_config = $tax_stmt->fetch(PDO::FETCH_ASSOC);
    
    // System statistics
    $stats_query = "SELECT 
                    (SELECT COUNT(*) FROM employees WHERE status = 'active') as total_employees,
                    (SELECT COUNT(*) FROM payroll_periods WHERE status = 'paid') as total_payrolls,
                    (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
                    (SELECT COUNT(*) FROM departments WHERE is_active = 1) as total_departments";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $system_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Settings error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">System Settings</h1>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-lg-3">
        <!-- Settings Menu -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Settings Menu</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="#company" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                        <i class="fas fa-building me-2"></i>Company Info
                    </a>
                    <a href="#tax" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <i class="fas fa-percent me-2"></i>Tax Configuration
                    </a>
                    <a href="#system" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <i class="fas fa-cog me-2"></i>System Settings
                    </a>
                    <a href="#backup" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <i class="fas fa-database me-2"></i>Backup & Restore
                    </a>
                </div>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">System Statistics</h6>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <div class="mb-3">
                        <div class="text-primary fw-bold" style="font-size: 2rem;">
                            <?php echo $system_stats['total_employees']; ?>
                        </div>
                        <small class="text-muted">Active Employees</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-success fw-bold" style="font-size: 2rem;">
                            <?php echo $system_stats['total_payrolls']; ?>
                        </div>
                        <small class="text-muted">Processed Payrolls</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-info fw-bold" style="font-size: 2rem;">
                            <?php echo $system_stats['total_users']; ?>
                        </div>
                        <small class="text-muted">System Users</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-warning fw-bold" style="font-size: 2rem;">
                            <?php echo $system_stats['total_departments']; ?>
                        </div>
                        <small class="text-muted">Departments</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="tab-content">
            <!-- Company Information Tab -->
            <div class="tab-pane fade show active" id="company">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Company Information</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Company Name *</label>
                                        <input type="text" class="form-control" name="company_name" 
                                               value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">RC Number</label>
                                        <input type="text" class="form-control" name="rc_number" 
                                               value="<?php echo htmlspecialchars($company['rc_number'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tax Identification Number</label>
                                        <input type="text" class="form-control" name="tax_identification_number" 
                                               value="<?php echo htmlspecialchars($company['tax_identification_number'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Industry Type *</label>
                                        <select class="form-control" name="industry_type" required>
                                            <option value="construction" <?php echo ($company['industry_type'] ?? '') == 'construction' ? 'selected' : ''; ?>>Construction</option>
                                            <option value="manufacturing" <?php echo ($company['industry_type'] ?? '') == 'manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
                                            <option value="other" <?php echo ($company['industry_type'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city" 
                                               value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">State</label>
                                        <input type="text" class="form-control" name="state" 
                                               value="<?php echo htmlspecialchars($company['state'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Country</label>
                                        <input type="text" class="form-control" value="Nigeria" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="url" class="form-control" name="website" 
                                               value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_company" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Company Information
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tax Configuration Tab -->
            <div class="tab-pane fade" id="tax">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Tax Configuration (Nigeria)</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Pension - Employee Rate (%)</label>
                                        <input type="number" class="form-control" name="pension_employee_rate" 
                                               value="<?php echo $tax_config['pension_employee_rate'] ?? 8.0; ?>" 
                                               step="0.01" min="0" max="100" required>
                                        <small class="form-text text-muted">Standard rate: 8% of basic salary</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Pension - Employer Rate (%)</label>
                                        <input type="number" class="form-control" name="pension_employer_rate" 
                                               value="<?php echo $tax_config['pension_employer_rate'] ?? 10.0; ?>" 
                                               step="0.01" min="0" max="100" required>
                                        <small class="form-text text-muted">Standard rate: 10% of basic salary</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">NHF - Employee Rate (%)</label>
                                        <input type="number" class="form-control" name="nhf_employee_rate" 
                                               value="<?php echo $tax_config['nhf_employee_rate'] ?? 2.5; ?>" 
                                               step="0.01" min="0" max="100" required>
                                        <small class="form-text text-muted">National Housing Fund contribution</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ITF Rate (%)</label>
                                        <input type="number" class="form-control" name="itf_rate" 
                                               value="<?php echo $tax_config['itf_rate'] ?? 1.0; ?>" 
                                               step="0.01" min="0" max="100">
                                        <small class="form-text text-muted">Industrial Training Fund (if applicable)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tax Year</label>
                                        <input type="number" class="form-control" name="tax_year" 
                                               value="<?php echo $tax_config['tax_year'] ?? date('Y'); ?>" 
                                               min="2020" max="2030" required>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_tax" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Tax Configuration
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h6>PAYE Tax Bands (Nigeria <?php echo date('Y'); ?>)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Annual Income (₦)</th>
                                        <th>Tax Rate</th>
                                        <th>Fixed Amount (₦)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>First 300,000</td>
                                        <td>7%</td>
                                        <td>0</td>
                                    </tr>
                                    <tr>
                                        <td>Next 300,000</td>
                                        <td>11%</td>
                                        <td>21,000</td>
                                    </tr>
                                    <tr>
                                        <td>Next 500,000</td>
                                        <td>15%</td>
                                        <td>54,000</td>
                                    </tr>
                                    <tr>
                                        <td>Next 500,000</td>
                                        <td>19%</td>
                                        <td>129,000</td>
                                    </tr>
                                    <tr>
                                        <td>Above 1,600,000</td>
                                        <td>21%</td>
                                        <td>224,000</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Settings Tab -->
            <div class="tab-pane fade" id="system">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">System Configuration</h6>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">System Name</label>
                                        <input type="text" class="form-control" value="Nigeria Payroll HR System" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Default Currency</label>
                                        <input type="text" class="form-control" value="NGN (Naira)" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Default Country</label>
                                        <input type="text" class="form-control" value="Nigeria" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">System Version</label>
                                        <input type="text" class="form-control" value="1.0.0" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">System Description</label>
                                <textarea class="form-control" rows="3" readonly>Comprehensive Payroll and Human Resources Management System for Nigerian Companies. Supports statutory compliance with PAYE, Pension, NHF, and ITF deductions.</textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Database Version</label>
                                <input type="text" class="form-control" value="MySQL 8.0" readonly>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                System configuration is managed by administrators. Contact system administrator for changes.
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Backup & Restore Tab -->
            <div class="tab-pane fade" id="backup">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Backup & Restore</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-left-primary">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-download me-2 text-primary"></i>Backup Database
                                        </h5>
                                        <p class="card-text">Create a complete backup of your payroll system database.</p>
                                        <button class="btn btn-primary" onclick="createBackup()">
                                            <i class="fas fa-database me-2"></i>Create Backup
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-left-warning">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-upload me-2 text-warning"></i>Restore Database
                                        </h5>
                                        <p class="card-text">Restore system from a previous backup file.</p>
                                        <div class="input-group">
                                            <input type="file" class="form-control" id="backupFile" accept=".sql,.backup">
                                            <button class="btn btn-warning" onclick="restoreBackup()">
                                                <i class="fas fa-upload me-2"></i>Restore
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h6>Recent Backups</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Backup Date</th>
                                        <th>File Size</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            No backup files found
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Regular backups are essential for data protection. 
                            We recommend creating backups before major system updates or payroll processing.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function createBackup() {
    if (confirm('Create a complete database backup? This may take a few minutes.')) {
        // This would typically call a backup script
        alert('Backup feature would be implemented here. In production, this would generate a SQL dump file.');
    }
}

function restoreBackup() {
    const fileInput = document.getElementById('backupFile');
    if (!fileInput.files.length) {
        alert('Please select a backup file first.');
        return;
    }
    
    if (confirm('WARNING: This will replace all current data with the backup. Continue?')) {
        // This would typically handle file upload and restoration
        alert('Restore feature would be implemented here. This is a critical operation that requires proper validation.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>