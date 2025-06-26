<?php
// Start session
session_start();

// Check if user is already logged in
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once "config.php";

// Define variables and initialize with empty values
$username = $email = $password = $confirm_password = $full_name = "";
$username_err = $email_err = $password_err = $confirm_password_err = $full_name_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate full name
    if(empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter your full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Check email format
        $email_input = trim($_POST["email"]);
        if(!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Check if it's a Gmail address
            $domain = substr(strrchr($email_input, "@"), 1);
            if(strtolower($domain) !== "gmail.com") {
                $email_err = "Only Gmail addresses (@gmail.com) are accepted.";
            } else {
                // Prepare a select statement
                $sql = "SELECT id FROM users WHERE email = ?";
                
                if($stmt = $conn->prepare($sql)) {
                    // Bind variables to the prepared statement as parameters
                    $stmt->bind_param("s", $param_email);
                    
                    // Set parameters
                    $param_email = $email_input;
                    
                    // Attempt to execute the prepared statement
                    if($stmt->execute()) {
                        // Store result
                        $stmt->store_result();
                        
                        if($stmt->num_rows == 1) {
                            $email_err = "This email is already registered.";
                        } else {
                            $email = $email_input;
                        }
                    } else {
                        echo "Oops! Something went wrong. Please try again later.";
                    }

                    // Close statement
                    $stmt->close();
                }
            }
        }
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if(empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($full_name_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password, email, full_name, role_id) VALUES (?, ?, ?, ?, ?)";
         
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssssi", $param_username, $param_password, $param_email, $param_full_name, $param_role_id);
            
            // Set parameters
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_email = $email;
            $param_full_name = $full_name;
            $param_role_id = 3; // Default role for patrons/members
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Redirect to login page
                header("location: login.php?registered=success");
                exit();
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
    <title>Register - Library Management System</title>
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
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateY(15px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .container {
            width: 100%;
            max-width: 380px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
            will-change: transform, opacity;
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
            transition: all 0.3s;
            animation: slideIn 0.5s ease-out forwards;
            animation-delay: calc(var(--animation-order) * 0.1s);
            opacity: 0;
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
            background: linear-gradient(135deg, #a0c4ff, #bdb2ff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            margin-top: 5px;
            animation: slideIn 0.5s ease-out forwards;
            animation-delay: 0.6s;
            opacity: 0;
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #8eb8ff, #ada0ff);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(160, 196, 255, 0.3);
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.7;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }
        
        .btn:focus:not(:active)::after {
            animation: ripple 0.8s ease-out;
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
        
        .invalid-feedback {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 4px;
        }
        
        .ripple {
            position: absolute;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            transform: scale(0);
            animation: rippleEffect 0.6s linear;
            pointer-events: none;
            width: 100px;
            height: 100px;
        }
        
        @keyframes rippleEffect {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="loader-container">
        <div class="loader"></div>
    </div>
    <div class="container">
        <div class="form-header">
            <h1>Create Account</h1>
            <p>Join our library community</p>
        </div>
        
        <div class="form-content">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>" style="--animation-order:1">
                    <?php if(!empty($full_name_err)): ?>
                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" style="--animation-order:2">
                    <?php if(!empty($username_err)): ?>
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    <?php endif; ?>
                </div>    
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" style="--animation-order:3">
                    <?php if(!empty($email_err)): ?>
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" style="--animation-order:4">
                    <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </span>
                    <?php if(!empty($password_err)): ?>
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" style="--animation-order:5">
                    <span class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </span>
                    <?php if(!empty($confirm_password_err)): ?>
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Register</button>
                </div>
            </form>
            
            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Function to toggle password visibility
        function togglePassword(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
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
    <script src="assets/js/loader.js" defer></script>
    <script src="assets/js/email-validator.js" defer></script>
    <script src="assets/js/security-enhancements.js" defer></script>
</body>
</html>