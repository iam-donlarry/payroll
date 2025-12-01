<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requirePermission('payroll_master');

$page_title = "Manage Payment Titles";
$body_class = "payment-titles-page";

$message = '';

// Handle Add Title
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_title'])) {
    $new_title = sanitizeInput($_POST['title']);
    
    if (!empty($new_title)) {
        try {
            $stmt = $db->prepare("INSERT INTO payment_titles (title) VALUES (:title)");
            $stmt->execute([':title' => $new_title]);
            $message = '<div class="alert alert-success">Payment title added successfully.</div>';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $message = '<div class="alert alert-warning">This title already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error adding title: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Title cannot be empty.</div>';
    }
}

// Handle Delete Title
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $db->prepare("DELETE FROM payment_titles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = '<div class="alert alert-success">Payment title deleted successfully.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Error deleting title: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all titles
$titles = [];
try {
    $stmt = $db->query("SELECT * FROM payment_titles ORDER BY title ASC");
    $titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Error fetching titles: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Manage Payment Titles</h1>
        <p class="text-muted mb-0">Add or remove titles for occasional payments.</p>
    </div>
    <a href="occasional_payments.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Payments
    </a>
</div>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Add New Title</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Payment Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Holiday Bonus" required>
                    </div>
                    <button type="submit" name="add_title" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Add Title
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Existing Titles</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th style="width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($titles)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No titles found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($titles as $t): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['title']); ?></td>
                                        <td class="text-center">
                                            <a href="?delete=<?php echo $t['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this title?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
