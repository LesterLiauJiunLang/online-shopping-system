<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../models/UserModel.php';

class AuthController {
    private $user;

    public function __construct() {
    // Add database connection
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getConnection();
    $this->user = new User($db);
}

    public function login($value, $password) {
        $user = $this->user->findByEmailOrUsername($value);

        if (!$user) {
            return "Account not found.";
        }

        if (!password_verify($password, $user['password_hash'])) {
            return "Invalid password.";
        }

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        return "success";
    }

    public function register($username, $email, $password) {
    if ($this->user->emailExists($email)) {
        return "Email already exists.";
    }

    if ($this->user->usernameExists($username)) {
        return "Username already exists.";
    }

    // Create the user
    $success = $this->user->create($username, $email, $password);
    
    if ($success) {
        // Automatically log in the user after registration
        $user = $this->user->findByEmailOrUsername($email);
        
        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            return "success";
        }
    }
    
    return "Registration failed. Please try again.";
}

    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}
