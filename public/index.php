<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $logout_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

// Check for registration success message
if (isset($_SESSION['registration_success']) && $_SESSION['registration_success']) {
    $registration_message = $_SESSION['registration_message'] ?? 'Registration successful!';
    unset($_SESSION['registration_success']);
    unset($_SESSION['registration_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GadgetMart - Home</title>
    <!-- Include both CSS files -->
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Header with home-header class -->
    <div class="home-header">
        <div class="logo">
            <strong>GadgetMart</strong>
        </div>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])): ?>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="index.php">Home</a>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <!-- Hero Section -->
        <div class="hero">
            <h1>Welcome to Our Online Store</h1>
            <p>Discover amazing products at great prices. Shop from the comfort of your home with our secure and easy-to-use platform.</p>
            
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])): ?>
                <a href="dashboard.php" class="btn-hero">Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn-hero">Get Started</a>
                <a href="login.php" class="btn-hero">Login</a>
            <?php endif; ?>
        </div>

        <!-- Features Section -->
        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">🛒</div>
                <h3>Easy Shopping</h3>
                <p>Browse through thousands of products with our intuitive interface.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Payments</h3>
                <p>Your transactions are protected with bank-level security.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🚚</div>
                <h3>Fast Delivery</h3>
                <p>Get your products delivered quickly with reliable logistics.</p>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="cta-section">
            <h2>Ready to Start Shopping?</h2>
            <p>Join thousands of satisfied customers who shop with us.</p>
            
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])): ?>
                <p>You're already logged in. Start shopping now!</p>
                <a href="dashboard.php" class="btn-hero">Go Shopping</a>
            <?php else: ?>
                <a href="register.php" class="btn-hero">Create Free Account</a>
                <p style="margin-top: 15px; color: #666;">Already have an account? <a href="login.php">Login here</a></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="home-footer">
        <p>&copy; <?php echo date('Y'); ?> GadgetMart. All rights reserved.</p>
        <p>
            <a href="#">Privacy Policy</a> | 
            <a href="#">Terms of Service</a> | 
            <a href="#">Contact Us</a>
        </p>
    </div>
    <!-- Toast Notification for logout success -->
<?php if (isset($logout_message)): ?>
<div class="toast-notification success" id="logoutToast">
    <span class="toast-icon">👋</span>
    <span><?php echo htmlspecialchars($logout_message); ?></span>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toast = document.getElementById('logoutToast');
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