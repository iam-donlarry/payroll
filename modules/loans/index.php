<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('hr_manager');

$page_title = "Loans Management";
$body_class = "loans-page";

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'approve_loan' && isset($_GET['id'])) {
    $loan_id = (int)$_GET['id'];
    
    try {
        $query = "UPDATE employee_loans SET 
                 status = 'approved',
                 approval_date = CURDATE(),
                 approved_by = :approved_by
                 WHERE loan_id = :loan_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':loan_id', $loan_id, PDO::PARAM_INT);
        $stmt->bindValue(':approved_by', $_SESSION['employee_id']);
        $stmt->execute();
        
        $message = '<div class="alert alert-success">Loan approved successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error approving loan: ' . $e->getMessage() . '</div>';
    }
}

if ($action === 'reject_loan' && isset($_GET['id'])) {
    $loan_id = (int)$_GET['id'];
    
    try {
        $query = "UPDATE employee_loans SET status = 'rejected' WHERE loan_id = :loan_id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':loan_id', $loan_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $message = '<div class="alert alert-success">Loan rejected successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error rejecting loan: ' . $e->getMessage() . '</div>';
    }
}

// Get loans and advances
$loans = [];
$advances = [];
$loan_types = [];

try {
    // Get employee loans
    $loans_query = "SELECT el.*, e.first_name, e.last_name, e.employee_code, 
                           lt.loan_name, lt.interest_rate,
                           CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                    FROM employee_loans el
                    JOIN employees e ON el.employee_id = e.employee_id
                    JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                    LEFT JOIN employees approver ON el.approved_by = approver.employee_id
                    ORDER BY el.application_date DESC";
    $loans_stmt = $db->prepare($loans_query);
    $loans_stmt->execute();
    $loans = $loans_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get salary advances
    $advances_query = "SELECT sa.*, e.first_name, e.last_name, e.employee_code,
                              CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                       FROM salary_advances sa
                       JOIN employees e ON sa.employee_id = e.employee_id
                       LEFT JOIN employees approver ON sa.approved_by = approver.employee_id
                       ORDER BY sa.request_date DESC";
    $advances_stmt = $db->prepare($advances_query);
    $advances_stmt->execute();
    $advances = $advances_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get loan types
    $types_query = "SELECT * FROM loan_types WHERE is_active = 1 ORDER BY loan_name";
    $types_stmt = $db->prepare($types_query);
    $types_stmt->execute();
    $loan_types = $types_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Loans management error: " . $e->getMessage());
    $message = '<div class="alert alert-danger">Error loading loans data.</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Loans & Advances Management</h1>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLoanModal">
            <i class="fas fa-hand-holding-usd me-2"></i>Apply for Loan
        </button>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#requestAdvanceModal">
            <i class="fas fa-money-bill-wave me-2"></i>Request Advance
        </button>
    </div>
</div>

<?php echo $message; ?>

<ul class="nav nav-tabs" id="loansTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#loans">Employee Loans</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#advances">Salary Advances</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#repayments">Repayments</a>
    </li>
</ul>

<div class="tab-content mt-3">
    <!-- Employee Loans Tab -->
    <div class="tab-pane fade show active" id="loans">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Employee Loans</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="loansTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Loan Type</th>
                                <th>Amount</th>
                                <th>Interest Rate</th>
                                <th>Tenure</th>
                                <th>Monthly Payment</th>
                                <th>Application Date</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($loan['employee_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($loan['loan_name']); ?></td>
                                <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                <td><?php echo $loan['interest_rate']; ?>%</td>
                                <td><?php echo $loan['tenure_months']; ?> months</td>
                                <td><?php echo formatCurrency($loan['monthly_repayment']); ?></td>
                                <td><?php echo formatDate($loan['application_date']); ?></td>
                                <td><?php echo getStatusBadge($loan['status']); ?></td>
                                <td><?php echo htmlspecialchars($loan['approver_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($loan['status'] == 'pending' && $auth->hasPermission('admin')): ?>
                                        <a href="?action=approve_loan&id=<?php echo $loan['loan_id']; ?>" 
                                           class="btn btn-success" title="Approve"
                                           onclick="return confirm('Approve this loan application?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?action=reject_loan&id=<?php echo $loan['loan_id']; ?>" 
                                           class="btn btn-danger" title="Reject"
                                           onclick="return confirm('Reject this loan application?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                        <?php endif; ?>                                       
                                        <button class="btn btn-info view-loan" 
                                                data-id="<?php echo $loan['loan_id']; ?>"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (in_array($loan['status'], ['approved', 'active'])): ?>
                                        <button class="btn btn-warning add-repayment" 
                                                data-id="<?php echo $loan['loan_id']; ?>"
                                                title="Add Repayment">
                                            <i class="fas fa-money-bill"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary Advances Tab -->
    <div class="tab-pane fade" id="advances">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Salary Advances</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="advancesTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Amount</th>
                                <th>Request Date</th>
                                <th>Repayment Period</th>
                                <th>Monthly Repayment</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($advances as $advance): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($advance['first_name'] . ' ' . $advance['last_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($advance['employee_code']); ?></small>
                                </td>
                                <td><?php echo formatCurrency($advance['advance_amount']); ?></td>
                                <td><?php echo formatDate($advance['request_date']); ?></td>
                                <td><?php echo $advance['repayment_period_months']; ?> months</td>
                                <td><?php echo formatCurrency($advance['monthly_repayment_amount']); ?></td>
                                <td><?php echo getStatusBadge($advance['status']); ?></td>
                                <td><?php echo htmlspecialchars($advance['approver_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($advance['status'] == 'pending' && $auth->hasPermission('admin')): ?>
                                        <a href="?action=approve_advance&id=<?php echo $advance['advance_id']; ?>" 
                                           class="btn btn-success" title="Approve"
                                           onclick="return confirm('Approve this salary advance?')">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?action=reject_advance&id=<?php echo $advance['advance_id']; ?>" 
                                           class="btn btn-danger" title="Reject"
                                           onclick="return confirm('Reject this salary advance?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                        <?php endif; ?>
                                        <button class="btn btn-info view-advance" 
                                                data-id="<?php echo $advance['advance_id']; ?>"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Repayments Tab -->
    <div class="tab-pane fade" id="repayments">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Loan Repayments</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="repaymentsTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Loan Type</th>
                                <th>Installment</th>
                                <th>Due Date</th>
                                <th>Amount Due</th>
                                <th>Paid Date</th>
                                <th>Amount Paid</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $repayments_query = "SELECT lr.*, el.loan_amount, el.loan_id,
                                                           e.first_name, e.last_name, e.employee_code,
                                                           lt.loan_name
                                                    FROM loan_repayments lr
                                                    JOIN employee_loans el ON lr.loan_id = el.loan_id
                                                    JOIN employees e ON el.employee_id = e.employee_id
                                                    JOIN loan_types lt ON el.loan_type_id = lt.loan_type_id
                                                    ORDER BY lr.due_date DESC, lr.installment_number";
                                $repayments_stmt = $db->prepare($repayments_query);
                                $repayments_stmt->execute();
                                $repayments = $repayments_stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                error_log("Repayments loading error: " . $e->getMessage());
                                $repayments = [];
                            }
                                foreach ($repayments as $repayment):
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($repayment['first_name'] . ' ' . $repayment['last_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($repayment['employee_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($repayment['loan_name']); ?></td>
                                <td>#<?php echo $repayment['installment_number']; ?></td>
                                <td><?php echo formatDate($repayment['due_date']); ?></td>
                                <td><?php echo formatCurrency($repayment['amount_due']); ?></td>
                                <td>
                                    <?php if ($repayment['paid_date']): ?>
                                        <?php echo formatDate($repayment['paid_date']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($repayment['amount_paid']); ?></td>
                                <td><?php echo getStatusBadge($repayment['status']); ?></td>
                                <td>
                                    <?php if ($repayment['status'] == 'pending'): ?>
                                    <button class="btn btn-sm btn-success mark-paid" 
                                            data-id="<?php echo $repayment['repayment_id']; ?>"
                                            data-amount="<?php echo $repayment['amount_due']; ?>">
                                        <i class="fas fa-check"></i> Mark Paid
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Apply Loan Modal -->
<div class="modal fade" id="applyLoanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply for Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="applyLoanForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Employee *</label>
                                <select class="form-control" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>">
                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_code'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Loan Type *</label>
                                <select class="form-control" name="loan_type_id" required>
                                    <option value="">Select Loan Type</option>
                                    <?php foreach ($loan_types as $type): ?>
                                    <option value="<?php echo $type['loan_type_id']; ?>" 
                                            data-rate="<?php echo $type['interest_rate']; ?>"
                                            data-max="<?php echo $type['max_amount']; ?>"
                                            data-tenure="<?php echo $type['max_tenure_months']; ?>">
                                        <?php echo htmlspecialchars($type['loan_name']); ?> (<?php echo $type['interest_rate']; ?>%)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Loan Amount (₦) *</label>
                                <input type="number" class="form-control" name="loan_amount" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tenure (Months) *</label>
                                <input type="number" class="form-control" name="tenure_months" min="1" max="36" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Purpose</label>
                        <textarea class="form-control" name="purpose" rows="3" placeholder="Briefly describe the purpose of this loan..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-calculator me-2"></i>
                        <span id="loanCalculation">Loan details will be calculated upon selection</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Apply for Loan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Advance Modal -->
<div class="modal fade" id="requestAdvanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Salary Advance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="requestAdvanceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-control" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>">
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Advance Amount (₦) *</label>
                        <input type="number" class="form-control" name="advance_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Repayment Period (Months) *</label>
                        <select class="form-control" name="repayment_period_months" required>
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3" selected>3 Months</option>
                            <option value="4">4 Months</option>
                            <option value="5">5 Months</option>
                            <option value="6">6 Months</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Advance *</label>
                        <textarea class="form-control" name="reason" rows="3" required placeholder="Please provide a reason for this salary advance..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Salary advances are typically limited to 50% of net monthly salary and subject to approval.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Request Advance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#loansTable, #advancesTable, #repaymentsTable').DataTable({
        pageLength: 25,
        order: [[6, 'desc']]
    });

    // Loan calculation
    $('select[name="loan_type_id"], input[name="loan_amount"], input[name="tenure_months"]').on('change', function() {
        calculateLoan();
    });

    function calculateLoan() {
        const loanType = $('select[name="loan_type_id"]');
        const amount = parseFloat($('input[name="loan_amount"]').val()) || 0;
        const tenure = parseInt($('input[name="tenure_months"]').val()) || 0;
        const rate = parseFloat(loanType.find('option:selected').data('rate')) || 0;
        const maxAmount = parseFloat(loanType.find('option:selected').data('max')) || 0;
        const maxTenure = parseInt(loanType.find('option:selected').data('tenure')) || 0;

        if (amount && tenure && rate) {
            // Simple interest calculation for demonstration
            const totalInterest = (amount * rate * tenure) / 100;
            const totalRepayable = amount + totalInterest;
            const monthlyPayment = totalRepayable / tenure;

            $('#loanCalculation').html(
                `Monthly Payment: ₦${monthlyPayment.toFixed(2)} | Total Repayable: ₦${totalRepayable.toFixed(2)} | Total Interest: ₦${totalInterest.toFixed(2)}`
            );

            // Validate against limits
            if (maxAmount && amount > maxAmount) {
                $('#loanCalculation').append(`<br><span class="text-danger">Amount exceeds maximum of ₦${maxAmount.toFixed(2)} for this loan type.</span>`);
            }
            if (maxTenure && tenure > maxTenure) {
                $('#loanCalculation').append(`<br><span class="text-danger">Tenure exceeds maximum of ${maxTenure} months for this loan type.</span>`);
            }
        }
    }

    // Apply Loan Form
    $('#applyLoanForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '/api/loans/apply',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Loan application submitted successfully!');
                    $('#applyLoanModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error submitting loan application');
            }
        });
    });

    // Request Advance Form
    $('#requestAdvanceForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '/api/loans/request_advance',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Salary advance requested successfully!');
                    $('#requestAdvanceModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error requesting salary advance');
            }
        });
    });

    // Mark repayment as paid
    $('.mark-paid').on('click', function() {
        const repaymentId = $(this).data('id');
        const amountDue = $(this).data('amount');
        
        if (confirm(`Mark this repayment of ₦${amountDue} as paid?`)) {
            $.ajax({
                url: '/api/loans/mark_repayment_paid',
                type: 'POST',
                data: { repayment_id: repaymentId },
                success: function(response) {
                    if (response.success) {
                        alert('Repayment marked as paid!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error updating repayment');
                }
            });
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>