-- Companies table
CREATE TABLE companies (
    company_id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    rc_number VARCHAR(50) UNIQUE,
    tax_identification_number VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Nigeria',
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(255),
    industry_type ENUM('construction', 'manufacturing', 'other') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Departments table
CREATE TABLE departments (
    department_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    department_name VARCHAR(255) NOT NULL,
    department_code VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Employee Types table
CREATE TABLE employee_types (
    employee_type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL,
    description TEXT,
    payment_frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- ==================== EMPLOYEE MANAGEMENT ====================

-- Employees master table
CREATE TABLE employees (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_code VARCHAR(50) UNIQUE NOT NULL,
    company_id INT NOT NULL,
    title ENUM('Mr', 'Mrs', 'Miss', 'Dr', 'Prof') NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    date_of_birth DATE NOT NULL,
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone_number VARCHAR(20),
    alternate_phone VARCHAR(20),
    residential_address TEXT,
    state_of_origin VARCHAR(100),
    lga_of_origin VARCHAR(100),
    nationality VARCHAR(100) DEFAULT 'Nigerian',
    employee_type_id INT NOT NULL,
    department_id INT NOT NULL,
    employment_date DATE NOT NULL,
    confirmation_date DATE,
    status ENUM('active', 'inactive', 'suspended', 'terminated') DEFAULT 'active',
    bank_name VARCHAR(255),
    account_number VARCHAR(20),
    account_name VARCHAR(255),
    bvn VARCHAR(11),
    pension_pin VARCHAR(50),
    insurance_number VARCHAR(50),
    tax_state VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (employee_type_id) REFERENCES employee_types(employee_type_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

-- Expatriate employees extension
CREATE TABLE expatriate_employees (
    expatriate_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT UNIQUE NOT NULL,
    passport_number VARCHAR(50) NOT NULL,
    passport_expiry DATE NOT NULL,
    country_of_origin VARCHAR(100) NOT NULL,
    work_permit_number VARCHAR(100),
    work_permit_expiry DATE,
    residential_permit_number VARCHAR(100),
    residential_permit_expiry DATE,
    base_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    conversion_rate DECIMAL(10,4) NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- ==================== PROJECT/SITE MANAGEMENT ====================

-- Construction sites/Projects table
CREATE TABLE projects (
    project_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    project_code VARCHAR(50) UNIQUE NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    project_type ENUM('construction', 'manufacturing', 'other') NOT NULL,
    location_address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    start_date DATE,
    expected_end_date DATE,
    actual_end_date DATE,
    project_status ENUM('planned', 'ongoing', 'completed', 'on_hold') DEFAULT 'planned',
    project_budget DECIMAL(15,2),
    project_manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (project_manager_id) REFERENCES employees(employee_id)
);

-- Employee project assignments with different wage rates
CREATE TABLE employee_project_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    project_id INT NOT NULL,
    assignment_start_date DATE NOT NULL,
    assignment_end_date DATE,
    base_wage_rate DECIMAL(12,2) NOT NULL,
    wage_currency VARCHAR(3) DEFAULT 'NGN',
    overtime_rate DECIMAL(5,2) DEFAULT 1.5,
    hazard_allowance DECIMAL(10,2) DEFAULT 0,
    site_allowance DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    UNIQUE KEY unique_active_assignment (employee_id, is_active)
);

-- ==================== SALARY STRUCTURE ====================

-- Salary components table
CREATE TABLE salary_components (
    component_id INT PRIMARY KEY AUTO_INCREMENT,
    component_name VARCHAR(255) NOT NULL,
    component_type ENUM('earning', 'deduction', 'allowance') NOT NULL,
    component_code VARCHAR(50) UNIQUE NOT NULL,
    is_taxable BOOLEAN DEFAULT FALSE,
    is_statutory BOOLEAN DEFAULT FALSE,
    calculation_type ENUM('fixed', 'percentage', 'variable') DEFAULT 'fixed',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE
);

-- Employee salary structure
CREATE TABLE employee_salary_structure (
    salary_structure_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    component_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'NGN',
    effective_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (component_id) REFERENCES salary_components(component_id)
);

-- ==================== LOAN AND ADVANCE MANAGEMENT ====================

-- Salary advances table
CREATE TABLE salary_advances (
    advance_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    advance_amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'NGN',
    request_date DATE NOT NULL,
    approval_date DATE,
    repayment_start_date DATE,
    repayment_period_months INT DEFAULT 1,
    monthly_repayment_amount DECIMAL(10,2),
    total_repayment_amount DECIMAL(12,2),
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    reason TEXT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id)
);

-- Loan types table
CREATE TABLE loan_types (
    loan_type_id INT PRIMARY KEY AUTO_INCREMENT,
    loan_name VARCHAR(255) NOT NULL,
    description TEXT,
    interest_rate DECIMAL(5,2) NOT NULL,
    max_amount DECIMAL(12,2),
    max_tenure_months INT,
    is_active BOOLEAN DEFAULT TRUE
);

-- Employee loans table
CREATE TABLE employee_loans (
    loan_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    loan_type_id INT NOT NULL,
    loan_amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'NGN',
    application_date DATE NOT NULL,
    approval_date DATE,
    disbursement_date DATE,
    interest_rate DECIMAL(5,2) NOT NULL,
    tenure_months INT NOT NULL,
    monthly_repayment DECIMAL(10,2) NOT NULL,
    total_repayable_amount DECIMAL(12,2) NOT NULL,
    start_repayment_date DATE,
    end_repayment_date DATE,
    remaining_balance DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'approved', 'disbursed', 'active', 'completed', 'defaulted') DEFAULT 'pending',
    purpose TEXT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (loan_type_id) REFERENCES loan_types(loan_type_id),
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id)
);

-- Loan repayment schedule
CREATE TABLE loan_repayments (
    repayment_id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    installment_number INT NOT NULL,
    due_date DATE NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    principal_amount DECIMAL(10,2) NOT NULL,
    interest_amount DECIMAL(10,2) NOT NULL,
    paid_date DATE,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'paid', 'overdue', 'partial') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES employee_loans(loan_id) ON DELETE CASCADE
);

-- Create the advance_repayments table
CREATE TABLE IF NOT EXISTS `advance_repayments` (
  `repayment_id` int(11) NOT NULL AUTO_INCREMENT,
  `advance_id` int(11) NOT NULL,
  `payroll_id` int(11) DEFAULT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'payroll',
  `status` enum('pending','paid','failed','reversed') NOT NULL DEFAULT 'pending',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`repayment_id`),
  KEY `advance_id` (`advance_id`),
  KEY `payroll_id` (`payroll_id`),
  KEY `payment_date` (`payment_date`),
  KEY `status` (`status`),
  CONSTRAINT `advance_repayments_ibfk_1` FOREIGN KEY (`advance_id`) REFERENCES `salary_advances` (`advance_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `advance_repayments_ibfk_2` FOREIGN KEY (`payroll_id`) REFERENCES `payroll_master` (`payroll_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add index on created_at for better query performance
ALTER TABLE `advance_repayments` ADD INDEX `idx_created_at` (`created_at`);

-- ==================== PAYROLL PROCESSING ====================

-- Payroll periods table
CREATE TABLE payroll_periods (
    period_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    period_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    payment_date DATE NOT NULL,
    period_type ENUM('monthly', 'weekly', 'daily') NOT NULL,
    status ENUM('draft', 'processing', 'approved', 'paid', 'closed') DEFAULT 'draft',
    created_by INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES employees(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id)
);

-- Payroll master table
CREATE TABLE payroll_master (
    payroll_id INT PRIMARY KEY AUTO_INCREMENT,
    period_id INT NOT NULL,
    employee_id INT NOT NULL,
    project_id INT,
    basic_salary DECIMAL(12,2) NOT NULL,
    total_earnings DECIMAL(12,2) NOT NULL,
    total_deductions DECIMAL(12,2) NOT NULL,
    gross_salary DECIMAL(12,2) NOT NULL,
    net_salary DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'NGN',
    exchange_rate DECIMAL(10,4) DEFAULT 1.0,
    paid_amount DECIMAL(12,2),
    payment_status ENUM('pending', 'paid', 'partial', 'hold') DEFAULT 'pending',
    payment_date DATE,
    payment_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES payroll_periods(period_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);

-- Payroll details table
CREATE TABLE payroll_details (
    payroll_detail_id INT PRIMARY KEY AUTO_INCREMENT,
    payroll_id INT NOT NULL,
    component_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    component_type ENUM('earning', 'deduction') NOT NULL,
    is_taxable BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (payroll_id) REFERENCES payroll_master(payroll_id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES salary_components(component_id)
);

-- ==================== TAX AND STATUTORY DEDUCTIONS ====================

-- Tax configurations (Nigeria specific)
CREATE TABLE tax_configurations (
    config_id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    tax_year YEAR NOT NULL,
    pension_employee_rate DECIMAL(5,2) DEFAULT 8.0,
    pension_employer_rate DECIMAL(5,2) DEFAULT 10.0,
    nhf_employee_rate DECIMAL(5,2) DEFAULT 2.5,
    itf_rate DECIMAL(5,2) DEFAULT 1.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id)
);

-- PAYE tax bands (Nigeria specific)
CREATE TABLE paye_tax_bands (
    band_id INT PRIMARY KEY AUTO_INCREMENT,
    tax_year YEAR NOT NULL,
    lower_limit DECIMAL(12,2) NOT NULL,
    upper_limit DECIMAL(12,2),
    tax_rate DECIMAL(5,2) NOT NULL,
    fixed_amount DECIMAL(10,2) DEFAULT 0
);

-- ==================== BANKS AND PAYMENT METHODS ====================
CREATE TABLE banks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(255) NOT NULL,
    bank_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================== BENEFITS AND ALLOWANCES ====================

-- Special benefits table
CREATE TABLE special_benefits (
    benefit_id INT PRIMARY KEY AUTO_INCREMENT,
    benefit_name VARCHAR(255) NOT NULL,
    benefit_code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    is_recurring BOOLEAN DEFAULT FALSE,
    is_taxable BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE
);

-- Employee benefits assignment
CREATE TABLE employee_benefits (
    employee_benefit_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    benefit_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'NGN',
    effective_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (benefit_id) REFERENCES special_benefits(benefit_id)
);

-- ==================== ATTENDANCE AND TIME TRACKING ====================

-- Attendance records
CREATE TABLE attendance_records (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    project_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    total_hours DECIMAL(4,2),
    overtime_hours DECIMAL(4,2) DEFAULT 0,
    status ENUM('present', 'absent', 'late', 'half_day', 'leave') DEFAULT 'present',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);

-- ==================== MULTI-CURRENCY SUPPORT ====================

-- Currency exchange rates
CREATE TABLE currency_rates (
    rate_id INT PRIMARY KEY AUTO_INCREMENT,
    base_currency VARCHAR(3) NOT NULL,
    target_currency VARCHAR(3) NOT NULL,
    exchange_rate DECIMAL(10,4) NOT NULL,
    effective_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================== INTEGRATION AND THIRD-PARTY APPS ====================

-- Third-party applications integration
CREATE TABLE third_party_apps (
    app_id INT PRIMARY KEY AUTO_INCREMENT,
    app_name VARCHAR(255) NOT NULL,
    app_type ENUM('accounting', 'expense', 'hr', 'other') NOT NULL,
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    base_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Integration logs
CREATE TABLE integration_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    app_id INT NOT NULL,
    integration_type VARCHAR(100) NOT NULL,
    request_data JSON,
    response_data JSON,
    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES third_party_apps(app_id)
);

-- ==================== USER MANAGEMENT AND PERMISSIONS ====================

-- Users table for system access
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT UNIQUE,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'payroll_master', 'hr_manager', 'employee', 'project_manager') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

-- Roles and permissions
CREATE TABLE user_roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE
);

-- User role assignments
CREATE TABLE user_role_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id)
);

