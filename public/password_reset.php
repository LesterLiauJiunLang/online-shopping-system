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
        // Store in session
        $_SESSION['reset_notification'] = 'Password reset link has been sent to your email. Please check your inbox.';
        $_SESSION['reset_notification_type'] = 'success';
        // Use JavaScript redirect instead of PHP header redirect
        $use_js_redirect = true;
        $redirect_url = 'password_reset.php';
    } else {
        $_SESSION['reset_notification'] = 'Email not found in our system.';
        $_SESSION['reset_notification_type'] = 'error';
        $use_js_redirect = true;
        $redirect_url = 'password_reset.php';
    }
}

// Process password reset with token
if (is_post() && post('token')) {
    $token = post('token');
    $new_password = post('new_password');
    $confirm_password = post('confirm_password');
    
    // Validate password match
    if ($new_password !== $confirm_password) {
        $_SESSION['reset_notification'] = 'Passwords do not match.';
        $_SESSION['reset_notification_type'] = 'error';
        $use_js_redirect = true;
        $redirect_url = 'password_reset.php?token=' . urlencode($token);
    }
    
    // Validate password strength using the function from base.php
    $password_errors = validate_password_strength($new_password);
    
    if (!empty($password_errors)) {
        // Format error message with password requirements
        $error_message = "Password does not meet requirements:<br>";
        $error_message .= "<ul style='text-align: left; margin: 10px 20px; padding-left: 20px; font-size: 0.9em;'>";
        foreach ($password_errors as $error) {
            $error_message .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $error_message .= "</ul>";
        
        $_SESSION['reset_notification'] = $error_message;
        $_SESSION['reset_notification_type'] = 'error';
        $use_js_redirect = true;
        $redirect_url = 'password_reset.php?token=' . urlencode($token);
    } else {
        $result = reset_password($token, $new_password);
        
        if ($result === "success") {
            $_SESSION['reset_notification'] = 'Password reset successful! You can now login with your new password.';
            $_SESSION['reset_notification_type'] = 'success';
            $use_js_redirect = true;
            $redirect_url = 'password_reset.php';
        } else {
            $_SESSION['reset_notification'] = $result;
            $_SESSION['reset_notification_type'] = 'error';
            $use_js_redirect = true;
            $redirect_url = 'password_reset.php?token=' . urlencode($token);
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
        /* Your existing CSS styles here */
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
        
        /* Password requirements styling */
        .password-requirements {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .password-requirements h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }
        
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 13px;
        }
        
        .requirements-list li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .requirements-list li i {
            margin-right: 8px;
            font-size: 12px;
        }
        
        .requirement-valid {
            color: #28a745;
        }
        
        .requirement-invalid {
            color: #6c757d;
        }
        
        .password-strength-meter {
            height: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .strength-text {
            font-size: 12px;
            margin-top: 3px;
            text-align: right;
            color: #6c757d;
        }
        
        .password-input-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 16px;
        }
        
        /* Remove browser's default password strength indicators */
        input[type="password"]::-webkit-meter-bar,
        input[type="password"]::-webkit-meter-even-less-good-value,
        input[type="password"]::-webkit-meter-optimum-value,
        input[type="password"]::-webkit-meter-suboptimum-value {
            display: none !important;
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
            <form method="POST" action="" class="auth-form" id="resetForm">
                <input type="hidden" name="token" value="<?php echo encode(get('token')); ?>">
                
                <!-- Password Requirements Info -->
                <div class="password-requirements">
                    <h4><i class="fas fa-shield-alt"></i> Password Requirements:</h4>
                    <ul class="requirements-list" id="requirementsList">
                        <li id="req-length" class="requirement-invalid">
                            <i class="fas fa-circle"></i> At least 8 characters
                        </li>
                        <li id="req-uppercase" class="requirement-invalid">
                            <i class="fas fa-circle"></i> At least one uppercase letter (A-Z)
                        </li>
                        <li id="req-lowercase" class="requirement-invalid">
                            <i class="fas fa-circle"></i> At least one lowercase letter (a-z)
                        </li>
                        <li id="req-number" class="requirement-invalid">
                            <i class="fas fa-circle"></i> At least one number (0-9)
                        </li>
                        <li id="req-special" class="requirement-invalid">
                            <i class="fas fa-circle"></i> At least one special character (!@#$%^&*()-_=+{};:,<.>)
                        </li>
                    </ul>
                    <div class="password-strength-meter">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Password strength: Very Weak</div>
                </div>
                
                <div class="auth-form-group">
                    <label for="new_password" class="auth-label">New Password:</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="new_password" name="new_password" required 
                               class="auth-input" oninput="checkPasswordStrength()">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="auth-form-group">
                    <label for="confirm_password" class="auth-label">Confirm New Password:</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               class="auth-input" oninput="checkPasswordMatch()">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchMessage" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <button type="submit" class="auth-btn" id="submitBtn">
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
        
        <?php if (isset($use_js_redirect) && $use_js_redirect): ?>
            // JavaScript redirect to prevent header issues
            setTimeout(function() {
                window.location.href = '<?php echo $redirect_url; ?>';
            }, 100);
        <?php endif; ?>
        
        // Form submission loading state
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                // Check password strength if it's the reset form
                if (this.id === 'resetForm') {
                    const password = document.getElementById('new_password').value;
                    if (!validatePassword(password)) {
                        e.preventDefault();
                        showNotification('Please ensure your password meets all requirements.', 'error', 5000);
                        return;
                    }
                    
                    const confirmPassword = document.getElementById('confirm_password').value;
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        showNotification('Passwords do not match.', 'error', 5000);
                        return;
                    }
                }
                
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
        
        // Initialize password check
        if (document.getElementById('new_password')) {
            checkPasswordStrength();
            checkPasswordMatch();
        }
    });
    
    // Password strength validation function
    function validatePassword(password) {
        const minLength = password.length >= 8;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumbers = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
        
        return minLength && hasUpperCase && hasLowerCase && hasNumbers && hasSpecial;
    }
    
    // Check password strength in real-time
    function checkPasswordStrength() {
        const password = document.getElementById('new_password').value;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
        };
        
        // Update requirement indicators
        updateRequirement('req-length', requirements.length);
        updateRequirement('req-uppercase', requirements.uppercase);
        updateRequirement('req-lowercase', requirements.lowercase);
        updateRequirement('req-number', requirements.number);
        updateRequirement('req-special', requirements.special);
        
        // Calculate strength score
        let score = 0;
        let total = 0;
        
        for (const key in requirements) {
            total++;
            if (requirements[key]) score++;
        }
        
        // Calculate strength percentage and update meter
        const percentage = (score / total) * 100;
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');
        
        // Update strength meter color and width
        let strengthLabel = '';
        let color = '';
        
        if (percentage <= 20) {
            strengthLabel = 'Very Weak';
            color = '#dc3545';
        } else if (percentage <= 40) {
            strengthLabel = 'Weak';
            color = '#ffc107';
        } else if (percentage <= 60) {
            strengthLabel = 'Fair';
            color = '#fd7e14';
        } else if (percentage <= 80) {
            strengthLabel = 'Good';
            color = '#28a745';
        } else {
            strengthLabel = 'Strong';
            color = '#20c997';
        }
        
        strengthFill.style.width = percentage + '%';
        strengthFill.style.backgroundColor = color;
        strengthText.textContent = 'Password strength: ' + strengthLabel;
        strengthText.style.color = color;
        
        // Enable/disable submit button based on password validity
        const isValid = validatePassword(password);
        if (submitBtn) {
            submitBtn.disabled = !isValid;
        }
    }
    
    // Update requirement indicator
    function updateRequirement(elementId, isValid) {
        const element = document.getElementById(elementId);
        if (element) {
            if (isValid) {
                element.classList.remove('requirement-invalid');
                element.classList.add('requirement-valid');
                element.querySelector('i').className = 'fas fa-check-circle';
            } else {
                element.classList.remove('requirement-valid');
                element.classList.add('requirement-invalid');
                element.querySelector('i').className = 'fas fa-circle';
            }
        }
    }
    
    // Check if passwords match
    function checkPasswordMatch() {
        const password = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const messageElement = document.getElementById('passwordMatchMessage');
        
        if (!password || !confirmPassword) {
            messageElement.textContent = '';
            messageElement.style.color = '';
            return;
        }
        
        if (password === confirmPassword) {
            messageElement.textContent = '✓ Passwords match';
            messageElement.style.color = '#28a745';
        } else {
            messageElement.textContent = '✗ Passwords do not match';
            messageElement.style.color = '#dc3545';
        }
    }
    
    // Toggle password visibility
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
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