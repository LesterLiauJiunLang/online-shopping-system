<?php
require_once '../includes/header.php';
require_once '../lib/captcha.php'; // ← ADD THIS LINE

$error = '';
$success = '';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Process registration form
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