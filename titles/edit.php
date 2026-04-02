<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

// Allow only students to access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
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
$full_name = $_SESSION['full_name'];

// Get title ID from URL
$title_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$title_id) {
    $_SESSION['flash_message'] = "No title ID provided.";
    $_SESSION['flash_type'] = "error";
    header('Location: browse.php');
    exit;
}

// Get title details - only if it belongs to this student and is in 'revisions' status
$query = "SELECT ct.*, 
                 c.name as category_name,
                 a.full_name as adviser_name,
                 a.email as adviser_email
          FROM capstone_titles ct
          LEFT JOIN categories c ON ct.category_id = c.id
          LEFT JOIN users a ON ct.adviser_id = a.id
          WHERE ct.id = ? AND ct.student_id = ? AND ct.status = 'revisions'";

$stmt = $db->prepare($query);
$stmt->execute([$title_id, $user_id]);
$title = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$title) {
    $_SESSION['flash_message'] = "Title not found or you cannot edit this title right now.";
    $_SESSION['flash_type'] = "error";
    header('Location: browse.php');
    exit;
}

// Get categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $db->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

// Get advisers for dropdown (optional - if student can change adviser)
$advisers_query = "SELECT id, full_name, email FROM users WHERE role='adviser' ORDER BY full_name";
$advisers = $db->query($advisers_query)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_title'])) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $new_title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $new_abstract = htmlspecialchars(trim($_POST['abstract'] ?? ''), ENT_QUOTES, 'UTF-8');
        $new_category_id = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $new_adviser_id = !empty($_POST['adviser_id']) ? filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT) : null;
        $new_team_members = htmlspecialchars(trim($_POST['team_members'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        // Validate input
        if(empty($new_title)) {
            $_SESSION['flash_message'] = "Title is required.";
            $_SESSION['flash_type'] = "error";
        } elseif(!$new_category_id) {
            $_SESSION['flash_message'] = "Please select a valid category.";
            $_SESSION['flash_type'] = "error";
        } else {
            // Check for duplicate title (excluding current title)
            $check_query = "SELECT id FROM capstone_titles WHERE title = ? AND id != ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$new_title, $title_id]);
            
            if($check_stmt->rowCount() > 0) {
                $_SESSION['flash_message'] = "This title already exists. Please choose a different title.";
                $_SESSION['flash_type'] = "error";
            } else {
                // Start transaction
                $db->beginTransaction();
                
                try {
                    // Update the title - change status back to pending_review
                    $update_query = "UPDATE capstone_titles 
                                    SET title = ?, abstract = ?, category_id = ?, adviser_id = ?, 
                                        team_members = ?, status = 'pending_review', updated_at = NOW()
                                    WHERE id = ? AND student_id = ?";
                    $update_stmt = $db->prepare($update_query);
                    
                    if($update_stmt->execute([$new_title, $new_abstract, $new_category_id, $new_adviser_id, $new_team_members, $title_id, $user_id])) {
                        
                        // Add comment about the revision
                        $comment = "Student has edited the title and submitted for review again.";
                        $comment_stmt = $db->prepare("INSERT INTO comments (title_id, user_id, comment) VALUES (?, ?, ?)");
                        $comment_stmt->execute([$title_id, $user_id, $comment]);
                        
                        // Send notification to adviser
                        if($title['adviser_id']) {
                            $adviser_query = "SELECT email, full_name FROM users WHERE id = ?";
                            $adviser_stmt = $db->prepare($adviser_query);
                            $adviser_stmt->execute([$title['adviser_id']]);
                            $adviser = $adviser_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if($adviser && !empty($adviser['email'])) {
                                $message = "Student has revised the title and submitted for your review again.";
                                sendCapstoneNotification(
                                    $adviser['email'],
                                    $adviser['full_name'],
                                    $new_title,
                                    'pending_review',
                                    $message,
                                    $full_name
                                );
                            }
                        }
                        
                        $db->commit();
                        
                        $_SESSION['flash_message'] = "Title updated successfully and sent for review!";
                        $_SESSION['flash_type'] = "success";
                        
                        header('Location: view.php?id=' . $title_id);
                        exit;
                        
                    } else {
                        throw new Exception("Failed to update title.");
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Title edit error: " . $e->getMessage());
                    $_SESSION['flash_message'] = "Failed to update title. Please try again.";
                    $_SESSION['flash_type'] = "error";
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
    <title>Edit Title - Student Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/titles/edit.css?v=<?php echo time(); ?>">
    <style>
        .revision-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .revision-banner .material-symbols-outlined {
            color: #856404;
            font-size: 24px;
        }
        
        .revision-banner p {
            margin: 0;
            color: #856404;
            font-weight: 500;
        }
        
        .revision-banner small {
            color: #856404;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <div class="header">
            <div>
                <h1>Edit Title</h1>
                <p class="header-subtitle">Make changes to your title based on adviser feedback</p>
            </div>
        </div>

        <!-- Revision Notice -->
        <div class="revision-banner">
            <span class="material-symbols-outlined">info</span>
            <div>
                <p>Your adviser has requested revisions for this title.</p>
                <small>Please review the feedback below and make the necessary changes. After submitting, your title will be sent for review again.</small>
            </div>
        </div>

        <?php 
        $flash_message = $_SESSION['flash_message'] ?? '';
        $flash_type = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        if($flash_message): 
        ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="title-card">
            <form method="POST" class="edit-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">title</span>
                        Capstone Title *
                    </label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($title['title']); ?>" required>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>
                            <span class="material-symbols-outlined">category</span>
                            Category *
                        </label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($title['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="material-symbols-outlined">school</span>
                            Adviser (Optional)
                        </label>
                        <select name="adviser_id">
                            <option value="">Select Adviser</option>
                            <?php foreach($advisers as $adv): ?>
                                <option value="<?php echo $adv['id']; ?>" <?php echo ($title['adviser_id'] == $adv['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($adv['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="material-symbols-outlined">group</span>
                            Team Members
                        </label>
                        <input type="text" name="team_members" value="<?php echo htmlspecialchars($title['team_members'] ?? ''); ?>" placeholder="e.g., Juan Dela Cruz, Maria Santos">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">description</span>
                        Abstract
                    </label>
                    <textarea name="abstract" rows="8" placeholder="Describe your capstone project..."><?php echo htmlspecialchars($title['abstract'] ?? ''); ?></textarea>
                </div>

                <div class="info-box">
                    <span class="material-symbols-outlined">info</span>
                    <p>
                        <strong>Note:</strong> After making changes, your title will be sent back to your adviser for review.
                        You will be notified once they make a decision.
                    </p>
                </div>

                <div class="form-actions">
                    <a href="view.php?id=<?php echo $title_id; ?>" class="btn-secondary">
                        <span class="material-symbols-outlined">cancel</span>
                        Cancel
                    </a>
                    <button type="submit" name="update_title" class="btn-primary">
                        <span class="material-symbols-outlined">send</span>
                        Submit for Review
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
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