<?php
// This script verifies the activity log table structure and adds test entries

// Include database connection
require_once "config.php";

// Check if the table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'user_activity_log'")->num_rows > 0;

if (!$table_exists) {
    echo "Table 'user_activity_log' does not exist. Creating it now...<br>";
    
    // Create the table using the SQL file
    $sql = file_get_contents('user_activity_log.sql');
    
    if ($conn->multi_query($sql)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
            // Check if there are more result sets
        } while ($conn->more_results() && $conn->next_result());
        
        echo "Table created successfully.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
        exit;
    }
} else {
    echo "Table 'user_activity_log' exists.<br>";
}

// Add test entries
$timestamp = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Test login entry
$stmt = $conn->prepare("INSERT INTO user_activity_log 
                      (user_id, username, action, action_details, module, ip_address, user_agent, device_info, status) 
                      VALUES (1, 'admin', 'login', 'Test login entry', 'authentication', ?, ?, 'Test Device', 'success')");
$stmt->bind_param("ss", $ip, $user_agent);
$result = $stmt->execute();
echo $result ? "Test login entry added successfully.<br>" : "Error adding test login entry: " . $stmt->error . "<br>";
$stmt->close();

// Test failed login entry
$stmt = $conn->prepare("INSERT INTO user_activity_log 
                      (user_id, username, action, action_details, module, ip_address, user_agent, device_info, status) 
                      VALUES (NULL, 'baduser', 'login_failed', 'Test failed login entry', 'authentication', ?, ?, 'Test Device', 'failure')");
$stmt->bind_param("ss", $ip, $user_agent);
$result = $stmt->execute();
echo $result ? "Test failed login entry added successfully.<br>" : "Error adding test failed login entry: " . $stmt->error . "<br>";
$stmt->close();

// Check if entries were added
$result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log WHERE action = 'login'");
$row = $result->fetch_assoc();
echo "Login entries count: " . $row['count'] . "<br>";

$result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log WHERE action = 'login_failed'");
$row = $result->fetch_assoc();
echo "Failed login entries count: " . $row['count'] . "<br>";

// Close connection
$conn->close();
?>