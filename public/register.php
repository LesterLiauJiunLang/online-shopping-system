<?php
session_start();
require_once '../app/controllers/AuthController.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])) {
    header("Location: dashboard.php");
    exit();
}

// Process registration form
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
        } else {
            $error = $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Online Shopping System</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Toast notification styles */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #4CAF50;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            animation: slideUp 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 400px;
            width: 90%;
        }
        
        .toast-notification.error {
            background: #f44336;
        }
        
        .toast-notification.success {
            background: #4CAF50;
        }
        
        .toast-icon {
            font-size: 20px;
        }
        
        @keyframes slideUp {
            from {
                transform: translateX(-50%) translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        
        /* Loading overlay for better UX */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #5a4bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
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