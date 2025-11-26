<?php
require_once dirname(__DIR__) . '/config/constants.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$current_user = $is_logged_in ? [
    'full_name' => $_SESSION['full_name'] ?? '',
    'user_type' => $_SESSION['user_type'] ?? ''
] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Payroll HR System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="<?= base_url('assets/css/styles.css'); ?>" rel="stylesheet">
    
    <?php if (isset($page_css)): ?>
    <style><?php echo $page_css; ?></style>
    <?php endif; ?>
</head>
<body class="<?php echo isset($body_class) ? $body_class : ''; ?>">
    <?php if ($is_logged_in): ?>
    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= base_url('dashboard.php'); ?>">
                <i class="fas fa-building me-2"></i>Payroll HR System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('dashboard.php'); ?>"><i class="fas fa-home me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('modules/employees/'); ?>"><i class="fas fa-users me-1"></i>Employees</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('modules/payroll/'); ?>"><i class="fas fa-money-bill me-1"></i>Payroll</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('modules/attendance/'); ?>"><i class="fas fa-calendar-check me-1"></i>Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('modules/loans/'); ?>"><i class="fas fa-hand-holding-usd me-1"></i>Loans</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('modules/advance/'); ?>"><i class="fas fa-money-bill-wave me-1"></i>Salary Advance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('modules/reports/'); ?>"><i class="fas fa-chart-bar me-1"></i>Reports</a>
                    </li>
                    <?php if (in_array($_SESSION['user_type'], ['admin', 'hr_manager'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cogs me-1"></i>Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= base_url('modules/departments/'); ?>"><i class="fas fa-building me-2"></i>Departments</a></li>
                            <li><a class="dropdown-item" href="<?= base_url('modules/projects/'); ?>"><i class="fas fa-hard-hat me-2"></i>Projects</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= base_url('settings.php'); ?>"><i class="fas fa-sliders-h me-2"></i>Settings</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($current_user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= base_url('profile.php'); ?>"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-bell me-2"></i>Notifications</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= base_url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="<?php echo $is_logged_in ? 'container-fluid mt-4' : ''; ?>">