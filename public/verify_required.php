<?php
// verify_required.php
require_once '../lib/base.php';
require_once '../lib/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user email if logged in
$user_email = '';
$is_logged_in = false;

if (is_logged_in()) {
    $is_logged_in = true;
    $uid = $_SESSION['user_id'];
    
    try {
        global $_db;
        $stmt = $_db->prepare("SELECT email, email_verified FROM users WHERE user_id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        $user_email = $user['email'] ?? '';
        $is_verified = $user['email_verified'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting user data: " . $e->getMessage());
    }
}

// Set page title
$page_title = 'Why Email Verification is Required';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - GadgetMart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .popup-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .popup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .popup-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .popup-header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .user-email-display {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 15px;
            margin: 20px auto;
            max-width: 500px;
            font-size: 16px;
        }
        
        .popup-content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #4a5568;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .benefit-card {
            background: #f7fafc;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        
        .benefit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .benefit-card i {
            font-size: 36px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .benefit-card h3 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .benefit-card p {
            color: #718096;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .steps-list {
            background: #edf2f7;
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
        }
        
        .steps-list ol {
            padding-left: 20px;
            margin: 0;
        }
        
        .steps-list li {
            margin-bottom: 15px;
            padding-left: 10px;
            color: #4a5568;
        }
        
        .steps-list li strong {
            color: #2d3748;
        }
        
        .tips-box {
            background: #feebc8;
            border: 1px solid #fbd38d;
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
        }
        
        .tips-box h3 {
            color: #c05621;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tips-box ul {
            list-style: none;
            padding-left: 0;
        }
        
        .tips-box li {
            margin-bottom: 10px;
            padding-left: 25px;
            position: relative;
            color: #744210;
        }
        
        .tips-box li:before {
            content: "💡";
            position: absolute;
            left: 0;
        }
        
        .popup-footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            border: none;
            min-width: 200px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #718096;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }
        
        .btn-close {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-close:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .popup-content {
                padding: 20px;
            }
            
            .benefits-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="popup-container">
        <div class="popup-header">
            <h1>
                <i class="fas fa-shield-alt"></i>
                Email Verification Required
            </h1>
            <p>Secure your account and unlock all shopping features</p>
            
            <?php if ($user_email): ?>
            <div class="user-email-display">
                <i class="fas fa-envelope"></i>
                <strong>Your email:</strong> <?php echo htmlspecialchars($user_email); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="popup-content">
            <div class="section">
                <h2><i class="fas fa-unlock-alt"></i> Why Verify Your Email?</h2>
                <p style="color: #666; margin-bottom: 20px; font-size: 16px;">
                    Email verification is a crucial security measure that protects your account and enables 
                    all shopping features. It ensures that you are the legitimate owner of the email address 
                    and helps prevent unauthorized access.
                </p>
                
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Complete Purchases</h3>
                        <p>Finish checkout and buy products securely</p>
                    </div>
                    
                    <div class="benefit-card">
                        <i class="fas fa-history"></i>
                        <h3>View Order History</h3>
                        <p>Track all your past purchases and orders</p>
                    </div>
                    
                    <div class="benefit-card">
                        <i class="fas fa-comment"></i>
                        <h3>Write Reviews</h3>
                        <p>Share your feedback on purchased products</p>
                    </div>
                    
                    <div class="benefit-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Save Addresses</h3>
                        <p>Store shipping addresses for faster checkout</p>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-list-ol"></i> How to Verify Your Email</h2>
                <div class="steps-list">
                    <ol>
                        <li>
                            <strong>Step 1:</strong> Click "Verify Email Now" on your profile page or below
                        </li>
                        <li>
                            <strong>Step 2:</strong> Check your email inbox (and spam folder) for the verification code
                        </li>
                        <li>
                            <strong>Step 3:</strong> Enter the 6-digit code on the verification page
                        </li>
                        <li>
                            <strong>Step 4:</strong> Enjoy full access to all shopping features!
                        </li>
                    </ol>
                </div>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-question-circle"></i> Troubleshooting Tips</h2>
                <div class="tips-box">
                    <h3><i class="fas fa-lightbulb"></i> Common Solutions</h3>
                    <ul>
                        <li><strong>Email not received?</strong> Check your spam or junk folder</li>
                        <li><strong>Code expired?</strong> Request a new verification code</li>
                        <li><strong>Wrong email address?</strong> Update your email in profile settings</li>
                        <li><strong>Still having issues?</strong> Contact our support team for help</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="popup-footer">
            <div class="action-buttons">
                <?php if ($is_logged_in && !$is_verified): ?>
                <a href="verify_email_code.php" class="btn btn-primary" onclick="window.opener.focus();">
                    <i class="fas fa-envelope"></i> Verify Email Now
                </a>
                <?php elseif (!$is_logged_in): ?>
                <a href="login.php" class="btn btn-primary" onclick="window.opener.focus();">
                    <i class="fas fa-sign-in-alt"></i> Login to Verify
                </a>
                <?php endif; ?>
                
                <button onclick="window.close()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Close Window
                </button>
            </div>
            
            <p style="margin-top: 20px; color: #718096; font-size: 14px;">
                <i class="fas fa-info-circle"></i>
                You can always access this information from your profile page
            </p>
        </div>
    </div>
    
    <script>
    // Focus the parent window when clicking verification links
    document.addEventListener('DOMContentLoaded', function() {
        const links = document.querySelectorAll('a[href^="verify_email_code.php"], a[href^="login.php"]');
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.opener && !window.opener.closed) {
                    window.opener.focus();
                }
            });
        });
        
        // Auto-close if opened incorrectly
        if (window.opener === null && !window.history.length > 1) {
            setTimeout(() => {
                document.querySelector('.btn-secondary').textContent = 'Return to Website';
                document.querySelector('.btn-secondary').onclick = function() {
                    window.location.href = 'index.php';
                };
            }, 1000);
        }
    });
    </script>
</body>
</html>