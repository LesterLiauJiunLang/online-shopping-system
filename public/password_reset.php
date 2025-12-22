<?php
require_once '../includes/header.php';
require_once '../lib/base.php';     
require_once '../lib/auth.php';    

$error = '';
$success = '';

// Process password reset request
if (is_post() && post('email')) {
    $email = post('email');
    
    $token = create_password_reset($email);
    
    if ($token) {
        // Store in session BEFORE redirect
        $_SESSION['reset_notification'] = 'Password reset link has been sent to your email. Please check your inbox.';
        $_SESSION['reset_notification_type'] = 'success';
        // Force session write
        session_write_close();
        redirect('password_reset.php');
        exit();
    } else {
        $_SESSION['reset_notification'] = 'Email not found in our system.';
        $_SESSION['reset_notification_type'] = 'error';
        session_write_close();
        redirect('password_reset.php');
        exit();
    }
}

// Process password reset with token
if (is_post() && post('token')) {
    $token = post('token');
    $new_password = post('new_password');
    $confirm_password = post('confirm_password');
    
    if ($new_password !== $confirm_password) {
        $_SESSION['reset_notification'] = 'Passwords do not match.';
        $_SESSION['reset_notification_type'] = 'error';
        session_write_close();
        redirect('password_reset.php?token=' . urlencode($token));
        exit();
    } elseif (strlen($new_password) < 6) {
        $_SESSION['reset_notification'] = 'Password must be at least 6 characters long.';
        $_SESSION['reset_notification_type'] = 'error';
        session_write_close();
        redirect('password_reset.php?token=' . urlencode($token));
        exit();
    } else {
        $result = reset_password($token, $new_password);
        
        if ($result === "success") {
            $_SESSION['reset_notification'] = 'Password reset successful! You can now login with your new password.';
            $_SESSION['reset_notification_type'] = 'success';
            session_write_close();
            redirect('password_reset.php');
            exit();
        } else {
            $_SESSION['reset_notification'] = $result;
            $_SESSION['reset_notification_type'] = 'error';
            session_write_close();
            redirect('password_reset.php?token=' . urlencode($token));
            exit();
        }
    }
}

// Check for notification messages from session
$notification = isset($_SESSION['reset_notification']) ? $_SESSION['reset_notification'] : '';
$notification_type = isset($_SESSION['reset_notification_type']) ? $_SESSION['reset_notification_type'] : '';

// Clear session notification after reading
unset($_SESSION['reset_notification']);
unset($_SESSION['reset_notification_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Online Shopping System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        /* Minimal additional styles */
        .reset-description {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        
        .back-link {
            margin-top: 20px;
            text-align: center;
        }
        
        .back-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <h2 class="auth-title">Password Reset</h2>
        
        <?php if (!get('token')): ?>
            <!-- Request reset form -->
            <form method="POST" action="" class="auth-form">
                <p class="reset-description">
                    Enter your email address to receive a password reset link.
                </p>
                
                <div class="auth-form-group">
                    <label for="email" class="auth-label">Email:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars(post('email') ?? ''); ?>"
                           class="auth-input">
                </div>
                
                <button type="submit" class="auth-btn">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
        <?php else: ?>
            <!-- Reset password form -->
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="token" value="<?php echo encode(get('token')); ?>">
                
                <div class="auth-form-group">
                    <label for="new_password" class="auth-label">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required 
                           class="auth-input">
                </div>
                
                <div class="auth-form-group">
                    <label for="confirm_password" class="auth-label">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           class="auth-input">
                </div>
                
                <button type="submit" class="auth-btn">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <!-- Include your existing notifications.js -->
    <script src="<?php echo base('assets/js/notifications.js'); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($notification)): ?>
            // Show notification if exists
            setTimeout(function() {
                showNotification('<?php echo addslashes($notification); ?>', '<?php echo $notification_type; ?>', 5000);
            }, 300);
        <?php endif; ?>
        
        // Form submission loading state
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    submitBtn.classList.add('auth-btn-loading');
                    
                    // Re-enable after 5 seconds in case of error
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('auth-btn-loading');
                    }, 5000);
                }
            });
        });
    });
    
    // If your notifications.js doesn't have showNotification function, add this:
    function showNotification(message, type = 'info', duration = 5000) {
        // Check if notification function exists from notifications.js
        if (typeof createNotification === 'function') {
            createNotification(message, type, duration);
        } else if (typeof displayNotification === 'function') {
            displayNotification(message, type, duration);
        } else {
            // Fallback: create simple notification
            const container = document.createElement('div');
            container.className = 'notification-toast';
            container.setAttribute('data-type', type);
            container.innerHTML = `
                <div class="notification-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</div>
                <div class="notification-message">${message}</div>
                <button class="notification-close" onclick="this.parentElement.remove()">×</button>
            `;
            document.body.appendChild(container);
            
            // Auto remove after duration
            setTimeout(() => {
                if (container.parentNode) {
                    container.remove();
                }
            }, duration);
        }
    }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>