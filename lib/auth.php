<?php
class Auth {
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header("Location: dashboard.php");
            exit();
        }
    }
}
?>