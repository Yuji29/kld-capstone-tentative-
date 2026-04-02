<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser'){
    header('Location: ../auth/login.php');
    exit;
}

// Check CSRF token
if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['flash_message'] = "Invalid security token.";
    $_SESSION['flash_type'] = "error";
    header('Location: pending.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$title_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_status = isset($_GET['status']) ? $_GET['status'] : '';

if(!$title_id || !in_array($new_status, ['completed', 'active', 'revisions'])) {
    $_SESSION['flash_message'] = "Invalid request.";
    $_SESSION['flash_type'] = "error";
    header('Location: pending.php');
    exit;
}

// Verify this title belongs to this adviser
$check_query = "SELECT ct.*, u.email as student_email, u.full_name as student_name 
                FROM capstone_titles ct
                JOIN users u ON ct.student_id = u.id
                WHERE ct.id = ? AND ct.adviser_id = ?";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute([$title_id, $_SESSION['user_id']]);
$title = $check_stmt->fetch(PDO::FETCH_ASSOC);

if(!$title) {
    $_SESSION['flash_message'] = "Title not found or you don't have permission.";
    $_SESSION['flash_type'] = "error";
    header('Location: pending.php');
    exit;
}

// Update status
$update_query = "UPDATE capstone_titles SET status = ?, updated_at = NOW() WHERE id = ?";
$update_stmt = $db->prepare($update_query);

if($update_stmt->execute([$new_status, $title_id])) {
    // Send notification to student
    if(!empty($title['student_email'])) {
        $status_message = "Your title has been marked as " . str_replace('_', ' ', $new_status) . " by your adviser.";
        sendCapstoneNotification(
            $title['student_email'],
            $title['student_name'],
            $title['title'],
            $new_status,
            $status_message,
            $_SESSION['full_name']
        );
    }
    
    $_SESSION['flash_message'] = "Title status updated successfully!";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Failed to update status.";
    $_SESSION['flash_type'] = "error";
}

header('Location: pending.php?filter=' . $new_status);
exit;