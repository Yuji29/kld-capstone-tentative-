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

// Get current user data
$user_query = "SELECT full_name, email, role FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = "Password must contain at least one number.";
    } else {
        $query = "SELECT password FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_user && password_verify($current_password, $current_user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':user_id', $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Password changed successfully!";
                $_POST = [];
            } else {
                $error = "Failed to change password.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

// Handle Preferences Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $deadline_reminders = isset($_POST['deadline_reminders']) ? 1 : 0;
    
    try {
        // Check if user_preferences table exists
        $check_table = "SHOW TABLES LIKE 'user_preferences'";
        $table_exists = $db->query($check_table)->rowCount() > 0;
        
        if (!$table_exists) {
            $create_table = "CREATE TABLE user_preferences (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL UNIQUE,
                email_notifications TINYINT(1) DEFAULT 1,
                deadline_reminders TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            $db->exec($create_table);
        }
        
        // Check if record exists
        $check_query = "SELECT id FROM user_preferences WHERE user_id = :user_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        $exists = $check_stmt->fetch();
        
        if ($exists) {
            $update_query = "UPDATE user_preferences 
                            SET email_notifications = :email_notifications, 
                                deadline_reminders = :deadline_reminders 
                            WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':email_notifications', $email_notifications);
            $update_stmt->bindParam(':deadline_reminders', $deadline_reminders);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->execute();
        } else {
            $insert_query = "INSERT INTO user_preferences (user_id, email_notifications, deadline_reminders) 
                            VALUES (:user_id, :email_notifications, :deadline_reminders)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user_id);
            $insert_stmt->bindParam(':email_notifications', $email_notifications);
            $insert_stmt->bindParam(':deadline_reminders', $deadline_reminders);
            $insert_stmt->execute();
        }
        
        $success = "Preferences saved successfully!";
        
    } catch (PDOException $e) {
        error_log("Preferences error: " . $e->getMessage());
        $error = "Database error occurred.";
    }
}

// Get user preferences
$email_notifications = 1;
$deadline_reminders = 1;

try {
    $pref_query = "SELECT * FROM user_preferences WHERE user_id = :user_id";
    $pref_stmt = $db->prepare($pref_query);
    $pref_stmt->bindParam(':user_id', $user_id);
    $pref_stmt->execute();
    $db_prefs = $pref_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($db_prefs) {
        $email_notifications = $db_prefs['email_notifications'] ?? 1;
        $deadline_reminders = $db_prefs['deadline_reminders'] ?? 1;
    }
} catch (PDOException $e) {
    error_log("Fetch preferences error: " . $e->getMessage());
}

$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Active sessions
$active_sessions = [
    ['id' => 1, 'device' => 'Chrome on Windows', 'location' => 'Dasmarinas, Cavite', 'ip' => '192.168.1.1', 'last_active' => 'Now', 'current' => true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Account - KLD Capstone</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="css/manage-account.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/dashboard-nav.php'; ?>
    
    <div class="manage-container">
        <div class="header">
            <h1>Manage Account</h1>
            <p>Manage your password, preferences, and active sessions</p>
        </div>
        
        <?php if($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-btn active" data-tab="password">Change Password</button>
            <button class="tab-btn" data-tab="preferences">Preferences</button>
            <button class="tab-btn" data-tab="sessions">Active Sessions</button>
        </div>
        
        <!-- Tab 1: Change Password -->
        <div class="tab-content active" id="tab-password">
            <div class="user-info">
                <div><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div><?php echo htmlspecialchars($user['email']); ?></div>
                <div><?php echo ucfirst(htmlspecialchars($user['role'])); ?></div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter your current password" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required>
                    <div class="password-hint">Password must be at least 6 characters and contain uppercase, lowercase, and number.</div>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                </div>
                
                <button type="submit" name="change_password" class="btn-save">Change Password</button>
            </form>
        </div>
        
        <!-- Tab 2: Preferences -->
        <div class="tab-content" id="tab-preferences">
            <div style="margin-bottom: 1rem; padding: 0.75rem; background: #e8f5e9; border-radius: 8px; color: #2e7d32;">
                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">info</span>
                <small> Your preferences control how you receive communications from the system.</small>
            </div>
            
            <form method="POST">
                <div class="checkbox-group">
                    <input type="checkbox" name="email_notifications" id="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                    <label for="email_notifications">Receive email notifications</label>
                </div>
                <div style="margin-left: 28px; margin-bottom: 1rem;">
                    <small style="color: #666;">Get notified about title approvals, deadlines, and system updates.</small>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="deadline_reminders" id="deadline_reminders" <?php echo $deadline_reminders ? 'checked' : ''; ?>>
                    <label for="deadline_reminders">Send deadline reminders (24 hours before due date)</label>
                </div>
                <div style="margin-left: 28px; margin-bottom: 1.5rem;">
                    <small style="color: #666;">Receive email reminders one day before important deadlines.</small>
                </div>
                
                <button type="submit" name="save_preferences" class="btn-save">Save Preferences</button>
            </form>
            
            <div class="info-note" style="margin-top: 1.5rem;">
                <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">info</span>
                <small> These settings apply to all email communications from the system. You can change them at any time.</small>
            </div>
        </div>
        
        <!-- Tab 3: Active Sessions -->
        <div class="tab-content" id="tab-sessions">
            <div class="session-list">
                <?php foreach($active_sessions as $session): ?>
                    <div class="session-item">
                        <div class="session-info">
                            <div class="session-device">
                                <span class="material-symbols-outlined">
                                    <?php echo strpos($session['device'], 'Chrome') !== false ? 'computer' : 'devices'; ?>
                                </span>
                                <strong><?php echo htmlspecialchars($session['device']); ?></strong>
                            </div>
                            <div class="session-details">
                                <div><?php echo htmlspecialchars($session['location']); ?></div>
                                <div>IP: <?php echo htmlspecialchars($session['ip']); ?> • Last active: <?php echo htmlspecialchars($session['last_active']); ?></div>
                            </div>
                        </div>
                        <div>
                            <?php if($session['current']): ?>
                                <span class="current-badge">Current Session</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="info-note">
                <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">info</span>
                <small> Active sessions show where you're currently logged in. Terminate any session you don't recognize.</small>
            </div>
        </div>
        
        <a href="profile.php" class="back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Profile
        </a>
    </div>

    <!-- Include Footer -->
    <?php include 'includes/dashboard-footer.php'; ?>
    
    <script>
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                document.getElementById(`tab-${tabId}`).classList.add('active');
            });
        });
    </script>
</body>
</html>