<?php
// support/ticket_close.php - Mark ticket as closed
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../app/config/database.php';
$db = db();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'support') {
    header('Location: ../../login.php');
    exit();
}

$ticket_id = $_GET['id'] ?? 0;

if ($ticket_id) {
    // Update ticket status to closed
    $update = $db->prepare("UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE ticket_id = ?");
    $update->execute([$ticket_id]);
    
    // Add closure note
    $note = "Ticket marked as closed by " . $_SESSION['username'];
    $insert = $db->prepare("INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal, created_at) VALUES (?, ?, ?, 1, NOW())");
    $insert->execute([$ticket_id, $_SESSION['user_id'], $note]);
}

header('Location: tickets.php');
exit();
?>
