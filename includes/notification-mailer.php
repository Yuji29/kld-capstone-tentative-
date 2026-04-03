<?php
// includes/notification-mailer.php

// Load PHPMailer if Composer autoload exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Check if user wants to receive email notifications
 */
function userWantsEmailNotifications($user_id, $db) {
    try {
        $query = "SELECT email_notifications FROM user_preferences WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no preference set, default to true (send email)
        if (!$pref) {
            return true;
        }
        return $pref['email_notifications'] == 1;
    } catch (PDOException $e) {
        error_log("Check email preference error: " . $e->getMessage());
        return true; // Default to send on error
    }
}

/**
 * Check if user wants deadline reminders
 */
function userWantsDeadlineReminders($user_id, $db) {
    try {
        $query = "SELECT deadline_reminders FROM user_preferences WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pref) {
            return true;
        }
        return $pref['deadline_reminders'] == 1;
    } catch (PDOException $e) {
        error_log("Check deadline reminder error: " . $e->getMessage());
        return true;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using SMTP (Gmail or other SMTP servers)
 */
function sendSMTPEmail($to_email, $to_name, $subject, $html_message) {
    // ===== CONFIGURE YOUR SMTP SETTINGS HERE =====
    // For Gmail:
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $smtp_username = 'kldcapstonetracker@gmail.com'; 
    $smtp_password = 'wfkp dngl apnk pduf';      
    $smtp_encryption = PHPMailer::ENCRYPTION_STARTTLS;
    $from_email = 'noreply@kldcapstone.com';
    $from_name = 'KLD Capstone Tracker';
    // ===========================================
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;  // Set to SMTP::DEBUG_SERVER for testing
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_username;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = $smtp_encryption;
        $mail->Port       = $smtp_port;
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_message;
        $mail->AltBody = strip_tags($html_message);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Alternative: Send using your existing config/mail.php if available
 */
function sendEmail($to_email, $to_name, $subject, $html_message) {
    // Try to use existing mail config if it exists
    if (file_exists(__DIR__ . '/../config/mail.php')) {
        require_once __DIR__ . '/../config/mail.php';
        
        if (class_exists('Mailer')) {
            try {
                $mailer = new Mailer();
                
                // Try common method names
                if (method_exists($mailer, 'sendCustomEmail')) {
                    return $mailer->sendCustomEmail($to_email, $to_name, $subject, $html_message);
                } elseif (method_exists($mailer, 'send')) {
                    return $mailer->send($to_email, $to_name, $subject, $html_message);
                } elseif (method_exists($mailer, 'sendEmail')) {
                    return $mailer->sendEmail($to_email, $to_name, $subject, $html_message);
                }
            } catch (Exception $e) {
                error_log("Mailer error: " . $e->getMessage());
            }
        }
    }
    
    // Fallback to SMTP
    return sendSMTPEmail($to_email, $to_name, $subject, $html_message);
}

/**
 * Send email notification for capstone status changes
 */
function sendCapstoneNotification($to_email, $to_name, $title, $status, $remarks = '', $actor_name = '') {
    
    // Get user_id from email to check preferences
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $to_email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Check if user wants email notifications
        if (!userWantsEmailNotifications($user['id'], $db)) {
            error_log("User {$user['id']} has disabled email notifications. Skipping capstone notification.");
            return false;
        }
    }
    // ===== END OF PREFERENCE CHECK =====
    
    // Validate email
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to_email);
        return false;
    }
    
    $status_display = ucfirst(str_replace('_', ' ', $status));
    $app_name = "KLD Capstone Tracker";
    $actor_name = htmlspecialchars($actor_name ?: 'System');
    $title = htmlspecialchars($title);
    $remarks = htmlspecialchars($remarks);
    $to_name = htmlspecialchars($to_name);
    
    // Determine email subject and message based on status
    switch($status) {
        case 'pending_review':
            $subject = "New Title Pending Your Review";
            $greeting = "Hello $to_name,";
            $message_body = "
                <p>Student <strong>$actor_name</strong> has submitted a capstone title for your review:</p>
                <p><strong>\"$title\"</strong></p>
                <p>Please log in to the system to review and provide feedback.</p>";
            break;
            
        case 'active':
            $subject = "Your Capstone Title Has Been Approved!";
            $greeting = "Hello $to_name,";
            $message_body = "
                <p>Good news! Your capstone title has been <strong>approved</strong> and is now active:</p>
                <p><strong>\"$title\"</strong></p>
                <p>You can now proceed with your research and upload your papers.</p>";
            break;
            
        case 'revisions':
            $subject = "Revisions Requested for Your Capstone Title";
            $greeting = "Hello $to_name,";
            $message_body = "
                <p>Your capstone title requires <strong>revisions</strong>:</p>
                <p><strong>\"$title\"</strong></p>
                " . ($remarks ? "<p><strong>Feedback from reviewer:</strong><br>$remarks</p>" : "") . "
                <p>Please log in to the system to make the necessary changes.</p>";
            break;
            
        case 'completed':
            $subject = "Congratulations! Capstone Title Completed";
            $greeting = "Hello $to_name,";
            $message_body = "
                <p>Great news! Your capstone title has been marked as <strong>completed</strong>:</p>
                <p><strong>\"$title\"</strong></p>
                <p>Congratulations on this achievement! You can view your completed title in the system.</p>";
            break;
            
        default:
            $subject = "Capstone Title Status Update";
            $greeting = "Hello $to_name,";
            $message_body = "
                <p>The status of your capstone title has been updated to <strong>$status_display</strong>:</p>
                <p><strong>\"$title\"</strong></p>";
            break;
    }
    
    // Get base URL from server
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    
    // Build HTML email
    $message = "
    <div style='font-family: \"Segoe UI\", Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
            <div style='background: #2D5A27; color: white; padding: 30px 20px; text-align: center;'>
                <h2 style='margin: 0; font-size: 24px;'>$app_name</h2>
                <p style='margin: 5px 0 0; opacity: 0.9;'>Capstone Title Status Update</p>
            </div>
            <div style='background: white; padding: 30px;'>
                <h3>$greeting</h3>
                
                $message_body
                
                <div style='display: inline-block; padding: 8px 20px; border-radius: 30px; font-weight: 600; background: #e9f2e7; color: #2D5A27; margin: 15px 0; font-size: 14px;'>
                    Current Status: $status_display
                </div>
                
                " . ($remarks ? "<div style='background: #f8fbf8; border-left: 4px solid #2D5A27; padding: 15px; margin: 20px 0; border-radius: 8px;'><strong>Remarks:</strong><br>$remarks</div>" : "") . "
                
                <p style='margin-top: 25px;'>
                    <a href='{$base_url}/titles/browse.php' style='display: inline-block; padding: 14px 30px; background: #2D5A27; color: white; text-decoration: none; border-radius: 40px; font-weight: 600;'>
                        View in System
                    </a>
                </p>
                
                <p style='color: #666; font-size: 14px; margin-top: 25px;'>
                    Thank you for using KLD Capstone Tracker.
                </p>
            </div>
            <div style='text-align: center; padding: 25px; background: #f0f0f0; color: #666; font-size: 13px; border-top: 1px solid #ddd;'>
                <p>© " . date('Y') . " KLD Innovatech. All rights reserved.</p>
                <p>This is an automated message, please do not reply.</p>
            </div>
        </div>
    </div>
    ";
    
    // Send email
    return sendEmail($to_email, $to_name, $subject, $message);
}

