<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;

    public function __construct($conn = null) {
        if ($conn === null) {
            $this->conn = Database::connect();
        } else {
            $this->conn = $conn;
        }
    }

    public function findByEmailOrUsername($value) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM users WHERE email = ? OR username = ?"
        );
        $stmt->execute([$value, $value]);
        return $stmt->fetch();
    }

    public function create($username, $email, $password, $role = 'customer') {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare(
            "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)"
        );

        return $stmt->execute([$username, $email, $hash, $role]);
    }

    public function emailExists($email) {
        $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    }

    public function usernameExists($username) {
        $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->rowCount() > 0;
    }

    public function getUserById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - total customers count
    public function getTotalCustomersCount() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    // For admin dashboard - get all users
    public function getAllUsers($limit = 100) {
        $stmt = $this->conn->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - get recent customers
    public function getRecentCustomers($limit = 5) {
        $stmt = $this->conn->prepare("
            SELECT * FROM users 
            WHERE role = 'customer' 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>