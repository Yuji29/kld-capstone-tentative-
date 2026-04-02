<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/mail.php';

Session::start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Rate limiting - prevent spam
if (isset($_SESSION['last_reset_request']) && time() - $_SESSION['last_reset_request'] < 300) {
    $error = "Please wait 5 minutes before requesting another password reset.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please try again.";
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        
        if (empty($email)) {
            $error = "Please enter a valid email address";
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                // Check if email exists
                $query = "SELECT * FROM users WHERE email = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Delete any existing tokens for this email
                    $delete = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                    $delete->execute([$email]);
                    
                    // Insert new token
                    $insert = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    if ($insert->execute([$email, $token, $expires])) {
                        // Generate dynamic reset link
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        $reset_link = $protocol . "://" . $host . "/kld-capstone/auth/reset-password.php?token=" . urlencode($token);
                        
                        // Send email
                        $mailer = new Mailer();
                        if ($mailer->sendResetLink($email, $user['full_name'], $reset_link)) {
                            $message = "Password reset instructions have been sent to your email.";
                            $_SESSION['last_reset_request'] = time();
                        } else {
                            $error = "Failed to send email. Please try again later.";
                            error_log("Failed to send password reset email to: " . $email);
                        }
                    } else {
                        $error_info = $insert->errorInfo();
                        error_log("Failed to create reset token: " . $error_info[2]);
                        $error = "Failed to create reset token. Please try again.";
                    }
                } else {
                    // Don't reveal that email doesn't exist for security
                    $message = "If your email is registered, you will receive reset instructions.";
                    $_SESSION['last_reset_request'] = time();
                }
            } catch (PDOException $e) {
                error_log("Password reset database error: " . $e->getMessage());
                $error = "Database error occurred. Please try again later.";
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
    <title>Forgot Password - KLD</title>
    <link rel="stylesheet" href="../css/auth.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
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
                <h2>Reset Password</h2>
            </div>
            
            <?php if($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if(!$message): ?>
                <p class="info-text">Enter your email address and we'll send you instructions to reset your password.</p>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                    <button type="submit">Send Reset Instructions</button>
                </form>
            <?php endif; ?>
            
            <div class="links">
                <div class="back-to-login">
                    <a href="login.php">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to Login
                    </a>
                </div>
                <p>Don't have an account? <a href="register.php" class="register-link">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>