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
log_admin_activity($_SESSION["user_id"], 'users_page_access', $conn);

// Initialize variables
$users = [];
$total_users = 0;
$search = "";
$role_filter = "";
$status_filter = "all";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['role'])) {
        $role_filter = trim($_GET['role']);
    }
    if (isset($_GET['status'])) {
        $status_filter = trim($_GET['status']);
    }
}

// Handle user actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'activate':
                if (isset($_POST['user_id'])) {
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->bind_param("i", $_POST['user_id']);
                    $stmt->execute();
                    $stmt->close();
                    log_admin_activity($_SESSION["user_id"], 'user_activated', $conn, null, $_POST['user_id']);
                }
                break;
                
            case 'deactivate':
                if (isset($_POST['user_id'])) {
                    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                    $stmt->bind_param("i", $_POST['user_id']);
                    $stmt->execute();
                    $stmt->close();
                    log_admin_activity($_SESSION["user_id"], 'user_deactivated', $conn, null, $_POST['user_id']);
                }
                break;
                
            case 'delete':
                if (isset($_POST['user_id'])) {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Check if user has borrowings
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ?");
                        $stmt->bind_param("i", $_POST['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        
                        if ($row['count'] > 0) {
                            throw new Exception("Cannot delete user with active borrowings");
                        }
                        
                        // Check if user is admin
                        $stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ?");
                        $stmt->bind_param("i", $_POST['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $stmt->close();
                        
                        if (!$user || $user['role_id'] == 1) {
                            throw new Exception("Cannot delete admin users");
                        }
                        
                        // Delete related notifications first
                        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                        $stmt->bind_param("i", $_POST['user_id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Delete related reservations
                        $stmt = $conn->prepare("DELETE FROM reservations WHERE user_id = ?");
                        $stmt->bind_param("i", $_POST['user_id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Delete related activity logs
                        $stmt = $conn->prepare("DELETE FROM user_activity_log WHERE user_id = ?");
                        $stmt->bind_param("i", $_POST['user_id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Finally delete the user
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->bind_param("i", $_POST['user_id']);
                        $stmt->execute();
                        
                        if ($stmt->affected_rows > 0) {
                            // Commit transaction
                            $conn->commit();
                            log_admin_activity($_SESSION["user_id"], 'user_deleted', $conn, null, $_POST['user_id']);
                            $_SESSION['success_message'] = "User deleted successfully";
                        } else {
                            throw new Exception("Failed to delete user");
                        }
                        $stmt->close();
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $_SESSION['error_message'] = $e->getMessage();
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header("Location: users.php");
        exit;
    }
}

// Build query based on filters
$query = "SELECT u.*, r.name as role_name FROM users u 
          JOIN roles r ON u.role_id = r.id 
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $count_query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $query .= " AND u.role_id = ?";
    $count_query .= " AND u.role_id = ?";
    $params[] = $role_filter;
    $types .= "i";
}

if ($status_filter != "all") {
    $query .= " AND u.status = ?";
    $count_query .= " AND u.status = ?";
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
$total_users = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY u.id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get users
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Get roles for filter
$roles = [];
$stmt = $conn->prepare("SELECT id, name FROM roles ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_users / $per_page);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "User Management";
include "../admin/includes/header.php";
?>

<div class="main-content responsive-container">
    <div class="header">
        <h1><i class="fas fa-users"></i> User Management</h1>
        <div class="header-actions">
            <button class="btn-primary btn-responsive" onclick="location.href='add_user.php'">
                <i class="fas fa-plus"></i> Add New User
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
    
    <div class="card card-responsive">
        <div class="card-header">
            <h2>Users List</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-responsive">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="role" class="form-control">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo ($role_filter == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
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
            <div class="table-container">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th class="col-id">ID</th>
                            <th class="col-user">Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th class="col-status">Status</th>
                            <th class="col-time">Created</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="col-id"><?php echo $user['id']; ?></td>
                                    <td class="col-user"><div class="text-truncate" title="<?php echo htmlspecialchars($user['username']); ?>"><?php echo htmlspecialchars($user['username']); ?></div></td>
                                    <td><div class="text-truncate" title="<?php echo htmlspecialchars($user['full_name']); ?>"><?php echo htmlspecialchars($user['full_name']); ?></div></td>
                                    <td><div class="text-truncate" title="<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></div></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                                    <td class="col-status">
                                        <?php if ($user['status'] == 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-time"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="col-actions">
                                        <div class="btn-group-responsive">
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($user['role_id'] != 1): // Prevent actions on admin users ?>
                                                <?php if ($user['status'] == 'active'): ?>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn-sm btn-warning" title="Deactivate">
                                                            <i class="fas fa-user-slash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn-sm btn-success" title="Activate">
                                                            <i class="fas fa-user-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
                        <a href="?page=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">First</a>
                        <a href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Next</a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?><?php echo $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" class="page-link">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <div class="summary">
                Showing <?php echo min(($page - 1) * $per_page + 1, $total_users); ?> to <?php echo min($page * $per_page, $total_users); ?> of <?php echo $total_users; ?> users
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include "../admin/includes/footer.php";
?>