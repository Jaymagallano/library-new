<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role_id"] != 1) {
    header("location: ../admin_login.php");
    exit;
}

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = "Admin Dashboard";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?> - Library Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&family=Poppins:wght@400;500&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'" crossorigin="anonymous">
    <link rel="stylesheet" href="/library%20new/admin/includes/responsive.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/admin-responsive.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/table-spacing.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/action-buttons.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/fix-truncation.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/small-text.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/medium-buttons.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/enhanced-shadows.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/activity-summary.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/activity-logs.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/lowercase-text.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/chart-refresh.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/dashboard-stats.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/dashboard-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/button-colors.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/load-more.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/button-icon-spacing.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/ultra-hd-icons.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/exchange-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/book-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/users-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/bookmark-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/bell-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/chart-bar-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/cog-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/shield-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/history-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/sign-out-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/trash-icon.css">
    <link rel="stylesheet" href="/library%20new/admin/includes/edit-icon.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4158D0;
            --primary-dark: #2D3748;
            --accent-color: #C850C0;
            --text-color: #2D3748;
            --border-color: #E2E8F0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f8fafc;
            color: var(--text-color);
            min-height: 100vh;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 200px;
            background: linear-gradient(180deg, #1A202C, #2D3748);
            color: #fff;
            padding: 15px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 100;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        
        /* Animation removed to prevent lag */
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .sidebar::-webkit-scrollbar {
            display: none;
        }
        
        .sidebar-header {
            padding: 15px 0;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
            position: relative;
        }
        
        /* Removed decorative elements for better performance */
        
        .sidebar-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #ffffff;
        }
        
        .sidebar-header p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-weight: 300;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease-out;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
            border: 1px solid rgba(255, 255, 255, 0);
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            color: #ffffff;
        }
        
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-weight: 500;
            color: #ffffff;
            position: relative;
        }
        
        .sidebar-menu a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: #C850C0;
            border-radius: 0 3px 3px 0;
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .sidebar-menu a:hover i, .sidebar-menu a.active i {
            color: #ffffff;
            transform: scale(1.1);
        }
        
        .main-content {
            flex: 1;
            margin-left: 200px;
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        /* Sidebar toggle button removed */
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
        }
        
        .header h1 i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--gray-color);
        }
        
        .card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-dark);
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            background-color: #f9fafc;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            font-weight: 600;
            background-color: #f9fafc;
            color: var(--primary-dark);
        }
        
        .table tr:hover {
            background-color: #f8fafc;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .badge-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        
        .badge-warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }
        
        .badge-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .btn-primary, .btn-secondary, .btn-danger, .btn-success, .btn-warning {
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .btn-secondary:hover {
            background-color: #e2e8f0;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #d35400;
            transform: translateY(-2px);
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-group {
            display: flex;
            gap: 5px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        
        .form-group {
            margin-bottom: 15px;
            flex: 1;
            min-width: 150px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.1);
            outline: none;
        }
        
        .search-form {
            margin-bottom: 0;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .page-link {
            padding: 8px 12px;
            border-radius: 5px;
            background-color: #fff;
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }
        
        .page-link:hover {
            background-color: #f8fafc;
        }
        
        .page-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .summary {
            font-size: 14px;
            color: var(--gray-color);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-color: var(--danger-color);
            color: var(--danger-color);
        }
        
        .alert-warning {
            background-color: rgba(243, 156, 18, 0.1);
            border-color: var(--warning-color);
            color: var(--warning-color);
        }
        
        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .d-inline {
            display: inline-block;
        }
        
        .text-center {
            text-align: center;
        }

        /* Add ultra-premium text-truncate utility for table cells */
        .text-truncate {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }

        /* Responsive adjustment for text-truncate */
        @media (max-width: 992px) {
            .text-truncate {
                max-width: 100px;
            }
        }
        @media (max-width: 576px) {
            .text-truncate {
                max-width: 60px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 15px 10px;
            }
            
            .sidebar-header h2, .sidebar-header p {
                display: none;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 18px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .toggle-sidebar {
                left: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Library Admin</h2>
                <p>Management System</p>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="/library%20new/admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/books.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'books.php' ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span>Books</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/borrowings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'borrowings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Borrowings</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/reservations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reservations.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bookmark"></i>
                        <span>Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/security.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'security.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/admin/activity_logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                <li>
                    <a href="/library%20new/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>