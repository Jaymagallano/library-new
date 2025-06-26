<?php
// Start session
session_start();

// Check if the user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit();
}

// Include database connection
require_once "config.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Library Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #a0c4ff, #bdb2ff);
            padding: 15px 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar h1 {
            font-size: 22px;
            font-weight: 600;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
        }
        
        .user-info {
            margin-right: 20px;
            font-size: 14px;
        }
        
        .logout-btn {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-card {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            margin-bottom: 30px;
        }
        
        .welcome-card h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .welcome-card p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .feature-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            font-size: 30px;
            color: #bdb2ff;
            margin-bottom: 15px;
        }
        
        .feature-card h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .feature-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Library Management System</h1>
        <div class="navbar-right">
            <div class="user-info">
                Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong>
            </div>
            <a href="logout.php" class="logout-btn">Sign Out</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-card">
            <h2>Welcome to the Library Management System</h2>
            <p>You have successfully logged in. Explore our library resources and manage your account.</p>
        </div>
        
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Browse Books</h3>
                <p>Explore our extensive collection of books across various categories and subjects.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-bookmark"></i>
                </div>
                <h3>My Borrowings</h3>
                <p>View your current borrowings, history, and manage returns.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h3>My Profile</h3>
                <p>Update your personal information and account settings.</p>
            </div>
        </div>
    </div>
</body>
</html>