<?php
class Auth {
    private $conn;
    
    public function __construct($db = null) {
        if ($db) {
            $this->conn = $db;
        }
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($username, $password) {
        if (!$this->conn) {
            throw new Exception("Database connection not available");
        }

        $query = "SELECT u.*, e.first_name, e.last_name, e.employee_id, e.status as employee_status
                  FROM users u 
                  LEFT JOIN employees e ON u.employee_id = e.employee_id 
                  WHERE u.username = :username AND u.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if employee is active - ONLY if they have an employee record
            // Allow login if no employee_id (like admin users) OR if employee is active
            if (!empty($user['employee_id']) && $user['employee_status'] !== 'active') {
                return false;
            }
            
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['employee_id'] = $user['employee_id'];
                
                // Set full name - use username if no employee name
                if (!empty($user['first_name']) && !empty($user['last_name'])) {
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                } else {
                    $_SESSION['full_name'] = $user['username']; // Fallback to username
                }
                
                $_SESSION['login_time'] = time();
                
                // Update last login
                $this->updateLastLogin($user['user_id']);
                
                return true;
            }
        }
        return false;
    }

    private function updateLastLogin($user_id) {
        $query = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !$this->isSessionExpired();
    }

    private function isSessionExpired() {
        $max_session_duration = 8 * 60 * 60; // 8 hours
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $max_session_duration)) {
            $this->logout();
            return true;
        }
        return false;
    }

    public function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        header("Location: login.php");
        exit;
    }

    public function hasPermission($required_role) {
        if (!isset($_SESSION['user_type'])) {
            return false;
        }
        
        $user_role = $_SESSION['user_type'];
        $hierarchy = [
            'employee' => 1,
            'project_manager' => 2,
            'hr_manager' => 3,
            'payroll_master' => 4,
            'admin' => 5
        ];
        
        return isset($hierarchy[$user_role]) && $hierarchy[$user_role] >= $hierarchy[$required_role];
    }

    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit;
        }
    }

    public function requirePermission($required_role) {
        $this->requireAuth();
        
        if (!$this->hasPermission($required_role)) {
            http_response_code(403);
            include '403.php';
            exit;
        }
    }

    public function getCurrentUser() {
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'user_type' => $_SESSION['user_type'] ?? null,
            'employee_id' => $_SESSION['employee_id'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null
        ];
    }

    // Add this method to check if current user is admin (no employee_id)
    public function isSystemAdmin() {
        return empty($_SESSION['employee_id']) && $_SESSION['user_type'] === 'admin';
    }
}
?>