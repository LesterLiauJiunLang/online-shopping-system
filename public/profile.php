<?php
// At the top of profile.php
require_once '../lib/base.php';      // includes your base functions
require_once '../lib/auth.php';      // includes auth functions

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// ----------------------------------------------------------------------------
// GET: Load current user data
// ----------------------------------------------------------------------------
if (is_get()) {
    $stm = $_db->prepare('SELECT username, email, profile_photo FROM users WHERE user_id = ?');
    $stm->execute([$uid]);
    $u = $stm->fetch();

    if (!$u) {
        redirect('dashboard.php');
    }

    // Set variables from database
    $username = $u['username'] ?? '';
    $email = $u['email'] ?? '';
    $profile_photo = $u['profile_photo'] ?? '';
    
    // Update session with current email if not set
    if (!isset($_SESSION['email']) && $email) {
        $_SESSION['email'] = $email;
    }
    
    // Set photo for header if exists
    if ($profile_photo) {
        $_SESSION['photo'] = $profile_photo;
    }
}

// ----------------------------------------------------------------------------
// POST: Update profile
// ----------------------------------------------------------------------------
if (is_post()) {
    $username = req('username');
    $email = req('email');
    $photo = $_SESSION['photo'] ?? 'avatar.png';
    
    // FIXED: Use $_FILES instead of get_file() which doesn't exist
    $f = isset($_FILES['photo']) && $_FILES['photo']['error'] != UPLOAD_ERR_NO_FILE ? $_FILES['photo'] : null;

    // Validate: username
    if ($username == '') {
        $_err['username'] = 'Required';
    }
    else if (strlen($username) > 50) {
        $_err['username'] = 'Maximum 50 characters';
    }

    // Validate: email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else {
        // Check if email is unique (excluding current user's email)
        $stm = $_db->prepare('
            SELECT COUNT(*) FROM users 
            WHERE email = ? AND user_id != ?
        ');
        $stm->execute([$email, $uid]);

        if ($stm->fetchColumn() > 0) {
            $_err['email'] = 'Duplicated';
        }
    }

    // Validate: photo (file) --> optional
    if ($f) {
        if (!str_starts_with($f['type'], 'image/')) {
            $_err['photo'] = 'Must be image';
        }
        else if ($f['size'] > 2 * 1024 * 1024) {
            $_err['photo'] = 'Maximum 2MB';
        }
    }

    // DB operation
    if (empty($_err)) {
        // Start transaction
        $_db->beginTransaction();
        
        try {
            // (1) Handle photo upload --> optional
            if ($f && !isset($_err['photo'])) {
                // Delete old photo if exists and not default avatar
                if ($photo && $photo != 'avatar.png') {
                    $old_path = root("uploads/profile/$photo");
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
                
                // Use the new function from base.php
                $name = generate_profile_filename($uid, $f['name']);
                $path = root("uploads/profile/$name");
                
                // Save the uploaded image
                if (save_uploaded_image($f, $path)) {
                    $photo = $name;
                    $_SESSION['photo'] = $photo;
                } else {
                    throw new Exception("Failed to save uploaded image.");
                }
            }
            
            // (2) Update user (username, email, profile_photo)
            $stm = $_db->prepare('
                UPDATE users 
                SET username = ?, email = ?, profile_photo = ?
                WHERE user_id = ?
            ');
            $stm->execute([$username, $email, $photo, $uid]);

            // (3) Update session variables
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;

            $_db->commit();
            
            temp('info', 'Profile updated successfully!');
            redirect('profile.php');
            
        } catch (Exception $e) {
            $_db->rollBack();
            temp('error', 'Error updating profile: ' . $e->getMessage());
        }
    }
}

// ----------------------------------------------------------------------------
// Get verification status for display
// ----------------------------------------------------------------------------
try {
    $stmt = $_db->prepare('SELECT email_verified, verification_code FROM users WHERE user_id = ?');
    $stmt->execute([$uid]);
    $verification = $stmt->fetch();
    $is_verified = $verification['email_verified'] ?? 0;
    $has_code = !empty($verification['verification_code'] ?? '');
} catch (Exception $e) {
    $is_verified = 0;
    $has_code = false;
}

// ----------------------------------------------------------------------------
// Set page title
$_title = 'My Profile';
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../assets/css/profile.css?v=<?php echo time(); ?>">


<div class="profile-container">
    <div class="profile-header">
        <h2>My Profile</h2>
        <p>Manage your account information and preferences</p>
    </div>
    
    <?php 
    $message = temp('info');
    if ($message) {
        echo '<div class="success-message">' . htmlspecialchars($message) . '</div>';
    }
    $error = temp('error');
    if ($error) {
        echo '<div class="error-message">' . htmlspecialchars($error) . '</div>';
    }
    ?>
    
    <!-- Profile Update Form -->
    <div class="profile-form">
        <form method="post" enctype="multipart/form-data" id="profile-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                       maxlength="50" required>
                <span class="form-error"><?php echo $_err['username'] ?? ''; ?></span>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                       maxlength="100" required>
                <span class="form-error"><?php echo $_err['email'] ?? ''; ?></span>
            </div>
            
            <!-- Profile Picture with Button to Open Modal -->
            <div class="photo-upload">
                <div class="current-photo">
                    <img src="<?php echo base("uploads/profile/" . ($profile_photo ?? 'avatar.png')); ?>" 
                         alt="Current Profile Photo" class="photo-preview" id="current-photo-preview">
                    <div class="photo-actions">
                        <button type="button" class="btn btn-secondary" id="change-photo-btn">
                            <i class="fas fa-camera"></i> Change Profile Picture
                        </button>
                    </div>
                </div>
                <!-- Hidden file input for the form -->
                <input type="file" id="photo" name="photo" accept="image/*" style="display: none;">
                <span class="form-error"><?php echo $_err['photo'] ?? ''; ?></span>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="btn_update" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="password_reset.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
    
    <!-- Email Verification Section -->
    <div class="verification-card">
        <div class="card-header">
            <h5><i class="fas fa-envelope"></i> Email Verification Status</h5>
        </div>
        <div class="card-body">
            <?php if ($is_verified): ?>
                <div class="verification-status alert-success">
                    <div class="status-icon">✅</div>
                    <div class="status-content">
                        <h6>Email Verified</h6>
                        <p>Your email <strong><?php echo htmlspecialchars($email); ?></strong> is verified and ready to use.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="verification-status alert-warning">
                    <div class="status-icon">⚠️</div>
                    <div class="status-content">
                        <h6>Email Not Verified</h6>
                        <p>
                            Your email <strong><?php echo htmlspecialchars($email); ?></strong> is not verified.
                            You need to verify your email to access all features.
                        </p>
                    </div>
                </div>
                
                <div class="verification-features">
                    <h6>Features requiring verification:</h6>
                    <ul class="features-list">
                        <li>Complete purchases (checkout)</li>
                        <li>View order history</li>
                        <li>Write product reviews</li>
                        <li>Save shipping addresses</li>
                        <li>Receive order notifications</li>
                    </ul>
                </div>
                
                <div class="verification-actions">
                    <!-- Single button that goes to verification page - email will be sent automatically -->
                    <a href="verify_email_code.php" class="btn-verify">
                        <i class="fas fa-envelope"></i> Verify Email Now
                    </a>
                    
                    <a href="verify_required.php" class="btn-info">
                        <i class="fas fa-info-circle"></i> Learn More
                    </a>
                </div>
                
                <div class="verification-tips">
                    <p><i class="fas fa-exclamation-triangle"></i> Check your spam folder if you don't see the email</p>
                    <p><i class="fas fa-clock"></i> Verification codes expire in 10 minutes</p>
                    <p><i class="fas fa-redo"></i> You can request a new code on the verification page</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile Picture Upload Modal (Popup) -->
<div class="modal-overlay" id="photo-modal" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Upload Profile Picture</h3>
            <button type="button" class="close-modal" id="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Drag & Drop Area -->
            <div class="modal-drag-drop-area" id="modal-drop-zone">
                <div class="drag-drop-content">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="upload-text">Drag & Drop your image here</div>
                    <div class="upload-subtext">or click to browse</div>
                    <div class="file-types">Supported: JPG, PNG, GIF (Max 2MB)</div>
                </div>
                
                <!-- Hidden file input for the modal -->
                <input type="file" id="modal-file-input" accept="image/*" style="display: none;">
                
                <!-- Preview area -->
                <div class="preview-area" id="modal-preview" style="display: none;">
                    <img src="" alt="Preview" id="modal-preview-image">
                    <div class="preview-info">
                        <span id="modal-file-name"></span>
                        <span id="modal-file-size"></span>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" id="cancel-upload">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-upload" disabled>
                    <i class="fas fa-check"></i> Use This Photo
                </button>
            </div>
        </div>
    </div>
</div>


<script src="../assets/js/profile.js"></script>

<?php include '../includes/footer.php'; ?>