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
log_admin_activity($_SESSION["user_id"], 'settings_page_access', $conn);

// Initialize variables
$settings = [];
$success_message = "";
$error_message = "";

// Create settings table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_description TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$conn->query($sql);

// Default settings
$default_settings = [
    [
        'key' => 'site_name',
        'value' => 'Library Management System',
        'description' => 'Name of the library system',
        'type' => 'text'
    ],
    [
        'key' => 'loan_period',
        'value' => '14',
        'description' => 'Default loan period in days',
        'type' => 'number'
    ],
    [
        'key' => 'max_books',
        'value' => '5',
        'description' => 'Maximum number of books a user can borrow at once',
        'type' => 'number'
    ],
    [
        'key' => 'fine_amount',
        'value' => '1.00',
        'description' => 'Fine amount per day for overdue books',
        'type' => 'number'
    ],
    [
        'key' => 'reservation_limit',
        'value' => '3',
        'description' => 'Maximum number of active reservations per user',
        'type' => 'number'
    ],
    [
        'key' => 'allow_renewals',
        'value' => 'yes',
        'description' => 'Allow users to renew borrowed books',
        'type' => 'select',
        'options' => 'yes,no'
    ],
    [
        'key' => 'renewal_limit',
        'value' => '2',
        'description' => 'Maximum number of renewals per book',
        'type' => 'number'
    ],
    [
        'key' => 'admin_email',
        'value' => 'admin@gmail.com',
        'description' => 'Admin email address for notifications',
        'type' => 'email'
    ],
    [
        'key' => 'enable_notifications',
        'value' => 'yes',
        'description' => 'Enable system notifications',
        'type' => 'select',
        'options' => 'yes,no'
    ],
    [
        'key' => 'maintenance_mode',
        'value' => 'no',
        'description' => 'Put the system in maintenance mode',
        'type' => 'select',
        'options' => 'yes,no'
    ]
];

// Insert default settings if they don't exist
foreach ($default_settings as $setting) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $setting['key']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] == 0) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_description, setting_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $setting['key'], $setting['value'], $setting['description'], $setting['type']);
        $stmt->execute();
        $stmt->close();
        
        // If there are options, add them
        if (isset($setting['options'])) {
            $stmt = $conn->prepare("UPDATE system_settings SET setting_options = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $setting['options'], $setting['key']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid token");
    }
    
    // Update settings
    if (isset($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
            $stmt->close();
        }
        
        log_admin_activity($_SESSION["user_id"], 'settings_updated', $conn);
        $success_message = "Settings updated successfully";
    }
}

// Get all settings
$stmt = $conn->prepare("SELECT * FROM system_settings ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}
$stmt->close();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include header
$page_title = "System Settings";
include "../admin/includes/header.php";
?>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-cog"></i> System Settings</h1>
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
    
    <div class="card">
        <div class="card-header">
            <h2>General Settings</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="settings-grid">
                    <?php foreach ($settings as $key => $setting): ?>
                        <div class="setting-item">
                            <label for="<?php echo $key; ?>"><?php echo htmlspecialchars($setting['setting_description']); ?></label>
                            
                            <?php if ($setting['setting_type'] == 'select' && isset($setting['setting_options'])): ?>
                                <select name="settings[<?php echo $key; ?>]" id="<?php echo $key; ?>" class="form-control">
                                    <?php foreach (explode(',', $setting['setting_options']) as $option): ?>
                                        <option value="<?php echo $option; ?>" <?php echo ($setting['setting_value'] == $option) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($setting['setting_type'] == 'textarea'): ?>
                                <textarea name="settings[<?php echo $key; ?>]" id="<?php echo $key; ?>" class="form-control" rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                            <?php else: ?>
                                <input type="<?php echo $setting['setting_type']; ?>" name="settings[<?php echo $key; ?>]" id="<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                            <?php endif; ?>
                            
                            <small class="setting-help">
                                <?php 
                                switch ($key) {
                                    case 'loan_period':
                                        echo "Default number of days a book can be borrowed";
                                        break;
                                    case 'max_books':
                                        echo "Maximum books a user can borrow simultaneously";
                                        break;
                                    case 'fine_amount':
                                        echo "Amount in dollars charged per day for overdue books";
                                        break;
                                    case 'maintenance_mode':
                                        echo "When enabled, only admins can access the system";
                                        break;
                                    default:
                                        // No additional help text
                                }
                                ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h2>System Information</h2>
        </div>
        <div class="card-body">
            <div class="system-info">
                <div class="info-item">
                    <span class="info-label">PHP Version:</span>
                    <span class="info-value"><?php echo phpversion(); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">MySQL Version:</span>
                    <span class="info-value"><?php echo $conn->server_info; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Server Software:</span>
                    <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">System Date:</span>
                    <span class="info-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">System Path:</span>
                    <span class="info-value"><?php echo $_SERVER['DOCUMENT_ROOT']; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .setting-item {
        margin-bottom: 15px;
    }
    
    .setting-item label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .setting-help {
        display: block;
        color: #718096;
        font-size: 12px;
        margin-top: 5px;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .mt-4 {
        margin-top: 20px;
    }
    
    .system-info {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }
    
    .info-item {
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 5px;
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-weight: 500;
        color: #4a5568;
        margin-bottom: 5px;
    }
    
    .info-value {
        color: #2d3748;
    }
</style>

<?php
// Include footer
include "../admin/includes/footer.php";
?>