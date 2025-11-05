<?php
// File: /api/loans/apply.php (or similar path corresponding to the AJAX URL)
require_once '../common/response.php';
require_once '../common/database.php';
require_once '../common/auth.php';

class LoanApplicationAPI {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        // Assuming Auth constructor does not require $db if it manages its own connection, 
        // or ensure it's initialized correctly if it needs the connection.
        $this->auth = new Auth($this->db); 
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method !== 'POST') {
            Response::error('Method not allowed', 405);
        }
        
        // This authentication should verify the user is logged in and has privileges
        if (!$this->auth->authenticateRequest()) {
            Response::error('Unauthorized access', 401);
        }
        
        // 💡 CRITICAL FIX: Read standard POST data ($_POST) first.
        // The front-end uses jQuery.serialize(), which is URL-encoded form data.
        $input = $_POST;
        
        if (empty($input)) {
            // Fallback for strict JSON requests, but this is unlikely for your current JS
            $input = json_decode(file_get_contents('php://input'), true);
        }

        if (!$input) {
            Response::error('No input data received or invalid submission', 400);
        }
        
        $this->applyForLoan($input);
    }
    
    private function applyForLoan($data) {
        $required_fields = ['employee_id', 'loan_type_id', 'loan_amount', 'tenure_months'];
        
        // Use array_filter to ensure all fields are present AND not empty strings/null
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                Response::error("Missing required field: $field", 400);
            }
        }
        
        // Sanitize and validate types immediately after checking for presence
        $employee_id   = (int)$data['employee_id'];
        $loan_type_id  = (int)$data['loan_type_id'];
        $loan_amount   = (float)$data['loan_amount'];
        $tenure_months = (int)$data['tenure_months'];
        $purpose       = trim($data['purpose'] ?? ''); // Purpose is optional

        if ($loan_amount <= 0 || $tenure_months <= 0) {
            Response::error("Loan amount and tenure must be positive numbers.", 400);
        }

        try {
            // Get loan type details
            $type_query = "SELECT * FROM loan_types WHERE loan_type_id = ?";
            $type_stmt = $this->db->prepare($type_query);
            $type_stmt->execute([$loan_type_id]);
            $loan_type = $type_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$loan_type) {
                Response::error('Invalid loan type', 400);
            }
            
            // Validate against limits
            if ($loan_type['max_amount'] && $loan_amount > $loan_type['max_amount']) {
                Response::error("Loan amount exceeds maximum limit of " . number_format($loan_type['max_amount'], 2), 400);
            }
            
            if ($loan_type['max_tenure_months'] && $tenure_months > $loan_type['max_tenure_months']) {
                Response::error("Loan tenure exceeds maximum limit of " . $loan_type['max_tenure_months'] . " months", 400);
            }
            
            // Calculate loan details (using the original compound interest formula)
            $interest_rate = $loan_type['interest_rate'];
            $monthly_interest = ($interest_rate / 100) / 12;

            if ($monthly_interest > 0) {
                 // Annuity formula for monthly payment (PMT)
                $monthly_repayment = ($loan_amount * $monthly_interest * pow(1 + $monthly_interest, $tenure_months)) / 
                                     (pow(1 + $monthly_interest, $tenure_months) - 1);
            } else {
                 // Simple repayment (0% interest)
                 $monthly_repayment = $loan_amount / $tenure_months;
            }

            $total_repayable = $monthly_repayment * $tenure_months;
            
            // ------------------------------------
            // Insert loan application
            // ------------------------------------
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
                     status = 'pending'"; // Set initial status as pending
            
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt->bindParam(':loan_type_id', $loan_type_id, PDO::PARAM_INT);
            $stmt->bindParam(':loan_amount', $loan_amount);
            $stmt->bindParam(':interest_rate', $interest_rate);
            $stmt->bindParam(':tenure_months', $tenure_months, PDO::PARAM_INT);
            $stmt->bindParam(':monthly_repayment', $monthly_repayment);
            $stmt->bindParam(':total_repayable', $total_repayable);
            $stmt->bindParam(':purpose', $purpose);

            $stmt->execute();
            
            $loan_id = $this->db->lastInsertId();
            
            // 💡 Important Note: You are creating the repayment schedule *immediately*
            // upon application. In most HR systems, this step should wait 
            // until the loan is 'approved'. I will keep it for now as per your original code,
            // but advise you move this function call to the approval logic.
            $this->createRepaymentSchedule($loan_id, $tenure_months, $loan_amount, $monthly_repayment, $monthly_interest);
            
            Response::success(['loan_id' => $loan_id], 'Loan application submitted successfully', 201);
            
        } catch (PDOException $e) {
            error_log("Loan application error: " . $e->getMessage());
            Response::error('Database error: Could not submit application.', 500);
        }
    }
    
    private function createRepaymentSchedule($loan_id, $tenure_months, $loan_amount, $monthly_payment, $monthly_interest_rate) {
        $current_date = new DateTime();
        $current_balance = $loan_amount;

        $insert_sql = "INSERT INTO loan_repayments (loan_id, installment_number, due_date, amount_due, principal_amount, interest_amount, status) 
                       VALUES (:loan_id, :installment, :due_date, :amount_due, :principal, :interest, 'pending')";
        $stmt = $this->db->prepare($insert_sql);

        for ($i = 1; $i <= $tenure_months; $i++) {
            $due_date = clone $current_date;
            // Set due date to the next month, maintaining the day of the month
            $due_date->modify("+$i months"); 

            // Calculate interest and principal using amortization (correct formula)
            $interest_amount = $current_balance * $monthly_interest_rate;
            $principal_payment = $monthly_payment - $interest_amount;
            
            // Ensure last payment zeroes out the balance if floating point math caused issues
            if ($i == $tenure_months) {
                $principal_payment = $current_balance;
                $monthly_payment = $principal_payment + $interest_amount;
            }

            $current_balance -= $principal_payment;

            // Execute insert for the installment
            $stmt->execute([
                ':loan_id' => $loan_id,
                ':installment' => $i,
                ':due_date' => $due_date->format('Y-m-d'),
                ':amount_due' => $monthly_payment,
                ':principal' => $principal_payment,
                ':interest' => $interest_amount,
            ]);
        }
    }
}

$application = new LoanApplicationAPI();
$application->handleRequest();
?>