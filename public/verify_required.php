<?php
session_start();
require_once '../lib/base.php';
require_once '../lib/auth.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

// This page is deprecated - redirect to dashboard with popup
$_SESSION['force_verify_popup'] = true;
header("Location: dashboard.php");
exit();
?>