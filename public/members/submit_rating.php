<?php
// members/submit_rating.php - CLEAN WORKING VERSION

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header immediately
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized. Please log in again.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method. Use POST.'
    ]);
    exit();
}

try {
    // Include database
    require_once __DIR__ . '/../../app/config/database.php';
    $db = db();
    
    // Get and validate input
    $ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    
    if ($ticket_id <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid ticket ID'
        ]);
        exit();
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode([
            'success' => false, 
            'message' => 'Rating must be between 1 and 5'
        ]);
        exit();
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    // 1. Check if ticket exists and belongs to user
    $stmt = $db->prepare("
        SELECT st.* 
        FROM support_tickets st
        WHERE st.ticket_id = ? 
        AND st.user_id = ?
        AND st.status = 'resolved'
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        echo json_encode([
            'success' => false, 
            'message' => 'Ticket not found, not resolved, or not yours'
        ]);
        exit();
    }
    
    // 2. Check if already rated
    $stmt = $db->prepare("
        SELECT rating_id 
        FROM ticket_ratings 
        WHERE ticket_id = ? AND user_id = ?
    ");
    $stmt->execute([$ticket_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'You have already rated this ticket'
        ]);
        exit();
    }
    
    // 3. Insert rating
    $stmt = $db->prepare("
        INSERT INTO ticket_ratings 
        (ticket_id, user_id, rating, feedback, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([$ticket_id, $user_id, $rating, $feedback]);
    
    if (!$result) {
        $error = $stmt->errorInfo();
        throw new Exception("Database error: " . $error[2]);
    }
    
    $rating_id = $db->lastInsertId();
    
    // 4. Mark notification as read
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? 
        AND type = 'ticket_resolved' 
        AND related_id = ?
    ");
    $stmt->execute([$user_id, $ticket_id]);
    
    // 5. Notify support agent
    if (!empty($ticket['assigned_to'])) {
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $agent_id = (int)$ticket['assigned_to'];
        
        // Get agent's name for the message
        $stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->execute([$agent_id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        $agent_name = $agent ? $agent['username'] : 'Support Agent';
        
        $message = "Customer rated ticket #{$ticket_id} with {$stars} ({$rating}/5)";
        if (!empty($feedback)) {
            $message .= "\nFeedback: " . substr($feedback, 0, 100) . (strlen($feedback) > 100 ? '...' : '');
        }
        
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, related_id, created_at)
            VALUES (?, 'ticket_rated', 'Ticket Rated', ?, ?, NOW())
        ");
        $stmt->execute([$agent_id, $message, $ticket_id]);
    }
    
    // 6. Return success
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your rating!',
        'redirect' => '../notifications.php',
        'rating_id' => $rating_id
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred.',
        'error' => $e->getMessage()
    ]);
    error_log("PDO Error in submit_rating.php: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.',
        'error' => $e->getMessage()
    ]);
    error_log("General Error in submit_rating.php: " . $e->getMessage());
}
?>
