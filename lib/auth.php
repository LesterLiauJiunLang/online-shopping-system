<?php
require_once '../config/database.php';

class Auth {
    private $conn;
    private $table_name = "users";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Login user
    public function login($username, $password) {
        try {
            $query = "SELECT user_id, username, email, password_hash, role, is_active, login_attempts 
                      FROM " . $this->table_name . " 
                      WHERE username = :username OR email = :username";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if account is active
                if (!$row['is_active']) {
                    return "Account is deactivated. Please contact administrator.";
                }

                // Check login attempts (max 3 attempts)
                if ($row['login_attempts'] >= 3) {
                    return "Account temporarily locked. Please reset your password.";
                }

                // Verify password
                if (password_verify($password, $row['password_hash'])) {
                    // Reset login attempts on successful login
                    $this->resetLoginAttempts($row['user_id']);
                    
                    // Update last login
                    $this->updateLastLogin($row['user_id']);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['logged_in'] = true;

                    return "success";
                } else {
                    // Increment login attempts
                    $this->incrementLoginAttempts($row['user_id']);
                    $attempts_left = 2 - $row['login_attempts'];
                    return "Invalid password. " . $attempts_left . " attempts left.";
                }
            }
            return "Username/Email not found.";
            
        } catch(PDOException $exception) {
            return "Database error: " . $exception->getMessage();
        }
    }

    // Register new user
    public function register($username, $email, $password, $role = 'member') {
        try {
            // Check if username or email already exists
            $query = "SELECT user_id FROM " . $this->table_name . " 
                      WHERE username = :username OR email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return "Username or Email already exists.";
            }

            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $query = "INSERT INTO " . $this->table_name . " 
                      (username, email, password_hash, role) 
                      VALUES (:username, :email, :password_hash, :role)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':role', $role);

            if ($stmt->execute()) {
                return "success";
            } else {
                return "Registration failed. Please try again.";
            }
            
        } catch(PDOException $exception) {
            return "Database error: " . $exception->getMessage();
        }
    }

    // Password reset request
    public function createPasswordReset($email) {
        try {
            $query = "SELECT user_id FROM " . $this->table_name . " WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $row['user_id'];
                
                // Generate unique token
                $token = bin2hex(random_bytes(50));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Insert into password_resets table
                $query = "INSERT INTO password_resets (user_id, reset_token, expires_at) 
                          VALUES (:user_id, :token, :expires)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':expires', $expires);
                
                if ($stmt->execute()) {
                    return $token; // In real application, you would email this token
                }
            }
            return false;
            
        } catch(PDOException $exception) {
            return false;
        }
    }

    // Reset password with token
    public function resetPassword($token, $new_password) {
        try {
            $query = "SELECT pr.user_id, pr.expires_at 
                      FROM password_resets pr 
                      WHERE pr.reset_token = :token AND pr.is_used = 0 AND pr.expires_at > NOW()";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $row['user_id'];
                
                // Hash new password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user password
                $query = "UPDATE " . $this->table_name . " 
                          SET password_hash = :password_hash, login_attempts = 0 
                          WHERE user_id = :user_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':user_id', $user_id);
                
                if ($stmt->execute()) {
                    // Mark token as used
                    $query = "UPDATE password_resets SET is_used = 1 WHERE reset_token = :token";
                    $stmt = $this->conn->prepare($query);
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

    // Helper methods
    private function incrementLoginAttempts($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET login_attempts = login_attempts + 1 
                  WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }

    private function resetLoginAttempts($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET login_attempts = 0 
                  WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }

    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET last_login = NOW() 
                  WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }

    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    // Check if user is admin
    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['role'] === 'admin';
    }

    // Redirect if not logged in
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }

    // Redirect if not admin
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header("Location: ../dashboard.php");
            exit();
        }
    }

    // Logout user
    public function logout() {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
}
?>