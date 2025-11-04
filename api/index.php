<?php
// Load common API files
require_once 'common/database.php';
require_once 'common/auth.php';
require_once 'common/response.php'; // For standardized JSON output

// 1. Get the requested resource path and HTTP method
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove the base path /api/ from the URI
$api_path = str_replace('/PAY/api/', '', $request_uri);
$path_segments = explode('/', trim($api_path, '/'));

// The first segment is the module (e.g., 'employees', 'payroll')
$module = $path_segments[0] ?? null; 

// The second segment (if available) determines the operation (e.g., 'create', 'read', '123')
$operation = $path_segments[1] ?? null;

// Default file to include
$file_to_include = null;

if ($module) {
    // --- Determine the correct file based on the method and path ---
    
    if ($module === 'employees') {
        if ($method === 'POST' && $operation === 'create') {
            $file_to_include = 'employees/create.php';
        } elseif ($method === 'GET' && $operation === 'statistics') {
            $file_to_include = 'employees/statistics.php';
        } elseif ($method === 'GET' && empty($operation)) {
            // GET /api/employees/ -> List all employees
            $file_to_include = 'employees/read.php'; 
        } elseif ($method === 'PUT' && is_numeric($operation)) {
            // PUT /api/employees/123 -> Update
            // You might need to set $_GET['id'] = $operation; here
            $file_to_include = 'employees/update.php';
        }
        // ... add logic for other methods (DELETE, etc.)
    } 
    // ... add logic for other modules (payroll, attendance)
}

if ($file_to_include && file_exists($file_to_include)) {
    require_once $file_to_include;
} else {
    // Return a 404 Not Found response
    send_response(404, ['error' => 'API Endpoint Not Found']);
}
?>