/**
 * Send notification for paper upload
 */
function sendPaperUploadNotification($to_email, $to_name, $student_name, $title, $paper_type, $title_id = null) {
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $to_email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if (!userWantsEmailNotifications($user['id'], $db)) {
            error_log("User {$user['id']} has disabled email notifications. Skipping paper upload notification.");
            return false;
        }
    }
    // ===== END OF PREFERENCE CHECK =====
    
    // Validate email
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to_email);
        return false;
    }
    
    $subject = "New Paper Uploaded for Review";
    $app_name = "KLD Capstone Tracker";
    
    $paper_type_display = ucfirst(str_replace('_', ' ', $paper_type));
    $student_name = htmlspecialchars($student_name);
    $title = htmlspecialchars($title);
    $to_name = htmlspecialchars($to_name);
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    $view_url = $title_id ? "{$base_url}/titles/view.php?id={$title_id}" : "{$base_url}/titles/browse.php";
    
    $message = "
    <div style='font-family: \"Segoe UI\", Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden;'>
            <div style='background: #2D5A27; color: white; padding: 30px 20px; text-align: center;'>
                <h2 style='margin: 0;'>$app_name</h2>
            </div>
            <div style='background: white; padding: 30px;'>
                <h3>Hello $to_name,</h3>
                
                <p>Student <strong>$student_name</strong> has uploaded a new paper for your review.</p>
                
                <div style='background: #e9f2e7; padding: 20px; border-radius: 12px; margin: 20px 0;'>
                    <p><strong>Title:</strong> $title</p>
                    <p><strong>Paper Type:</strong> $paper_type_display</p>
                    <p><strong>Uploaded by:</strong> $student_name</p>
                </div>
                
                <p>Please log in to the system to review the uploaded paper.</p>
                
                <p>
                    <a href='{$view_url}' style='display: inline-block; padding: 14px 30px; background: #2D5A27; color: white; text-decoration: none; border-radius: 40px; font-weight: 600;'>
                        Review Paper
                    </a>
                </p>
                
                <p>Thank you for your continued support.</p>
            </div>
            <div style='text-align: center; padding: 25px; background: #f0f0f0; color: #666;'>
                <p>© " . date('Y') . " KLD Innovatech</p>
            </div>
        </div>
    </div>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $message);
}

