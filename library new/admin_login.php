<?php
// Include database connection and admin authentication
require_once "config.php";
require_once "admin_auth.php";
require_once "includes/user_logger.php";

// Ensure admin log table exists
ensure_admin_log_table($conn);

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Check for brute force attempts
function check_brute_force($email, $ip, $conn) {
    // Count failed attempts in the last 30 minutes
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM admin_login_attempts 
                           WHERE (email = ? OR ip_address = ?) 
                           AND success = 0 
                           AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt->bind_param("ss", $email, $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // If more than 5 failed attempts, block access
    return ($row['attempts'] >= 5);
}

// Log login attempt
function log_login_attempt($email, $ip, $success, $conn) {
    $stmt = $conn->prepare("INSERT INTO admin_login_attempts (email, ip_address, success) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $email, $ip, $success);
    $stmt->execute();
    $stmt->close();
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get client IP address
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Check if email is empty
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($email_err) && empty($password_err)) {
        // Check for brute force attempts
        if(check_brute_force($email, $ip, $conn)) {
            $login_err = "Too many failed login attempts. Please try again later.";
        } else {
            // Attempt to log in
            $result = secure_admin_login($email, $password, $conn);
            
            if($result['success']) {
                // Log successful attempt
                log_login_attempt($email, $ip, 1, $conn);
                
                // Also log to user_activity_log for dashboard stats - direct query
                $username_esc = $conn->real_escape_string($_SESSION["username"]);
                $ip_esc = $conn->real_escape_string($ip);
                $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                $conn->query("INSERT INTO user_activity_log 
                            (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                            VALUES 
                            (NULL, '$username_esc', 'login', 'Admin logged in successfully', 'authentication', '$ip_esc', '$user_agent', 'success')");
                
                // Redirect to admin dashboard
                header("location: admin_dashboard.php");
                exit;
            } else {
                // Log failed attempt
                log_login_attempt($email, $ip, 0, $conn);
                
                // Also log to user_activity_log for dashboard stats - direct query
                $email_esc = $conn->real_escape_string($email);
                $ip_esc = $conn->real_escape_string($ip);
                $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                $conn->query("INSERT INTO user_activity_log 
                            (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                            VALUES 
                            (NULL, '$email_esc', 'login_failed', 'Admin login failed', 'authentication', '$ip_esc', '$user_agent', 'failure')");
                
                $login_err = $result['message'];
            }
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Library Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/loader.css">
    <link rel="stylesheet" href="assets/css/validation.css">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #1c1f2f;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 0;
            margin: 0;
            overflow: hidden;
            color: #333;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateY(8px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .container {
            width: 100%;
            max-width: 360px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: fadeIn 0.2s ease-out;
            will-change: transform, opacity;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .admin-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ff6b6b;
            color: white;
            font-size: 12px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 20px;
            z-index: 10;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .form-header {
            background: #3a5998;
            padding: 20px 15px;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        
        /* Removed form-header background pattern for better performance */
        
        .form-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-header p {
            font-size: 13px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }
        
        .form-content {
            padding: 20px 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: #2c3e50;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 13px;
            border: 1px solid rgba(225, 229, 235, 0.8);
            border-radius: 8px;
            background-color: rgba(249, 250, 252, 0.8);
            transition: all 0.2s;
            animation: slideIn 0.15s ease-out forwards;
            animation-delay: calc(var(--animation-order) * 0.03s);
            opacity: 0;
        }
        
        .form-control:focus {
            border-color: #4a69bd;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.15);
            outline: none;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 42px;
            cursor: pointer;
            color: #777;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: #4a69bd;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #4a69bd;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            margin-top: 5px;
            animation: slideIn 0.15s ease-out forwards;
            animation-delay: 0.1s;
            opacity: 0;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            background: #5a79cd;
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }
        
        .form-footer a {
            color: #4a69bd;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .form-footer a:hover {
            color: #2c3e50;
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .invalid-feedback {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .alert-danger {
            background-color: #fff5f5;
            color: #e53e3e;
            border-left: 4px solid #e53e3e;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 15px;
            padding: 8px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .security-badge i {
            color: #4a69bd;
            margin-right: 8px;
            font-size: 14px;
        }
        
        .security-badge span {
            color: #4a5568;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Removed loader for faster page load -->
    <div class="container">
        <div class="admin-badge">
            <i class="fas fa-shield-alt"></i> Admin
        </div>
        <div class="form-header">
            <h1>Admin Access</h1>
            <p>Secure login for administrative users</p>
        </div>
        
        <div class="form-content">
            <?php 
            if(!empty($login_err)){
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" style="--animation-order:1">
                    <?php if(!empty($email_err)): ?>
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    <?php endif; ?>
                </div>    
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" style="--animation-order:2">
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                    <?php if(!empty($password_err)): ?>
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Secure Login</button>
                </div>
            </form>
            
            <div class="security-badge">
                <i class="fas fa-lock"></i>
                <span>Enhanced security protocols active</span>
            </div>
            
            <div class="form-footer">
                <p>Return to <a href="login.php">standard login</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Function to toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById("password");
            const toggleIcon = document.getElementById("toggleIcon");
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
        
        // Add security features
        document.addEventListener('DOMContentLoaded', function() {
            // Disable right-click for security
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
            
            // Disable F12 key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F12' || 
                    (e.ctrlKey && e.shiftKey && e.key === 'I') || 
                    (e.ctrlKey && e.shiftKey && e.key === 'J') || 
                    (e.ctrlKey && e.key === 'U')) {
                    e.preventDefault();
                }
            });
            
            // Add timing analysis for bot detection
            const form = document.querySelector('form');
            const startTime = Date.now();
            
            form.addEventListener('submit', function(e) {
                const elapsedTime = Date.now() - startTime;
                
                // If form is submitted too quickly (less than 2 seconds), likely a bot
                if (elapsedTime < 2000) {
                    e.preventDefault();
                    console.log('Potential automated submission detected');
                }
                
                // Add hidden field with timing data
                const timingField = document.createElement('input');
                timingField.type = 'hidden';
                timingField.name = 'form_timing';
                timingField.value = elapsedTime;
                form.appendChild(timingField);
            });
        });
    </script>
    <script src="assets/js/email-validator.js" defer></script>
</body>
</html>