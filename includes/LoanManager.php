<?php
class LoanManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Calculate monthly gross salary from active salary components
     */
    public function getGrossSalary($employee_id) {
        // Sum all active earning and allowance components
        $query = "
            SELECT SUM(amount) as gross_salary
            FROM employee_salary_structure ess
            JOIN salary_components sc ON ess.component_id = sc.component_id
            WHERE ess.employee_id = ? 
            AND ess.is_active = 1
            AND sc.component_type IN ('earning', 'allowance')
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float)($result['gross_salary'] ?? 0);
    }

    /**
     * Get total outstanding balance from loans
     * Includes pending applications to prevent race conditions/over-borrowing
     */
    public function getOutstandingLoanBalance($employee_id) {
        $query = "
            SELECT SUM(remaining_balance) as total_loan_balance
            FROM employee_loans
            WHERE employee_id = ?
            AND status IN ('pending', 'approved', 'disbursed', 'active')
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float)($result['total_loan_balance'] ?? 0);
    }

    /**
     * Get total outstanding balance from salary advances
     * Includes pending applications
     */
    public function getOutstandingAdvanceBalance($employee_id) {
        // For advances, we check the original amount minus what has been deducted/paid
        // Note: The schema has 'deducted_amount' in salary_advances table
        $query = "
            SELECT SUM(advance_amount - COALESCE(deducted_amount, 0)) as total_advance_balance
            FROM salary_advances
            WHERE employee_id = ?
            AND status IN ('pending', 'approved')
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float)($result['total_advance_balance'] ?? 0);
    }

    /**
     * Check if employee can borrow the requested amount
     */
    public function checkBorrowingLimit($employee_id, $requested_amount) {
        $gross_salary = $this->getGrossSalary($employee_id);
        $max_limit = $gross_salary * 0.33;
        
        $loan_balance = $this->getOutstandingLoanBalance($employee_id);
        $advance_balance = $this->getOutstandingAdvanceBalance($employee_id);
        $total_outstanding = $loan_balance + $advance_balance;
        
        $available_amount = max(0, $max_limit - $total_outstanding);
        
        if (($total_outstanding + $requested_amount) > $max_limit) {
            return [
                'allowed' => false,
                'max_limit' => $max_limit,
                'current_outstanding' => $total_outstanding,
                'available_amount' => $available_amount,
                'gross_salary' => $gross_salary,
                'message' => "Request exceeds borrowing limit. 
                             Gross Salary: ₦" . number_format($gross_salary, 2) . ". 
                             33% Limit: ₦" . number_format($max_limit, 2) . ". 
                             Outstanding: ₦" . number_format($total_outstanding, 2) . ". 
                             Available: ₦" . number_format($available_amount, 2)
            ];
        }
        
        return [
            'allowed' => true,
            'max_limit' => $max_limit,
            'current_outstanding' => $total_outstanding,
            'available_amount' => $available_amount,
            'gross_salary' => $gross_salary
        ];
    }
}
?>
