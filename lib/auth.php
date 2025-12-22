<?php

// ============================================================================
// Session Start (MUST BE HERE!)
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// Global User Object (From Practical 7)
// ============================================================================
$_user = $_SESSION['user'] ?? null;

// ============================================================================
// Practical 7 Auth Function
// ============================================================================
function auth(...$roles) {
    // Get user from session
    $user = $_SESSION['user'] ?? null;
    
    // If not logged in
    if (!$user) {
        // Save where we came from
        $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
        
        // Go to login
        header('Location: login.php');
        exit();
    }
    
    // Set global $_user
    global $_user;
    $_user = $user;
    
    // Check roles if specified
    if (!empty($roles) && !in_array($user['role'], $roles)) {
        header('Location: access_denied.php');
        exit();
    }
}



// ============================================================================
// Basic Authentication Functions
// ============================================================================


function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function is_admin() {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// ============================================================================
// Login/Logout Functions
// ============================================================================

function login($username, $password) {
    global $_db;

    // Sanitize input
    $username = trim($username);
    $password = trim($password);
    
    // Validate email format if email is provided
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $username = filter_var($username, FILTER_SANITIZE_EMAIL);
    }
    
    try {
        $query = "SELECT user_id, username, email, password_hash, role, is_active, login_attempts, last_attempt, email_verified 
                  FROM users 
                  WHERE username = :username OR email = :username";
        
        $stmt = $_db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if account is active
            if (!$row['is_active']) {
                return "Account is deactivated. Please contact administrator.";
            }

            // Enhanced login attempts check with time-based lock
            if ($row['login_attempts'] >= 3) {
                $last_attempt = $row['last_attempt'] ?? null;
                
                if ($last_attempt) {
                    $lock_time = strtotime($last_attempt) + (15 * 60);
                    if (time() < $lock_time) {
                        $minutes_left = ceil(($lock_time - time()) / 60);
                        return "Account temporarily locked. Try again in " . $minutes_left . " minutes.";
                    } else {
                        reset_login_attempts($row['user_id']);
                    }
                } else {
                    return "Account temporarily locked. Please reset your password.";
                }
            }

            // Verify password
            if (password_verify($password, $row['password_hash'])) {

                session_regenerate_id(true);

                // Reset login attempts on successful login
                reset_login_attempts($row['user_id']);
                
                // Update last login
                update_last_login($row['user_id']);
                
                // Set session variables
                $_SESSION['email_verified'] = $row['email_verified'];
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['logged_in'] = true;

                // Cache profile photo in session for header
                $stm = $_db->prepare('SELECT profile_photo FROM users WHERE user_id = ?');
                $stm->execute([$row['user_id']]);
                $_SESSION['photo'] = $stm->fetchColumn() ?: 'avatar.png';
                // Create $_user object (Practical 7 style)
                $_user = [
                        'id' => $row['user_id'],
                        'email' => $row['email'],
                        'name' => $row['username'],
                        'role' => $row['role'],
                        'email_verified' => $row['email_verified']
                         ];

                // Store in global scope for auth() function
                $GLOBALS['_user'] = $_user;
    
                // Store in session (Practical 7 style)
                $_SESSION['user'] = $_user;

                //  Return special status for unverified members
                if ($row['role'] === 'member' && !$row['email_verified']) {
                    return "success_needs_verification";
                }

                return "success";

            } else {
                // Increment login attempts
                increment_login_attempts($row['user_id']);
                $attempts_left = 2 - $row['login_attempts'];
                return "Invalid password. " . $attempts_left . " attempts left.";
            }
        }
        return "Username/Email not found.";
        
    } catch(PDOException $exception) {
        return "Database error: " . $exception->getMessage();
    }
}

