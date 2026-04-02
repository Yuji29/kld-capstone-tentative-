<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
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

// Get flash messages from session
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Get title ID from URL
$title_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$title_id) {
    $_SESSION['flash_message'] = "No title ID provided.";
    $_SESSION['flash_type'] = "error";
    header('Location: dashboard.php');
    exit;
}

// Get title details with all related info
$query = "SELECT ct.*, 
                 s.id as student_id,
                 s.full_name as student_name,
                 s.email as student_email,
                 a.id as adviser_id,
                 a.full_name as adviser_name,
                 c.name as category_name,
                 c.color as category_color
          FROM capstone_titles ct
          LEFT JOIN users s ON ct.student_id = s.id
          LEFT JOIN users a ON ct.adviser_id = a.id
          LEFT JOIN categories c ON ct.category_id = c.id
          WHERE ct.id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$title_id]);
$title = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$title) {
    $_SESSION['flash_message'] = "Title not found.";
    $_SESSION['flash_type'] = "error";
    header('Location: dashboard.php');
    exit;
}

// Get categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $db->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

// Get advisers for dropdown
$advisers_query = "SELECT id, full_name, email FROM users WHERE role='adviser' ORDER BY full_name";
$advisers = $db->query($advisers_query)->fetchAll(PDO::FETCH_ASSOC);

// Get students for dropdown
$students_query = "SELECT id, full_name, email FROM users WHERE role='student' ORDER BY full_name";
$students = $db->query($students_query)->fetchAll(PDO::FETCH_ASSOC);

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
        $new_student_id = filter_var($_POST['student_id'] ?? '', FILTER_VALIDATE_INT);
        $new_adviser_id = !empty($_POST['adviser_id']) ? filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT) : null;
        $new_team_members = htmlspecialchars(trim($_POST['team_members'] ?? ''), ENT_QUOTES, 'UTF-8');
        $new_status = $_POST['status'] ?? $title['status'];
        
        // Validate input
        if(empty($new_title)) {
            $_SESSION['flash_message'] = "Title is required.";
            $_SESSION['flash_type'] = "error";
        } elseif(!$new_category_id) {
            $_SESSION['flash_message'] = "Please select a valid category.";
            $_SESSION['flash_type'] = "error";
        } elseif(!$new_student_id) {
            $_SESSION['flash_message'] = "Please select a valid student.";
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
                // Update the title
                $update_query = "UPDATE capstone_titles 
                                SET title = ?, abstract = ?, category_id = ?, student_id = ?, adviser_id = ?, team_members = ?, status = ?
                                WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if($update_stmt->execute([$new_title, $new_abstract, $new_category_id, $new_student_id, $new_adviser_id, $new_team_members, $new_status, $title_id])) {
                    
                    // Send notifications if status changed
                    if($new_status !== $title['status']) {
                        
                        // Notify student
                        if(!empty($title['student_email'])) {
                            sendCapstoneNotification(
                                $title['student_email'],
                                $title['student_name'],
                                $new_title,
                                $new_status,
                                "Your title status has been updated by an administrator.",
                                $full_name
                            );
                        }
                        
                        // Notify adviser if assigned
                        if($new_adviser_id) {
                            $adviser_query = "SELECT email, full_name FROM users WHERE id = ?";
                            $adviser_stmt = $db->prepare($adviser_query);
                            $adviser_stmt->execute([$new_adviser_id]);
                            $adviser = $adviser_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if($adviser && !empty($adviser['email'])) {
                                sendCapstoneNotification(
                                    $adviser['email'],
                                    $adviser['full_name'],
                                    $new_title,
                                    $new_status,
                                    "A title you are advising has been updated by an administrator.",
                                    $full_name
                                );
                            }
                        }
                    }
                    
                    $_SESSION['flash_message'] = "Title updated successfully!";
                    $_SESSION['flash_type'] = "success";
                    
                    // Redirect to the same page with the ID to refresh data
                    header('Location: edit-title.php?id=' . $title_id);
                    exit;
                    
                } else {
                    $error_info = $update_stmt->errorInfo();
                    error_log("Admin edit title failed - ID: $title_id, Error: " . $error_info[2]);
                    $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        }
    }
    // If there's an error, redirect back to the form with the error message
    header('Location: edit-title.php?id=' . $title_id);
    exit;
}

