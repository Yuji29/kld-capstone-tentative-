<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';

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

// Get departments for dropdown
$dept_query = "SELECT name FROM departments ORDER BY name";
$departments = $db->query($dept_query)->fetchAll(PDO::FETCH_COLUMN);

// Handle Add User
if(isset($_POST['add_user'])) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $full_name = htmlspecialchars(trim($_POST['full_name']), ENT_QUOTES, 'UTF-8');
        $id_number = htmlspecialchars(trim($_POST['id_number']), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars(trim($_POST['email']), ENT_QUOTES, 'UTF-8');
        $password = $_POST['password'];
        $role = $_POST['role'];
        $department = !empty($_POST['department']) ? htmlspecialchars(trim($_POST['department']), ENT_QUOTES, 'UTF-8') : null;
        
        if(empty($full_name) || empty($id_number) || empty($email) || empty($password)) {
            $_SESSION['flash_message'] = "All fields are required.";
            $_SESSION['flash_type'] = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_message'] = "Invalid email format.";
            $_SESSION['flash_type'] = "error";
        } elseif(strlen($password) < 8) {
            $_SESSION['flash_message'] = "Password must be at least 8 characters long.";
            $_SESSION['flash_type'] = "error";
        } elseif(!preg_match('/[A-Z]/', $password)) {
            $_SESSION['flash_message'] = "Password must contain at least one uppercase letter.";
            $_SESSION['flash_type'] = "error";
        } elseif(!preg_match('/[a-z]/', $password)) {
            $_SESSION['flash_message'] = "Password must contain at least one lowercase letter.";
            $_SESSION['flash_type'] = "error";
        } elseif(!preg_match('/[0-9]/', $password)) {
            $_SESSION['flash_message'] = "Password must contain at least one number.";
            $_SESSION['flash_type'] = "error";
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE id_number = ? OR email = ?");
            $check->execute([$id_number, $email]);
            
            if($check->rowCount() > 0) {
                $_SESSION['flash_message'] = "ID Number or email already exists!";
                $_SESSION['flash_type'] = "error";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert = $db->prepare("INSERT INTO users (id_number, email, password, full_name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
                if($insert->execute([$id_number, $email, $hashed_password, $full_name, $role, $department])) {
                    $_SESSION['flash_message'] = "User added successfully!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $error_info = $insert->errorInfo();
                    error_log("Add user failed: " . $error_info[2]);
                    $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        }
    }
    header('Location: users.php');
    exit;
}

// Handle Delete User
if(isset($_GET['delete'])) {
    $user_id = filter_var($_GET['delete'], FILTER_VALIDATE_INT);
    
    if($user_id) {
        if($user_id == $_SESSION['user_id']) {
            $_SESSION['flash_message'] = "You cannot delete your own account!";
            $_SESSION['flash_type'] = "error";
        } else {
            try {
                $user_info = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                $user_info->execute([$user_id]);
                $user_data = $user_info->fetch(PDO::FETCH_ASSOC);
                
                if($user_data) {
                    $delete = $db->prepare("DELETE FROM users WHERE id = ?");
                    if($delete->execute([$user_id])) {
                        $_SESSION['flash_message'] = "User '" . htmlspecialchars($user_data['full_name']) . "' deleted successfully!";
                        $_SESSION['flash_type'] = "success";
                    } else {
                        $error_info = $delete->errorInfo();
                        error_log("Delete user failed: " . $error_info[2]);
                        $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                        $_SESSION['flash_type'] = "error";
                    }
                }
            } catch (PDOException $e) {
                error_log("Delete user exception: " . $e->getMessage());
                $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header('Location: users.php');
    exit;
}

// Handle Edit User
if(isset($_POST['edit_user'])) {
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        $full_name = htmlspecialchars(trim($_POST['full_name']), ENT_QUOTES, 'UTF-8');
        $id_number = htmlspecialchars(trim($_POST['id_number']), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars(trim($_POST['email']), ENT_QUOTES, 'UTF-8');
        $role = $_POST['role'];
        $department = !empty($_POST['department']) ? htmlspecialchars(trim($_POST['department']), ENT_QUOTES, 'UTF-8') : null;
        
        if($user_id && !empty($full_name) && !empty($id_number) && !empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash_message'] = "Invalid email format.";
                $_SESSION['flash_type'] = "error";
            } else {
                $check = $db->prepare("SELECT id FROM users WHERE (id_number = ? OR email = ?) AND id != ?");
                $check->execute([$id_number, $email, $user_id]);
                
                if($check->rowCount() > 0) {
                    $_SESSION['flash_message'] = "ID Number or email already exists!";
                    $_SESSION['flash_type'] = "error";
                } else {
                    if(!empty($_POST['new_password'])) {
                        $new_password = $_POST['new_password'];
                        if(strlen($new_password) < 8) {
                            $_SESSION['flash_message'] = "Password must be at least 8 characters long.";
                            $_SESSION['flash_type'] = "error";
                        } elseif(!preg_match('/[A-Z]/', $new_password)) {
                            $_SESSION['flash_message'] = "Password must contain at least one uppercase letter.";
                            $_SESSION['flash_type'] = "error";
                        } elseif(!preg_match('/[a-z]/', $new_password)) {
                            $_SESSION['flash_message'] = "Password must contain at least one lowercase letter.";
                            $_SESSION['flash_type'] = "error";
                        } elseif(!preg_match('/[0-9]/', $new_password)) {
                            $_SESSION['flash_message'] = "Password must contain at least one number.";
                            $_SESSION['flash_type'] = "error";
                        } else {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $update = $db->prepare("UPDATE users SET full_name = ?, id_number = ?, email = ?, role = ?, department = ?, password = ? WHERE id = ?");
                            $success = $update->execute([$full_name, $id_number, $email, $role, $department, $hashed_password, $user_id]);
                            
                            if($success) {
                                $_SESSION['flash_message'] = "User updated successfully!";
                                $_SESSION['flash_type'] = "success";
                            } else {
                                $error_info = $update->errorInfo();
                                error_log("Update user failed: " . $error_info[2]);
                                $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                                $_SESSION['flash_type'] = "error";
                            }
                        }
                    } else {
                        $update = $db->prepare("UPDATE users SET full_name = ?, id_number = ?, email = ?, role = ?, department = ? WHERE id = ?");
                        $success = $update->execute([$full_name, $id_number, $email, $role, $department, $user_id]);
                        
                        if($success) {
                            $_SESSION['flash_message'] = "User updated successfully!";
                            $_SESSION['flash_type'] = "success";
                        } else {
                            $error_info = $update->errorInfo();
                            error_log("Update user failed: " . $error_info[2]);
                            $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                            $_SESSION['flash_type'] = "error";
                        }
                    }
                }
            }
        } else {
            $_SESSION['flash_message'] = "All fields are required.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header('Location: users.php');
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';

$query = "SELECT * FROM users";
$params = [];
$conditions = [];

if(!empty($search)) {
    $conditions[] = "(full_name LIKE ? OR email LIKE ? OR id_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if(!empty($role_filter)) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
}

if(!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC";

$total_users = 0;
$total_students = 0;
$total_advisers = 0;
$total_admins = 0;
$users = [];

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users = count($users);
    foreach($users as $user) {
        if($user['role'] == 'student') $total_students++;
        elseif($user['role'] == 'adviser') $total_advisers++;
        elseif($user['role'] == 'admin') $total_admins++;
    }
} catch (PDOException $e) {
    error_log("Users query error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading users.";
    $_SESSION['flash_type'] = "error";
    $users = [];
}

$back_url = 'dashboard.php';
if(isset($_SERVER['HTTP_REFERER']) && 
   !strpos($_SERVER['HTTP_REFERER'], 'users.php') && 
   !strpos($_SERVER['HTTP_REFERER'], 'delete=') &&
   !strpos($_SERVER['HTTP_REFERER'], 'edit_user')) {
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
    <title>Manage Users - KLD Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/users.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <div class="header">
            <div>
                <h1>User Management</h1>
                <p class="header-subtitle">Manage students, advisers, and administrators</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid stats-grid-4">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">people</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">school</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_students; ?></h3>
                    <p>Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">rate_review</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_advisers; ?></h3>
                    <p>Advisers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_admins; ?></h3>
                    <p>Admins</p>
                </div>
            </div>
        </div>

        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Add User Form -->
        <div class="title-card form-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">person_add</span>
                    Add New User
                </h2>
            </div>
            
            <form method="POST" class="add-form" id="addUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" id="fullName" maxlength="100" placeholder="e.g., Juan Dela Cruz" required>
                        <div class="char-counter"><span id="fullNameCharCount">0</span>/100 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label>ID Number *</label>
                        <input type="text" name="id_number" id="idNumber" maxlength="50" placeholder="e.g., 2025-8-00001" required>
                    </div>
                </div>
                
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="email" autocomplete="on" maxlength="100" placeholder="user@kld.edu.ph" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" id="password" maxlength="255" placeholder="Enter password" required>
                    </div>
                </div>
                
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" id="role" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="adviser">Adviser</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="department">
                            <option value="">None</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_user" class="btn-primary">
                        <span class="material-symbols-outlined">add</span>
                        Add User
                    </button>
                </div>
            </form>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="search-box">
                    <span class="search-icon material-symbols-outlined">search</span>
                    <input type="text" name="search" placeholder="Search by name, email, or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if(!empty($search)): ?>
                        <a href="?" class="clear-search">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="filter-group">
                    <select name="role_filter" class="filter-select">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students</option>
                        <option value="adviser" <?php echo $role_filter === 'adviser' ? 'selected' : ''; ?>>Advisers</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                    
                    <button type="submit" class="btn-filter">
                        <span class="material-symbols-outlined">filter_alt</span>
                        Filter
                    </button>
                    
                    <?php if(!empty($search) || !empty($role_filter)): ?>
                        <a href="?" class="clear-filters">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Users List -->
        <div class="title-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">group</span>
                    Users List
                </h2>
                <span class="item-count"><?php echo count($users); ?> users</span>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>ID Number</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Joined</th>
                            <th style="width: 60px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <span class="material-symbols-outlined">person_off</span>
                                    <p>No users found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['id_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['department'] ?? '-'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-menu">
                                        <button class="meatball-btn" onclick="toggleDropdown(event, this)">
                                            <span class="material-symbols-outlined">more_horiz</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="javascript:void(0)" class="edit" onclick="openEditModal(
                                                <?php echo $user['id']; ?>,
                                                '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>',
                                                '<?php echo htmlspecialchars($user['id_number']); ?>',
                                                '<?php echo htmlspecialchars($user['email']); ?>',
                                                '<?php echo $user['role']; ?>',
                                                '<?php echo htmlspecialchars(addslashes($user['department'] ?? '')); ?>'
                                            )">
                                                <span class="material-symbols-outlined">edit</span>
                                                Edit
                                            </a>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <div class="dropdown-divider"></div>
                                                <a href="javascript:void(0)" class="delete" onclick="showConfirmationModal({
                                                    title: 'Delete User',
                                                    message: 'Are you sure you want to delete this user?<br><br><strong>This action cannot be undone.</strong>',
                                                    confirmUrl: '?delete=<?php echo $user['id']; ?>',
                                                    confirmText: 'Yes, Delete',
                                                    type: 'delete'
                                                }); closeDropdowns();">
                                                    <span class="material-symbols-outlined">delete</span>
                                                    Delete
                                                </a>
                                            <?php endif; ?>
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

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <!-- Custom Header -->
            <div class="modal-header-custom">
                <span class="material-symbols-outlined">person_edit</span>
                <h3>Edit User</h3>
            </div>
        
            <!-- Custom Body -->
            <div class="modal-body-custom">
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="user_id" id="editUserId">
                                                
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" id="editFullName" maxlength="100" required>
                        <div class="char-counter"><span id="editFullNameCharCount">0</span>/100 characters</div>
                    </div>
                                                
                    <div class="form-group">
                        <label>ID Number <span class="required">*</span></label>
                        <input type="text" name="id_number" id="editIdNumber" maxlength="50" required>
                    </div>
                                                
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" id="editEmail" maxlength="100" autocomplete="on" required>
                    </div>
                                                
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="editPassword" maxlength="255" placeholder="Leave blank to keep current">
                        <div class="password-hint">Minimum 8 characters, at least one uppercase, one lowercase, and one number</div>
                    </div>
                                                
                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Role <span class="required">*</span></label>
                            <select name="role" id="editRole" required>
                                <option value="student">Student</option>
                                <option value="adviser">Adviser</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                                                
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department" id="editDepartment">
                                <option value="">None</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
                                    
            <!-- Custom Footer -->
            <div class="modal-footer-custom">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save" form="editForm">
                    <span class="material-symbols-outlined">save</span>
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

    <script>
        // Close all dropdowns when clicking outside
        function closeDropdowns() {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }
        
        // Toggle dropdown menu
        function toggleDropdown(event, button) {
            event.stopPropagation();
            const menu = button.nextElementSibling;
            const isOpen = menu.classList.contains('show');
            
            // Close all other dropdowns first
            closeDropdowns();
            
            if (!isOpen) {
                menu.classList.add('show');
            }
        }
        
        // Close dropdowns when clicking anywhere else
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.action-menu')) {
                closeDropdowns();
            }
        });
        
        // Close dropdown on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDropdowns();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            if (performance.navigation.type === 1) {
                sessionStorage.removeItem('form_submitted');
            }
            
            document.getElementById('fullName')?.addEventListener('input', function() {
                document.getElementById('fullNameCharCount').textContent = this.value.length;
            });
            
            document.getElementById('editFullName')?.addEventListener('input', function() {
                document.getElementById('editFullNameCharCount').textContent = this.value.length;
            });
            
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    sessionStorage.setItem('form_submitted', 'true');
                });
            });
        });

        function openEditModal(id, fullName, idNumber, email, role, department) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editFullNameCharCount').textContent = fullName.length;
            document.getElementById('editIdNumber').value = idNumber;
            document.getElementById('editEmail').value = email;
            document.getElementById('editRole').value = role;
            document.getElementById('editDepartment').value = department;
            document.getElementById('editPassword').value = '';
            document.getElementById('editModal').classList.add('active');
            closeDropdowns(); // Close any open dropdowns
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }

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