<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../models/UserModel.php';

class AuthController {
    private $user;

    public function __construct() {
        $this->user = new User();
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

        $this->user->create($username, $email, $password);
        return "success";
    }

    public function logout() {
        session_destroy();
        header("Location: login.php");
        exit;
    }

    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}
