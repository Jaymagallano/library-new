<?php
// Include necessary files
require_once "../config.php";
require_once "../admin_auth.php";

// Verify admin session
if (!verify_admin_session()) {
    header("Location: ../admin_login.php");
    exit;
}

// Log this page access
log_admin_activity($_SESSION["user_id"], 'borrowings_page_access', $conn);

// Initialize variables
$borrowings = [];
$total_borrowings = 0;
$search = "";
$status_filter = "all";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['status'])) {
        $status_filter = trim($_GET['status']);
    }
}

// Handle borrowing actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'return':
                if (isset($_POST['borrowing_id'])) {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Get borrowing details
                        $stmt = $conn->prepare("SELECT book_id FROM borrowings WHERE id = ?");
                        $stmt->bind_param("i", $_POST['borrowing_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $borrowing = $result->fetch_assoc();
                        $stmt->close();
                        
                        if ($borrowing) {
                            // Update borrowing status
                            $stmt = $conn->prepare("UPDATE borrowings SET status = 'returned', return_date = CURRENT_DATE() WHERE id = ?");
                            $stmt->bind_param("i", $_POST['borrowing_id']);
                            $stmt->execute();
                            $stmt->close();
                            
                            // Update book copies available
                            $stmt = $conn->prepare("UPDATE books SET copies_available = copies_available + 1 WHERE id = ?");
                            $stmt->bind_param("i", $borrowing['book_id']);
                            $stmt->execute();
                            $stmt->close();
                            
                            // Update book status if all copies are now available
                            $stmt = $conn->prepare("UPDATE books SET status = 'available' WHERE id = ? AND copies_available = copies_total");
                            $stmt->bind_param("i", $borrowing['book_id']);
                            $stmt->execute();
                            $stmt->close();
                            
                            // Log activity
                            log_admin_activity($_SESSION["user_id"], 'borrowing_returned', $conn, null, $_POST['borrowing_id']);
                            
                            // Commit transaction
                            $conn->commit();
                            
                            $_SESSION['success_message'] = "Book returned successfully";
                        } else {
                            throw new Exception("Borrowing record not found");
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $_SESSION['error_message'] = "Error: " . $e->getMessage();
                    }
                }
                break;
                
            case 'extend':
                if (isset($_POST['borrowing_id']) && isset($_POST['extend_days'])) {
                    $extend_days = (int)$_POST['extend_days'];
                    
                    if ($extend_days > 0) {
                        $stmt = $conn->prepare("UPDATE borrowings SET due_date = DATE_ADD(due_date, INTERVAL ? DAY) WHERE id = ? AND status = 'active'");
                        $stmt->bind_param("ii", $extend_days, $_POST['borrowing_id']);
                        $stmt->execute();
                        
                        if ($stmt->affected_rows > 0) {
                            log_admin_activity($_SESSION["user_id"], 'borrowing_extended', $conn, null, $_POST['borrowing_id']);
                            $_SESSION['success_message'] = "Due date extended successfully";
                        } else {
                            $_SESSION['error_message'] = "Failed to extend due date";
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['error_message'] = "Invalid extension days";
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: borrowings.php");
        exit;
    }
}

// Build query based on filters
$query = "SELECT b.*, u.username, u.full_name, bk.title as book_title, bk.isbn 
          FROM borrowings b
          JOIN users u ON b.user_id = u.id
          JOIN books bk ON b.book_id = bk.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total 
                FROM borrowings b
                JOIN users u ON b.user_id = u.id
                JOIN books bk ON b.book_id = bk.id
                WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR bk.title LIKE ? OR bk.isbn LIKE ?)";
    $count_query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR bk.title LIKE ? OR bk.isbn LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($status_filter != "all") {
    $query .= " AND b.status = ?";
    $count_query .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get total count for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_borrowings = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY b.id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get borrowings
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $borrowings[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_borrowings / $per_page);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "Borrowings Management";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-exchange-alt"></i> Borrowings Management</h1>
        <div class="header-actions">
            <button class="btn-primary" onclick="location.href='add_borrowing.php'">
                <i class="fas fa-plus"></i> New Borrowing
            </button>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2>Borrowings List</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search borrowings..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="returned" <?php echo ($status_filter == 'returned') ? 'selected' : ''; ?>>Returned</option>
                                <option value="overdue" <?php echo ($status_filter == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-primary btn-responsive">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($borrowings)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No borrowings found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($borrowings as $borrowing): ?>
                                <?php
                                // Calculate if overdue
                                $is_overdue = false;
                                $days_overdue = 0;
                                
                                if ($borrowing['status'] == 'active') {
                                    $due_date = new DateTime($borrowing['due_date']);
                                    $today = new DateTime();
                                    
                                    if ($today > $due_date) {
                                        $is_overdue = true;
                                        $interval = $today->diff($due_date);
                                        $days_overdue = $interval->days;
                                        
                                        // Update status to overdue if not already
                                        if ($borrowing['status'] != 'overdue') {
                                            $stmt = $conn->prepare("UPDATE borrowings SET status = 'overdue' WHERE id = ?");
                                            $stmt->bind_param("i", $borrowing['id']);
                                            $stmt->execute();
                                            $stmt->close();
                                            $borrowing['status'] = 'overdue';
                                        }
                                    }
                                }
                                
                                // Calculate fine amount (if applicable)
                                $fine_amount = $borrowing['fine_amount'];
                                if ($is_overdue && $fine_amount == 0) {
                                    // Example: $1 per day overdue
                                    $fine_amount = $days_overdue * 1.00;
                                    
                                    // Update fine amount in database
                                    $stmt = $conn->prepare("UPDATE borrowings SET fine_amount = ? WHERE id = ?");
                                    $stmt->bind_param("di", $fine_amount, $borrowing['id']);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                                ?>
                                <tr class="<?php echo $is_overdue ? 'overdue-row' : ''; ?>">
                                    <td><?php echo $borrowing['id']; ?></td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $borrowing['user_id']; ?>" title="View User">
                                            <span class="text-truncate" title="<?php echo htmlspecialchars($borrowing['full_name']); ?>">
                                                <?php echo htmlspecialchars($borrowing['full_name']); ?>
                                            </span>
                                            <small>(<?php echo htmlspecialchars($borrowing['username']); ?>)</small>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="edit_book.php?id=<?php echo $borrowing['book_id']; ?>" title="View Book">
                                            <span class="text-truncate" title="<?php echo htmlspecialchars($borrowing['book_title']); ?>">
                                                <?php echo htmlspecialchars($borrowing['book_title']); ?>
                                            </span>
                                            <small>(<?php echo htmlspecialchars($borrowing['isbn']); ?>)</small>
                                        </a>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($borrowing['borrow_date'])); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($borrowing['due_date'])); ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge badge-danger"><?php echo $days_overdue; ?> days overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $borrowing['return_date'] ? date('M d, Y', strtotime($borrowing['return_date'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($borrowing['status']) {
                                            case 'active':
                                                $status_class = 'badge-success';
                                                break;
                                            case 'returned':
                                                $status_class = 'badge-info';
                                                break;
                                            case 'overdue':
                                                $status_class = 'badge-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($borrowing['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($fine_amount > 0): ?>
                                            <span class="badge badge-warning">$<?php echo number_format($fine_amount, 2); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($borrowing['status'] == 'active' || $borrowing['status'] == 'overdue'): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="return">
                                                    <input type="hidden" name="borrowing_id" value="<?php echo $borrowing['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-success" title="Return Book">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                                
                                                <button type="button" class="btn-sm btn-primary" title="Extend Due Date" onclick="showExtendModal(<?php echo $borrowing['id']; ?>)">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn-sm btn-secondary" disabled title="No Actions Available">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <a href="view_borrowing.php?id=<?php echo $borrowing['id']; ?>" class="btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">First</a>
                        <a href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Next</a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <div class="summary">
                Showing <?php echo min(($page - 1) * $per_page + 1, $total_borrowings); ?> to <?php echo min($page * $per_page, $total_borrowings); ?> of <?php echo $total_borrowings; ?> borrowings
            </div>
        </div>
    </div>
</div>

<!-- Extend Due Date Modal -->
<div id="extendModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Extend Due Date</h2>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="extend">
            <input type="hidden" name="borrowing_id" id="modal_borrowing_id">
            
            <div class="form-group">
                <label for="extend_days">Days to Extend</label>
                <input type="number" name="extend_days" id="extend_days" class="form-control" min="1" max="30" value="7">
                <small class="form-text">Enter the number of days to extend the due date</small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn-primary">Extend Due Date</button>
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Modal Styles */
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        width: 400px;
        max-width: 90%;
        box-shadow: var(--shadow-lg);
        position: relative;
    }
    
    .close {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        cursor: pointer;
        color: var(--gray-color);
    }
    
    .close:hover {
        color: var(--dark-color);
    }
    
    .form-text {
        font-size: 12px;
        color: var(--gray-color);
        margin-top: 5px;
    }
    
    .overdue-row {
        background-color: rgba(231, 76, 60, 0.05);
    }
    
    .overdue-row:hover {
        background-color: rgba(231, 76, 60, 0.1) !important;
    }
</style>

<script>
    function showExtendModal(borrowingId) {
        document.getElementById('modal_borrowing_id').value = borrowingId;
        document.getElementById('extendModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('extendModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('extendModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php
// Include footer
include "../admin/includes/footer.php";
?>