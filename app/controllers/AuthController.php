<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../lib/base.php';

class AuthController {
    private $user;

public function __construct() {
        global $_db;  // Use existing connection from base.php
        require_once __DIR__ . '/../../app/models/UserModel.php';
        $this->user = new User($_db);
    }

    public function login($value, $password) {  
    $user = $this->user->findByEmailOrUsername($value);

    if (!$user) {
        return "Account not found.";
    }

    if (!password_verify($password, $user['password_hash'])) {
        return "Invalid password.";
    }

    // Set old system variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true; 

    // ALSO set new system variables (Practical 7 style)
    $_SESSION['user'] = [
        'id' => $user['user_id'],
        'email' => $user['email'],
        'name' => $user['username'],
        'role' => $user['role']
    ];

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
