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

// Handle loan approval/rejection
if (isset($_GET['id'])) {
    $loan_id = (int)$_GET['id'];
    
    if ($action === 'approve_loan') {
        try {
            // First, get loan details for repayment calculation
            $loan_query = "SELECT loan_amount, interest_rate, tenure_months FROM employee_loans WHERE loan_id = ?";
            $loan_stmt = $db->prepare($loan_query);
            $loan_stmt->execute([$loan_id]);
            $loan_details = $loan_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$loan_details) {
                throw new Exception('Loan not found');
            }
            
            // Calculate repayment schedule
            $calculation = calculateLoanRepayment(
                $loan_details['loan_amount'],
                $loan_details['tenure_months']
            );
            
            // Start transaction
            $db->beginTransaction();
            
            // Update loan status
            $update_query = "UPDATE employee_loans SET 
                           status = 'approved',
                           approval_date = CURDATE(),
                           approved_by = :approved_by,
                           disbursement_date = CURDATE(),
                           start_repayment_date = DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
                           remaining_balance = :total_repayable
                           WHERE loan_id = :loan_id AND status = 'pending'";
            
            $stmt = $db->prepare($update_query);
            $stmt->bindValue(':loan_id', $loan_id, PDO::PARAM_INT);
            $stmt->bindValue(':approved_by', $_SESSION['employee_id']);
            $stmt->bindValue(':total_repayable', $calculation['total_repayable']);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    // Create repayment schedule only after approval
                    createRepaymentSchedule($db, $loan_id, $calculation, $loan_details['tenure_months']);
                    
                    $db->commit();
                    $message = '<div class="alert alert-success">Loan approved successfully. Repayment schedule created.</div>';
                } else {
                    $db->rollBack();
                    $message = '<div class="alert alert-warning">Loan could not be approved. It may have already been processed.</div>';
                }
            } else {
                $db->rollBack();
                throw new Exception('Failed to execute update query');
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Approve loan error: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Error approving loan: ' . $e->getMessage() . '</div>';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Approve loan error: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }

    if ($action === 'reject_loan') {
        try {
            $query = "UPDATE employee_loans SET 
                     status = 'rejected',
                     approved_by = :approved_by
                     WHERE loan_id = :loan_id AND status = 'pending'";
            
            $stmt = $db->prepare($query);
            $stmt->bindValue(':loan_id', $loan_id, PDO::PARAM_INT);
            $stmt->bindValue(':approved_by', $_SESSION['employee_id']);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $message = '<div class="alert alert-success">Loan rejected successfully.</div>';
                } else {
                    $message = '<div class="alert alert-warning">Loan could not be rejected. It may have already been processed.</div>';
                }
            } else {
                throw new Exception('Failed to execute query');
            }
        } catch (PDOException $e) {
            error_log("Reject loan error: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Error rejecting loan: ' . $e->getMessage() . '</div>';
        }
    }
}

// Get loans and advances
$loans = [];
$advances = [];
$loan_types = [];

