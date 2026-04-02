<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';
require_once '../includes/notification-mailer.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header('Location: ../auth/login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$database = new Database();
$db = $database->getConnection();

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Get departments for dropdown
$sections_query = "SELECT DISTINCT department as section FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
$sections = $db->query($sections_query)->fetchAll(PDO::FETCH_COLUMN);

// Get all students
$students_query = "SELECT id, full_name, id_number, department FROM users WHERE role = 'student' ORDER BY full_name";
$students = $db->query($students_query)->fetchAll(PDO::FETCH_ASSOC);

// Handle Add Deadline
if(isset($_POST['add_deadline'])) {
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token.";
        $_SESSION['flash_type'] = "error";
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadline_date = $_POST['deadline_date'] ?? '';
        $deadline_time = $_POST['deadline_time'] ?? '23:59:00';
        $target_type = $_POST['target_type'] ?? 'all';
        $target_role = $_POST['target_role'] ?? 'all';
        $target_section = $_POST['target_section'] ?? null;
        $visibility_type = $_POST['visibility_type'] ?? 'all';
        $category = trim($_POST['category'] ?? 'General');
        
        // Get student IDs from POST
        $target_students = isset($_POST['target_students']) ? implode(',', $_POST['target_students']) : null;
        $visible_to_students = isset($_POST['visible_to_students']) ? implode(',', $_POST['visible_to_students']) : null;
        
        if(empty($title)) {
            $_SESSION['flash_message'] = "Title is required";
            $_SESSION['flash_type'] = "error";
        } elseif(empty($deadline_date)) {
            $_SESSION['flash_message'] = "Date is required";
            $_SESSION['flash_type'] = "error";
        } elseif(strtotime($deadline_date . ' ' . $deadline_time) < time()) {
            $_SESSION['flash_message'] = "Deadline cannot be in the past";
            $_SESSION['flash_type'] = "error";
        } elseif($visibility_type === 'specific_students' && empty($visible_to_students)) {
            $_SESSION['flash_message'] = "Please select at least one student who can see this deadline";
            $_SESSION['flash_type'] = "error";
        } else {
            $insert = $db->prepare("INSERT INTO deadlines (title, description, deadline_date, deadline_time, target_type, target_role, target_section, target_ids, visibility_type, visible_to_students, is_active, category, created_by, created_by_role) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 'admin')");
            
            if($insert->execute([$title, $description, $deadline_date, $deadline_time, $target_type, $target_role, $target_section, $target_students, $visibility_type, $visible_to_students, $category, $_SESSION['user_id']])) {
                
                // ============ SEND EMAIL NOTIFICATIONS ============
                $notification_count = 0;
                $deadline_datetime = date('F j, Y g:i A', strtotime($deadline_date . ' ' . $deadline_time));
                $users_to_notify = [];
                
                // Get users based on target type
                if($target_type === 'all') {
                    $users_to_notify = $db->query("SELECT email, full_name FROM users WHERE email IS NOT NULL AND email != ''")->fetchAll();
                } 
                elseif($target_type === 'role' && $target_role !== 'all') {
                    $stmt = $db->prepare("SELECT email, full_name FROM users WHERE role = ? AND email IS NOT NULL AND email != ''");
                    $stmt->execute([$target_role]);
                    $users_to_notify = $stmt->fetchAll();
                }
                elseif($target_type === 'specific_students' && $target_students) {
                    $ids = explode(',', $target_students);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id IN ($placeholders) AND email IS NOT NULL AND email != ''");
                    $stmt->execute($ids);
                    $users_to_notify = $stmt->fetchAll();
                }
                
                // Send emails
                foreach($users_to_notify as $user) {
                    if(!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                        $email_subject = "New Deadline: " . $title;
                        $email_body = "Dear " . $user['full_name'] . ",\n\n";
                        $email_body .= "A new deadline has been set:\n";
                        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        $email_body .= "📌 Title: " . $title . "\n";
                        $email_body .= "📅 Date: " . date('F j, Y', strtotime($deadline_date)) . "\n";
                        $email_body .= "⏰ Time: " . date('g:i A', strtotime($deadline_time)) . "\n";
                        if(!empty($description)) {
                            $email_body .= "📝 Details: " . $description . "\n";
                        }
                        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $email_body .= "Please log in to the Capstone Tracker system for more details.\n\n";
                        $email_body .= "Best regards,\n";
                        $email_body .= "KLD Capstone Tracker Team";
                        
                        $email_sent = sendCapstoneNotification(
                            $user['email'],
                            $user['full_name'],
                            $title,
                            'deadline',
                            $email_body,
                            $_SESSION['full_name']
                        );
                        
                        if($email_sent) $notification_count++;
                    }
                }
                
                $_SESSION['flash_message'] = "Deadline added successfully! " . $notification_count . " notification(s) sent.";
                $_SESSION['flash_type'] = "success";
                
            } else {
                $error = $insert->errorInfo();
                error_log("Add deadline failed: " . $error[2]);
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
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadline_date = $_POST['deadline_date'] ?? '';
        $deadline_time = $_POST['deadline_time'] ?? '23:59:00';
        $category = trim($_POST['category'] ?? 'General');
        $target_type = $_POST['target_type'] ?? 'all';
        $target_role = $_POST['target_role'] ?? 'all';
        $visibility_type = $_POST['visibility_type'] ?? 'all';
        
        $target_students = isset($_POST['target_students']) ? implode(',', $_POST['target_students']) : null;
        $visible_to_students = isset($_POST['visible_to_students']) ? implode(',', $_POST['visible_to_students']) : null;
        
        if(empty($title) || empty($deadline_date)) {
            $_SESSION['flash_message'] = "Title and date are required";
            $_SESSION['flash_type'] = "error";
        } else {
            $update = $db->prepare("UPDATE deadlines SET 
                title = ?, description = ?, deadline_date = ?, deadline_time = ?, 
                category = ?, target_type = ?, target_role = ?, target_ids = ?,
                visibility_type = ?, visible_to_students = ?
                WHERE id = ?");
            
            if($update->execute([$title, $description, $deadline_date, $deadline_time, 
                $category, $target_type, $target_role, $target_students, 
                $visibility_type, $visible_to_students, $deadline_id])) {
                $_SESSION['flash_message'] = "Deadline updated successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Database error occurred.";
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header('Location: deadlines.php');
    exit;
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM deadlines WHERE id = ?")->execute([$id]);
    $_SESSION['flash_message'] = "Deadline deleted successfully!";
    $_SESSION['flash_type'] = "success";
    header('Location: deadlines.php');
    exit;
}

// Handle Toggle
if(isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE deadlines SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    $_SESSION['flash_message'] = "Deadline status updated!";
    $_SESSION['flash_type'] = "success";
    header('Location: deadlines.php');
    exit;
}

// Get all deadlines
$deadlines = $db->query("SELECT d.*, u.full_name as creator_name 
                         FROM deadlines d 
                         LEFT JOIN users u ON d.created_by = u.id 
                         ORDER BY d.deadline_date DESC")->fetchAll();

$total_deadlines = count($deadlines);
$active_deadlines = $db->query("SELECT COUNT(*) FROM deadlines WHERE is_active = 1 AND deadline_date >= CURDATE()")->fetchColumn();
$past_deadlines = $db->query("SELECT COUNT(*) FROM deadlines WHERE deadline_date < CURDATE()")->fetchColumn();
$upcoming_deadlines = $db->query("SELECT COUNT(*) FROM deadlines WHERE deadline_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Deadlines - KLD Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-deadlines.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <div class="header">
            <div>
                <h1>Deadline Management</h1>
                <p class="header-subtitle">Create and manage important deadlines</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card"><h3><?php echo $total_deadlines; ?></h3><p>Total</p></div>
            <div class="stat-card"><h3><?php echo $active_deadlines ?: 0; ?></h3><p>Active</p></div>
            <div class="stat-card"><h3><?php echo $upcoming_deadlines ?: 0; ?></h3><p>Upcoming (7d)</p></div>
            <div class="stat-card"><h3><?php echo $past_deadlines ?: 0; ?></h3><p>Past</p></div>
        </div>

        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- ADD DEADLINE FORM -->
        <div class="title-card form-card">
            <div class="card-header">
                <h2><span class="material-symbols-outlined">event</span> Add New Deadline</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" required style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="General" style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;"></textarea>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="deadline_date" min="<?php echo date('Y-m-d'); ?>" required style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="deadline_time" value="23:59" style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                    </div>
                </div>

                <!-- Target Audience -->
                <div class="form-group">
                    <label>Send Notifications To</label>
                    <select name="target_type" id="targetType" style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                        <option value="all">All Users</option>
                        <option value="role">Specific Role</option>
                        <option value="specific_students">Specific Students</option>
                    </select>
                </div>

                <div id="roleField" style="display:none; margin-top:10px;">
                    <label>Select Role</label>
                    <select name="target_role" style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                        <option value="all">All Roles</option>
                        <option value="student">Students</option>
                        <option value="adviser">Advisers</option>
                        <option value="admin">Admins</option>
                    </select>
                </div>

                <div id="studentsField" style="display:none; margin-top:10px;">
                    <label>Select Students to Notify</label>
                    <div class="student-checkbox-list">
                        <?php foreach($students as $student): ?>
                            <label>
                                <input type="checkbox" name="target_students[]" value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['id_number']); ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Visibility -->
                <div class="form-group" style="margin-top:15px;">
                    <label>Who Can See This Deadline?</label>
                    <select name="visibility_type" id="visibilityType" style="width:100%; padding:10px; border:2px solid #e0e0e0; border-radius:8px;">
                        <option value="all">All Users</option>
                        <option value="specific_students">Only Specific Students</option>
                    </select>
                </div>

                <div id="visibilityField" style="display:none; margin-top:10px;">
                    <label>Select Students Who Can See This Deadline</label>
                    <div class="student-checkbox-list">
                        <?php foreach($students as $student): ?>
                            <label>
                                <input type="checkbox" name="visible_to_students[]" value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['id_number']); ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="add_deadline" class="btn-primary">Add Deadline</button>
                </div>
            </form>
        </div>

        <!-- DEADLINES LIST -->
        <div class="title-card">
            <div class="card-header">
                <h2><span class="material-symbols-outlined">list</span> Existing Deadlines</h2>
            </div>
            <div class="table-wrapper">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fbf8;">
                            <th style="padding:12px; text-align:left;">Status</th>
                            <th style="padding:12px; text-align:left;">Title</th>
                            <th style="padding:12px; text-align:left;">Category</th>
                            <th style="padding:12px; text-align:left;">Target</th>
                            <th style="padding:12px; text-align:left;">Visibility</th>
                            <th style="padding:12px; text-align:left;">Deadline</th>
                            <th style="width: 60px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($deadlines)): ?>
                            <tr><td colspan="7" style="padding:40px; text-align:center;">No deadlines added yet.</td></tr>
                        <?php else: ?>
                            <?php foreach($deadlines as $d): 
                                $is_past = strtotime($d['deadline_date'] . ' ' . $d['deadline_time']) < time();
                                $status = $is_past ? 'past' : ($d['is_active'] ? 'active' : 'inactive');
                            ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:12px;"><span class="status-badge <?php echo $status; ?>"><?php echo $is_past ? 'Past' : ($d['is_active'] ? 'Active' : 'Inactive'); ?></span></td>
                                <td style="padding:12px;"><strong><?php echo htmlspecialchars($d['title']); ?></strong></td>
                                <td style="padding:12px;"><?php echo htmlspecialchars($d['category'] ?? 'General'); ?></td>
                                <td style="padding:12px;">
                                    <?php 
                                    if($d['target_type'] == 'all') echo 'All Users';
                                    elseif($d['target_type'] == 'role') echo ucfirst($d['target_role']);
                                    elseif($d['target_type'] == 'specific_students') echo 'Specific Students';
                                    ?>
                                 </td>
                                <td style="padding:12px;">
                                    <?php echo $d['visibility_type'] == 'all' ? 'All Users' : 'Restricted'; ?>
                                 </td>
                                <td style="padding:12px;"><?php echo date('M d, Y', strtotime($d['deadline_date'])); ?><br><small><?php echo date('h:i A', strtotime($d['deadline_time'])); ?></small></td>
                                <td style="padding:12px;">
                                    <div class="action-menu">
                                        <button class="meatball-btn" onclick="toggleDropdown(event, this)">
                                            <span class="material-symbols-outlined">more_horiz</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="javascript:void(0)" class="edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($d)); ?>); closeDropdowns();">
                                                <span class="material-symbols-outlined">edit</span>
                                                Edit
                                            </a>
                                            <a href="javascript:void(0)" class="toggle-status" onclick="toggleDeadlineStatus(<?php echo $d['id']; ?>, <?php echo $d['is_active'] ? 'false' : 'true'; ?>); closeDropdowns();">
                                                <span class="material-symbols-outlined"><?php echo $d['is_active'] ? 'pause_circle' : 'play_circle'; ?></span>
                                                <?php echo $d['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0)" class="delete" onclick="showConfirmationModal({
                                                title: 'Delete Deadline',
                                                message: 'Are you sure you want to delete this deadline?<br><br><strong>This action cannot be undone.</strong>',
                                                confirmUrl: '?delete=<?php echo $d['id']; ?>',
                                                confirmText: 'Yes, Delete',
                                                type: 'delete'
                                            }); closeDropdowns();">
                                                <span class="material-symbols-outlined">delete</span>
                                                Delete
                                            </a>
                                        </div>
                                    </div>
                                 </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../includes/dashboard-footer.php'; ?>
    <?php include '../includes/confirmation-modal.php'; ?>

    <!-- EDIT DEADLINE MODAL -->
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

                    <div class="form-group">
                        <label>Send Notifications To</label>
                        <select name="target_type" id="editTargetType">
                            <option value="all">All Users</option>
                            <option value="role">Specific Role</option>
                            <option value="specific_students">Specific Students</option>
                        </select>
                    </div>

                    <div id="editRoleField" class="edit-target-field" style="display:none;">
                        <label>Select Role</label>
                        <select name="target_role">
                            <option value="student">Students</option>
                            <option value="adviser">Advisers</option>
                            <option value="admin">Admins</option>
                        </select>
                    </div>

                    <div id="editStudentsField" class="edit-target-field" style="display:none;">
                        <label>Select Students to Notify</label>
                        <div class="student-checkbox-list" id="editTargetStudentsList">
                            <?php foreach($students as $student): ?>
                                <label>
                                    <input type="checkbox" name="target_students[]" value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['id_number']); ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label>Who Can See This Deadline?</label>
                        <select name="visibility_type" id="editVisibilityType">
                            <option value="all">All Users</option>
                            <option value="specific_students">Only Specific Students</option>
                        </select>
                    </div>

                    <div id="editVisibilityField" class="edit-target-field" style="display:none;">
                        <label>Select Students Who Can See This Deadline</label>
                        <div class="student-checkbox-list" id="editVisibleStudentsList">
                            <?php foreach($students as $student): ?>
                                <label>
                                    <input type="checkbox" name="visible_to_students[]" value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['id_number']); ?>)
                                </label>
                            <?php endforeach; ?>
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

    <!-- Toggle Confirmation Modal (Hidden by default, reused from confirmation modal) -->
    <div class="modal" id="toggleModal">
        <div class="modal-content">
            <div class="modal-header-custom">
                <span class="material-symbols-outlined">toggle_on</span>
                <h3 id="toggleModalTitle">Confirm Status Change</h3>
            </div>
            <div class="modal-body-custom">
                <p id="toggleModalMessage">Are you sure you want to change the status of this deadline?</p>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-cancel" onclick="closeToggleModal()">Cancel</button>
                <button type="button" class="btn-save" id="confirmToggleBtn">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Toggle target fields for add form
        document.getElementById('targetType').addEventListener('change', function() {
            document.getElementById('roleField').style.display = 'none';
            document.getElementById('studentsField').style.display = 'none';
            if(this.value === 'role') {
                document.getElementById('roleField').style.display = 'block';
            } else if(this.value === 'specific_students') {
                document.getElementById('studentsField').style.display = 'block';
            }
        });
        
        // Toggle visibility field for add form
        document.getElementById('visibilityType').addEventListener('change', function() {
            document.getElementById('visibilityField').style.display = this.value === 'specific_students' ? 'block' : 'none';
        });

        // ========== MEATBALL MENU FUNCTIONS ==========
        function closeDropdowns() {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
        
        function toggleDropdown(event, button) {
            event.stopPropagation();
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
            
            // Set target type
            const targetType = document.getElementById('editTargetType');
            targetType.value = deadline.target_type || 'all';
            
            // Handle target fields
            const roleField = document.getElementById('editRoleField');
            const studentsField = document.getElementById('editStudentsField');
            
            if (deadline.target_type === 'role') {
                roleField.style.display = 'block';
                studentsField.style.display = 'none';
                const roleSelect = roleField.querySelector('select');
                roleSelect.value = deadline.target_role || 'student';
            } else if (deadline.target_type === 'specific_students') {
                roleField.style.display = 'none';
                studentsField.style.display = 'block';
                
                // Check target students
                const targetIds = deadline.target_ids ? deadline.target_ids.split(',') : [];
                const checkboxes = studentsField.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => {
                    cb.checked = targetIds.includes(cb.value);
                });
            } else {
                roleField.style.display = 'none';
                studentsField.style.display = 'none';
            }
            
            // Set visibility type
            const visibilityType = document.getElementById('editVisibilityType');
            visibilityType.value = deadline.visibility_type || 'all';
            
            // Handle visibility field
            const visibilityField = document.getElementById('editVisibilityField');
            if (deadline.visibility_type === 'specific_students') {
                visibilityField.style.display = 'block';
                const visibleIds = deadline.visible_to_students ? deadline.visible_to_students.split(',') : [];
                const visibleCheckboxes = visibilityField.querySelectorAll('input[type="checkbox"]');
                visibleCheckboxes.forEach(cb => {
                    cb.checked = visibleIds.includes(cb.value);
                });
            } else {
                visibilityField.style.display = 'none';
            }
            
            document.getElementById('editModal').classList.add('active');
        }
        
        // Add event listeners for edit form dynamic fields
        document.getElementById('editTargetType')?.addEventListener('change', function() {
            const roleField = document.getElementById('editRoleField');
            const studentsField = document.getElementById('editStudentsField');
            
            roleField.style.display = 'none';
            studentsField.style.display = 'none';
            
            if (this.value === 'role') {
                roleField.style.display = 'block';
            } else if (this.value === 'specific_students') {
                studentsField.style.display = 'block';
            }
        });
        
        document.getElementById('editVisibilityType')?.addEventListener('change', function() {
            const visibilityField = document.getElementById('editVisibilityField');
            visibilityField.style.display = this.value === 'specific_students' ? 'block' : 'none';
        });
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // ========== TOGGLE STATUS WITH CONFIRMATION ==========
        let pendingToggleId = null;
        let pendingToggleState = null;
        
        function toggleDeadlineStatus(id, newState) {
            pendingToggleId = id;
            pendingToggleState = newState;
            
            const modalTitle = document.getElementById('toggleModalTitle');
            const modalMessage = document.getElementById('toggleModalMessage');
            
            modalTitle.innerHTML = newState ? 'Activate Deadline' : 'Deactivate Deadline';
            modalMessage.innerHTML = newState 
                ? 'Are you sure you want to <strong>activate</strong> this deadline? It will become visible to selected users.'
                : 'Are you sure you want to <strong>deactivate</strong> this deadline? It will be hidden from users.';
            
            document.getElementById('toggleModal').classList.add('active');
        }
        
        function closeToggleModal() {
            document.getElementById('toggleModal').classList.remove('active');
            pendingToggleId = null;
            pendingToggleState = null;
        }
        
        document.getElementById('confirmToggleBtn')?.addEventListener('click', function() {
            if (pendingToggleId) {
                window.location.href = '?toggle=' + pendingToggleId;
            }
        });
        
        // Close toggle modal when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const toggleModal = document.getElementById('toggleModal');
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == toggleModal) {
                closeToggleModal();
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
    </script>
</body>
</html>