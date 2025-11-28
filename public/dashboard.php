<?php
require_once '../includes/header.php';

$auth = new Auth();
$auth->requireLogin();
?>

<h2>Dashboard</h2>
<p>Welcome to your dashboard, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

<div class="nav">
    <h3>Quick Links:</h3>
    <ul>
        <li><a href="profile.php">Update Profile</a></li>
        <li><a href="change-password.php">Change Password</a></li>
        <?php if ($auth->isAdmin()): ?>
            <li><a href="admin/members.php">Manage Members</a></li>
        <?php endif; ?>
    </ul>
</div>

<?php require_once '../includes/footer.php'; ?>