try {
    // Get employee loans
    $loans_query = "SELECT el.*, e.first_name, e.last_name, e.employee_code, 
                           lt.loan_name, remaining_balance, lt.interest_rate,
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

// Initialize employees array
$employees = []; 
try {
    // Get all employees for the dropdowns
    $employees_query = "SELECT employee_id, first_name, last_name, employee_code 
                        FROM employees 
                        ORDER BY last_name, first_name";
    $employees_stmt = $db->prepare($employees_query);
    $employees_stmt->execute();
    $employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Employees loading error: " . $e->getMessage());
    // Optionally display an error message to the user
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
                                <th>Tenure</th>
                                <th>Monthly Payment</th>
                                <th>Application Date</th>
                                <th>Amount Paid</th>
                                <th>Balance</th>
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
                                <td><?php echo $loan['tenure_months']; ?> months</td>
                                <td><?php echo formatCurrency($loan['monthly_repayment']); ?></td>
                                <td><?php echo formatDate($loan['application_date']); ?></td>
                                <?php
                                $repaid_amount = $loan['loan_amount'] - $loan['remaining_balance'];
                                ?>
                                <td><?php echo formatCurrency($repaid_amount); ?></td>
                                <td><?php echo formatCurrency($loan['remaining_balance']); ?></td>
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
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Apply for a Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="loanApplicationForm">
                <div class="modal-body">
                    <div id="loanFormAlert" class="alert d-none"></div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Loan Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="loanType" name="loan_type_id" required>
                                <option value="">-- Select Loan Type --</option>
                                <?php foreach ($loan_types as $type): ?>
                                    <option value="<?php echo $type['loan_type_id']; ?>" 
                                            data-max-amount="<?php echo $type['max_amount'] ?? ''; ?>"
                                            data-max-tenure="<?php echo $type['max_tenure_months'] ?? ''; ?>">
                                        <?php echo htmlspecialchars($type['loan_name'] . 
                                            ($type['max_amount'] ? ' - Max: ' . formatCurrency($type['max_amount']) : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" id="employeeId" name="employee_id" <?php echo $auth->hasPermission('admin') ? '' : 'disabled'; ?> required>
                                <?php if ($auth->hasPermission('admin')): ?>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['employee_id']; ?>" 
                                                <?php echo ($emp['employee_id'] == ($_SESSION['employee_id'] ?? 0)) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['employee_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="<?php echo $_SESSION['employee_id']; ?>">
                                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name'] . ' (' . $_SESSION['employee_code'] . ')'); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Loan Amount (₦) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₦</span>
                                <input type="number" class="form-control" id="loanAmount" name="loan_amount" 
                                       min="1000" step="1000" required>
                            </div>
                            <small class="text-muted" id="maxAmountHint"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Repayment Period (Months) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="repaymentPeriod" name="tenure_months" 
                                   min="1" max="36" value="12" required>
                            <small class="text-muted" id="maxTenureHint"></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Purpose <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" required 
                                  placeholder="Please specify the purpose of this loan"></textarea>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Loan Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <small class="text-muted">Interest Rate:</small>
                                        <div class="fw-bold">0%</div>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Monthly Payment:</small>
                                        <div id="monthlyPayment" class="fw-bold">-</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <small class="text-muted">Total Repayable:</small>
                                        <div id="totalRepayable" class="fw-bold">-</div>
                                    </div>
                                    <!-- Optionally hide Total Interest or show 0 -->
                                    <div class="mb-2">
                                        <small class="text-muted">Total Interest:</small>
                                        <div class="fw-bold">₦0.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitLoanBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Submit Application
                    </button>
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
<?php include '../../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    // Initialize DataTables
    const loansTable = $('#loansTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[6, 'desc']],
        dom: '<"d-flex justify-content-between align-items-center mb-3"f<"ms-3"l>>rtip'
    });

    const advancesTable = $('#advancesTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[2, 'desc']],
        dom: '<"d-flex justify-content-between align-items-center mb-3"f<"ms-3"l>>rtip'
    });

    const repaymentsTable = $('#repaymentsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[3, 'desc']],
        dom: '<"d-flex justify-content-between align-items-center mb-3"f<"ms-3"l>>rtip'
    });

    // Loan type change handler
    $('#loanType').on('change', function() {
        updateLoanHints();
        calculateLoan();
    });

    // Loan amount and tenure change handlers
    $('#loanAmount, #repaymentPeriod').on('input', function() {
        calculateLoan();
    });

    // Update loan hints based on selected loan type
    function updateLoanHints() {
        const selectedOption = $('#loanType option:selected');
        const maxAmount = selectedOption.data('max-amount');
        const maxTenure = selectedOption.data('max-tenure');
        
        if (maxAmount) {
            $('#maxAmountHint').text(`Maximum: ₦${parseFloat(maxAmount).toLocaleString()}`);
            $('#loanAmount').attr('max', maxAmount);
        } else {
            $('#maxAmountHint').text('No maximum limit');
            $('#loanAmount').removeAttr('max');
        }
        
        if (maxTenure) {
            $('#maxTenureHint').text(`Maximum: ${maxTenure} months`);
            $('#repaymentPeriod').attr('max', maxTenure);
        } else {
            $('#maxTenureHint').text('No maximum limit');
            $('#repaymentPeriod').removeAttr('max');
        }
        
        // Update interest rate display
        const loanText = selectedOption.text();
        const rateMatch = loanText.match(/\(([\d.]+)%/);
        if (rateMatch) {
            $('#interestRateDisplay').text(rateMatch[1] + '%');
        }
    }

    // Calculate loan details
    function calculateLoan() {
        const amount = parseFloat($('#loanAmount').val()) || 0;
        const tenure = parseInt($('#repaymentPeriod').val()) || 0;
        
        if (amount > 0 && tenure > 0) {
            const monthly_repayment = amount / tenure;
            const total_repayable = amount;
            const total_interest = 0;
            
            $('#interestRateDisplay').text('0%');
            $('#monthlyPayment').text('₦' + monthly_repayment.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
            $('#totalInterest').text('₦0.00');
            $('#totalRepayable').text('₦' + total_repayable.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        } else {
            resetSummary();
        }
    }

    // Client-side calculation fallback
    function calculateClientSide(amount, rate, tenure) {
        const calculation = calculateLoanRepayment(amount, rate, tenure);
        updateLoanSummary(calculation);
    }


    // Update loan summary display
    function updateLoanSummary(calculation) {
        $('#monthlyPayment').text('₦' + calculation.monthly_repayment.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
        $('#totalInterest').text('₦0.00'); // Always zero
        $('#totalRepayable').text('₦' + calculation.total_repayable.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
        
        $('#totalRepayable').text('₦' + calculation.total_repayable.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
    }

    // Reset summary
    function resetSummary() {
        $('#monthlyPayment').text('-');
        $('#totalInterest').text('-');
        $('#totalRepayable').text('-');
    }

    // Show alert
    function showAlert(message, type = 'info', container = '#loanFormAlert') {
        const alertDiv = $(container);
        alertDiv.removeClass('d-none alert-success alert-danger alert-warning alert-info')
               .addClass(`alert-${type} show`)
               .html(message);
    }

    // Hide alert
    function hideAlert(container = '#loanFormAlert') {
        $(container).addClass('d-none').removeClass('show');
    }

    // Loan application form submission
    $('#loanApplicationForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const spinner = submitBtn.find('.spinner-border');
        
        // Show loading state
        submitBtn.prop('disabled', true);
        spinner.removeClass('d-none');
        hideAlert();
        
        // Get form data
        const formData = {
            loan_type_id: $('#loanType').val(),
            employee_id: $('#employeeId').val(),
            loan_amount: parseFloat($('#loanAmount').val()),
            tenure_months: parseInt($('#repaymentPeriod').val()),
            purpose: $('#purpose').val()
        };
        
        // Validation
        if (!formData.loan_type_id || !formData.employee_id || !formData.loan_amount || !formData.tenure_months || !formData.purpose) {
            showAlert('Please fill in all required fields.', 'danger');
            submitBtn.prop('disabled', false);
            spinner.addClass('d-none');
            return;
        }
        
        if (formData.loan_amount <= 0) {
            showAlert('Please enter a valid loan amount.', 'danger');
            submitBtn.prop('disabled', false);
            spinner.addClass('d-none');
            return;
        }
        
        // Submit via API
        $.ajax({
            url: '../../api/loans/index.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    showAlert('Loan application submitted successfully!', 'success');
                    form[0].reset();
                    resetSummary();
                    $('#applyLoanModal').modal('hide');
                    
                    // Reload page after delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(response.message || 'Error submitting loan application.', 'danger');
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while processing your request.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    // Use default error message
                }
                showAlert(errorMessage, 'danger');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                spinner.addClass('d-none');
            }
        });
    });

    // Salary advance form submission
    $('#requestAdvanceForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        
        // Show loading state
        const originalBtnText = submitBtn.html();
        submitBtn.prop('disabled', true).html(`
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            Processing...
        `);
        
        hideAlert('#advanceFormAlert');
        
        // Get form data
        const formData = {
            employee_id: form.find('[name="employee_id"]').val(),
            advance_amount: parseFloat(form.find('[name="advance_amount"]').val()),
            repayment_period_months: parseInt(form.find('[name="repayment_period_months"]').val()),
            reason: form.find('[name="reason"]').val()
        };
        
        // Validation
        if (!formData.employee_id || !formData.advance_amount || !formData.repayment_period_months || !formData.reason) {
            showAlert('Please fill in all required fields.', 'danger', '#advanceFormAlert');
            submitBtn.prop('disabled', false).html(originalBtnText);
            return;
        }
        
        if (formData.advance_amount <= 0) {
            showAlert('Please enter a valid advance amount.', 'danger', '#advanceFormAlert');
            submitBtn.prop('disabled', false).html(originalBtnText);
            return;
        }
        
        // Submit via API
        $.ajax({
            url: '../../api/loans/advances.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    showAlert('Salary advance requested successfully!', 'success', '#advanceFormAlert');
                    form[0].reset();
                    $('#requestAdvanceModal').modal('hide');
                    
                    // Reload page after delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(response.message || 'Error requesting salary advance.', 'danger', '#advanceFormAlert');
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while processing your request.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    // Use default error message
                }
                showAlert(errorMessage, 'danger', '#advanceFormAlert');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    });

    // Approve loan
    $(document).on('click', '.approve-loan', function() {
        const loanId = $(this).data('id');
        if (confirm('Are you sure you want to approve this loan?')) {
            updateLoanStatus(loanId, 'approved');
        }
    });

    // Reject loan
    $(document).on('click', '.reject-loan', function() {
        const loanId = $(this).data('id');
        if (confirm('Are you sure you want to reject this loan?')) {
            updateLoanStatus(loanId, 'rejected');
        }
    });

    // Update loan status
    function updateLoanStatus(loanId, status) {
        $.ajax({
            url: `../../api/loans/index.php?id=${loanId}`,
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ status: status }),
            success: function(response) {
                if (response.success) {
                    alert('Loan status updated successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while processing your request.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    // Use default error message
                }
                alert('Error: ' + errorMessage);
            }
        });
    }

    
    // View loan details
    $(document).on('click', '.view-loan', function() {
        const loanId = $(this).data('id');
        loadLoanDetails(loanId);
    });

    // Load loan details
    function loadLoanDetails(loanId) {
        const modal = $('#loanDetailsModal');
        modal.find('.modal-body').html(`
            <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading loan details...</p>
            </div>
        `);
        modal.modal('show');
        
        $.getJSON(`../../api/loans/index.php?id=${loanId}`, function(response) {
            if (response.success) {
                const loan = response.data;
                renderLoanDetails(loan);
            } else {
                modal.find('.modal-body').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading loan details: ${response.message}
                    </div>
                `);
            }
        }).fail(function() {
            modal.find('.modal-body').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Failed to load loan details. Please try again.
                </div>
            `);
        });
    }

    // Render loan details
    function renderLoanDetails(loan) {
        const modal = $('#loanDetailsModal');
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <small class="text-muted">Loan ID:</small>
                        <div class="fw-bold">#${loan.loan_id}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Employee:</small>
                        <div class="fw-bold">${loan.first_name} ${loan.last_name}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Employee Code:</small>
                        <div class="fw-bold">${loan.employee_code}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Loan Type:</small>
                        <div class="fw-bold">${loan.loan_name}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <small class="text-muted">Loan Amount:</small>
                        <div class="fw-bold">₦${parseFloat(loan.loan_amount).toLocaleString()}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Interest Rate:</small>
                        <div class="fw-bold">${loan.interest_rate}%</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Tenure:</small>
                        <div class="fw-bold">${loan.tenure_months} months</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Status:</small>
                        <div>${getStatusBadgeHtml(loan.status)}</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <h6 class="border-bottom pb-2">Repayment Schedule</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th>Principal</th>
                                    <th>Total Due</th>
                                    <th>Paid Date</th>
                                    <th>Amount Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
        `;
        
        if (loan.repayment_schedule && loan.repayment_schedule.length > 0) {
            loan.repayment_schedule.forEach(payment => {
                html += `
                    <tr>
                        <td>${payment.installment_number}</td>
                        <td>${formatDate(payment.due_date)}</td>
                        <td>₦${parseFloat(payment.principal_amount).toLocaleString()}</td>
                        <td>₦${parseFloat(payment.amount_due).toLocaleString()}</td>
                        <td>${payment.paid_date ? formatDate(payment.paid_date) : '-'}</td>
                        <td>${payment.amount_paid ? '₦' + parseFloat(payment.amount_paid).toLocaleString() : '-'}</td>
                        <td>${getStatusBadgeHtml(payment.status)}</td>
                    </tr>
                `;
            });
        } else {
            html += `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        No repayment schedule available.
                    </td>
                </tr>
            `;
        }
        
        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        modal.find('.modal-body').html(html);
    }

    // Helper function for status badges
    function getStatusBadgeHtml(status) {
        const statuses = {
            'pending': 'warning',
            'approved': 'success',
            'rejected': 'danger',
            'completed': 'info',
            'active': 'primary',
            'disbursed': 'info',
            'defaulted': 'danger',
            'paid': 'success',
            'overdue': 'warning',
            'partial': 'warning'
        };
        
        const color = statuses[status.toLowerCase()] || 'secondary';
        return `<span class="badge bg-${color}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
    }

    // Helper function for date formatting
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>