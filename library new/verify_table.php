<?php
// Include database connection
require_once "config.php";

echo "<h2>User Activity Log Table Verification</h2>";

// Check if the table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'user_activity_log'")->num_rows > 0;

if (!$table_exists) {
    echo "<p>Table 'user_activity_log' does not exist!</p>";
    
    // Create the table
    $create_sql = "CREATE TABLE user_activity_log (
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
    
    if ($conn->query($create_sql)) {
        echo "<p>Table created successfully!</p>";
    } else {
        echo "<p>Error creating table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Table 'user_activity_log' exists.</p>";
    
    // Check table structure
    $result = $conn->query("DESCRIBE user_activity_log");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row['Type'];
    }
    
    echo "<p>Table structure:</p>";
    echo "<ul>";
    foreach ($columns as $field => $type) {
        echo "<li>$field: $type</li>";
    }
    echo "</ul>";
    
    // Check for required columns
    $required_columns = ['user_id', 'username', 'action', 'module', 'ip_address', 'status', 'timestamp'];
    $missing_columns = [];
    
    foreach ($required_columns as $column) {
        if (!isset($columns[$column])) {
            $missing_columns[] = $column;
        }
    }
    
    if (!empty($missing_columns)) {
        echo "<p>Missing columns: " . implode(", ", $missing_columns) . "</p>";
        
        // Add missing columns
        foreach ($missing_columns as $column) {
            $alter_sql = "";
            switch ($column) {
                case 'user_id':
                    $alter_sql = "ALTER TABLE user_activity_log ADD COLUMN user_id INT NULL";
                    break;
                case 'username':
                    $alter_sql = "ALTER TABLE user_activity_log ADD COLUMN username VARCHAR(50) NULL";
                    break;
                case 'action':
                    $alter_sql = "ALTER TABLE user_activity_log ADD COLUMN action VARCHAR(100) NOT NULL";
                    break;
                case 'module':
                    $alter_sql = "ALTER TABLE user_activity_log ADD COLUMN module VARCHAR(50) NULL";
                    break;
                case 'ip_address':
                    $alter_sql = "ALTER TABLE user_activity_log ADD COLUMN ip_address VARCHAR(45) NOT NULL";
                    break;
                case 'status':
                    $alter_sql = "ALTER TABLE user_activity_log ADD COLUMN status VARCHAR(20) DEFAULT 'success'";
                    break;
                case 'timestamp':
                    $alter_sql = "ALTER TABLE user_activity_log ADD COLUMN timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                    break;
            }
            
            if (!empty($alter_sql)) {
                if ($conn->query($alter_sql)) {
                    echo "<p>Added column: $column</p>";
                } else {
                    echo "<p>Error adding column $column: " . $conn->error . "</p>";
                }
            }
        }
    } else {
        echo "<p>All required columns are present.</p>";
    }
}

// Check if there are any records
$count_result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log");
$count_row = $count_result->fetch_assoc();
echo "<p>Number of records: " . $count_row['count'] . "</p>";

// Insert a test record with NULL user_id to avoid foreign key constraint
echo "<p>Inserting a test record...</p>";
$test_sql = "INSERT INTO user_activity_log (user_id, username, action, action_details, module, ip_address, status) 
             VALUES (NULL, 'test_user', 'test', 'Test record from verification script', 'system', '127.0.0.1', 'success')";
             
if ($conn->query($test_sql)) {
    echo "<p>Test record inserted successfully!</p>";
} else {
    echo "<p>Error inserting test record: " . $conn->error . "</p>";
}

// Close connection
$conn->close();
?>