<?php
// ========== SESSION START AT THE VERY TOP ==========
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========== REQUIRE FILES ==========
require_once __DIR__ . '/../lib/base.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/captcha.php';

// ========== REDIRECT IF LOGGED IN ==========
if (is_logged_in()) {
    redirect('dashboard.php');
    exit();
}


// ========== AJAX ENDPOINT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_register'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $captcha_response = $_POST['g-recaptcha-response'] ?? '';
    
    $response = [
        'success' => false,
        'message' => '',
        'errors' => [],
        'redirect' => null,
        'debug' => ''
    ];
    
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $response['message'] = "Security token invalid.";
        $response['debug'] = 'CSRF failed';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // CAPTCHA validation
    if (empty($captcha_response)) {
        $response['message'] = "Please complete the CAPTCHA verification.";
        $response['debug'] = 'CAPTCHA empty';
    } elseif (!Captcha::verify($captcha_response)) {
        $response['message'] = "CAPTCHA verification failed.";
        $response['debug'] = 'CAPTCHA verify failed';
    } else {
        // Basic validation
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $response['message'] = "All fields are required.";
            $response['debug'] = 'Empty fields';
        } elseif ($password !== $confirm_password) {
            $response['errors']['confirm_password'] = "Passwords do not match.";
            $response['debug'] = 'Password mismatch';
        } else {
            // Enhanced password strength validation
            $password_errors = validate_password_strength($password);
            
            if (!empty($password_errors)) {
                $response['message'] = implode("<br>", $password_errors);
                $response['debug'] = 'Password strength failed';
            } else {
                // Call your existing register_user function
                $result = register_user($username, $email, $password);
                
                // DEBUG: Log what register_user returns
                $response['debug'] = 'register_user returned: ' . var_export($result, true);
                $response['debug'] .= ' | Type: ' . gettype($result);
                
                // Check for any success indicator
                $is_success = false;
                
                // Check multiple possible success returns
                if ($result === "success") {
                    $is_success = true;
                } elseif ($result === true) {
                    $is_success = true;
                } elseif (is_numeric($result) && $result > 0) {
                    $is_success = true;
                } elseif (is_string($result) && (stripos($result, 'success') !== false || stripos($result, 'registered') !== false)) {
                    $is_success = true;
                }
                
                if ($is_success) {
                    $response['success'] = true;
                    $response['message'] = "Registration successful! Please login with your credentials.";
                    $response['redirect'] = 'login.php';
                    $_SESSION['show_verification_reminder'] = true;
                } else {
                    $response['message'] = $result ?: "Registration failed. Please try again.";
                    $response['debug'] .= ' | Not recognized as success';
                }
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
// ===================================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Online Shopping System</title>
    
    <!-- External CSS Files -->
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    
    <style>
        /* Small inline styles for password requirement colors */
        .req-valid { color: #28a745 !important; }
        .req-invalid { color: #dc3545 !important; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="auth-header">
        <h1>Online Shopping System</h1>
        <p>Create Your Account</p>
    </div>
    
    <!-- Registration Form -->
    <div class="auth-container">
        <h2 class="auth-title">Register</h2>
        
        <!-- Error Message Container -->
        <div id="error-message" class="auth-message error" style="display: none;"></div>
        
        <form id="register-form" method="POST">
            <div class="auth-form">
                <div class="auth-form-group">
                    <label for="username" class="auth-label">Username:</label>
                    <input type="text" id="username" name="username" class="auth-input" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    <div class="auth-error" id="error-username"></div>
                </div>
                
                <div class="auth-form-group">
                    <label for="email" class="auth-label">Email:</label>
                    <input type="email" id="email" name="email" class="auth-input" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    <div class="auth-error" id="error-email"></div>
                </div>
                
                <div class="auth-form-group">
                    <label for="password" class="auth-label">Password:</label>
                    <input type="password" id="password" name="password" class="auth-input" required>
                    <div class="auth-error" id="error-password"></div>
                </div>
                
                <div class="auth-form-group">
                    <label for="confirm_password" class="auth-label">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="auth-input" required>
                    <div class="auth-error" id="error-confirm_password"></div>
                </div>
            </div>
            
            <!-- Password Requirements -->
            <div class="password-requirements">
                <strong>Password must contain:</strong>
                <ul>
                    <li id="req-length">✓ At least 8 characters</li>
                    <li id="req-upper">✓ One uppercase letter (A-Z)</li>
                    <li id="req-lower">✓ One lowercase letter (a-z)</li>
                    <li id="req-number">✓ One number (0-9)</li>
                    <li id="req-special">✓ One special character (!@#$%^&*()\-_=+{};:,<.>)</li>
                </ul>
            </div>
            
            <!-- Password Strength Meter -->
            <div id="password-strength" class="password-strength"></div>
            
            <!-- CAPTCHA -->
            <div class="captcha-container">
                <p class="captcha-label">Please verify you're not a robot:</p>
                <?php echo Captcha::render(); ?>
            </div>
            
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            
            <!-- Submit Button -->
            <button type="submit" id="register-btn" class="auth-btn">
                Register
            </button>
        </form>
        
        <!-- Links -->
        <div class="auth-links">
            <p>Already have an account? <a href="login.php" class="auth-link">Login here</a></p>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="auth-footer">
        <p>&copy; <?php echo date('Y'); ?> Online Shopping System. All rights reserved.</p>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../assets/js/notifications.js"></script>
    
    <!-- Registration JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Registration page loaded');
        
        // Password strength checker
        function checkPasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
            };
            
            // Update requirement indicators
            const reqIds = ['length', 'upper', 'lower', 'number', 'special'];
            reqIds.forEach(id => {
                const element = document.getElementById('req-' + id);
                if (element) {
                    element.className = requirements[id] ? 'req-valid' : 'req-invalid';
                }
            });
            
            // Update strength meter
            const strengthMeter = document.getElementById('password-strength');
            const metCount = Object.values(requirements).filter(Boolean).length;
            
            // Clear all classes
            strengthMeter.className = 'password-strength';
            
            if (metCount === 0) {
                strengthMeter.textContent = 'Password strength: Very Weak';
                strengthMeter.classList.add('strength-very-weak');
            } else if (metCount <= 2) {
                strengthMeter.textContent = 'Password strength: Weak';
                strengthMeter.classList.add('strength-weak');
            } else if (metCount <= 4) {
                strengthMeter.textContent = 'Password strength: Good';
                strengthMeter.classList.add('strength-good');
            } else {
                strengthMeter.textContent = 'Password strength: Strong';
                strengthMeter.classList.add('strength-strong');
            }
        }
        
        // Attach to password input
        document.getElementById('password').addEventListener('input', function(e) {
            checkPasswordStrength(e.target.value);
        });
        
        // Initialize password strength if password already has value
        const passwordInput = document.getElementById('password');
        if (passwordInput.value) {
            checkPasswordStrength(passwordInput.value);
        }
        
        // AJAX Registration
        document.getElementById('register-form').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            
            // Clear previous errors
            document.getElementById('error-message').style.display = 'none';
            document.querySelectorAll('.auth-error').forEach(el => el.textContent = '');
            
            // Get form data
            const formData = new FormData(this);
            const captchaResponse = grecaptcha?.getResponse();
            
            if (!captchaResponse) {
                showToast('Please complete the CAPTCHA verification.', 'error');
                return;
            }
            
            formData.append('g-recaptcha-response', captchaResponse);
            formData.append('ajax_register', '1');
            
            // Show loading state
            const submitBtn = document.getElementById('register-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = '';
            submitBtn.classList.add('auth-btn-loading');
            submitBtn.disabled = true;
            
            // Send AJAX request
            fetch('register.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Response received');
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Registration response:', data);
                
                // Reset button
                submitBtn.textContent = originalText;
                submitBtn.classList.remove('auth-btn-loading');
                submitBtn.disabled = false;
                
                if (data.success) {
                    // Show success toast
                    showToast(data.message || 'Registration successful!', 'success');
                    
                    // Clear form
                    document.getElementById('register-form').reset();
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.reset();
                    }
                    
                    // Redirect after delay
                    setTimeout(() => {
                        window.location.href = data.redirect || 'login.php';
                    }, 2000);
                } else {
                    // Show error with debug info
                    const errorMsg = data.message || 'Registration failed. Please try again.';
                    showToast(errorMsg, 'error');
                    
                    // Show error in message div
                    const errorDiv = document.getElementById('error-message');
                    errorDiv.textContent = errorMsg;
                    errorDiv.style.display = 'block';
                    
                    // Show field errors
                    if (data.errors) {
                        for (const field in data.errors) {
                            const errorEl = document.getElementById('error-' + field);
                            if (errorEl) {
                                errorEl.textContent = data.errors[field];
                            }
                        }
                    }
                    
                    // Log debug info to console
                    if (data.debug) {
                        console.log('Debug info:', data.debug);
                    }
                }
            })
            .catch(error => {
                // Reset button
                submitBtn.textContent = originalText;
                submitBtn.classList.remove('auth-btn-loading');
                submitBtn.disabled = false;
                
                console.error('Registration fetch error:', error);
                showToast('Registration failed. Please check your connection and try again.', 'error');
            });
        });
    });
    </script>
</body>
</html>