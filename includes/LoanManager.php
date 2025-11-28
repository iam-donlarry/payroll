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
     * Get total outstanding balance from loans (including pending applications)
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
     * Get total outstanding balance from salary advances (including pending)
     */
    public function getOutstandingAdvanceBalance($employee_id) {
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
     * Get total monthly repayment amount for active loans of an employee.
     * This amount reduces the advance borrowing limit for the current month.
     */
    public function getMonthlyLoanRepayment($employee_id) {
        $query = "
            SELECT SUM(monthly_repayment) as total_monthly_repayment
            FROM employee_loans
            WHERE employee_id = ?
            AND status IN ('approved', 'disbursed', 'active')
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['total_monthly_repayment'] ?? 0);
    }

    /**
     * Check if an advance request complies with the 33% gross salary rule.
     * Loans are not limited; only the monthly loan repayment reduces the available advance.
     */
    public function checkAdvanceBorrowingLimit($employee_id, $requested_amount) {
        $gross_salary = $this->getGrossSalary($employee_id);
        $max_limit = $gross_salary * 0.33;
        $monthly_loan_repayment = $this->getMonthlyLoanRepayment($employee_id);
        $available_amount = max(0, $max_limit - $monthly_loan_repayment);

        if ($requested_amount > $available_amount) {
            return [
                'allowed' => false,
                'gross_salary' => $gross_salary,
                'max_limit' => $max_limit,
                'monthly_loan_repayment' => $monthly_loan_repayment,
                'available_amount' => $available_amount,
                'message' => "Advance request exceeds available amount.\nGross Salary: ₦" . number_format($gross_salary, 2) .
                             ", 33% Limit: ₦" . number_format($max_limit, 2) .
                             ", Monthly Loan Repayment: ₦" . number_format($monthly_loan_repayment, 2) .
                             ", Available Advance: ₦" . number_format($available_amount, 2)
            ];
        }

        return [
            'allowed' => true,
            'gross_salary' => $gross_salary,
            'max_limit' => $max_limit,
            'monthly_loan_repayment' => $monthly_loan_repayment,
            'available_amount' => $available_amount
        ];
    }

    // Legacy method kept for backward compatibility (not used for advances)
    public function checkBorrowingLimit($employee_id, $requested_amount) {
        // Loans are unrestricted; this method now simply returns allowed = true.
        return [
            'allowed' => true,
            'gross_salary' => $this->getGrossSalary($employee_id),
            'max_limit' => null,
            'current_outstanding' => null,
            'available_amount' => null
        ];
    }
}
?>
