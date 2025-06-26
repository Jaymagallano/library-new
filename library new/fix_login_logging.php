<?php
// This script will create the user_activity_log table if it doesn't exist
// and insert a test login record to verify functionality

// Include database connection
require_once "config.php";

echo "<h2>Login Activity Log Fix</h2>";

// Check if the user_activity_log table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'user_activity_log'")->num_rows > 0;

if (!$table_exists) {
    echo "<p>Creating user_activity_log table...</p>";
    
    // SQL to create the table
    $sql = "CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        username VARCHAR(50) NULL,
        action VARCHAR(100) NOT NULL,
        action_details TEXT NULL,
        module VARCHAR(50) NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT NULL,
        device_info VARCHAR(255) NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'success',
        INDEX (user_id),
        INDEX (action),
        INDEX (timestamp)
    )";
    
    if ($conn->query($sql)) {
        echo "<p>Table created successfully!</p>";
    } else {
        echo "<p>Error creating table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>user_activity_log table already exists.</p>";
}

// Insert a test login record
$user_id = 1; // Test user ID
$username = "test_user";
$action = "login";
$details = "Test login record";
$module = "authentication";
$status = "success";
$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$device_info = "Test Device";

$stmt = $conn->prepare("INSERT INTO user_activity_log 
                      (user_id, username, action, action_details, module, ip_address, user_agent, device_info, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo "<p>Error preparing statement: " . $conn->error . "</p>";
} else {
    $stmt->bind_param("issssssss", $user_id, $username, $action, $details, $module, $ip, $user_agent, $device_info, $status);
    
    if ($stmt->execute()) {
        echo "<p>Test login record inserted successfully!</p>";
    } else {
        echo "<p>Error inserting test record: " . $stmt->error . "</p>";
    }
    
    $stmt->close();
}

// Check if there are any records in the table
$result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log");
$row = $result->fetch_assoc();
echo "<p>Total records in user_activity_log: " . $row['count'] . "</p>";

// Close connection
$conn->close();
?>