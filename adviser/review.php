<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

// Allow only advisers to access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser'){
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
    header('Location: pending.php');
    exit;
}

// Get title details with all related info
$query = "SELECT ct.*, 
                 c.name as category_name,
                 c.color as category_color,
                 s.id as student_id,
                 s.full_name as student_name,
                 s.email as student_email,
                 s.id_number as student_id_number,
                 s.department as student_department,
                 a.full_name as adviser_name,
                 a.email as adviser_email
          FROM capstone_titles ct
          LEFT JOIN categories c ON ct.category_id = c.id
          LEFT JOIN users s ON ct.student_id = s.id
          LEFT JOIN users a ON ct.adviser_id = a.id
          WHERE ct.id = ? AND ct.adviser_id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$title_id, $user_id]);
$title = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$title) {
    $_SESSION['flash_message'] = "Title not found or you don't have permission to review it.";
    $_SESSION['flash_type'] = "error";
    header('Location: pending.php');
    exit;
}

// Get papers for this title
$papers_query = "SELECT p.*, u.full_name as uploaded_by_name 
                 FROM papers p
                 LEFT JOIN users u ON p.uploaded_by = u.id
                 WHERE p.title_id = ?
                 ORDER BY p.uploaded_at DESC";
$papers_stmt = $db->prepare($papers_query);
$papers_stmt->execute([$title_id]);
$papers = $papers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent comments
$comments_query = "SELECT c.*, u.full_name, u.role 
                   FROM comments c
                   JOIN users u ON c.user_id = u.id
                   WHERE c.title_id = ?
                   ORDER BY c.created_at DESC
                   LIMIT 20";
