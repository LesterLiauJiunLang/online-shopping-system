<?php
// support_details.php - Updated with profile editing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include base.php which contains all our functions
require_once __DIR__ . '/../../lib/base.php';
require_once __DIR__ . '/../../lib/auth.php';

// Check if user is admin
require_login();
$user_role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'member';

if ($user_role !== 'admin') {
    redirect('../memberDashboard.php');
}

// Check if agent ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('manage_support.php?error=No agent specified');
}

$agentId = (int)$_GET['id'];
$agent = get_user_by_id($agentId);

// Check if agent exists and is actually a support agent
if (!$agent || $agent['role'] !== 'support') {
    redirect('manage_support.php?error=Support agent not found');
}

// Get current user
$currentUser = get_user_by_id($_SESSION['user_id']);

// === HANDLE PROFILE AND PASSWORD UPDATES ===
$profileMessage = '';
$profileError = '';
$passwordMessage = '';
$passwordError = '';

// Database connection
require_once __DIR__ . '/../../app/config/database.php';
$db = db();

// Handle Profile Information Update
if (isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $employeeId = trim($_POST['employee_id'] ?? '');
    
    // Validate inputs
    if (empty($newUsername) || empty($newEmail)) {
        $profileError = "Username and email are required.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $profileError = "Please enter a valid email address.";
    } else {
        try {
            // Check if username already exists (excluding current agent)
            $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$newUsername, $agentId]);
            if ($stmt->fetch()) {
                $profileError = "Username already exists.";
            }
            
            // Check if email already exists (excluding current agent)
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$newEmail, $agentId]);
            if ($stmt->fetch()) {
                $profileError = "Email already exists.";
            }
            
            // If no errors, update profile
            if (empty($profileError)) {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, employee_id = ?, updated_at = NOW() WHERE user_id = ?");
                if ($stmt->execute([$newUsername, $newEmail, $employeeId, $agentId])) {
                    // Refresh agent data
                    $agent = get_user_by_id($agentId);
                    $profileMessage = "Agent profile updated successfully!";
                } else {
                    $profileError = "Failed to update profile. Please try again.";
                }
            }
        } catch (Exception $e) {
            $profileError = "Database error: " . $e->getMessage();
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($newPassword) || empty($confirmPassword)) {
        $passwordError = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = "New passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $passwordError = "New password must be at least 6 characters long.";
    } else {
        try {
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password in database
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
            if ($stmt->execute([$hashedPassword, $agentId])) {
                $passwordMessage = "Agent password changed successfully!";
                // Clear the password fields
                $_POST['new_password'] = $_POST['confirm_password'] = '';
            } else {
                $passwordError = "Failed to change password. Please try again.";
            }
        } catch (Exception $e) {
            $passwordError = "Database error: " . $e->getMessage();
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

// Pagination for activities
$activityLimit = 5;
$activityPage = isset($_GET['activity_page']) ? (int)$_GET['activity_page'] : 1;
$activityOffset = ($activityPage - 1) * $activityLimit;

// Get agent statistics
$ticketStats = get_support_agent_stats($agentId);
$totalActivities = count_user_activities($agentId);  // Use the new function
$activityPages = ceil($totalActivities / $activityLimit);
$activities = get_user_activities_paginated($agentId, $activityLimit, $activityOffset);

// Get recent tickets assigned to this agent
$recentTickets = get_agent_recent_tickets($agentId, 5);

// Generate CSRF token for security
$csrf_token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo encode($agent['username']); ?> - Support Agent Details</title>
    
    <!-- Include CSS files - NO BOOTSTRAP -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/users.css">
    <link rel="stylesheet" href="../../assets/css/notifications.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    
    <style>
    /* Custom CSS to replace Bootstrap styles */
    .admin-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .admin-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .header-content h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
    }
    
    .header-subtitle {
        margin: 5px 0 0;
        opacity: 0.9;
        font-size: 16px;
    }
    
    .header-actions {
        margin-top: 10px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .btn, .btn-back {
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        font-size: 16px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        border: none;
    }
    
    .btn-back {
        background: #f8f9fa;
        color: #495057;
        border: 1px solid #dee2e6;
    }
    
    .btn-back:hover {
        background: #e9ecef;
        color: #212529;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .btn-primary {
        background: #4299e1;
        color: white;
        border: 1px solid #4299e1;
    }
    
    .btn-primary:hover {
        background: #3182ce;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
        border: 1px solid #6c757d;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }
    
    /* Edit Profile and Change Password Buttons */
    .btn-edit-profile {
        background: linear-gradient(135deg, #48bb78, #38a169);
        color: white;
        border: none;
    }
    
    .btn-edit-profile:hover {
        background: linear-gradient(135deg, #38a169, #2f855a);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
    }
    
    .btn-change-password {
        background: linear-gradient(135deg, #ed8936, #dd6b20);
        color: white;
        border: none;
    }
    
    .btn-change-password:hover {
        background: linear-gradient(135deg, #dd6b20, #c05621);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(237, 137, 54, 0.3);
    }
    
    /* Profile Edit Section */
    .profile-edit-section {
        display: none;
        margin: 2rem 0;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border-left: 4px solid #48bb78;
        animation: slideDown 0.3s ease-out;
    }
    
    .password-change-section {
        display: none;
        margin: 2rem 0;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border-left: 4px solid #ed8936;
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Form Styles */
    .profile-form, .password-form {
        margin-top: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #4a5568;
        font-size: 0.95em;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: #f7fafc;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #4299e1;
        background: white;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }
    
    .form-buttons {
        display: flex;
        gap: 12px;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
    }
    
    /* Alert Messages */
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }
    
    .alert-success {
        background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
        color: #22543d;
        border-left: 4px solid #38a169;
    }
    
    .alert-error {
        background: linear-gradient(135deg, #fed7d7, #fc8181);
        color: #742a2a;
        border-left: 4px solid #e53e3e;
    }
    
    .alert i {
        font-size: 1.2em;
    }
    
    .form-text {
        display: block;
        margin-top: 6px;
        font-size: 0.85em;
        color: #718096;
    }
    
    /* Member Info Section */
    .member-info-cell {
        background: white;
        border-radius: 10px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .member-info {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .large-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .large-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #e2e8f0, #cbd5e0);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #718096;
        font-size: 40px;
    }
    
    .member-details {
        flex: 1;
        min-width: 0;
    }
    
    .member-name {
        margin: 0 0 10px;
        color: #2d3748;
        font-weight: 600;
        font-size: 1.8em;
    }
    
    .member-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .member-id {
        background: linear-gradient(135deg, #4299e1, #3182ce);
        color: white;
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    
    .member-joined {
        color: #718096;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #f7fafc;
        border-radius: 20px;
        font-size: 14px;
    }
    
    .member-status {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .status-badge.active {
        background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
        color: #22543d;
    }
    
    .status-badge.inactive {
        background: linear-gradient(135deg, #fed7d7, #fc8181);
        color: #742a2a;
    }
    
    .verification-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .verification-badge.verified {
        background: linear-gradient(135deg, #bee3f8, #90cdf4);
        color: #2c5282;
    }
    
    .verification-badge.pending {
        background: linear-gradient(135deg, #feebc8, #fbd38d);
        color: #744210;
    }
    
    /* Stats Overview */
    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-total { background: linear-gradient(135deg, #4299e1, #3182ce); }
    .stat-open { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-resolved { background: linear-gradient(135deg, #48bb78, #38a169); }
    .stat-activities { background: linear-gradient(135deg, #9f7aea, #805ad5); }
    
    .stat-content h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
    }
    
    .stat-content p {
        margin: 5px 0 0;
        color: #718096;
        font-size: 14px;
    }
    
    /* Tab Navigation */
    .tab-navigation {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        background: white;
        padding: 10px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        flex-wrap: wrap;
    }
    
    .tab-btn {
        padding: 12px 24px;
        border: none;
        background: #f7fafc;
        color: #4a5568;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 200px;
        justify-content: center;
    }
    
    .tab-btn:hover {
        background: #edf2f7;
        color: #2d3748;
    }
    
    .tab-btn.active {
        background: linear-gradient(135deg, #4299e1, #3182ce);
        color: white;
        box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
    }
    
    .tab-content {
        display: none;
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* Info Grid */
    .info-section {
        margin-bottom: 30px;
    }
    
    .info-section h4 {
        margin: 0 0 20px;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #4299e1;
    }
    
    .info-label {
        color: #718096;
        font-size: 14px;
        font-weight: 500;
    }
    
    .info-value {
        color: #2d3748;
        font-size: 16px;
        font-weight: 500;
    }
    
    .info-value a {
        color: #4299e1;
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .info-value a:hover {
        color: #3182ce;
        text-decoration: underline;
    }
    
    /* Table Styles */
    .table-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .table-header h3 {
        margin: 0;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
    }
    
    .total-count {
        color: #718096;
        font-size: 14px;
        background: #f7fafc;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 500;
    }
    
    .table-responsive {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    .members-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }
    
    .members-table th {
        background: #f7fafc;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
        font-size: 14px;
    }
    
    .members-table td {
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
        font-size: 14px;
    }
    
    .members-table tr:hover {
        background: #f7fafc;
    }
    
    .members-table tr:last-child td {
        border-bottom: none;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #718096;
    }
    
    .empty-icon {
        font-size: 64px;
        color: #cbd5e0;
        margin-bottom: 20px;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        margin: 0 0 10px;
        color: #4a5568;
        font-size: 1.2em;
    }
    
    .empty-state p {
        margin: 0 0 20px;
        color: #a0aec0;
    }
    
    /* Pagination */
    .pagination-section {
        background: #f7fafc;
        border-radius: 8px;
        padding: 25px 30px;
        margin-top: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .pagination-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: white;
        color: #4a5568;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }
    
    .pagination-btn:hover {
        background: #edf2f7;
        color: #2d3748;
        text-decoration: none;
        border-color: #cbd5e0;
    }
    
    .pagination-btn:disabled,
    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f7fafc;
    }
    
    .page-numbers {
        display: flex;
        gap: 6px;
        margin: 0 15px;
        flex-wrap: wrap;
    }
    
    .page-numbers a {
        min-width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        color: #4a5568;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        font-size: 14px;
    }
    
    .page-numbers a:hover {
        background: #edf2f7;
        color: #2d3748;
        text-decoration: none;
        border-color: #cbd5e0;
    }
    
    .page-numbers a.active {
        background: linear-gradient(135deg, #4299e1, #3182ce);
        color: white;
        border-color: #4299e1;
        box-shadow: 0 4px 8px rgba(66, 153, 225, 0.2);
    }
    
    .page-dots {
        color: #a0aec0;
        display: flex;
        align-items: center;
        padding: 0 10px;
    }
    
    .pagination-info {
        color: #4a5568;
        font-weight: 500;
        font-size: 14px;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .btn-sm {
        padding: 8px 16px;
        font-size: 14px;
    }
    
    .btn-success {
        background: linear-gradient(135deg, #48bb78, #38a169);
        color: white;
        border: none;
    }
    
    .btn-success:hover {
        background: linear-gradient(135deg, #38a169, #2f855a);
    }
    
    .btn-warning {
        background: linear-gradient(135deg, #ed8936, #dd6b20);
        color: white;
        border: none;
    }
    
    .btn-warning:hover {
        background: linear-gradient(135deg, #dd6b20, #c05621);
    }
    
    .btn-info {
        background: linear-gradient(135deg, #4299e1, #3182ce);
        color: white;
        border: none;
    }
    
    .btn-info:hover {
        background: linear-gradient(135deg, #3182ce, #2c5282);
    }
    
    /* Section Headers */
    .section-title {
        font-size: 1.5em;
        color: #2d3748;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        color: #4299e1;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .admin-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }
        
        .header-actions {
            width: 100%;
            justify-content: flex-start;
        }
        
        .member-info {
            flex-direction: column;
            text-align: center;
        }
        
        .member-details {
            width: 100%;
        }
        
        .member-name {
            text-align: center;
        }
        
        .member-status {
            justify-content: center;
        }
        
        .tab-btn {
            min-width: 100%;
        }
        
        .tab-navigation {
            flex-direction: column;
        }
        
        .form-buttons {
            flex-direction: column;
        }
        
        .form-buttons .btn {
            width: 100%;
            justify-content: center;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-overview {
            grid-template-columns: 1fr;
        }
        
        .table-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .pagination-section {
            flex-direction: column;
            text-align: center;
        }
        
        .pagination-controls {
            justify-content: center;
        }
        
        .page-numbers {
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .admin-container {
            padding: 10px;
        }
        
        .member-info-cell {
            padding: 20px;
        }
        
        .tab-content {
            padding: 20px;
        }
        
        .table-section {
            padding: 15px;
        }
        
        .members-table th,
        .members-table td {
            padding: 10px;
            font-size: 12px;
        }
    }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="ajax-loading" id="ajaxLoading">
        <div class="spinner"></div>
    </div>
    
    <div class="admin-container">
        <!-- Page Header -->
        <div class="admin-header">
            <div class="header-content">
                <h1>
                    <i class="fas fa-headset"></i>
                    Support Agent Details
                </h1>
                <p class="header-subtitle">View and manage support agent information and activities</p>
            </div>
            <div class="header-actions">
                <a href="manage_support.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Agents List
                </a>
                <button type="button" class="btn btn-edit-profile" onclick="toggleProfileEdit()">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </button>
                <button type="button" class="btn btn-change-password" onclick="togglePasswordChange()">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </div>

        <!-- Edit Profile Form (Hidden by default) -->
        <?php if ($profileMessage || $profileError): ?>
        <div class="profile-edit-section" id="profileEditSection" style="display: block;">
        <?php else: ?>
        <div class="profile-edit-section" id="profileEditSection">
        <?php endif; ?>
            <h3 class="section-title">
                <i class="fas fa-user-edit"></i> Edit Agent Profile
            </h3>
            
            <?php if ($profileMessage): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($profileMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($profileError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($profileError); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($agent['username']); ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($agent['email']); ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="employee_id">Employee ID</label>
                    <input type="text" id="employee_id" name="employee_id" 
                           value="<?php echo htmlspecialchars($agent['employee_id'] ?? ''); ?>" 
                           class="form-control" placeholder="Optional employee ID">
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                    <button type="button" onclick="toggleProfileEdit()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Change Password Form (Hidden by default) -->
        <?php if ($passwordMessage || $passwordError): ?>
        <div class="password-change-section" id="passwordChangeSection" style="display: block;">
        <?php else: ?>
        <div class="password-change-section" id="passwordChangeSection">
        <?php endif; ?>
            <h3 class="section-title">
                <i class="fas fa-key"></i> Change Agent Password
            </h3>
            
            <?php if ($passwordMessage): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($passwordMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($passwordError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($passwordError); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="password-form">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" 
                           class="form-control" required minlength="6">
                    <small class="form-text">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="form-control" required minlength="6">
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-save"></i> Change Password
                    </button>
                    <button type="button" onclick="togglePasswordChange()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Agent Profile Header -->
        <div class="member-info-cell">
            <div class="member-info">
                <div class="large-avatar">
                    <?php if (!empty($agent['profile_photo'])): ?>
                        <img src="../<?php echo encode($agent['profile_photo']); ?>" 
                             alt="<?php echo encode($agent['username']); ?>">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="member-details">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                        <div>
                            <h4 class="member-name"><?php echo encode($agent['username']); ?></h4>
                            <div class="member-meta">
                                <span class="member-id">ID: #<?php echo $agent['user_id']; ?></span>
                                <span class="member-joined">
                                    <i class="fas fa-id-card"></i> Employee ID: <?php echo encode($agent['employee_id'] ?? 'Not assigned'); ?>
                                </span>
                            </div>
                            <div class="member-status">
                                <?php if ($agent['is_active']): ?>
                                    <span class="status-badge active">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Active Account</span>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge inactive">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Inactive Account</span>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($agent['email_verified']): ?>
                                    <span class="verification-badge verified">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Email Verified</span>
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge pending">
                                        <i class="fas fa-clock"></i>
                                        <span>Email Pending</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <a href="manage_support.php?action=toggle_status&id=<?php echo $agent['user_id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                               class="btn btn-sm <?php echo $agent['is_active'] ? 'btn-warning' : 'btn-success'; ?> confirm-action"
                               data-confirm-message="Are you sure you want to <?php echo $agent['is_active'] ? 'deactivate' : 'activate'; ?> this agent?"
                               data-action="<?php echo $agent['is_active'] ? 'deactivate' : 'activate'; ?>">
                                <i class="fas fa-<?php echo $agent['is_active'] ? 'ban' : 'check'; ?>"></i>
                                <span><?php echo $agent['is_active'] ? 'Deactivate Agent' : 'Activate Agent'; ?></span>
                            </a>
                            <a href="../admin/support_chat.php?agent_id=<?php echo $agent['user_id']; ?>" 
                               class="btn btn-sm btn-info">
                                <i class="fas fa-comment"></i>
                                <span>Send Message</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon stat-total">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $ticketStats['total_tickets']; ?></h3>
                    <p>Total Tickets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-open">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $ticketStats['open_tickets']; ?></h3>
                    <p>Open Tickets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $ticketStats['resolved_tickets']; ?></h3>
                    <p>Resolved Tickets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-activities">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $totalActivities; ?></h3>
                    <p>Recent Activities</p>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" data-tab="info">
                <i class="fas fa-info-circle"></i> Agent Information
            </button>
            <button class="tab-btn" data-tab="activities">
                <i class="fas fa-history"></i> Recent Activities
            </button>
            <button class="tab-btn" data-tab="tickets">
                <i class="fas fa-ticket-alt"></i> Assigned Tickets
            </button>
        </div>

        <!-- Agent Information Tab -->
        <div class="tab-content active" id="info-tab">
            <div class="info-section">
                <h4><i class="fas fa-user-circle"></i> Personal Information</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo encode($agent['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <span class="info-value">
                            <a href="mailto:<?php echo encode($agent['email']); ?>" style="color: #4299e1; text-decoration: none;">
                                <i class="fas fa-envelope"></i> <?php echo encode($agent['email']); ?>
                            </a>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Employee ID</span>
                        <span class="info-value">
                            <?php if (!empty($agent['employee_id'])): ?>
                                <span class="member-id" style="display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-id-card"></i> <?php echo encode($agent['employee_id']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #a0aec0; font-style: italic;">Not assigned</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Status</span>
                        <span class="info-value">
                            <?php if ($agent['is_active']): ?>
                                <span class="status-badge active">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Active</span>
                                </span>
                            <?php else: ?>
                                <span class="status-badge inactive">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Inactive</span>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Verification</span>
                        <span class="info-value">
                            <?php if ($agent['email_verified']): ?>
                                <span class="verification-badge verified">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Verified</span>
                                </span>
                            <?php else: ?>
                                <span class="verification-badge pending">
                                    <i class="fas fa-clock"></i>
                                    <span>Pending</span>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Created</span>
                        <span class="info-value">
                            <i class="fas fa-calendar-plus"></i>
                            <?php echo date('F d, Y', strtotime($agent['created_at'])); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Login</span>
                        <span class="info-value">
                            <i class="fas fa-sign-in-alt"></i>
                            <?php if ($agent['last_login']): ?>
                                <?php echo date('F d, Y h:i A', strtotime($agent['last_login'])); ?>
                            <?php else: ?>
                                <span style="color: #a0aec0; font-style: italic;">Never logged in</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities Tab -->
        <div class="tab-content" id="activities-tab">
            <div class="table-section">
                <div class="table-header">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                    <div class="table-actions">
                        <span class="total-count">
                            <?php echo $totalActivities; ?> total activities
                        </span>
                    </div>
                </div>

                <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>No Recent Activities</h3>
                        <p>This agent hasn't performed any activities yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="members-table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Type</th>
                                    <th>IP Address</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td class="member-info-cell">
                                        <div class="member-info">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #f7fafc; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                                <?php
                                                $icon = 'fa-history';
                                                $color = '#4299e1';
                                                if (strpos($activity['activity_type'], 'login') !== false) {
                                                    $icon = 'fa-sign-in-alt';
                                                    $color = '#48bb78';
                                                } elseif (strpos($activity['activity_type'], 'update') !== false) {
                                                    $icon = 'fa-edit';
                                                    $color = '#ed8936';
                                                } elseif (strpos($activity['activity_type'], 'create') !== false) {
                                                    $icon = 'fa-plus';
                                                    $color = '#48bb78';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i>
                                            </div>
                                            <div class="member-details">
                                                <h4 class="member-name" style="font-size: 14px; margin-bottom: 5px;">
                                                    <?php echo encode($activity['description']); ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0;">
                                            <?php echo encode($activity['activity_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="email-info">
                                            <i class="fas fa-globe"></i>
                                            <span style="font-family: monospace; font-size: 12px;"><?php echo encode($activity['ip_address']); ?></span>
                                        </div>
                                    </td>
                                    <td class="activity-cell">
                                        <div class="last-login">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>
                                                <?php echo date('M d, Y', strtotime($activity['created_at'])); ?><br>
                                                <small style="color: #a0aec0;"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></small>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Activities Pagination -->
                    <?php if ($activityPages > 1): ?>
                        <div class="pagination-section">
                            <div class="pagination-info">
                                Page <?php echo $activityPage; ?> of <?php echo $activityPages; ?>
                            </div>
                            <div class="pagination-controls">
                                <?php if ($activityPage > 1): ?>
                                    <a href="support_details.php?id=<?php echo $agentId; ?>&activity_page=1" 
                                       class="pagination-btn first" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="support_details.php?id=<?php echo $agentId; ?>&activity_page=<?php echo $activityPage - 1; ?>" 
                                       class="pagination-btn prev" title="Previous Page">
                                        <i class="fas fa-angle-left"></i>
                                        <span>Previous</span>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php 
                                    $start_page = max(1, $activityPage - 2);
                                    $end_page = min($activityPages, $activityPage + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <a href="support_details.php?id=<?php echo $agentId; ?>&activity_page=1">1</a>
                                        <?php if ($start_page > 2): ?>
                                            <span class="page-dots">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <a href="support_details.php?id=<?php echo $agentId; ?>&activity_page=<?php echo $i; ?>" 
                                           class="<?php echo $i == $activityPage ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $activityPages): ?>
                                        <?php if ($end_page < $activityPages - 1): ?>
                                            <span class="page-dots">...</span>
                                        <?php endif; ?>
                                        <a href="support_details.php?id=<?php echo $agentId; ?>&activity_page=<?php echo $activityPages; ?>">
                                            <?php echo $activityPages; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($activityPage < $activityPages): ?>
                                    <a href="support_details.php?id=<?php echo $agentId; ?>&activity_page=<?php echo $activityPage + 1; ?>" 
                                       class="pagination-btn next" title="Next Page">
                                        <span>Next</span>
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="support_details.php?id=<?php echo $agentId; ?>&activity_page=<?php echo $activityPages; ?>" 
                                       class="pagination-btn last" title="Last Page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned Tickets Tab -->
        <div class="tab-content" id="tickets-tab">
            <div class="table-section">
                <div class="table-header">
                    <h3><i class="fas fa-ticket-alt"></i> Assigned Tickets</h3>
                    <div class="table-actions">
                        <span class="total-count">
                            <?php echo $ticketStats['total_tickets']; ?> total tickets
                        </span>
                    </div>
                </div>

                <?php if (empty($recentTickets)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h3>No Assigned Tickets</h3>
                        <p>No tickets have been assigned to this agent yet.</p>
                        <a href="../support/tickets.php" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i> View All Tickets
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="members-table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Customer</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTickets as $ticket): ?>
                                <tr>
                                    <td class="member-info-cell">
                                        <div class="member-info">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #f7fafc; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                                <i class="fas fa-ticket-alt" style="color: #4299e1;"></i>
                                            </div>
                                            <div class="member-details">
                                                <h4 class="member-name" style="font-size: 14px; margin-bottom: 5px;">
                                                    <a href="../support/view_ticket.php?id=<?php echo $ticket['ticket_id']; ?>" 
                                                       class="text-decoration-none" style="color: #2d3748;">
                                                        #<?php echo $ticket['ticket_id']; ?>
                                                    </a>
                                                </h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="email-info">
                                            <i class="fas fa-user"></i>
                                            <span><?php echo encode($ticket['customer_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="email-info">
                                            <i class="fas fa-file-alt"></i>
                                            <span style="max-width: 200px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo encode($ticket['subject']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'open' => 'warning',
                                            'in_progress' => 'info',
                                            'resolved' => 'success',
                                            'closed' => 'secondary'
                                        ];
                                        $color = $statusColors[strtolower($ticket['status'])] ?? 'secondary';
                                        ?>
                                        <?php if ($color === 'warning'): ?>
                                            <span class="status-badge" style="background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2);">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo ucfirst($ticket['status']); ?></span>
                                            </span>
                                        <?php elseif ($color === 'info'): ?>
                                            <span class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: #4299e1; border: 1px solid rgba(59, 130, 246, 0.2);">
                                                <i class="fas fa-spinner"></i>
                                                <span><?php echo ucfirst($ticket['status']); ?></span>
                                            </span>
                                        <?php elseif ($color === 'success'): ?>
                                            <span class="status-badge active">
                                                <i class="fas fa-check-circle"></i>
                                                <span><?php echo ucfirst($ticket['status']); ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background: #f7fafc; color: #4a5568; border: 1px solid #e2e8f0;">
                                                <i class="fas fa-times-circle"></i>
                                                <span><?php echo ucfirst($ticket['status']); ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="activity-cell">
                                        <div class="last-login">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span><?php echo date('M d, Y', strtotime($ticket['updated_at'])); ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-section" style="background: #f7fafc; border-top: 1px solid #e2e8f0; padding: 20px 30px; text-align: center;">
                        <a href="../support/tickets.php?agent=<?php echo $agentId; ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-external-link-alt" style="margin-right: 8px;"></i>View All Tickets
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- jQuery Library (REQUIRED) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Your custom JS file -->
    <script src="../../assets/js/notifications.js"></script>
    
    <script>
    // jQuery event handling (REQUIRED by assignment)
    $(document).ready(function() {
        console.log('Support Agent Details page loaded');
        
        // Tab functionality with jQuery
        $('.tab-btn').click(function() {
            const tabId = $(this).data('tab');
            
            // Remove active class from all buttons and contents
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            
            // Add active class to clicked button and corresponding content
            $(this).addClass('active');
            $('#' + tabId + '-tab').addClass('active');
        });
        
        // Confirm actions with jQuery (replaces inline onclick)
        $(document).on('click', '.confirm-action', function(e) {
            e.preventDefault();
            var message = $(this).data('confirm-message');
            var url = $(this).attr('href');
            
            if (confirm(message)) {
                window.location.href = url;
            }
            return false;
        });
        
        // Handle pagination clicks with loading indicator
        $('.pagination-btn, .page-numbers a').click(function() {
            $('#ajaxLoading').fadeIn();
        });
        
        // Show profile edit section if there's a message
        <?php if ($profileMessage || $profileError): ?>
            $('#profileEditSection').show();
        <?php endif; ?>
        
        // Show password change section if there's a message
        <?php if ($passwordMessage || $passwordError): ?>
            $('#passwordChangeSection').show();
        <?php endif; ?>
    });
    
    // Toggle profile edit section
    function toggleProfileEdit() {
        const profileSection = $('#profileEditSection');
        const passwordSection = $('#passwordChangeSection');
        
        if (profileSection.is(':visible')) {
            // If profile section is visible, hide it
            profileSection.slideUp(300);
        } else {
            // Hide password section if open
            passwordSection.slideUp(300);
            // Show profile section with animation
            profileSection.slideDown(300, function() {
                // Scroll to profile section
                $('html, body').animate({
                    scrollTop: profileSection.offset().top - 100
                }, 500);
                // Focus on first input
                profileSection.find('input').first().focus();
            });
        }
    }
    
    // Toggle password change section
    function togglePasswordChange() {
        const profileSection = $('#profileEditSection');
        const passwordSection = $('#passwordChangeSection');
        
        if (passwordSection.is(':visible')) {
            // If password section is visible, hide it
            passwordSection.slideUp(300);
        } else {
            // Hide profile section if open
            profileSection.slideUp(300);
            // Show password section with animation
            passwordSection.slideDown(300, function() {
                // Scroll to password section
                $('html, body').animate({
                    scrollTop: passwordSection.offset().top - 100
                }, 500);
                // Focus on first input
                passwordSection.find('input').first().focus();
            });
        }
    }
    
    // Close sections when clicking outside (optional enhancement)
    $(document).mouseup(function(e) {
        const profileContainer = $("#profileEditSection");
        const passwordContainer = $("#passwordChangeSection");
        
        // If the click is outside the profile edit section and it's visible
        if (profileContainer.is(':visible') && !profileContainer.is(e.target) && profileContainer.has(e.target).length === 0) {
            profileContainer.slideUp(300);
        }
        
        // If the click is outside the password change section and it's visible
        if (passwordContainer.is(':visible') && !passwordContainer.is(e.target) && passwordContainer.has(e.target).length === 0) {
            passwordContainer.slideUp(300);
        }
    });
    
    // Close with Escape key
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            $('#profileEditSection').slideUp(300);
            $('#passwordChangeSection').slideUp(300);
        }
    });
    </script>
</body>
</html>
