<?php
<<<<<<< HEAD
require_once '../includes/header.php';
require_once '../lib/captcha.php'; // ← ADD THIS LINE

$error = '';
$success = '';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
=======
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base path for includes
$base_path = dirname(__DIR__);

// Check if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])) {
    header("Location: dashboard.php");
    exit();
>>>>>>> origin/LoginAndProductPart
}

// Include dependencies
require_once $base_path . '/app/config/config.php';
require_once $base_path . '/app/controllers/AuthController.php';

$error = '';
$success = '';

// Process registration form
<<<<<<< HEAD
if (is_post()) {
    $username = post('username');
    $email = post('email');
    $password = post('password');
    $confirm_password = post('confirm_password');
    
    // ========== CAPTCHA CHECK FIRST ==========
    $captcha_response = $_POST['g-recaptcha-response'] ?? '';
    $captcha_passed = false;
    
    if (empty($captcha_response)) {
        $error = "Please complete the CAPTCHA verification.";
    } elseif (!Captcha::verify($captcha_response)) {
        $error = "CAPTCHA verification failed. Please try again.";
    } else {
        $captcha_passed = true;
    }
    // ========== PROCEED ONLY IF CAPTCHA PASSED ==========
    if ($captcha_passed) {
        // Basic validation
        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
=======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $authController = new AuthController();
        $result = $authController->register($username, $email, $password);
        
        if ($result === "success") {
            $success = "Registration successful! You will be redirected to login page in 3 seconds.";
            
            // Set success message in session for persistence during redirect
            $_SESSION['registration_success'] = true;
            $_SESSION['registration_message'] = "Registration successful! You can now login.";
            
            // JavaScript for showing success message and redirect
            echo '<script>
                setTimeout(function() {
                    window.location.href = "login.php";
                }, 3000);
            </script>';
>>>>>>> origin/LoginAndProductPart
        } else {
            // Enhanced password strength validation
            $password_errors = validate_password_strength($password);
            
            if (!empty($password_errors)) {
                $error = implode("<br>", $password_errors);
            } else {
                $result = register_user($username, $email, $password);

                if ($result === "success" || $result === "success_email_failed") {
                    // Both cases mean registration succeeded
                    if ($result === "success_email_failed") {
                        // Registration succeeded but email failed
                        temp('success', 'Registration successful! However, we couldn\'t send the verification email. Please go to your profile to resend it.');
                    } else {
                        temp('success', 'Registration successful! Please login with your credentials.');
                    }
                    redirect('login.php');
                } else {
                    $error = $result;
                }
            }
        }
    }
}
?>

<<<<<<< HEAD
<div class="form-container">
    <h2>Register</h2>
    
    <?php if ($error): ?>
        <div class="error-message" style="color: red; padding: 10px; margin: 10px 0; border: 1px solid red; background: #ffe6e6;">
            <?php echo encode($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success = temp('success')): ?>
        <div class="success-message" style="color: green; padding: 10px; margin: 10px 0; border: 1px solid green; background: #e6ffe6;">
            <?php echo encode($success); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form">
            <label for="username">Username:</label>
            <?php html_text('username'); ?>
            <?php err('username'); ?>
            
            <label for="email">Email:</label>
            <?php html_text('email', 'type="email"'); ?>
            <?php err('email'); ?>
            
            <label for="password">Password:</label>
            <?php html_password('password'); ?>
            <?php err('password'); ?>
            
            <label for="confirm_password">Confirm Password:</label>
            <?php html_password('confirm_password'); ?>
            <?php err('confirm_password'); ?>
        </div>
        
        <!-- Password Requirements -->
        <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin: 10px 0; font-size: 12px;">
            <strong>Password must contain:</strong>
            <ul style="margin: 5px 0; padding-left: 20px;">
                <li id="req-length">✓ At least 8 characters</li>
                <li id="req-upper">✓ One uppercase letter (A-Z)</li>
                <li id="req-lower">✓ One lowercase letter (a-z)</li>
                <li id="req-number">✓ One number (0-9)</li>
                <li id="req-special">✓ One special character (!@#$%^&*()\-_=+{};:,<.>)</li>
            </ul>
        </div>
        
        <!-- ========== ADD CAPTCHA HERE ========== -->
        <div style="margin: 20px 0; text-align: center;">
                <p style="margin-bottom: 10px; font-size: 14px; color: #666;">
        Please verify you're not a robot:
                </p>
            <?php echo Captcha::render(); ?>
        </div>
        <!-- ====================================== -->

        <!-- ADD A HIDDEN FIELD TO TRACK CAPTCHA STATE -->
        <input type="hidden" name="captcha_loaded" id="captcha_loaded" value="0">

<script>
// Function to enable form submission when CAPTCHA is ready
function onCaptchaLoad() {
    console.log("CAPTCHA loaded");
    document.getElementById('captcha_loaded').value = '1';
}

// Add callback to your CAPTCHA render if possible
// Or use this workaround:
document.addEventListener('DOMContentLoaded', function() {
    // Wait for CAPTCHA to load (adjust timing as needed)
    setTimeout(function() {
        document.getElementById('captcha_loaded').value = '1';
        console.log("CAPTCHA should be loaded by now");
    }, 2000); // 2 seconds delay
    
    // Prevent form submission if CAPTCHA not loaded
    document.querySelector('form').addEventListener('submit', function(e) {
        if (document.getElementById('captcha_loaded').value === '0') {
            e.preventDefault();
            alert('Please wait for CAPTCHA to load completely.');
            return false;
        }
    });
});
</script>
        
        <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Register
        </button>
    </form>
    
    <div style="margin-top: 15px; text-align: center;">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

<!-- Add Password Strength Meter -->
<div id="password-strength" style="margin: 10px 0; font-weight: bold;"></div>

<script>
function checkPasswordStrength(password) {
    const requirements = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
    };
    
    // Update requirement indicators
    document.getElementById('req-length').style.color = requirements.length ? 'green' : 'red';
    document.getElementById('req-upper').style.color = requirements.upper ? 'green' : 'red';
    document.getElementById('req-lower').style.color = requirements.lower ? 'green' : 'red';
    document.getElementById('req-number').style.color = requirements.number ? 'green' : 'red';
    document.getElementById('req-special').style.color = requirements.special ? 'green' : 'red';
    
    // Update strength meter
    const strengthMeter = document.getElementById('password-strength');
    const metCount = Object.values(requirements).filter(Boolean).length;
    const totalCount = Object.keys(requirements).length;
    
    if (metCount === 0) {
        strengthMeter.innerHTML = 'Password strength: Very Weak';
        strengthMeter.style.color = 'red';
    } else if (metCount <= 2) {
        strengthMeter.innerHTML = 'Password strength: Weak';
        strengthMeter.style.color = 'orange';
    } else if (metCount <= 4) {
        strengthMeter.innerHTML = 'Password strength: Good';
        strengthMeter.style.color = 'blue';
    } else {
        strengthMeter.innerHTML = 'Password strength: Strong';
        strengthMeter.style.color = 'green';
    }
}

