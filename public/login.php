<?php
require_once '../includes/header.php';

$error = '';
$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = Helpers::sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $result = $auth->login($username, $password);
    
    if ($result === "success") {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = $result;
    }
}
?>

<div class="form-container">
    <h2>Login</h2>
    
    <?php echo Helpers::displayError($error); ?>
    
    <form method="POST" action="">
        <?php echo Helpers::inputField('text', 'username', '', 'Username or Email', true); ?>
        <?php echo Helpers::inputField('password', 'password', '', 'Password', true); ?>
        
        <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Login
        </button>
    </form>
    
    <div style="margin-top: 15px; text-align: center;">
        <a href="password-reset.php">Forgot Password?</a>
    </div>
    
    <div style="margin-top: 15px; text-align: center;">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>