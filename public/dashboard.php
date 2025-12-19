<?php
// dashboard.php - Router file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check user role and redirect to appropriate dashboard
require_once __DIR__ . '/../app/config/database.php';
$db = Database::getConnection();

try {
    $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($user['role'] === 'admin') {
            header("Location: adminDashboard.php");
        } else {
            header("Location: memberDashboard.php");
        }
        exit();
    }
} catch (Exception $e) {
    // If error, default to member dashboard
    header("Location: memberDashboard.php");
    exit();
}
?>