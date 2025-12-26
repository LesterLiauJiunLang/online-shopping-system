<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ============================================================================
// PHP Setups
// ============================================================================
date_default_timezone_set('Asia/Kuala_Lumpur');
//session_start();

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
// Path and URL Functions (CRITICAL FOR PASSWORD RESET)
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
// Email Functions (FOR PASSWORD RESET)
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

// Generate <input type='search'>
function html_search($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='search' id='$key' name='$key' value='$value' $attr>";
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
// Your Additional Helpers 
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

// ============================================================================
// Database Helper Functions
// ============================================================================
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
// Drag and Drop 
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
    
    // Calculate new dimensions
    $ratio = $orig_width / $orig_height;
    if ($max_width / $max_height > $ratio) {
        $new_width = $max_height * $ratio;
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    }
    
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

/**
 * Get all categories for dropdown
 */
function get_all_categories($db) {
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
function get_filtered_products($db, $name = '', $category_id = '', $min_price = null, $max_price = null) {
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN category c ON p.category_id = c.category_id 
            WHERE 1=1";
    $params = [];

    // Name filter
    if (!empty($name)) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%$name%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Category filter
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

    $sql .= " ORDER BY p.created_at DESC";

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
// Shopping Cart
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
// NOTIFICATION HELPERS
// ============================================================================

function createNotification($user_id, $type, $title, $message, $related_id = null) {
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $db = db();
        
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $type, $title, $message, $related_id]);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

function notifyAllAdmins($type, $title, $message, $related_id = null) {
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $db = db();
        
        // Get all admin users
        $stmt = $db->prepare("SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            createNotification($admin['user_id'], $type, $title, $message, $related_id);
        }
        
        return count($admins);
    } catch (Exception $e) {
        error_log("Admin notification failed: " . $e->getMessage());
        return false;
    }
}

function notifyAllSupportAgents($type, $title, $message, $related_id = null) {
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $db = db();
        
        // Get all support agents
        $stmt = $db->prepare("SELECT user_id FROM users WHERE role = 'support' AND is_active = 1");
        $stmt->execute();
        $agents = $stmt->fetchAll();
        
        foreach ($agents as $agent) {
            createNotification($agent['user_id'], $type, $title, $message, $related_id);
        }
        
        return count($agents);
    } catch (Exception $e) {
        error_log("Support agent notification failed: " . $e->getMessage());
        return false;
    }
}

function notifyOrderStatusUpdate($order_id, $new_status, $user_id = null) {
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $db = db();
        
        // If user_id not provided, get it from order
        if (!$user_id) {
            $stmt = $db->prepare("SELECT user_id FROM orders WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            $user_id = $order['user_id'];
        }
        
        $status_messages = [
            'Pending' => 'Your order is now being processed.',
            'Processing' => 'Your order is now being processed.',
            'Shipped' => 'Your order has been shipped!',
            'Completed' => 'Your order has been delivered successfully.',
            'Cancelled' => 'Your order has been cancelled.'
        ];
        
        $message = isset($status_messages[$new_status]) 
            ? $status_messages[$new_status] 
            : "Your order status has been updated to: {$new_status}";
        
        // Notify the member
        createNotification(
            $user_id,
            'order_update',
            'Order Status Updated',
            "Order #{$order_id}: {$message}",
            $order_id
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Order status notification failed: " . $e->getMessage());
        return false;
    }
}

function notifySupportReply($ticket_id, $sender_id, $message) {
    try {
        require_once __DIR__ . '/../app/config/database.php';
        $db = db();
        
        // Get ticket info
        $stmt = $db->prepare("SELECT user_id, subject, assigned_to FROM support_tickets WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            // Notify the ticket creator (member)
            if ($ticket['user_id'] != $sender_id) {
                createNotification(
                    $ticket['user_id'],
                    'support_reply',
                    'New Support Reply',
                    "Support agent replied to your ticket: \"{$message}\"",
                    $ticket_id
                );
            }
            
            // Notify admin about support activity
            notifyAllAdmins(
                'support_activity',
                'Support Activity',
                "New reply on ticket #{$ticket_id}: \"{$message}\"",
                $ticket_id
            );
            
            // Notify assigned support agent if different from sender
            if ($ticket['assigned_to'] && $ticket['assigned_to'] != $sender_id) {
                createNotification(
                    $ticket['assigned_to'],
                    'ticket_update',
                    'Ticket Update',
                    "New activity on ticket #{$ticket_id}",
                    $ticket_id
                );
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Support reply notification failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// SUPPORT FUNCTIONS
// ============================================================================

// Support Management Functions
function get_support_agents($limit = 10, $offset = 0) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT * FROM users 
            WHERE role = 'support' 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get support agents error: " . $e->getMessage());
        return [];
    }
}

function get_support_agents_count() {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT COUNT(*) as count FROM users WHERE role = 'support'
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get support agents count error: " . $e->getMessage());
        return 0;
    }
}

function search_support_agents($search, $limit = 10, $offset = 0) {
    global $_db;
    try {
        $searchTerm = "%$search%";
        $stmt = $_db->prepare("
            SELECT * FROM users 
            WHERE role = 'support' 
            AND (username LIKE ? 
                OR email LIKE ? 
                OR employee_id LIKE ?)
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Search support agents error: " . $e->getMessage());
        return [];
    }
}

function update_user_status($userId, $status) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            UPDATE users 
            SET is_active = ?, updated_at = NOW() 
            WHERE user_id = ?
        ");
        return $stmt->execute([$status, $userId]);
    } catch (Exception $e) {
        error_log("Update user status error: " . $e->getMessage());
        return false;
    }
}

function count_active_support_agents() {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE role = 'support' AND is_active = 1
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Count active support agents error: " . $e->getMessage());
        return 0;
    }
}

