<?php
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Filtering options
$user_filter = isset($_GET['user']) ? $_GET['user'] : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$module_filter = isset($_GET['module']) ? $_GET['module'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($user_filter)) {
    $conditions[] = "(username LIKE ? OR user_id = ?)";
    $params[] = "%$user_filter%";
    $params[] = (int)$user_filter;
    $types .= 'si';
}

if (!empty($action_filter)) {
    $conditions[] = "action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if (!empty($module_filter)) {
    $conditions[] = "module = ?";
    $params[] = $module_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $conditions[] = "timestamp >= ?";
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
}

if (!empty($date_to)) {
    $conditions[] = "timestamp <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

// Build the WHERE clause
$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get activity logs with filters
$sql = "SELECT * FROM user_activity_log $where_clause ORDER BY timestamp DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
$filename = 'activity_logs_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, [
    'ID', 
    'User ID', 
    'Username', 
    'Action', 
    'Action Details', 
    'Module', 
    'IP Address', 
    'User Agent', 
    'Device Info', 
    'Timestamp', 
    'Status'
]);

// Output each row of the data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['user_id'],
        $row['username'],
        $row['action'],
        $row['action_details'],
        $row['module'],
        $row['ip_address'],
        $row['user_agent'],
        $row['device_info'],
        $row['timestamp'],
        $row['status']
    ]);
}

// Log the export activity
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$export_details = "Exported activity logs with filters: " . 
                 (!empty($user_filter) ? "User: $user_filter, " : "") . 
                 (!empty($action_filter) ? "Action: $action_filter, " : "") . 
                 (!empty($module_filter) ? "Module: $module_filter, " : "") . 
                 (!empty($status_filter) ? "Status: $status_filter, " : "") . 
                 (!empty($date_from) ? "From: $date_from, " : "") . 
                 (!empty($date_to) ? "To: $date_to" : "");

// Include user logger if not already included
if (!function_exists('log_user_activity')) {
    require_once '../includes/user_logger.php';
}

log_user_activity($admin_id, $admin_username, 'export_logs', $export_details, 'administration', 'success', $conn);

exit();
?>