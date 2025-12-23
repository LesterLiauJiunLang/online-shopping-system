<?php

date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// ============================================================================
// General Page Functions
// ============================================================================

// Is GET request?
function is_get() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}

// Is POST request?
function is_post() {
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

// Obtain GET parameter
function get($key, $value = null) {
    $value = $_GET[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain POST parameter
function post($key, $value = null) {
    $value = $_POST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Obtain REQUEST (GET and POST) parameter
function req($key, $value = null) {
    $value = $_REQUEST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim($value);
}

// Redirect to URL
function redirect($url = null) {
    $url ??= $_SERVER['REQUEST_URI'];
    header("Location: $url");
    exit();
}

// Set or get temporary session variable
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

// Encode HTML special characters
function encode($value) {
    return htmlentities($value ?? '');
}

// Generate <input type='text'>
function html_text($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='text' id='$key' name='$key' value='$value' $attr>";
}

// Generate <input type='search'>
function html_search($key, $attr = '') {
    $value = encode($GLOBALS[$key] ?? '');
    echo "<input type='search' id='$key' name='$key' value='$value' $attr>";
}

// Generate <input type='radio'> list
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

// Generate <select>
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

// Generate table headers <th>
function table_headers($fields, $sort, $dir, $href = '') {
    foreach ($fields as $k => $v) {
        $d = 'asc'; // Default direction
        $c = '';    // Default class
        
        // TODO

        echo "<th><a href='?sort=$k&dir=$d&$href' class='$c'>$v</a></th>";
    }
}

// Helper Functions (PRODUCTS)

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
function get_filtered_products($db, $name = '', $category_id = '') {
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

    // Add ordering
    $sql .= " ORDER BY p.created_at DESC";

    // Prepare and execute query
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
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
// Error Handlings
// ============================================================================

// Global error array
$_err = [];

// Generate <span class='err'>
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
// Database Setups and Functions
// ============================================================================

// Global PDO object
$_db = new PDO('mysql:dbname=db5', 'root', '', [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
]);

// Is unique?
function is_unique($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() == 0;
}

// Is exists?
function is_exists($value, $table, $field) {
    global $_db;
    $stm = $_db->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
    $stm->execute([$value]);
    return $stm->fetchColumn() > 0;
}

// ============================================================================
// Global Constants and Variables
// ============================================================================



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
?>