function get_user_activities($userId, $limit = 10) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT * FROM user_activity 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get user activities error: " . $e->getMessage());
        return [];
    }
}

function get_support_agent_stats($agentId) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets
            FROM support_tickets 
            WHERE assigned_to = ?
        ");
        $stmt->execute([$agentId]);
        $result = $stmt->fetch();
        
        // Ensure all values are set
        return [
            'total_tickets' => $result['total_tickets'] ?? 0,
            'open_tickets' => $result['open_tickets'] ?? 0,
            'resolved_tickets' => $result['resolved_tickets'] ?? 0,
            'closed_tickets' => $result['closed_tickets'] ?? 0
        ];
    } catch (Exception $e) {
        error_log("Get support agent stats error: " . $e->getMessage());
        return [
            'total_tickets' => 0,
            'open_tickets' => 0,
            'resolved_tickets' => 0,
            'closed_tickets' => 0
        ];
    }
}

function get_total_assigned_tickets() {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT COUNT(*) as count 
            FROM support_tickets 
            WHERE assigned_to IS NOT NULL
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get total assigned tickets error: " . $e->getMessage());
        return 0;
    }
}

function get_open_tickets_count() {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT COUNT(*) as count 
            FROM support_tickets 
            WHERE status IN ('open', 'in_progress')
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get open tickets count error: " . $e->getMessage());
        return 0;
    }
}

function get_total_resolved_tickets() {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT COUNT(*) as count 
            FROM support_tickets 
            WHERE status = 'resolved'
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get total resolved tickets error: " . $e->getMessage());
        return 0;
    }
}

function get_agent_recent_tickets($agentId, $limit = 5) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT st.*, u.username as customer_name 
            FROM support_tickets st
            JOIN users u ON st.user_id = u.user_id
            WHERE st.assigned_to = ?
            ORDER BY st.updated_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $agentId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get agent recent tickets error: " . $e->getMessage());
        return [];
    }
}

function get_user_by_id($userId) {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by ID error: " . $e->getMessage());
        return null;
    }
}

function get_user_by_email($email) {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by email error: " . $e->getMessage());
        return null;
    }
}

function get_user_by_username($username) {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by username error: " . $e->getMessage());
        return null;
    }
}

function get_total_customers_count() {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'member'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get total customers count error: " . $e->getMessage());
        return 0;
    }
}

function get_total_orders_count() {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT COUNT(*) as count FROM orders");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get total orders count error: " . $e->getMessage());
        return 0;
    }
}

