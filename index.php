<?php
session_start();

// Redirect to login if not authenticated, otherwise to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?>