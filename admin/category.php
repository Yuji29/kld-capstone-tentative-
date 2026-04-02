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

// Handle Add Category
if(isset($_POST['add_category'])) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $category_name = trim($_POST['category_name']);
        $color = $_POST['color'] ?? '#4CAF50';
        
        // Validate color
        if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            $color = '#4CAF50';
        }
        
        if(!empty($category_name)) {
            // Check if category already exists
            $check = $db->prepare("SELECT id FROM categories WHERE name = ?");
            $check->execute([$category_name]);
            
            if($check->rowCount() > 0) {
                $_SESSION['flash_message'] = "Category already exists!";
                $_SESSION['flash_type'] = "error";
            } else {
                $insert = $db->prepare("INSERT INTO categories (name, color) VALUES (?, ?)");
                if($insert->execute([$category_name, $color])) {
                    $_SESSION['flash_message'] = "Category added successfully!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $error_info = $insert->errorInfo();
                    error_log("Add category failed: " . $error_info[2]);
                    $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        } else {
            $_SESSION['flash_message'] = "Category name cannot be empty.";
            $_SESSION['flash_type'] = "error";
        }
    }
    // Redirect to prevent form resubmission
    header('Location: category.php');
    exit;
}

// Handle Delete Category
if(isset($_GET['delete'])) {
    $category_id = filter_var($_GET['delete'], FILTER_VALIDATE_INT);
    
    if($category_id) {
        // Check if category is being used
        $check = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE category_id = ?");
        $check->execute([$category_id]);
        $count = $check->fetchColumn();
        
        if($count > 0) {
            $_SESSION['flash_message'] = "Cannot delete category. It is being used by $count capstone title(s).";
            $_SESSION['flash_type'] = "error";
        } else {
            $delete = $db->prepare("DELETE FROM categories WHERE id = ?");
            if($delete->execute([$category_id])) {
                $_SESSION['flash_message'] = "Category deleted successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $error_info = $delete->errorInfo();
                error_log("Delete category failed: " . $error_info[2]);
                $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    header('Location: category.php');
    exit;
}

// Handle Edit Category
if(isset($_POST['edit_category'])) {
    // Check CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
        $new_name = trim($_POST['new_name']);
        $new_color = $_POST['new_color'] ?? '#4CAF50';
        
        // Validate color
        if (!preg_match('/^#[a-f0-9]{6}$/i', $new_color)) {
            $new_color = '#4CAF50';
        }
        
        if($category_id && !empty($new_name)) {
            // Check if new name already exists
            $check = $db->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
            $check->execute([$new_name, $category_id]);
            
            if($check->rowCount() > 0) {
                $_SESSION['flash_message'] = "Category name already exists!";
                $_SESSION['flash_type'] = "error";
            } else {
                $update = $db->prepare("UPDATE categories SET name = ?, color = ? WHERE id = ?");
                if($update->execute([$new_name, $new_color, $category_id])) {
                    $_SESSION['flash_message'] = "Category updated successfully!";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $error_info = $update->errorInfo();
                    error_log("Update category failed: " . $error_info[2]);
                    $_SESSION['flash_message'] = "Database error occurred. Please try again.";
                    $_SESSION['flash_type'] = "error";
                }
            }
        } else {
            $_SESSION['flash_message'] = "Category name cannot be empty.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header('Location: category.php');
    exit;
}

// Get all categories with title counts
$categories = $db->query("SELECT c.*, 
                          (SELECT COUNT(*) FROM capstone_titles WHERE category_id = c.id) as title_count 
                          FROM categories c 
                          ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);

$total_categories = count($categories);
$total_titles = $db->query("SELECT COUNT(*) FROM capstone_titles")->fetchColumn();

$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - KLD Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-category.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <div class="header">
            <div>
                <h1>Category Management</h1>
                <p class="header-subtitle">Manage and organize capstone categories</p>
            </div>
        </div>

        <div class="stats-grid stats-grid-2">
            <div class="stat-card">
                <h3><?php echo $total_categories; ?></h3>
                <p>Total Categories</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_titles; ?></h3>
                <p>Total Capstone Titles</p>
            </div>
        </div>

        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- ADD CATEGORY FORM -->
        <div class="title-card form-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">add_circle</span>
                    Add New Category
                </h2>
            </div>
            
            <form method="POST" id="addCategoryForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Category Name *</label>
                            <input type="text" name="category_name" id="categoryName" maxlength="100" placeholder="e.g., Artificial Intelligence" required>
                            <div class="char-counter"><span id="catCharCount">0</span>/100 characters</div>
                    </div>
                    <div class="form-group">
                        <label>Category Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="color" id="categoryColor" value="#4CAF50">
                            <span class="color-label" id="colorPreviewText">#4CAF50</span>
                        </div>
                        <div class="color-preview-box" id="colorPreview"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_category" class="btn-primary">
                        <span class="material-symbols-outlined">add</span>
                        Add Category
                    </button>
                </div>
            </form>
        </div>

        <!-- SEARCH BOX -->
        <div class="search-section">
            <div class="search-box">
                <span class="material-symbols-outlined search-icon">search</span>
                <input type="text" id="categorySearch" placeholder="Search categories...">
                <button class="clear-search" id="clearSearch" onclick="clearCategorySearch()" style="display: none;">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>

        <!-- CATEGORIES LIST -->
        <div class="title-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">bookmark</span>
                    Existing Categories
                </h2>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Color</th>
                            <th>Category Name</th>
                            <th>Titles</th>
                            <th>Created</th>
                            <th style="width: 60px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesTableBody">
                        <?php if(empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <span class="material-symbols-outlined">bookmark</span>
                                    <p>No categories added yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($categories as $category): ?>
                            <tr>
                                <td>
                                    <div class="color-badge" style="background-color: <?php echo htmlspecialchars($category['color'] ?? '#4CAF50'); ?>;"></div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                <td><?php echo $category['title_count']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                <td>
                                    <div class="action-menu">
                                        <button class="meatball-btn" onclick="toggleDropdown(event, this)">
                                            <span class="material-symbols-outlined">more_horiz</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="javascript:void(0)" class="edit" onclick="openEditModal(
                                                <?php echo $category['id']; ?>, 
                                                '<?php echo htmlspecialchars(addslashes($category['name'])); ?>',
                                                '<?php echo htmlspecialchars($category['color'] ?? '#4CAF50'); ?>'
                                            ); closeDropdowns();">
                                                <span class="material-symbols-outlined">edit</span>
                                                Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0)" class="delete" onclick="showConfirmationModal({
                                                title: 'Delete Category',
                                                message: 'Are you sure you want to delete this category?<br><br><strong>This action cannot be undone.</strong>',
                                                confirmUrl: '?delete=<?php echo $category['id']; ?>',
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

    <!-- EDIT MODAL -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header-custom">
                <span class="material-symbols-outlined">edit_note</span>
                <h3>Edit Category</h3>
            </div>
            
            <div class="modal-body-custom">
                <form method="POST" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="category_id" id="editCategoryId">
                                    
                    <div class="form-group">
                        <label>Category Name <span class="required">*</span></label>
                        <input type="text" name="new_name" id="editCategoryName" maxlength="100" placeholder="Category name" required>
                        <div class="char-counter"><span id="editCharCount">0</span>/100 characters</div>
                    </div>
                
                    <div class="form-group">
                        <label>Category Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="new_color" id="editCategoryColor" value="#4CAF50">
                            <span class="color-label">Choose color</span>
                        </div>
                        <div class="color-preview-box" id="editColorPreview"></div>
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
            // Initialize character counters
            updateCharCount('categoryName', 'catCharCount');
            updateCharCount('editCategoryName', 'editCharCount');
            
            // Initialize color preview
            updateColorPreview('categoryColor', 'colorPreview', 'colorPreviewText');
            updateColorPreview('editCategoryColor', 'editColorPreview', null);
            
            // Add input listeners
            document.getElementById('categoryName')?.addEventListener('input', function() {
                updateCharCount('categoryName', 'catCharCount');
            });
            
            document.getElementById('editCategoryName')?.addEventListener('input', function() {
                updateCharCount('editCategoryName', 'editCharCount');
            });
            
            document.getElementById('categoryColor')?.addEventListener('input', function() {
                updateColorPreview('categoryColor', 'colorPreview', 'colorPreviewText');
            });
            
            document.getElementById('editCategoryColor')?.addEventListener('input', function() {
                updateColorPreview('editCategoryColor', 'editColorPreview', null);
            });
            
            // Search functionality
            document.getElementById('categorySearch')?.addEventListener('keyup', filterCategories);
        });

        function updateCharCount(inputId, countId) {
            const input = document.getElementById(inputId);
            const count = document.getElementById(countId);
            if (input && count) {
                count.textContent = input.value.length;
            }
        }

        function updateColorPreview(inputId, previewId, textId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const text = document.getElementById(textId);
            
            if (input && preview) {
                const color = input.value;
                preview.style.backgroundColor = color;
                if (text) {
                    text.textContent = color;
                }
            }
        }

        function filterCategories() {
            const searchTerm = document.getElementById('categorySearch').value.toLowerCase();
            const rows = document.querySelectorAll('#categoriesTableBody tr');
            const clearBtn = document.getElementById('clearSearch');
            let visibleCount = 0;
            
            clearBtn.style.display = searchTerm.length > 0 ? 'flex' : 'none';
            
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;
                const categoryName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const matches = categoryName.includes(searchTerm);
                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });
            
            // Show no results message
            let noResultsRow = document.querySelector('.no-results-row');
            if (noResultsRow) noResultsRow.remove();
            
            if (visibleCount === 0 && document.querySelector('#categoriesTableBody tr:not(.empty-state)')) {
                const tbody = document.getElementById('categoriesTableBody');
                const row = document.createElement('tr');
                row.className = 'no-results-row';
                row.innerHTML = '<td colspan="5" class="empty-state"><span class="material-symbols-outlined">search_off</span><p>No categories match your search</p></td>';
                tbody.appendChild(row);
            }
        }

        function clearCategorySearch() {
            document.getElementById('categorySearch').value = '';
            filterCategories();
        }

        function openEditModal(id, name, color) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCategoryColor').value = color;
            document.getElementById('editColorPreview').style.backgroundColor = color;
            document.getElementById('editCharCount').textContent = name.length;
            document.getElementById('editModal').classList.add('active');
            closeDropdowns(); // Close any open dropdowns
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function clearAddForm() {
            const form = document.getElementById('addCategoryForm');
            if (form && confirm('Clear all form fields?')) {
                form.reset();
                document.getElementById('catCharCount').textContent = '0';
                updateColorPreview('categoryColor', 'colorPreview', 'colorPreviewText');
            }
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