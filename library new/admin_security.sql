-- Ultra-Premium Admin Security Features
-- This script adds enhanced security tables and features for admin accounts

-- Create admin activity log table for security auditing
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    email VARCHAR(100) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (admin_id),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create admin login attempts table to prevent brute force attacks
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX (email),
    INDEX (ip_address)
);

-- Create admin security settings table
CREATE TABLE IF NOT EXISTS admin_security_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(100) NULL,
    password_last_changed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    require_password_change BOOLEAN DEFAULT FALSE,
    last_login TIMESTAMP NULL,
    UNIQUE KEY (admin_id),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add security settings for the new admin account
INSERT INTO admin_security_settings (admin_id, two_factor_enabled, require_password_change)
SELECT id, FALSE, FALSE FROM users WHERE email = 'admin@gmail.com' AND role_id = 1;

-- Create a trigger to log password changes
DELIMITER //
CREATE TRIGGER log_password_change
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.password != NEW.password AND NEW.role_id = 1 THEN
        UPDATE admin_security_settings 
        SET password_last_changed = CURRENT_TIMESTAMP
        WHERE admin_id = NEW.id;
        
        INSERT INTO admin_activity_log (admin_id, action, ip_address, user_agent)
        VALUES (NEW.id, 'password_changed', '127.0.0.1', 'System');
    END IF;
END //
DELIMITER ;

-- Create a stored procedure to clean up old login attempts (retention policy)
DELIMITER //
CREATE PROCEDURE cleanup_login_attempts()
BEGIN
    DELETE FROM admin_login_attempts 
    WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //
DELIMITER ;

-- Create an event to run the cleanup procedure daily
DELIMITER //
CREATE EVENT IF NOT EXISTS daily_cleanup
ON SCHEDULE EVERY 1 DAY
DO
    CALL cleanup_login_attempts()//
DELIMITER ;