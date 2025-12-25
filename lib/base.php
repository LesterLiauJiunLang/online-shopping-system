<?php
// ============================================================================
// PHP Setups
// ============================================================================
date_default_timezone_set('Asia/Kuala_Lumpur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// Database Connection
// ============================================================================
$_db = new PDO("mysql:host=localhost;dbname=OnlineShoppingSystem", "root", "", [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ============================================================================
// General Page Functions
// ============================================================================
function is_get() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}

function is_post() {
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

function post($key, $value = null) {
    $value = $_POST[$key] ?? $value;
    
    // Handle null before trim
    if ($value === null) {
        return $value;
    }
    
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

function get($key, $value = null) {
    $value = $_GET[$key] ?? $value;
    
    // Handle null before trim
    if ($value === null) {
        return $value;
    }
    
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

function req($key, $value = null) {
    $value = $_REQUEST[$key] ?? $value;
    
    // Handle null before trim
    if ($value === null) {
        return $value;
    }
    
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

function redirect($url, $permanent = false) {
    // If headers have already been sent, use JavaScript redirect
    if (headers_sent()) {
        echo "<script>window.location.href = '$url';</script>";
        exit();
    }
    
    // Otherwise use PHP header redirect
    if ($permanent) {
        header('HTTP/1.1 301 Moved Permanently');
    }
    header("Location: $url");
    exit();
}

function temp($key, $value = null) {
    if ($value !== null) {
        $_SESSION["temp_$key"] = $value;
    }
    else {
        $value = $_SESSION["temp_$key"] ?? null;
        unset($_SESSION["temp_$key"]);
        return $value;
    }
}

// ============================================================================
// CSRF TOKEN
// ============================================================================
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Password strength validation
function validate_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*()-_=+{};:,<.>).";
    }
    
    return $errors;
}

// ============================================================================
// Path and URL Functions
// ============================================================================
function root($path = '') {
    return $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
}

function base($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host . '/' . ltrim($path, '/');
}

// ============================================================================
// Email Functions
// ============================================================================
function get_mail() {
    require_once __DIR__ . '/PHPMailer.php';
    require_once __DIR__ . '/SMTP.php';
    require_once __DIR__ . '/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->Username = 'AACS3173@gmail.com';
    $mail->Password = 'xxna ftdu plga hzxl';
    $mail->CharSet = 'utf-8';
    $mail->setFrom($mail->Username, 'Tech Gadgets Shop');

    return $mail;
}

function is_email($value) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

// ============================================================================
// File Upload Helper
// ============================================================================
function get_file($key) {
    if (isset($_FILES[$key]) && $_FILES[$key]['error'] != UPLOAD_ERR_NO_FILE) {
        return $_FILES[$key];
    }
    return null;
}

// ============================================================================
// HTML Helpers
// ============================================================================
function encode($string) {
    if ($string === null) {
        return '';
    }
    return htmlentities($string, ENT_QUOTES, 'UTF-8');
}

function html_text($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='text' id='$key' name='$key' value='$value' $attr>";
}

function html_search($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='search' id='$key' name='$key' value='$value' $attr>";
}

function html_password($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='password' id='$key' name='$key' value='$value' $attr>";
}

function html_radios($key, $items, $br = false) {
    $value = encode($GLOBALS[$key] ?? '');
    echo '<div>';
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'checked' : '';
        echo "<label><input type='radio' id='{$key}_$id' name='$key' value='$id' $state>$text</label>";
        if ($br) {
            echo '<br>';
        }
    }
    echo '</div>';
}

function html_select($key, $items, $default = '- Select One -', $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<select id='$key' name='$key' $attr>";
    if ($default !== null) {
        echo "<option value=''>$default</option>";
    }
    foreach ($items as $id => $text) {
        $state = $id == $value ? 'selected' : '';
        echo "<option value='$id' $state>$text</option>";
    }
    echo '</select>';
}

function html_file($key, $accept = '', $attr = '') {
    echo "<input type='file' id='$key' name='$key' accept='$accept' $attr>";
}

// Generate table headers <th> (From _base.php)
function table_headers($fields, $sort, $dir, $href = '') {
    foreach ($fields as $k => $v) {
        $d = 'asc'; // Default direction
        $c = '';    // Default class
        
        // TODO: Implement sorting logic
        
        echo "<th><a href='?sort=$k&dir=$d&$href' class='$c'>$v</a></th>";
    }
}

// ============================================================================
// Error Handlings
// ============================================================================
$_err = [];

function err($key) {
    global $_err;
    if ($_err[$key] ?? false) {
        echo "<span class='err'>$_err[$key]</span>";
    }
    else {
        echo '<span></span>';
    }
}

// ============================================================================
// Notification Functions
// ============================================================================
function save_notification_and_redirect($message, $type = 'error', $url = null) {
    show_notification($message, $type);
    
    // Force session write before redirect
    session_write_close();
    
    redirect($url);
}

// ============================================================================
// Database Functions
// ============================================================================
function is_unique($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}

function is_exists($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}

// ============================================================================
// Product Helper Functions (From _base.php)
// ============================================================================

/**
 * Get all categories for dropdown
 */
function get_all_categories($db = null) {
    global $_db;
    $db = $db ?? $_db;
    $stmt = $db->query("SELECT category_id, name FROM category ORDER BY name");
    $categories = $stmt->fetchAll();
    
    $category_options = [];
    foreach ($categories as $cat) {
        $category_options[$cat['category_id']] = $cat['name'];
    }
    
    return $category_options;
}

/**
 * Get filtered products based on search criteria
 */
function get_filtered_products($db = null, $name = '', $category_id = '') {
    global $_db;
    $db = $db ?? $_db;
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN category c ON p.category_id = c.category_id 
            WHERE 1=1";
    $params = [];

    // Add name search filter if provided
    if (!empty($name)) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%$name%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Add category filter if provided
    if (!empty($category_id) && $category_id != 'All') {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
    }

    // Price filter
    if (!empty($min_price)) {
        $sql .= " AND p.price >= ?";
        $params[] = $min_price;
    }
    if (!empty($max_price)) {
        $sql .= " AND p.price <= ?";
        $params[] = $max_price;
    }

    // Add ordering
    $sql .= " ORDER BY p.created_at DESC";

    // Prepare and execute query
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Function to get price range (add this to your database functions)
function get_price_range($db) {
    $stmt = $db->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Format price with RM currency
 */
function format_price($price) {
    return 'RM ' . number_format($price, 2);
}

/**
 * Get stock status class
 */
function get_stock_class($quantity) {
    if ($quantity <= 0) {
        return 'out-of-stock';
    } elseif ($quantity <= 10) {
        return 'low-stock';
    }
    return 'in-stock';
}

/**
 * Get stock display text
 */
function get_stock_text($quantity) {
    if ($quantity <= 0) {
        return 'Out of Stock';
    } elseif ($quantity <= 10) {
        return "Low Stock: $quantity left";
    }
    return "In Stock: $quantity units";
}

/**
 * Get product image URL (with fallback)
 */
function get_product_image($image) {
    return !empty($image) ? $image : 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e';
}

// ============================================================================
// Shopping Cart Functions (From _base.php)
// ============================================================================
function get_cart() {
    return $_SESSION['cart'] ?? [];
}

function set_cart($cart = []) {
    $_SESSION['cart'] = $cart;
}

function update_cart($id, $unit) {
    $cart = get_cart();

    if ($unit <= 0) {
        unset($cart[$id]);
    } else {
        $cart[$id] = $unit;
        ksort($cart); // sort by product id
    }

    set_cart($cart);
}

// ============================================================================
// Additional Helper Functions
// ============================================================================
function displaySuccess($message) {
    if (!empty($message)) {
        return '<div class="success-message">' . encode($message) . '</div>';
    }
    return '';
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function format_date($date, $format = 'Y-m-d H:i') {
    if (empty($date) || $date == '0000-00-00 00:00:00') {
        return 'Never';
    }
    return date($format, strtotime($date));
}

function db() {
    global $_db;
    return $_db;
}

function getDatabase() {
    return db();
}

/**
 * Remove a query parameter from current URL
 */
function remove_query_param($param) {
    $query = $_GET;
    unset($query[$param]);
    $query['page'] = 1; // Reset to page 1
    return 'users.php?' . http_build_query($query);
}

/**
 * Build query string with existing parameters
 */
function build_query($new_params = []) {
    $query = array_merge($_GET, $new_params);
    return http_build_query($query);
}

// ============================================================================
// Image Upload and Processing Functions
// ============================================================================
function is_valid_image($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check MIME type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return false;
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    
    // Check if it's actually an image
    $image_info = @getimagesize($file['tmp_name']);
    if (!$image_info) {
        return false;
    }
    
    return true;
}

function save_uploaded_image($file, $target_path, $max_width = 300, $max_height = 300, $quality = 90) {
    if (!is_valid_image($file)) {
        return false;
    }
    
    // Create directory if it doesn't exist
    $dir = dirname($target_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Try to use SimpleImage class if available
    if (class_exists('SimpleImage')) {
        try {
            $img = new SimpleImage();
            $img->fromFile($file['tmp_name'])
                ->thumbnail($max_width, $max_height, 'center')
                ->toFile($target_path, 'image/jpeg', ['quality' => $quality]);
            return true;
        } catch (Exception $e) {
            error_log("SimpleImage error: " . $e->getMessage());
            // Fall through to GD
        }
    }
    
    // Fallback to GD
    $image_info = getimagesize($file['tmp_name']);
    $original_image = null;
    
    switch($image_info[2]) {
        case IMAGETYPE_JPEG:
            $original_image = imagecreatefromjpeg($file['tmp_name']);
            break;
        case IMAGETYPE_PNG:
            $original_image = imagecreatefrompng($file['tmp_name']);
            // Preserve transparency for PNG
            imagepalettetotruecolor($original_image);
            imagealphablending($original_image, true);
            imagesavealpha($original_image, true);
            break;
        case IMAGETYPE_GIF:
            $original_image = imagecreatefromgif($file['tmp_name']);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $original_image = imagecreatefromwebp($file['tmp_name']);
            }
            break;
    }
    
    if (!$original_image) {
        return false;
    }
    
    // Get original dimensions
    $orig_width = imagesx($original_image);
    $orig_height = imagesy($original_image);
    
    // Calculate new dimensions - FIXED: Use explicit type casting
    $ratio = $orig_width / $orig_height;
    
    if ($max_width / $max_height > $ratio) {
        $new_width = intval($max_height * $ratio);  // Explicitly cast to int
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = intval($max_width / $ratio);  // Explicitly cast to int
    }
    
    // Ensure minimum dimensions of 1px
    $new_width = max(1, $new_width);
    $new_height = max(1, $new_height);
    
    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($image_info[2] == IMAGETYPE_PNG || $image_info[2] == IMAGETYPE_GIF) {
        imagecolortransparent($new_image, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }
    
    // Resize image
    imagecopyresampled($new_image, $original_image, 0, 0, 0, 0, 
                      $new_width, $new_height, $orig_width, $orig_height);
    
    // Save image
    $extension = pathinfo($target_path, PATHINFO_EXTENSION);
    $result = false;
    
    switch(strtolower($extension)) {
        case 'jpg':
        case 'jpeg':
            $result = imagejpeg($new_image, $target_path, $quality);
            break;
        case 'png':
            $result = imagepng($new_image, $target_path, 9);
            break;
        case 'gif':
            $result = imagegif($new_image, $target_path);
            break;
        case 'webp':
            if (function_exists('imagewebp')) {
                $result = imagewebp($new_image, $target_path, $quality);
            } else {
                $result = imagejpeg($new_image, $target_path, $quality);
            }
            break;
        default:
            $result = imagejpeg($new_image, $target_path, $quality);
    }
    
    // Free memory
    imagedestroy($original_image);
    imagedestroy($new_image);
    
    return $result;
}

function generate_profile_filename($user_id, $original_name = '') {
    $extension = 'jpg'; // Default to jpg for consistency
    if ($original_name) {
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = strtolower($ext) == 'jpeg' ? 'jpg' : strtolower($ext);
        }
    }
    
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    return "profile_{$user_id}_{$timestamp}_{$random}.{$extension}";
}

function delete_old_photo($filename, $directory) {
    if (!$filename || $filename == 'avatar.png') {
        return;
    }
    
    $path = rtrim($directory, '/') . '/' . $filename;
    if (file_exists($path)) {
        unlink($path);
    }
}
?>