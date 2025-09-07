<?php
/**
 * Admin Panel - System Settings
 */

session_start();

if (!isset($_SESSION['admin_id']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

require_once '../db_connect.php';

// Handle settings update
if (isset($_POST['update_settings'])) {
    $updated_count = 0;
    $errors = [];
    
    foreach ($_POST as $key => $value) {
        if ($key === 'update_settings') continue;
        
        // Validate setting value based on type
        $stmt = $conn->prepare("SELECT setting_type, is_editable FROM system_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($setting = $result->fetch_assoc()) {
            if (!$setting['is_editable']) {
                $errors[] = "Setting '{$key}' is not editable.";
                continue;
            }
            
            // Type validation
            switch ($setting['setting_type']) {
                case 'integer':
                    if (!is_numeric($value)) {
                        $errors[] = "Setting '{$key}' must be a number.";
                        continue 2;
                    }
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = $value ? '1' : '0';
                    break;
                case 'json':
                    if (!json_decode($value)) {
                        $errors[] = "Setting '{$key}' must be valid JSON.";
                        continue 2;
                    }
                    break;
            }
            
            // Update setting
            $updateStmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $updateStmt->bind_param("sis", $value, $_SESSION['admin_id'], $key);
            
            if ($updateStmt->execute()) {
                $updated_count++;
            } else {
                $errors[] = "Failed to update setting '{$key}'.";
            }
        }
    }
    
    // Log the action
    $description = "Updated {$updated_count} system settings";
    if (!empty($errors)) {
        $description .= ". Errors: " . implode(", ", $errors);
    }
    
    $logStmt = $conn->prepare("INSERT INTO system_logs (admin_id, action, target_type, description, ip_address) VALUES (?, 'settings_update', 'system', ?, ?)");
    $logStmt->bind_param("iss", $_SESSION['admin_id'], $description, $_SERVER['REMOTE_ADDR']);
    $logStmt->execute();
    
    if (empty($errors)) {
        $_SESSION['admin_message'] = "{$updated_count} settings updated successfully.";
        $_SESSION['admin_message_type'] = 'success';
    } else {
        $_SESSION['admin_message'] = "Some settings could not be updated: " . implode(", ", $errors);
        $_SESSION['admin_message_type'] = 'error';
    }
    
    header("Location: settings.php");
    exit;
}

// Get all settings grouped by category
$settings = $conn->query("
    SELECT setting_key, setting_value, setting_type, description, is_editable 
    FROM system_settings 
    ORDER BY 
        CASE 
            WHEN setting_key LIKE 'site_%' THEN 1
            WHEN setting_key LIKE 'smtp_%' THEN 2
            WHEN setting_key LIKE 'max_%' THEN 3
            WHEN setting_key LIKE '%_mode' OR setting_key LIKE '%_enabled' THEN 4
            ELSE 5
        END,
        setting_key
")->fetch_all(MYSQLI_ASSOC);

// Group settings by category
$grouped_settings = [
    'Site Configuration' => [],
    'Email Settings' => [],
    'User Limits' => [],
    'System Settings' => [],
    'Other Settings' => []
];

foreach ($settings as $setting) {
    if (strpos($setting['setting_key'], 'site_') === 0) {
        $grouped_settings['Site Configuration'][] = $setting;
    } elseif (strpos($setting['setting_key'], 'smtp_') === 0) {
        $grouped_settings['Email Settings'][] = $setting;
    } elseif (strpos($setting['setting_key'], 'max_') === 0) {
        $grouped_settings['User Limits'][] = $setting;
    } elseif (in_array($setting['setting_key'], ['maintenance_mode', 'registration_enabled', 'email_verification_required', 'default_timezone'])) {
        $grouped_settings['System Settings'][] = $setting;
    } else {
        $grouped_settings['Other Settings'][] = $setting;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <p>Configure system-wide settings and preferences</p>
            </div>

            <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="admin-message <?php echo $_SESSION['admin_message_type'] ?? 'success'; ?>">
                    <?php echo $_SESSION['admin_message']; unset($_SESSION['admin_message'], $_SESSION['admin_message_type']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="settings-form">
                <?php foreach ($grouped_settings as $category => $categorySettings): ?>
                    <?php if (!empty($categorySettings)): ?>
                    <div class="dashboard-section">
                        <h2><i class="fas fa-<?php echo $category == 'Site Configuration' ? 'globe' : ($category == 'Email Settings' ? 'envelope' : ($category == 'User Limits' ? 'users' : 'cog')); ?>"></i> <?php echo $category; ?></h2>
                        
                        <div class="settings-grid">
                            <?php foreach ($categorySettings as $setting): ?>
                            <div class="form-group">
                                <label for="<?php echo $setting['setting_key']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                    <?php if (!$setting['is_editable']): ?>
                                        <span class="readonly-indicator">(Read Only)</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($setting['description']): ?>
                                    <p class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></p>
                                <?php endif; ?>
                                
                                <?php
                                $inputType = 'text';
                                $inputValue = htmlspecialchars($setting['setting_value']);
                                $disabled = !$setting['is_editable'] ? 'readonly' : '';
                                
                                switch ($setting['setting_type']) {
                                    case 'boolean':
                                        ?>
                                        <div class="toggle-switch">
                                            <input type="checkbox" 
                                                   id="<?php echo $setting['setting_key']; ?>" 
                                                   name="<?php echo $setting['setting_key']; ?>"
                                                   value="1"
                                                   <?php echo $setting['setting_value'] ? 'checked' : ''; ?>
                                                   <?php echo $disabled; ?>>
                                            <label for="<?php echo $setting['setting_key']; ?>" class="toggle-label"></label>
                                        </div>
                                        <?php
                                        break;
                                    
                                    case 'integer':
                                        ?>
                                        <input type="number" 
                                               id="<?php echo $setting['setting_key']; ?>" 
                                               name="<?php echo $setting['setting_key']; ?>"
                                               value="<?php echo $inputValue; ?>"
                                               <?php echo $disabled; ?>>
                                        <?php
                                        break;
                                    
                                    case 'json':
                                        ?>
                                        <textarea id="<?php echo $setting['setting_key']; ?>" 
                                                  name="<?php echo $setting['setting_key']; ?>"
                                                  rows="4"
                                                  <?php echo $disabled; ?>><?php echo $inputValue; ?></textarea>
                                        <?php
                                        break;
                                    
                                    default:
                                        // Special cases
                                        if ($setting['setting_key'] == 'smtp_password') {
                                            ?>
                                            <input type="password" 
                                                   id="<?php echo $setting['setting_key']; ?>" 
                                                   name="<?php echo $setting['setting_key']; ?>"
                                                   value="<?php echo $inputValue; ?>"
                                                   placeholder="Enter SMTP password"
                                                   <?php echo $disabled; ?>>
                                            <?php
                                        } elseif ($setting['setting_key'] == 'smtp_encryption') {
                                            ?>
                                            <select id="<?php echo $setting['setting_key']; ?>" 
                                                    name="<?php echo $setting['setting_key']; ?>"
                                                    <?php echo $disabled; ?>>
                                                <option value="tls" <?php echo $setting['setting_value'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo $setting['setting_value'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="none" <?php echo $setting['setting_value'] == 'none' ? 'selected' : ''; ?>>None</option>
                                            </select>
                                            <?php
                                        } elseif ($setting['setting_key'] == 'default_timezone') {
                                            $timezones = timezone_identifiers_list();
                                            ?>
                                            <select id="<?php echo $setting['setting_key']; ?>" 
                                                    name="<?php echo $setting['setting_key']; ?>"
                                                    <?php echo $disabled; ?>>
                                                <?php foreach ($timezones as $tz): ?>
                                                    <option value="<?php echo $tz; ?>" <?php echo $setting['setting_value'] == $tz ? 'selected' : ''; ?>>
                                                        <?php echo $tz; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php
                                        } else {
                                            ?>
                                            <input type="text" 
                                                   id="<?php echo $setting['setting_key']; ?>" 
                                                   name="<?php echo $setting['setting_key']; ?>"
                                                   value="<?php echo $inputValue; ?>"
                                                   <?php echo $disabled; ?>>
                                            <?php
                                        }
                                        break;
                                }
                                ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div class="form-actions">
                    <button type="submit" name="update_settings" class="btn primary">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                    <button type="button" onclick="testEmailSettings()" class="btn secondary">
                        <i class="fas fa-envelope"></i> Test Email Settings
                    </button>
                    <button type="button" onclick="resetToDefaults()" class="btn danger">
                        <i class="fas fa-undo"></i> Reset to Defaults
                    </button>
                </div>
            </form>

            <!-- System Info -->
            <div class="dashboard-section">
                <h2><i class="fas fa-info-circle"></i> System Information</h2>
                <div class="system-info-grid">
                    <div class="info-item">
                        <strong>PHP Version:</strong>
                        <span><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>MySQL Version:</strong>
                        <span><?php echo $conn->server_info; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Server Software:</strong>
                        <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Memory Limit:</strong>
                        <span><?php echo ini_get('memory_limit'); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Upload Max Size:</strong>
                        <span><?php echo ini_get('upload_max_filesize'); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Current Time:</strong>
                        <span><?php echo date('Y-m-d H:i:s T'); ?></span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Test Email Modal -->
    <div id="testEmailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Test Email Settings</h3>
                <span class="modal-close" onclick="closeTestEmailModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="testEmailForm">
                    <div class="form-group">
                        <label for="test_email">Test Email Address:</label>
                        <input type="email" id="test_email" name="test_email" required 
                               value="<?php echo $_SESSION['admin_email'] ?? ''; ?>" 
                               placeholder="Enter email to send test message">
                    </div>
                    <div class="form-buttons">
                        <button type="submit" class="btn primary">
                            <i class="fas fa-paper-plane"></i> Send Test Email
                        </button>
                        <button type="button" onclick="closeTestEmailModal()" class="btn secondary">Cancel</button>
                    </div>
                </form>
                <div id="testEmailResult" class="test-result" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script>
    function testEmailSettings() {
        document.getElementById('testEmailModal').style.display = 'block';
    }

    function closeTestEmailModal() {
        document.getElementById('testEmailModal').style.display = 'none';
        document.getElementById('testEmailResult').style.display = 'none';
    }

    document.getElementById('testEmailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('test_email').value;
        const resultDiv = document.getElementById('testEmailResult');
        
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending test email...';
        resultDiv.className = 'test-result';
        
        fetch('ajax/test_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> Test email sent successfully!';
                resultDiv.className = 'test-result success';
            } else {
                resultDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed to send test email: ' + data.error;
                resultDiv.className = 'test-result error';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error: ' + error.message;
            resultDiv.className = 'test-result error';
        });
    });

    function resetToDefaults() {
        if (confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.')) {
            fetch('ajax/reset_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error resetting settings: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('testEmailModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Add validation for numeric inputs
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
    });

    // Add syntax highlighting for JSON fields
    document.querySelectorAll('textarea').forEach(textarea => {
        if (textarea.name && textarea.name.includes('json')) {
            textarea.addEventListener('blur', function() {
                try {
                    const parsed = JSON.parse(this.value);
                    this.value = JSON.stringify(parsed, null, 2);
                    this.style.borderColor = '#28a745';
                } catch (e) {
                    this.style.borderColor = '#dc3545';
                }
            });
        }
    });
    </script>

    <style>
    .settings-form {
        max-width: none;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }

    .form-group {
        background: var(--light-grey);
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid var(--admin-accent);
    }

    .form-group label {
        font-size: 1rem;
        font-weight: 600;
        color: var(--admin-primary);
        margin-bottom: 8px;
        display: block;
    }

    .readonly-indicator {
        font-size: 0.8rem;
        color: var(--mid-grey);
        font-weight: normal;
        font-style: italic;
    }

    .setting-description {
        font-size: 0.85rem;
        color: var(--mid-grey);
        margin-bottom: 10px;
        line-height: 1.4;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid var(--border-color);
        border-radius: 5px;
        font-size: 0.95rem;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--admin-accent);
    }

    .form-group input[readonly] {
        background-color: #f8f9fa;
        color: var(--mid-grey);
    }

    /* Toggle Switch Styles */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }

    .toggle-switch input[type="checkbox"] {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-label {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .toggle-label:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input[type="checkbox"]:checked + .toggle-label {
        background-color: var(--admin-accent);
    }

    input[type="checkbox"]:checked + .toggle-label:before {
        transform: translateX(26px);
    }

    .form-actions {
        text-align: center;
        margin: 40px 0;
        padding: 20px;
        background: var(--white);
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }

    .form-actions .btn {
        margin: 0 10px;
    }

    /* System Info */
    .system-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 15px;
        background: var(--light-grey);
        border-radius: 5px;
        border-left: 3px solid var(--admin-accent);
    }

    .info-item strong {
        color: var(--admin-primary);
    }

    /* Test Result Styles */
    .test-result {
        margin-top: 15px;
        padding: 15px;
        border-radius: 5px;
        font-weight: 500;
    }

    .test-result.success {
        background-color: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
    }

    .test-result.error {
        background-color: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
        
        .form-actions .btn {
            display: block;
            margin: 10px 0;
            width: 100%;
        }
        
        .system-info-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>