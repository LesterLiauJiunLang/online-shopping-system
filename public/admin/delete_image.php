<?php
// delete_image.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../lib/base.php';

$db = db();

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image_id = $_POST['image_id'] ?? 0;
    $product_id = $_POST['product_id'] ?? 0;
    
    // Validate inputs
    if (!$image_id || !$product_id) {
        echo "Invalid request parameters";
        exit();
    }
    
    try {
        // Get the file path first
        $stmt = $db->prepare("SELECT file_path FROM product_image WHERE image_id = ? AND product_id = ?");
        $stmt->execute([$image_id, $product_id]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Delete the physical file
            $file_path = __DIR__ . '/../../' . $image['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete the database record
            $stmt = $db->prepare("DELETE FROM product_image WHERE image_id = ? AND product_id = ?");
            $stmt->execute([$image_id, $product_id]);
            
            echo "Image deleted successfully";
        } else {
            echo "Image not found";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request method";
}
?>