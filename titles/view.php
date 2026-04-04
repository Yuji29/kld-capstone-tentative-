<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';

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
$title_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$title_id) {
    header('Location: browse.php');
    exit;
}

// Check for success messages from redirect
if(isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "Comment deleted successfully!";
}
if(isset($_GET['paper_deleted']) && $_GET['paper_deleted'] == 1) {
    $message = "Paper deleted successfully!";
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
                 a.id as adviser_id,
                 a.full_name as adviser_name,
                 a.email as adviser_email
          FROM capstone_titles ct
          LEFT JOIN categories c ON ct.category_id = c.id
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

// Check if user has permission to view this title
$can_view = false;
$can_edit = false;
$can_review = false;

if($role === 'admin') {
    $can_view = true;
    $can_edit = true;
    $can_review = true;
} elseif($role === 'adviser' && $title['adviser_id'] == $user_id) {
    $can_view = true;
    $can_review = true;
} elseif($role === 'student' && $title['student_id'] == $user_id) {
    $can_view = true;
    $can_edit = true;
}

if(!$can_view) {
    header('Location: browse.php');
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

// Get comments/feedback for this title with user avatars
$comments_query = "SELECT c.*, u.full_name as commenter_name, u.role as commenter_role, u.avatar as commenter_avatar
                   FROM comments c
                   LEFT JOIN users u ON c.user_id = u.id
                   WHERE c.title_id = ?
                   ORDER BY c.created_at DESC";
$comments_stmt = $db->prepare($comments_query);
$comments_stmt->execute([$title_id]);
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle adding comment
if(isset($_POST['add_comment'])) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please try again.";
    } else {
        $comment = htmlspecialchars(trim($_POST['comment']), ENT_QUOTES, 'UTF-8');
        
        if(!empty($comment)) {
            $insert_query = "INSERT INTO comments (title_id, user_id, comment) VALUES (?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            
            if($insert_stmt->execute([$title_id, $user_id, $comment])) {
                $message = "Comment added successfully!";
                
                // Refresh comments
                $comments_stmt->execute([$title_id]);
                $comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to add comment.";
            }
        } else {
            $error = "Comment cannot be empty.";
        }
    }
}

