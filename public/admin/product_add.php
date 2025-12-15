<?php
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

// Check if editing existing product
$product = null;
$is_edit = false;

if (isset($_GET['edit'])) {
    $product_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    $is_edit = true;
}

// Get categories for dropdown
$categories = $db->query("SELECT * FROM category ORDER BY name")->fetchAll();

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $stock_quantity = trim($_POST['stock_quantity']);
    $category_id = $_POST['category_id'] ?: null;
    
    // Validation
    if (empty($name) || empty($price)) {
        $error = "Product name and price are required.";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Price must be a positive number.";
    } elseif (!is_numeric($stock_quantity) || $stock_quantity < 0) {
        $error = "Stock quantity must be a non-negative number.";
    } else {
        try {
            if ($is_edit && $product) {
                // Update existing product
                $stmt = $db->prepare("
                    UPDATE products SET 
                    name = ?, description = ?, price = ?, stock_quantity = ?, category_id = ?
                    WHERE product_id = ?
                ");
                $stmt->execute([$name, $description, $price, $stock_quantity, $category_id, $product['product_id']]);
                
                $success = "Product updated successfully!";
            } else {
                // Insert new product
                $stmt = $db->prepare("
                    INSERT INTO products (name, description, price, stock_quantity, category_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $price, $stock_quantity, $category_id]);
                
                $success = "Product added successfully!";
                
                // Clear form for new entry
                if (!$is_edit) {
                    $_POST = [];
                }
            }
        } catch (Exception $e) {
            $error = "Error saving product: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Product - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title"><?php echo $is_edit ? '✏️ Edit Product' : '➕ Add New Product'; ?></h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
            </div>
        </div>
        
        <div class="admin-nav">
            <a href="products.php">📋 Products</a>
            <a href="product_add.php" class="active">➕ Add Product</a>
            <a href="orders.php">📦 Orders</a>
            <a href="../dashboard.php">🏠 Dashboard</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="product-form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Product Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name'] ?? ''); ?>" 
                           required placeholder="Enter product name">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Enter product description"><?php echo htmlspecialchars($_POST['description'] ?? $product['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price (RM) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" min="0.01"
                               value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price'] ?? ''); ?>" 
                               required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0"
                               value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? $product['stock_quantity'] ?? '0'); ?>" 
                               placeholder="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo (isset($product['category_id']) && $product['category_id'] == $category['category_id']) || 
                                         (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Image Upload Section (for future implementation) -->
                <div class="form-group">
                    <label>Product Images (Coming Soon)</label>
                    <div class="image-upload" onclick="document.getElementById('imageInput').click()">
                        <div style="font-size: 48px;">📷</div>
                        <p>Click to upload product images</p>
                        <small>Max size: 5MB • Supported: JPG, PNG, GIF</small>
                    </div>
                    <input type="file" id="imageInput" accept="image/*" multiple style="display: none;">
                    <div class="image-preview" id="imagePreview"></div>
                </div>
                
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn-submit">
                            <?php echo $is_edit ? 'Update Product' : 'Add Product'; ?>
                        </button>
                        <a href="products.php" class="btn-cancel">Cancel</a>
                    </div>
                    
                    <?php if ($is_edit && $product): ?>
                        <div>
                            <small>Last updated: <?php echo date('M d, Y H:i', strtotime($product['updated_at'])); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Image upload preview (for future implementation)
    document.getElementById('imageInput').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        preview.style.display = 'block';
        
        const files = e.target.files;
        for (let i = 0; i < Math.min(files.length, 3); i++) {
            const file = files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                preview.appendChild(img);
            }
            
            reader.readAsDataURL(file);
        }
    });
    
    // Price formatting
    document.getElementById('price').addEventListener('blur', function() {
        let value = parseFloat(this.value);
        if (!isNaN(value)) {
            this.value = value.toFixed(2);
        }
    });
    </script>
</body>
</html>