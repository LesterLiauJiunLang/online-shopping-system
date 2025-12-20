<?php
// [file name]: categories.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';

$db = Database::getConnection();

$error = '';
$success = '';
$current_category = null;
$product_counts = [];

// Handle Add/Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $category_name = trim($_POST['category_name'] ?? '');


    // Validation
    if (empty($category_name)) {
        $error = "Category name is required.";
    } elseif (strlen($category_name) > 100) {
        $error = "Category name cannot exceed 100 characters.";
    } else {
        try {
            if ($action === 'add') {
                // Check if category already exists
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM category WHERE LOWER(name) = LOWER(?)");
                $checkStmt->execute([$category_name]);
                if ($checkStmt->fetchColumn() > 0) {
                    $error = "Category '$category_name' already exists!";
                } else {
                    // Add new category
                    $stmt = $db->prepare("INSERT INTO category (name) VALUES (?)");
                    $stmt->execute([$category_name]);
                    $success = "✅ Category '$category_name' added successfully!";
                }
                
            } elseif ($action === 'edit' && !empty($category_id)) {
                // Check if new name conflicts with other categories
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM category WHERE LOWER(name) = LOWER(?) AND category_id != ?");
                $checkStmt->execute([$category_name, $category_id]);
                if ($checkStmt->fetchColumn() > 0) {
                    $error = "Category '$category_name' already exists!";
                } else {
                    // Update existing category
                    $stmt = $db->prepare("UPDATE category SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE category_id = ?");
                    $stmt->execute([$category_name, $category_id]);
                    $success = "✅ Category '$category_name' updated successfully!";
                }
                
            } elseif ($action === 'delete' && !empty($category_id)) {
                // Check if category has products
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $checkStmt->execute([$category_id]);
                $productCount = $checkStmt->fetchColumn();
                
                if ($productCount > 0) {
                    $error = "Cannot delete category. It has $productCount product(s) associated with it.";
                } else {
                    $stmt = $db->prepare("DELETE FROM category WHERE category_id = ?");
                    $stmt->execute([$category_id]);
                    $success = "🗑️ Category deleted successfully!";
                }
            }
            
            // Clear form if successful
            if (empty($error)) {
                $_POST = [];
                $current_category = null;
            }
            
        } catch (Exception $e) {
            error_log("Category error: " . $e->getMessage());
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Edit request from GET
if (isset($_GET['edit'])) {
    $category_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM category WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $current_category = $stmt->fetch();
}

// Handle Delete request from GET
if (isset($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    // Get category name for message
    $stmt = $db->prepare("SELECT name FROM category WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $category_name = $stmt->fetchColumn();
    
    // Check if category has products
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $checkStmt->execute([$category_id]);
    $productCount = $checkStmt->fetchColumn();
    
    if ($productCount > 0) {
        $error = "Cannot delete '$category_name'. It has $productCount product(s) associated with it.";
    } else {
        $stmt = $db->prepare("DELETE FROM category WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $success = "🗑️ Category '$category_name' deleted successfully!";
    }
}

// Get all categories
$stmt = $db->prepare("SELECT c.*, COUNT(p.product_id) as product_count 
                      FROM category c 
                      LEFT JOIN products p ON c.category_id = p.category_id 
                      GROUP BY c.category_id 
                      ORDER BY c.name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get product counts for each category
foreach ($categories as $category) {
    $product_counts[$category['category_id']] = $category['product_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="categories-page">
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">🏷️ Manage Categories</h1>
            <div class="category-stats">
                <div class="stat-badge">
                    <i class="fas fa-layer-group"></i>
                    <?php echo count($categories); ?> Categories
                </div>
                <div class="stat-badge">
                    <i class="fas fa-box"></i>
                    <?php echo array_sum($product_counts); ?> Total Products
                </div>
            </div>
        </div>
        
        <div class="admin-nav">
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="products.php">📋 Products</a>
            <a href="product_add.php">➕ Add Product</a>
            <a href="orders.php">📦 Orders</a>
            <a href="categories.php" class="active">🏷️ Categories</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message" style="animation: slideIn 0.3s ease-out;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message" style="animation: slideIn 0.3s ease-out;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="two-column-layout">
            <!-- Left Column: Category Form -->
            <div class="form-column">
                <div class="category-form-card">
                    
                    <h3 class="form-section-title">
                        <?php echo $current_category ? '✏️ Edit Category' : '➕ Add New Category'; ?>
                    </h3>
                    
                    <form method="POST" action="" id="categoryForm">
                        <?php if ($current_category): ?>
                            <input type="hidden" name="category_id" value="<?php echo $current_category['category_id']; ?>">
                            <input type="hidden" name="action" value="edit">
                        <?php else: ?>
                            <input type="hidden" name="action" value="add">
                        <?php endif; ?>
                        
                        <div class="input-group">
                            <label for="category_name">
                                <i class="fas fa-tag"></i> Category Name
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="category_name" 
                                   name="category_name" 
                                   value="<?php echo htmlspecialchars($_POST['category_name'] ?? $current_category['name'] ?? ''); ?>" 
                                   required 
                                   placeholder="Enter category name"
                                   maxlength="100"
                                   autocomplete="off">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary-gradient">
                                <?php if ($current_category): ?>
                                    <i class="fas fa-save"></i> Update Category
                                <?php else: ?>
                                    <i class="fas fa-plus"></i> Add Category
                                <?php endif; ?>
                            </button>
                            
                            <?php if ($current_category): ?>
                                <a href="categories.php" class="btn-secondary-gradient">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php else: ?>
                                <button type="reset" class="btn-secondary-gradient" onclick="resetForm()">
                                    <i class="fas fa-eraser"></i> Clear
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Right Column: Category List -->
            <div class="list-column">
                <div class="category-card">
                    <h3 style="margin: 0 0 25px 0; color: #333; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list"></i> All Categories
                        <span style="background: #5a4bff; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px;">
                            <?php echo count($categories); ?>
                        </span>
                    </h3>
                    
                    <?php if (empty($categories)): ?>
                        <div class="empty-categories">
                            <div class="empty-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <h3 style="color: #666; margin-bottom: 15px;">No Categories Found</h3>
                            <p style="color: #888; max-width: 400px; margin: 0 auto 25px;">
                                You haven't created any categories yet. Start by adding your first category using the form on the left.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($categories as $index => $category): 
                                $product_count = $product_counts[$category['category_id']] ?? 0;
                                $is_current = $current_category && $current_category['category_id'] == $category['category_id'];
                            ?>
                                <div class="category-item <?php echo $is_current ? 'current-edit' : ''; ?>">
                                    <div class="category-info">
                                        <div class="category-name">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                            <?php if ($is_current): ?>
                                                <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
                                                    <i class="fas fa-pen"></i> Editing
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="category-meta">
                                            <span class="product-count">
                                                <i class="fas fa-box"></i> <?php echo $product_count; ?> product(s)
                                            </span>
                                            <span style="color: #888;">
                                                <i class="far fa-calendar"></i> 
                                                <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="category-actions">
                                        <a href="categories.php?edit=<?php echo $category['category_id']; ?>" 
                                           class="btn-category btn-edit-category">
                                           <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="categories.php?delete=<?php echo $category['category_id']; ?>" 
                                           class="btn-category btn-delete-category"
                                           onclick="return confirmDelete('<?php echo addslashes($category['name']); ?>', <?php echo $product_count; ?>)">
                                           <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            const categoryName = document.getElementById('category_name').value.trim();
            if (!categoryName) {
                e.preventDefault();
                alert('Please enter a category name');
                document.getElementById('category_name').focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            // Re-enable button if form submission fails
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
            
            return true;
        });
        
        // Delete confirmation with product count warning
        function confirmDelete(categoryName, productCount) {
            if (productCount > 0) {
                alert(`Cannot delete "${categoryName}" because it has ${productCount} product(s) associated with it.\n\nPlease reassign or delete the products first.`);
                return false;
            }
            
            return confirm(`Are you sure you want to delete "${categoryName}"?\n\nThis action cannot be undone.`);
        }
        
        // Reset form
        function resetForm() {
            document.getElementById('categoryForm').reset();
            document.getElementById('category_name').focus();
        }
        
        // Auto-focus on category name field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('category_name').focus();
        });
    </script>
</body>
</html>