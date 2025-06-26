<?php
/**
 * Ultra-Premium Admin Authentication System
 * Enhanced security for admin accounts
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verify if the current user has admin privileges
 * @return bool Whether the user is an admin
 */
function is_admin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] === 1;
}

/**
 * Secure admin login with advanced protection
 * @param string $email Admin email
 * @param string $password Admin password
 * @param object $conn Database connection
 * @return array Login result with status and message
 */
function secure_admin_login($email, $password, $conn) {
    // Initialize result array
    $result = [
        'success' => false,
        'message' => '',
        'user_data' => null
    ];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['message'] = "Invalid email format";
        return $result;
    }
    
    // Check if it's a Gmail address
    $domain = substr(strrchr($email, "@"), 1);
    if (strtolower($domain) !== "gmail.com") {
        $result['message'] = "Only Gmail addresses (@gmail.com) are accepted";
        return $result;
    }
    
    try {
        // Prepare a secure statement with parameterized query
        $stmt = $conn->prepare("SELECT id, username, email, password, role_id, full_name, status FROM users WHERE email = ? AND role_id = 1 LIMIT 1");
        $stmt->bind_param("s", $email);
        
        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }
        
        // Get result
        $result_set = $stmt->get_result();
        
        // Check if admin exists
        if ($result_set->num_rows !== 1) {
            // Use generic error message for security
            $result['message'] = "Invalid credentials";
            return $result;
        }
        
        // Fetch admin data
        $admin = $result_set->fetch_assoc();
        
        // Check if account is active
        if ($admin['status'] !== 'active') {
            $result['message'] = "Account is inactive. Please contact support.";
            return $result;
        }
        
        // Verify password with constant-time comparison
        if (password_verify($password, $admin['password'])) {
            // Check if password needs rehashing (if PHP's default algorithm or cost has changed)
            if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT, ['cost' => 12])) {
                // Update password hash
                $new_hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hash, $admin['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            // Set session variables
            $_SESSION["loggedin"] = true;
            $_SESSION["user_id"] = $admin['id'];
            $_SESSION["username"] = $admin['username'];
            $_SESSION["email"] = $admin['email'];
            $_SESSION["role_id"] = $admin['role_id'];
            $_SESSION["full_name"] = $admin['full_name'];
            $_SESSION["admin_auth"] = true;
            $_SESSION["admin_last_activity"] = time();
            $_SESSION["admin_ip"] = $_SERVER['REMOTE_ADDR'];
            $_SESSION["admin_user_agent"] = $_SERVER['HTTP_USER_AGENT'];
            
            // Log successful login
            log_admin_activity($admin['id'], 'login_success', $conn);
            
            $result['success'] = true;
            $result['message'] = "Login successful";
            $result['user_data'] = [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'full_name' => $admin['full_name']
            ];
            
            return $result;
        } else {
            // Log failed attempt
            log_admin_activity(null, 'login_failed', $conn, $email);
            
            $result['message'] = "Invalid credentials";
            return $result;
        }
    } catch (Exception $e) {
        $result['message'] = "An error occurred. Please try again later.";
        error_log("Admin login error: " . $e->getMessage());
        return $result;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Log admin activity for security auditing
 * @param int|null $admin_id Admin ID or null for failed attempts
 * @param string $action Action performed
 * @param object $conn Database connection
 * @param string $email Optional email for failed attempts
 */
function log_admin_activity($admin_id, $action, $conn, $email = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, ip_address, user_agent, email) VALUES (?, ?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $stmt->bind_param("issss", $admin_id, $action, $ip, $user_agent, $email);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

/**
 * Verify admin session security
 * Checks for session hijacking attempts
 * @return bool Whether the session is secure
 */
function verify_admin_session() {
    if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
        return false;
    }
    
    // Check for session timeout (15 minutes)
    if (time() - $_SESSION['admin_last_activity'] > 900) {
        return false;
    }
    
    // Check for IP address change (potential session hijacking)
    if ($_SESSION['admin_ip'] !== $_SERVER['REMOTE_ADDR']) {
        return false;
    }
    
    // Check for User-Agent change (potential session hijacking)
    if ($_SESSION['admin_user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        return false;
    }
    
    // Update last activity time
    $_SESSION['admin_last_activity'] = time();
    
    return true;
}

/**
 * Create admin activity log table if it doesn't exist
 * @param object $conn Database connection
 */
function ensure_admin_log_table($conn) {
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
    
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        error_log("Failed to create admin log table: " . $e->getMessage());
    }
}
?>