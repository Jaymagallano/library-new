<?php
// Start session
session_start();

// Include database connection if needed for logging
if (isset($_SESSION["user_id"])) {
    require_once "config.php";
    require_once "admin_auth.php";
    require_once "includes/user_logger.php";
    
    // Log logout activity for admins
    if (isset($_SESSION["role_id"]) && $_SESSION["role_id"] == 1) {
        log_admin_activity($_SESSION["user_id"], 'logout', $conn);
    }
    
    // Log logout activity for all users
    log_logout_activity($_SESSION["user_id"], $_SESSION["username"], $conn);
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("location: login.php");
exit;
?>