-- System permissions
CREATE TABLE permissions (
    permission_id INT PRIMARY KEY AUTO_INCREMENT,
    permission_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    module VARCHAR(100) NOT NULL
);

-- Role permissions
CREATE TABLE role_permissions (
    role_permission_id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id)
);

-- ==================== AUDIT AND SYSTEM LOGS ====================

-- Audit trail table
CREATE TABLE audit_trail (
    audit_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- System settings
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== INDEXES FOR PERFORMANCE ====================

CREATE INDEX idx_employees_company ON employees(company_id);
CREATE INDEX idx_employees_department ON employees(department_id);
CREATE INDEX idx_employees_type ON employees(employee_type_id);
CREATE INDEX idx_employees_status ON employees(status);
CREATE INDEX idx_payroll_period ON payroll_master(period_id, employee_id);
CREATE INDEX idx_payroll_employee ON payroll_master(employee_id);
CREATE INDEX idx_attendance_employee_date ON attendance_records(employee_id, attendance_date);
CREATE INDEX idx_loan_employee ON employee_loans(employee_id, status);
CREATE INDEX idx_advance_employee ON salary_advances(employee_id, status);
CREATE INDEX idx_project_assignments ON employee_project_assignments(employee_id, project_id);
CREATE INDEX idx_users_employee ON users(employee_id);

-- ==================== DEFAULT DATA INSERTIONS ====================

-- Insert default employee types
INSERT INTO employee_types (type_name, description, payment_frequency) VALUES
('Monthly Paid', 'Staff paid on monthly basis', 'monthly'),
('Weekly Paid', 'Staff paid on weekly basis', 'weekly'),
('Daily Paid', 'Staff paid on daily basis', 'daily'),
('Casual Worker', 'Temporary or casual workers', 'daily'),
('Foreign Expatriate', 'Foreign staff paid in foreign currency', 'monthly');

-- Insert default salary components
INSERT INTO salary_components (component_name, component_type, component_code, is_taxable, is_statutory) VALUES
('Basic Salary', 'earning', 'BASIC', TRUE, FALSE),
('Housing Allowance', 'allowance', 'HOUSE', TRUE, FALSE),
('Transport Allowance', 'allowance', 'TRANS', TRUE, FALSE),
('Meal Allowance', 'allowance', 'MEAL', TRUE, FALSE),
('Utility Allowance', 'allowance', 'UTIL', TRUE, FALSE),
('13th Month', 'earning', '13TH', TRUE, FALSE),
('Hazard Allowance', 'allowance', 'HAZARD', TRUE, FALSE),
('Non-Accident Bonus', 'earning', 'NOACC', FALSE, FALSE),
('Project Allowance', 'allowance', 'PROJ', TRUE, FALSE),
('PAYE Tax', 'deduction', 'PAYE', FALSE, TRUE),
('Pension Contribution', 'deduction', 'PENS', FALSE, TRUE),
('NHF Contribution', 'deduction', 'NHF', FALSE, TRUE),
('Loan Repayment', 'deduction', 'LOAN', FALSE, FALSE),
('Salary Advance', 'deduction', 'ADVANCE', FALSE, FALSE);

-- Insert special benefits
INSERT INTO special_benefits (benefit_name, benefit_code, description, is_recurring, is_taxable) VALUES
('13th Month', '13TH_MONTH', '13th month salary payment', TRUE, TRUE),
('Hazard Allowance', 'HAZARD_PAY', 'Payment for hazardous work conditions', TRUE, TRUE),
('Non-Accident Bonus', 'DRIVER_SAFETY', 'Bonus for drivers with no accidents', FALSE, FALSE),
('Project Completion Bonus', 'PROJ_BONUS', 'Bonus for project completion', FALSE, TRUE),
('Annual Leave Bonus', 'LEAVE_BONUS', 'Bonus paid with annual leave', FALSE, TRUE);

-- Insert default user roles
INSERT INTO user_roles (role_name, description) VALUES
('System Administrator', 'Full system access and configuration'),
('Payroll Manager', 'Payroll processing and management'),
('HR Manager', 'Human resources management'),
('Project Manager', 'Project and site management'),
('Employee', 'Basic employee self-service');

-- Insert default tax bands for Nigeria (2024 rates - example)
INSERT INTO paye_tax_bands (tax_year, lower_limit, upper_limit, tax_rate, fixed_amount) VALUES
(2024, 0, 300000, 7, 0),
(2024, 300001, 600000, 11, 21000),
(2024, 600001, 1100000, 15, 54000),
(2024, 1100001, 1600000, 19, 129000),
(2024, 1600001, NULL, 21, 224000);

-- Insert default currencies
INSERT INTO currency_rates (base_currency, target_currency, exchange_rate, effective_date) VALUES
('NGN', 'NGN', 1.0000, CURDATE()),
('USD', 'NGN', 1500.0000, CURDATE()),
('GBP', 'NGN', 1900.0000, CURDATE()),
('EUR', 'NGN', 1600.0000, CURDATE());

COMMIT;