<?php
/**
 * Ensure Activity Log Tables
 * 
 * This script ensures that both admin_activity_log and user_activity_log tables exist
 * in the database. It should be run once during installation or when setting up the system.
 */

// Include database connection
require_once "../config.php";
require_once "../admin_auth.php";

// Verify admin session
if (!verify_admin_session()) {
    header("Location: ../admin_login.php");
    exit;
}

// Check if admin_activity_log table exists
$admin_table_check = $conn->query("SHOW TABLES LIKE 'admin_activity_log'");
if ($admin_table_check->num_rows == 0) {
    // Create admin_activity_log table
    $sql = "CREATE TABLE IF NOT EXISTS admin_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        action VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT NOT NULL,
        email VARCHAR(100) NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (admin_id),
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($sql)) {
        echo "<p>Admin activity log table created successfully.</p>";
    } else {
        echo "<p>Error creating admin activity log table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Admin activity log table already exists.</p>";
}

// Check if user_activity_log table exists
$user_table_check = $conn->query("SHOW TABLES LIKE 'user_activity_log'");
if ($user_table_check->num_rows == 0) {
    // Create user_activity_log table
    $sql = file_get_contents(dirname(__DIR__) . '/user_activity_log.sql');
    
    if ($conn->multi_query($sql)) {
        echo "<p>User activity log table created successfully.</p>";
        
        // Clear results
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    } else {
        echo "<p>Error creating user activity log table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>User activity log table already exists.</p>";
}

echo "<p>Activity log tables check completed.</p>";
echo "<p><a href='/library%20new/admin_dashboard.php'>Return to Dashboard</a></p>";
?>