<?php
session_start();
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/base.php';


// ======== Get database connection ========
$db = db(); // This returns PDO connection

// Initialize variables
$success = false;
$error = '';
$created_username = '';
$created_password = '';
$created_employee_id = '';

// PROCESS FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Check if admin is logged in
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        die("Access denied. Admins only.");
    }

    // 2. Get form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 3. Validate inputs
    if (empty($full_name)) {
        $error = "Full name is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = "Username must be 3-20 characters (letters, numbers, underscores only)";
    }

    if (!$error) {
        // 4. Generate password if empty
        if (empty($password)) {
            // Generate secure random password
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
            $password = substr(str_shuffle($chars), 0, 12);
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters";
        }
    }

    if (!$error) {
        // 5. Generate employee ID
        try {
            $stmt = $db->prepare("SELECT MAX(employee_id) as max_id FROM users WHERE employee_id LIKE 'SUP-%'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result && $result['max_id']) {
                // Extract numeric part safely
                if (preg_match('/SUP-(\d+)/', $result['max_id'], $matches)) {
                    $next_num = intval($matches[1]) + 1;
                } else {
                    $next_num = 1;
                }
                $employee_id = "SUP-" . str_pad($next_num, 3, '0', STR_PAD_LEFT);
            } else {
                $employee_id = "SUP-001";
            }
            
            $created_employee_id = $employee_id;
            
        } catch (PDOException $e) {
            $error = "Failed to generate employee ID";
            error_log("Employee ID generation error: " . $e->getMessage());
        }
    }

    if (!$error) {
        // 6. Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 7. Insert into database
        try {
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash, role, employee_id, created_at) 
                VALUES (?, ?, ?, 'support', ?, NOW())
            ");
            
            $stmt->execute([$username, $email, $password_hash, $employee_id]);
            
            // 8. Log the action
            $stmt = $db->prepare("
                INSERT INTO user_activity (user_id, activity_type, description, ip_address) 
                VALUES (?, 'create_support', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                "Created support agent: $full_name ($email)",
                $_SERVER['REMOTE_ADDR']
            ]);
            
            // Success!
            $success = true;
            $created_username = $username;
            $created_password = $password;
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Username or email already exists!";
            } else {
                $error = "An error occurred. Please try again.";
                error_log("Database error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Support Agent</title>
    <link rel="stylesheet" href="/online-shopping-system/assets/css/style.css">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 500px; 
            margin: 50px auto; 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        .form-group { 
            margin-bottom: 25px; 
            position: relative;
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600;
            color: #333;
        }
        input { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        button { 
            padding: 14px 28px; 
            background: linear-gradient(to right, #007bff, #0056b3); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
            margin-top: 10px;
        }
        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 91, 187, 0.3);
        }
        .success-box { 
            background: #d4edda; 
            color: #155724; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
        }
        .error-box { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 25px;
            border-left: 5px solid #dc3545;
        }
        .credentials { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 25px 0; 
            font-family: 'Courier New', monospace;
            border-left: 5px solid #17a2b8;
        }
        .credentials p {
            margin: 10px 0;
            padding: 8px;
            background: white;
            border-radius: 4px;
        }
        .back-link { 
            display: inline-block; 
            margin-top: 20px; 
            color: #666; 
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .back-link:hover {
            background: #f8f9fa;
            color: #333;
        }
        small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.9em;
        }
        .info-note {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }
        h2 {
            color: #333;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <?php if ($success): ?>
            <!-- SUCCESS MESSAGE -->
            <div class="success-box">
                <h3>✅ Support Agent Created Successfully!</h3>
                <p>The new support agent account has been created.</p>
            </div>
            
            <h2>Login Credentials</h2>
            <div class="info-note">
                <p><strong>⚠️ Important:</strong> Share these credentials with the new support agent securely. 
                They should change their password upon first login.</p>
            </div>
            
            <div class="credentials">
                <p><strong>Full Name:</strong> <?= htmlspecialchars($full_name) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
                <p><strong>Username:</strong> <?= htmlspecialchars($created_username) ?></p>
                <p><strong>Password:</strong> <?= htmlspecialchars($created_password) ?></p>
                <p><strong>Employee ID:</strong> <?= htmlspecialchars($created_employee_id) ?></p>
                <p><strong>Role:</strong> Support Agent</p>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <form method="GET" action="" style="flex: 1;">
                    <button type="submit">➕ Create Another</button>
                </form>
                <a class="back-link" href="../adminDashboard.php" style="flex: 1; text-align: center; line-height: 40px;">
                    ← Back to Dashboard
                </a>
            </div>
            
        <?php else: ?>
            <!-- CREATE FORM -->
            <h2>Create New Support Agent</h2>
            
            
            
            <?php if ($error): ?>
                <div class="error-box">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="supportForm">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" required 
                           value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>"
                           placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" name="email" id="email" required 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                           placeholder="email@example.com">
                </div>
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" name="username" id="username" required 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                           placeholder="3-20 characters (letters, numbers, underscores)">
                    <small>Only letters, numbers, and underscores allowed</small>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" 
                           placeholder="Leave blank to auto-generate secure password">
                    <small>Minimum 8 characters if provided. Auto-generated if blank.</small>
                </div>
                
                <button type="submit">➕ Create Support Account</button>
            </form>
            
            <a class="back-link" href="../adminDashboard.php">← Back to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>