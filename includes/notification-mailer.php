<?php
// includes/notification-mailer.php
require_once __DIR__ . '/../config/mail.php';

/**
 * Send email notification for capstone status changes
 */
function sendCapstoneNotification($to_email, $to_name, $title, $status, $remarks = '', $actor_name = '') {
    
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
    $base_url = isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    
    // Build HTML email
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
            .header { background: #2D5A27; color: white; padding: 30px 20px; text-align: center; }
            .header h2 { margin: 0; font-size: 24px; }
            .content { background: white; padding: 30px; }
            .status-badge { 
                display: inline-block; 
                padding: 8px 20px; 
                border-radius: 30px; 
                font-weight: 600;
                background: #e9f2e7;
                color: #2D5A27;
                margin: 15px 0;
                font-size: 14px;
            }
            .remarks-box {
                background: #f8fbf8;
                border-left: 4px solid #2D5A27;
                padding: 15px;
                margin: 20px 0;
                border-radius: 8px;
            }
            .button {
                display: inline-block;
                padding: 14px 30px;
                background: #2D5A27;
                color: white;
                text-decoration: none;
                border-radius: 40px;
                font-weight: 600;
                margin: 20px 0 10px;
            }
            .button:hover {
                background: #1e3f1a;
            }
            .footer { 
                text-align: center; 
                padding: 25px; 
                background: #f0f0f0;
                color: #666;
                font-size: 13px;
                border-top: 1px solid #ddd;
            }
            .footer a { color: #2D5A27; text-decoration: none; }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .content { padding: 20px; }
                .button { display: block; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>$app_name</h2>
                <p style='margin: 5px 0 0; opacity: 0.9;'>Capstone Title Status Update</p>
            </div>
            <div class='content'>
                $greeting
                
                $message_body
                
                <div class='status-badge'>
                    Current Status: $status_display
                </div>
                
                " . ($remarks ? "<div class='remarks-box'><strong>Remarks:</strong><br>$remarks</div>" : "") . "
                
                <p style='margin-top: 25px;'>
                    <a href='{$base_url}/titles/browse.php' class='button'>
                        View in System
                    </a>
                </p>
                
                <p style='color: #666; font-size: 14px; margin-top: 25px;'>
                    Thank you for using KLD Capstone Tracker.
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " KLD Innovatech. All rights reserved.</p>
                <p>This is an automated message, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Use your existing Mailer class
    $mailer = new Mailer();
    
    // Send email
    try {
        return $mailer->sendCustomEmail($to_email, $to_name, $subject, $message);
    } catch (Exception $e) {
        error_log("Notification mail error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for paper upload
 */
function sendPaperUploadNotification($to_email, $to_name, $student_name, $title, $paper_type, $title_id = null) {
    
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
    
    $base_url = isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    $view_url = $title_id ? "{$base_url}/titles/view.php?id={$title_id}" : "{$base_url}/titles/browse.php";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden; }
            .header { background: #2D5A27; color: white; padding: 30px 20px; text-align: center; }
            .content { background: white; padding: 30px; }
            .paper-info {
                background: #e9f2e7;
                padding: 20px;
                border-radius: 12px;
                margin: 20px 0;
            }
            .button {
                display: inline-block;
                padding: 14px 30px;
                background: #2D5A27;
                color: white;
                text-decoration: none;
                border-radius: 40px;
                font-weight: 600;
                margin: 20px 0;
            }
            .footer { text-align: center; padding: 25px; background: #f0f0f0; color: #666; }
            @media (max-width: 600px) {
                .button { display: block; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>$app_name</h2>
            </div>
            <div class='content'>
                <h3>Hello $to_name,</h3>
                
                <p>Student <strong>$student_name</strong> has uploaded a new paper for your review.</p>
                
                <div class='paper-info'>
                    <p><strong>Title:</strong> $title</p>
                    <p><strong>Paper Type:</strong> $paper_type_display</p>
                    <p><strong>Uploaded by:</strong> $student_name</p>
                </div>
                
                <p>Please log in to the system to review the uploaded paper.</p>
                
                <p>
                    <a href='{$view_url}' class='button'>
                        Review Paper
                    </a>
                </p>
                
                <p>Thank you for your continued support.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " KLD Innovatech</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mailer = new Mailer();
    try {
        return $mailer->sendCustomEmail($to_email, $to_name, $subject, $message);
    } catch (Exception $e) {
        error_log("Paper upload notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for adviser request
 */
function sendAdviserRequestNotification($to_email, $to_name, $student_name, $title) {
    
    // Validate email
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to_email);
        return false;
    }
    
    $subject = "New Adviser Request";
    $student_name = htmlspecialchars($student_name);
    $title = htmlspecialchars($title);
    $to_name = htmlspecialchars($to_name);
    
    $base_url = isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden; }
            .header { background: #2D5A27; color: white; padding: 30px 20px; text-align: center; }
            .content { background: white; padding: 30px; }
            .request-box {
                background: #e9f2e7;
                padding: 20px;
                border-radius: 12px;
                margin: 20px 0;
            }
            .button {
                display: inline-block;
                padding: 14px 30px;
                background: #2D5A27;
                color: white;
                text-decoration: none;
                border-radius: 40px;
                font-weight: 600;
                margin: 20px 0;
            }
            .footer { text-align: center; padding: 25px; background: #f0f0f0; color: #666; }
            @media (max-width: 600px) {
                .button { display: block; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>KLD Capstone Tracker</h2>
            </div>
            <div class='content'>
                <h3>Hello $to_name,</h3>
                
                <p>Student <strong>$student_name</strong> has requested you to be their adviser for the following capstone title:</p>
                
                <div class='request-box'>
                    <p><strong>Title:</strong> $title</p>
                    <p><strong>Student:</strong> $student_name</p>
                </div>
                
                <p>Please log in to the system to respond to this request.</p>
                
                <p>
                    <a href='{$base_url}/adviser/requests.php' class='button'>
                        View Request
                    </a>
                </p>
                
                <p>Thank you.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " KLD Innovatech</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mailer = new Mailer();
    try {
        return $mailer->sendCustomEmail($to_email, $to_name, $subject, $message);
    } catch (Exception $e) {
        error_log("Adviser request notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for new deadlines
 */
function sendDeadlineNotification($to_email, $to_name, $deadline_title, $deadline_datetime, $description, $created_by) {
    
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
    
    $base_url = isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/kld-capstone' : 'http://localhost:8080/kld-capstone';
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
            .header { background: #2D5A27; color: white; padding: 30px 20px; text-align: center; }
            .header h2 { margin: 0; font-size: 24px; }
            .content { background: white; padding: 30px; }
            .deadline-card {
                background: linear-gradient(135deg, #f8fbf8 0%, #e9f2e7 100%);
                border-left: 4px solid #2D5A27;
                padding: 20px;
                margin: 20px 0;
                border-radius: 12px;
            }
            .deadline-title {
                font-size: 20px;
                font-weight: 600;
                color: #2D5A27;
                margin: 0 0 10px 0;
            }
            .deadline-datetime {
                background: #2D5A27;
                color: white;
                padding: 12px 20px;
                border-radius: 30px;
                display: inline-block;
                margin: 10px 0;
                font-weight: 500;
            }
            .countdown-badge {
                display: inline-block;
                padding: 8px 16px;
                background: " . ($days_remaining <= 3 && $days_remaining >= 0 ? '#dc3545' : '#2D5A27') . ";
                color: white;
                border-radius: 30px;
                font-size: 14px;
                font-weight: 600;
                margin: 10px 0;
            }
            .description-box {
                background: #f5f5f5;
                padding: 15px;
                border-radius: 8px;
                margin: 15px 0;
                border: 1px solid #e0e0e0;
            }
            .button {
                display: inline-block;
                padding: 14px 30px;
                background: #2D5A27;
                color: white;
                text-decoration: none;
                border-radius: 40px;
                font-weight: 600;
                margin: 20px 0 10px;
                transition: background 0.3s;
            }
            .button:hover {
                background: #1e3f1a;
            }
            .footer { 
                text-align: center; 
                padding: 25px; 
                background: #f0f0f0;
                color: #666;
                font-size: 13px;
                border-top: 1px solid #ddd;
            }
            .footer a { color: #2D5A27; text-decoration: none; }
            .urgent { color: #dc3545; font-weight: bold; }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .content { padding: 20px; }
                .button { display: block; text-align: center; }
                .deadline-datetime, .countdown-badge { display: block; text-align: center; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>$app_name</h2>
                <p style='margin: 5px 0 0; opacity: 0.9;'>📅 Deadline Notification</p>
            </div>
            <div class='content'>
                <h3>Hello $to_name,</h3>
                
                <p>A new deadline has been created by <strong>$created_by</strong> that requires your attention.</p>
                
                <div class='deadline-card'>
                    <div class='deadline-title'>$deadline_title</div>
                    
                    <div class='deadline-datetime'>
                        📅 $formatted_date at $formatted_time
                    </div>
                    
                    <div class='countdown-badge'>
                        ⏰ $time_message
                    </div>
                    
                    " . (!empty($description) ? "
                    <div class='description-box'>
                        <strong>Description:</strong><br>
                        " . nl2br($description) . "
                    </div>
                    " : "") . "
                </div>
                
                " . ($days_remaining <= 3 && $days_remaining >= 0 ? "
                <p class='urgent'>⚠️ This deadline is approaching soon! Please prioritize this task.</p>
                " : "") . "
                
                <p>
                    <a href='{$base_url}/titles/deadlines.php' class='button'>
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
            <div class='footer'>
                <p>© " . date('Y') . " KLD Innovatech. All rights reserved.</p>
                <p>This is an automated notification from the KLD Capstone Tracker System.</p>
                <p>
                    <a href='{$base_url}/privacy.php'>Privacy Policy</a> |
                    <a href='{$base_url}/contact.php'>Contact Support</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mailer = new Mailer();
    try {
        return $mailer->sendCustomEmail($to_email, $to_name, $subject, $message);
    } catch (Exception $e) {
        error_log("Deadline notification error: " . $e->getMessage());
        return false;
    }
}
?>