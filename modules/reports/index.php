<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireAuth();

$page_title = "Reports & Analytics";
$body_class = "reports-page";

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Reports & Analytics</h1>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Payroll Reports</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                            Generate payroll summaries, tax reports, and payslips
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="payroll.php" class="btn btn-primary btn-sm">View Reports</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Attendance Reports</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                            View attendance summaries, overtime, and absence reports
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="attendance.php" class="btn btn-success btn-sm">View Reports</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Employee Reports</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                            Employee lists, statistics, and demographic reports
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="employees.php" class="btn btn-info btn-sm">View Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Reports</h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="/api/reports/payroll.php?format=csv" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-csv me-2"></i>Export Payroll Summary (CSV)
                    </a>
                    <a href="/api/reports/attendance.php?format=pdf" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-pdf me-2"></i>Attendance Report (PDF)
                    </a>
                    <a href="/api/reports/employees.php?format=excel" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-excel me-2"></i>Employee List (Excel)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Report Generator</h6>
            </div>
            <div class="card-body">
                <form id="reportForm">
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-control" name="report_type" required>
                            <option value="">Select Report Type</option>
                            <option value="payroll_summary">Payroll Summary</option>
                            <option value="attendance_summary">Attendance Summary</option>
                            <option value="employee_list">Employee List</option>
                            <option value="tax_deductions">Tax Deductions</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <select class="form-control" name="format" required>
                            <option value="json">JSON</option>
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-download me-2"></i>Generate Report
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const reportType = formData.get('report_type');
    const format = formData.get('format');
    const startDate = formData.get('start_date');
    const endDate = formData.get('end_date');
    
    // Redirect to the appropriate API endpoint
    window.location.href = `/api/reports/${reportType}?format=${format}&start_date=${startDate}&end_date=${endDate}`;
});
</script>

<?php include '../../includes/footer.php'; ?>