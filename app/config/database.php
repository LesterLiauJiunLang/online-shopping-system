<?php
require_once __DIR__ . '/../../lib/base.php'; 
$_db = new PDO("mysql:host=localhost;dbname=OnlineShoppingSystem", "root", "");
$_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$_db->exec("set names utf8");
?>