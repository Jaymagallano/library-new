<?php
// Include necessary files
require_once "../config.php";
require_once "../admin_auth.php";
require_once "../includes/user_logger.php";

// Verify admin session
if (!verify_admin_session()) {
    header("Location: ../admin_login.php");
    exit;
}

// Log this page access
log_admin_activity($_SESSION["user_id"], 'activity_logs_page_access', $conn);

// Initialize variables
$logs = [];
$total_logs = 0;
$search = "";
$action_filter = "all";
$module_filter = "all";
$status_filter = "all";
$date_from = "";
$date_to = "";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
    }
    if (isset($_GET['action'])) {
        $action_filter = trim($_GET['action']);
    }
    if (isset($_GET['module'])) {
        $module_filter = trim($_GET['module']);
    }
    if (isset($_GET['status'])) {
        $status_filter = trim($_GET['status']);
    }
    if (isset($_GET['date_from'])) {
        $date_from = trim($_GET['date_from']);
    }
    if (isset($_GET['date_to'])) {
        $date_to = trim($_GET['date_to']);
    }
}

// Handle export action
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user_activity_logs_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header
    fputcsv($output, ['ID', 'User ID', 'Username', 'Action', 'Details', 'Module', 'IP Address', 'Device', 'Timestamp', 'Status']);
    
    // Build query based on filters (without pagination)
    $query = "SELECT * FROM user_activity_log WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $search_param = "%$search%";
        $query .= " AND (username LIKE ? OR action LIKE ? OR action_details LIKE ? OR ip_address LIKE ?)";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }
    
    if ($action_filter != "all") {
        $query .= " AND action = ?";
        $params[] = $action_filter;
        $types .= "s";
    }
    
    if ($module_filter != "all") {
        $query .= " AND module = ?";
        $params[] = $module_filter;
        $types .= "s";
    }
    
    if ($status_filter != "all") {
        $query .= " AND status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if (!empty($date_from)) {
        $query .= " AND timestamp >= ?";
        $params[] = $date_from . " 00:00:00";
        $types .= "s";
    }
    
    if (!empty($date_to)) {
        $query .= " AND timestamp <= ?";
        $params[] = $date_to . " 23:59:59";
        $types .= "s";
    }
    
    $query .= " ORDER BY timestamp DESC";
    
    // Execute query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Output each row as CSV
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['user_id'],
            $row['username'],
            $row['action'],
            $row['action_details'],
            $row['module'],
            $row['ip_address'],
            $row['device_info'],
            $row['timestamp'],
            $row['status']
        ]);
    }
    
    // Close statement and exit
    $stmt->close();
    exit;
}

// Build query based on filters
$query = "SELECT * FROM user_activity_log WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM user_activity_log WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $query .= " AND (username LIKE ? OR action LIKE ? OR action_details LIKE ? OR ip_address LIKE ?)";
    $count_query .= " AND (username LIKE ? OR action LIKE ? OR action_details LIKE ? OR ip_address LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($action_filter != "all") {
    $query .= " AND action = ?";
    $count_query .= " AND action = ?";
    $params[] = $action_filter;
    $types .= "s";
}

if ($module_filter != "all") {
    $query .= " AND module = ?";
    $count_query .= " AND module = ?";
    $params[] = $module_filter;
    $types .= "s";
}

if ($status_filter != "all") {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND timestamp >= ?";
    $count_query .= " AND timestamp >= ?";
    $params[] = $date_from . " 00:00:00";
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND timestamp <= ?";
    $count_query .= " AND timestamp <= ?";
    $params[] = $date_to . " 23:59:59";
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
$total_logs = $row['total'];
$stmt->close();

// Add pagination to query
$query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Get logs
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_logs / $per_page);

// Get unique actions for filter dropdown
$actions = [];
$stmt = $conn->prepare("SELECT DISTINCT action FROM user_activity_log ORDER BY action");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $actions[] = $row['action'];
}
$stmt->close();

