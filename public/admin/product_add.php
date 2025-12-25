
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

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
require_once __DIR__ . '/../../lib/base.php';

$db = db();

// Define base_url if not already defined
if (!isset($base_url)) {
    $base_url = '';
}

// Check if editing existing product
$product = null;
$is_edit = false;
$product_image = null;

if (isset($_GET['edit'])) {
    $product_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    $is_edit = true;
    
    // Get existing product image
    if ($product) {
        $stmt = $db->prepare("SELECT * FROM product_image WHERE product_id = ? LIMIT 1");
        $stmt->execute([$product_id]);
        $product_image = $stmt->fetch();
    }
}

// Get categories for dropdown
$stmt = $db->prepare("SELECT * FROM category ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

$error = '';
$success = '';

// Handle image upload function (SINGLE IMAGE ONLY)
function handleImageUpload($product_id) {
    global $db, $error;
    
    // Check if file was uploaded
    if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] == UPLOAD_ERR_NO_FILE) {
        return true; // No file to upload
    }
    
    $file = $_FILES['product_image'];
    
    // Use the validation function from base.php
    if (!is_valid_image($file)) {
        $error = "Invalid image file. Please upload a valid image (JPG, PNG, GIF, WebP) under 2MB.";
        return false;
    }
    
    // Define upload directory relative to root
    $upload_dir = root('ProductImage/');
    
    // Generate filename using the helper function
    $filename = "product_{$product_id}_" . time() . "_" . bin2hex(random_bytes(4));
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $target_filename = $filename . '.' . strtolower($extension);
    $target_path = $upload_dir . $target_filename;
    
    // Save uploaded image with resizing
    if (save_uploaded_image($file, $target_path)) {
        // Store relative path in database
        $relative_path = 'ProductImage/' . $target_filename;
        
        // Check if product already has an image
        $stmt = $db->prepare("SELECT * FROM product_image WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $existing_image = $stmt->fetch();
        
        if ($existing_image) {
            // Delete old physical file using helper
            delete_old_photo($existing_image['file_path'], $upload_dir);
            
            // Update existing record
            $stmt = $db->prepare("UPDATE product_image SET file_path = ? WHERE product_id = ?");
            $stmt->execute([$relative_path, $product_id]);
        } else {
            // Insert new record
            $stmt = $db->prepare("INSERT INTO product_image (product_id, file_path, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$product_id, $relative_path, 0]);
        }
        
        return true;
    } else {
        $error = "Failed to save uploaded image.";
        return false;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $stock_quantity = trim($_POST['stock_quantity']);
    $category_id = $_POST['category_id'] ?: null;
    
    // Check if we're updating (product_id in POST or edit mode)
    $is_update = false;
    $update_product_id = null;
    
    if (isset($_POST['product_id'])) {
        $update_product_id = $_POST['product_id'];
        $is_update = true;
    } elseif ($is_edit && $product) {
        $update_product_id = $product['product_id'];
        $is_update = true;
    }
    
    // Validation
    if (empty($name) || empty($price)) {
        $error = "Product name and price are required.";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Price must be a positive number.";
    } elseif (!is_numeric($stock_quantity) || $stock_quantity < 0) {
        $error = "Stock quantity must be a non-negative number.";
    } else {
        try {
            if ($is_update && $update_product_id) {
                // Update existing product
                $stmt = $db->prepare("
                    UPDATE products SET 
                    name = ?, description = ?, price = ?, stock_quantity = ?, category_id = ?
                    WHERE product_id = ?
                ");
                $stmt->execute([$name, $description, $price, $stock_quantity, $category_id, $update_product_id]);
                
                // Handle image upload if any
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != UPLOAD_ERR_NO_FILE) {
                    handleImageUpload($update_product_id);
                }
                
                $success = "Product updated successfully!";
                
                // Refresh product data after update
                $stmt = $db->prepare("SELECT * FROM products WHERE product_id = ?");
                $stmt->execute([$update_product_id]);
                $product = $stmt->fetch();
                
                // Refresh product image
                $stmt = $db->prepare("SELECT * FROM product_image WHERE product_id = ? LIMIT 1");
                $stmt->execute([$update_product_id]);
                $product_image = $stmt->fetch();
                
            } else {
                // Insert new product
                $stmt = $db->prepare("
                    INSERT INTO products (name, description, price, stock_quantity, category_id) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $price, $stock_quantity, $category_id]);
                
                $new_product_id = $db->lastInsertId();
                $success = "Product added successfully!";
                
                // Handle image upload if any
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != UPLOAD_ERR_NO_FILE) {
                    handleImageUpload($new_product_id);
                }
                
                // Redirect to edit mode for the new product
                header("Location: product_add.php?edit=" . $new_product_id);
                exit();
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
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="products.php">📋 Products</a>
            <a href="product_add.php" class="active">➕ Add Product</a>
            <a href="orders.php">📦 Orders</a>
            <a href="categories.php">🏷️ Categories</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="product-form-container">
            <form method="POST" action="" enctype="multipart/form-data">
                <?php if ($is_edit && $product): ?>
                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                <?php endif; ?>
                
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
                    <div class="category-management">
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
                </div>
                
                <!-- Image Upload Section (SINGLE IMAGE ONLY) -->
                 <!-- Image Upload Section (SIMPLE SINGLE IMAGE) -->
<div class="form-group">
    <label>Product Image</label>
    <p class="help-text">Upload a single product image (Max 2MB. Supported: JPG, PNG, GIF, WebP)</p>
    
    <!-- Simple file input with preview -->
    <div class="simple-image-upload">
        <!-- Hidden file input -->
        <input type="file" id="product_image" name="product_image" 
               accept="image/jpeg, image/jpg, image/png, image/gif, image/webp" 
               style="display: none;" 
               onchange="previewImage(this)">
        
        <!-- Upload button -->
        <button type="button" class="btn-upload" onclick="document.getElementById('product_image').click()">
            <i class="fas fa-cloud-upload-alt"></i> Choose Image
        </button>
        
        <!-- Image preview area -->
        <div class="image-preview-container" id="imagePreviewContainer" style="margin-top: 10px; display: none;">
            <div class="preview-wrapper">
                <img id="imagePreview" src="" alt="Preview" style="max-width: 200px; max-height: 200px;">
                <button type="button" class="btn-remove" onclick="removeImage()" title="Remove image">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="fileName" class="file-name" style="margin-top: 5px; font-size: 12px; color: #666;"></div>
        </div>
        
        <!-- Display existing image if editing -->
        <?php if ($is_edit && $product_image): 
            $image_path = htmlspecialchars($product_image['file_path']);
            $full_path = __DIR__ . '/../../' . htmlspecialchars($product_image['file_path']);
            $image_exists = file_exists($full_path);
        ?>
            <div class="existing-image" style="margin-top: 15px;">
                <p><strong>Current Image:</strong></p>
                <div id="existingImageContainer">
                    <?php if ($image_exists): ?>
                        <div class="existing-image-item" style="position: relative; display: inline-block;">
                            <img src="<?php echo $image_path; ?>" 
                                 alt="Product Image"
                                 style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                            <div class="image-actions" style="margin-top: 5px;">
                                <button type="button" class="btn-delete-small" 
                                        onclick="deleteProductImage(<?php echo $product_image['image_id']; ?>, <?php echo $product['product_id']; ?>)" 
                                        title="Delete this image">
                                    <i class="fas fa-trash"></i> Delete Image
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="image-placeholder" style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
                            <span style="color: #dc3545;">
                                <i class="fas fa-exclamation-triangle"></i> Image file not found
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Status message -->
    <div id="uploadStatus" class="help-text" style="margin-top: 5px;"></div>
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
// Simple image preview function
function previewImage(input) {
    const previewContainer = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
    const fileName = document.getElementById('fileName');
    const status = document.getElementById('uploadStatus');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            status.innerHTML = '<span style="color: #dc3545;">File too large. Maximum size is 2MB.</span>';
            input.value = '';
            return;
        }
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            status.innerHTML = '<span style="color: #dc3545;">Invalid file type. Please upload an image.</span>';
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
            fileName.textContent = file.name;
            status.innerHTML = '<span style="color: #28a745;">Image ready for upload.</span>';
        }
        
        reader.readAsDataURL(file);
    }
}

// Remove selected image
function removeImage() {
    const input = document.getElementById('product_image');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const status = document.getElementById('uploadStatus');
    
    input.value = '';
    previewContainer.style.display = 'none';
    status.innerHTML = '';
}

// Delete existing product image (AJAX)
function deleteProductImage(imageId, productId) {
    if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('image_id', imageId);
    formData.append('product_id', productId);
    
    fetch('delete_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('successfully') || data.includes('deleted')) {
            // Remove the image from UI
            const existingContainer = document.getElementById('existingImageContainer');
            if (existingContainer) {
                existingContainer.innerHTML = '<p style="color: #666;">No image uploaded for this product.</p>';
            }
            showMessage('Image deleted successfully!', 'success');
        } else {
            showMessage('Error: ' + data, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error deleting image: ' + error.message, 'error');
    });
}

// Simple message display
function showMessage(text, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `global-message message-${type}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 4px;
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
        background-color: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
        border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
        z-index: 9999;
        max-width: 300px;
    `;
    messageDiv.textContent = text;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const imageInput = document.getElementById('product_image');
    
    form.addEventListener('submit', function(e) {
        const file = imageInput.files[0];
        
        // Validate image if selected
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                e.preventDefault();
                showMessage('Image file is too large. Maximum size is 2MB.', 'error');
                return;
            }
        }
        
        // Prevent double submission
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<?php echo $is_edit ? 'Updating...' : 'Adding...'; ?>';
    });
});
</script>

</body>
</html>