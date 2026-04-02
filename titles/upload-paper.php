<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: ../auth/login.php');
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

$message = '';
$error = '';

// Get title ID from URL
$title_id = isset($_GET['title_id']) ? (int)$_GET['title_id'] : 0;

if(!$title_id) {
    header('Location: browse.php');
    exit;
}

// Get title details to check permissions and status
$query = "SELECT ct.*, s.id as student_id, s.full_name as student_name,
                 a.id as adviser_id, a.full_name as adviser_name, a.email as adviser_email
          FROM capstone_titles ct
          LEFT JOIN users s ON ct.student_id = s.id
          LEFT JOIN users a ON ct.adviser_id = a.id
          WHERE ct.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$title_id]);
$title = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$title) {
    header('Location: browse.php');
    exit;
}

// Check if user has permission to upload papers
$can_upload = false;
$upload_message = '';

if($role === 'admin') {
    $can_upload = true;
} elseif($role === 'student' && $title['student_id'] == $user_id) {
    // Students can only upload when title is ACTIVE
    if($title['status'] === 'active') {
        $can_upload = true;
    } else {
        $upload_message = "You can only upload papers when your title is active.";
    }
} elseif($role === 'adviser' && $title['adviser_id'] == $user_id) {
    $can_upload = true; // Advisers can upload feedback files etc.
}

if(!$can_upload) {
    $_SESSION['flash_message'] = $upload_message ?: "You don't have permission to upload papers.";
    $_SESSION['flash_type'] = "error";
    header('Location: view.php?id=' . $title_id);
    exit;
}

// Handle file upload
if(isset($_POST['upload_paper'])) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please try again.";
    } else {
        $paper_type = $_POST['paper_type'] ?? '';
        
        if(empty($paper_type)) {
            $error = "Please select a paper type.";
        } elseif(!isset($_FILES['paper_file']) || $_FILES['paper_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "Please select a file to upload.";
        } else {
            $file = $_FILES['paper_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed file types
            $allowed_exts = ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'zip'];
            
            if(!in_array($file_ext, $allowed_exts)) {
                $error = "Invalid file type. Allowed types: " . implode(', ', $allowed_exts);
            } elseif($file_size > 50 * 1024 * 1024) { // 50MB max
                $error = "File size too large. Maximum size is 50MB.";
            } else {
                // Create directory if it doesn't exist
                $upload_dir = "../uploads/papers/title_{$title_id}/";
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
                $file_path = $upload_dir . $new_file_name;
                
                if(move_uploaded_file($file_tmp, $file_path)) {
                    // Save to database
                    $insert_query = "INSERT INTO papers (title_id, paper_type, file_path, file_name, file_size, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $db->prepare($insert_query);
                    
                    if($insert_stmt->execute([$title_id, $paper_type, $file_path, $file_name, $file_size, $user_id])) {
                        
                        // Add comment to title
                        $comment = "📎 Paper uploaded: " . $file_name . " (" . ucfirst(str_replace('_', ' ', $paper_type)) . ")";
                        $comment_stmt = $db->prepare("INSERT INTO comments (title_id, user_id, comment) VALUES (?, ?, ?)");
                        $comment_stmt->execute([$title_id, $user_id, $comment]);
                        
                        $message = "Paper uploaded successfully!";
                        
                        // Notify adviser if student uploaded
                        if($role === 'student' && $title['adviser_id']) {
                            $adviser_query = "SELECT email, full_name FROM users WHERE id = ?";
                            $adviser_stmt = $db->prepare($adviser_query);
                            $adviser_stmt->execute([$title['adviser_id']]);
                            $adviser = $adviser_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if($adviser && !empty($adviser['email'])) {
                                $email_message = $full_name . " has uploaded a new paper: " . $file_name;
                                sendCapstoneNotification(
                                    $adviser['email'],
                                    $adviser['full_name'],
                                    $title['title'],
                                    $title['status'],
                                    $email_message,
                                    $full_name
                                );
                            }
                        }
                        
                        // Redirect back to view page after 2 seconds
                        header("refresh:2;url=view.php?id=$title_id");
                    } else {
                        $error = "Failed to save paper information.";
                        // Delete uploaded file if database insert fails
                        unlink($file_path);
                    }
                } else {
                    $error = "Failed to upload file.";
                }
            }
        }
    }
}

// Set variables for navigation include
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Paper - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/titles/upload-paper.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Background Animation Elements -->
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <!-- Include Navigation -->
    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>Upload Paper</h1>
                <p class="header-subtitle">Upload a new paper for: <?php echo htmlspecialchars($title['title']); ?></p>
            </div>
        </div>

        <!-- Messages -->
        <?php if($message): ?>
            <div class="message">
                <span class="material-symbols-outlined">check_circle</span>
                <?php echo $message; ?>
                <p style="margin-top: 10px; font-size: 0.9rem;">Redirecting back to title page...</p>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="error">
                <span class="material-symbols-outlined">error</span>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="title-card form-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">upload</span>
                    Upload Paper
                </h2>
            </div>

            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label>Paper Type *</label>
                    <select name="paper_type" required>
                        <option value="">Select paper type</option>
                        <option value="proposal">Proposal</option>
                        <option value="chapter_1">Chapter 1</option>
                        <option value="chapter_2">Chapter 2</option>
                        <option value="chapter_3">Chapter 3</option>
                        <option value="chapter_4">Chapter 4</option>
                        <option value="chapter_5">Chapter 5</option>
                        <option value="full_manuscript">Full Manuscript</option>
                        <option value="presentation">Presentation</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select File *</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="paper_file" id="paper_file" required accept=".pdf,.doc,.docx,.txt,.ppt,.pptx,.xls,.xlsx,.zip">
                        <div class="file-input-placeholder">
                            <span class="material-symbols-outlined">cloud_upload</span>
                            <span id="file-name">Choose a file...</span>
                        </div>
                    </div>
                    <div class="help-text">
                        Allowed file types: PDF, DOC, DOCX, TXT, PPT, PPTX, XLS, XLSX, ZIP (Max size: 50MB)
                    </div>
                </div>

                <div class="info-box" style="margin-top: 20px;">
                    <span class="material-symbols-outlined">info</span>
                    <p>
                        <strong>Note:</strong> After uploading, your adviser will be notified. 
                        You can discuss the paper through the comments section.
                    </p>
                </div>

                <div class="form-actions">
                    <a href="view.php?id=<?php echo $title_id; ?>" class="btn-secondary">
                        <span class="material-symbols-outlined">cancel</span>
                        Cancel
                    </a>
                    <button type="submit" name="upload_paper" class="btn-primary">
                        <span class="material-symbols-outlined">upload</span>
                        Upload Paper
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
        // Display selected filename
        document.getElementById('paper_file')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'Choose a file...';
            document.getElementById('file-name').textContent = fileName;
        });

        // Mobile menu toggle
        document.getElementById('hamburger-btn')?.addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.toggle('active');
        });

        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobileMenu');
            const hamburgerBtn = document.getElementById('hamburger-btn');
            if (mobileMenu && hamburgerBtn && !mobileMenu.contains(event.target) && !hamburgerBtn.contains(event.target)) {
                mobileMenu.classList.remove('active');
            }
        });
    </script>
</body>
</html>