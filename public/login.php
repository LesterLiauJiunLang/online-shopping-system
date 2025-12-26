<?php
// ========== ADD THIS AT THE VERY TOP ==========
ob_start(); // Start output buffering

// Force session start at the VERY TOP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../lib/base.php';  
require_once '../lib/auth.php';

// Check if already logged in 
if (is_logged_in()) {
    // Redirect to appropriate dashboard based on role
    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'admin':
                redirect('adminDashboard.php');
                break;
            case 'support':
                redirect('supportDashboard.php');
                break;
            case 'member':
            default:
                redirect('memberDashboard.php');
                break;
        }
    }
    exit();
}

// Check for registration success message from register.php
if (isset($_SESSION['registration_success']) && $_SESSION['registration_success']) {
    $registration_message = $_SESSION['registration_message'] ?? 'Registration successful!';
    unset($_SESSION['registration_success']);
    unset($_SESSION['registration_message']);
} 

// ========== AJAX LOGIN ENDPOINT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_login'])) {
    // Clean ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) ? 1 : 0;
    
    $response = [
        'success' => false,
        'message' => '',
        'redirect' => 'login.php',
        'needs_verification' => false
    ];
    
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $response['message'] = "Security token invalid.";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Call login function
    $result = login($username, $password);
    
    if ($result === "success" || $result === "success_needs_verification") {
        $response['success'] = true;
        $response['message'] = "Login successful!";
        
        // Determine redirect based on ACTUAL role in session
        if (isset($_SESSION['role'])) {
            switch ($_SESSION['role']) {
                case 'admin':
                    $response['redirect'] = 'adminDashboard.php';
                    break;
                case 'support':
                    $response['redirect'] = 'supportDashboard.php';
                    break;
                case 'member':
                default:
                    $response['redirect'] = 'memberDashboard.php';
                    break;
            }
        }
        
        // Set remember me token if checked
        if ($remember_me) {
            generate_remember_token($_SESSION['user_id']);
        }
        
        // Check if email needs verification
        if ($result === "success_needs_verification" || !$_SESSION['email_verified']) {
            $response['needs_verification'] = true;
            $_SESSION['needs_email_verification'] = true;
        }
        
        // Check if coming from registration
        if (isset($_SESSION['show_verification_reminder'])) {
            $response['needs_verification'] = true;
            $_SESSION['needs_email_verification'] = true;
            unset($_SESSION['show_verification_reminder']);
        }
        
    } else {
        $response['message'] = $result;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
// ========================================

// Process traditional form submission (non-AJAX)
if (is_post() && !isset($_POST['ajax_login'])) {
    $username = post('username');
    $password = post('password');
    $remember_me = isset($_POST['remember_me']) ? 1 : 0;
    
    $result = login($username, $password);
    
    if ($result === "success" || $result === "success_needs_verification") {
        if ($remember_me) {
            generate_remember_token($_SESSION['user_id']);
        }
        
        if ($result === "success_needs_verification" || !$_SESSION['email_verified']) {
            $_SESSION['needs_email_verification'] = true;
        }
        
        if (isset($_SESSION['show_verification_reminder'])) {
            $_SESSION['needs_email_verification'] = true;
            unset($_SESSION['show_verification_reminder']);
        }
        
        // Direct redirect based on role
        if (isset($_SESSION['role'])) {
            switch ($_SESSION['role']) {
                case 'admin':
                    redirect('adminDashboard.php');
                    break;
                case 'support':
                    redirect('supportDashboard.php');
                    break;
                case 'member':
                default:
                    redirect('memberDashboard.php');
                    break;
            }
        }
        
    } else {
        // Handle error messages
        $_err['login'] = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GadgetMart</title>
    
    <!-- External CSS Files -->
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <!-- Header -->
    <div class="auth-header">
        <h1>GadgetMart</h1>
        <p>Login to Your Account</p>
    </div>
    
    <!-- Login Form -->
    <div class="auth-container">
        <h2 class="auth-title">Login</h2>
        
        <!-- Error Message (for non-AJAX) -->
        <?php if (isset($_err['login'])): ?>
            <div class="auth-message error">
                <?php echo htmlspecialchars($_err['login']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Registration Success Message -->
        <?php if (isset($registration_message)): ?>
            <div class="auth-message success">
                <?php echo htmlspecialchars($registration_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Error Message Container (for AJAX) -->
        <div id="login-error" class="auth-message error" style="display: none;"></div>
        
        <form id="login-form" method="POST">
            <div class="auth-form">
                <div class="auth-form-group">
                    <label for="username" class="auth-label">Username or Email:</label>
                    <input type="text" id="username" name="username" class="auth-input" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>
                
                <div class="auth-form-group">
                    <label for="password" class="auth-label">Password:</label>
                    <input type="password" id="password" name="password" class="auth-input" required>
                </div>
            </div>
            
            <!-- Remember Me Checkbox -->
            <div class="auth-remember">
                <input type="checkbox" name="remember_me" id="remember_me" value="1">
                <label for="remember_me">Remember me for 30 days</label>
            </div>
            
            <!-- Hidden fields for AJAX -->
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="ajax_login" value="1">
            
            <!-- Submit Button -->
            <button type="submit" id="login-btn" class="auth-btn">
                Login
            </button>
        </form>
        
        <!-- Links -->
        <div class="auth-links">
            <p>
                <a href="password_reset.php" class="auth-link">Forgot Password?</a>
                <a href="register.php" class="auth-link">Create Account</a>
            </p>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="auth-footer">
        <p>&copy; <?php echo date('Y'); ?> Online Shopping System. All rights reserved.</p>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../assets/js/notifications.js"></script>
    <script src="../assets/js/login.js"></script>
</body>
</html>

