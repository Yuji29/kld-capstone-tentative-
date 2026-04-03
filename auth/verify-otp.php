<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../config/database.php';
require_once '../includes/email-helper.php';

// Display debug OTP if email sending failed
$debug_otp = '';
if (isset($_SESSION['debug_otp'])) {
    $debug_otp = $_SESSION['debug_otp'];
    unset($_SESSION['debug_otp']);
}

// Display flash message if exists
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Redirect if not coming from registration
if (!isset($_SESSION['pending_verification']) || !isset($_SESSION['pending_user_id'])) {
    header('Location: register.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$resend_message = '';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $otp_code = trim($_POST['otp_code'] ?? '');
    
    if (empty($otp_code)) {
        $error = "Please enter the verification code.";
    } elseif (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
        $error = "Please enter a valid 6-digit verification code.";
    } else {
        $user_id = $_SESSION['pending_user_id'];
        
        // Debug log
        error_log("Verifying OTP: $otp_code for user_id: $user_id");
        
        if (verifyOTP($db, $user_id, $otp_code)) {
            // Verification successful
            unset($_SESSION['pending_verification']);
            unset($_SESSION['pending_user_id']);
            $_SESSION['flash_message'] = "Email verified successfully! You can now login.";
            $_SESSION['flash_type'] = "success";
            header('Location: login.php');
            exit;
        } else {
            // Check if OTP exists but expired
            $check_query = "SELECT * FROM email_verifications 
                            WHERE user_id = :user_id 
                            AND otp_code = :otp_code 
                            AND is_verified = 0";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->bindParam(':otp_code', $otp_code);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $otp_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                if (strtotime($otp_data['expires_at']) < time()) {
                    $error = "Verification code has expired. Please request a new code.";
                } else {
                    $error = "Invalid verification code. Please try again.";
                }
            } else {
                $error = "Invalid verification code. Please try again or request a new code.";
            }
        }
    }
}

// Handle resend OTP
if (isset($_GET['resend'])) {
    // Get user info
    $user_query = "SELECT id, full_name, email FROM users WHERE id = :user_id AND email_verified = 0";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['pending_user_id']);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $new_otp = generateOTP();
        
        if (saveOTP($db, $user['id'], $user['email'], $new_otp)) {
            $email_sent = sendOTPEmail($user['email'], $user['full_name'], $new_otp);
            if ($email_sent) {
                $resend_message = "A new verification code has been sent to your email.";
                error_log("Resend OTP successful for: " . $user['email']);
            } else {
                $resend_message = "Failed to send email. Please try again.";
                error_log("Resend OTP email failed for: " . $user['email']);
                // Store debug OTP
                $_SESSION['debug_otp'] = $new_otp;
            }
        } else {
            $resend_message = "Failed to generate new code. Please try again.";
        }
    } else {
        $resend_message = "User not found or already verified. Please register again.";
    }
}

// Get user info for display
$user_query = "SELECT full_name, email FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['pending_user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// If user not found, redirect to register
if (!$user) {
    unset($_SESSION['pending_verification']);
    unset($_SESSION['pending_user_id']);
    header('Location: register.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - KLD Capstone</title>
    <link rel="stylesheet" href="../css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0">
    <style>
        .otp-input {
            text-align: center;
            letter-spacing: 5px;
            font-size: 2rem;
            font-weight: bold;
            padding: 15px;
        }
        .resend-link {
            text-align: center;
            margin-top: 20px;
        }
        .resend-link a {
            color: var(--primary-color, #2D5A27);
            text-decoration: none;
        }
        .resend-link a:hover {
            text-decoration: underline;
        }
        .email-display {
            background: #f0f7f0;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
        }
        .warning-box {
            background: #fff3cd;
            color: #856404;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
        }
        .success-box {
            background: #d4edda;
            color: #155724;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #28a745;
        }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #dc3545;
        }
        .debug-code {
            font-family: monospace;
            font-size: 24px;
            font-weight: bold;
            background: #f0f7f0;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            letter-spacing: 5px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="back-to-home">
            <a href="../index.php" class="back-btn">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Home
            </a>
        </div>
        
        <div class="auth-box">
            <div class="logo">
                <img src="../Images/kld logo.png" alt="KLD">
                <h2>Verify Your Email</h2>
            </div>
            
            <p style="text-align: center; margin-bottom: 20px;">
                We've sent a verification code to:
            </p>
            <div class="email-display">
                <strong><?php echo htmlspecialchars($user['email'] ?? ''); ?></strong>
            </div>
            
            <?php if($flash_message): ?>
                <div class="<?php echo $flash_type === 'success' ? 'success-box' : 'warning-box'; ?>">
                    <?php echo htmlspecialchars($flash_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($resend_message): ?>
                <div class="success-box"><?php echo htmlspecialchars($resend_message); ?></div>
            <?php endif; ?>
            
            <?php if($debug_otp): ?>
                <div class="warning-box">
                    <strong>⚠️ Debug Mode (Email couldn't be sent)</strong><br>
                    Your verification code is: <strong style="font-size: 24px; letter-spacing: 3px;"><?php echo $debug_otp; ?></strong>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Verification Code</label>
                    <input type="text" 
                           name="otp_code" 
                           class="otp-input" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           placeholder="000000"
                           autocomplete="off"
                           required>
                </div>
                
                <button type="submit" name="verify">Verify Email</button>
            </form>
            
            <div class="resend-link">
                <p>Didn't receive the code? <a href="?resend=1">Click here to resend</a></p>
            </div>
            
            <div class="links">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>