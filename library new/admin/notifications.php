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
log_admin_activity($_SESSION["user_id"], 'notifications_page_access', $conn);

// Initialize variables
$notifications = [];
$total_notifications = 0;
$search = "";
$type_filter = "";
$read_filter = "all";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['type'])) {
        $type_filter = trim($_GET['type']);
    }
    if (isset($_GET['read'])) {
        $read_filter = trim($_GET['read']);
    }
}

// Handle notification actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                if (isset($_POST['notification_id'])) {
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                    $stmt->bind_param("i", $_POST['notification_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    $_SESSION['success_message'] = "Notification marked as read";
                }
                break;
                
            case 'mark_unread':
                if (isset($_POST['notification_id'])) {
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 0 WHERE id = ?");
                    $stmt->bind_param("i", $_POST['notification_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    $_SESSION['success_message'] = "Notification marked as unread";
                }
                break;
                
            case 'delete':
                if (isset($_POST['notification_id'])) {
                    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
                    $stmt->bind_param("i", $_POST['notification_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    $_SESSION['success_message'] = "Notification deleted successfully";
                }
                break;
                
            case 'mark_all_read':
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                
                $_SESSION['success_message'] = "$affected notifications marked as read";
                break;
                
            case 'send_notification':
                if (isset($_POST['user_id']) && isset($_POST['message']) && isset($_POST['type'])) {
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $_POST['user_id'], $_POST['message'], $_POST['type']);
                    $stmt->execute();
                    $stmt->close();
                    
                    log_admin_activity($_SESSION["user_id"], 'notification_sent', $conn);
                    $_SESSION['success_message'] = "Notification sent successfully";
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: notifications.php");
        exit;
    }
}

// Build query based on filters
$query = "SELECT n.*, u.username, u.full_name 
          FROM notifications n
          JOIN users u ON n.user_id = u.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total 
                FROM notifications n
                JOIN users u ON n.user_id = u.id
                WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (n.message LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $count_query .= " AND (n.message LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($type_filter)) {
    $query .= " AND n.type = ?";
    $count_query .= " AND n.type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($read_filter != "all") {
    $is_read = ($read_filter == "read") ? 1 : 0;
    $query .= " AND n.is_read = ?";
    $count_query .= " AND n.is_read = ?";
    $params[] = $is_read;
    $types .= "i";
}

// Get total count for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_notifications = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get notifications
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get users for notification form
$users = [];
$stmt = $conn->prepare("SELECT id, username, full_name FROM users ORDER BY full_name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_notifications / $per_page);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "Notifications Management";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-bell"></i> Notifications Management</h1>
        <div class="header-actions">
            <button class="btn-primary" onclick="showSendModal()">
                <i class="fas fa-paper-plane"></i> Send Notification
            </button>
            
            <form method="POST" action="" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-secondary">
                    <i class="fas fa-check-double"></i> Mark All Read
                </button>
            </form>
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
            <h2>Notifications List</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="type" class="form-control">
                                <option value="">All Types</option>
                                <option value="info" <?php echo ($type_filter == 'info') ? 'selected' : ''; ?>>Info</option>
                                <option value="warning" <?php echo ($type_filter == 'warning') ? 'selected' : ''; ?>>Warning</option>
                                <option value="reminder" <?php echo ($type_filter == 'reminder') ? 'selected' : ''; ?>>Reminder</option>
                                <option value="alert" <?php echo ($type_filter == 'alert') ? 'selected' : ''; ?>>Alert</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="read" class="form-control">
                                <option value="all" <?php echo ($read_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="read" <?php echo ($read_filter == 'read') ? 'selected' : ''; ?>>Read</option>
                                <option value="unread" <?php echo ($read_filter == 'unread') ? 'selected' : ''; ?>>Unread</option>
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
                            <th>Message</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No notifications found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <tr class="<?php echo $notification['is_read'] ? '' : 'unread-row'; ?>">
                                    <td><?php echo $notification['id']; ?></td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $notification['user_id']; ?>" title="View User">
                                            <span class="text-truncate" title="<?php echo htmlspecialchars($notification['full_name']); ?>">
                                                <?php echo htmlspecialchars($notification['full_name']); ?>
                                            </span>
                                            <small>(<?php echo htmlspecialchars($notification['username']); ?>)</small>
                                        </a>
                                    </td>
                                    <td><div class="text-truncate" title="<?php echo htmlspecialchars($notification['message']); ?>"><?php echo htmlspecialchars($notification['message']); ?></div></td>
                                    <td>
                                        <?php
                                        $type_class = '';
                                        switch ($notification['type']) {
                                            case 'info':
                                                $type_class = 'badge-info';
                                                break;
                                            case 'warning':
                                                $type_class = 'badge-warning';
                                                break;
                                            case 'reminder':
                                                $type_class = 'badge-primary';
                                                break;
                                            case 'alert':
                                                $type_class = 'badge-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $type_class; ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($notification['is_read']): ?>
                                            <span class="badge badge-success">Read</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($notification['is_read']): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="mark_unread">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-primary" title="Mark as Unread">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-success" title="Mark as Read">
                                                        <i class="fas fa-envelope-open"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
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
                        <a href="?page=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type='.$type_filter : ''; ?><?php echo $read_filter != 'all' ? '&read='.$read_filter : ''; ?>" class="page-link">First</a>
                        <a href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type='.$type_filter : ''; ?><?php echo $read_filter != 'all' ? '&read='.$read_filter : ''; ?>" class="page-link">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type='.$type_filter : ''; ?><?php echo $read_filter != 'all' ? '&read='.$read_filter : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type='.$type_filter : ''; ?><?php echo $read_filter != 'all' ? '&read='.$read_filter : ''; ?>" class="page-link">Next</a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type='.$type_filter : ''; ?><?php echo $read_filter != 'all' ? '&read='.$read_filter : ''; ?>" class="page-link">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <div class="summary">
                Showing <?php echo min(($page - 1) * $per_page + 1, $total_notifications); ?> to <?php echo min($page * $per_page, $total_notifications); ?> of <?php echo $total_notifications; ?> notifications
            </div>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div id="sendModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Send Notification</h2>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="send_notification">
            
            <div class="form-group">
                <label for="user_id">User</label>
                <select name="user_id" id="user_id" class="form-control" required>
                    <option value="">Select User</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="type">Notification Type</label>
                <select name="type" id="type" class="form-control" required>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="reminder">Reminder</option>
                    <option value="alert">Alert</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="message">Message</label>
                <textarea name="message" id="message" class="form-control" rows="4" required></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn-primary">Send Notification</button>
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
        width: 500px;
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
    
    .unread-row {
        background-color: rgba(74, 105, 189, 0.05);
        font-weight: 500;
    }
    
    .unread-row:hover {
        background-color: rgba(74, 105, 189, 0.1) !important;
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }
    
    .header-actions {
        display: flex;
        gap: 10px;
    }
</style>

<script>
    function showSendModal() {
        document.getElementById('sendModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('sendModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('sendModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php
// Include footer
include "../admin/includes/footer.php";
?>