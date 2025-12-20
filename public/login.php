<?php
<<<<<<< HEAD

require_once '../lib/base.php';  
require_once '../lib/auth.php';

// Check if already logged in 
if (isset($_SESSION['user']) || (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true)) {
    // Already logged in - go to dashboard
=======
session_start();
require_once '../app/controllers/AuthController.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])) {
>>>>>>> origin/LoginAndProductPart
    header("Location: dashboard.php");
    exit();
}

<<<<<<< HEAD
require_once '../includes/header.php';
check_remember_token(); // Check for auto-login on every page

// Check for registration success message
if ($success = temp('success')) {
    // Success message will be displayed
}

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Process login form
if (is_post()) {
    $username = post('username');
    $password = post('password');
    $remember_me = isset($_POST['remember_me']) ? 1 : 0;
    
    $result = login($username, $password);
    
    if ($result === "success" || $result === "success_needs_verification") {
        // If login successful AND remember me was checked, generate token
        if ($remember_me) {
            generate_remember_token($_SESSION['user_id']);
        }
        
        temp('success', 'Login successful!');
        if ($result === "success_needs_verification") {
            // Store where they wanted to go (for after verification)
            if (!isset($_SESSION['redirect_after_verify'])) {
                $_SESSION['redirect_after_verify'] = $_GET['redirect'] ?? 'shop.php';
            }
            redirect('verify_required.php');
        } else {
            // Normal redirection for verified users
            redirect('dashboard.php');
        }
        
=======
// Check for registration success message from register.php
if (isset($_SESSION['registration_success']) && $_SESSION['registration_success']) {
    $registration_message = $_SESSION['registration_message'] ?? 'Registration successful!';
    unset($_SESSION['registration_success']);
    unset($_SESSION['registration_message']);
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_email = trim($_POST['username_email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $authController = new AuthController();
    $result = $authController->login($username_email, $password);
    
    if ($result === "success") {
        if ($remember) {
            setcookie('remember_user', $_SESSION['user_id'], time() + (86400 * 30), "/");
        }
        header("Location: dashboard.php");
        exit();
>>>>>>> origin/LoginAndProductPart
    } else {
        // Handle error messages
        $_err['login'] = $result;
    }
}
?>

<<<<<<< HEAD
<div class="form-container">
    <h2>Login</h2>
    
    <?php echo displaySuccess($success); ?>
    
    <form method="POST" action="">
        <div class="form">
            <label for="username">Username/Email:</label>
            <?php html_text('username'); ?>
            <?php err('username'); ?>
            
            <label for="password">Password:</label>
            <input type="password" id="password" name="password">
            <?php err('password'); ?>
            <?php err('login'); ?>
        </div>
        
        <!-- Remember Me Checkbox -->
        <div style="margin: 15px 0; display: flex; align-items: center;">
            <input type="checkbox" name="remember_me" id="remember_me" value="1" 
                   style="width: auto; margin-right: 8px;">
            <label for="remember_me" style="margin: 0; font-size: 14px;">
                Remember me for 30 days
            </label>
        </div>
        
        <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Login
        </button>
    </form>
    
    <div style="margin-top: 15px; text-align: center;">
        <a href="password_reset.php">Forgot Password?</a>
=======
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Online Shopping System</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-icon">🔐</div>
            <h2>Welcome Back</h2>
            <p>Sign in to your account to continue shopping</p>
            
            <?php if ($error): ?>
                <div class="auth-message message-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="auth-message message-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username_email">Email or Username</label>
                    <div class="input-wrapper">
                        <span>📧</span>
                        <input type="text" id="username_email" name="username_email" 
                               value="<?php echo htmlspecialchars($_POST['username_email'] ?? ''); ?>" 
                               required placeholder="Enter your email or username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span>🔒</span>
                        <input type="password" id="password" name="password" 
                               required placeholder="Enter your password">
                    </div>
                </div>
                
                <div class="options">
                    <label>
                        <input type="checkbox" name="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        Remember me
                    </label>
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-auth">Sign In</button>
            </form>
            
            <div class="switch">
                Don't have an account? <a href="register.php">Sign up here</a>
            </div>
        </div>
>>>>>>> origin/LoginAndProductPart
    </div>
    
    <!-- Toast Notification - Place this just before </body> -->
    <?php if (isset($registration_message)): ?>
    <div class="toast-notification success" id="registrationToast">
        <span class="toast-icon">✅</span>
        <span><?php echo htmlspecialchars($registration_message); ?></span>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('registrationToast');
            if (toast) {
                setTimeout(() => {
                    toast.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>