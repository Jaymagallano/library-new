<?php
// Include necessary files
require_once "../config.php";
require_once "../admin_auth.php";

// Verify admin session
if (!verify_admin_session()) {
    header("Location: ../admin_login.php");
    exit;
}

// Log this page access
log_admin_activity($_SESSION["user_id"], 'security_page_access', $conn);

// Initialize variables
$success_message = "";
$error_message = "";
$current_admin = [];
$login_attempts = [];
$admin_activities = [];

// Get current admin details
$stmt = $conn->prepare("SELECT u.*, ass.password_last_changed, ass.two_factor_enabled 
                       FROM users u 
                       LEFT JOIN admin_security_settings ass ON u.id = ass.admin_id
                       WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $current_admin = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                if (isset($_POST['current_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
                    $current_password = $_POST['current_password'];
                    $new_password = $_POST['new_password'];
                    $confirm_password = $_POST['confirm_password'];
                    
                    // Validate current password
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param("i", $_SESSION["user_id"]);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!password_verify($current_password, $user['password'])) {
                        $error_message = "Current password is incorrect";
                    } elseif ($new_password != $confirm_password) {
                        $error_message = "New passwords do not match";
                    } elseif (strlen($new_password) < 8) {
                        $error_message = "New password must be at least 8 characters long";
                    } else {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->bind_param("si", $hashed_password, $_SESSION["user_id"]);
                        $stmt->execute();
                        $stmt->close();
                        
                        log_admin_activity($_SESSION["user_id"], 'password_changed', $conn);
                        $success_message = "Password changed successfully";
                    }
                }
                break;
                
            case 'clear_login_attempts':
                // Clear login attempts older than specified days
                if (isset($_POST['days'])) {
                    $days = (int)$_POST['days'];
                    if ($days > 0) {
                        $stmt = $conn->prepare("DELETE FROM admin_login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? DAY)");
                        $stmt->bind_param("i", $days);
                        $stmt->execute();
                        $affected = $stmt->affected_rows;
                        $stmt->close();
                        
                        log_admin_activity($_SESSION["user_id"], 'login_attempts_cleared', $conn);
                        $success_message = "$affected login attempt records cleared";
                    }
                }
                break;
                
            case 'toggle_2fa':
                // Toggle two-factor authentication
                $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
                
                // Check if admin security settings exist
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_security_settings WHERE admin_id = ?");
                $stmt->bind_param("i", $_SESSION["user_id"]);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                if ($row['count'] > 0) {
                    // Update existing settings
                    $stmt = $conn->prepare("UPDATE admin_security_settings SET two_factor_enabled = ? WHERE admin_id = ?");
                    $stmt->bind_param("ii", $enable_2fa, $_SESSION["user_id"]);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Insert new settings
                    $stmt = $conn->prepare("INSERT INTO admin_security_settings (admin_id, two_factor_enabled) VALUES (?, ?)");
                    $stmt->bind_param("ii", $_SESSION["user_id"], $enable_2fa);
                    $stmt->execute();
                    $stmt->close();
                }
                
                log_admin_activity($_SESSION["user_id"], $enable_2fa ? '2fa_enabled' : '2fa_disabled', $conn);
                $success_message = "Two-factor authentication " . ($enable_2fa ? "enabled" : "disabled") . " successfully";
                
                // Update current admin data
                $current_admin['two_factor_enabled'] = $enable_2fa;
                break;
        }
    }
}

// Get recent login attempts
$stmt = $conn->prepare("SELECT * FROM admin_login_attempts ORDER BY attempt_time DESC LIMIT 20");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $login_attempts[] = $row;
}
$stmt->close();

