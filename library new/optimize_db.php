<?php
// Database optimization script
require_once "config.php";

echo "Starting database optimization...<br>";

// Add indexes to frequently queried tables
echo "Adding indexes to user_activity_log table...<br>";
$conn->query("CREATE INDEX IF NOT EXISTS idx_action_status ON user_activity_log(action, status)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_module_timestamp ON user_activity_log(module, timestamp)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_user_timestamp ON user_activity_log(user_id, timestamp)");

echo "Adding indexes to users table...<br>";
$conn->query("CREATE INDEX IF NOT EXISTS idx_username ON users(username)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_email ON users(email)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_role_status ON users(role_id, status)");

echo "Adding indexes to books table...<br>";
$conn->query("CREATE INDEX IF NOT EXISTS idx_title ON books(title)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_status ON books(status)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_author ON books(author)");

echo "Optimizing tables...<br>";
$conn->query("OPTIMIZE TABLE user_activity_log, users, books");

echo "Database optimization completed.<br>";
$conn->close();
?>