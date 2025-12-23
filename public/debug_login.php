<?php
// test_simple.php
session_start();

// Manual login test
if (isset($_GET['login'])) {
    $_SESSION['user'] = [
        'id' => 1,
        'name' => 'Test User',
        'role' => 'admin',
        'email' => 'test@test.com'
    ];
    $_SESSION['logged_in'] = true;
    echo "<p>✅ Manually logged in</p>";
}

echo "<h2>Session Check</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session data: <pre>";
print_r($_SESSION);
echo "</pre>";

echo '<a href="?login=1">Simulate Login</a> | ';
echo '<a href="dashboard.php">Test Dashboard</a>';
?>