// Get admin activities
$stmt = $conn->prepare("SELECT a.*, u.username, u.full_name 
                       FROM admin_activity_log a
                       LEFT JOIN users u ON a.admin_id = u.id
                       ORDER BY a.timestamp DESC
                       LIMIT 20");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admin_activities[] = $row;
}
$stmt->close();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "Security Settings";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-shield-alt"></i> Security Settings</h1>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2>Change Password</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8">
                            <small class="form-text">Password must be at least 8 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strength-meter-fill"></div>
                            </div>
                            <div class="strength-text" id="strength-text">Password strength</div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <div class="password-info">
                        <p><strong>Last password change:</strong> 
                            <?php 
                            if (isset($current_admin['password_last_changed'])) {
                                echo date('M d, Y H:i', strtotime($current_admin['password_last_changed']));
                            } else {
                                echo "Never";
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Two-Factor Authentication</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="toggle_2fa">
                        
                        <div class="form-group">
                            <div class="toggle-switch">
                                <input type="checkbox" id="enable_2fa" name="enable_2fa" class="toggle-input" <?php echo (isset($current_admin['two_factor_enabled']) && $current_admin['two_factor_enabled']) ? 'checked' : ''; ?>>
                                <label for="enable_2fa" class="toggle-label"></label>
                                <span class="toggle-text">Enable Two-Factor Authentication</span>
                            </div>
                            <small class="form-text">Two-factor authentication adds an extra layer of security to your account</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2>Login Attempts</h2>
                    <div class="card-tools">
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="clear_login_attempts">
                            <input type="hidden" name="days" value="30">
                            <button type="submit" class="btn-sm btn-secondary">
                                <i class="fas fa-trash"></i> Clear Old Records
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>IP Address</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($login_attempts)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No login attempts recorded</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($login_attempts as $attempt): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($attempt['email']); ?></td>
                                            <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($attempt['attempt_time'])); ?></td>
                                            <td>
                                                <?php if ($attempt['success']): ?>
                                                    <span class="badge badge-success">Success</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Admin Activity Log</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Time</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admin_activities)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No activities recorded</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admin_activities as $activity): ?>
                                        <tr>
                                            <td>
                                                <?php if ($activity['admin_id']): ?>
                                                    <?php echo htmlspecialchars($activity['full_name'] ?? 'Unknown'); ?>
                                                <?php else: ?>
                                                    <em>System</em>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?></td>
                                            <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -10px;
    }
    
    .col-md-6 {
        flex: 0 0 calc(50% - 20px);
        margin: 0 10px;
    }
    
    @media (max-width: 768px) {
        .col-md-6 {
            flex: 0 0 100%;
        }
    }
    
    .mt-4 {
        margin-top: 20px;
    }
    
    .form-text {
        font-size: 12px;
        color: #718096;
        margin-top: 5px;
    }
    
    .password-strength {
        margin: 15px 0;
    }
    
    .strength-meter {
        height: 8px;
        background-color: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .strength-meter-fill {
        height: 100%;
        width: 0;
        background-color: #e53e3e;
        transition: width 0.3s, background-color 0.3s;
    }
    
    .strength-text {
        font-size: 12px;
        margin-top: 5px;
        color: #718096;
    }
    
    .toggle-switch {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .toggle-input {
        display: none;
    }
    
    .toggle-label {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        background-color: #e2e8f0;
        border-radius: 12px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    .toggle-label:after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 20px;
        height: 20px;
        background-color: #fff;
        border-radius: 50%;
        transition: left 0.3s;
    }
    
    .toggle-input:checked + .toggle-label {
        background-color: #4a69bd;
    }
    
    .toggle-input:checked + .toggle-label:after {
        left: 28px;
    }
    
    .toggle-text {
        margin-left: 10px;
        font-weight: 500;
    }
    
    .password-info {
        font-size: 13px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password strength meter
        const passwordInput = document.getElementById('new_password');
        const strengthMeter = document.getElementById('strength-meter-fill');
        const strengthText = document.getElementById('strength-text');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                // Update strength meter
                strengthMeter.style.width = strength.score + '%';
                strengthMeter.style.backgroundColor = strength.color;
                strengthText.textContent = strength.label;
                strengthText.style.color = strength.color;
            });
        }
        
        // Password confirmation validation
        const passwordForm = document.getElementById('passwordForm');
        const confirmInput = document.getElementById('confirm_password');
        
        if (passwordForm && confirmInput) {
            passwordForm.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match');
                }
            });
        }
    });
    
    // Calculate password strength
    function calculatePasswordStrength(password) {
        let score = 0;
        let label = 'Very Weak';
        let color = '#e53e3e'; // Red
        
        if (!password) {
            return { score: 0, label, color };
        }
        
        // Length check
        if (password.length >= 8) score += 20;
        if (password.length >= 12) score += 10;
        
        // Complexity checks
        if (/[a-z]/.test(password)) score += 10; // Lowercase
        if (/[A-Z]/.test(password)) score += 15; // Uppercase
        if (/[0-9]/.test(password)) score += 15; // Numbers
        if (/[^a-zA-Z0-9]/.test(password)) score += 20; // Special chars
        
        // Variety check
        const uniqueChars = new Set(password).size;
        score += Math.min(uniqueChars * 2, 10);
        
        // Determine label and color based on score
        if (score >= 90) {
            label = 'Very Strong';
            color = '#2f855a'; // Green
        } else if (score >= 70) {
            label = 'Strong';
            color = '#38a169'; // Green
        } else if (score >= 50) {
            label = 'Moderate';
            color = '#d69e2e'; // Yellow
        } else if (score >= 30) {
            label = 'Weak';
            color = '#dd6b20'; // Orange
        }
        
        return { score, label, color };
    }
</script>

<?php
// Include footer
include "../admin/includes/footer.php";
?>