<?php
// Include database connection and admin authentication
require_once "config.php";
require_once "admin_auth.php";

// Verify admin session
if (!verify_admin_session()) {
    // Redirect to admin login
    header("Location: admin_login.php");
    exit;
}

// Get admin information
$admin_id = $_SESSION["user_id"];
$admin_name = $_SESSION["full_name"];

// Log this dashboard access
log_admin_activity($admin_id, 'dashboard_access', $conn);

// Get statistics
$stats = [
    'users' => 0,
    'books' => 0,
    'borrowings' => 0,
    'reservations' => 0
];

// Get users count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['users'] = $row['count'];
}
$stmt->close();

// Get books count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM books");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['books'] = $row['count'];
}
$stmt->close();

// Get active borrowings count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowings WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['borrowings'] = $row['count'];
}
$stmt->close();

// Get active reservations count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats['reservations'] = $row['count'];
}
$stmt->close();

// Get recent admin activities
$admin_activities = [];
$stmt = $conn->prepare("
    SELECT a.*, u.username, u.full_name 
    FROM admin_activity_log a
    LEFT JOIN users u ON a.admin_id = u.id
    ORDER BY a.timestamp DESC
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admin_activities[] = $row;
}
$stmt->close();

// Get recent user activities
$user_activities = [];
$stmt = $conn->prepare("
    SELECT a.*, u.username, u.full_name 
    FROM user_activity_log a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.timestamp DESC
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_activities[] = $row;
}
$stmt->close();