/**
 * Send notification for adviser request
 */
function sendAdviserRequestNotification($to_email, $to_name, $student_name, $title) {
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $to_email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if (!userWantsEmailNotifications($user['id'], $db)) {
            error_log("User {$user['id']} has disabled email notifications. Skipping adviser request notification.");
            return false;
        }
    }
    // ===== END OF PREFERENCE CHECK =====
    
    // Validate email
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to_email);
        return false;
    }
    
    $subject = "New Adviser Request";
    $app_name = "KLD Capstone Tracker";
    $student_name = htmlspecialchars($student_name);
    $title = htmlspecialchars($title);
    $to_name = htmlspecialchars($to_name);
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    
    $message = "
    <div style='font-family: \"Segoe UI\", Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden;'>
            <div style='background: #2D5A27; color: white; padding: 30px 20px; text-align: center;'>
                <h2 style='margin: 0;'>$app_name</h2>
            </div>
            <div style='background: white; padding: 30px;'>
                <h3>Hello $to_name,</h3>
                
                <p>Student <strong>$student_name</strong> has requested you to be their adviser for the following capstone title:</p>
                
                <div style='background: #e9f2e7; padding: 20px; border-radius: 12px; margin: 20px 0;'>
                    <p><strong>Title:</strong> $title</p>
                    <p><strong>Student:</strong> $student_name</p>
                </div>
                
                <p>Please log in to the system to respond to this request.</p>
                
                <p>
                    <a href='{$base_url}/adviser/requests.php' style='display: inline-block; padding: 14px 30px; background: #2D5A27; color: white; text-decoration: none; border-radius: 40px; font-weight: 600;'>
                        View Request
                    </a>
                </p>
                
                <p>Thank you.</p>
            </div>
            <div style='text-align: center; padding: 25px; background: #f0f0f0; color: #666;'>
                <p>© " . date('Y') . " KLD Innovatech</p>
            </div>
        </div>
    </div>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $message);
}

/**
 * Send notification for new deadlines
 */
