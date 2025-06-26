<?php
// Start session
session_start();

// Check if user is already logged in
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit();
}

// Include database connection and user logger
require_once "config.php";
require_once "includes/user_logger.php";

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if email is empty
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email_input = trim($_POST["email"]);
        // Check if it's a valid email and specifically a Gmail address
        if(!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Check if it's a Gmail address
            $domain = substr(strrchr($email_input, "@"), 1);
            if(strtolower($domain) !== "gmail.com") {
                $email_err = "Only Gmail addresses (@gmail.com) are accepted.";
            } else {
                $email = $email_input;
            }
        }
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($email_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, email, password, role_id FROM users WHERE email = ?";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);
            
            // Set parameters
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if email exists, if yes then verify password
                if($stmt->num_rows == 1) {                    
                    // Bind result variables
                    $stmt->bind_result($id, $username, $email, $hashed_password, $role_id);
                    if($stmt->fetch()) {
                        if(password_verify($password, $hashed_password)) {
                            // Password is correct, no need to start a new session as it's already started
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["email"] = $email;
                            $_SESSION["role_id"] = $role_id;
                            
                            // Log successful login with direct query - escape values to prevent SQL injection
                            $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
                            $username_esc = $conn->real_escape_string($username);
                            $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                            $conn->query("INSERT INTO user_activity_log 
                                        (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                                        VALUES 
                                        (NULL, '$username_esc', 'login', 'User logged in successfully', 'authentication', '$ip', '$user_agent', 'success')");
                            
                            // Redirect user to welcome page
                            header("location: index.php");
                            exit();
                        } else {
                            // Password is not valid
                            $login_err = "Invalid email or password.";
                            // Log failed login attempt with direct query
                            $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
                            $email_esc = $conn->real_escape_string($email);
                            $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                            $conn->query("INSERT INTO user_activity_log 
                                        (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                                        VALUES 
                                        (NULL, '$email_esc', 'login_failed', 'Failed login attempt - invalid password', 'authentication', '$ip', '$user_agent', 'failure')");
                        }
                    }
                } else {
                    // Email doesn't exist
                    $login_err = "Invalid email or password.";
                    // Log failed login attempt with direct query
                    $ip = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
                    $email_esc = $conn->real_escape_string($email);
                    $user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
                    $conn->query("INSERT INTO user_activity_log 
                                (user_id, username, action, action_details, module, ip_address, user_agent, status) 
                                VALUES 
                                (NULL, '$email_esc', 'login_failed', 'Failed login attempt - email not found', 'authentication', '$ip', '$user_agent', 'failure')");
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
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
    <title>Login - Library Management System</title>
    <link rel="preload" href="assets/css/styles.min.css" as="style">
    <link rel="stylesheet" href="assets/css/styles.min.css">
    <link rel="stylesheet" href="assets/css/loader.css">
    <link rel="stylesheet" href="assets/css/validation.css">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 0;
            margin: 0;
            overflow: hidden;
        }
        
        /* Animations removed for faster performance */
        
        .container {
            width: 100%;
            max-width: 360px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #a0c4ff, #bdb2ff);
            padding: 20px 15px;
            text-align: center;
            color: #fff;
        }
        
        .form-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 3px;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .form-header p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }
        
        .form-content {
            padding: 20px 20px;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            background-color: #f9fafc;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: #bdb2ff;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(189, 178, 255, 0.15);
            outline: none;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 34px;
            cursor: pointer;
            color: #777;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #a0c4ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            margin-top: 5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            background: #8eb8ff;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }
        
        .form-footer a {
            color: #a0c4ff;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .form-footer a:hover {
            color: #8eb8ff;
        }
        
        .alert {
            padding: 10px 12px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-size: 13px;
        }
        
        .alert-danger {
            background-color: #ffe5e5;
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2ecc71;
            border-left: 4px solid #2ecc71;
        }
        
        .invalid-feedback {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 4px;
        }
        
        /* Ripple effect removed for better performance */
    </style>
</head>
<body>
    <!-- Loader removed for faster page load -->
    <div class="container">
        <div class="form-header">
            <h1>Welcome Back</h1>
            <p>Sign in to access your library account</p>
        </div>
        
        <div class="form-content">
            <?php 
            if(!empty($login_err)){
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }
            if(isset($_GET['registered']) && $_GET['registered'] == 'success'){
                echo '<div class="alert alert-success">You have been successfully registered. Please login.</div>';
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
                    <button type="submit" class="btn">Sign In</button>
                </div>
            </form>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
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
    </script>
    <script src="assets/js/scripts.min.js" defer></script>
    <script src="assets/js/email-validator.js" defer></script>
    <script src="assets/js/security-enhancements.js" defer></script>
</body>
</html>