// Get unique modules for filter dropdown
$modules = [];
$stmt = $conn->prepare("SELECT DISTINCT module FROM user_activity_log ORDER BY module");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $modules[] = $row['module'];
}
$stmt->close();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "User Activity Logs";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-history"></i> User Activity Logs</h1>
        <div class="header-actions">
            <button class="btn-secondary" onclick="location.href='activity_logs.php?export=csv<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>'">
                <i class="fas fa-download"></i> Export to CSV
            </button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2>Activity Logs</h2>
            <div class="card-tools">
                <form method="GET" action="" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <select name="action" class="form-control">
                                <option value="all" <?php echo ($action_filter == 'all') ? 'selected' : ''; ?>>All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo ($action_filter == $action) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $action))); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="module" class="form-control">
                                <option value="all" <?php echo ($module_filter == 'all') ? 'selected' : ''; ?>>All Modules</option>
                                <?php foreach ($modules as $module): ?>
                                <option value="<?php echo htmlspecialchars($module); ?>" <?php echo ($module_filter == $module) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($module)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="success" <?php echo ($status_filter == 'success') ? 'selected' : ''; ?>>Success</option>
                                <option value="failure" <?php echo ($status_filter == 'failure') ? 'selected' : ''; ?>>Failure</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_from">From Date:</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date:</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="activity_logs.php" class="btn-secondary">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Module</th>
                            <th>IP Address</th>
                            <th>Device</th>
                            <th>Timestamp</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['id']); ?></td>
                                    <td>
                                        <?php if ($log['user_id']): ?>
                                            <a href="users.php?id=<?php echo htmlspecialchars($log['user_id']); ?>">
                                                <?php echo htmlspecialchars($log['username'] ?? 'User #' . $log['user_id']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($log['username'] ?? 'Anonymous'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))); ?></td>
                                    <td><?php echo htmlspecialchars($log['action_details']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($log['module'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($log['device_info']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($log['status'] == 'success') ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($log['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No activity logs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" class="page-link">&laquo; First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" class="page-link">&lsaquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" class="page-link">Next &rsaquo;</a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter != 'all' ? '&action=' . urlencode($action_filter) : ''; ?><?php echo $module_filter != 'all' ? '&module=' . urlencode($module_filter) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>" class="page-link">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="summary">
                Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?> logs
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h2>Activity Summary</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="summary-box">
                        <h3>Recent Activity</h3>
                        <ul class="activity-list">
                            <?php 
                            $stmt = $conn->prepare("SELECT * FROM user_activity_log ORDER BY timestamp DESC LIMIT 5");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()):
                            ?>
                                <li>
                                    <span class="activity-time"><?php echo htmlspecialchars(date('M d, H:i', strtotime($row['timestamp']))); ?></span>
                                    <span class="activity-user"><?php echo htmlspecialchars($row['username'] ?? 'Anonymous'); ?></span>
                                    <span class="activity-action"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['action']))); ?></span>
                                </li>
                            <?php endwhile; ?>
                            <?php $stmt->close(); ?>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="summary-box">
                        <h3>Activity by Module</h3>
                        <canvas id="moduleChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get module data for chart
    <?php
    $stmt = $conn->prepare("SELECT module, COUNT(*) as count FROM user_activity_log GROUP BY module ORDER BY count DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $module_data = [];
    while ($row = $result->fetch_assoc()) {
        $module_data[$row['module']] = $row['count'];
    }
    $stmt->close();
    ?>
    
    var moduleData = <?php echo json_encode($module_data); ?>;
    var moduleLabels = Object.keys(moduleData);
    var moduleCounts = Object.values(moduleData);
    
    // Create chart
    var ctx = document.getElementById('moduleChart').getContext('2d');
    var moduleChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: moduleLabels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
            datasets: [{
                label: 'Activity Count',
                data: moduleCounts,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php include "../admin/includes/footer.php"; ?>