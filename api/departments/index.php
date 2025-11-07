<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database and auth
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Route the request
try {
    switch ($method) {
        case 'GET':
            if ($id) {
                getDepartment($db, $id);
            } else {
                getDepartments($db);
            }
            break;
        case 'POST':
            createDepartment($db, $input);
            break;
        case 'PUT':
            if (!$id && !empty($input['department_id'])) {
                $id = (int)$input['department_id'];
            }
            if ($id) {
                updateDepartment($db, $id, $input);
            } else {
                throw new Exception('Department ID is required for update');
            }
            break;
        case 'DELETE':
            if (!$id && !empty($input['department_id'])) {
                $id = (int)$input['department_id'];
            }
            if ($id) {
                deleteDepartment($db, $id);
            } else {
                throw new Exception('Department ID is required for deletion');
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Get all departments
function getDepartments($db) {
    $query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $departments]);
}

// Get single department
function getDepartment($db, $id) {
    $query = "SELECT * FROM departments WHERE department_id = ? AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($department) {
        echo json_encode(['success' => true, 'data' => $department]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Department not found']);
    }
}

// Create department
function createDepartment($db, $data) {
    $required = ['department_name', 'department_code'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $query = "INSERT INTO departments 
              (company_id, department_name, department_code, description) 
              VALUES (:company_id, :department_name, :department_code, :description)";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        ':company_id' => $data['company_id'] ?? 1,
        ':department_name' => $data['department_name'],
        ':department_code' => $data['department_code'],
        ':description' => $data['description'] ?? ''
    ]);

    if ($result) {
        $id = $db->lastInsertId();
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Department created successfully',
            'department_id' => $id
        ]);
    } else {
        throw new Exception('Failed to create department');
    }
}

// Update department
function updateDepartment($db, $id, $data) {
    $query = "UPDATE departments SET 
              department_name = :department_name,
              department_code = :department_code,
              description = :description,
              is_active = :is_active
              WHERE department_id = :id";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        ':department_name' => $data['department_name'],
        ':department_code' => $data['department_code'],
        ':description' => $data['description'] ?? '',
        ':is_active' => $data['is_active'] ?? 1,
        ':id' => $id
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
    } else {
        throw new Exception('Failed to update department');
    }
}

// Delete department (soft delete)
function deleteDepartment($db, $id) {
    // First check if department has employees
    $check = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE department_id = ?");
    $check->execute([$id]);
    $result = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete department with assigned employees']);
        return;
    }

    // Soft delete
    $query = "UPDATE departments SET is_active = 0 WHERE department_id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Department deleted successfully']);
    } else {
        throw new Exception('Failed to delete department');
    }
}