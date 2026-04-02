<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

// Allow only advisers (teachers) to access
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

// Get flash messages from session
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$user_id = $_SESSION['user_id'];

// Get teacher's students (students they advise)
try {
    $students_query = "SELECT DISTINCT u.id, u.full_name, u.id_number, u.department 
                       FROM users u 
                       INNER JOIN capstone_titles ct ON u.id = ct.student_id 
                       WHERE ct.adviser_id = ? 
                       ORDER BY u.full_name";
    $students_stmt = $db->prepare($students_query);
    $students_stmt->execute([$user_id]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch students: " . $e->getMessage());
    $students = [];
    $_SESSION['flash_message'] = "Error loading your advisees.";
    $_SESSION['flash_type'] = "error";
}

// Helper function to validate student IDs
function validateStudentIds($ids) {
    if(empty($ids) || !is_array($ids)) return [];
    
    $valid_ids = [];
    foreach($ids as $id) {
        if(filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
            $valid_ids[] = (int)$id;
        }
    }
    return $valid_ids;
}

// Helper function to validate date
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Handle Add Deadline
if(isset($_POST['add_deadline'])) {
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $deadline_date = $_POST['deadline_date'] ?? '';
        $deadline_time = $_POST['deadline_time'] ?? '23:59:00';
        $category = htmlspecialchars(trim($_POST['category'] ?? 'General'), ENT_QUOTES, 'UTF-8');
        $target_type = $_POST['target_type'] ?? 'none';
        
        $target_students = null;
        if(isset($_POST['target_students']) && is_array($_POST['target_students'])) {
            $valid_students = validateStudentIds($_POST['target_students']);
            $target_students = !empty($valid_students) ? implode(',', $valid_students) : null;
        }
        
        $visibility_type = $_POST['visibility_type'] ?? 'all';
        
        $visible_to_students = null;
        if(isset($_POST['visible_to_students']) && is_array($_POST['visible_to_students'])) {
            $valid_visible = validateStudentIds($_POST['visible_to_students']);
            $visible_to_students = !empty($valid_visible) ? implode(',', $valid_visible) : null;
        }
        
        if(!validateDate($deadline_date)) {
            $_SESSION['flash_message'] = "Invalid date format.";
            $_SESSION['flash_type'] = "error";
        }
        elseif(empty($title)) {
            $_SESSION['flash_message'] = "Deadline title is required.";
            $_SESSION['flash_type'] = "error";
        } elseif(empty($deadline_date)) {
            $_SESSION['flash_message'] = "Deadline date is required.";
            $_SESSION['flash_type'] = "error";
        } elseif(strtotime($deadline_date . ' ' . $deadline_time) < time()) {
            $_SESSION['flash_message'] = "Deadline cannot be in the past.";
            $_SESSION['flash_type'] = "error";
        } elseif($visibility_type === 'specific_students' && empty($visible_to_students)) {
            $_SESSION['flash_message'] = "Please select at least one student who can see this deadline.";
            $_SESSION['flash_type'] = "error";
        } else {
            try {
                $insert_query = "INSERT INTO deadlines (title, description, deadline_date, deadline_time, category, target_type, target_ids, visibility_type, visible_to_students, is_active, created_by, created_by_role) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'adviser')";
                $insert_stmt = $db->prepare($insert_query);
                
                if($insert_stmt->execute([$title, $description, $deadline_date, $deadline_time, $category, $target_type, $target_students, $visibility_type, $visible_to_students, $user_id])) {
                    $deadline_id = $db->lastInsertId();
                    
                    $notify_query = "SELECT email, full_name FROM users WHERE 1=1";
                    $params = [];
                    
                    switch($target_type) {
                        case 'my_students':
                            if(!empty($students)) {
                                $student_ids = array_column($students, 'id');
                                $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                                $notify_query .= " AND id IN ($placeholders)";
                                $params = $student_ids;
                            }
                            break;
                            
                        case 'specific_students':
                            if($target_students) {
                                $student_ids = explode(',', $target_students);
                                $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                                $notify_query .= " AND id IN ($placeholders)";
                                $params = array_merge($params, $student_ids);
                            }
                            break;
                            
                        default:
                            break;
                    }
                    
                    if(!empty($params)) {
                        $notify_stmt = $db->prepare($notify_query);
                        $notify_stmt->execute($params);
                        $users_to_notify = $notify_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $notification_count = 0;
                        $deadline_datetime = $deadline_date . ' ' . $deadline_time;
                        
                        foreach($users_to_notify as $user) {
                            if(!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                                $email_sent = sendDeadlineNotification(
                                    $user['email'],
                                    $user['full_name'],
                                    $title,
                                    $deadline_datetime,
                                    $description,
                                    $_SESSION['full_name']
                                );
                                if($email_sent) $notification_count++;
                            }
                        }
                        
                        $visibility_display = $visibility_type === 'specific_students' ? ' (Visible only to selected students)' : '';
                        $_SESSION['flash_message'] = "Deadline added successfully! " . $notification_count . " notification(s) sent to students." . $visibility_display;
                        $_SESSION['flash_type'] = "success";
                    } else {
                        $visibility_display = $visibility_type === 'specific_students' ? ' (Visible only to selected students)' : '';
                        $_SESSION['flash_message'] = "Deadline added successfully!" . $visibility_display;
                        $_SESSION['flash_type'] = "success";
                    }
                    
                } else {
                    $_SESSION['flash_message'] = "Failed to add deadline. Please try again.";
                    $_SESSION['flash_type'] = "error";
                }
            } catch (PDOException $e) {
                error_log("Failed to add deadline: " . $e->getMessage());
                $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header('Location: deadlines.php');
    exit;
}

// Handle Edit Deadline
if(isset($_POST['edit_deadline'])) {
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token.";
        $_SESSION['flash_type'] = "error";
    } else {
        $deadline_id = filter_var($_POST['deadline_id'], FILTER_VALIDATE_INT);
        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $deadline_date = $_POST['deadline_date'] ?? '';
        $deadline_time = $_POST['deadline_time'] ?? '23:59:00';
        $category = htmlspecialchars(trim($_POST['category'] ?? 'General'), ENT_QUOTES, 'UTF-8');
        $visibility_type = $_POST['visibility_type'] ?? 'all';
        
        $visible_to_students = null;
        if(isset($_POST['visible_to_students']) && is_array($_POST['visible_to_students'])) {
            $valid_visible = validateStudentIds($_POST['visible_to_students']);
            $visible_to_students = !empty($valid_visible) ? implode(',', $valid_visible) : null;
        }
        
        if(empty($title) || empty($deadline_date)) {
            $_SESSION['flash_message'] = "Title and date are required.";
            $_SESSION['flash_type'] = "error";
        } else {
            try {
                $check = $db->prepare("SELECT created_by FROM deadlines WHERE id = ?");
                $check->execute([$deadline_id]);
                $deadline = $check->fetch(PDO::FETCH_ASSOC);
                
                if($deadline && $deadline['created_by'] == $user_id) {
                    $update = $db->prepare("UPDATE deadlines SET 
                        title = ?, description = ?, deadline_date = ?, deadline_time = ?, 
                        category = ?, visibility_type = ?, visible_to_students = ?
                        WHERE id = ?");
                    
                    if($update->execute([$title, $description, $deadline_date, $deadline_time, 
                        $category, $visibility_type, $visible_to_students, $deadline_id])) {
                        $_SESSION['flash_message'] = "Deadline updated successfully!";
                        $_SESSION['flash_type'] = "success";
                    } else {
                        $_SESSION['flash_message'] = "Failed to update deadline.";
                        $_SESSION['flash_type'] = "error";
                    }
                } else {
                    $_SESSION['flash_message'] = "You don't have permission to edit this deadline.";
                    $_SESSION['flash_type'] = "error";
                }
            } catch (PDOException $e) {
                error_log("Failed to edit deadline: " . $e->getMessage());
                $_SESSION['flash_message'] = "Database error occurred.";
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header('Location: deadlines.php');
    exit;
}

// Handle Delete Deadline
if(isset($_GET['delete'])) {
    $deadline_id = filter_var($_GET['delete'], FILTER_VALIDATE_INT);
    
    if(!$deadline_id) {
        $_SESSION['flash_message'] = "Invalid deadline ID.";
        $_SESSION['flash_type'] = "error";
        header('Location: deadlines.php');
        exit;
    }
    
    try {
        $check = $db->prepare("SELECT created_by FROM deadlines WHERE id = ?");
        $check->execute([$deadline_id]);
        $deadline = $check->fetch(PDO::FETCH_ASSOC);
        
        if($deadline && $deadline['created_by'] == $user_id) {
            $delete = $db->prepare("DELETE FROM deadlines WHERE id = ?");
            if($delete->execute([$deadline_id])) {
                $_SESSION['flash_message'] = "Deadline deleted successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Failed to delete deadline.";
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_message'] = "You don't have permission to delete this deadline.";
            $_SESSION['flash_type'] = "error";
        }
    } catch (PDOException $e) {
        error_log("Failed to delete deadline: " . $e->getMessage());
        $_SESSION['flash_message'] = "Database error occurred.";
        $_SESSION['flash_type'] = "error";
    }
    
    header('Location: deadlines.php');
    exit;
}

// Handle Toggle Active Status
if(isset($_GET['toggle'])) {
    $deadline_id = filter_var($_GET['toggle'], FILTER_VALIDATE_INT);
    
    if(!$deadline_id) {
        $_SESSION['flash_message'] = "Invalid deadline ID.";
        $_SESSION['flash_type'] = "error";
        header('Location: deadlines.php');
        exit;
    }
    
    try {
        $check = $db->prepare("SELECT created_by, is_active FROM deadlines WHERE id = ?");
        $check->execute([$deadline_id]);
        $deadline = $check->fetch(PDO::FETCH_ASSOC);
        
        if($deadline && $deadline['created_by'] == $user_id) {
            $new_status = $deadline['is_active'] ? 0 : 1;
            $toggle = $db->prepare("UPDATE deadlines SET is_active = ? WHERE id = ?");
            if($toggle->execute([$new_status, $deadline_id])) {
                $_SESSION['flash_message'] = "Deadline " . ($new_status ? "activated" : "deactivated") . " successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Failed to update status.";
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_message'] = "You don't have permission to modify this deadline.";
            $_SESSION['flash_type'] = "error";
        }
    } catch (PDOException $e) {
        error_log("Failed to toggle deadline: " . $e->getMessage());
        $_SESSION['flash_message'] = "Database error occurred.";
        $_SESSION['flash_type'] = "error";
    }
    
    header('Location: deadlines.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get all deadlines with pagination
try {
    $query = "SELECT SQL_CALC_FOUND_ROWS d.*, u.full_name as created_by_name 
              FROM deadlines d
              LEFT JOIN users u ON d.created_by = u.id
              ORDER BY d.deadline_date DESC, d.deadline_time DESC
              LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
    $deadlines = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    $total_results = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
    $total_pages = ceil($total_results / $per_page);
} catch (PDOException $e) {
    error_log("Failed to fetch deadlines: " . $e->getMessage());
    $deadlines = [];
    $total_results = 0;
    $total_pages = 0;
}

// Get upcoming deadlines (next 30 days)
try {
    $upcoming_query = "SELECT * FROM deadlines 
                       WHERE deadline_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                       AND is_active = 1
                       ORDER BY deadline_date ASC";
    $upcoming = $db->query($upcoming_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch upcoming deadlines: " . $e->getMessage());
    $upcoming = [];
}

// Get statistics
$total_my_deadlines = 0;
foreach($deadlines as $dl) {
    if($dl['created_by'] == $user_id) $total_my_deadlines++;
}

try {
    $active_stmt = $db->prepare("SELECT COUNT(*) FROM deadlines WHERE created_by = ? AND is_active = 1 AND CONCAT(deadline_date, ' ', deadline_time) >= NOW()");
    $active_stmt->execute([$user_id]);
    $active_my_deadlines = $active_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Failed to fetch active deadlines: " . $e->getMessage());
    $active_my_deadlines = 0;
}

$back_url = '../dashboard.php';
if(isset($_SERVER['HTTP_REFERER']) && 
   !strpos($_SERVER['HTTP_REFERER'], 'deadlines.php') && 
   !strpos($_SERVER['HTTP_REFERER'], 'delete=') &&
   !strpos($_SERVER['HTTP_REFERER'], 'toggle=')) {
    $back_url = $_SERVER['HTTP_REFERER'];
}

$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Deadlines - Adviser Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/adviser/adviser-deadlines.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="admin-container">
        <div class="header">
            <div>
                <h1>Manage Deadlines</h1>
                <span class="header-subtitle">
                    You can only edit/delete deadlines you created
                </span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_my_deadlines; ?></h3>
                <p>My Deadlines</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $active_my_deadlines ?: 0; ?></h3>
                <p>Active Deadlines</p>
            </div>
        </div>

        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($upcoming)): ?>
        <div class="add-section">
            <h2>
                <span class="material-symbols-outlined">event_upcoming</span>
                Upcoming Deadlines (Next 30 Days)
            </h2>
            <div class="upcoming-grid">
                <?php foreach($upcoming as $up): 
                    $days_left = max(0, ceil((strtotime($up['deadline_date']) - time()) / 86400));
                    $is_own = ($up['created_by'] == $user_id);
                    
                    $visibility_label = '';
                    if($up['visibility_type'] === 'specific_students') {
                        $student_count = $up['visible_to_students'] ? count(explode(',', $up['visible_to_students'])) : 0;
                        $visibility_label = " · {$student_count} students";
                    }
                ?>
                <div class="deadline-card <?php echo $is_own ? 'own-deadline' : ''; ?>">
                    <div class="deadline-card-header">
                        <span class="category-badge">
                            <?php echo htmlspecialchars($up['category'] ?? 'General'); ?>
                        </span>
                        <?php if($is_own): ?>
                            <span class="own-badge">You</span>
                        <?php endif; ?>
                    </div>
                    <h4 class="deadline-card-title"><?php echo htmlspecialchars($up['title']); ?></h4>
                    <div class="deadline-card-datetime">
                        <span class="datetime-item">
                            <span class="material-symbols-outlined">calendar_month</span>
                            <?php echo date('M j, Y', strtotime($up['deadline_date'])); ?>
                        </span>
                        <?php if(!empty($up['deadline_time']) && $up['deadline_time'] !== '00:00:00'): ?>
                            <span class="datetime-item">
                                <span class="material-symbols-outlined">schedule</span>
                                <?php echo date('g:i A', strtotime($up['deadline_time'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if($up['visibility_type'] === 'specific_students'): ?>
                        <div class="visibility-info">
                            <span class="material-symbols-outlined">visibility</span>
                            Visible to specific students<?php echo $visibility_label; ?>
                        </div>
                    <?php endif; ?>
                    <div class="days-remaining <?php echo $days_left <= 3 ? 'urgent' : ''; ?>">
                        <?php echo $days_left; ?> days remaining
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="add-section">
            <h2>
                <span class="material-symbols-outlined">add_circle</span>
                Add New Deadline
            </h2>
            <form method="POST" class="add-form" id="deadlineForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Deadline Title *</label>
                        <input type="text" name="title" placeholder="e.g., Submit Chapter 1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" placeholder="e.g., Submission, Proposal, Paper" value="General" list="categorySuggestions">
                    <datalist id="categorySuggestions">
                        <option value="General">
                        <option value="Submission">
                        <option value="Proposal">
                        <option value="Approval">
                        <option value="Paper">
                        <option value="Presentation">
                        <option value="Defense">
                        <option value="Thesis">
                        <option value="Research">
                    </datalist>
                    <small class="help-text">Type or select a category</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Provide details about this deadline..."></textarea>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="deadline_date" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="deadline_time" value="23:59">
                        <small class="help-text">Default: 11:59 PM</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Send Notifications To</label>
                    <select name="target_type" id="targetType" onchange="toggleTargetFields()">
                        <option value="none">Don't send notifications</option>
                        <option value="my_students">All my advisees</option>
                        <option value="specific_students">Specific students</option>
                    </select>
                    <small class="help-text">Choose who will receive email notifications</small>
                </div>

                <div class="form-group target-field" id="studentsField" style="display: none;">
                    <label>Select Students to Notify</label>
                    
                    <?php if(empty($students)): ?>
                        <div class="empty-students-message">
                            <span class="material-symbols-outlined">info</span>
                            <p>You don't have any advisees yet. Students will appear here once they're assigned to you.</p>
                        </div>
                    <?php else: ?>
                        <div class="student-search-wrapper">
                            <input type="text" 
                                   id="studentSearch" 
                                   placeholder="Search by name or ID..." 
                                   class="student-search-input"
                                   onkeyup="filterStudents()">
                        </div>
                        
                        <div class="student-actions">
                            <button type="button" onclick="selectAllStudents()" class="select-all-btn">
                                <span class="material-symbols-outlined">select_all</span>
                                Select All
                            </button>
                            <button type="button" onclick="clearAllStudents()" class="clear-all-btn">
                                <span class="material-symbols-outlined">clear</span>
                                Clear All
                            </button>
                        </div>
                        
                        <div class="student-counter">
                            <span class="counter-text">
                                <span class="counter-number" id="selectedCount">0</span> students selected for notifications
                            </span>
                            <span class="counter-text">
                                Showing <span class="counter-number" id="visibleCount">0</span> of <?php echo count($students); ?> students
                            </span>
                        </div>
                        
                        <div class="student-selection-box" id="studentListContainer">
                            <?php foreach($students as $index => $student): ?>
                                <div class="student-item" data-name="<?php echo strtolower(htmlspecialchars($student['full_name'])); ?>" data-id="<?php echo strtolower(htmlspecialchars($student['id_number'])); ?>" data-index="<?php echo $index; ?>">
                                    <label>
                                        <input type="checkbox" name="target_students[]" value="<?php echo $student['id']; ?>" class="student-checkbox" onchange="updateSelectedCount()">
                                        <div class="student-info">
                                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                            <small>ID: <?php echo htmlspecialchars($student['id_number']); ?> | <?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></small>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="help-text">Selected students will receive email notifications</small>
                    <?php endif; ?>
                </div>

                <div class="visibility-section">
                    <h4>
                        <span class="material-symbols-outlined">visibility</span>
                        Who Can See This Deadline?
                    </h4>
                    
                    <div class="visibility-options">
                        <label class="visibility-option">
                            <input type="radio" name="visibility_type" value="all" checked onchange="toggleVisibilityField()">
                            <span>All users can see this deadline</span>
                        </label>
                        <label class="visibility-option">
                            <input type="radio" name="visibility_type" value="specific_students" onchange="toggleVisibilityField()">
                            <span>Only specific students can see this deadline</span>
                        </label>
                    </div>

                    <div class="form-group target-field" id="visibilityStudentsField" style="display: none;">
                        <label>Select Students Who Can See This Deadline</label>
                        
                        <?php if(empty($students)): ?>
                            <div class="empty-students-message">
                                <span class="material-symbols-outlined">info</span>
                                <p>You don't have any advisees yet. Students will appear here once they're assigned to you.</p>
                            </div>
                        <?php else: ?>
                            <div class="student-search-wrapper">
                                <input type="text" 
                                       id="visibilityStudentSearch" 
                                       placeholder="Search by name or ID..." 
                                       class="student-search-input"
                                       onkeyup="filterVisibilityStudents()">
                            </div>
                            
                            <div class="student-actions">
                                <button type="button" onclick="selectAllVisibilityStudents()" class="select-all-btn">
                                    <span class="material-symbols-outlined">select_all</span>
                                    Select All
                                </button>
                                <button type="button" onclick="clearAllVisibilityStudents()" class="clear-all-btn">
                                    <span class="material-symbols-outlined">clear</span>
                                    Clear All
                                </button>
                            </div>
                            
                            <div class="student-counter">
                                <span class="counter-text">
                                    <span class="counter-number" id="visibilitySelectedCount">0</span> students selected
                                </span>
                                <span class="counter-text">
                                    Showing <span class="counter-number" id="visibilityVisibleCount">0</span> of <?php echo count($students); ?> students
                                </span>
                            </div>
                            
                            <div class="student-selection-box" id="visibilityStudentListContainer">
                                <?php foreach($students as $index => $student): ?>
                                    <div class="student-item" data-name="<?php echo strtolower(htmlspecialchars($student['full_name'])); ?>" data-id="<?php echo strtolower(htmlspecialchars($student['id_number'])); ?>" data-index="<?php echo $index; ?>">
                                        <label>
                                            <input type="checkbox" name="visible_to_students[]" value="<?php echo $student['id']; ?>" class="visibility-student-checkbox" onchange="updateVisibilitySelectedCount()">
                                            <div class="student-info">
                                                <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                                <small>ID: <?php echo htmlspecialchars($student['id_number']); ?> | <?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></small>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="help-text">Only selected students will be able to view this deadline</small>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" name="add_deadline" class="btn-add">
                    <span class="material-symbols-outlined">add</span>
                    Add Deadline
                </button>
            </form>
        </div>

        <!-- All Deadlines Table with Search and Filter -->
        <div class="deadlines-table">
            <div class="deadlines-header">
                <h2>
                    <span class="material-symbols-outlined">list</span>
                    All Deadlines
                    <span class="deadline-count"><?php echo $total_results; ?> total</span>
                </h2>
                <div class="header-controls">
                    <div class="search-wrapper">
                        <span class="material-symbols-outlined search-icon">search</span>
                        <input type="text" 
                               id="deadlineSearch" 
                               class="search-input" 
                               placeholder="Search deadlines..."
                               onkeyup="searchDeadlines()">
                        <button class="clear-search" id="clearSearch" onclick="clearSearch()" style="display: none;">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    
                    <?php if(!empty($students)): ?>
                    <div class="filter-dropdown">
                        <select id="studentFilter" class="filter-select" onchange="filterByStudent()">
                            <option value="">All Deadlines</option>
                            <option value="my_students">My Advisees Only</option>
                            <?php foreach($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Visibility</th>
                            <th>Deadline</th>
                            <th>Created By</th>
                            <th style="width: 60px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($deadlines)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <span class="material-symbols-outlined">event_busy</span>
                                    <p>No deadlines added yet. Add your first deadline above!</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($deadlines as $dl): 
                                $deadline_datetime = $dl['deadline_date'] . ' ' . ($dl['deadline_time'] ?? '23:59:00');
                                $is_past = strtotime($deadline_datetime) < time();
                                $status_class = $dl['is_active'] ? 'active' : 'inactive';
                                if($is_past) $status_class = 'past';
                                $can_modify = ($dl['created_by'] == $user_id);
                                
                                $visibility_display = 'All Users';
                                $visibility_class = '';
                                if($dl['visibility_type'] === 'specific_students') {
                                    $student_count = $dl['visible_to_students'] ? count(explode(',', $dl['visible_to_students'])) : 0;
                                    $visibility_display = $student_count . ' Specific Students';
                                    $visibility_class = 'restricted';
                                }
                                
                                $student_ids = $dl['visible_to_students'] ? explode(',', $dl['visible_to_students']) : [];
                                $data_students = implode(',', $student_ids);
                            ?>
                            <tr data-student-ids="<?php echo htmlspecialchars($data_students); ?>" data-title="<?php echo strtolower(htmlspecialchars($dl['title'])); ?>" data-category="<?php echo strtolower(htmlspecialchars($dl['category'] ?? '')); ?>" data-description="<?php echo strtolower(htmlspecialchars($dl['description'] ?? '')); ?>">
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php 
                                        if($is_past) echo 'Past';
                                        else echo $dl['is_active'] ? 'Active' : 'Inactive'; 
                                        ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($dl['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($dl['category'] ?? 'General'); ?></td>
                                <td>
                                    <span class="visibility-badge <?php echo $visibility_class; ?>">
                                        <?php echo htmlspecialchars($visibility_display); ?>
                                    </span>
                                </td>
                                <td class="deadline-datetime-cell">
                                    <?php echo date('M d, Y', strtotime($dl['deadline_date'])); ?>
                                    <?php if(!empty($dl['deadline_time']) && $dl['deadline_time'] !== '00:00:00'): ?>
                                        <br><small><?php echo date('h:i A', strtotime($dl['deadline_time'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($dl['created_by_name'] ?? 'System'); ?>
                                    <?php if($can_modify): ?>
                                        <span class="own-indicator">(You)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($can_modify): ?>
                                    <div class="action-menu">
                                        <button type="button" class="meatball-btn" onclick="event.stopPropagation(); toggleDropdown(event, this)">
                                            <span class="material-symbols-outlined">more_horiz</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="javascript:void(0)" class="edit" onclick="event.stopPropagation(); openEditModal(<?php echo htmlspecialchars(json_encode($dl)); ?>); closeDropdowns();">
                                                <span class="material-symbols-outlined">edit</span>
                                                Edit
                                            </a>
                                            <?php if(!$is_past): ?>
                                            <a href="javascript:void(0)" class="toggle-status" onclick="event.stopPropagation(); showConfirmationModal({
                                                title: 'Toggle Status',
                                                message: 'Are you sure you want to <?php echo $dl['is_active'] ? 'deactivate' : 'activate'; ?> this deadline?',
                                                confirmUrl: '?toggle=<?php echo $dl['id']; ?>',
                                                confirmText: 'Yes, Toggle',
                                                type: 'warning'
                                            }); closeDropdowns();">
                                                <span class="material-symbols-outlined"><?php echo $dl['is_active'] ? 'visibility_off' : 'visibility'; ?></span>
                                                <?php echo $dl['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                            <?php endif; ?>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0)" class="delete" onclick="event.stopPropagation(); showConfirmationModal({
                                                title: 'Delete Deadline',
                                                message: 'Are you sure you want to delete this deadline?<br><br><strong>This action cannot be undone.</strong>',
                                                confirmUrl: '?delete=<?php echo $dl['id']; ?>',
                                                confirmText: 'Yes, Delete',
                                                type: 'delete'
                                            }); closeDropdowns();">
                                                <span class="material-symbols-outlined">delete</span>
                                                Delete
                                            </a>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <span class="view-only-label">View only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>" class="page-link">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>" class="page-link">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Deadline Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header-custom">
                <span class="material-symbols-outlined">edit_calendar</span>
                <h3>Edit Deadline</h3>
            </div>
            
            <div class="modal-body-custom">
                <form method="POST" id="editDeadlineForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="deadline_id" id="editDeadlineId">
                    <input type="hidden" name="edit_deadline" value="1">
                    
                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" id="editTitle" required>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" id="editCategory">
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="editDescription" rows="3"></textarea>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="deadline_date" id="editDeadlineDate" required>
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" name="deadline_time" id="editDeadlineTime">
                        </div>
                    </div>

                    <div class="visibility-section" style="margin-top: 15px;">
                        <h4 style="margin-bottom: 15px;">
                            <span class="material-symbols-outlined">visibility</span>
                            Who Can See This Deadline?
                        </h4>
                        
                        <div class="visibility-options">
                            <label class="visibility-option">
                                <input type="radio" name="visibility_type" value="all" id="editVisibilityAll" onchange="toggleEditVisibilityField()">
                                <span>All users can see this deadline</span>
                            </label>
                            <label class="visibility-option">
                                <input type="radio" name="visibility_type" value="specific_students" id="editVisibilitySpecific" onchange="toggleEditVisibilityField()">
                                <span>Only specific students can see this deadline</span>
                            </label>
                        </div>

                        <div class="form-group target-field" id="editVisibilityStudentsField" style="display: none;">
                            <label>Select Students Who Can See This Deadline</label>
                            
                            <?php if(empty($students)): ?>
                                <div class="empty-students-message">
                                    <span class="material-symbols-outlined">info</span>
                                    <p>You don't have any advisees yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="student-selection-box" style="max-height: 200px;">
                                    <?php foreach($students as $student): ?>
                                        <div class="student-item">
                                            <label>
                                                <input type="checkbox" name="visible_to_students[]" value="<?php echo $student['id']; ?>" class="edit-visibility-checkbox">
                                                <div class="student-info">
                                                    <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                                    <small>ID: <?php echo htmlspecialchars($student['id_number']); ?></small>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="student-actions" style="margin-top: 10px;">
                                    <button type="button" onclick="selectAllEditVisibility()" class="select-all-btn" style="padding: 6px 12px; font-size: 0.8rem;">Select All</button>
                                    <button type="button" onclick="clearAllEditVisibility()" class="clear-all-btn" style="padding: 6px 12px; font-size: 0.8rem;">Clear All</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer-custom">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save" form="editDeadlineForm" name="edit_deadline">
                    <span class="material-symbols-outlined">save</span>
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <?php include '../includes/dashboard-footer.php'; ?>
    <?php include '../includes/confirmation-modal.php'; ?>

    <script>
        // ========== MEATBALL MENU FUNCTIONS ==========
        function closeDropdowns() {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
        
        function toggleDropdown(event, button) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            const menu = button.nextElementSibling;
            const isOpen = menu.classList.contains('show');
            
            closeDropdowns();
            
            if (!isOpen) {
                menu.classList.add('show');
            }
        }
        
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.action-menu')) {
                closeDropdowns();
            }
        });
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDropdowns();
                closeEditModal();
            }
        });

        // ========== EDIT MODAL FUNCTIONS ==========
        function openEditModal(deadline) {
            document.getElementById('editDeadlineId').value = deadline.id;
            document.getElementById('editTitle').value = deadline.title;
            document.getElementById('editCategory').value = deadline.category || 'General';
            document.getElementById('editDescription').value = deadline.description || '';
            document.getElementById('editDeadlineDate').value = deadline.deadline_date;
            document.getElementById('editDeadlineTime').value = deadline.deadline_time || '23:59';
            
            // Set visibility type
            if (deadline.visibility_type === 'specific_students') {
                document.getElementById('editVisibilitySpecific').checked = true;
                document.getElementById('editVisibilityStudentsField').style.display = 'block';
                
                // Check the checkboxes for visible students
                const visibleIds = deadline.visible_to_students ? deadline.visible_to_students.split(',') : [];
                const checkboxes = document.querySelectorAll('.edit-visibility-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = visibleIds.includes(cb.value);
                });
            } else {
                document.getElementById('editVisibilityAll').checked = true;
                document.getElementById('editVisibilityStudentsField').style.display = 'none';
            }
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function toggleEditVisibilityField() {
            const visibilityField = document.getElementById('editVisibilityStudentsField');
            if (document.getElementById('editVisibilitySpecific').checked) {
                visibilityField.style.display = 'block';
            } else {
                visibilityField.style.display = 'none';
            }
        }
        
        function selectAllEditVisibility() {
            const checkboxes = document.querySelectorAll('.edit-visibility-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = true;
            });
        }
        
        function clearAllEditVisibility() {
            const checkboxes = document.querySelectorAll('.edit-visibility-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
            });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Student search and selection functions
        function filterStudents() {
            const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
            const studentItems = document.querySelectorAll('#studentListContainer .student-item');
            let visibleCount = 0;
            
            studentItems.forEach(item => {
                const name = item.getAttribute('data-name');
                const id = item.getAttribute('data-id');
                const matches = name.includes(searchTerm) || id.includes(searchTerm);
                
                if (matches) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            document.getElementById('visibleCount').textContent = visibleCount;
            updateSelectedCount();
        }

        function selectAllStudents() {
            const checkboxes = document.querySelectorAll('#studentListContainer .student-checkbox');
            const searchTerm = document.getElementById('studentSearch')?.value.toLowerCase() || '';
            
            checkboxes.forEach(checkbox => {
                const studentItem = checkbox.closest('.student-item');
                if (searchTerm === '') {
                    checkbox.checked = true;
                } else {
                    if (studentItem.style.display !== 'none') {
                        checkbox.checked = true;
                    }
                }
            });
            
            updateSelectedCount();
        }

        function clearAllStudents() {
            const checkboxes = document.querySelectorAll('#studentListContainer .student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('#studentListContainer .student-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selectedCount;
        }

        function filterVisibilityStudents() {
            const searchTerm = document.getElementById('visibilityStudentSearch').value.toLowerCase();
            const studentItems = document.querySelectorAll('#visibilityStudentListContainer .student-item');
            let visibleCount = 0;
            
            studentItems.forEach(item => {
                const name = item.getAttribute('data-name');
                const id = item.getAttribute('data-id');
                const matches = name.includes(searchTerm) || id.includes(searchTerm);
                
                if (matches) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            document.getElementById('visibilityVisibleCount').textContent = visibleCount;
            updateVisibilitySelectedCount();
        }

        function selectAllVisibilityStudents() {
            const checkboxes = document.querySelectorAll('#visibilityStudentListContainer .visibility-student-checkbox');
            const searchTerm = document.getElementById('visibilityStudentSearch')?.value.toLowerCase() || '';
            
            checkboxes.forEach(checkbox => {
                const studentItem = checkbox.closest('.student-item');
                if (searchTerm === '') {
                    checkbox.checked = true;
                } else {
                    if (studentItem.style.display !== 'none') {
                        checkbox.checked = true;
                    }
                }
            });
            
            updateVisibilitySelectedCount();
        }

        function clearAllVisibilityStudents() {
            const checkboxes = document.querySelectorAll('#visibilityStudentListContainer .visibility-student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateVisibilitySelectedCount();
        }

        function updateVisibilitySelectedCount() {
            const selectedCount = document.querySelectorAll('#visibilityStudentListContainer .visibility-student-checkbox:checked').length;
            document.getElementById('visibilitySelectedCount').textContent = selectedCount;
        }

        function toggleTargetFields() {
            const targetType = document.getElementById('targetType').value;
            const studentsField = document.getElementById('studentsField');
            
            if(targetType === 'specific_students') {
                studentsField.style.display = 'block';
                if (document.getElementById('studentSearch')) {
                    document.getElementById('studentSearch').value = '';
                    filterStudents();
                }
                updateSelectedCount();
            } else {
                studentsField.style.display = 'none';
            }
        }

        function toggleVisibilityField() {
            const visibilityType = document.querySelector('input[name="visibility_type"]:checked').value;
            const visibilityField = document.getElementById('visibilityStudentsField');
            
            if(visibilityType === 'specific_students') {
                visibilityField.style.display = 'block';
                if (document.getElementById('visibilityStudentSearch')) {
                    document.getElementById('visibilityStudentSearch').value = '';
                    filterVisibilityStudents();
                }
                updateVisibilitySelectedCount();
            } else {
                visibilityField.style.display = 'none';
            }
        }

        function searchDeadlines() {
            const searchTerm = document.getElementById('deadlineSearch').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            const clearBtn = document.getElementById('clearSearch');
            if (searchTerm.length > 0) {
                clearBtn.style.display = 'flex';
            } else {
                clearBtn.style.display = 'none';
            }
            
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;
                
                const title = row.getAttribute('data-title') || '';
                const category = row.getAttribute('data-category') || '';
                const description = row.getAttribute('data-description') || '';
                const text = title + ' ' + category + ' ' + description;
                
                const matches = text.includes(searchTerm);
                
                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateNoResultsMessage(visibleCount);
            
            const studentFilter = document.getElementById('studentFilter');
            if (studentFilter && studentFilter.value !== '') {
                filterByStudent();
            }
        }

        function clearSearch() {
            document.getElementById('deadlineSearch').value = '';
            searchDeadlines();
        }

        function filterByStudent() {
            const filterValue = document.getElementById('studentFilter').value;
            const rows = document.querySelectorAll('tbody tr');
            const searchTerm = document.getElementById('deadlineSearch').value.toLowerCase();
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;
                
                const title = row.getAttribute('data-title') || '';
                const category = row.getAttribute('data-category') || '';
                const description = row.getAttribute('data-description') || '';
                const text = title + ' ' + category + ' ' + description;
                const matchesSearch = text.includes(searchTerm);
                
                let matchesFilter = true;
                if (filterValue === 'my_students') {
                    matchesFilter = true;
                } else if (filterValue !== '') {
                    const studentIds = row.getAttribute('data-student-ids') || '';
                    matchesFilter = studentIds.split(',').includes(filterValue);
                }
                
                if (matchesSearch && matchesFilter) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateNoResultsMessage(visibleCount);
        }

        function updateNoResultsMessage(visibleCount) {
            let noResultsRow = document.querySelector('.no-results-row');
            const tbody = document.querySelector('tbody');
            
            if (noResultsRow) {
                noResultsRow.remove();
            }
            
            if (visibleCount === 0 && tbody.children.length > 0) {
                const firstRow = tbody.children[0];
                if (!firstRow.querySelector('.empty-state')) {
                    const row = document.createElement('tr');
                    row.className = 'no-results-row';
                    row.innerHTML = `
                        <td colspan="7" class="empty-state">
                            <span class="material-symbols-outlined">search_off</span>
                            <p>No deadlines match your search criteria</p>
                        </td>
                    `;
                    tbody.appendChild(row);
                }
            }
        }

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

        document.addEventListener('DOMContentLoaded', function() {
            const totalItems = document.querySelectorAll('#studentListContainer .student-item').length;
            if (document.getElementById('visibleCount')) {
                document.getElementById('visibleCount').textContent = totalItems;
            }
            updateSelectedCount();
            
            const totalVisibilityItems = document.querySelectorAll('#visibilityStudentListContainer .student-item').length;
            if (document.getElementById('visibilityVisibleCount')) {
                document.getElementById('visibilityVisibleCount').textContent = totalVisibilityItems;
            }
            updateVisibilitySelectedCount();
            
            const searchInput = document.getElementById('studentSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', filterStudents);
            }
            
            const visibilitySearchInput = document.getElementById('visibilityStudentSearch');
            if (visibilitySearchInput) {
                visibilitySearchInput.addEventListener('keyup', filterVisibilityStudents);
            }
        });
    </script>
</body>
</html>