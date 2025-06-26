<?php
// Direct fix for login activity recording
require_once "config.php";

// Check if the table exists
$table_check = $conn->query("SHOW TABLES LIKE 'user_activity_log'");
if ($table_check->num_rows == 0) {
    echo "Creating user_activity_log table...<br>";
    
    // Create the table without foreign key constraint
    $sql = "CREATE TABLE user_activity_log (
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
        echo "Table created successfully.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "Table exists. Checking for foreign key constraints...<br>";
    
    // Check for foreign key constraints
    $fk_check = $conn->query("SELECT * FROM information_schema.TABLE_CONSTRAINTS 
                             WHERE CONSTRAINT_SCHEMA = 'library_management' 
                             AND TABLE_NAME = 'user_activity_log' 
                             AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    
    if ($fk_check->num_rows > 0) {
        echo "Found foreign key constraints. Removing them...<br>";
        
        while ($row = $fk_check->fetch_assoc()) {
            $constraint_name = $row['CONSTRAINT_NAME'];
            $sql = "ALTER TABLE user_activity_log DROP FOREIGN KEY " . $constraint_name;
            
            if ($conn->query($sql)) {
                echo "Removed constraint: " . $constraint_name . "<br>";
            } else {
                echo "Error removing constraint: " . $conn->error . "<br>";
            }
        }
    } else {
        echo "No foreign key constraints found.<br>";
    }
}

// Insert sample login records
echo "Inserting sample login records...<br>";

// Sample login record
$sql = "INSERT INTO user_activity_log 
        (user_id, username, action, action_details, module, ip_address, status) 
        VALUES 
        (NULL, 'user1@gmail.com', 'login', 'User logged in successfully', 'authentication', '127.0.0.1', 'success')";

if ($conn->query($sql)) {
    echo "Inserted successful login record.<br>";
} else {
    echo "Error inserting login record: " . $conn->error . "<br>";
}

// Sample failed login record
$sql = "INSERT INTO user_activity_log 
        (user_id, username, action, action_details, module, ip_address, status) 
        VALUES 
        (NULL, 'baduser@gmail.com', 'login_failed', 'Failed login attempt', 'authentication', '127.0.0.1', 'failure')";

if ($conn->query($sql)) {
    echo "Inserted failed login record.<br>";
} else {
    echo "Error inserting failed login record: " . $conn->error . "<br>";
}

// Check if records were inserted
$result = $conn->query("SELECT COUNT(*) as count FROM user_activity_log");
$row = $result->fetch_assoc();
echo "Total records in user_activity_log: " . $row['count'] . "<br>";

// Close connection
$conn->close();

echo "<p>Now check the activity logs page to see if the login records appear.</p>";
?>