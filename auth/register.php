<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/email-helper.php';

Session::start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (Session::isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit();
}

$error = '';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// ===== FETCH DEPARTMENTS =====
try {
    $dept_query = "SELECT id, name FROM departments ORDER BY name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Department fetch error: " . $e->getMessage());
    $departments = [];
}

// Get user's IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token first
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please try again.";
    } 
    // Only proceed if CSRF token is valid
    else {
        $full_name = trim($_POST['full_name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $department = trim($_POST['department'] ?? '');
        $agree_privacy = isset($_POST['agree_privacy']) ? true : false;
        
        // Validation checks
        if (empty($full_name) || empty($id_number) || empty($email) || empty($password)) {
            $error = "All fields are required";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = "Password must contain at least one uppercase letter";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = "Password must contain at least one lowercase letter";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = "Password must contain at least one number";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!preg_match('/@kld\.edu\.ph$/i', $email)) {
            $allowed_personal = ['yujipelegrino@gmail.com'];
            if (!in_array($email, $allowed_personal)) {
                $error = "Only @kld.edu.ph email addresses are allowed to register. Please use your school email.";
            }
        } elseif (!$agree_privacy) {
            $error = "You must agree to the Privacy Policy and Terms of Service to register";
        } else {
            try {
                // ===== CHECK IF USER EXISTS =====
                $check_query = "SELECT id FROM users WHERE id_number = :id_number OR email = :email";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':id_number', $id_number);
                $check_stmt->bindParam(':email', $email);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    $error = "ID Number or email already exists";
                } else {
                    // ===== HASH PASSWORD =====
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // ===== GET USER IP AND USER AGENT =====
                    $ip_address = getUserIP();
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $privacy_version = '1.0';
                    
                    // ===== INSERT NEW USER =====
                    $insert_query = "INSERT INTO users (
                        id_number, email, password, full_name, role, department, 
                        agreed_privacy, agreed_terms, agreed_at, privacy_version, ip_address, user_agent
                    ) VALUES (
                        :id_number, :email, :password, :full_name, 'student', :department,
                        :agree_privacy, :agree_terms, NOW(), :privacy_version, :ip_address, :user_agent
                    )";
                    
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':id_number', $id_number);
                    $insert_stmt->bindParam(':email', $email);
                    $insert_stmt->bindParam(':password', $hashed_password);
                    $insert_stmt->bindParam(':full_name', $full_name);
                    $insert_stmt->bindParam(':department', $department);
                    $insert_stmt->bindParam(':agree_privacy', $agree_privacy);
                    $insert_stmt->bindParam(':agree_terms', $agree_privacy);
                    $insert_stmt->bindParam(':privacy_version', $privacy_version);
                    $insert_stmt->bindParam(':ip_address', $ip_address);
                    $insert_stmt->bindParam(':user_agent', $user_agent);
                    
                    if ($insert_stmt->execute()) {
                        // Get the new user's ID
                        $new_user_id = $db->lastInsertId();
                        
                        // Generate OTP
                        $otp_code = generateOTP();
                        
                        // Save OTP to database
                        $otp_saved = saveOTP($db, $new_user_id, $email, $otp_code);
                        
                        // Send OTP email
                        $email_sent = sendOTPEmail($email, $full_name, $otp_code);
                        
                        // Debug logging
                        error_log("=== REGISTRATION DEBUG ===");
                        error_log("User ID: " . $new_user_id);
                        error_log("Email: " . $email);
                        error_log("OTP Code: " . $otp_code);
                        error_log("OTP Saved: " . ($otp_saved ? 'YES' : 'NO'));
                        error_log("Email Sent: " . ($email_sent ? 'YES' : 'NO'));
                        error_log("=========================");
                        
                        // Store in session for verification page
                        $_SESSION['pending_verification'] = true;
                        $_SESSION['pending_user_id'] = $new_user_id;
                        
                        // Set flash message
                        if ($email_sent) {
                            $_SESSION['flash_message'] = "Registration successful! A verification code has been sent to your email.";
                            $_SESSION['flash_type'] = "success";
                        } else {
                            $_SESSION['flash_message'] = "Registration successful but we couldn't send the verification email. Please use the code below or request a new one.";
                            $_SESSION['flash_type'] = "warning";
                            $_SESSION['debug_otp'] = $otp_code; // For debugging only
                        }
                        
                        // Redirect to verification page
                        header('Location: verify-otp.php');
                        exit();
                    } else {
                        $error_info = $insert_stmt->errorInfo();
                        error_log("Registration failed: " . $error_info[2]);
                        $error = "Registration failed. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Registration database error: " . $e->getMessage());
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Register - KLD Capstone</title>
    <link rel="stylesheet" href="../css/auth.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0">
</head>
<body>
    <div class="auth-container wide">
        <!-- Back to Home Button -->
        <div class="back-to-home">
            <a href="../index.php" class="back-btn">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Home
            </a>
        </div>
        
        <div class="auth-box wide">
            <div class="logo">
                <img src="../Images/kld logo.png" alt="KLD">
                <h2>Create Account</h2>
            </div>

            <?php if($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" maxlength="100" placeholder="Enter full name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>ID Number *</label>
                        <input type="text" name="id_number" maxlength="50" placeholder="Enter your ID number" value="<?php echo htmlspecialchars($id_number ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department" required>
                            <option value="">Select Department</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['name']); ?>" 
                                    <?php echo (isset($department) && $department == $dept['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Email Address *</label>
                        <input type="email" name="email" maxlength="100" autocomplete="on" placeholder="Enter your email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" minlength="6" placeholder="Create a password" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" name="confirm_password" minlength="6" placeholder="Confirm password" required>
                    </div>
                </div>

                <!-- Privacy Policy Checkbox -->
                <div class="privacy-policy">
                    <label class="checkbox-container">
                        <input type="checkbox" name="agree_privacy" value="1" required>
                        <span class="checkbox-text">
                            I agree to the <a href="javascript:void(0)" onclick="openPrivacyModal()" class="privacy-link">Privacy Policy</a> and <a href="javascript:void(0)" onclick="openTermsModal()" class="privacy-link">Terms of Service</a>
                        </span>
                    </label>
                </div>

                <button type="submit">Create Account</button>
            </form>

            <div class="links">
                <p>Already have an account? <a href="login.php" class="login-link">Login here</a></p>
            </div>
        </div>
    </div>

    <!-- Include Privacy Modals -->
    <?php include '../includes/privacy-modals.php'; ?>
</body>
</html>