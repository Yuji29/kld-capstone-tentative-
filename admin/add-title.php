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

// Get flash messages from session
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Get categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $db->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

// Get advisers for dropdown
$advisers_query = "SELECT id, full_name, email FROM users WHERE role='adviser' ORDER BY full_name";
$advisers = $db->query($advisers_query)->fetchAll(PDO::FETCH_ASSOC);

// Get students for dropdown (for admin to assign)
$students_query = "SELECT id, full_name, email FROM users WHERE role='student' ORDER BY full_name";
$students = $db->query($students_query)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token first
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        // Sanitize and validate inputs
        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $abstract = htmlspecialchars(trim($_POST['abstract'] ?? ''), ENT_QUOTES, 'UTF-8');
        $team_members = htmlspecialchars(trim($_POST['team_members'] ?? ''), ENT_QUOTES, 'UTF-8');
        $category_id = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $student_id = filter_var($_POST['student_id'] ?? '', FILTER_VALIDATE_INT);
        $adviser_id = !empty($_POST['adviser_id']) ? filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT) : null;
        
        // Validate input
        if(empty($title)) {
            $_SESSION['flash_message'] = "Title is required.";
            $_SESSION['flash_type'] = "error";
        } elseif(!$category_id) {
            $_SESSION['flash_message'] = "Please select a valid category.";
            $_SESSION['flash_type'] = "error";
        } elseif(!$student_id) {
            $_SESSION['flash_message'] = "Please select a valid student.";
            $_SESSION['flash_type'] = "error";
        } else {
            // Check for duplicate title
            $check_query = "SELECT id FROM capstone_titles WHERE title = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$title]);
            
            if($check_stmt->rowCount() > 0) {
                $_SESSION['flash_message'] = "This title already exists. Please choose a different title.";
                $_SESSION['flash_type'] = "error";
            } else {
                // Insert new title
                $insert_query = "INSERT INTO capstone_titles (title, abstract, category_id, student_id, adviser_id, team_members, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'pending_review')";
                $insert_stmt = $db->prepare($insert_query);
                
                if($insert_stmt->execute([$title, $abstract, $category_id, $student_id, $adviser_id, $team_members])) {
                    $title_id = $db->lastInsertId();
    
                    // If adviser selected, create adviser request (check if exists first)
                    if($adviser_id) {
                        // Check if request already exists
                        $check_request = "SELECT id FROM adviser_requests WHERE capstone_id = ?";
                        $check_stmt = $db->prepare($check_request);
                        $check_stmt->execute([$title_id]);
        
                        if($check_stmt->rowCount() == 0) {
                            $request_query = "INSERT INTO adviser_requests (capstone_id, adviser_id, status) VALUES (?, ?, 'pending')";
                            $request_stmt = $db->prepare($request_query);
                            $request_stmt->execute([$title_id, $adviser_id]);
                        }
                    }
    
                    // Send email notification to adviser if selected
                    if($adviser_id) {
                        // Get adviser details
                        $adviser_query = "SELECT email, full_name FROM users WHERE id = ?";
                        $adviser_stmt = $db->prepare($adviser_query);
                        $adviser_stmt->execute([$adviser_id]);
                        $adviser = $adviser_stmt->fetch(PDO::FETCH_ASSOC);

                        if($adviser && !empty($adviser['email'])) {
                            sendCapstoneNotification(
                                $adviser['email'],
                                $adviser['full_name'],
                                $title,
                                'pending_review',
                                'A new capstone title has been assigned for your review.',
                                'Admin'
                            );
                        }
                    }

                    // Also notify the student
                    $student_query = "SELECT email, full_name FROM users WHERE id = ?";
                    $student_stmt = $db->prepare($student_query);
                    $student_stmt->execute([$student_id]);
                    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

                    if($student && !empty($student['email'])) {
                        sendCapstoneNotification(
                            $student['email'],
                            $student['full_name'],
                            $title,
                            'pending_review',
                            'Your title has been submitted and is now pending review.',
                            'System'
                        );
                    }

                    $_SESSION['flash_message'] = "Title added successfully and is now pending review!";
                    $_SESSION['flash_type'] = "success";

                } else {
                    // Get the actual database error for logging
                    $error_info = $insert_stmt->errorInfo();
                    error_log("Admin add title failed - Database error: " . $error_info[2]);
                    error_log("Failed data - Title: " . $title . ", Student ID: " . $student_id . ", Adviser ID: " . $adviser_id);
    
                    $_SESSION['flash_message'] = "Database error occurred. Please try again or contact support.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        }
    }
    // Redirect to prevent form resubmission
    header('Location: add-title.php');
    exit;
}

// Determine safe back URL
$back_url = '../dashboard.php';
if(isset($_SERVER['HTTP_REFERER']) && 
   !strpos($_SERVER['HTTP_REFERER'], 'add-title.php') && 
   !strpos($_SERVER['HTTP_REFERER'], 'add_title')) {
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
    <title>Admin Add Title - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-add-title.css?v=<?php echo time(); ?>">
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
                <h1>Add New Capstone Title</h1>
                <p class="header-subtitle">Create and assign a new capstone title to a student</p>
            </div>
        </div>

        <!-- Messages from session flash -->
        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo $flash_message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="title-card form-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">edit_note</span>
                    Title Information
                </h2>
            </div>

            <form method="POST" class="add-form">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <!-- Title Field -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">title</span>
                        Capstone Title *
                    </label>
                    <input type="text" name="title" placeholder="Enter capstone title" value="" maxlength="255" required>
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
                                <option value="<?php echo $cat['id']; ?>">
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
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?>
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
                                <option value="<?php echo $adv['id']; ?>">
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
                        Team Members (Optional)
                    </label>
                    <input type="text" name="team_members" maxlength="500" placeholder="e.g., Juan Dela Cruz, Maria Santos, Pedro Reyes" value="">
                    <div class="help-text">Separate names with commas if working in a team</div>
                </div>

                <!-- Abstract -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">description</span>
                        Abstract / Description
                    </label>
                    <textarea name="abstract" rows="6" maxlength="5000" placeholder="Describe the capstone project, objectives, and methodology..." onkeyup="countChars(this)"></textarea>
                    <div class="char-counter"><span id="charCount">0</span>/5000 characters</div>
                </div>

                <!-- Info Box -->
                <div class="info-box">
                    <span class="material-symbols-outlined">info</span>
                    <p>
                        <strong>Note:</strong> This title will be assigned to the selected student. 
                        The status will be set to "Pending Review" initially.
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="form-actions">
                    <a href="../dashboard.php" class="btn-secondary" onclick="window.history.back(); return false;">
                        <span class="material-symbols-outlined">cancel</span>
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        <span class="material-symbols-outlined">add_circle</span>
                        Add Title
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
            
            // Track all form submissions
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    sessionStorage.setItem('form_submitted', 'true');
                });
            });
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

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Adding...';
        });

        // Character counter for abstract
        function countChars(textarea) {
            document.getElementById('charCount').textContent = textarea.value.length;
        }
    </script>
</body>
</html>