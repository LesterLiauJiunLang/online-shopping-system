<?php
require_once __DIR__ . '/../config/database.php';

class Order {
    private $conn;

    public function __construct($conn = null) {
    if ($conn === null) {
        global $_db;  // Use existing connection
        $this->conn = $_db;
    } else {
        $this->conn = $conn;
    }
}

    // For customer dashboard
    public function getOrdersByUser($userId) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - recent orders
    public function getRecentOrdersByUser($userId, $limit = 5) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - all recent orders
    public function getRecentOrders($limit = 10) {
    try {
        // First check what columns exist in the orders table
        $checkColumns = $this->conn->query("SHOW COLUMNS FROM orders")->fetchAll();
        $columns = array_column($checkColumns, 'Field');
        
        // Determine which date column to use
        $dateColumn = in_array('created_at', $columns) ? 'created_at' : 'order_date';
        
        // Check if users table has username or name column
        $userColumns = $this->conn->query("SHOW COLUMNS FROM users")->fetchAll();
        $userFields = array_column($userColumns, 'Field');
        $userNameColumn = in_array('username', $userFields) ? 'username' : 
                         (in_array('name', $userFields) ? 'name' : 'email');
        
        // Build the query
        $sql = "
            SELECT 
                o.*, 
                u.{$userNameColumn} as customer_name, 
                u.email 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.user_id 
            ORDER BY o.{$dateColumn} ASC 
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getRecentOrders: " . $e->getMessage());
        return [];
    }
}
    
    // For admin dashboard - total orders count
public function getTotalOrdersCount() {
    try {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM orders");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error in getTotalOrdersCount: " . $e->getMessage());
        return 0;
    }
}
    
    // For admin dashboard - total revenue
    public function getTotalRevenue() {
        $stmt = $this->conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }
    
    // For admin dashboard - orders by status
    public function getOrdersByStatus($status) {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - monthly revenue
    public function getMonthlyRevenue() {
        $stmt = $this->conn->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(total_amount) as revenue
            FROM orders 
            WHERE status IN ('completed')
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 6
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>