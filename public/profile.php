<?php
require_once '../includes/header.php';
// Use the same pattern as practical class
// REMOVED: session_start() - already in base.php
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
                
                // Save new photo
                $name = "profile_{$uid}.jpg";
                $path = root("uploads/profile/$name");
                
                // Create directory if it doesn't exist
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                // Process image with SimpleImage
                // Check if SimpleImage class exists, if not use GD functions
                if (class_exists('SimpleImage')) {
                    $img = new SimpleImage();
                    $img->fromFile($f['tmp_name'])
                        ->thumbnail(300, 300, 'center')
                        ->toFile($path, 'image/jpeg', ['quality' => 90]);
                } else {
                    // Fallback to GD functions
                    $image_info = getimagesize($f['tmp_name']);
                    $original_image = null;
                    
                    switch($image_info[2]) {
                        case IMAGETYPE_JPEG:
                            $original_image = imagecreatefromjpeg($f['tmp_name']);
                            break;
                        case IMAGETYPE_PNG:
                            $original_image = imagecreatefrompng($f['tmp_name']);
                            break;
                        case IMAGETYPE_GIF:
                            $original_image = imagecreatefromgif($f['tmp_name']);
                            break;
                    }
                    
                    if ($original_image) {
                        $thumb = imagecreatetruecolor(300, 300);
                        imagecopyresampled($thumb, $original_image, 0, 0, 0, 0, 300, 300, 
                                          imagesx($original_image), imagesy($original_image));
                        imagejpeg($thumb, $path, 90);
                        imagedestroy($original_image);
                        imagedestroy($thumb);
                    }
                }
                    
                $photo = $name;
                $_SESSION['photo'] = $photo;
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
            
            temp('info', 'Profile updated');
            redirect('profile.php');
            
        } catch (Exception $e) {
            $_db->rollBack();
            temp('error', 'Error updating profile: ' . $e->getMessage());
        }
    }
}

// ----------------------------------------------------------------------------

$_title = 'My Profile';
?>

<h2>My Profile</h2>

<?php 
$message = temp('info');
if ($message) {
    echo '<div class="success-message">' . encode($message) . '</div>';
}
$error = temp('error');
if ($error) {
    echo '<div class="error-message">' . encode($error) . '</div>';
}
?>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="username">Username</label>
    <?php html_text('username', 'maxlength="50" required'); ?>
    <?php err('username'); ?>

    <label for="email">Email</label>
    <?php html_text('email', 'maxlength="100" required'); ?>
    <?php err('email'); ?>

    <label for="photo">Profile Photo</label>
    <label class="upload" tabindex="0">
        <?php html_file('photo', 'image/*', 'hidden'); ?>
        <img src="<?= base("uploads/profile/" . ($profile_photo ?? 'avatar.png')) ?>" width="150" height="150" style="border-radius: 50%; object-fit: cover;">
    </label>
    <?php err('photo'); ?>

    <section>
        <button name="btn_update">Update Profile</button>
        <a href="password_reset.php" class="btn btn-secondary">Change Password</a>
        <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
    </section>
</form>

<!-- Email Verification Section -->
<div class="card mt-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">📧 Email Verification Status</h5>
    </div>
    <div class="card-body">
        <?php
        // Get verification status
        $stmt = $_db->prepare('SELECT email_verified, verification_code FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $verification = $stmt->fetch();
        $is_verified = $verification['email_verified'] ?? 0;
        $has_token = !empty($verification['verification_token']);
        ?>
        
        <?php if ($is_verified): ?>
            <div class="alert alert-success d-flex align-items-center">
                <span class="me-3" style="font-size: 24px;">✅</span>
                <div>
                    <h6 class="alert-heading mb-1">Email Verified</h6>
                    <p class="mb-0">Your email <strong><?php echo htmlspecialchars($email); ?></strong> is verified.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning d-flex align-items-center">
                <span class="me-3" style="font-size: 24px;">⚠️</span>
                <div>
                    <h6 class="alert-heading mb-1">Email Not Verified</h6>
                    <p class="mb-0">
                        Your email <strong><?php echo htmlspecialchars($email); ?></strong> is not verified.
                        You need to verify your email to access all features.
                    </p>
                </div>
            </div>
            
            <div class="mt-3">
                <h6>Features requiring verification:</h6>
                <ul class="mb-3">
                    <li>Complete purchases (checkout)</li>
                    <li>View order history</li>
                    <li>Write product reviews</li>
                    <li>Save shipping addresses</li>
                </ul>
                
                <div class="d-flex gap-2">
                    <a href="verify_email_code.php" class="btn btn-primary">
                        📧 Verify Email Now
                    </a>
                    <a href="verify_required.php" class="btn btn-outline-secondary">
                        <i class="fas fa-info-circle me-1"></i> Learn More
                    </a>
                </div>
                
                <div class="mt-3 small text-muted">
                    <p class="mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Check your spam folder</p>
                    <p class="mb-0"><i class="fas fa-clock me-1"></i> Links expire in 24 hours</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include '../includes/footer.php';