function logout() {
    global $_db;
    // Clear remember token if user is logged in
    if (is_logged_in()) {
        $query = "UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE user_id = :user_id";
        $stmt = $_db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
    }
    
    // Clear remember token cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}


// ============================================================================
// Registration Functions
// ============================================================================

function register_user($username, $email, $password, $role = 'member') {
    global $_db;
    
    try {
        // Check if username or email already exists
        if (!is_unique($username, 'users', 'username')) {
            return "Username already exists.";
        }
        if (!is_unique($email, 'users', 'email')) {
            return "Email already exists.";
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // For members: NOT verified yet, generate verification code but don't send email yet
        $email_verified = 0; // NOT verified
        $verification_code = sprintf('%06d', random_int(0, 999999));
        $verification_code_expires = date('Y-m-d H:i:s', strtotime('+24 hours')); // 24 hour expiry
        
        // Insert new user WITH verification code but not verified
        $query = "INSERT INTO users (username, email, password_hash, role, email_verified, verification_code, verification_code_expires) 
                  VALUES (:username, :email, :password_hash, :role, :email_verified, :verification_code, :verification_code_expires)";

        $stmt = $_db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':email_verified', $email_verified, PDO::PARAM_INT);
        $stmt->bindParam(':verification_code', $verification_code);
        $stmt->bindParam(':verification_code_expires', $verification_code_expires);

        if ($stmt->execute()) {
            // ✅ DO NOT send verification email on registration
            // Just store the code for later when user chooses to verify
            return "success";
        } else {
            return "Registration failed. Please try again.";
        }
        
    } catch(PDOException $exception) {
        error_log("Registration error: " . $exception->getMessage());
        return "Database error: " . $exception->getMessage();
    }
}
// ============================================================================
// Verification Email Function
// ============================================================================

function send_verification_code_email($to_email, $username, $code) {
    try {
        require_once __DIR__ . '/base.php';
        $mail = get_mail();
        
        // Set recipient
        $mail->addAddress($to_email, $username);
        $mail->Subject = 'Verify Your Email - Tech Gadgets Shop';
        
        // Email content with code
        $template_path = __DIR__ . '/email_templates/verification_code_email.html';
        
        if (!file_exists($template_path)) {
            // Fallback to simple email
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .code { font-size: 32px; font-weight: bold; color: #2563eb; letter-spacing: 5px; text-align: center; margin: 20px 0; }
                    .note { color: #666; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Verify Your Email</h2>
                    <p>Hello $username,</p>
                    <p>Enter this verification code on our website:</p>
                    
                    <div class='code'>$code</div>
                    
                    <p class='note'>This code expires in 10 minutes.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </div>
            </body>
            </html>
            ";
            
            $mail->AltBody = "Your verification code is: $code\nThis code expires in 10 minutes.";
        } else {
            // Read template
            $template = file_get_contents($template_path);
            
            // Replace placeholders
            $template = str_replace('{{USERNAME}}', htmlspecialchars($username), $template);
            $template = str_replace('{{VERIFICATION_CODE}}', htmlspecialchars($code), $template);
            $template = str_replace('{{YEAR}}', date('Y'), $template);
            
            $mail->Body = $template;
            $mail->AltBody = "Your verification code is: $code\nThis code expires in 10 minutes.";
        }
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Verification email error: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// Password Reset Functions
// ============================================================================

function create_password_reset($email) {
    global $_db;
    
    try {
        $query = "SELECT user_id, username FROM users WHERE email = :email";
        $stmt = $_db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $row['user_id'];
            $username = $row['username'];
            
            // Generate token
            $token = bin2hex(random_bytes(50));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save to database
            $query = "INSERT INTO password_resets (user_id, reset_token, expires_at) 
                      VALUES (:user_id, :token, :expires)";
            
            $stmt = $_db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires', $expires);
            
            if ($stmt->execute()) {
                // Send email using practical's PHPMailer setup
                if (send_password_reset_email($email, $username, $token)) {
                    return true;
                } else {
                    return "email_failed";
                }
            }
        }
        return false;
        
    } catch(PDOException $e) {
        return false;
    }
}


function send_password_reset_email($to_email, $username, $token) {
    try {
         require_once __DIR__ . '/base.php';
        // Get mail object from base.php (uses practical's PHPMailer setup)
        $mail = get_mail();
        
        // Set recipient
        $mail->addAddress($to_email, $username);
        
        // Email content
        $mail->Subject = 'Password Reset - Tech Gadgets Shop';
        
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/online-shopping-system/public/password_reset.php?token=" . urlencode($token);
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9fafb; border: 1px solid #e5e7eb; }
                .button { display: inline-block; background: #2563eb; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Tech Gadgets Shop</h2>
                    <p>Password Reset Request</p>
                </div>
                <div class="content">
                    <p>Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
                    
                    <p>Click the button below to reset your password:</p>
                    
                    <p style="text-align: center;">
                        <a href="' . $reset_link . '" class="button">
                            Reset Password
                        </a>
                    </p>
                    
                    <p>Or copy this link:<br>
                       <code>' . $reset_link . '</code>
                    </p>
                    
                    <p><strong>This link expires in 1 hour</strong></p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Tech Gadgets Shop</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Password Reset Link: " . $reset_link . "\nThis link expires in 1 hour.";
        
        // Send email
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}


function reset_password($token, $new_password) {
    global $_db;
    
    try {
        $query = "SELECT pr.user_id, pr.expires_at 
                  FROM password_resets pr 
                  WHERE pr.reset_token = :token AND pr.is_used = 0 AND pr.expires_at > NOW()";
        
        $stmt = $_db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $row['user_id'];
            
            // Hash new password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update user password
            $query = "UPDATE users 
                      SET password_hash = :password_hash, login_attempts = 0 
                      WHERE user_id = :user_id";
            
            $stmt = $_db->prepare($query);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                // Mark token as used
                $query = "UPDATE password_resets SET is_used = 1 WHERE reset_token = :token";
                $stmt = $_db->prepare($query);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                
                return "success";
            }
        }
        return "Invalid or expired reset token.";
        
    } catch(PDOException $exception) {
        return "Database error: " . $exception->getMessage();
    }
}

// ============================================================================
// Helper Functions
// ============================================================================

function increment_login_attempts($user_id) {
    global $_db;
    $query = "UPDATE users 
              SET login_attempts = login_attempts + 1, 
                  last_attempt = NOW() 
              WHERE user_id = :user_id";
    $stmt = $_db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
}

function reset_login_attempts($user_id) {
    global $_db;
    $query = "UPDATE users SET login_attempts = 0 WHERE user_id = :user_id";
    $stmt = $_db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
}

function update_last_login($user_id) {
    global $_db;
    $query = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
    $stmt = $_db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
}

// Remember Me Functions
function generate_remember_token($user_id) {
    global $_db;
    
    try {
        // Generate token RANDOM token (64 hex chars)
        $token = bin2hex(random_bytes(32));// 256-bit random token

        // 2. Create HASHED version for database (security!)
        $hashed_token = hash('sha256', $token); // 64 hex chars

        // 3. Set expiration (30 days)
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Prepare the statement FIRST
        $query = "UPDATE users 
                  SET remember_token = :token, remember_expires = :expires 
                  WHERE user_id = :user_id";
        
        $stmt = $_db->prepare($query); // ← You were missing this line!
        
        
        $stmt->bindParam(':token', $hashed_token); // ← Store HASH instead!
        $stmt->bindParam(':expires', $expires);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Set cookie (with security improvements)
        $cookie_options = [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        // Add secure flag if using HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $cookie_options['secure'] = true;
        }
        
        setcookie('remember_token', $token, $cookie_options);
        
        return true;
        
    } catch(PDOException $exception) {
        error_log("Remember token error: " . $exception->getMessage());
        return false;
    }
}

function check_remember_token() {
    global $_db;
    
    error_log("DEBUG: Starting remember token check...");
    error_log("DEBUG: Cookie exists? " . (isset($_COOKIE['remember_token']) ? 'YES' : 'NO'));
    error_log("DEBUG: Already logged in? " . (is_logged_in() ? 'YES' : 'NO'));
    
    // Only check if cookie exists AND user is NOT logged in
    if (isset($_COOKIE['remember_token']) && !is_logged_in()) {
        $token = $_COOKIE['remember_token'];
        error_log("DEBUG: Cookie token found: " . substr($token, 0, 20) . "...");
        error_log("DEBUG: Token length: " . strlen($token) . " chars");
        
        // 1. Hash the cookie token to compare with database
        $hashed_token = hash('sha256', $token); // ← Hash it FIRST!
        error_log("DEBUG: Hashed cookie token: " . substr($hashed_token, 0, 20) . "...");
        
        try {
            // 2. Look for the HASHED token in database
            $query = "SELECT user_id FROM users 
                      WHERE remember_token = :token AND remember_expires > NOW()";
            
            $stmt = $_db->prepare($query);
            $stmt->bindParam(':token', $hashed_token); // ← Compare with HASHED version
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("DEBUG: Token found in DB for user " . $row['user_id']);
                
                // 3. Auto-login the user
                if (auto_login($row['user_id'])) {
                    error_log("DEBUG: Auto-login successful for user " . $row['user_id']);
                    return true;
                } else {
                    error_log("DEBUG: Auto-login failed for user " . $row['user_id']);
                }
            } else {
                error_log("DEBUG: Token NOT found in DB or expired");
                
                // Invalid token - clear the cookie
                setcookie('remember_token', '', time() - 3600, '/');
                error_log("DEBUG: Cleared invalid cookie");
                return false;
            }
            
        } catch(PDOException $exception) {
            error_log("Check remember token error: " . $exception->getMessage());
            return false;
        }
    } else {
        error_log("DEBUG: No cookie or already logged in - skipping");
    }
    return false;
}

function auto_login($user_id) {
    global $_db;
    
    try {
        $query = "SELECT user_id, username, email, role FROM users 
                  WHERE user_id = :user_id AND is_active = 1";
        
        $stmt = $_db->prepare($query); // ← Prepare first!
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['login_time'] = time();

            $_user = [
                'id' => $row['user_id'],
                'email' => $row['email'],
                'name' => $row['username'],
                'role' => $row['role']
            ];
            
            $_SESSION['user'] = $_user; // ← THIS IS WHAT auth() CHECKS!
            $GLOBALS['_user'] = $_user;
            
            update_last_login($user_id);
            return true;
        }
        return false;
        
    } catch(PDOException $exception) {
        error_log("Auto login error: " . $exception->getMessage());
        return false;
    }
}

// ============================================================================
// Email Verfication 
// ============================================================================
/**
 * Check if current user's email is verified
 * Admins are automatically considered verified
 */
function is_email_verified() {
    global $_user;
    
    // Not logged in
    if (!$_user) {
        return false;
    }
    
    // Admins are automatically verified
    if ($_user['role'] === 'admin') {
        return true;
    }
    
    // Check members in database
    global $_db;
    $stmt = $_db->prepare("SELECT email_verified FROM users WHERE user_id = ?");
    $stmt->execute([$_user['id']]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Require verified email (for members only)
 */
function require_verified_email() {
    if (!is_email_verified()) {
        // Save where they came from
        $_SESSION['redirect_after_verify'] = $_SERVER['REQUEST_URI'];
        header("Location: verify_required.php");
        exit();
    }
}

?>

