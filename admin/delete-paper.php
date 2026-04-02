<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => ''];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paper_id'])) {
    $paper_id = filter_var($_POST['paper_id'], FILTER_VALIDATE_INT);
    
    if(!$paper_id) {
        $response['message'] = 'Invalid paper ID.';
        echo json_encode($response);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Get paper info to check permissions and get file path
    $query = "SELECT p.*, ct.student_id, ct.title 
              FROM papers p
              JOIN capstone_titles ct ON p.title_id = ct.id
              WHERE p.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$paper_id]);
    $paper = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$paper) {
        $response['message'] = 'Paper not found.';
        echo json_encode($response);
        exit;
    }
    
    // Check permissions: Admin can delete any paper, student can delete their own papers
    $can_delete = false;
    if($role === 'admin') {
        $can_delete = true;
    } elseif($role === 'student' && $paper['student_id'] == $user_id) {
        $can_delete = true;
    } elseif($role === 'adviser' && $paper['adviser_id'] == $user_id) {
        $can_delete = true; // Allow advisers to delete papers too
    }
    
    if(!$can_delete) {
        $response['message'] = 'You are not authorized to delete this paper.';
        echo json_encode($response);
        exit;
    }
    
    // SAFETY CHECK: Validate file path is within uploads directory
    $upload_dir = realpath(dirname(dirname(__FILE__)) . '/uploads/papers/');
    $file_path = realpath($paper['file_path']);
    
    // Check if file exists and is within uploads directory
    if($file_path && strpos($file_path, $upload_dir) === 0 && file_exists($file_path)) {
        if(!unlink($file_path)) {
            error_log("Failed to delete file: " . $paper['file_path']);
        }
    } else {
        // File doesn't exist or is outside uploads directory - log warning
        error_log("Warning: Paper file not found or invalid path: " . $paper['file_path']);
    }
    
    // Delete from database
    $delete = $db->prepare("DELETE FROM papers WHERE id = ?");
    if($delete->execute([$paper_id])) {
        $response['success'] = true;
        $response['message'] = 'Paper deleted successfully!';
    } else {
        $error_info = $delete->errorInfo();
        error_log("Delete paper failed - ID: $paper_id, Error: " . $error_info[2]);
        $response['message'] = 'Failed to delete paper from database.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>