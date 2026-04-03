<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        
        if (empty($full_name)) {
            $error = "Full name is required.";
        } else {
            try {
                $update_query = "UPDATE users SET full_name = :full_name, department = :department WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':full_name', $full_name);
                $update_stmt->bindParam(':department', $department);
                $update_stmt->bindParam(':user_id', $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['flash_message'] = "Profile updated successfully!";
                    $_SESSION['flash_type'] = "success";
                    header('Location: profile.php');
                    exit;
                } else {
                    $error = "Failed to update profile.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }

    // Handle Delete Avatar
    if (isset($_POST['delete_avatar'])) {
        try {
            // Get current avatar path
            $avatar_query = "SELECT avatar FROM users WHERE id = :user_id";
            $avatar_stmt = $db->prepare($avatar_query);
            $avatar_stmt->bindParam(':user_id', $user_id);
            $avatar_stmt->execute();
            $user_data = $avatar_stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_data && !empty($user_data['avatar']) && file_exists($user_data['avatar'])) {
                // Delete the file
                unlink($user_data['avatar']);
            }

            // Update database to remove avatar
            $update_query = "UPDATE users SET avatar = NULL WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->execute();

            $_SESSION['flash_message'] = "Profile picture deleted successfully!";
            $_SESSION['flash_type'] = "success";
            header('Location: profile.php');
            exit;

        } catch (PDOException $e) {
            error_log("Delete avatar error: " . $e->getMessage());
            $_SESSION['flash_error'] = "Failed to delete profile picture.";
            header('Location: profile.php');
            exit;
        }
    }
    
    // Handle avatar upload
    if (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {
        $target_dir = "uploads/avatars/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $_SESSION['flash_error'] = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
            header('Location: profile.php');
            exit;
        } elseif ($_FILES['avatar']['size'] > 2097152) {
            $_SESSION['flash_error'] = "File size must be less than 2MB.";
            header('Location: profile.php');
            exit;
        } else {
            $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                $old_query = "SELECT avatar FROM users WHERE id = :user_id";
                $old_stmt = $db->prepare($old_query);
                $old_stmt->bindParam(':user_id', $user_id);
                $old_stmt->execute();
                $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($old_data && !empty($old_data['avatar']) && file_exists($old_data['avatar'])) {
                    unlink($old_data['avatar']);
                }
                
                $update_query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':avatar', $target_file);
                $update_stmt->bindParam(':user_id', $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['flash_message'] = "Profile picture updated successfully!";
                    $_SESSION['flash_type'] = "success";
                    header('Location: profile.php');
                    exit;
                } else {
                    $_SESSION['flash_error'] = "Failed to save avatar to database.";
                    header('Location: profile.php');
                    exit;
                }
            } else {
                $_SESSION['flash_error'] = "Failed to upload file.";
                header('Location: profile.php');
                exit;
            }
        }
    }
}

// Get flash messages
if (isset($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Get user data
$query = "SELECT id, full_name, email, id_number, department, role, avatar FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get departments for dropdown
$dept_query = "SELECT name FROM departments ORDER BY name";
$dept_stmt = $db->query($dept_query);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - KLD Capstone</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="css/profile.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/dashboard-nav.php'; ?>
    
    <div class="profile-container">
        <div class="header">
            <div>
                <h1>My Profile</h1>
                <p class="header-subtitle">View and manage your account information</p>
            </div>
        </div>
        
        <?php if($success): ?>
            <div class="message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <div class="profile-card avatar-card">
                <h2>Profile Picture</h2>
                
                <div class="image-preview-container">
                    <img id="imagePreview" class="image-preview" src="#" alt="Preview">
                </div>
                
                <div class="avatar-container">
                    <?php if(!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="profile-avatar" id="currentAvatar">
                    <?php else: ?>
                        <div class="profile-avatar-initials" id="currentAvatar">
                            <?php 
                            $initials = '';
                            $name_parts = explode(' ', $user['full_name'] ?? 'User');
                            if (count($name_parts) >= 2) {
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                            } else {
                                $initials = strtoupper(substr($user['full_name'] ?? 'U', 0, 2));
                            }
                            echo $initials;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="avatar-form" id="avatarForm">
                    <div class="avatar-buttons">
                        <div class="file-input-wrapper">
                            <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                            <label for="avatar" class="file-label">Choose Image</label>
                        </div>
                        <?php if(!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <button type="button" class="btn-delete-avatar" onclick="confirmDeleteAvatar()">
                                <span class="material-symbols-outlined">delete</span>
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                    <small class="file-hint">Max 2MB. JPG, PNG, GIF, or WEBP</small>
                    <button type="submit" name="upload_avatar" class="btn-upload" id="uploadBtn" style="display: none;">Upload Picture</button>
                </form>
                
                <div class="profile-divider"></div>
                
                <div class="profile-quick-actions">
                    <a href="manage-account.php" class="quick-action">
                        <span class="material-symbols-outlined">lock</span>
                        <div class="quick-action-text">
                            <strong>Change Password</strong>
                            <small>Update your password</small>
                        </div>
                    </a>
                    <a href="my-titles.php" class="quick-action">
                        <span class="material-symbols-outlined">description</span>
                        <div class="quick-action-text">
                            <strong>My Titles</strong>
                            <small>View your capstone titles</small>
                        </div>
                    </a>
                    <div class="quick-action-divider"></div>
                    <a href="javascript:void(0)" onclick="openPrivacyModal()" class="quick-action">
                        <span class="material-symbols-outlined">privacy_tip</span>
                        <div class="quick-action-text">
                            <strong>Privacy Policy</strong>
                            <small>How we protect your data</small>
                        </div>
                    </a>
                    <a href="javascript:void(0)" onclick="openTermsModal()" class="quick-action">
                        <span class="material-symbols-outlined">gavel</span>
                        <div class="quick-action-text">
                            <strong>Terms of Service</strong>
                            <small>Rules and guidelines</small>
                        </div>
                    </a>
                </div>
            </div>
            
            <div class="profile-card info-card">
                <h2>Personal Information</h2>
                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['id_number'] ?? ''); ?>" disabled class="disabled-input">
                        <small>ID number cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled class="disabled-input">
                        <small>Email cannot be changed. Contact administrator for changes.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department">
                            <option value="">Select Department</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($user['department'] ?? '') == $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?php echo ucfirst($user['role'] ?? 'user'); ?>" disabled class="disabled-input">
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include 'includes/dashboard-footer.php'; ?>

    <!-- Include Privacy Modals -->
    <?php include 'includes/privacy-modals.php'; ?>

    <!-- Include Confirmation Modal -->
    <?php include 'includes/confirmation-modal.php'; ?>
    
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const currentAvatar = document.getElementById('currentAvatar');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;  
                    preview.classList.add('show');
                    if (currentAvatar) {
                        currentAvatar.style.display = 'none';
                    }
                    uploadBtn.style.display = 'inline-block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Delete Avatar Confirmation
        function confirmDeleteAvatar() {
            showConfirmationModal({
                title: 'Delete Profile Picture',
                message: 'Are you sure you want to delete your profile picture?<br><br><strong>This action cannot be undone.</strong>',
                confirmText: 'Yes, Delete',
                type: 'delete',
                onConfirm: function() {
                    // Submit a form to delete avatar
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    form.style.display = 'none';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_avatar';
                    input.value = '1';
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>