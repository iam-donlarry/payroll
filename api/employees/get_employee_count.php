<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    
    $query = "SELECT COUNT(*) as count FROM employees 
              WHERE YEAR(employment_date) = :year 
              AND MONTH(employment_date) = :month";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':year' => $year, ':month' => $month]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['count' => (int)$result['count']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}