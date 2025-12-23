<?php
// includes/verification_popup.php

// Only show if member needs verification and not admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'member' && 
    (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified'])) {
    
    // Get user email
    global $_db;
    $stmt = $_db->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $user_email = $user['email'] ?? '';
    
    // Check if popup was recently hidden (cookie lasts 24 hours)
    $popup_hidden = isset($_COOKIE['hide_verify_popup']);
    
    // Show popup if:
    // 1. Force flag is set (from login) OR
    // 2. Popup hasn't been hidden by cookie
    $show_popup = isset($_SESSION['force_verify_popup']) || !$popup_hidden;
    
    if ($show_popup) {
        // Clear force flag
        unset($_SESSION['force_verify_popup']);
?>
<style>
.verification-popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(3px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.verification-popup-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 30px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    animation: slideUp 0.4s ease;
    position: relative;
}

.verification-popup-icon {
    font-size: 48px;
    text-align: center;
    margin-bottom: 15px;
}

.verification-popup-title {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
    font-size: 24px;
}

.verification-popup-email {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 12px 15px;
    text-align: center;
    font-weight: bold;
    color: #495057;
    margin: 15px 0;
    word-break: break-all;
}

.verification-popup-features {
    background: #f0f7ff;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
}

.verification-popup-features ul {
    list-style: none;
    padding-left: 0;
    margin: 10px 0 0 0;
}

.verification-popup-features li {
    padding: 5px 0;
    display: flex;
    align-items: center;
}

.verification-popup-features li:before {
    content: "✓";
    color: #28a745;
    font-weight: bold;
    margin-right: 10px;
}

.verification-popup-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 25px 0;
}

.verification-popup-btn {
    padding: 14px 20px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.verification-popup-btn-primary {
    background: #007bff;
    color: white;
}

.verification-popup-btn-primary:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
}

.verification-popup-btn-secondary {
    background: #6c757d;
    color: white;
}

.verification-popup-btn-secondary:hover {
    background: #545b62;
    transform: translateY(-2px);
}

.verification-popup-btn-outline {
    background: transparent;
    color: #6c757d;
    border: 2px solid #dee2e6;
}

.verification-popup-btn-outline:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

.verification-popup-note {
    text-align: center;
    color: #6c757d;
    font-size: 14px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 576px) {
    .verification-popup-content {
        padding: 20px;
        width: 95%;
    }
    
    .verification-popup-actions {
        flex-direction: column;
    }
    
    .verification-popup-btn {
        padding: 12px 15px;
    }
}
</style>

<div id="verificationPopup" class="verification-popup-overlay">
    <div class="verification-popup-content">
        <div class="verification-popup-icon">📧</div>
        <h2 class="verification-popup-title">Verify Your Email</h2>
        
        <div class="verification-popup-message">
            <p style="text-align: center; color: #666; line-height: 1.6;">
                To unlock all features and complete purchases, please verify your email address.
            </p>
            
            <div class="verification-popup-email">
                <?php echo htmlspecialchars($user_email); ?>
            </div>
        </div>
        
        <div class="verification-popup-features">
            <p style="margin-bottom: 10px; font-weight: 600; color: #333;">
                <strong>Verification unlocks:</strong>
            </p>
            <ul>
                <li>Complete purchases (checkout)</li>
                <li>View order history</li>
                <li>Write product reviews</li>
                <li>Save shipping addresses</li>
                <li>Access wishlist features</li>
            </ul>
        </div>
        
        <div class="verification-popup-actions">
            <a href="verify_email_code.php" class="verification-popup-btn verification-popup-btn-primary">
                <span>📨</span> Verify Email Now
            </a>
            
            <a href="resend_verification.php" class="verification-popup-btn verification-popup-btn-secondary">
                <span>🔄</span> Resend Verification Code
            </a>
            
            <button onclick="hideVerificationPopup()" class="verification-popup-btn verification-popup-btn-outline">
                <span>⏰</span> Maybe Later
            </button>
        </div>
        
        <div class="verification-popup-note">
            <p>You can verify anytime from your profile page.</p>
            <p style="font-size: 12px; margin-top: 8px; color: #868e96;">
                <em>This popup won't show again for 24 hours after clicking "Maybe Later".</em>
            </p>
        </div>
    </div>
</div>

<script>
function hideVerificationPopup() {
    const popup = document.getElementById('verificationPopup');
    if (popup) {
        // Add closing animation
        popup.style.opacity = '0';
        popup.style.transform = 'translateY(30px)';
        popup.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            popup.style.display = 'none';
            // Prevent body scrolling if it was prevented
            document.body.style.overflow = '';
        }, 300);
        
        // Set cookie to hide for 24 hours
        document.cookie = "hide_verify_popup=1; max-age=" + (3600 * 24) + "; path=/";
    }
}

// Prevent body scrolling when popup is open
document.body.style.overflow = 'hidden';

// Close on outside click
document.getElementById('verificationPopup').addEventListener('click', function(e) {
    if (e.target === this) hideVerificationPopup();
});

// Close with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideVerificationPopup();
});

// Optional: Auto-close after 30 seconds 
// setTimeout(hideVerificationPopup, 30000);
</script>
<?php
    }
}
?>