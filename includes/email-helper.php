<?php
// includes/email-helper.php
// Handles sending OTP emails

// Load .env file
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Debug: Check if .env loaded
error_log("=== EMAIL HELPER DEBUG ===");
error_log("SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'NOT SET'));
error_log("SMTP_USER: " . (getenv('SMTP_USER') ?: 'NOT SET'));
error_log("SMTP_PASS: " . (getenv('SMTP_PASS') ? 'SET' : 'NOT SET'));
error_log("=========================");

// Check if PHPMailer exists
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload_path)) {
    error_log("ERROR: PHPMailer not found at: " . $autoload_path);
    die("PHPMailer not installed. Run: composer require phpmailer/phpmailer");
}
require_once $autoload_path;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send OTP verification email to user
 */
function sendOTPEmail($to, $name, $otp_code) {
    error_log("sendOTPEmail called - To: $to, OTP: $otp_code");
    
    $subject = "Verify Your Email - KLD Capstone Tracker";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; }
            .header { text-align: center; border-bottom: 2px solid #2D5A27; padding-bottom: 10px; margin-bottom: 20px; }
            .header h2 { color: #2D5A27; margin: 0; }
            .code { font-size: 32px; font-weight: bold; text-align: center; letter-spacing: 5px; padding: 20px; background: #f0f7f0; border-radius: 10px; margin: 20px 0; }
            .footer { text-align: center; font-size: 12px; color: #999; margin-top: 20px; padding-top: 10px; border-top: 1px solid #e0e0e0; }
            .warning { color: #dc3545; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>KLD Capstone Tracker</h2>
                <p>Email Verification</p>
            </div>
            
            <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
            
            <p>Thank you for registering with KLD Capstone Tracker. Please use the verification code below to complete your registration:</p>
            
            <div class='code'>
                " . htmlspecialchars($otp_code) . "
            </div>
            
            <p>This code will expire in <strong>15 minutes</strong>.</p>
            
            <p>If you didn't create this account, please ignore this email.</p>
            
            <div class='footer'>
                <p>Kolehiyo ng Lungsod ng Dasmariñas<br>
                Capstone Tracker System</p>
                <p class='warning'>This is an automated message. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $alt_message = "Dear " . $name . ",\n\n";
    $alt_message .= "Thank you for registering with KLD Capstone Tracker.\n\n";
    $alt_message .= "Your verification code is: " . $otp_code . "\n\n";
    $alt_message .= "This code will expire in 15 minutes.\n\n";
    $alt_message .= "If you didn't create this account, please ignore this email.\n\n";
    $alt_message .= "Best regards,\nKLD Capstone Team";
    
    return sendEmail($to, $name, $subject, $message, $alt_message);
}

/**
 * Generic email sender function
 */
function sendEmail($to, $name, $subject, $html_message, $text_message = null) {
    $smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtp_port = getenv('SMTP_PORT') ?: 587;
    $smtp_user = getenv('SMTP_USER') ?: '';
    $smtp_pass = getenv('SMTP_PASS') ?: '';
    $from_email = getenv('FROM_EMAIL') ?: 'kldcapstonetracker@gmail.com';
    $from_name = getenv('FROM_NAME') ?: 'KLD Capstone Tracker';
    
    // Debug log
    error_log("Sending email via: $smtp_host");
    error_log("From: $from_email, To: $to");
    
    if (empty($smtp_user) || empty($smtp_pass)) {
        error_log("ERROR: SMTP credentials not configured");
        return false;
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Enable verbose debug output (remove after testing)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_port;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_message;
        if ($text_message) {
            $mail->AltBody = $text_message;
        } else {
            $mail->AltBody = strip_tags($html_message);
        }
        
        $mail->send();
        error_log("Email sent successfully to: $to");
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a random 6-digit OTP code
 */
function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}

/**
 * Save OTP to database
 */
function saveOTP($db, $user_id, $email, $otp_code) {
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Delete any existing unverified OTPs for this user
    $delete_query = "DELETE FROM email_verifications WHERE user_id = :user_id AND is_verified = 0";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':user_id', $user_id);
    $delete_stmt->execute();
    
    // Insert new OTP
    $insert_query = "INSERT INTO email_verifications (user_id, email, otp_code, expires_at) 
                     VALUES (:user_id, :email, :otp_code, :expires_at)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':user_id', $user_id);
    $insert_stmt->bindParam(':email', $email);
    $insert_stmt->bindParam(':otp_code', $otp_code);
    $insert_stmt->bindParam(':expires_at', $expires_at);
    
    $result = $insert_stmt->execute();
    error_log("saveOTP result: " . ($result ? 'SUCCESS' : 'FAILED'));
    return $result;
}

/**
 * Verify OTP code
 */
function verifyOTP($db, $user_id, $otp_code) {
    $query = "SELECT * FROM email_verifications 
              WHERE user_id = :user_id 
              AND otp_code = :otp_code 
              AND is_verified = 0 
              AND expires_at > NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':otp_code', $otp_code);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Mark as verified
        $update_query = "UPDATE email_verifications SET is_verified = 1 WHERE user_id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':user_id', $user_id);
        $update_stmt->execute();
        
        // Update users table
        $user_query = "UPDATE users SET email_verified = 1, verified_at = NOW() WHERE id = :user_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $user_id);
        $user_stmt->execute();
        
        error_log("OTP verified for user_id: $user_id");
        return true;
    }
    
    error_log("OTP verification failed for user_id: $user_id");
    return false;
}
?>