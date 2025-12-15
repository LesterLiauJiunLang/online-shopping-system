<?php
// app/config/database.php

class Database {
    private static $connection = null;
    
    public static function connect() {
        if (self::$connection === null) {
            try {
                // Change 'your_database_name' to your actual database name
                self::$connection = new PDO(
                    "mysql:host=localhost;dbname=OnlineShoppingSystem;charset=utf8mb4",
                    "root",     // XAMPP default username
                    ""          // XAMPP default password (empty)
                );
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
    
    public static function getConnection() {
        return self::connect();
    }
}