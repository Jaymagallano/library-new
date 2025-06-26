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
log_admin_activity($_SESSION["user_id"], 'books_page_access', $conn);

// Initialize variables
$books = [];
$total_books = 0;
$search = "";
$category_filter = "";
$status_filter = "all";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['category'])) {
        $category_filter = trim($_GET['category']);
    }
    if (isset($_GET['status'])) {
        $status_filter = trim($_GET['status']);
    }
}

// Handle book actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['book_id'])) {
                    // Check if book has active borrowings
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowings WHERE book_id = ? AND status = 'active'");
                    $stmt->bind_param("i", $_POST['book_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($row['count'] > 0) {
                        $_SESSION['error_message'] = "Cannot delete book with active borrowings";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
                        $stmt->bind_param("i", $_POST['book_id']);
                        $stmt->execute();
                        if ($stmt->affected_rows > 0) {
                            log_admin_activity($_SESSION["user_id"], 'book_deleted', $conn, null, $_POST['book_id']);
                            $_SESSION['success_message'] = "Book deleted successfully";
                        } else {
                            $_SESSION['error_message'] = "Failed to delete book";
                        }
                        $stmt->close();
                    }
                }
                break;
                
            case 'update_status':
                if (isset($_POST['book_id']) && isset($_POST['status'])) {
                    $stmt = $conn->prepare("UPDATE books SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $_POST['status'], $_POST['book_id']);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        log_admin_activity($_SESSION["user_id"], 'book_status_updated', $conn, null, $_POST['book_id']);
                        $_SESSION['success_message'] = "Book status updated successfully";
                    } else {
                        $_SESSION['error_message'] = "Failed to update book status";
                    }
                    $stmt->close();
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: books.php");
        exit;
    }
}

// Build query based on filters
$query = "SELECT * FROM books WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM books WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $count_query .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $count_query .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($status_filter != "all") {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
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
$total_books = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get books
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
$stmt->close();

// Get categories for filter
$categories = [];
$stmt = $conn->prepare("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_books / $per_page);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "Book Management";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-book"></i> Book Management</h1>
        <div class="header-actions">
            <button class="btn-primary" onclick="location.href='add_book.php'">
                <i class="fas fa-plus"></i> Add New Book
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
            <h2>Books List</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search books..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter == $category) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="available" <?php echo ($status_filter == 'available') ? 'selected' : ''; ?>>Available</option>
                                <option value="borrowed" <?php echo ($status_filter == 'borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                                <option value="reserved" <?php echo ($status_filter == 'reserved') ? 'selected' : ''; ?>>Reserved</option>
                                <option value="maintenance" <?php echo ($status_filter == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
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
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Copies</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No books found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><?php echo $book['id']; ?></td>
                                    <td><div class="truncate" title="<?php echo htmlspecialchars($book['title']); ?>"><?php echo htmlspecialchars($book['title']); ?></div></td>
                                    <td><div class="truncate" title="<?php echo htmlspecialchars($book['author']); ?>"><?php echo htmlspecialchars($book['author']); ?></div></td>
                                    <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                    <td><?php echo htmlspecialchars($book['category']); ?></td>
                                    <td>
                                        <?php echo $book['copies_available']; ?> / <?php echo $book['copies_total']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($book['status']) {
                                            case 'available':
                                                $status_class = 'badge-success';
                                                break;
                                            case 'borrowed':
                                                $status_class = 'badge-warning';
                                                break;
                                            case 'reserved':
                                                $status_class = 'badge-info';
                                                break;
                                            case 'maintenance':
                                                $status_class = 'badge-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <button type="button" class="btn-sm btn-secondary" title="Change Status" onclick="showStatusModal(<?php echo $book['id']; ?>, '<?php echo $book['status']; ?>')">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this book? This action cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                <button type="submit" class="btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
                        <a href="?page=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category='.urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">First</a>
                        <a href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category='.urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category='.urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category='.urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Next</a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category='.urlencode($category_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <div class="summary">
                Showing <?php echo min(($page - 1) * $per_page + 1, $total_books); ?> to <?php echo min($page * $per_page, $total_books); ?> of <?php echo $total_books; ?> books
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div id="statusModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Change Book Status</h2>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="book_id" id="modal_book_id">
            
            <div class="form-group">
                <label for="book_status">Status</label>
                <select name="status" id="book_status" class="form-control">
                    <option value="available">Available</option>
                    <option value="borrowed">Borrowed</option>
                    <option value="reserved">Reserved</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn-primary">Update Status</button>
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
</style>

<script>
    function showStatusModal(bookId, currentStatus) {
        document.getElementById('modal_book_id').value = bookId;
        document.getElementById('book_status').value = currentStatus;
        document.getElementById('statusModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('statusModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('statusModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php
// Include footer
include "../admin/includes/footer.php";
?>