$comments_stmt = $db->prepare($comments_query);
$comments_stmt->execute([$title_id]);
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if student has made changes after revisions
$revision_edited = false;
if($title['status'] === 'revisions') {
    // Check if there's a comment indicating student made changes
    $check_edit = $db->prepare("SELECT COUNT(*) FROM comments 
                                WHERE title_id = ? AND user_id = ? 
                                AND comment LIKE '%edited%' 
                                AND created_at > ?");
    $check_edit->execute([$title_id, $title['student_id'], $title['updated_at']]);
    $revision_edited = $check_edit->fetchColumn() > 0;
}

// Handle review submission
if(isset($_POST['submit_review'])) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $decision = $_POST['decision'] ?? '';
        $feedback = htmlspecialchars(trim($_POST['feedback'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        if(empty($decision)) {
            $_SESSION['flash_message'] = "Please select a decision.";
            $_SESSION['flash_type'] = "error";
        } else {
            // Determine new status based on current status and decision
            $new_status = '';
            $remarks = '';
            $redirect_page = 'pending.php';
            $notification_message = '';
            
            switch($title['status']) {
                case 'pending_review':
                    // Initial review
                    switch($decision) {
                        case 'approve':
                            $new_status = 'active';
                            $remarks = "Your title has been approved and is now active. You can now upload your papers.";
                            $notification_message = "Your title has been approved!";
                            break;
                        case 'revisions':
                            $new_status = 'revisions';
                            $remarks = "Revisions are requested for your title. Please edit your title based on the feedback.";
                            $notification_message = "Revisions requested for your title.";
                            break;
                        default:
                            $_SESSION['flash_message'] = "Invalid decision for pending review.";
                            $_SESSION['flash_type'] = "error";
                            break;
                    }
                    break;
                    
                case 'revisions':
                    // Review after student made revisions
                    switch($decision) {
                        case 'approve':
                            $new_status = 'active';
                            $remarks = "Your revised title has been approved and is now active.";
                            $notification_message = "Your revised title has been approved!";
                            break;
                        case 'revisions':
                            $new_status = 'revisions';
                            $remarks = "Further revisions are needed. Please review the feedback carefully.";
                            $notification_message = "Additional revisions requested.";
                            break;
                        default:
                            $_SESSION['flash_message'] = "Invalid decision for revisions.";
                            $_SESSION['flash_type'] = "error";
                            break;
                    }
                    break;
                    
                case 'active':
                    // Review active title (for completion)
                    if($decision === 'complete') {
                        $new_status = 'completed';
                        $remarks = "Congratulations! Your title has been marked as completed.";
                        $notification_message = "Your title has been completed!";
                        $redirect_page = '../dashboard.php';
                    } else {
                        $_SESSION['flash_message'] = "Invalid decision for active title.";
                        $_SESSION['flash_type'] = "error";
                    }
                    break;
                    
                default:
                    $_SESSION['flash_message'] = "This title cannot be reviewed in its current status.";
                    $_SESSION['flash_type'] = "error";
                    break;
            }
            
            if(empty($_SESSION['flash_message']) && $new_status) {
                // Update title status
                $update_query = "UPDATE capstone_titles SET status = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if($update_stmt->execute([$new_status, $title_id])) {
                    // Add feedback as a comment
                    if(!empty($feedback)) {
                        $comment_query = "INSERT INTO comments (title_id, user_id, comment) VALUES (?, ?, ?)";
                        $comment_stmt = $db->prepare($comment_query);
                        $comment_stmt->execute([$title_id, $user_id, "Review Feedback: " . $feedback]);
                    }
                    
                    // Add system comment for status change
                    $system_comment = "Status changed from '" . $title['status'] . "' to '" . $new_status . "' by " . $full_name;
                    $system_comment_stmt = $db->prepare("INSERT INTO comments (title_id, user_id, comment, is_system) VALUES (?, ?, ?, 1)");
                    $system_comment_stmt->execute([$title_id, $user_id, $system_comment]);
                    
                    // Send notification to student
                    if(!empty($title['student_email'])) {
                        $email_content = $feedback ?: $remarks;
                        sendCapstoneNotification(
                            $title['student_email'],
                            $title['student_name'],
                            $title['title'],
                            $new_status,
                            $email_content,
                            $full_name
                        );
                    }
                    
                    $_SESSION['flash_message'] = "Review submitted successfully!";
                    $_SESSION['flash_type'] = "success";
                    
                    // Redirect based on decision
                    header('Location: ' . $redirect_page);
                    exit;
                    
                } else {
                    $_SESSION['flash_message'] = "Failed to update title status.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        }
    }
    // If there's an error, redirect back to the review page
    header('Location: review.php?id=' . $title_id);
    exit;
}

// Determine safe back URL
$back_url = 'pending.php';
if(isset($_SERVER['HTTP_REFERER']) && 
   !strpos($_SERVER['HTTP_REFERER'], 'review.php')) {
    $back_url = $_SERVER['HTTP_REFERER'];
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
    <title>Review Title - Adviser Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/adviser/adviser-review.css?v=<?php echo time(); ?>">
    <style>
        .revision-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .revision-notice .material-symbols-outlined {
            color: #856404;
        }
        
        .revision-notice p {
            margin: 0;
            color: #856404;
        }
        
        .revision-notice p strong {
            font-weight: 600;
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
                <h1>Review Capstone Title</h1>
                <p class="header-subtitle">
                    Current Status: 
                    <span class="status-badge <?php echo $title['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                    </span>
                    <?php if($title['status'] === 'revisions'): ?>
                        <?php if($revision_edited): ?>
                            <span class="status-badge pending_review" style="margin-left: 10px;">
                                Student has made changes - Ready for review
                            </span>
                        <?php else: ?>
                            <span class="status-badge revisions" style="margin-left: 10px;">
                                Waiting for student to make changes
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
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

        <!-- Show revision notice if applicable -->
        <?php if($title['status'] === 'revisions' && $revision_edited): ?>
            <div class="revision-notice">
                <span class="material-symbols-outlined">info</span>
                <p>
                    <strong>Student has made changes to this title.</strong> 
                    Please review the updated content and provide your decision.
                </p>
            </div>
        <?php endif; ?>

        <div class="review-grid">
            <!-- Left Column - Title Information -->
            <div class="left-column">
                <div class="title-card">
                    <div class="card-header">
                        <div class="title-badges">
                            <span class="category-badge" style="background: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>20; color: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>;">
                                <?php echo htmlspecialchars($title['category_name'] ?? 'Uncategorized'); ?>
                            </span>
                        </div>
                    </div>

                    <h1 class="title"><?php echo htmlspecialchars($title['title']); ?></h1>

                    <div class="meta-info">
                        <div class="meta-item">
                            <span class="material-symbols-outlined">person</span>
                            <div>
                                <strong>Student</strong>
                                <p><?php echo htmlspecialchars($title['student_name'] ?? 'Unknown'); ?></p>
                                <small><?php echo htmlspecialchars($title['student_id_number'] ?? ''); ?></small>
                                <small><?php echo htmlspecialchars($title['student_email'] ?? ''); ?></small>
                            </div>
                        </div>

                        <div class="meta-item">
                            <span class="material-symbols-outlined">calendar_today</span>
                            <div>
                                <strong>Submitted</strong>
                                <p><?php echo date('F j, Y', strtotime($title['created_at'])); ?></p>
                                <small><?php echo date('g:i A', strtotime($title['created_at'])); ?></small>
                            </div>
                        </div>

                        <div class="meta-item">
                            <span class="material-symbols-outlined">update</span>
                            <div>
                                <strong>Last Updated</strong>
                                <p><?php echo date('F j, Y', strtotime($title['updated_at'])); ?></p>
                                <small><?php echo date('g:i A', strtotime($title['updated_at'])); ?></small>
                            </div>
                        </div>
                    </div>

                    <?php if($title['abstract']): ?>
                    <div class="abstract-section">
                        <h3>Abstract</h3>
                        <div class="abstract-content">
                            <?php echo nl2br(htmlspecialchars($title['abstract'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($papers)): ?>
                    <div class="papers-section">
                        <h3>Uploaded Papers</h3>
                        <div class="papers-list">
                            <?php foreach($papers as $paper): ?>
                                <div class="paper-item">
                                    <span class="material-symbols-outlined">description</span>
                                    <div class="paper-info">
                                        <strong><?php echo htmlspecialchars($paper['file_name']); ?></strong>
                                        <small>Uploaded by <?php echo htmlspecialchars($paper['uploaded_by_name'] ?? 'Unknown'); ?> on <?php echo date('M d, Y', strtotime($paper['uploaded_at'])); ?></small>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($paper['file_path']); ?>" 
                                       class="btn-download" 
                                       target="_blank"
                                       title="Download">
                                        <span class="material-symbols-outlined">download</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($comments)): ?>
                    <div class="comments-section">
                        <h3>Recent Activity</h3>
                        <div class="comments-list">
                            <?php foreach($comments as $comment): ?>
                                <div class="comment-item <?php echo isset($comment['is_system']) ? 'system' : ''; ?>">
                                    <div class="comment-header">
                                        <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                        <small><?php echo date('M d, Y g:i A', strtotime($comment['created_at'])); ?></small>
                                    </div>
                                    <div class="comment-body">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column - Review Form -->
            <div class="right-column">
                <div class="title-card">
                    <div class="card-header">
                        <h2>
                            <span class="material-symbols-outlined">rate_review</span>
                            Review Decision
                        </h2>
                    </div>

                    <form method="POST" class="review-form" id="reviewForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="form-group">
                            <label>Decision *</label>
                            <div class="decision-options">
                                <?php if($title['status'] === 'pending_review' || ($title['status'] === 'revisions' && $revision_edited)): ?>
                                    <!-- Show both options for pending review or revised submissions -->
                                    <label class="decision-option">
                                        <input type="radio" name="decision" value="approve" required>
                                        <span class="decision-card approve">
                                            <span class="material-symbols-outlined">check_circle</span>
                                            <div>
                                                <strong>Approve</strong>
                                                <small>Title is ready for active status (student can upload papers)</small>
                                            </div>
                                        </span>
                                    </label>

                                    <label class="decision-option">
                                        <input type="radio" name="decision" value="revisions">
                                        <span class="decision-card revisions">
                                            <span class="material-symbols-outlined">edit_note</span>
                                            <div>
                                                <strong>Request Revisions</strong>
                                                <small>Student needs to make more changes</small>
                                            </div>
                                        </span>
                                    </label>

                                <?php elseif($title['status'] === 'revisions' && !$revision_edited): ?>
                                    <!-- Student hasn't made changes yet -->
                                    <div class="no-options">
                                        <span class="material-symbols-outlined">hourglass_empty</span>
                                        <p>Waiting for student to make changes...</p>
                                        <small>The student has been notified to revise this title.</small>
                                    </div>

                                <?php elseif($title['status'] === 'active'): ?>
                                    <!-- Active Title Options -->
                                    <label class="decision-option">
                                        <input type="radio" name="decision" value="complete" required>
                                        <span class="decision-card complete">
                                            <span class="material-symbols-outlined">check_circle</span>
                                            <div>
                                                <strong>Mark as Complete</strong>
                                                <small>Title is finished and defended</small>
                                            </div>
                                        </span>
                                    </label>

                                    <label class="decision-option">
                                        <input type="radio" name="decision" value="revisions">
                                        <span class="decision-card revisions">
                                            <span class="material-symbols-outlined">edit_note</span>
                                            <div>
                                                <strong>Request Revisions</strong>
                                                <small>Send back for changes</small>
                                            </div>
                                        </span>
                                    </label>

                                <?php elseif($title['status'] === 'completed'): ?>
                                    <div class="no-options">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        <p>This title is already completed.</p>
                                    </div>

                                <?php else: ?>
                                    <div class="no-options">
                                        <span class="material-symbols-outlined">info</span>
                                        <p>No review options available for this status.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($title['status'] !== 'completed' && !($title['status'] === 'revisions' && !$revision_edited)): ?>
                        <div class="form-group">
                            <label>Feedback / Remarks</label>
                            <textarea name="feedback" rows="5" placeholder="Provide detailed feedback to the student..."></textarea>
                            <small class="help-text">This will be added as a comment and included in the email notification</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit_review" class="btn-primary">
                                <span class="material-symbols-outlined">send</span>
                                Submit Review
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
        document.getElementById('reviewForm')?.addEventListener('submit', function() {
            sessionStorage.setItem('form_submitted', 'true');
        });

        if (performance.navigation.type === 1) {
            sessionStorage.removeItem('form_submitted');
        }

        document.querySelectorAll('.decision-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.decision-card').forEach(card => {
                    card.classList.remove('selected');
                });
                if (this.checked) {
                    this.closest('.decision-option').querySelector('.decision-card').classList.add('selected');
                }
            });
        });

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