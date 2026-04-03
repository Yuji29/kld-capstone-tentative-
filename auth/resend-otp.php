<?php
session_start();
require_once '../config/database.php';
require_once '../includes/email-helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['pending_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No pending verification found']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['pending_user_id'];

// Get user info
$user_query = "SELECT id, full_name, email FROM users WHERE id = :user_id AND email_verified = 0";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found or already verified']);
    exit;
}

$new_otp = generateOTP();

if (saveOTP($db, $user['id'], $user['email'], $new_otp)) {
    if (sendOTPEmail($user['email'], $user['full_name'], $new_otp)) {
        echo json_encode(['success' => true, 'message' => 'New verification code sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to generate new code']);
}
?>