// Attach to password input
document.querySelector('input[name="password"]').addEventListener('input', function(e) {
    checkPasswordStrength(e.target.value);
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.querySelector('input[name="password"]');
    if (passwordInput.value) {
        checkPasswordStrength(passwordInput.value);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
=======
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Online Shopping System</title>
    <link rel="stylesheet" href="/online-shopping-system-product/assets/css/style.css">
    <link rel="stylesheet" href="/online-shopping-system-product/assets/css/auth.css">
</head>
<body class="auth-page-body">
    <div class="auth-page-container">
        <div class="auth-card">
            <div class="auth-icon">📝</div>
            <h2>Create Account</h2>
            <p>Join our shopping community today</p>
            
            <?php if ($error): ?>
                <div class="auth-message message-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="auth-message message-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm" onsubmit="showLoading()">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <span>👤</span>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               required placeholder="Choose a username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span>📧</span>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required placeholder="Enter your email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span>🔒</span>
                        <input type="password" id="password" name="password" 
                               required placeholder="Create a password (min 6 chars)">
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <span>🔒</span>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               required placeholder="Confirm your password">
                    </div>
                    <div class="password-match" id="passwordMatch"></div>
                </div>
                
                <button type="submit" class="btn-auth" id="submitBtn">Create Account</button>
            </form>
            
            <div class="switch">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
    <?php if ($success): ?>
    <!-- Success Toast Notification -->
    <div class="toast-notification success" id="successToast">
        <span class="toast-icon">✅</span>
        <span>Registration successful! Redirecting to login page...</span>
    </div>
    
    <script>
        // Show success toast
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('successToast');
            if (toast) {
                // Auto-remove toast after 3 seconds
                setTimeout(() => {
                    toast.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => toast.remove(), 300);
                }, 2700);
            }
        });
    </script>
    <?php endif; ?>
    
    <script>
        // Password validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordMatch = document.getElementById('passwordMatch');
        const submitBtn = document.getElementById('submitBtn');
        
        function checkPasswordStrength(pass) {
            let strength = 0;
            if (pass.length >= 6) strength++;
            if (pass.match(/[a-z]+/)) strength++;
            if (pass.match(/[A-Z]+/)) strength++;
            if (pass.match(/[0-9]+/)) strength++;
            if (pass.match(/[$@#&!]+/)) strength++;
            
            const messages = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['#f44336', '#ff9800', '#ffc107', '#4caf50', '#2e7d32'];
            
            passwordStrength.textContent = `Strength: ${messages[strength]}`;
            passwordStrength.style.color = colors[strength];
        }
        
        function checkPasswordMatch() {
            if (confirmPassword.value === '') {
                passwordMatch.textContent = '';
                passwordMatch.className = 'password-match';
                return;
            }
            
            if (password.value === confirmPassword.value) {
                passwordMatch.textContent = '✓ Passwords match';
                passwordMatch.className = 'password-match match-good';
                submitBtn.disabled = false;
            } else {
                passwordMatch.textContent = '✗ Passwords do not match';
                passwordMatch.className = 'password-match match-bad';
                submitBtn.disabled = true;
            }
        }
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        password.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        confirmPassword.addEventListener('input', checkPasswordMatch);
        
        // Initial check
        checkPasswordStrength(password.value);
        checkPasswordMatch();
    </script>
</body>
</html>
>>>>>>> origin/LoginAndProductPart