function get_total_revenue() {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status IN ('Completed', 'Shipped', 'Processing')");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Get total revenue error: " . $e->getMessage());
        return 0;
    }
}

function get_total_products_count() {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT COUNT(*) as count FROM products");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get total products count error: " . $e->getMessage());
        return 0;
    }
}

function get_recent_orders($limit = 10) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT o.*, u.username as customer_name 
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            ORDER BY o.order_date DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get recent orders error: " . $e->getMessage());
        return [];
    }
}

function get_low_stock_products($threshold = 10) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT * FROM products 
            WHERE stock_quantity <= ? 
            AND stock_quantity > 0
            ORDER BY stock_quantity ASC 
            LIMIT 10
        ");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get low stock products error: " . $e->getMessage());
        return [];
    }
}

/*
function log_user_activity($userId, $activityType, $description, $ipAddress = null) {
    global $_db;
    try {
        $ipAddress = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $_db->prepare("
            INSERT INTO user_activity (user_id, activity_type, description, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $activityType, $description, $ipAddress]);
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
        return false;
    }
}
*/

// ============================================================================
// Helpers 
// ============================================================================

function get_pagination_data($totalItems, $itemsPerPage, $currentPage) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'start_item' => $offset + 1,
        'end_item' => min($offset + $itemsPerPage, $totalItems),
        'total_items' => $totalItems
    ];
}

function generate_pagination_links($currentPage, $totalPages, $baseUrl = '', $search = '') {
    $queryParams = [];
    if ($search) {
        $queryParams['search'] = $search;
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $queryParams['page'] = $currentPage - 1;
        $url = $baseUrl . '?' . http_build_query($queryParams);
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $queryParams['page'] = $i;
        $url = $baseUrl . '?' . http_build_query($queryParams);
        $active = $i == $currentPage ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $queryParams['page'] = $currentPage + 1;
        $url = $baseUrl . '?' . http_build_query($queryParams);
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">Next &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

function get_user_status_badge($isActive) {
    if ($isActive) {
        return '<span class="badge bg-success">Active</span>';
    } else {
        return '<span class="badge bg-danger">Inactive</span>';
    }
}

function get_profile_image_html($profilePhoto, $username, $size = 32) {
    if (!empty($profilePhoto)) {
        return '<img src="../' . encode($profilePhoto) . '" 
                   alt="' . encode($username) . '" 
                   class="rounded-circle me-2" 
                   width="' . $size . '" 
                   height="' . $size . '">';
    } else {
        return '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                    style="width: ' . $size . 'px; height: ' . $size . 'px;">
                    <i class="fas fa-user text-white"></i>
                </div>';
    }
}

function format_last_login($lastLogin) {
    if ($lastLogin) {
        return date('M d, Y', strtotime($lastLogin)) . 
               '<small class="text-muted d-block">' . 
               date('h:i A', strtotime($lastLogin)) . '</small>';
    } else {
        return '<span class="text-muted">Never</span>';
    }
}

function get_activity_type_badge($activityType) {
    $badgeClasses = [
        'profile_update' => 'bg-primary',
        'create_support' => 'bg-success',
        'ticket_update' => 'bg-warning',
        'login' => 'bg-info',
        'password_reset' => 'bg-secondary',
        'activate_support' => 'bg-success',
        'deactivate_support' => 'bg-danger'
    ];
    
    $class = $badgeClasses[$activityType] ?? 'bg-secondary';
    return '<span class="badge ' . $class . '">' . encode($activityType) . '</span>';
}

function get_user_activities_paginated($userId, $limit = 10, $offset = 0) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT * FROM user_activity 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get user activities error: " . $e->getMessage());
        return [];
    }
}

function count_user_activities($userId) {
    global $_db;
    try {
        $stmt = $_db->prepare("SELECT COUNT(*) as count FROM user_activity WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Count user activities error: " . $e->getMessage());
        return 0;
    }
}

// ============================================================================
// support helpers 
// ============================================================================

