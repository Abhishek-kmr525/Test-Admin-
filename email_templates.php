<?php
/**
 * Admin - Email Templates Management
 * File: admin/email_templates.php
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit;
}

require_once '../db_connect.php';

// Handle template actions
$message = '';
$messageType = '';

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create_template':
            $name = trim($_POST['name']);
            $subject = trim($_POST['subject']);
            $content = trim($_POST['content']);
            $type = $_POST['type'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($subject) || empty($content)) {
                $message = "All fields are required.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("INSERT INTO email_templates (name, subject, content, type, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssi", $name, $subject, $content, $type, $isActive);
                
                if ($stmt->execute()) {
                    $message = "Email template created successfully.";
                    $messageType = "success";
                } else {
                    $message = "Error creating template: " . $conn->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            break;
            
        case 'update_template':
            $id = (int)$_POST['template_id'];
            $name = trim($_POST['name']);
            $subject = trim($_POST['subject']);
            $content = trim($_POST['content']);
            $type = $_POST['type'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($subject) || empty($content)) {
                $message = "All fields are required.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("UPDATE email_templates SET name = ?, subject = ?, content = ?, type = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssssii", $name, $subject, $content, $type, $isActive, $id);
                
                if ($stmt->execute()) {
                    $message = "Email template updated successfully.";
                    $messageType = "success";
                } else {
                    $message = "Error updating template: " . $conn->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            break;
            
        case 'delete_template':
            $id = (int)$_POST['template_id'];
            
            // Check if template is being used
            $stmt = $conn->prepare("SELECT COUNT(*) as usage_count FROM reminders WHERE template_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $usageCount = $result->fetch_assoc()['usage_count'];
            $stmt->close();
            
            if ($usageCount > 0) {
                $message = "Cannot delete template. It is currently being used by {$usageCount} reminder(s).";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("DELETE FROM email_templates WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $message = "Email template deleted successfully.";
                    $messageType = "success";
                } else {
                    $message = "Error deleting template: " . $conn->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            break;
            
        case 'toggle_status':
            $id = (int)$_POST['template_id'];
            $stmt = $conn->prepare("UPDATE email_templates SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "Template status updated successfully.";
                $messageType = "success";
            } else {
                $message = "Error updating template status.";
                $messageType = "error";
            }
            $stmt->close();
            break;
    }
}

// Get templates with usage counts
$query = "SELECT et.*, 
          (SELECT COUNT(*) FROM reminders r WHERE r.template_id = et.id) as usage_count
          FROM email_templates et
          ORDER BY et.created_at DESC";

$result = $conn->query($query);
$templates = [];
while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}

// Get template types
$templateTypes = [
    'system' => 'System',
    'user' => 'User',
    'notification' => 'Notification',
    'marketing' => 'Marketing'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Templates - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="content-header">
                <h1><i class="fas fa-envelope"></i> Email Templates</h1>
                <div class="header-actions">
                    <button onclick="showCreateTemplate()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Template
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Templates List -->
            <div class="templates-container">
                <?php if (!empty($templates)): ?>
                    <div class="templates-grid">
                        <?php foreach ($templates as $template): ?>
                            <div class="template-card <?php echo !$template['is_active'] ? 'inactive' : ''; ?>">
                                <div class="template-header">
                                    <div class="template-title">
                                        <h3><?php echo htmlspecialchars($template['name']); ?></h3>
                                        <span class="template-type"><?php echo $templateTypes[$template['type']] ?? 'Custom'; ?></span>
                                    </div>
                                    <div class="template-status">
                                        <?php if ($template['is_active']): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="template-content">
                                    <div class="template-subject">
                                        <strong>Subject:</strong> <?php echo htmlspecialchars($template['subject']); ?>
                                    </div>
                                    <div class="template-preview">
                                        <?php echo htmlspecialchars(substr(strip_tags($template['content']), 0, 150)); ?>
                                        <?php if (strlen(strip_tags($template['content'])) > 150): ?>...<?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="template-meta">
                                    <div class="template-usage">
                                        <i class="fas fa-users"></i> Used by <?php echo $template['usage_count']; ?> reminder(s)
                                    </div>
                                    <div class="template-date">
                                        <i class="fas fa-calendar"></i> 
                                        <?php 
                                        $createdAt = new DateTime($template['created_at']);
                                        echo $createdAt->format('M j, Y');
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="template-actions">
                                    <button onclick="viewTemplate(<?php echo $template['id']; ?>)" class="btn-action btn-view" title="View Template">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="editTemplate(<?php echo $template['id']; ?>)" class="btn-action btn-edit" title="Edit Template">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                        <button type="submit" class="btn-action <?php echo $template['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                title="<?php echo $template['is_active'] ? 'Deactivate' : 'Activate'; ?> Template">
                                            <i class="fas fa-<?php echo $template['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                    </form>
                                    <?php if ($template['usage_count'] == 0): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_template">
                                            <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                            <button type="submit" class="btn-action btn-danger" title="Delete Template" 
                                                    onclick="return confirm('Are you sure you want to delete this template?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn-action btn-disabled" title="Cannot delete - template is in use">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-envelope"></i>
                        <h3>No Email Templates</h3>
                        <p>Create your first email template to get started.</p>
                        <button onclick="showCreateTemplate()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Template
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create/Edit Template Modal -->
    <div id="templateModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="modalTitle">Create Email Template</h3>
                <span class="close" onclick="closeTemplateModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="templateForm" method="POST">
                    <input type="hidden" id="templateAction" name="action" value="create_template">
                    <input type="hidden" id="templateId" name="template_id" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="templateName">Template Name *</label>
                            <input type="text" id="templateName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="templateType">Type</label>
                            <select id="templateType" name="type" required>
                                <?php foreach ($templateTypes as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="templateSubject">Subject *</label>
                        <input type="text" id="templateSubject" name="subject" required 
                               placeholder="Email subject line (can include variables like {name}, {date})">
                    </div>
                    
                    <div class="form-group">
                        <label for="templateContent">Content *</label>
                        <textarea id="templateContent" name="content" rows="15" required 
                                  placeholder="Email content (HTML allowed). Available variables: {name}, {email}, {message}, {date}, {time}"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="templateActive" name="is_active" checked>
                            <span class="checkmark"></span>
                            Active (template can be used)
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeTemplateModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Template Modal -->
    <div id="viewTemplateModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Template Preview</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="templatePreview">
                <!-- Template preview will be loaded here -->
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        function showCreateTemplate() {
            document.getElementById('modalTitle').textContent = 'Create Email Template';
            document.getElementById('templateAction').value = 'create_template';
            document.getElementById('templateId').value = '';
            document.getElementById('templateForm').reset();
            document.getElementById('templateActive').checked = true;
            document.getElementById('templateModal').style.display = 'block';
        }

        function editTemplate(id) {
            // Fetch template data and populate form
            fetch(`ajax/get_template.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = 'Edit Email Template';
                        document.getElementById('templateAction').value = 'update_template';
                        document.getElementById('templateId').value = id;
                        document.getElementById('templateName').value = data.template.name;
                        document.getElementById('templateType').value = data.template.type;
                        document.getElementById('templateSubject').value = data.template.subject;
                        document.getElementById('templateContent').value = data.template.content;
                        document.getElementById('templateActive').checked = data.template.is_active == 1;
                        document.getElementById('templateModal').style.display = 'block';
                    } else {
                        alert('Error loading template data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading template data');
                });
        }

        function viewTemplate(id) {
            fetch(`ajax/get_template_preview.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('templatePreview').innerHTML = data.html;
                        document.getElementById('viewTemplateModal').style.display = 'block';
                    } else {
                        alert('Error loading template preview');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading template preview');
                });
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('viewTemplateModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const templateModal = document.getElementById('templateModal');
            const viewModal = document.getElementById('viewTemplateModal');
            if (event.target == templateModal) {
                templateModal.style.display = 'none';
            }
            if (event.target == viewModal) {
                viewModal.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            const name = document.getElementById('templateName').value.trim();
            const subject = document.getElementById('templateSubject').value.trim();
            const content = document.getElementById('templateContent').value.trim();

            if (!name || !subject || !content) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });
    </script>
</body>
</html>