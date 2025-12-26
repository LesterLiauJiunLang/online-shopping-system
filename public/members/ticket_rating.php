<?php
// Update session start to be more robust
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true);
}

// Only accessible to members
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    echo "Access denied.";
    exit();
}

// Get correct base path
$base_path = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($base_path == '/') {
    $base_path = '';
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../lib/base.php';
$db = db();
$user_id = $_SESSION['user_id'];

// Check if ticket exists and belongs to user
$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

if ($ticket_id <= 0) {
    echo "Invalid ticket.";
    exit();
}

// Verify ticket ownership and status
$stmt = $db->prepare("
    SELECT st.ticket_id, st.subject, st.status, st.assigned_to, 
           u.username as agent_name
    FROM support_tickets st
    LEFT JOIN users u ON st.assigned_to = u.user_id
    WHERE st.ticket_id = ? 
    AND st.user_id = ?
    AND st.status = 'resolved'
");
$stmt->execute([$ticket_id, $user_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo "Ticket not found, not resolved, or doesn't belong to you.";
    exit();
}

// Check if already rated
$stmt = $db->prepare("SELECT rating_id FROM ticket_ratings WHERE ticket_id = ? AND user_id = ?");
$stmt->execute([$ticket_id, $user_id]);

if ($stmt->rowCount() > 0) {
    echo "You have already rated this ticket.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Support Service - Ticket #<?php echo $ticket_id; ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .rating-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .ticket-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid #667eea;
        }
        
        .ticket-info p {
            margin: 5px 0;
            color: #555;
        }
        
        .rating-section {
            margin: 30px 0;
        }
        
        .stars {
            font-size: 3.5rem;
            margin: 20px 0;
            cursor: pointer;
            display: inline-block;
        }
        
        .star {
            color: #ddd;
            transition: all 0.2s;
            margin: 0 5px;
        }
        
        .star:hover,
        .star.hover {
            color: #FFD700;
            transform: scale(1.1);
        }
        
        .star.selected {
            color: #FFD700;
        }
        
        .rating-text {
            font-size: 1.2rem;
            color: #666;
            margin-top: 10px;
            min-height: 28px;
        }
        
        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            resize: vertical;
            transition: border-color 0.3s;
        }
        
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 140px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #5a4bff, #3a2ee0);
            color: white;
            box-shadow: 0 4px 15px rgba(90, 75, 255, 0.2);
        }
        
        .btn-submit:hover {
            background: linear-gradient(135deg, #4a3bff, #2a1ed0);
            box-shadow: 0 6px 20px rgba(90, 75, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-cancel {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #dee2e6;
        }
        
        .btn-cancel:hover {
            background: #e9ecef;
            border-color: #ccc;
        }
        
        .error-message {
            color: #e74c3c;
            margin-top: 10px;
            display: none;
        }
        
        .success-message {
            color: #28a745;
            margin-top: 10px;
            display: none;
        }
        
        .agent-name {
            color: #5a4bff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="rating-container">
        <a href="../../notifications.php" class="back-link">← Back to Notifications</a>
        
        <h1>Rate Support Service</h1>
        <p style="color: #666; margin-bottom: 20px;">
            Please rate the support you received for this ticket
        </p>
        
        <div class="ticket-info">
            <p><strong>Ticket #<?php echo $ticket_id; ?></strong></p>
            <p><strong>Subject:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
            <?php if (!empty($ticket['agent_name'])): ?>
                <p><strong>Support Agent:</strong> <span class="agent-name"><?php echo htmlspecialchars($ticket['agent_name']); ?></span></p>
            <?php endif; ?>
        </div>
        
        <div class="rating-section">
            <div class="stars" id="starsContainer">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star" data-value="<?php echo $i; ?>">☆</span>
                <?php endfor; ?>
            </div>
            <div class="rating-text" id="ratingText">Click stars to rate</div>
            <input type="hidden" id="ratingValue" value="0">
        </div>
        
        <form id="ratingForm">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
            
            <div class="form-group">
                <label for="feedback">Your Feedback (Optional):</label>
                <textarea id="feedback" name="feedback" rows="4" 
                          placeholder="Tell us about your experience with our support service..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" id="cancelBtn" class="btn btn-cancel">Cancel</button>
                <button type="submit" id="submitBtn" class="btn btn-submit" disabled>Submit Rating</button>
            </div>
            
            <div id="errorMessage" class="error-message"></div>
            <div id="successMessage" class="success-message"></div>
        </form>
    </div>

    <script>
        const stars = document.querySelectorAll('.star');
        const ratingValue = document.getElementById('ratingValue');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('submitBtn');
        const ratingForm = document.getElementById('ratingForm');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');
        const cancelBtn = document.getElementById('cancelBtn');
        
        const ratingMessages = [
            "Click stars to rate",
            "Poor - Very dissatisfied",
            "Fair - Could be better",
            "Good - Satisfied",
            "Very Good - Happy with service",
            "Excellent - Exceeded expectations"
        ];
        
        // Star rating functionality
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const value = parseInt(this.getAttribute('data-value'));
                ratingValue.value = value;
                
                // Update stars display
                stars.forEach((s, index) => {
                    if (index < value) {
                        s.classList.add('selected');
                        s.classList.remove('hover');
                    } else {
                        s.classList.remove('selected', 'hover');
                    }
                });
                
                // Update rating text
                ratingText.textContent = ratingMessages[value];
                
                // Enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = `Submit ${value} Star Rating`;
                
                // Clear errors
                errorMessage.style.display = 'none';
            });
            
            star.addEventListener('mouseover', function() {
                const value = parseInt(this.getAttribute('data-value'));
                
                stars.forEach((s, index) => {
                    if (index < value) {
                        s.classList.add('hover');
                    } else {
                        s.classList.remove('hover');
                    }
                });
                
                ratingText.textContent = ratingMessages[value];
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = parseInt(ratingValue.value) || 0;
                
                stars.forEach((s, index) => {
                    s.classList.remove('hover');
                    if (index < currentRating) {
                        s.classList.add('selected');
                    }
                });
                
                ratingText.textContent = currentRating ? ratingMessages[currentRating] : ratingMessages[0];
            });
        });
        
        // Form submission
        ratingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const rating = ratingValue.value;
            const ticketId = document.querySelector('input[name="ticket_id"]').value;
            const feedback = document.getElementById('feedback').value;
            
            if (!rating || rating < 1 || rating > 5) {
                showError('Please select a rating');
                return;
            }
            
            // Disable submit button during submission
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('ticket_id', ticketId);
            formData.append('rating', rating);
            formData.append('feedback', feedback);
            formData.append('action', 'submit_rating');
            
            console.log('Submitting rating...');
            console.log('Ticket ID:', ticketId);
            console.log('Rating:', rating);
            
            // Submit via AJAX - Use absolute path
            const currentPath = window.location.pathname;
            const pathParts = currentPath.split('/');
            const basePath = pathParts.slice(0, -2).join('/'); // Remove /members/ticket_rating.php
            const submitUrl = basePath + '/members/submit_rating.php';
            
            console.log('Submitting to:', submitUrl);
            
            fetch(submitUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.success) {
                    // Show success message
                    successMessage.textContent = data.message || 'Thank you for your rating!';
                    successMessage.style.display = 'block';
                    errorMessage.style.display = 'none';
                    
                    // Update button
                    submitBtn.textContent = '✓ Rating Submitted';
                    submitBtn.style.background = '#28a745';
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = data.redirect || '../../notifications.php';
                    }, 2000);
                } else {
                    showError(data.message || 'Failed to submit rating');
                    submitBtn.disabled = false;
                    submitBtn.textContent = `Submit ${rating} Star Rating`;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showError('Network error. Please check console for details and try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = `Submit ${rating} Star Rating`;
            });
        });
        
        // Cancel button
        cancelBtn.addEventListener('click', function() {
            if (confirm('Are you sure? Your rating will not be saved.')) {
                window.location.href = '../../notifications.php';
            }
        });
        
        // Show error message
        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            successMessage.style.display = 'none';
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = '../../notifications.php';
            }
            
            // Number keys 1-5 for rating
            if (e.key >= '1' && e.key <= '5') {
                const value = parseInt(e.key);
                stars[value - 1].click();
            }
        });
    </script>
</body>
</html>

