<?php
/**
 * Admin - Categories Management
 * File: admin/categories.php
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit;
}

require_once '../db_connect.php';

// Handle category actions
$message = '';
$messageType = '';

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create_category':
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $color = $_POST['color'];
            $icon = $_POST['icon'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                $message = "Category name is required.";
                $messageType = "error";
            } else {
                // Check if category name already exists
                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE name = ?");
                $checkStmt->bind_param("s", $name);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $exists = $checkResult->fetch_assoc()['count'] > 0;
                $checkStmt->close();
                
                if ($exists) {
                    $message = "A category with this name already exists.";
                    $messageType = "error";
                } else {
                    $stmt = $conn->prepare("INSERT INTO categories (name, description, color, icon, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("ssssi", $name, $description, $color, $icon, $isActive);
                    
                    if ($stmt->execute()) {
                        $message = "Category created successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Error creating category: " . $conn->error;
                        $messageType = "error";
                    }
                    $stmt->close();
                }
            }
            break;
            
        case 'update_category':
            $id = (int)$_POST['category_id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $color = $_POST['color'];
            $icon = $_POST['icon'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                $message = "Category name is required.";
                $messageType = "error";
            } else {
                // Check if category name already exists (excluding current category)
                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE name = ? AND id != ?");
                $checkStmt->bind_param("si", $name, $id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $exists = $checkResult->fetch_assoc()['count'] > 0;
                $checkStmt->close();
                
                if ($exists) {
                    $message = "A category with this name already exists.";
                    $messageType = "error";
                } else {
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, color = ?, icon = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("ssssii", $name, $description, $color, $icon, $isActive, $id);
                    
                    if ($stmt->execute()) {
                        $message = "Category updated successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Error updating category: " . $conn->error;
                        $messageType = "error";
                    }
                    $stmt->close();
                }
            }
            break;
            
        case 'delete_category':
            $id = (int)$_POST['category_id'];
            
            // Check if category is being used
            $stmt = $conn->prepare("SELECT COUNT(*) as usage_count FROM reminders WHERE category_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $usageCount = $result->fetch_assoc()['usage_count'];
            $stmt->close();
            
            if ($usageCount > 0) {
                $message = "Cannot delete category. It is currently being used by {$usageCount} reminder(s).";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $message = "Category deleted successfully.";
                    $messageType = "success";
                } else {
                    $message = "Error deleting category: " . $conn->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            break;
            
        case 'toggle_status':
            $id = (int)$_POST['category_id'];
            $stmt = $conn->prepare("UPDATE categories SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "Category status updated successfully.";
                $messageType = "success";
            } else {
                $message = "Error updating category status.";
                $messageType = "error";
            }
            $stmt->close();
            break;
    }
}

// Get categories with usage counts
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM reminders r WHERE r.category_id = c.id) as usage_count
          FROM categories c
          ORDER BY c.name ASC";

$result = $conn->query($query);
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Available icons for categories
$availableIcons = [
    'fas fa-calendar' => 'Calendar',
    'fas fa-birthday-cake' => 'Birthday',
    'fas fa-briefcase' => 'Work',
    'fas fa-user-md' => 'Medical',
    'fas fa-graduation-cap' => 'Education',
    'fas fa-home' => 'Home',
    'fas fa-car' => 'Transportation',
    'fas fa-heart' => 'Personal',
    'fas fa-dollar-sign' => 'Finance',
    'fas fa-dumbbell' => 'Fitness',
    'fas fa-utensils' => 'Food',
    'fas fa-plane' => 'Travel',
    'fas fa-bell' => 'General',
    'fas fa-star' => 'Important',
    'fas fa-clock' => 'Time',
    'fas fa-check' => 'Task'
];

// Available colors for categories
$availableColors = [
    '#0066CC' => 'Blue',
    '#28a745' => 'Green', 
    '#FF6600' => 'Orange',
    '#dc3545' => 'Red',
    '#6f42c1' => 'Purple',
    '#ffc107' => 'Yellow',
    '#20c997' => 'Teal',
    '#fd7e14' => 'Orange Alt',
    '#e83e8c' => 'Pink',
    '#17a2b8' => 'Cyan'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Admin Panel</title>
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
                <h1><i class="fas fa-tags"></i> Categories</h1>
                <div class="header-actions">
                    <span class="total-count">Total: <?php echo count($categories); ?> categories</span>
                    <button onclick="showCreateCategory()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Category
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Categories Grid -->
            <div class="categories-container">
                <?php if (!empty($categories)): ?>
                    <div class="categories-grid">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-card <?php echo !$category['is_active'] ? 'inactive' : ''; ?>">
                                <div class="category-header">
                                    <div class="category-icon" style="background-color: <?php echo $category['color']; ?>;">
                                        <i class="<?php echo $category['icon']; ?>"></i>
                                    </div>
                                    <div class="category-info">
                                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                        <p><?php echo htmlspecialchars($category['description'] ?: 'No description'); ?></p>
                                    </div>
                                    <div class="category-status">
                                        <?php if ($category['is_active']): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="category-stats">
                                    <div class="stat-item">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $category['usage_count']; ?> reminder(s)</span>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>
                                            <?php 
                                            $createdAt = new DateTime($category['created_at']);
                                            echo $createdAt->format('M j, Y');
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="category-actions">
                                    <button onclick="editCategory(<?php echo $category['id']; ?>)" class="btn-action btn-edit" title="Edit Category">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" class="btn-action <?php echo $category['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                title="<?php echo $category['is_active'] ? 'Deactivate' : 'Activate'; ?> Category">
                                            <i class="fas fa-<?php echo $category['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                    </form>
                                    <?php if ($category['usage_count'] == 0): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" class="btn-action btn-danger" title="Delete Category" 
                                                    onclick="return confirm('Are you sure you want to delete this category?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn-action btn-disabled" title="Cannot delete - category is in use">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <h3>No Categories</h3>
                        <p>Create your first category to organize reminders.</p>
                        <button onclick="showCreateCategory()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Category
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create/Edit Category Modal -->
    <div id="categoryModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Create Category</h3>
                <span class="close" onclick="closeCategoryModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="categoryForm" method="POST">
                    <input type="hidden" id="categoryAction" name="action" value="create_category">
                    <input type="hidden" id="categoryId" name="category_id" value="">
                    
                    <div class="form-group">
                        <label for="categoryName">Category Name *</label>
                        <input type="text" id="categoryName" name="name" required maxlength="50">
                    </div>
                    
                    <div class="form-group">
                        <label for="categoryDescription">Description</label>
                        <textarea id="categoryDescription" name="description" rows="3" maxlength="200" 
                                  placeholder="Brief description of this category"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoryColor">Color</label>
                            <div class="color-picker">
                                <select id="categoryColor" name="color">
                                    <?php foreach ($availableColors as $colorValue => $colorName): ?>
                                        <option value="<?php echo $colorValue; ?>" data-color="<?php echo $colorValue; ?>">
                                            <?php echo $colorName; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="color-preview" id="colorPreview"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="categoryIcon">Icon</label>
                            <div class="icon-picker">
                                <select id="categoryIcon" name="icon">
                                    <?php foreach ($availableIcons as $iconClass => $iconName): ?>
                                        <option value="<?php echo $iconClass; ?>" data-icon="<?php echo $iconClass; ?>">
                                            <?php echo $iconName; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="icon-preview" id="iconPreview">
                                    <i class="fas fa-calendar"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="categoryActive" name="is_active" checked>
                            <span class="checkmark"></span>
                            Active (category can be used)
                        </label>
                    </div>
                    
                    <div class="category-preview">
                        <h4>Preview:</h4>
                        <div class="preview-category-card">
                            <div class="preview-icon" id="previewIcon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="preview-info">
                                <h5 id="previewName">Category Name</h5>
                                <p id="previewDescription">Description will appear here</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" onclick="closeCategoryModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        function showCreateCategory() {
            document.getElementById('modalTitle').textContent = 'Create Category';
            document.getElementById('categoryAction').value = 'create_category';
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryActive').checked = true;
            updatePreview();
            document.getElementById('categoryModal').style.display = 'block';
        }

        function editCategory(id) {
            // Fetch category data and populate form
            fetch(`ajax/get_category.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = 'Edit Category';
                        document.getElementById('categoryAction').value = 'update_category';
                        document.getElementById('categoryId').value = id;
                        document.getElementById('categoryName').value = data.category.name;
                        document.getElementById('categoryDescription').value = data.category.description || '';
                        document.getElementById('categoryColor').value = data.category.color;
                        document.getElementById('categoryIcon').value = data.category.icon;
                        document.getElementById('categoryActive').checked = data.category.is_active == 1;
                        updatePreview();
                        document.getElementById('categoryModal').style.display = 'block';
                    } else {
                        alert('Error loading category data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading category data');
                });
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        function updatePreview() {
            const name = document.getElementById('categoryName').value || 'Category Name';
            const description = document.getElementById('categoryDescription').value || 'Description will appear here';
            const color = document.getElementById('categoryColor').value;
            const icon = document.getElementById('categoryIcon').value;

            // Update preview
            document.getElementById('previewName').textContent = name;
            document.getElementById('previewDescription').textContent = description;
            document.getElementById('previewIcon').style.backgroundColor = color;
            document.getElementById('previewIcon').innerHTML = `<i class="${icon}"></i>`;

            // Update color and icon previews
            document.getElementById('colorPreview').style.backgroundColor = color;
            document.getElementById('iconPreview').innerHTML = `<i class="${icon}"></i>`;
        }

        // Event listeners for real-time preview updates
        document.getElementById('categoryName').addEventListener('input', updatePreview);
        document.getElementById('categoryDescription').addEventListener('input', updatePreview);
        document.getElementById('categoryColor').addEventListener('change', updatePreview);
        document.getElementById('categoryIcon').addEventListener('change', updatePreview);

        // Initialize preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('categoryModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            const name = document.getElementById('categoryName').value.trim();

            if (!name) {
                e.preventDefault();
                alert('Please enter a category name.');
                return false;
            }
        });
    </script>
</body>
</html>