function get_support_agents_count_filtered($search = '', $status = 'all') {
    global $_db;
    
    $where_conditions = ["role = 'support'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ? OR employee_id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($status !== 'all') {
        if ($status === 'active') {
            $where_conditions[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where_conditions[] = "is_active = 0";
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    $query = "SELECT COUNT(*) FROM users WHERE $where_clause";
    
    try {
        $stmt = $_db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database error in get_support_agents_count_filtered: " . $e->getMessage());
        return 0;
    }
}

function get_support_agents_filtered($limit = 20, $offset = 0, $search = '', $status = 'all', $sort = 'newest') {
    global $_db;
    
    $where_conditions = ["role = 'support'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ? OR employee_id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($status !== 'all') {
        if ($status === 'active') {
            $where_conditions[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where_conditions[] = "is_active = 0";
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Build ORDER BY clause
    $order_by = 'created_at DESC'; // default
    switch ($sort) {
        case 'oldest':
            $order_by = 'created_at ASC';
            break;
        case 'name_asc':
            $order_by = 'username ASC';
            break;
        case 'name_desc':
            $order_by = 'username DESC';
            break;
        case 'last_login':
            $order_by = 'last_login DESC';
            break;
        case 'newest':
        default:
            $order_by = 'created_at DESC';
            break;
    }
    
    $query = "SELECT * FROM users 
              WHERE $where_clause 
              ORDER BY $order_by 
              LIMIT ? OFFSET ?";
    
    try {
        $stmt = $_db->prepare($query);
        
        // Bind search and filter parameters
        $param_index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($param_index, $param, PDO::PARAM_STR);
            $param_index++;
        }
        
        // Bind limit and offset
        $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($param_index, $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in get_support_agents_filtered: " . $e->getMessage());
        return [];
    }
}

function build_query_global($new_params = []) {
    $query = array_merge($_GET, $new_params);
    // Remove empty values
    $query = array_filter($query, function($value) {
        return $value !== '' && $value !== null;
    });
    return http_build_query($query);
}

function remove_query_param_global($param) {
    $query = $_GET;
    unset($query[$param]);
    // Reset page to 1 when removing filters
    if (isset($query['page'])) {
        $query['page'] = 1;
    }
    $query = array_filter($query, function($value) {
        return $value !== '' && $value !== null;
    });
    return '?' . http_build_query($query);
}

function count_inactive_support_agents_filtered($search = '') {
    global $_db;
    
    $where_conditions = ["role = 'support'", "is_active = 0"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ? OR employee_id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    try {
        $stmt = $_db->prepare("SELECT COUNT(*) as count FROM users WHERE $where_clause");
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Count inactive support agents error: " . $e->getMessage());
        return 0;
    }
}

function get_support_agent_stats_filtered($agentId, $date_from = null, $date_to = null) {
    global $_db;
    
    $where_conditions = ["assigned_to = ?"];
    $params = [$agentId];
    
    if ($date_from) {
        $where_conditions[] = "created_at >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "created_at <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    try {
        $stmt = $_db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets
            FROM support_tickets 
            WHERE $where_clause
        ");
        
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        // Ensure all values are set
        return [
            'total_tickets' => $result['total_tickets'] ?? 0,
            'open_tickets' => $result['open_tickets'] ?? 0,
            'in_progress_tickets' => $result['in_progress_tickets'] ?? 0,
            'resolved_tickets' => $result['resolved_tickets'] ?? 0,
            'closed_tickets' => $result['closed_tickets'] ?? 0
        ];
    } catch (Exception $e) {
        error_log("Get support agent stats error: " . $e->getMessage());
        return [
            'total_tickets' => 0,
            'open_tickets' => 0,
            'in_progress_tickets' => 0,
            'resolved_tickets' => 0,
            'closed_tickets' => 0
        ];
    }
}

function get_agent_monthly_performance($agentId, $months = 6) {
    global $_db;
    
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime("-{$months} months"));
    
    try {
        $stmt = $_db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets
            FROM support_tickets 
            WHERE assigned_to = ? 
                AND created_at >= ? 
                AND created_at <= ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT ?
        ");
        
        $stmt->execute([$agentId, $start_date, $end_date, $months]);
        $results = $stmt->fetchAll();
        
        // Format for easier use
        $performance = [];
        foreach ($results as $row) {
            $performance[$row['month']] = [
                'total' => $row['total_tickets'],
                'resolved' => $row['resolved_tickets'],
                'resolution_rate' => $row['total_tickets'] > 0 
                    ? round(($row['resolved_tickets'] / $row['total_tickets']) * 100, 2)
                    : 0
            ];
        }
        
        return $performance;
    } catch (Exception $e) {
        error_log("Get agent monthly performance error: " . $e->getMessage());
        return [];
    }
}

function get_all_support_agents_for_dropdown($only_active = true) {
    global $_db;
    
    $where_conditions = ["role = 'support'"];
    if ($only_active) {
        $where_conditions[] = "is_active = 1";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    try {
        $stmt = $_db->prepare("
            SELECT user_id, username, employee_id 
            FROM users 
            WHERE $where_clause 
            ORDER BY username ASC
        ");
        $stmt->execute();
        $agents = $stmt->fetchAll();
        
        $dropdown = [];
        foreach ($agents as $agent) {
            $label = $agent['username'];
            if ($agent['employee_id']) {
                $label .= " ({$agent['employee_id']})";
            }
            $dropdown[$agent['user_id']] = $label;
        }
        
        return $dropdown;
    } catch (Exception $e) {
        error_log("Get all support agents for dropdown error: " . $e->getMessage());
        return [];
    }
}

function get_recently_assigned_tickets($limit = 10) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT 
                st.*,
                u_customer.username as customer_name,
                u_agent.username as agent_name,
                u_agent.employee_id as agent_employee_id
            FROM support_tickets st
            JOIN users u_customer ON st.user_id = u_customer.user_id
            LEFT JOIN users u_agent ON st.assigned_to = u_agent.user_id
            WHERE st.assigned_to IS NOT NULL
            ORDER BY st.updated_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get recently assigned tickets error: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// Notification Count
// ============================================================================
function get_unread_chat_notifications_count($user_id) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? 
            AND type = 'internal_chat' 
            AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get unread chat notifications count error: " . $e->getMessage());
        return 0;
    }
}

function get_total_unread_notifications($user_id) {
    global $_db;
    try {
        $stmt = $_db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? 
            AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get total unread notifications error: " . $e->getMessage());
        return 0;
    }
}

// ============================================================================
// Extra 
// ============================================================================

function form_start($id = '', $method = 'POST', $action = '', $attributes = []) {
    $method = strtoupper($method);
    $action = $action ?: $_SERVER['PHP_SELF'];
    
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        $attr_string .= " $key=\"$value\"";
    }
    
    return "<form id=\"$id\" method=\"$method\" action=\"$action\"$attr_string>";
}

function form_end() {
    return '</form>';
}

function hidden_field($name, $value = '', $attributes = []) {
    $attr_string = '';
    foreach ($attributes as $key => $val) {
        $attr_string .= " $key=\"$val\"";
    }
    
    $value = encode($value);
    return "<input type=\"hidden\" name=\"$name\" value=\"$value\"$attr_string>";
}

function textarea_field($name, $value = '', $attributes = []) {
    $attr_string = '';
    foreach ($attributes as $key => $val) {
        $attr_string .= " $key=\"$val\"";
    }
    
    $value = encode($value);
    return "<textarea name=\"$name\"$attr_string>$value</textarea>";
}

function submit_button($text = 'Submit', $name = 'submit', $attributes = []) {
    $attr_string = '';
    foreach ($attributes as $key => $val) {
        $attr_string .= " $key=\"$val\"";
    }
    
    return "<button type=\"submit\" name=\"$name\"$attr_string>$text</button>";
}

function button($text, $type = 'button', $attributes = []) {
    $attr_string = '';
    foreach ($attributes as $key => $val) {
        $attr_string .= " $key=\"$val\"";
    }
    
    return "<button type=\"$type\"$attr_string>$text</button>";
}

function select_field($name, $options, $selected = '', $attributes = []) {
    $attr_string = '';
    foreach ($attributes as $key => $val) {
        $attr_string .= " $key=\"$val\"";
    }
    
    $html = "<select name=\"$name\"$attr_string>";
    foreach ($options as $value => $label) {
        $is_selected = ($value == $selected) ? ' selected' : '';
        $html .= "<option value=\"$value\"$is_selected>$label</option>";
    }
    $html .= '</select>';
    
    return $html;
}

?>
