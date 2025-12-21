<?php

// Base URL - adjust this based on your setup
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/online-shopping-system/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('CSS_URL', ASSETS_URL . 'css/');
define('JS_URL', ASSETS_URL . 'js/');
define('IMG_URL', ASSETS_URL . 'images/');

// File upload configuration
define('UPLOAD_BASE_PATH', __DIR__ . '/../../uploads/');
define('UPLOAD_PROFILES_PATH', UPLOAD_BASE_PATH . 'profiles/');
define('UPLOAD_PRODUCTS_PATH', UPLOAD_BASE_PATH . 'products/');

// URL paths for accessing uploaded files
define('UPLOAD_PROFILES_URL', '/online-shopping-system/uploads/profiles/');
define('UPLOAD_PRODUCTS_URL', '/online-shopping-system/uploads/products/');

// File size limits
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
?>