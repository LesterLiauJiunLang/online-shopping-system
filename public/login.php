<?php

require_once '../lib/base.php';  
require_once '../lib/auth.php';

// Check if already logged in 
if (isset($_SESSION['user']) || (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true)) {
    // Already logged in - go to dashboard
    header("Location: dashboard.php");
    exit();
}

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
        
    } else {
        // Handle error messages
        $_err['login'] = $result;
    }
}
?>

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
    </div>
    
    <div style="margin-top: 15px; text-align: center;">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>