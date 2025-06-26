<?php
/**
 * User Activity Logger
 * 
 * This file provides functions to log user activities for audit trail,
 * monitoring, security, and troubleshooting purposes.
 */

/**
 * Log user activity to the database
 * 
 * @param int|null $user_id User ID or null for anonymous/unknown users
 * @param string|null $username Username or null for anonymous/unknown users
 * @param string $action Action performed (e.g., login, logout, view_book)
 * @param string $action_details Additional details about the action
 * @param string $module System module where action occurred (e.g., authentication, catalog)
 * @param string $status Status of the action (success, failure)
 * @param object $conn Database connection object
 * @return bool Whether logging was successful
 */
function log_user_activity($user_id, $username, $action, $action_details, $module, $status, $conn) {
    try {
        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Get device info
        $device_info = get_device_info($user_agent);
        
        // Debug log
        error_log("Logging activity: User ID: $user_id, Action: $action, Module: $module");
        
        // Prepare statement
        $stmt = $conn->prepare("INSERT INTO user_activity_log 
                              (user_id, username, action, action_details, module, ip_address, user_agent, device_info, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        // Bind parameters
        $bind_result = $stmt->bind_param("issssssss", $user_id, $username, $action, $action_details, $module, $ip_address, $user_agent, $device_info, $status);
        
        if (!$bind_result) {
            error_log("Binding parameters failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        // Execute statement
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        // Log error to server error log
        error_log("Failed to log user activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get device information from user agent
 * 
 * @param string $user_agent User agent string
 * @return string Device information
 */
function get_device_info($user_agent) {
    $device_info = 'Unknown';
    
    // Detect operating system
    if (strpos($user_agent, 'Windows') !== false) {
        $device_info = 'Windows';
    } elseif (strpos($user_agent, 'Macintosh') !== false) {
        $device_info = 'macOS';
    } elseif (strpos($user_agent, 'Linux') !== false && strpos($user_agent, 'Android') === false) {
        $device_info = 'Linux';
    } elseif (strpos($user_agent, 'Android') !== false) {
        $device_info = 'Android';
    } elseif (strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPad') !== false) {
        $device_info = 'iOS';
    }
    
    // Detect browser
    if (strpos($user_agent, 'Chrome') !== false && strpos($user_agent, 'Edg') === false) {
        $device_info .= ', Chrome';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        $device_info .= ', Firefox';
    } elseif (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) {
        $device_info .= ', Safari';
    } elseif (strpos($user_agent, 'Edg') !== false) {
        $device_info .= ', Edge';
    } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
        $device_info .= ', Internet Explorer';
    }
    
    return $device_info;
}

/**
 * Log login activity
 * 
 * @param int|null $user_id User ID or null for failed logins
 * @param string $username Username
 * @param bool $success Whether login was successful
 * @param object $conn Database connection object
 * @return bool Whether logging was successful
 */
function log_login_activity($user_id, $username, $success, $conn) {
    $action = $success ? 'login' : 'login_failed';
    $details = $success ? 'User logged in successfully' : "Failed login attempt for username: $username";
    $status = $success ? 'success' : 'failure';
    
    // Debug log
    error_log("Logging login activity: User: $username, Success: " . ($success ? 'true' : 'false'));
    
    // Make sure the user_activity_log table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_activity_log'");
    if ($table_check->num_rows == 0) {
        error_log("user_activity_log table does not exist. Creating it now.");
        $sql = file_get_contents(dirname(__DIR__) . '/user_activity_log.sql');
        $conn->multi_query($sql);
        
        // Clear results
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    }
    
    return log_user_activity($user_id, $username, $action, $details, 'authentication', $status, $conn);
}

/**
 * Log logout activity
 * 
 * @param int $user_id User ID
 * @param string $username Username
 * @param object $conn Database connection object
 * @return bool Whether logging was successful
 */
function log_logout_activity($user_id, $username, $conn) {
    return log_user_activity($user_id, $username, 'logout', 'User logged out', 'authentication', 'success', $conn);
}

/**
 * Log book-related activity
 * 
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $action Action performed (e.g., view_book, borrow_book)
 * @param string $details Details about the action
 * @param object $conn Database connection object
 * @return bool Whether logging was successful
 */
function log_book_activity($user_id, $username, $action, $details, $conn) {
    return log_user_activity($user_id, $username, $action, $details, 'catalog', 'success', $conn);
}

/**
 * Log borrowing activity
 * 
 * @param int $user_id User ID
 * @param string $username Username
 * @param string $action Action performed (e.g., borrow_book, return_book)
 * @param string $details Details about the action
 * @param object $conn Database connection object
 * @return bool Whether logging was successful
 */
function log_borrowing_activity($user_id, $username, $action, $details, $conn) {
    return log_user_activity($user_id, $username, $action, $details, 'circulation', 'success', $conn);
}
?>