// Handle delete comment
if(isset($_GET['delete_comment']) && isset($_GET['comment_id']) && isset($_GET['token'])) {
    if($_GET['token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token.";
    } else {
        $comment_id = filter_var($_GET['comment_id'], FILTER_VALIDATE_INT);
        
        if($comment_id) {
            $check_query = "SELECT user_id FROM comments WHERE id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$comment_id]);
            $comment = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if($comment && ($comment['user_id'] == $user_id || $role === 'admin')) {
                $delete_query = "DELETE FROM comments WHERE id = ?";
                $delete_stmt = $db->prepare($delete_query);
                
                if($delete_stmt->execute([$comment_id])) {
                    header('Location: view.php?id=' . $title_id . '&deleted=1');
                    exit;
                } else {
                    $error = "Failed to delete comment.";
                }
            } else {
                $error = "You don't have permission to delete this comment.";
            }
        }
    }
}

// Handle delete paper
if(isset($_GET['delete_paper']) && isset($_GET['paper_id']) && isset($_GET['token'])) {
    if($_GET['token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token.";
    } else {
        $paper_id = filter_var($_GET['paper_id'], FILTER_VALIDATE_INT);
        
        if($paper_id) {
            $paper_query = "SELECT p.*, ct.student_id, ct.title 
                           FROM papers p
                           JOIN capstone_titles ct ON p.title_id = ct.id
                           WHERE p.id = ?";
            $paper_stmt = $db->prepare($paper_query);
            $paper_stmt->execute([$paper_id]);
            $paper = $paper_stmt->fetch(PDO::FETCH_ASSOC);
            
            if($paper) {
                $can_delete = false;
                if($role === 'admin') {
                    $can_delete = true;
                } elseif($role === 'student' && $paper['student_id'] == $user_id) {
                    $can_delete = true;
                } elseif($role === 'adviser' && $title['adviser_id'] == $user_id) {
                    $can_delete = true;
                }
                
                if($can_delete) {
                    $file_path = $paper['file_path'];
                    if(file_exists($file_path)) {
                        unlink($file_path);
                    }
                    
                    $delete_query = "DELETE FROM papers WHERE id = ?";
                    $delete_stmt = $db->prepare($delete_query);
                    
                    if($delete_stmt->execute([$paper_id])) {
                        header('Location: view.php?id=' . $title_id . '&paper_deleted=1');
                        exit;
                    } else {
                        $error = "Failed to delete paper.";
                    }
                } else {
                    $error = "You don't have permission to delete this paper.";
                }
            } else {
                $error = "Paper not found.";
            }
        }
    }
}

// Determine return URL
$return_url = 'browse.php';
if(isset($_SERVER['HTTP_REFERER'])) {
    $referrer = $_SERVER['HTTP_REFERER'];
    if(strpos($referrer, 'dashboard.php') !== false) {
        $return_url = '../dashboard.php';
    } elseif(strpos($referrer, 'browse.php') !== false) {
        $return_url = 'browse.php';
    } elseif(strpos($referrer, 'review.php') !== false) {
        $return_url = '../adviser/review.php';
    }
}

// Helper function to get user initials
function getUserInitials($name) {
    $parts = explode(' ', $name);
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Helper function to get avatar URL
function getAvatarUrl($avatar_path) {
    if (empty($avatar_path)) {
        return '';
    }
    // If it's already a full URL
    if (filter_var($avatar_path, FILTER_VALIDATE_URL)) {
        return $avatar_path;
    }
    // Remove leading slash if exists
    $avatar_path = ltrim($avatar_path, '/');
    // Add the base path
    return '/kld-capstone/' . $avatar_path;
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
    <title><?php echo htmlspecialchars($title['title']); ?> - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/titles/view.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <div class="header">
            <div>
                <h1>Capstone Title Details</h1>
                <p class="header-subtitle">View complete information about this research project</p>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message">
                <span class="material-symbols-outlined">check_circle</span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="error">
                <span class="material-symbols-outlined">error</span>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="view-grid">
            <div class="left-column">
                <div class="title-card">
                    <div class="card-header">
                        <div class="title-badges">
                            <span class="category-badge" style="background: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>20; color: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>;">
                                <?php echo htmlspecialchars($title['category_name'] ?? 'Uncategorized'); ?>
                            </span>
                            <span class="status-badge <?php echo $title['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                            </span>
                        </div>
    
                        <div class="header-actions">
                            <?php if($can_edit && $role === 'admin'): ?>
                                <a href="../admin/edit-title.php?id=<?php echo $title_id; ?>" class="btn-edit">
                                    <span class="material-symbols-outlined">edit</span>
                                    Edit
                                </a>
                            <?php endif; ?>
        
                            <?php if($role === 'student' && $title['status'] === 'revisions'): ?>
                                <a href="edit.php?id=<?php echo $title_id; ?>" class="btn-edit" style="background: #fd7e14; border-color: #fd7e14; color: white;">
                                    <span class="material-symbols-outlined">edit_note</span>
                                    Edit Title (Revisions Needed)
                                </a>
                            <?php endif; ?>
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
                            </div>
                        </div>

                        <?php if($title['adviser_name']): ?>
                        <div class="meta-item">
                            <span class="material-symbols-outlined">school</span>
                            <div>
                                <strong>Adviser</strong>
                                <p><?php echo htmlspecialchars($title['adviser_name']); ?></p>
                                <?php if($title['adviser_email']): ?>
                                    <small><?php echo htmlspecialchars($title['adviser_email']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($title['team_members']): ?>
                        <div class="meta-item">
                            <span class="material-symbols-outlined">group</span>
                            <div>
                                <strong>Team Members</strong>
                                <p><?php echo htmlspecialchars($title['team_members']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="meta-item">
                            <span class="material-symbols-outlined">calendar_today</span>
                            <div>
                                <strong>Submitted</strong>
                                <p><?php echo date('F j, Y', strtotime($title['created_at'])); ?></p>
                                <small><?php echo date('g:i A', strtotime($title['created_at'])); ?></small>
                            </div>
                        </div>

                        <?php if($title['updated_at'] != $title['created_at']): ?>
                        <div class="meta-item">
                            <span class="material-symbols-outlined">update</span>
                            <div>
                                <strong>Last Updated</strong>
                                <p><?php echo date('F j, Y', strtotime($title['updated_at'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if($title['abstract']): ?>
                    <div class="abstract-section">
                        <h3>Abstract</h3>
                        <div class="abstract-content">
                            <?php echo nl2br(htmlspecialchars($title['abstract'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($can_review && $title['status'] === 'pending_review'): ?>
                    <div class="action-section">
                        <h3>Review Actions</h3>
                        <div class="review-actions">
                            <a href="../adviser/review.php?id=<?php echo $title_id; ?>" class="btn-primary">
                                <span class="material-symbols-outlined">rate_review</span>
                                Review Title
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Comments Section with User Avatars -->
                <div class="title-card">
                    <div class="card-header">
                        <h2>
                            <span class="material-symbols-outlined">comment</span>
                            Comments & Feedback
                        </h2>
                        <span class="item-count"><?php echo count($comments); ?> comments</span>
                    </div>

                    <form method="POST" class="comment-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-group">
                            <textarea name="comment" rows="3" placeholder="Write your comment or feedback here..." required></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_comment" class="btn-primary">
                                <span class="material-symbols-outlined">send</span>
                                Post Comment
                            </button>
                        </div>
                    </form>

                    <div class="comments-list">
                        <?php if(empty($comments)): ?>
                            <div class="empty-message">
                                <span class="material-symbols-outlined">forum</span>
                                <p>No comments yet. Be the first to comment!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($comments as $comment): ?>
                                <?php
                                // Get user avatar and initials
                                $commenter_avatar = $comment['commenter_avatar'] ?? '';
                                $commenter_initials = getUserInitials($comment['commenter_name']);
                                $avatar_url = getAvatarUrl($commenter_avatar);
                                ?>
                                <div class="comment-item">
                                    <div class="comment-avatar">
                                        <?php if(!empty($commenter_avatar)): ?>
                                            <img src="<?php echo $avatar_url; ?>" 
                                                 alt="Avatar" 
                                                 class="comment-avatar-img"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="comment-avatar-initials" style="background: <?php echo $comment['commenter_role'] === 'adviser' ? '#2D5A27' : '#6c757d'; ?>; display: none;">
                                                <?php echo $commenter_initials; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="comment-avatar-initials" style="background: <?php echo $comment['commenter_role'] === 'adviser' ? '#2D5A27' : '#6c757d'; ?>;">
                                                <?php echo $commenter_initials; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-content">
                                        <div class="comment-header">
                                            <strong><?php echo htmlspecialchars($comment['commenter_name']); ?></strong>
                                            <span class="role-badge <?php echo $comment['commenter_role']; ?>">
                                                <?php echo ucfirst($comment['commenter_role']); ?>
                                            </span>
                                            <span class="comment-date">
                                                <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                            </span>
                                            
                                            <?php if($comment['user_id'] == $user_id || $role === 'admin'): ?>
                                                <a href="javascript:void(0);" 
                                                   class="btn-delete-comment" 
                                                   onclick="showConfirmationModal({
                                                       title: 'Delete Comment',
                                                       message: 'Are you sure you want to delete this comment?<br><br><strong>This action cannot be undone.</strong>',
                                                       confirmUrl: 'view.php?id=<?php echo $title_id; ?>&delete_comment=1&comment_id=<?php echo $comment['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>',
                                                       confirmText: 'Yes, Delete',
                                                       type: 'delete',
                                                       method: 'GET'
                                                   });"
                                                   title="Delete comment">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Papers -->
            <div class="right-column">
                <div class="title-card">
                    <div class="card-header">
                        <h2>
                            <span class="material-symbols-outlined">description</span>
                            Papers
                        </h2>
                        <span class="item-count"><?php echo count($papers); ?> files</span>
                    </div>

                    <?php 
                    $can_upload = false;
                    if($role === 'admin') {
                        $can_upload = true;
                    } elseif($role === 'adviser' && $title['adviser_id'] == $user_id) {
                        $can_upload = true;
                    } elseif($role === 'student' && $title['student_id'] == $user_id && $title['status'] === 'active') {
                        $can_upload = true;
                    }
                    ?>

                    <?php if($can_upload): ?>
                        <div class="upload-section">
                            <a href="upload-paper.php?title_id=<?php echo $title_id; ?>" class="btn-primary" style="width: 100%; justify-content: center;">
                                <span class="material-symbols-outlined">upload</span>
                                Upload New Paper
                            </a>
                        </div>
                    <?php elseif($role === 'student' && $title['student_id'] == $user_id && $title['status'] !== 'active'): ?>
                        <div class="upload-disabled-message">
                            <span class="material-symbols-outlined">info</span>
                            <p>
                                <?php if($title['status'] === 'pending_review'): ?>
                                    You can upload papers only after your title is <strong>approved</strong> by your adviser.
                                <?php elseif($title['status'] === 'revisions'): ?>
                                    Please <strong>revise your title</strong> first based on your adviser's feedback before uploading papers.
                                <?php elseif($title['status'] === 'completed'): ?>
                                    This title is already <strong>completed</strong>. No further uploads are allowed.
                                <?php else: ?>
                                    You can upload papers only when the title status is <strong>active</strong>.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="papers-list">
                        <?php if(empty($papers)): ?>
                            <div class="empty-message">
                                <span class="material-symbols-outlined">upload_file</span>
                                <p>No papers uploaded yet.</p>
                                <?php if($role === 'student' && $title['student_id'] == $user_id && $title['status'] === 'active'): ?>
                                    <small>Click the "Upload New Paper" button above to add your first paper.</small>
                                <?php elseif($role === 'student' && $title['student_id'] == $user_id && $title['status'] !== 'active'): ?>
                                    <small>Papers can only be uploaded when the title is <strong>active</strong>.</small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach($papers as $paper): ?>
                                <div class="paper-item">
                                    <div class="paper-icon">
                                        <span class="material-symbols-outlined">description</span>
                                    </div>
                                    <div class="paper-info">
                                        <h4><?php echo htmlspecialchars($paper['file_name']); ?></h4>
                                        <p>
                                            <span class="paper-type"><?php echo ucfirst(str_replace('_', ' ', $paper['paper_type'])); ?></span>
                                            <span class="paper-meta">Uploaded by <?php echo htmlspecialchars($paper['uploaded_by_name']); ?></span>
                                            <span class="paper-date"><?php echo date('M j, Y', strtotime($paper['uploaded_at'])); ?></span>
                                        </p>
                                    </div>
                                    <div class="paper-actions">
                                        <a href="<?php echo htmlspecialchars($paper['file_path']); ?>" 
                                           class="btn-download" 
                                           target="_blank"
                                           title="Download">
                                            <span class="material-symbols-outlined">download</span>
                                        </a>
                                        <?php 
                                        $can_delete_paper = false;
                                        if($role === 'admin') {
                                            $can_delete_paper = true;
                                        } elseif($role === 'student' && $paper['uploaded_by'] == $user_id && $title['status'] === 'active') {
                                            $can_delete_paper = true;
                                        } elseif($role === 'adviser' && $title['adviser_id'] == $user_id) {
                                            $can_delete_paper = true;
                                        }
                                        
                                        if($can_delete_paper): 
                                        ?>
                                            <a href="javascript:void(0);" 
                                               class="btn-delete" 
                                               onclick="showConfirmationModal({
                                                   title: 'Delete Paper',
                                                   message: 'Are you sure you want to delete this paper?<br><br><strong>This action cannot be undone.</strong>',
                                                   confirmUrl: 'view.php?id=<?php echo $title_id; ?>&delete_paper=1&paper_id=<?php echo $paper['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>',
                                                   confirmText: 'Yes, Delete',
                                                   type: 'delete',
                                                   method: 'GET'
                                               });"
                                               title="Delete">
                                                <span class="material-symbols-outlined">delete</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div> 
    </div>

    <?php include '../includes/dashboard-footer.php'; ?>
    <?php include '../includes/confirmation-modal.php'; ?>

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