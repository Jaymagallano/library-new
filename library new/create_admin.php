<?php
// Simple script to create admin account with correct password

// Include database connection
require_once "config.php";

// Admin credentials
$username = "admin";
$password = "Wallerpo";
$email = "admin@gmail.com";
$full_name = "System Administrator";
$role_id = 1;

// Create password hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// First try to update existing admin
$stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE email = ? AND role_id = ?");
$stmt->bind_param("sssi", $username, $password_hash, $email, $role_id);
$stmt->execute();

// Check if any rows were affected
if ($stmt->affected_rows == 0) {
    // No rows updated, try to insert new admin
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $password_hash, $email, $full_name, $role_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo "Admin account created successfully!";
    } else {
        echo "Failed to create admin account. Error: " . $conn->error;
    }
} else {
    echo "Admin account updated successfully!";
}

// Close statement and connection
$stmt->close();
$conn->close();

echo "<br><br>Admin login details:<br>";
echo "Email: admin@gmail.com<br>";
echo "Password: Wallerpo<br>";
echo "<br><a href='admin_login.php'>Go to Admin Login</a>";
?>