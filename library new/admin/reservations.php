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
log_admin_activity($_SESSION["user_id"], 'reservations_page_access', $conn);

// Initialize variables
$reservations = [];
$total_reservations = 0;
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

// Handle reservation actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'fulfill':
                if (isset($_POST['reservation_id'])) {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Get reservation details
                        $stmt = $conn->prepare("SELECT r.user_id, r.book_id, b.copies_available 
                                               FROM reservations r
                                               JOIN books b ON r.book_id = b.id
                                               WHERE r.id = ? AND r.status = 'active'");
                        $stmt->bind_param("i", $_POST['reservation_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $reservation = $result->fetch_assoc();
                        $stmt->close();
                        
                        if ($reservation) {
                            // Check if book is available
                            if ($reservation['copies_available'] > 0) {
                                // Update reservation status
                                $stmt = $conn->prepare("UPDATE reservations SET status = 'fulfilled' WHERE id = ?");
                                $stmt->bind_param("i", $_POST['reservation_id']);
                                $stmt->execute();
                                $stmt->close();
                                
                                // Create borrowing record
                                $stmt = $conn->prepare("INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) 
                                                      VALUES (?, ?, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY), 'active')");
                                $stmt->bind_param("ii", $reservation['user_id'], $reservation['book_id']);
                                $stmt->execute();
                                $borrowing_id = $conn->insert_id;
                                $stmt->close();
                                
                                // Update book copies available
                                $stmt = $conn->prepare("UPDATE books SET copies_available = copies_available - 1 WHERE id = ?");
                                $stmt->bind_param("i", $reservation['book_id']);
                                $stmt->execute();
                                $stmt->close();
                                
                                // Update book status if no more copies available
                                $stmt = $conn->prepare("UPDATE books SET status = 'borrowed' WHERE id = ? AND copies_available = 0");
                                $stmt->bind_param("i", $reservation['book_id']);
                                $stmt->execute();
                                $stmt->close();
                                
                                // Log activity
                                log_admin_activity($_SESSION["user_id"], 'reservation_fulfilled', $conn, null, $_POST['reservation_id']);
                                
                                // Commit transaction
                                $conn->commit();
                                
                                $_SESSION['success_message'] = "Reservation fulfilled and borrowing created successfully";
                            } else {
                                throw new Exception("No copies available for this book");
                            }
                        } else {
                            throw new Exception("Reservation not found or already fulfilled");
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $_SESSION['error_message'] = "Error: " . $e->getMessage();
                    }
                }
                break;
                
            case 'cancel':
                if (isset($_POST['reservation_id'])) {
                    $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND status = 'active'");
                    $stmt->bind_param("i", $_POST['reservation_id']);
                    $stmt->execute();
                    
                    if ($stmt->affected_rows > 0) {
                        log_admin_activity($_SESSION["user_id"], 'reservation_cancelled', $conn, null, $_POST['reservation_id']);
                        $_SESSION['success_message'] = "Reservation cancelled successfully";
                    } else {
                        $_SESSION['error_message'] = "Failed to cancel reservation";
                    }
                    $stmt->close();
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: reservations.php");
        exit;
    }
}

// Build query based on filters
$query = "SELECT r.*, u.username, u.full_name, b.title as book_title, b.isbn, b.copies_available 
          FROM reservations r
          JOIN users u ON r.user_id = u.id
          JOIN books b ON r.book_id = b.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total 
                FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN books b ON r.book_id = b.id
                WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
    $count_query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($status_filter != "all") {
    $query .= " AND r.status = ?";
    $count_query .= " AND r.status = ?";
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
$total_reservations = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY r.id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get reservations
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_reservations / $per_page);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "Reservations Management";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-bookmark"></i> Reservations Management</h1>
        <div class="header-actions">
            <button class="btn-primary" onclick="location.href='add_reservation.php'">
                <i class="fas fa-plus"></i> New Reservation
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
            <h2>Reservations List</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search reservations..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="fulfilled" <?php echo ($status_filter == 'fulfilled') ? 'selected' : ''; ?>>Fulfilled</option>
                                <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
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
                            <th>Reservation Date</th>
                            <th>Status</th>
                            <th>Book Availability</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No reservations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo $reservation['id']; ?></td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $reservation['user_id']; ?>" title="View User">
                                            <?php echo htmlspecialchars($reservation['full_name']); ?>
                                            <small>(<?php echo htmlspecialchars($reservation['username']); ?>)</small>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="edit_book.php?id=<?php echo $reservation['book_id']; ?>" title="View Book">
                                            <?php echo htmlspecialchars($reservation['book_title']); ?>
                                            <small>(<?php echo htmlspecialchars($reservation['isbn']); ?>)</small>
                                        </a>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($reservation['status']) {
                                            case 'active':
                                                $status_class = 'badge-success';
                                                break;
                                            case 'fulfilled':
                                                $status_class = 'badge-info';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'badge-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($reservation['copies_available'] > 0): ?>
                                            <span class="badge badge-success"><?php echo $reservation['copies_available']; ?> Available</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Not Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($reservation['status'] == 'active'): ?>
                                                <?php if ($reservation['copies_available'] > 0): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="fulfill">
                                                        <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                        <button type="submit" class="btn-sm btn-success" title="Fulfill Reservation">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn-sm btn-secondary" disabled title="No Copies Available">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-danger" title="Cancel Reservation">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn-sm btn-secondary" disabled title="No Actions Available">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <a href="view_reservation.php?id=<?php echo $reservation['id']; ?>" class="btn-sm btn-info" title="View Details">
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
                Showing <?php echo min(($page - 1) * $per_page + 1, $total_reservations); ?> to <?php echo min($page * $per_page, $total_reservations); ?> of <?php echo $total_reservations; ?> reservations
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include "../admin/includes/footer.php";
?>