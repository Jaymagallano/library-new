-- User Activity Log Table for comprehensive audit trail
CREATE TABLE IF NOT EXISTS user_activity_log (
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
    INDEX (timestamp),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add sample data for testing
INSERT INTO user_activity_log (user_id, username, action, action_details, module, ip_address, user_agent, device_info, timestamp, status)
VALUES 
(3, 'patron1', 'login', 'User logged in successfully', 'authentication', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Chrome', NOW() - INTERVAL 2 DAY, 'success'),
(3, 'patron1', 'search', 'Searched for "programming"', 'catalog', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Chrome', NOW() - INTERVAL 2 DAY + INTERVAL 5 MINUTE, 'success'),
(3, 'patron1', 'view_book', 'Viewed book ID: 1', 'catalog', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Chrome', NOW() - INTERVAL 2 DAY + INTERVAL 10 MINUTE, 'success'),
(3, 'patron1', 'logout', 'User logged out', 'authentication', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Chrome', NOW() - INTERVAL 2 DAY + INTERVAL 30 MINUTE, 'success'),
(4, 'cataloger1', 'login', 'User logged in successfully', 'authentication', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Firefox', NOW() - INTERVAL 1 DAY, 'success'),
(4, 'cataloger1', 'add_book', 'Added new book: "Machine Learning Basics"', 'catalog', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Firefox', NOW() - INTERVAL 1 DAY + INTERVAL 15 MINUTE, 'success'),
(4, 'cataloger1', 'update_book', 'Updated book ID: 5', 'catalog', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Firefox', NOW() - INTERVAL 1 DAY + INTERVAL 25 MINUTE, 'success'),
(4, 'cataloger1', 'logout', 'User logged out', 'authentication', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows 10, Firefox', NOW() - INTERVAL 1 DAY + INTERVAL 45 MINUTE, 'success'),
(NULL, 'unknown', 'login_failed', 'Failed login attempt for username: baduser', 'authentication', '192.168.1.100', 'Mozilla/5.0 (Linux; Android 10)', 'Android, Chrome Mobile', NOW() - INTERVAL 12 HOUR, 'failure'),
(NULL, 'unknown', 'login_failed', 'Failed login attempt for username: baduser', 'authentication', '192.168.1.100', 'Mozilla/5.0 (Linux; Android 10)', 'Android, Chrome Mobile', NOW() - INTERVAL 12 HOUR + INTERVAL 1 MINUTE, 'failure'),
(NULL, 'unknown', 'login_failed', 'Failed login attempt for username: baduser', 'authentication', '192.168.1.100', 'Mozilla/5.0 (Linux; Android 10)', 'Android, Chrome Mobile', NOW() - INTERVAL 12 HOUR + INTERVAL 2 MINUTE, 'failure'),
(2, 'librarian1', 'login', 'User logged in successfully', 'authentication', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'macOS, Safari', NOW() - INTERVAL 5 HOUR, 'success'),
(2, 'librarian1', 'issue_book', 'Issued book ID: 3 to user ID: 3', 'circulation', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'macOS, Safari', NOW() - INTERVAL 5 HOUR + INTERVAL 10 MINUTE, 'success'),
(2, 'librarian1', 'return_book', 'Returned book ID: 2 from user ID: 5', 'circulation', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'macOS, Safari', NOW() - INTERVAL 5 HOUR + INTERVAL 20 MINUTE, 'success'),
(2, 'librarian1', 'logout', 'User logged out', 'authentication', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'macOS, Safari', NOW() - INTERVAL 5 HOUR + INTERVAL 30 MINUTE, 'success');

-- Create function to log user activity
DELIMITER //
CREATE FUNCTION IF NOT EXISTS log_user_activity(
    p_user_id INT,
    p_username VARCHAR(50),
    p_action VARCHAR(100),
    p_action_details TEXT,
    p_module VARCHAR(50),
    p_ip_address VARCHAR(45),
    p_user_agent TEXT,
    p_status VARCHAR(20)
) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE device_info VARCHAR(255);
    
    -- Extract device info from user agent
    SET device_info = 'Unknown';
    
    IF p_user_agent LIKE '%Windows%' THEN
        SET device_info = 'Windows';
        IF p_user_agent LIKE '%Chrome%' THEN
            SET device_info = CONCAT(device_info, ', Chrome');
        ELSEIF p_user_agent LIKE '%Firefox%' THEN
            SET device_info = CONCAT(device_info, ', Firefox');
        ELSEIF p_user_agent LIKE '%Edge%' THEN
            SET device_info = CONCAT(device_info, ', Edge');
        ELSEIF p_user_agent LIKE '%Safari%' THEN
            SET device_info = CONCAT(device_info, ', Safari');
        END IF;
    ELSEIF p_user_agent LIKE '%Macintosh%' THEN
        SET device_info = 'macOS';
        IF p_user_agent LIKE '%Chrome%' THEN
            SET device_info = CONCAT(device_info, ', Chrome');
        ELSEIF p_user_agent LIKE '%Firefox%' THEN
            SET device_info = CONCAT(device_info, ', Firefox');
        ELSEIF p_user_agent LIKE '%Safari%' THEN
            SET device_info = CONCAT(device_info, ', Safari');
        END IF;
    ELSEIF p_user_agent LIKE '%Android%' THEN
        SET device_info = 'Android';
        IF p_user_agent LIKE '%Chrome%' THEN
            SET device_info = CONCAT(device_info, ', Chrome Mobile');
        END IF;
    ELSEIF p_user_agent LIKE '%iPhone%' OR p_user_agent LIKE '%iPad%' THEN
        SET device_info = 'iOS';
        IF p_user_agent LIKE '%Safari%' THEN
            SET device_info = CONCAT(device_info, ', Safari Mobile');
        END IF;
    END IF;
    
    -- Insert log entry
    INSERT INTO user_activity_log (
        user_id, 
        username, 
        action, 
        action_details, 
        module, 
        ip_address, 
        user_agent, 
        device_info, 
        status
    ) VALUES (
        p_user_id,
        p_username,
        p_action,
        p_action_details,
        p_module,
        p_ip_address,
        p_user_agent,
        device_info,
        p_status
    );
    
    RETURN LAST_INSERT_ID();
END //
DELIMITER ;