// Include header
$page_title = "Admin Dashboard";
include __DIR__ . "/admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
        <div class="user-info">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=4a69bd&color=fff" alt="Admin">
            <div>
                <div class="user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>
    
    <div class="security-alert alert alert-info">
        <i class="fas fa-shield-alt"></i>
        <div class="security-alert-content">
            <h4>Security Notice</h4>
            <p>Your session is secured with enhanced protection. Last login: <?php echo date('M d, Y H:i'); ?></p>
        </div>
    </div>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <i class="fas fa-users stat-icon"></i>
            <h3>Total Users</h3>
            <div class="stat-value"><?php echo $stats['users']; ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-book stat-icon"></i>
            <h3>Total Books</h3>
            <div class="stat-value"><?php echo $stats['books']; ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-exchange-alt stat-icon"></i>
            <h3>Active Borrowings</h3>
            <div class="stat-value"><?php echo $stats['borrowings']; ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-bookmark stat-icon"></i>
            <h3>Active Reservations</h3>
            <div class="stat-value"><?php echo $stats['reservations']; ?></div>
        </div>
    </div>
    
    <div class="row">
        <div class="dashboard-columns">
            <div class="card">
                <div class="card-header">
                    <h2>Recent Admin Activity</h2>
                    <a href="/library%20new/admin/activity_logs.php" class="view-all">View All</a>
                </div>
                <div class="card-body">
                    <ul class="activity-list">
                        <?php if (empty($admin_activities)): ?>
                            <li class="activity-item">No recent admin activities found</li>
                        <?php else: ?>
                            <?php foreach ($admin_activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $icon = 'fas fa-info-circle';
                                        switch ($activity['action']) {
                                            case 'login_success':
                                                $icon = 'fas fa-sign-in-alt';
                                                break;
                                            case 'login_failed':
                                                $icon = 'fas fa-exclamation-triangle';
                                                break;
                                            case 'user_created':
                                            case 'user_updated':
                                            case 'user_deleted':
                                                $icon = 'fas fa-user-edit';
                                                break;
                                            case 'book_added':
                                            case 'book_updated':
                                            case 'book_deleted':
                                                $icon = 'fas fa-book';
                                                break;
                                            case 'borrowing_created':
                                            case 'borrowing_returned':
                                                $icon = 'fas fa-exchange-alt';
                                                break;
                                            case 'reservation_created':
                                            case 'reservation_fulfilled':
                                            case 'reservation_cancelled':
                                                $icon = 'fas fa-bookmark';
                                                break;
                                            case 'dashboard_access':
                                                $icon = 'fas fa-tachometer-alt';
                                                break;
                                        }
                                        ?>
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <h4>
                                            <?php
                                            $action = str_replace('_', ' ', $activity['action']);
                                            echo ucwords($action);
                                            ?>
                                        </h4>
                                        <p>
                                            <?php if (!empty($activity['full_name'])): ?>
                                                By <?php echo htmlspecialchars($activity['full_name']); ?>
                                            <?php else: ?>
                                                System action
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('H:i', strtotime($activity['timestamp'])); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Recent User Activity</h2>
                    <a href="/library%20new/admin/activity_logs.php" class="view-all">View All</a>
                </div>
                <div class="card-body">
                    <ul class="activity-list">
                        <?php if (empty($user_activities)): ?>
                            <li class="activity-item">No recent user activities found</li>
                        <?php else: ?>
                            <?php foreach ($user_activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $icon = 'fas fa-info-circle';
                                        switch ($activity['action']) {
                                            case 'login':
                                                $icon = 'fas fa-sign-in-alt';
                                                break;
                                            case 'login_failed':
                                                $icon = 'fas fa-exclamation-triangle';
                                                break;
                                            case 'logout':
                                                $icon = 'fas fa-sign-out-alt';
                                                break;
                                            case 'search':
                                                $icon = 'fas fa-search';
                                                break;
                                            case 'view_book':
                                                $icon = 'fas fa-eye';
                                                break;
                                            case 'borrow_book':
                                                $icon = 'fas fa-hand-holding';
                                                break;
                                            case 'return_book':
                                                $icon = 'fas fa-undo';
                                                break;
                                            case 'reserve_book':
                                                $icon = 'fas fa-bookmark';
                                                break;
                                        }
                                        ?>
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <h4>
                                            <?php
                                            $action = str_replace('_', ' ', $activity['action']);
                                            echo ucwords($action);
                                            ?>
                                        </h4>
                                        <p>
                                            <?php if (!empty($activity['username'])): ?>
                                                By <?php echo htmlspecialchars($activity['username']); ?>
                                            <?php else: ?>
                                                Anonymous user
                                            <?php endif; ?>
                                            <span class="activity-module"><?php echo ucfirst($activity['module']); ?></span>
                                        </p>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('H:i', strtotime($activity['timestamp'])); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="card">
            <div class="card-header">
                <h2>Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="/library%20new/admin/users.php" class="btn-primary">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                    <a href="/library%20new/admin/books.php" class="btn-primary">
                        <i class="fas fa-book"></i> Manage Books
                    </a>
                    <a href="/library%20new/admin/borrowings.php" class="btn-primary">
                        <i class="fas fa-exchange-alt"></i> Manage Borrowings
                    </a>
                    <a href="/library%20new/admin/reports.php" class="btn-primary">
                        <i class="fas fa-chart-bar"></i> View Reports
                    </a>
                    <a href="/library%20new/admin/activity_logs.php" class="btn-primary">
                        <i class="fas fa-history"></i> Activity Logs
                    </a>
                    <a href="/library%20new/admin/settings.php" class="btn-primary">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* HD Quality Enhancements */
    .main-content {
        background-color: #f8fafc;
    }
    
    .header h1 {
        font-size: 18px; /* Medium size for big letters */
        color: #2D3748;
    }
    
    .header h1 i {
        color: #4158D0;
        margin-right: 8px;
    }
    
    .user-info .user-name {
        font-size: 14px; /* Medium size */
    }
    
    .user-info .user-role {
        font-size: 11px; /* Small size */
    }
    
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        position: relative;
        overflow: hidden;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    /* Solid colors instead of gradients for better performance */
    .stat-card:nth-child(1) {
        background: #4158D0;
    }
    
    .stat-card:nth-child(2) {
        background: #0093E9;
    }
    
    .stat-card:nth-child(3) {
        background: #8E2DE2;
    }
    
    .stat-card:nth-child(4) {
        background: #FF416C;
    }
    
    /* Removed pseudo-elements for better performance */
    
    /* Hover effects removed for better performance */
    
    .stat-card h3 {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 10px;
        font-weight: 500;
        position: relative;
        z-index: 1;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    
    .stat-card .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #ffffff;
        position: relative;
        z-index: 1;
        margin-top: 5px;
        letter-spacing: 0.5px;
    }
    
    .stat-card .stat-icon {
        position: absolute;
        bottom: -15px;
        right: -15px;
        font-size: 80px;
        opacity: 0.15;
        color: rgba(255, 255, 255, 0.8);
    }
    
    .row {
        margin-bottom: 25px;
    }
    
    .dashboard-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .card {
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.7);
        border-top: 3px solid #4158D0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .card-header {
        background: #f8fafc;
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
    }
    
    .card-header h2 {
        font-size: 16px; /* Medium size */
        color: #2d3748;
        margin: 0;
        font-weight: 600;
    }
    
    .card-header .view-all {
        font-size: 12px;
        color: #4158D0;
        text-decoration: none;
    }
    
    .card-header .view-all:hover {
        text-decoration: underline;
    }
    
    .card-body {
        padding: 0;
        flex: 1;
        overflow-y: auto;
        max-height: 300px;
    }
    
    .activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .activity-item {
        padding: 12px 15px;
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        display: flex;
        align-items: center;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #EBF4FF;
        color: #4158D0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .activity-details {
        flex: 1;
        min-width: 0;
    }
    
    .activity-details h4 {
        font-size: 14px; /* Medium size */
        margin-bottom: 3px;
        color: #2d3748;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .activity-details p {
        font-size: 11px; /* Small size */
        color: #718096;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .activity-module {
        background-color: #EBF4FF;
        color: #4158D0;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 9px;
        margin-left: 5px;
        text-transform: uppercase;
    }
    
    .activity-time {
        font-size: 11px; /* Small size */
        color: #a0aec0;
        font-weight: 500;
        margin-left: 10px;
        flex-shrink: 0;
    }
    
    .quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 5px;
    }
    
    .quick-actions .btn-primary {
        margin-right: 8px;
        margin-bottom: 8px;
        font-size: 12px; /* Small size */
        padding: 8px 12px;
        border-radius: 6px;
        background: #4158D0;
        color: white;
    }
    
    .security-alert {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        background: #E2E8F0;
        border-radius: 8px;
        padding: 12px 15px;
        border-left: 4px solid #4158D0;
    }
    
    .security-alert i {
        font-size: 20px;
        margin-right: 15px;
        color: #4158D0;
    }
    
    .security-alert-content h4 {
        margin: 0 0 3px 0;
        font-size: 14px; /* Medium size */
        color: #4158D0;
    }
    
    .security-alert-content p {
        margin: 0;
        font-size: 11px; /* Small size */
        color: #4a5568;
    }
    
    @media (max-width: 768px) {
        .dashboard-columns {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php
// Include footer
include __DIR__ . "/admin/includes/footer.php";
?>