// Determine safe back URL
$back_url = '../titles/view.php?id=' . $title_id;
if(isset($_SERVER['HTTP_REFERER']) && 
   !strpos($_SERVER['HTTP_REFERER'], 'edit-title.php') && 
   strpos($_SERVER['HTTP_REFERER'], 'view.php')) {
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
    <title>Edit Title - Admin - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-edit-title.css?v=<?php echo time(); ?>">
    <style>
        .char-counter {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
            text-align: right;
        }
        .char-counter span {
            font-weight: 600;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Background Animation Elements -->
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <!-- Include Navigation -->
    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <div class="header">
            <div>
                <h1>Edit Capstone Title</h1>
                <p class="header-subtitle">Modify title information and status</p>
            </div>
        </div>

        <!-- Messages from session flash -->
        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="title-card form-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">edit_note</span>
                    Edit Title Information
                </h2>
                <span class="admin-badge">Admin Access</span>
            </div>

            <form method="POST" class="edit-form" id="editForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <!-- Title Field -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">title</span>
                        Capstone Title *
                    </label>
                    <input type="text" name="title" id="titleField" value="<?php echo htmlspecialchars($title['title']); ?>" maxlength="255" required>
                    <div class="char-counter"><span id="titleCharCount"><?php echo strlen($title['title']); ?></span>/255 characters</div>
                </div>

                <!-- Three Column Row -->
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
                            <span class="material-symbols-outlined">person</span>
                            Student *
                        </label>
                        <select name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo ($title['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="material-symbols-outlined">school</span>
                            Adviser
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
                </div>

                <!-- Team Members -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">group</span>
                        Team Members
                    </label>
                    <input type="text" name="team_members" id="teamMembersField" value="<?php echo htmlspecialchars($title['team_members'] ?? ''); ?>" maxlength="500" placeholder="e.g., Juan Dela Cruz, Maria Santos">
                    <div class="char-counter"><span id="teamMembersCharCount"><?php echo strlen($title['team_members'] ?? ''); ?></span>/500 characters</div>
                    <small class="help-text">Separate names with commas</small>
                </div>

                <!-- Abstract -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">description</span>
                        Abstract
                    </label>
                    <textarea name="abstract" id="abstractField" rows="6" maxlength="5000" placeholder="Describe the capstone project..."><?php echo htmlspecialchars($title['abstract'] ?? ''); ?></textarea>
                    <div class="char-counter"><span id="abstractCharCount"><?php echo strlen($title['abstract'] ?? ''); ?></span>/5000 characters</div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">info</span>
                        Status
                    </label>
                    <select name="status">
                        <option value="pending_review" <?php echo ($title['status'] == 'pending_review') ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="active" <?php echo ($title['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="revisions" <?php echo ($title['status'] == 'revisions') ? 'selected' : ''; ?>>Revisions</option>
                        <option value="completed" <?php echo ($title['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <small class="help-text">Changing status will notify the student and adviser</small>
                </div>

                <!-- Info Box -->
                <div class="info-box">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    <div>
                        <strong>Admin Privileges:</strong> You have full control to edit any field and change the status. All changes will be logged and appropriate notifications will be sent.
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="../dashboard.php" class="btn-secondary" id="cancelButton">
                        <span class="material-symbols-outlined">cancel</span>
                        Cancel
                    </a>
                    <button type="submit" name="update_title" class="btn-primary">
                        <span class="material-symbols-outlined">save</span>
                        Update Title
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
        // Track form submissions
        document.addEventListener('DOMContentLoaded', function() {
            // Mark that we're not coming from a form submission on page load
            if (performance.navigation.type === 1) { // Page reloaded
                sessionStorage.removeItem('form_submitted');
            }
            
            // Track form submission
            const editForm = document.getElementById('editForm');
            if (editForm) {
                editForm.addEventListener('submit', function() {
                    sessionStorage.setItem('form_submitted', 'true');
                });
            }

            // Character counters
            document.getElementById('titleField')?.addEventListener('input', function() {
                document.getElementById('titleCharCount').textContent = this.value.length;
            });

            document.getElementById('teamMembersField')?.addEventListener('input', function() {
                document.getElementById('teamMembersCharCount').textContent = this.value.length;
            });

            document.getElementById('abstractField')?.addEventListener('input', function() {
                document.getElementById('abstractCharCount').textContent = this.value.length;
            });
        });

        // Smart cancel button handling
        document.getElementById('cancelButton')?.addEventListener('click', function(event) {
            const justSubmitted = sessionStorage.getItem('form_submitted') === 'true';
            
            // If we just submitted the form, go to view page
            if (justSubmitted) {
                sessionStorage.removeItem('form_submitted');
                window.location.href = '../titles/view.php?id=<?php echo $title_id; ?>';
                event.preventDefault();
                return false;
            }
            
            // Otherwise, let the link work normally
            return true;
        });

        // Mobile menu toggle
        document.getElementById('hamburger-btn')?.addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.toggle('active');
        });
        
        // Close mobile menu when clicking outside
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