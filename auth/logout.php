<?php
require_once '../config/session.php';
require_once '../config/database.php';

Session::start();

// Clear remember me token from database if user is logged in
if (Session::isLoggedIn()) {
    $user_id = Session::get('user_id');
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Delete remember me tokens for this user
    $delete = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    $delete->execute([$user_id]);
}

// Clear remember me cookie
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

// Don't clear test cookie yet - we want to see it
// setcookie('test_working', '', time() - 3600, '/');

// Destroy session
Session::destroy();

// Redirect to home page
header('Location: ../index.php');
exit;
?>