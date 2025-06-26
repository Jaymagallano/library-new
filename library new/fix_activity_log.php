<?php
// This script will check and fix the activity logging system

// Include necessary files
require_once "config.php";
require_once "includes/user_logger.php";

// Check if the user_activity_log table exists
$table_check = $conn->query("SHOW TABLES LIKE 'user_activity_log'");
if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = file_get_contents('user_activity_log.sql');
    $conn->multi_query($sql);
    
    // Clear results
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
    
    echo "Created user_activity_log table.<br>";
} else {
    echo "user_activity_log table already exists.<br>";
}

// Test logging functionality
$test_result = log_user_activity(
    null, 
    'system_test', 
    'system_check', 
    'Testing activity logging system', 
    'system', 
    'success', 
    $conn
);

if ($test_result) {
    echo "Activity logging is working correctly.<br>";
} else {
    echo "Activity logging failed. Check error logs.<br>";
}

// Check for recent login activities
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_activity_log WHERE action = 'login' AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "Recent login activities in the past 7 days: " . $row['count'] . "<br>";

// Check for recent failed login attempts
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_activity_log WHERE action = 'login_failed' AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "Recent failed login attempts in the past 7 days: " . $row['count'] . "<br>";

// Close connection
$conn->close();
?>