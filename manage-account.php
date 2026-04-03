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

// Handle Preferences Update (without theme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    $language = $_POST['language'] ?? 'en';
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
                language VARCHAR(10) DEFAULT 'en',
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
                            SET language = :language, 
                                email_notifications = :email_notifications, 
                                deadline_reminders = :deadline_reminders 
                            WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':language', $language);
            $update_stmt->bindParam(':email_notifications', $email_notifications);
            $update_stmt->bindParam(':deadline_reminders', $deadline_reminders);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->execute();
        } else {
            $insert_query = "INSERT INTO user_preferences (user_id, language, email_notifications, deadline_reminders) 
                            VALUES (:user_id, :language, :email_notifications, :deadline_reminders)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user_id);
            $insert_stmt->bindParam(':language', $language);
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

// Get user preferences (without theme)
$language = 'en';
$email_notifications = 1;
$deadline_reminders = 1;

try {
    $pref_query = "SELECT * FROM user_preferences WHERE user_id = :user_id";
    $pref_stmt = $db->prepare($pref_query);
    $pref_stmt->bindParam(':user_id', $user_id);
    $pref_stmt->execute();
    $db_prefs = $pref_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($db_prefs) {
        $language = $db_prefs['language'] ?? 'en';
        $email_notifications = $db_prefs['email_notifications'] ?? 1;
        $deadline_reminders = $db_prefs['deadline_reminders'] ?? 1;
    }
} catch (PDOException $e) {
    error_log("Fetch preferences error: " . $e->getMessage());
}

$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Active sessions (simplified - would need a sessions table for real implementation)
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f7f5;
            padding-top: 80px;
        }
        
        .manage-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            background: white;
            border-radius: 32px;
            padding: 25px 30px;
            margin: 20px 0 30px;
            box-shadow: 0 10px 25px rgba(45, 90, 39, 0.05);
            border: 1px solid #e2efdf;
            transition: all 0.3s ease;
        }
        
        .header h1 {
            color: #1e3d1a;
            font-size: 2rem;
            margin-bottom: 0.25rem;
        }
        
        .header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e2efdf;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            font-size: 1rem;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 30px 30px 0 0;
        }
        
        .tab-btn:hover {
            color: #2D5A27;
            background: rgba(45, 90, 39, 0.1);
        }
        
        .tab-btn.active {
            color: #2D5A27;
            border-bottom: 3px solid #2D5A27;
            background: rgba(45, 90, 39, 0.05);
        }
        
        .tab-content {
            display: none;
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid #e2efdf;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .user-info {
            background: #f8fbf8;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .user-info div:first-child {
            font-weight: 600;
            color: #1e3d1a;
        }
        
        .user-info div:nth-child(2) {
            font-size: 0.85rem;
            color: #666;
        }
        
        .user-info div:last-child {
            font-size: 0.8rem;
            color: #2D5A27;
            text-transform: capitalize;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2D5A27;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            color: #333;
        }
        
        .password-hint {
            font-size: 0.7rem;
            color: #999;
            margin-top: 0.5rem;
        }
        
        .btn-save {
            background: #2D5A27;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            background: #1e3d1a;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #a8e0b7;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .session-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8fbf8;
            border-radius: 16px;
            border: 1px solid #e2efdf;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .session-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .session-device {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .session-device .material-symbols-outlined {
            color: #2D5A27;
        }
        
        .session-details {
            color: #666;
            font-size: 0.8rem;
        }
        
        .current-badge {
            background: #2D5A27;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .btn-terminate {
            background: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-terminate:hover {
            background: #dc3545;
            color: white;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            color: #666;
            text-decoration: none;
        }
        
        .back-link:hover {
            color: #2D5A27;
        }
        
        .info-note {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8fbf8;
            border-radius: 12px;
            font-size: 0.8rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .manage-container {
                padding: 1rem;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                text-align: left;
                border-radius: 30px;
            }
            
            .tab-btn.active {
                border-bottom: none;
                background: rgba(45, 90, 39, 0.1);
            }
            
            .session-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
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
        
        <!-- Tab 2: Preferences (without theme) -->
        <div class="tab-content" id="tab-preferences">
            <form method="POST">
                <div class="form-group">
                    <label>Language</label>
                    <select name="language">
                        <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="fil" <?php echo $language === 'fil' ? 'selected' : ''; ?>>Filipino</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="email_notifications" id="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                    <label for="email_notifications">Receive email notifications</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="deadline_reminders" id="deadline_reminders" <?php echo $deadline_reminders ? 'checked' : ''; ?>>
                    <label for="deadline_reminders">Send deadline reminders (24 hours before due date)</label>
                </div>
                
                <button type="submit" name="save_preferences" class="btn-save">Save Preferences</button>
            </form>
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
                <small> Active sessions show where you're currently logged in.</small>
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