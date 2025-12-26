<?php
// support/get_faq.php - AJAX endpoint to get FAQ data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is support
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'support') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['faq_id'])) {
    echo json_encode(['success' => false, 'message' => 'FAQ ID required']);
    exit();
}

// Include database connection
require_once __DIR__ . '/../../app/config/database.php';

$db = db();
$faq_id = intval($_GET['faq_id']);

try {
    $stmt = $db->prepare("
        SELECT f.*, u.username as created_by_name 
        FROM faqs f 
        LEFT JOIN users u ON f.created_by = u.user_id 
        WHERE f.faq_id = ?
    ");
    $stmt->execute([$faq_id]);
    $faq = $stmt->fetch();
    
    if ($faq) {
        echo json_encode(['success' => true, 'data' => $faq]);
    } else {
        echo json_encode(['success' => false, 'message' => 'FAQ not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

