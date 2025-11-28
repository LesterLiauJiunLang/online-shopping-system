<?php
require_once 'includes/header.php';

$error = '';
$success = '';
$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = Helpers::sanitize($_POST['username']);
    $email = Helpers::sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $result = $auth->register($username, $email, $password);
        
        if ($result === "success") {
            $success = "Registration successful! You can now login.";
        } else {
            $error = $result;
        }
    }
}
?>

<div class="form-container">
    <h2>Register</h2>
    
    <?php echo Helpers::displayError($error); ?>
    <?php echo Helpers::displaySuccess($success); ?>
    
    <form method="POST" action="">
        <?php echo Helpers::inputField('text', 'username', isset($_POST['username']) ? $_POST['username'] : '', 'Username', true); ?>
        <?php echo Helpers::inputField('email', 'email', isset($_POST['email']) ? $_POST['email'] : '', 'Email', true); ?>
        <?php echo Helpers::inputField('password', 'password', '', 'Password', true); ?>
        <?php echo Helpers::inputField('password', 'confirm_password', '', 'Confirm Password', true); ?>
        
        <button type="submit" style="width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Register
        </button>
    </form>
    
    <div style="margin-top: 15px; text-align: center;">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>