function sendDeadlineNotification($to_email, $to_name, $deadline_title, $deadline_datetime, $description, $created_by) {
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $to_email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if (!userWantsEmailNotifications($user['id'], $db)) {
            error_log("User {$user['id']} has disabled email notifications. Skipping deadline notification.");
            return false;
        }
    }
    // ===== END OF PREFERENCE CHECK =====
    
    // Validate email
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to_email);
        return false;
    }
    
    $subject = "New Deadline: " . $deadline_title;
    $app_name = "KLD Capstone Tracker";
    
    // Format date and time
    $deadline_timestamp = strtotime($deadline_datetime);
    $formatted_date = date('F j, Y', $deadline_timestamp);
    $formatted_time = date('g:i A', $deadline_timestamp);
    
    // Calculate days remaining
    $now = time();
    $days_remaining = ceil(($deadline_timestamp - $now) / (60 * 60 * 24));
    
    if($days_remaining > 0) {
        $time_message = $days_remaining == 1 ? "1 day remaining" : "$days_remaining days remaining";
    } elseif($days_remaining == 0) {
        $time_message = "Today is the deadline!";
    } else {
        $time_message = "Deadline has passed";
    }
    
    $deadline_title = htmlspecialchars($deadline_title);
    $description = htmlspecialchars($description);
    $created_by = htmlspecialchars($created_by);
    $to_name = htmlspecialchars($to_name);
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    
    $urgent_color = ($days_remaining <= 3 && $days_remaining >= 0) ? '#dc3545' : '#2D5A27';
    
    $message = "
    <div style='font-family: \"Segoe UI\", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
            <div style='background: #2D5A27; color: white; padding: 30px 20px; text-align: center;'>
                <h2 style='margin: 0; font-size: 24px;'>$app_name</h2>
                <p style='margin: 5px 0 0; opacity: 0.9;'>📅 Deadline Notification</p>
            </div>
            <div style='background: white; padding: 30px;'>
                <h3>Hello $to_name,</h3>
                
                <p>A new deadline has been created by <strong>$created_by</strong> that requires your attention.</p>
                
                <div style='background: linear-gradient(135deg, #f8fbf8 0%, #e9f2e7 100%); border-left: 4px solid #2D5A27; padding: 20px; margin: 20px 0; border-radius: 12px;'>
                    <div style='font-size: 20px; font-weight: 600; color: #2D5A27; margin: 0 0 10px 0;'>$deadline_title</div>
                    
                    <div style='background: #2D5A27; color: white; padding: 12px 20px; border-radius: 30px; display: inline-block; margin: 10px 0; font-weight: 500;'>
                        📅 $formatted_date at $formatted_time
                    </div>
                    
                    <div style='display: inline-block; padding: 8px 16px; background: $urgent_color; color: white; border-radius: 30px; font-size: 14px; font-weight: 600; margin: 10px 0;'>
                        ⏰ $time_message
                    </div>
                    
                    " . (!empty($description) ? "
                    <div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #e0e0e0;'>
                        <strong>Description:</strong><br>
                        " . nl2br($description) . "
                    </div>
                    " : "") . "
                </div>
                
                " . ($days_remaining <= 3 && $days_remaining >= 0 ? "
                <p style='color: #dc3545; font-weight: bold;'>⚠️ This deadline is approaching soon! Please prioritize this task.</p>
                " : "") . "
                
                <p>
                    <a href='{$base_url}/titles/deadlines.php' style='display: inline-block; padding: 14px 30px; background: #2D5A27; color: white; text-decoration: none; border-radius: 40px; font-weight: 600; margin: 20px 0 10px;'>
                        View All Deadlines
                    </a>
                </p>
                
                <p style='color: #666; font-size: 14px; margin-top: 25px;'>
                    Please ensure you complete any requirements before this deadline.
                </p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 25px 0;'>
                
                <p style='color: #666; font-size: 13px;'>
                    <strong>Quick Tips:</strong><br>
                    • Mark this deadline on your personal calendar<br>
                    • Set reminders a few days before<br>
                    • Prepare your requirements in advance
                </p>
            </div>
            <div style='text-align: center; padding: 25px; background: #f0f0f0; color: #666; font-size: 13px; border-top: 1px solid #ddd;'>
                <p>© " . date('Y') . " KLD Innovatech. All rights reserved.</p>
                <p>This is an automated notification from the KLD Capstone Tracker System.</p>
            </div>
        </div>
    </div>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $message);
}