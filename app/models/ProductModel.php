<?php
require_once __DIR__ . '/../config/database.php';

class Product {
    private $conn;

    public function __construct($conn = null) {
        if ($conn === null) {
            $this->conn = Database::connect();
        } else {
            $this->conn = $conn;
        }
    }

    public function getAllProducts() {
        $stmt = $this->conn->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN category c ON p.category_id = c.category_id 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $description, $price, $stock_quantity, $category_id = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO products (name, description, price, stock_quantity, category_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$name, $description, $price, $stock_quantity, $category_id]);
    }

    public function update($id, $name, $description, $price, $stock_quantity, $category_id = null) {
        $stmt = $this->conn->prepare("
            UPDATE products SET 
            name = ?, description = ?, price = ?, stock_quantity = ?, category_id = ?, updated_at = NOW()
            WHERE product_id = ?
        ");
        return $stmt->execute([$name, $description, $price, $stock_quantity, $category_id, $id]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM products WHERE product_id = ?");
        return $stmt->execute([$id]);
    }

    public function searchProducts($search_term, $category_id = null, $min_price = null, $max_price = null) {
        $query = "SELECT p.*, c.name as category_name FROM products p 
                  LEFT JOIN category c ON p.category_id = c.category_id 
                  WHERE 1=1";
        $params = [];
        
        if (!empty($search_term)) {
            $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search_term%";
            $params[] = "%$search_term%";
        }
        
        if (!empty($category_id)) {
            $query .= " AND p.category_id = ?";
            $params[] = $category_id;
        }
        
        if (!empty($min_price)) {
            $query .= " AND p.price >= ?";
            $params[] = $min_price;
        }
        
        if (!empty($max_price)) {
            $query .= " AND p.price <= ?";
            $params[] = $max_price;
        }
        
        $query .= " ORDER BY p.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - total products count
    public function getTotalProductsCount() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM products");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    // For admin dashboard - low stock products
    public function getLowStockProducts($threshold = 10) {
        $stmt = $this->conn->prepare("
            SELECT * FROM products 
            WHERE stock_quantity <= ? AND stock_quantity > 0 
            ORDER BY stock_quantity ASC
            LIMIT 10
        ");
        $stmt->bindValue(1, $threshold, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - out of stock products
    public function getOutOfStockProducts() {
        $stmt = $this->conn->prepare("
            SELECT * FROM products 
            WHERE stock_quantity = 0 
            ORDER BY name ASC
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For admin dashboard - top selling products
    public function getTopSellingProducts($limit = 5) {
        // Assuming you have an order_items table
        // If not, you might need to adjust this
        $stmt = $this->conn->prepare("
            SELECT p.product_id, p.name, p.price, SUM(oi.quantity) as total_sold
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            GROUP BY p.product_id
            ORDER BY total_sold DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>