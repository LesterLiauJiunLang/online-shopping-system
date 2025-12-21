
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
    
    // Single folder for all product images
    $upload_dir = __DIR__ . '/../../ProductImage/';
    
    // Create folder if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Get file info
    $file_name = $_FILES['product_image']['name'];
    $file_size = $_FILES['product_image']['size'];
    $file_tmp = $_FILES['product_image']['tmp_name'];
    $file_type = $_FILES['product_image']['type'];
    $file_error = $_FILES['product_image']['error'];
    
    // Check for upload errors
    if ($file_error !== UPLOAD_ERR_OK) {
        $error = "Error uploading file: $file_name (Error code: $file_error)";
        return false;
    }
    
    // Check file size (max 5MB)
    if ($file_size > 5 * 1024 * 1024) {
        $error = "File $file_name is too large. Maximum size is 5MB.";
        return false;
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file_type, $allowed_types)) {
        $error = "File $file_name has invalid type. Only JPG, PNG, GIF, and WebP are allowed.";
        return false;
    }
    
    // Generate unique filename
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $safe_name = preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
    $safe_name = substr($safe_name, 0, 50);
    
    // Format: product_[id]_[timestamp]_[random].[ext]
    $new_file_name = sprintf(
        'product_%d_%s_%s.%s',
        $product_id,
        time(),
        bin2hex(random_bytes(4)),
        $file_ext
    );
    
    $destination = $upload_dir . $new_file_name;
    
    // Move uploaded file
    if (move_uploaded_file($file_tmp, $destination)) {
        // Store relative path in database
        $relative_path = 'ProductImage/' . $new_file_name;
        
        // Check if product already has an image
        $stmt = $db->prepare("SELECT * FROM product_image WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $existing_image = $stmt->fetch();
        
        if ($existing_image) {
            // Delete old physical file
            $old_file_path = __DIR__ . '/../../' . $existing_image['file_path'];
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
            
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
        $error = "Failed to upload file: $file_name";
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
                <div class="form-group">
                    <label>Product Image</label>
                    <p class="help-text">Upload a single product image (Max 5MB. Supported: JPG, PNG, GIF, WebP)</p>
                    
                    <!-- Image upload area -->
                    <div class="image-upload-area" id="imageUploadArea">
                        <div class="upload-icon">📷</div>
                        <p><strong>Click to upload</strong> or drag & drop image here</p>
                        <p>Only one image can be uploaded</p>
                        <p><small>Supported formats: JPG, PNG, GIF, WebP</small></p>
                        <p><small>Maximum file size: 5MB</small></p>
                    </div>
                    
                    <input type="file" id="imageInput" name="product_image" 
                           accept="image/jpeg, image/jpg, image/png, image/gif, image/webp" 
                           class="file-input-hidden">
                    
                    <!-- Preview of new image to be uploaded -->
                    <div class="image-preview" id="imagePreview">
                        <!-- Image will appear here -->
                    </div>
                    
                    <!-- Status message -->
                    <div id="uploadStatus" class="help-text"></div>
                    
                    <!-- Display existing image if editing -->
                    <?php if ($is_edit && $product_image): 
                        $image_path = htmlspecialchars($product_image['file_path']);
                        $full_path = __DIR__ . '/../../' . htmlspecialchars($product_image['file_path']);
                        $image_exists = file_exists($full_path);
                    ?>
                        <div class="existing-image">
                            <p><strong>Current Image:</strong></p>
                            <div id="existingImageContainer">
                                <?php if ($image_exists): ?>
                                    <div class="existing-image-item" data-image-id="<?php echo $product_image['image_id']; ?>">
                                        <img src="<?php echo $image_path; ?>" 
                                             alt="Product Image"
                                             loading="lazy"
                                             style="max-width: 200px; max-height: 200px;">
                                        <div class="image-actions">
                                            <button type="button" class="delete-btn" 
                                                    onclick="deleteProductImage(<?php echo $product_image['image_id']; ?>, <?php echo $product['product_id']; ?>)" 
                                                    title="Delete this image">
                                                Delete Image
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="image-placeholder">
                                        <span>⚠️ Image Not Found</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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
    // DOM elements
    const imageUploadArea = document.getElementById('imageUploadArea');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    const form = document.querySelector('form');

    // Image upload area click handler
    imageUploadArea.addEventListener('click', function(e) {
        console.log("Upload area clicked directly");
        e.stopPropagation();
        e.preventDefault();
        
        // Direct click on the file input
        imageInput.click();
    });

    // Also add direct click to file input itself if user clicks on it
    imageInput.addEventListener('click', function(e) {
        e.stopPropagation();
        console.log("File input clicked directly");
    });

    // Handle file selection - SINGLE FILE ONLY
    imageInput.addEventListener('change', function() {
        console.log("File input changed, file selected:", this.files[0]?.name);
        if (this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    // Handle and preview SINGLE file
    function handleFile(file) {
        console.log("handleFile called with file:", {
            name: file.name,
            type: file.type,
            size: file.size,
            lastModified: file.lastModified
        });
        
        // Clear previous preview
        imagePreview.innerHTML = '';
        
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert(`❌ File "${file.name}" is not an image. Please select an image file.`);
            imageInput.value = ''; // Clear the input
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert(`❌ File "${file.name}" is too large. Maximum size is 5MB.`);
            imageInput.value = ''; // Clear the input
            return;
        }
        
        const reader = new FileReader();
        
        reader.onloadstart = function() {
            console.log("Starting to read file:", file.name);
        };
        
        reader.onload = function(e) {
            console.log("✅ File loaded successfully:", file.name);
            
            const div = document.createElement('div');
            div.className = 'image-preview-item';
            div.dataset.filename = file.name;
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = file.name;
            img.style.maxWidth = '200px';
            img.style.maxHeight = '200px';
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '×';
            removeBtn.title = 'Remove image';
            removeBtn.onclick = function() {
                console.log("Removing preview for:", file.name);
                div.remove();
                imageInput.value = ''; // Clear the file input
            };
            
            const fileName = document.createElement('div');
            fileName.style.fontSize = '10px';
            fileName.style.textAlign = 'center';
            fileName.style.padding = '2px';
            fileName.style.background = 'rgba(0,0,0,0.5)';
            fileName.style.color = 'white';
            fileName.textContent = file.name.length > 20 ? file.name.substring(0, 17) + '...' : file.name;
            
            div.appendChild(img);
            div.appendChild(removeBtn);
            div.appendChild(fileName);
            imagePreview.appendChild(div);
            
            // Make preview area visible
            imagePreview.style.display = 'flex';
        };
        
        reader.onerror = function(e) {
            console.error("❌ Error reading file:", file.name, e);
            alert(`❌ Error reading file "${file.name}". Please try again.`);
            imageInput.value = ''; // Clear the input
        };
        
        reader.onloadend = function() {
            console.log("Finished reading file:", file.name);
        };
        
        // Read as Data URL
        reader.readAsDataURL(file);
    }

    // Delete existing product image using AJAX
    function deleteProductImage(imageId, productId) {
        console.log("Deleting image:", imageId, "for product:", productId);
        
        if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
            return;
        }
        
        // Show loading indicator
        const deleteBtn = event.target;
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = 'Deleting...';
        deleteBtn.disabled = true;
        
        // Create form data
        const formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('product_id', productId);
        
        // Send AJAX request
        fetch('../admin/delete_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(data => {
            console.log("Delete response:", data);
            
            if (data.includes('successfully') || data.includes('deleted')) {
                // Remove the image element from DOM
                const imageElement = document.querySelector('.existing-image-item');
                if (imageElement) {
                    imageElement.style.opacity = '0.5';
                    setTimeout(() => {
                        const existingImageContainer = document.getElementById('existingImageContainer');
                        if (existingImageContainer) {
                            existingImageContainer.innerHTML = '<p>No image uploaded for this product.</p>';
                        }
                        
                        // Show success message
                        showMessage('Image deleted successfully!', 'success');
                    }, 300);
                }
            } else {
                throw new Error(data || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error deleting image: ' + error.message, 'error');
            
            // Reset button
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
    }

    // Universal message display function
    function showMessage(text, type = 'info') {
        // Remove existing messages
        const existingMsgs = document.querySelectorAll('.global-message');
        existingMsgs.forEach(msg => msg.remove());
        
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `global-message message-${type}`;
        
        // Add styles inline
        Object.assign(messageDiv.style, {
            position: 'fixed',
            top: '80px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '6px',
            fontWeight: '500',
            zIndex: '9999',
            maxWidth: '350px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            animation: 'slideIn 0.3s ease-out',
            fontSize: '14px',
            display: 'flex',
            alignItems: 'center',
            gap: '10px'
        });
        
        // Set colors based on type
        switch(type) {
            case 'success':
                messageDiv.style.backgroundColor = '#d4edda';
                messageDiv.style.color = '#155724';
                messageDiv.style.borderLeft = '4px solid #28a745';
                break;
            case 'error':
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.borderLeft = '4px solid #dc3545';
                break;
            case 'warning':
                messageDiv.style.backgroundColor = '#fff3cd';
                messageDiv.style.color = '#856404';
                messageDiv.style.borderLeft = '4px solid #ffc107';
                break;
            default:
                messageDiv.style.backgroundColor = '#d1ecf1';
                messageDiv.style.color = '#0c5460';
                messageDiv.style.borderLeft = '4px solid #17a2b8';
        }
        
        // Add icon
        let icon = 'ℹ️';
        switch(type) {
            case 'success': icon = '✅'; break;
            case 'error': icon = '❌'; break;
            case 'warning': icon = '⚠️'; break;
        }
        
        messageDiv.innerHTML = `<span style="font-size: 16px;">${icon}</span> ${text}`;
        
        // Add to body
        document.body.appendChild(messageDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transition = 'opacity 0.5s';
                setTimeout(() => messageDiv.remove(), 500);
            }
        }, 5000);
    }

    // Add CSS animation
    const messageStyle = document.createElement('style');
    messageStyle.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .message-close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            margin-left: auto;
            padding: 0 5px;
        }
    `;
    document.head.appendChild(messageStyle);

    // Drag and drop functionality
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        imageUploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        imageUploadArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        imageUploadArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        console.log("Drag highlight");
        imageUploadArea.classList.add('dragover');
    }

    function unhighlight() {
        console.log("Drag unhighlight");
        imageUploadArea.classList.remove('dragover');
    }

    // Handle dropped files - SINGLE FILE ONLY
    imageUploadArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        console.log("Files dropped");
        const dt = e.dataTransfer;
        const files = dt.files;
        console.log("Dropped files:", files.length);
        
        if (files.length === 0) {
            return;
        }
        
        // Only take the first file if multiple are dropped
        const file = files[0];
        
        // Clear previous selection
        imageInput.value = '';
        
        // Create a new FileList with just the first file
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        imageInput.files = dataTransfer.files;
        
        handleFile(file);
        
        // Inform user if multiple files were dropped
        if (files.length > 1) {
            setTimeout(() => {
                showMessage(`Only the first file (${file.name}) was selected. Multiple images are not supported.`, 'warning');
            }, 500);
        }
    }

    // Initialize
    console.log("Single image upload script loaded");

    // Validate form before submission
    form.addEventListener('submit', function(e) {
        console.log("Form submit event triggered");
        
        // Basic validation
        const name = document.getElementById('name').value.trim();
        const price = document.getElementById('price').value.trim();
        
        if (!name) {
            e.preventDefault();
            alert('Product name is required!');
            document.getElementById('name').focus();
            return;
        }
        
        if (!price || isNaN(price) || parseFloat(price) <= 0) {
            e.preventDefault();
            alert('Please enter a valid price (greater than 0)!');
            document.getElementById('price').focus();
            return;
        }
        
        // Check if file is too large (single file only)
        if (imageInput.files.length > 0) {
            const file = imageInput.files[0];
            if (file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert(`File "${file.name}" is too large. Maximum size is 5MB.`);
                return;
            }
        }
        
        // Disable submit button to prevent double submission
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<?php echo $is_edit ? 'Updating...' : 'Adding...'; ?>';
        
        // Log for debugging
        console.log("Form passed validation, submitting...");
    });
    </script>

</body>
</html>