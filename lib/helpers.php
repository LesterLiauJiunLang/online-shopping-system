<?php
function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function setFlashMessage($message) {
    $_SESSION['flash_message'] = $message;
}
?>