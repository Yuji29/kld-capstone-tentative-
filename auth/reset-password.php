<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';
require_once '../config/session.php';

Session::start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please try again.";
    } else {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if ($password !== $confirm) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $query = "SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW()";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                
                if ($reset = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $update = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
                    
                    if ($update->execute([$hashed, $reset['email']])) {
                        $delete = $db->prepare("DELETE FROM password_resets WHERE token = ?");
                        $delete->execute([$token]);
                        $message = "Password updated successfully! You can now login.";
                    } else {
                        $error = "Error updating password";
                    }
                } else {
                    $error = "Invalid or expired reset link. Please request a new one.";
                }
            } catch (PDOException $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = "Database error occurred. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - KLD</title>
    <link rel="stylesheet" href="../css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
</head>
<body>
    <div class="auth-container">
        <!-- Back to Home Button -->
        <div class="back-to-home">
            <a href="../index.php" class="back-btn">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Home
            </a>
        </div>
        
        <div class="auth-box">
            <div class="logo">
                <img src="../Images/kld logo.png" alt="KLD">
                <h2>Set New Password</h2>
            </div>
            
            <?php if($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
                <div class="links">
                    <a href="login.php">Go to Login →</a>
                </div>
            <?php else: ?>
                <?php if(empty($token)): ?>
                    <div class="error">No reset token provided.</div>
                    <div class="links">
                        <a href="forgot-password.php">Request new reset link</a>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="password" required minlength="6" placeholder="Enter new password">
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" required placeholder="Confirm new password">
                        </div>
                        
                        <button type="submit">Reset Password</button>
                    </form>
                    
                    <div class="back-to-login">
                        <a href="login.php">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Back to Login
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>