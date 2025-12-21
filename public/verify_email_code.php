<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../lib/base.php';
require_once '../lib/auth.php'; // Add this for is_logged_in() function

$_title = 'Verify Email Code';

// If already logged in and verified, redirect
if (is_logged_in() && isset($_SESSION['email_verified']) && $_SESSION['email_verified']) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if (is_post()) {
    $code = post('verification_code');
    $email = post('email'); // Optional: for users not logged in
    
    if (empty($code) || strlen($code) !== 6) {
        $error = 'Please enter a valid 6-digit code.';
    } else {
        try {
            global $_db;
            
            // Find user with this code
            $query = "SELECT user_id, username, email, role 
                      FROM users 
                      WHERE verification_code = :code 
                        AND verification_code_expires > NOW()
                        AND email_verified = 0";
            
            $stmt = $_db->prepare($query);
            $stmt->execute([':code' => $code]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Mark as verified
                $stmt = $_db->prepare("
                    UPDATE users 
                    SET email_verified = 1, 
                        verification_code = NULL,
                        verification_code_expires = NULL,
                        updated_at = NOW()
                    WHERE user_id = :user_id
                ");
                $stmt->execute([':user_id' => $user['user_id']]);
                
                // Auto-login the user
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['email_verified'] = 1;
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                $_user = [
                    'id' => $user['user_id'],
                    'email' => $user['email'],
                    'name' => $user['username'],
                    'role' => $user['role'],
                    'email_verified' => 1
                ];
                $_SESSION['user'] = $_user;
                $GLOBALS['_user'] = $_user;
                
                $success = '✅ Email verified successfully!';
                temp('success', $success);
                header('Location: dashboard.php');
                exit;
                
            } else {
                // Check if code expired
                $query = "SELECT user_id FROM users WHERE verification_code = :code";
                $stmt = $_db->prepare($query);
                $stmt->execute([':code' => $code]);
                
                if ($stmt->rowCount() === 1) {
                    $error = '❌ Verification code has expired. Please request a new one.';
                } else {
                    $error = '❌ Invalid verification code.';
                }
            }
            
        } catch (PDOException $e) {
            error_log("Verification error: " . $e->getMessage());
            $error = 'Database error occurred.';
        }
    }
}

// If logged in but not verified, get user email
$user_email = '';
if (is_logged_in() && isset($_SESSION['email'])) {
    $user_email = $_SESSION['email'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo encode($_title); ?></title>
    <link rel="stylesheet" href="<?php echo base('assets/css/style.css'); ?>">
    <style>
        .verification-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .code-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
            outline: none;
        }
        
        .code-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }
        
        .timer {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
        }
        
        .resend-link {
            color: #2563eb;
            cursor: pointer;
            text-decoration: underline;
        }
        
        .disabled {
            color: #999;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="verification-container">
        <h2>📧 Verify Your Email</h2>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo encode($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo encode($success); ?></div>
        <?php endif; ?>
        
        <p>Enter the 6-digit verification code sent to:</p>
        <p style="font-weight: bold; font-size: 18px; margin: 10px 0;"><?php echo encode($user_email); ?></p>
        
        <form method="POST" action="" id="verification-form">
            <input type="hidden" name="email" value="<?php echo encode($user_email); ?>">
            
            <!-- Single input field for easier submission -->
            <div style="margin: 20px 0;">
                <label for="verification_code" style="display: block; margin-bottom: 8px;">Verification Code:</label>
                <input type="text" 
                       id="verification_code" 
                       name="verification_code" 
                       maxlength="6" 
                       placeholder="123456" 
                       style="width: 200px; padding: 15px; font-size: 20px; text-align: center; letter-spacing: 5px;"
                       required
                       autofocus>
            </div>
            
            <div class="timer" id="timer">
                Code expires in: <span id="countdown">10:00</span>
            </div>
            
            <button type="submit" style="width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">
                Verify Email
            </button>
        </form>
        
        <div style="margin-top: 20px;">
            <p>Didn't receive the code?</p>
            <a href="resend_verification.php" class="resend-link" id="resend-link">Resend Verification Code</a>
            <span id="resend-timer" style="display: none; margin-left: 10px;"></span>
        </div>
        
        <div style="margin-top: 15px; font-size: 14px; color: #666;">
            <p>📌 Check your spam folder if you don't see the email.</p>
            <p>⚠️ The code will expire in 10 minutes.</p>
        </div>
    </div>
    
    <script>
    // Countdown timer for code expiry (10 minutes)
    let timeLeft = 600; // 10 minutes in seconds
    const countdownElement = document.getElementById('countdown');
    
    function updateTimer() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft > 0) {
            timeLeft--;
            setTimeout(updateTimer, 1000);
        } else {
            countdownElement.textContent = "Expired!";
            document.getElementById('verification_code').disabled = true;
            document.getElementById('resend-link').classList.remove('disabled');
        }
    }
    
    // Auto-submit when 6 digits are entered
    document.getElementById('verification_code').addEventListener('input', function(e) {
        if (this.value.length === 6) {
            // Auto-submit form
            setTimeout(() => {
                document.getElementById('verification-form').submit();
            }, 500);
        }
    });
    
    // Start the timer
    updateTimer();
    
    // Auto-focus on code input
    document.getElementById('verification_code').focus();
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>