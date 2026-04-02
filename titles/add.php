<?php
session_start();
date_default_timezone_set('Asia/Manila'); // ← This is correct now
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

// Check if user is logged in and is a student
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

$message = '';
$error = '';

// Determine return URL
$return_url = 'browse.php'; // default

if(isset($_GET['return'])) {
    if($_GET['return'] === 'dashboard') {
        $return_url = '../dashboard.php';
    } elseif($_GET['return'] === 'browse') {
        $return_url = 'browse.php';
    }
} 
elseif(isset($_SERVER['HTTP_REFERER'])) {
    $referrer = $_SERVER['HTTP_REFERER'];
    if(strpos($referrer, 'dashboard.php') !== false) {
        $return_url = '../dashboard.php';
    } elseif(strpos($referrer, 'browse.php') !== false) {
        $return_url = 'browse.php';
    }
}

// Get categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $db->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

// Get advisers for dropdown
$advisers_query = "SELECT id, full_name, email FROM users WHERE role='adviser' ORDER BY full_name";
$advisers = $db->query($advisers_query)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid security token. Please try again.";
    } else {
        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $abstract = htmlspecialchars(trim($_POST['abstract'] ?? ''), ENT_QUOTES, 'UTF-8');
        $category_id = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $adviser_id = !empty($_POST['adviser_id']) ? filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT) : null;
        $team_members = htmlspecialchars(trim($_POST['team_members'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        // Validate input
        if(empty($title)) {
            $error = "Title is required.";
        } elseif(!$category_id) {
            $error = "Please select a category.";
        } else {
            // Check for duplicate title
            $check_query = "SELECT id FROM capstone_titles WHERE title = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$title]);
            
            if($check_stmt->rowCount() > 0) {
                $error = "This title already exists. Please choose a different title.";
            } else {
                // Insert new title
                $insert_query = "INSERT INTO capstone_titles (title, abstract, category_id, student_id, adviser_id, team_members, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'pending_review')";
                $insert_stmt = $db->prepare($insert_query);
                
                if($insert_stmt->execute([$title, $abstract, $category_id, $user_id, $adviser_id, $team_members])) {
                    $title_id = $db->lastInsertId();
                    
                    // If adviser selected, create adviser request
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
                        
                        // Send email notification to adviser
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
                                'A new capstone title has been submitted for your review.',
                                $_SESSION['full_name']
                            );
                        }
                    }
                    
                    $message = "Title submitted successfully and is now pending review!";
                    
                    // Clear form data
                    $title = $abstract = $team_members = '';
                    $category_id = '';
                    $adviser_id = null;
                } else {
                    $error_info = $insert_stmt->errorInfo();
                    error_log("Student add title failed: " . $error_info[2]);
                    $error = "Database error occurred. Please try again.";
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
    <title>Add New Title - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/titles/add.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Background Animation Elements -->
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <!-- Include Navigation -->
    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <!-- Header Section -->
        <div class="header">
            <div>
                <h1>Add New Capstone Title</h1>
                <p class="header-subtitle">Submit your research topic for review</p>
            </div>
        </div>

        <!-- Messages -->
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
                    <input type="text" name="title" id="titleField" maxlength="255" placeholder="Enter your capstone title" value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
                    <div class="char-counter"><span id="titleCharCount"><?php echo strlen($title ?? ''); ?></span>/255 characters</div>
                    <div class="help-text">Choose a clear and descriptive title for your research</div>
                </div>

                <!-- Two Column Row -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label>
                            <span class="material-symbols-outlined">category</span>
                            Category *
                        </label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($category_id) && $category_id == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="material-symbols-outlined">school</span>
                            Preferred Adviser (Optional)
                        </label>
                        <select name="adviser_id">
                            <option value="">Select Adviser</option>
                            <?php foreach($advisers as $adv): ?>
                                <option value="<?php echo $adv['id']; ?>" <?php echo (isset($adviser_id) && $adviser_id == $adv['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($adv['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">You can change this later</div>
                    </div>
                </div>

                <!-- Team Members -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">group</span>
                        Team Members (Optional)
                    </label>
                    <input type="text" name="team_members" id="teamMembersField" maxlength="500" placeholder="e.g., Juan Dela Cruz, Maria Santos, Pedro Reyes" value="<?php echo htmlspecialchars($team_members ?? ''); ?>">
                    <div class="char-counter"><span id="teamMembersCharCount"><?php echo strlen($team_members ?? ''); ?></span>/500 characters</div>
                    <div class="help-text">Separate names with commas if working in a team</div>
                </div>

                <!-- Abstract -->
                <div class="form-group">
                    <label>
                        <span class="material-symbols-outlined">description</span>
                        Abstract / Description
                    </label>
                    <textarea name="abstract" id="abstractField" rows="6" maxlength="5000" placeholder="Describe your capstone project, objectives, and methodology..."><?php echo htmlspecialchars($abstract ?? ''); ?></textarea>
                    <div class="char-counter"><span id="abstractCharCount"><?php echo strlen($abstract ?? ''); ?></span>/5000 characters</div>
                    <div class="help-text">Provide a brief summary of your research (optional but recommended)</div>
                </div>

                <!-- Info Box -->
                <div class="info-box">
                    <span class="material-symbols-outlined">info</span>
                    <p>
                        <strong>Note:</strong> Your title will be submitted for review immediately. 
                        After submission, an adviser will review and approve your title. 
                        You can track the status in your dashboard.
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="form-actions">
                    <a href="browse.php" class="btn-secondary">
                        <span class="material-symbols-outlined">cancel</span>
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        <span class="material-symbols-outlined">send</span>
                        Submit Title
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
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
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Submitting...';
        });

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
    </script>
</body>
</html>