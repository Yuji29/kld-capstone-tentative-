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

// Handle Add Department
if(isset($_POST['add_department'])) {
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $dept_name = htmlspecialchars(trim($_POST['department_name']), ENT_QUOTES, 'UTF-8');
        
        if(!empty($dept_name)) {
            $check = $db->prepare("SELECT id FROM departments WHERE name = ?");
            $check->execute([$dept_name]);
            
            if($check->rowCount() > 0) {
                $_SESSION['flash_message'] = "Department already exists!";
                $_SESSION['flash_type'] = "error";
            } else {
                $insert = $db->prepare("INSERT INTO departments (name) VALUES (?)");
                if($insert->execute([$dept_name])) {
                    $_SESSION['flash_message'] = "Department added successfully!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $error_info = $insert->errorInfo();
                    error_log("Add department failed: " . $error_info[2]);
                    $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        } else {
            $_SESSION['flash_message'] = "Department name cannot be empty.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header('Location: departments.php');
    exit;
}

// Handle Delete Department
if(isset($_GET['delete'])) {
    $dept_id = filter_var($_GET['delete'], FILTER_VALIDATE_INT);
    
    if($dept_id) {
        $check_users = $db->prepare("SELECT COUNT(*) FROM users WHERE department = (SELECT name FROM departments WHERE id = ?)");
        $check_users->execute([$dept_id]);
        $user_count = $check_users->fetchColumn();
        
        if($user_count > 0) {
            $_SESSION['flash_message'] = "Cannot delete: This department has $user_count user(s) assigned.";
            $_SESSION['flash_type'] = "error";
        } else {
            $delete = $db->prepare("DELETE FROM departments WHERE id = ?");
            if($delete->execute([$dept_id])) {
                $_SESSION['flash_message'] = "Department deleted successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $error_info = $delete->errorInfo();
                error_log("Delete department failed: " . $error_info[2]);
                $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header('Location: departments.php');
    exit;
}

// Handle Edit Department
if(isset($_POST['edit_department'])) {
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $dept_id = filter_var($_POST['dept_id'], FILTER_VALIDATE_INT);
        $new_name = htmlspecialchars(trim($_POST['new_name']), ENT_QUOTES, 'UTF-8');
        
        if($dept_id && !empty($new_name)) {
            $check = $db->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
            $check->execute([$new_name, $dept_id]);
            
            if($check->rowCount() > 0) {
                $_SESSION['flash_message'] = "Department name already exists!";
                $_SESSION['flash_type'] = "error";
            } else {
                $update = $db->prepare("UPDATE departments SET name = ? WHERE id = ?");
                if($update->execute([$new_name, $dept_id])) {
                    $_SESSION['flash_message'] = "Department updated successfully!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $error_info = $update->errorInfo();
                    error_log("Update department failed: " . $error_info[2]);
                    $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        } else {
            $_SESSION['flash_message'] = "Department name cannot be empty.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header('Location: departments.php');
    exit;
}

// Initialize default values
$total_depts = 0;
$total_users = 0;
$depts = [];

// Get all departments with user counts
try {
    $query = "SELECT d.*, 
                     (SELECT COUNT(*) FROM users WHERE department = d.name) as user_count 
              FROM departments d 
              ORDER BY d.name";
    $depts = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    $total_depts = count($depts);
} catch (PDOException $e) {
    error_log("Departments fetch error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading departments.";
    $_SESSION['flash_type'] = "error";
}

// Get total users with error handling
try {
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (PDOException $e) {
    error_log("Total users count error: " . $e->getMessage());
    $total_users = 0;
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
    <title>Manage Departments - KLD Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-departments.css?v=<?php echo time(); ?>">
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
        
        /* Meatball Menu Styles */
        .action-menu {
            position: relative;
            display: inline-block;
        }
        
        .meatball-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #666;
            width: 36px;
            height: 36px;
        }
        
        .meatball-btn:hover {
            background: #f0f0f0;
            color: var(--primary-color);
        }
        
        .meatball-btn .material-symbols-outlined {
            font-size: 20px;
        }
        
        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 5px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            min-width: 140px;
            z-index: 1000;
            display: none;
            overflow: hidden;
            border: 1px solid #e2efdf;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu a,
        .dropdown-menu button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            text-decoration: none;
            color: #333;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
        }
        
        .dropdown-menu a:hover,
        .dropdown-menu button:hover {
            background: #f5f5f5;
        }
        
        .dropdown-menu a.edit:hover {
            background: #e9f2e7;
            color: var(--primary-color);
        }
        
        .dropdown-menu button.delete:hover {
            background: #fee7e7;
            color: var(--danger-color);
        }
        
        .dropdown-menu .material-symbols-outlined {
            font-size: 18px;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e2efdf;
            margin: 5px 0;
        }
        
        td {
            overflow: visible;
        }
        
        th:last-child,
        td:last-child {
            overflow: visible;
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
                <h1>Department Management</h1>
                <p class="header-subtitle">Manage academic departments and sections</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid stats-grid-2">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">category</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_depts; ?></h3>
                    <p>Total Departments</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">people</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
        </div>

        <!-- Messages from session flash -->
        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Add Department Form -->
        <div class="title-card form-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">add_circle</span>
                    Add New Department
                </h2>
            </div>
            
            <form method="POST" class="add-form" id="addDepartmentForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Department Name *</label>
                        <input type="text" name="department_name" id="deptName" maxlength="100" placeholder="Enter department name (e.g., Computer Science)" required>
                        <div class="char-counter"><span id="deptCharCount">0</span>/100 characters</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_department" class="btn-primary">
                        <span class="material-symbols-outlined">add</span>
                        Add Department
                    </button>
                </div>
            </form>
        </div>

        <!-- Departments List -->
        <div class="title-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">list</span>
                    Existing Departments
                </h2>
                <span class="item-count"><?php echo count($depts); ?> departments</span>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Department Name</th>
                            <th>Users Count</th>
                            <th>Created</th>
                            <th style="width: 60px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($depts)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <span class="material-symbols-outlined">category</span>
                                    <p>No departments added yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($depts as $dept): ?>
                            <tr>
                                <td><?php echo $dept['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                <td>
                                    <span class="user-count">
                                        <?php echo $dept['user_count']; ?> user<?php echo $dept['user_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($dept['created_at'])); ?></td>
                                <td>
                                    <div class="action-menu">
                                        <button class="meatball-btn" onclick="toggleDropdown(event, this)">
                                            <span class="material-symbols-outlined">more_horiz</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="javascript:void(0)" class="edit" onclick="openEditModal(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars(addslashes($dept['name'])); ?>'); closeDropdowns();">
                                                <span class="material-symbols-outlined">edit</span>
                                                Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0)" class="delete" onclick="showConfirmationModal({
                                                title: 'Delete Department',
                                                message: 'Are you sure you want to delete this department?<br><br><strong>This action cannot be undone.</strong>',
                                                confirmUrl: '?delete=<?php echo $dept['id']; ?>',
                                                confirmText: 'Yes, Delete',
                                                type: 'delete'
                                            }); closeDropdowns();">
                                                <span class="material-symbols-outlined">delete</span>
                                                Delete
                                            </a>
                                        </div>
                                    </div>
                                 </td
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
            <div class="modal-header-custom">
                <span class="material-symbols-outlined">edit_note</span>
                <h3>Edit Department</h3>
            </div>
                                
            <div class="modal-body-custom">
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="dept_id" id="editDeptId">
                
                    <div class="form-group">
                        <label for="editDeptName">Department Name <span class="required">*</span></label>
                        <input type="text" name="new_name" id="editDeptName" maxlength="100" placeholder="Enter department name" required>
                        <div class="char-counter"><span id="editDeptCharCount">0</span>/100 characters</div>
                    </div>
                </form>
            </div>
        
            <div class="modal-footer-custom">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save" form="editForm">
                    <span class="material-symbols-outlined">save</span>
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <script>
        // Close all dropdowns
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
            
            closeDropdowns();
            
            if (!isOpen) {
                menu.classList.add('show');
                
                // Check if dropdown goes out of viewport
                const rect = menu.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                
                if (rect.bottom > viewportHeight) {
                    menu.style.top = 'auto';
                    menu.style.bottom = '100%';
                    menu.style.marginTop = '0';
                    menu.style.marginBottom = '5px';
                } else {
                    menu.style.top = '100%';
                    menu.style.bottom = 'auto';
                    menu.style.marginTop = '5px';
                    menu.style.marginBottom = '0';
                }
            }
        }
        
        // Close dropdowns when clicking outside
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
        
        // Close dropdowns when scrolling
        window.addEventListener('scroll', function() {
            closeDropdowns();
        });

        // Character counter for add form
        document.getElementById('deptName')?.addEventListener('input', function() {
            document.getElementById('deptCharCount').textContent = this.value.length;
        });

        // Character counter for edit modal
        document.getElementById('editDeptName')?.addEventListener('input', function() {
            document.getElementById('editDeptCharCount').textContent = this.value.length;
        });

        // Modal functions
        function openEditModal(id, name) {
            document.getElementById('editDeptId').value = id;
            document.getElementById('editDeptName').value = name;
            document.getElementById('editDeptCharCount').textContent = name.length;
            document.getElementById('editModal').classList.add('active');
            closeDropdowns();
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
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