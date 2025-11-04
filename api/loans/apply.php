<?php
require_once '../common/response.php';
require_once '../common/database.php';
require_once '../common/auth.php';

class LoanApplication {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new Auth();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        
        if (!$this->auth->authenticateRequest()) {
            Response::error('Unauthorized access', 401);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('Invalid JSON input', 400);
        }
        
        $this->applyForLoan($input);
    }
    
    private function applyForLoan($data) {
        $required_fields = ['employee_id', 'loan_type_id', 'loan_amount', 'tenure_months'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                Response::error("Missing required field: $field", 400);
            }
        }
        
        try {
            // Get loan type details
            $type_query = "SELECT * FROM loan_types WHERE loan_type_id = ?";
            $type_stmt = $this->db->prepare($type_query);
            $type_stmt->execute([$data['loan_type_id']]);
            $loan_type = $type_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$loan_type) {
                Response::error('Invalid loan type', 400);
            }
            
            // Validate against limits
            if ($loan_type['max_amount'] && $data['loan_amount'] > $loan_type['max_amount']) {
                Response::error("Loan amount exceeds maximum limit of " . $loan_type['max_amount'], 400);
            }
            
            if ($loan_type['max_tenure_months'] && $data['tenure_months'] > $loan_type['max_tenure_months']) {
                Response::error("Loan tenure exceeds maximum limit of " . $loan_type['max_tenure_months'] . " months", 400);
            }
            
            // Calculate loan details
            $interest_rate = $loan_type['interest_rate'];
            $monthly_interest = $interest_rate / 12 / 100;
            $monthly_repayment = ($data['loan_amount'] * $monthly_interest * pow(1 + $monthly_interest, $data['tenure_months'])) / 
                                (pow(1 + $monthly_interest, $data['tenure_months']) - 1);
            $total_repayable = $monthly_repayment * $data['tenure_months'];
            
            // Insert loan application
            $query = "INSERT INTO employee_loans SET 
                     employee_id = :employee_id,
                     loan_type_id = :loan_type_id,
                     loan_amount = :loan_amount,
                     interest_rate = :interest_rate,
                     tenure_months = :tenure_months,
                     monthly_repayment = :monthly_repayment,
                     total_repayable_amount = :total_repayable,
                     remaining_balance = :loan_amount,
                     purpose = :purpose,
                     application_date = CURDATE(),
                     status = 'pending'";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':employee_id' => $data['employee_id'],
                ':loan_type_id' => $data['loan_type_id'],
                ':loan_amount' => $data['loan_amount'],
                ':interest_rate' => $interest_rate,
                ':tenure_months' => $data['tenure_months'],
                ':monthly_repayment' => $monthly_repayment,
                ':total_repayable' => $total_repayable,
                ':purpose' => $data['purpose'] ?? null
            ]);
            
            $loan_id = $this->db->lastInsertId();
            
            // Create repayment schedule
            $this->createRepaymentSchedule($loan_id, $data['tenure_months'], $monthly_repayment);
            
            Response::success(['loan_id' => $loan_id], 'Loan application submitted successfully', 201);
            
        } catch (PDOException $e) {
            error_log("Loan application error: " . $e->getMessage());
            Response::error('Database error: ' . $e->getMessage(), 500);
        }
    }
    
    private function createRepaymentSchedule($loan_id, $tenure_months, $monthly_repayment) {
        $current_date = new DateTime();
        
        for ($i = 1; $i <= $tenure_months; $i++) {
            $due_date = clone $current_date;
            $due_date->modify("+$i months");
            
            $query = "INSERT INTO loan_repayments SET 
                     loan_id = :loan_id,
                     installment_number = :installment,
                     due_date = :due_date,
                     amount_due = :amount_due,
                     principal_amount = :principal_amount,
                     interest_amount = :interest_amount,
                     status = 'pending'";
            
            $stmt = $this->db->prepare($query);
            
            // Simplified calculation - in practice, you'd calculate principal and interest separately
            $principal = $monthly_repayment * 0.8; // Example: 80% principal
            $interest = $monthly_repayment * 0.2;  // Example: 20% interest
            
            $stmt->execute([
                ':loan_id' => $loan_id,
                ':installment' => $i,
                ':due_date' => $due_date->format('Y-m-d'),
                ':amount_due' => $monthly_repayment,
                ':principal_amount' => $principal,
                ':interest_amount' => $interest
            ]);
        }
    }
}

$application = new LoanApplication();
$application->handleRequest();
?>