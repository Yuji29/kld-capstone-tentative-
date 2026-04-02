<?php

// At the very top of config/mail.php, after <?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../mail_error.log');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    private $mail;
    private $isConfigured = false;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'kldcapstonetracker@gmail.com';  
            $this->mail->Password   = 'wfkpdnglapnkpduf';      
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = 587;
            $this->mail->setFrom('kldcapstonetracker@gmail.com', 'KLD Capstone Tracker');
            $this->mail->isHTML(true);
            
            // Additional settings for better delivery
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            $this->mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            $this->isConfigured = true;
        } catch (Exception $e) {
            error_log("Mailer initialization error: " . $e->getMessage());
            $this->isConfigured = false;
        }
    }
    
    public function sendResetLink($to, $name, $link) {
        if (!$this->isConfigured) {
            error_log("Mailer not configured. Cannot send email to: $to");
            return false;
        }
        
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($to, $name);
            $this->mail->Subject = 'Password Reset Request - KLD Capstone Tracker';
            $this->mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f4f7f4; }
                        .content { background: white; padding: 30px; border-radius: 20px; }
                        h2 { color: #2D5A27; }
                        .btn { background: #2D5A27; color: white; padding: 12px 30px; text-decoration: none; border-radius: 30px; display: inline-block; }
                        .footer { color: #666; font-size: 12px; margin-top: 20px; }
                        hr { border: 1px solid #e0e0e0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='content'>
                            <h2>Password Reset Request</h2>
                            <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                            <p>We received a request to reset your password. Click the button below to proceed:</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='" . htmlspecialchars($link) . "' class='btn'>Reset Password</a>
                            </div>
                            <p>This link will expire in <strong>1 hour</strong>.</p>
                            <p>If you didn't request this, please ignore this email.</p>
                            <hr>
                            <p class='footer'>KLD Capstone Tracker</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            // Plain text alternative for email clients that don't support HTML
            $this->mail->AltBody = "Password Reset Request\n\n";
            $this->mail->AltBody .= "Hello {$name},\n\n";
            $this->mail->AltBody .= "We received a request to reset your password. Click the link below to proceed:\n\n";
            $this->mail->AltBody .= $link . "\n\n";
            $this->mail->AltBody .= "This link will expire in 1 hour.\n\n";
            $this->mail->AltBody .= "If you didn't request this, please ignore this email.\n\n";
            $this->mail->AltBody .= "KLD Capstone Tracker";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    // Method for capstone title notifications
    public function sendCapstoneNotification($to, $name, $title, $status, $message, $from_name) {
        if (!$this->isConfigured) {
            error_log("Mailer not configured. Cannot send email to: $to");
            return false;
        }
        
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($to, $name);
            
            $statusColors = [
                'pending_review' => '#ffc107',
                'active' => '#28a745',
                'revisions' => '#fd7e14',
                'completed' => '#6c757d'
            ];
            $statusColor = $statusColors[$status] ?? '#2D5A27';
            
            $this->mail->Subject = 'Capstone Title Update - ' . $title;
            $this->mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f4f7f4; }
                        .content { background: white; padding: 30px; border-radius: 20px; }
                        h2 { color: #2D5A27; }
                        .status { display: inline-block; padding: 5px 15px; border-radius: 20px; background: {$statusColor}; color: white; }
                        .footer { color: #666; font-size: 12px; margin-top: 20px; }
                        hr { border: 1px solid #e0e0e0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='content'>
                            <h2>Capstone Title Update</h2>
                            <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                            <p>" . htmlspecialchars($message) . "</p>
                            <div style='margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 10px;'>
                                <strong>Title:</strong> " . htmlspecialchars($title) . "<br>
                                <strong>Status:</strong> <span class='status'>" . ucfirst(str_replace('_', ' ', $status)) . "</span>
                            </div>
                            <p>You can view more details by logging into your account.</p>
                            <hr>
                            <p class='footer'>KLD Capstone Tracker<br>Sent by: " . htmlspecialchars($from_name) . "</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $this->mail->AltBody = "Capstone Title Update\n\n";
            $this->mail->AltBody .= "Hello {$name},\n\n";
            $this->mail->AltBody .= $message . "\n\n";
            $this->mail->AltBody .= "Title: {$title}\n";
            $this->mail->AltBody .= "Status: " . ucfirst(str_replace('_', ' ', $status)) . "\n\n";
            $this->mail->AltBody .= "You can view more details by logging into your account.\n\n";
            $this->mail->AltBody .= "KLD Capstone Tracker\nSent by: {$from_name}";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Capstone notification error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    // Method for deadline notifications
    public function sendDeadlineNotification($to, $name, $title, $datetime, $description, $from_name) {
        if (!$this->isConfigured) {
            error_log("Mailer not configured. Cannot send email to: $to");
            return false;
        }
        
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($to, $name);
            
            $this->mail->Subject = 'New Deadline: ' . $title;
            $this->mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f4f7f4; }
                        .content { background: white; padding: 30px; border-radius: 20px; }
                        h2 { color: #2D5A27; }
                        .deadline-info { background: #fff3cd; padding: 15px; border-radius: 10px; margin: 20px 0; }
                        .footer { color: #666; font-size: 12px; margin-top: 20px; }
                        hr { border: 1px solid #e0e0e0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='content'>
                            <h2>New Deadline</h2>
                            <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                            <p>A new deadline has been set for your capstone project:</p>
                            <div class='deadline-info'>
                                <strong>📌 Title:</strong> " . htmlspecialchars($title) . "<br>
                                <strong>📅 Date/Time:</strong> " . htmlspecialchars($datetime) . "<br>
                                " . (!empty($description) ? "<strong>📝 Details:</strong> " . htmlspecialchars($description) . "<br>" : "") . "
                            </div>
                            <p>Please make sure to complete the required tasks before the deadline.</p>
                            <hr>
                            <p class='footer'>KLD Capstone Tracker<br>Sent by: " . htmlspecialchars($from_name) . "</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $this->mail->AltBody = "New Deadline\n\n";
            $this->mail->AltBody .= "Hello {$name},\n\n";
            $this->mail->AltBody .= "A new deadline has been set:\n";
            $this->mail->AltBody .= "Title: {$title}\n";
            $this->mail->AltBody .= "Date/Time: {$datetime}\n";
            if (!empty($description)) {
                $this->mail->AltBody .= "Details: {$description}\n";
            }
            $this->mail->AltBody .= "\nPlease log in to view more details.\n\n";
            $this->mail->AltBody .= "KLD Capstone Tracker\nSent by: {$from_name}";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Deadline notification error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    // Check if mailer is configured
    public function isConfigured() {
        return $this->isConfigured;
    }
}
?>