<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/email-helper.php';

Session::start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$error = '';
$login = '';

// Check if user is already logged in
if (Session::isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

// Check if remember me cookie exists
if (isset($_COOKIE['remember_token']) && !Session::isLoggedIn()) {
    $token = $_COOKIE['remember_token'];
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Clean up expired tokens first
        $db->query("DELETE FROM remember_tokens WHERE expiry < NOW()");
        
        // Check if token exists
        $query = "SELECT rt.*, u.* FROM remember_tokens rt 
                  JOIN users u ON rt.user_id = u.id 
                  WHERE rt.token = :token AND rt.expiry > NOW()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['id_number'] = $user['id_number'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect to dashboard
            header('Location: ../dashboard.php');
            exit;
        } else {
            // Invalid token, clear cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    } catch (PDOException $e) {
        error_log("Remember me login error: " . $e->getMessage());
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please try again.";
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;
        
        if (empty($login) || empty($password)) {
            $error = "Please enter both ID/Email and password";
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                // Allow login with either ID number OR email
                $query = "SELECT * FROM users WHERE id_number = :login OR email = :login";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':login', $login);
                $stmt->execute();
                
                if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($user['email_verified']) && $user['email_verified'] == 0 && $user['role'] !== 'admin') {
                        // Store user info for verification
                        $_SESSION['pending_verification'] = true;
                        $_SESSION['pending_user_id'] = $user['id'];
                        
                        // Generate and send new OTP
                        $otp_code = generateOTP();
                        saveOTP($db, $user['id'], $user['email'], $otp_code);
                        sendOTPEmail($user['email'], $user['full_name'], $otp_code);
                        
                        // Redirect to verification page with message
                        $_SESSION['flash_message'] = "Please verify your email address before logging in. A new verification code has been sent to your email.";
                        $_SESSION['flash_type'] = "warning";
                        header('Location: verify-otp.php');
                        exit;
                    }
                    if (password_verify($password, $user['password'])) {
                        // Set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['id_number'] = $user['id_number'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['email'] = $user['email'];
                        
                        // Handle Remember Me
                        if ($remember) {
                            // Generate secure token
                            $token = bin2hex(random_bytes(32));
                            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                            
                            // Delete any existing tokens for this user
                            $delete = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                            $delete->execute([$user['id']]);
                            
                            // Store new token
                            $insert = $db->prepare("INSERT INTO remember_tokens (user_id, token, expiry) VALUES (?, ?, ?)");
                            $insert->execute([$user['id'], $token, $expiry]);
                            
                            // Set cookie for 30 days
                            setcookie(
                                'remember_token',
                                $token,
                                [
                                    'expires' => time() + (30 * 24 * 60 * 60),
                                    'path' => '/',
                                    'domain' => '',
                                    'secure' => false,
                                    'httponly' => true,
                                    'samesite' => 'Lax'
                                ]
                            );
                        }
                        
                        // Redirect to dashboard
                        header('Location: ../dashboard.php');
                        exit;
                    } else {
                        $error = "Invalid ID/Email or password";
                    }
                } else {
                    $error = "Invalid ID/Email or password";
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
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
    <title>Login - KLD</title>
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
                <h2>KLD Capstone Tracker</h2>
            </div>
            
            <?php if(!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label>Email or ID Number</label>
                    <input type="text" name="login" placeholder="Email or ID Number" 
                           value="<?php echo htmlspecialchars($login); ?>" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <!-- Remember Me & Forgot Password Row -->
                <div class="remember-me">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit">Login</button>
            </form>
            
            <div class="links">
                <p>Don't have an account? <a href="register.php" class="register-link">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>