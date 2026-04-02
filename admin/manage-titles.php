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

// Handle Batch Delete
if (isset($_POST['batch_delete']) && isset($_POST['selected_titles'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
    } else {
        $selected_titles = $_POST['selected_titles'];
        
        if (is_array($selected_titles) && !empty($selected_titles)) {
            $selected_titles = array_map('intval', $selected_titles);
            $placeholders = implode(',', array_fill(0, count($selected_titles), '?'));
            
            $db->beginTransaction();
            
            try {
                // Delete paper files
                $papers_query = "SELECT file_path FROM papers WHERE title_id IN ($placeholders)";
                $papers_stmt = $db->prepare($papers_query);
                $papers_stmt->execute($selected_titles);
                $papers = $papers_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $upload_dir = realpath(dirname(dirname(__FILE__)) . '/uploads/papers/');

                foreach($papers as $paper) {
                    $file_path = realpath($paper['file_path']);
                    if($file_path && strpos($file_path, $upload_dir) === 0 && file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // Delete directories
                foreach($selected_titles as $title_id) {
                    $paper_dir = "../uploads/papers/title_{$title_id}";
                    if(is_dir($paper_dir)) {
                        $files = glob($paper_dir . '/*');
                        foreach($files as $file) {
                            if(is_file($file)) {
                                unlink($file);
                            }
                        }
                        rmdir($paper_dir);
                    }
                }
                
                // Delete from database
                $db->prepare("DELETE FROM papers WHERE title_id IN ($placeholders)")->execute($selected_titles);
                $db->prepare("DELETE FROM comments WHERE title_id IN ($placeholders)")->execute($selected_titles);
                $db->prepare("DELETE FROM adviser_requests WHERE capstone_id IN ($placeholders)")->execute($selected_titles);
                $db->prepare("DELETE FROM capstone_titles WHERE id IN ($placeholders)")->execute($selected_titles);
                
                $db->commit();
                $_SESSION['flash_message'] = "Successfully deleted " . count($selected_titles) . " title(s).";
                $_SESSION['flash_type'] = "success";
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "Error deleting titles: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_message'] = "No titles selected.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header('Location: manage-titles.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Handle Single Delete
if (isset($_GET['delete'])) {
    $title_id = filter_var($_GET['delete'], FILTER_VALIDATE_INT);
    
    if ($title_id) {
        if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['flash_message'] = "Invalid security token.";
            $_SESSION['flash_type'] = "error";
        } else {
            try {
                $db->beginTransaction();
                
                $papers_stmt = $db->prepare("SELECT file_path FROM papers WHERE title_id = ?");
                $papers_stmt->execute([$title_id]);
                $papers = $papers_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $upload_dir = realpath(dirname(dirname(__FILE__)) . '/uploads/papers/');

                foreach($papers as $paper) {
                    $file_path = realpath($paper['file_path']);
                    if($file_path && strpos($file_path, $upload_dir) === 0 && file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                $paper_dir = "../uploads/papers/title_{$title_id}";
                if(is_dir($paper_dir)) {
                    $files = glob($paper_dir . '/*');
                    foreach($files as $file) {
                        if(is_file($file)) {
                            unlink($file);
                        }
                    }
                    rmdir($paper_dir);
                }
                
                $db->prepare("DELETE FROM papers WHERE title_id = ?")->execute([$title_id]);
                $db->prepare("DELETE FROM comments WHERE title_id = ?")->execute([$title_id]);
                $db->prepare("DELETE FROM adviser_requests WHERE capstone_id = ?")->execute([$title_id]);
                $db->prepare("DELETE FROM capstone_titles WHERE id = ?")->execute([$title_id]);
                
                $db->commit();
                $_SESSION['flash_message'] = "Title deleted successfully.";
                $_SESSION['flash_type'] = "success";
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "Error deleting title: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        }
    }
    unset($_GET['delete']);
    unset($_GET['csrf_token']);
    header('Location: manage-titles.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get categories for filter
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$query = "SELECT SQL_CALC_FOUND_ROWS ct.*, 
                 c.name as category_name,
                 c.color as category_color,
                 u.full_name as student_name,
                 u.id_number as student_id_number,
                 a.full_name as adviser_name,
                 (SELECT COUNT(*) FROM papers WHERE title_id = ct.id) as paper_count,
                 (SELECT COUNT(*) FROM comments WHERE title_id = ct.id) as comment_count
          FROM capstone_titles ct
          LEFT JOIN categories c ON ct.category_id = c.id
          LEFT JOIN users u ON ct.student_id = u.id
          LEFT JOIN users a ON ct.adviser_id = a.id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (ct.title LIKE ? OR ct.abstract LIKE ? OR u.full_name LIKE ? OR u.id_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status)) {
    $query .= " AND ct.status = ?";
    $params[] = $status;
}

if ($category_id > 0) {
    $query .= " AND ct.category_id = ?";
    $params[] = $category_id;
}

switch($sort) {
    case 'oldest':
        $query .= " ORDER BY ct.created_at ASC";
        break;
    case 'title_asc':
        $query .= " ORDER BY ct.title ASC";
        break;
    case 'title_desc':
        $query .= " ORDER BY ct.title DESC";
        break;
    case 'status':
        $query .= " ORDER BY ct.status, ct.created_at DESC";
        break;
    default:
        $query .= " ORDER BY ct.created_at DESC";
        break;
}

$query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$titles = [];
$total_results = 0;
$total_pages = 0;

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_results = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
    $total_pages = ceil($total_results / $per_page);
} catch (PDOException $e) {
    error_log("Manage titles query error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading titles.";
    $_SESSION['flash_type'] = "error";
}

$stats = [
    'total' => 0,
    'pending' => 0,
    'active' => 0,
    'completed' => 0,
    'revisions' => 0
];

try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM capstone_titles")->fetchColumn();
    $stats['pending'] = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status = 'pending_review'")->fetchColumn();
    $stats['active'] = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status = 'active'")->fetchColumn();
    $stats['completed'] = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status = 'completed'")->fetchColumn();
    $stats['revisions'] = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status = 'revisions'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Manage titles stats error: " . $e->getMessage());
}

$back_url = 'dashboard.php';
if(isset($_SERVER['HTTP_REFERER']) && 
   !strpos($_SERVER['HTTP_REFERER'], 'manage-titles.php') && 
   !strpos($_SERVER['HTTP_REFERER'], 'delete=') &&
   !strpos($_SERVER['HTTP_REFERER'], 'batch_delete')) {
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
    <title>Manage Titles - KLD Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-manage-titles.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="browse-container">
        <div class="header">
            <div>
                <h1>Manage Titles</h1>
                <p class="header-subtitle">View, filter, and delete capstone titles</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid stats-grid-5">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Titles</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">pending_actions</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">play_circle</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['active']; ?></h3>
                    <p>Active</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">edit_note</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['revisions']; ?></h3>
                    <p>Revisions</p>
                </div>
            </div>
        </div>

        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-form" id="filterForm">
                <div class="search-box">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input type="text" 
                           name="search" 
                           placeholder="Search by title, author, or ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <?php if(!empty($search)): ?>
                        <a href="?" class="clear-search">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="filter-group">
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="pending_review" <?php echo $status === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="revisions" <?php echo $status === 'revisions' ? 'selected' : ''; ?>>Revisions</option>
                    </select>

                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="sort" class="filter-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
                        <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
                        <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>By Status</option>
                    </select>

                    <button type="submit" class="btn-filter">
                        <span class="material-symbols-outlined">filter_alt</span>
                        Apply Filters
                    </button>
                    
                    <?php if(!empty($search) || !empty($status) || $category_id > 0 || $sort !== 'newest'): ?>
                        <a href="?" class="clear-filters">Clear All</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="results-summary">
            <p>Showing <strong><?php echo count($titles); ?></strong> of <strong><?php echo $total_results; ?></strong> titles</p>
        </div>

        <!-- Batch Delete Bar -->
        <div class="batch-bar" id="batchBar" style="display: none;">
            <div class="batch-info">
                <label class="select-all">
                    <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)">
                    <span>Select All</span>
                </label>
                <span class="selected-count" id="selectedCount">0</span>
                <span>titles selected</span>
            </div>
            <div class="batch-actions">
                <button class="btn-clear" onclick="clearAllSelections()">
                    <span class="material-symbols-outlined">close</span>
                    Clear
                </button>
                <button class="btn-batch-delete" id="batchDeleteBtn" onclick="confirmBatchDelete()" disabled>
                    <span class="material-symbols-outlined">delete_sweep</span>
                    Delete Selected
                </button>
            </div>
        </div>

        <!-- Batch Delete Form -->
        <form method="POST" id="batchDeleteForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="batch_delete" value="1">

            <!-- Titles Table -->
<div class="title-card">
    <div class="card-header">
        <h2>
            <span class="material-symbols-outlined">list</span>
            All Titles
        </h2>
        <span class="item-count"><?php echo $total_results; ?> titles</span>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th class="checkbox-cell" style="width: 40px;">
                        <input type="checkbox" id="tableSelectAll" onclick="toggleSelectAll(this)">
                    </th>
                    <th style="min-width: 250px;">Title & Details</th>
                    <th style="width: 120px;">Category</th>
                    <th style="width: 180px;">Student</th>
                    <th style="width: 180px;">Adviser</th>
                    <th style="width: 130px;">Status</th>
                    <th style="width: 70px; text-align: center;">Papers</th>
                    <th style="width: 110px;">Created</th>
                    <th style="width: 70px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($titles)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            <span class="material-symbols-outlined">search_off</span>
                            <p>No titles found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($titles as $title): ?>
                    <tr>
                        <td class="checkbox-cell" style="text-align: center;">
                            <input type="checkbox" 
                                   name="selected_titles[]" 
                                   value="<?php echo $title['id']; ?>" 
                                   class="title-select" 
                                   onchange="updateBatchBar()">
                        </td>
                        <td>
                            <div class="title-info">
                                <h4>
                                    <a href="../titles/view.php?id=<?php echo $title['id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars(substr($title['title'], 0, 60)) . (strlen($title['title']) > 60 ? '...' : ''); ?>
                                    </a>
                                </h4>
                                <div class="title-meta">
                                    <span>
                                        <span class="material-symbols-outlined">description</span> 
                                        <?php echo $title['paper_count']; ?> papers
                                    </span>
                                    <span>
                                        <span class="material-symbols-outlined">comment</span> 
                                        <?php echo $title['comment_count']; ?> comments
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if($title['category_name']): ?>
                                <span class="category-badge" style="background: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>20; color: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>;">
                                    <?php echo htmlspecialchars($title['category_name']); ?>
                                </span>
                            <?php else: ?>
                                <span class="category-badge">Uncategorized</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="student-info">
                                <span class="student-name"><?php echo htmlspecialchars($title['student_name'] ?? 'Unknown'); ?></span>
                                <span class="student-id"><?php echo htmlspecialchars($title['student_id_number'] ?? ''); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if($title['adviser_name']): ?>
                                <span class="adviser-name"><?php echo htmlspecialchars($title['adviser_name']); ?></span>
                            <?php else: ?>
                                <span style="color: #999;">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $title['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                            </span>
                        </td>
                        <td style="text-align: center; font-weight: 500;">
                            <?php echo $title['paper_count']; ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($title['created_at'])); ?>
                        </td>
                        <td style="text-align: center;">
                            <div class="action-menu">
                                <button type="button" class="meatball-btn" onclick="event.stopPropagation(); toggleDropdown(event, this)">
                                    <span class="material-symbols-outlined">more_horiz</span>
                                </button>
                                <div class="dropdown-menu">
                                    <a href="javascript:void(0)" class="view" onclick="event.stopPropagation(); window.open('../titles/view.php?id=<?php echo $title['id']; ?>', '_blank'); closeDropdowns();">
                                        <span class="material-symbols-outlined">visibility</span>
                                        View
                                    </a>
                                    <a href="edit-title.php?id=<?php echo $title['id']; ?>" class="edit" onclick="event.stopPropagation();">
                                        <span class="material-symbols-outlined">edit</span>
                                        Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0)" class="delete" onclick="event.stopPropagation(); confirmSingleDelete(<?php echo $title['id']; ?>, '<?php echo htmlspecialchars(addslashes($title['title'])); ?>'); closeDropdowns();">
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

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>" class="page-link">
                    <span class="material-symbols-outlined">chevron_left</span>
                </a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for($i = $start; $i <= $end; $i++):
            ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>" class="page-link">
                    <span class="material-symbols-outlined">chevron_right</span>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
                closeConfirmationModal();
            }
        });

        // Batch Delete Functions
        function updateBatchBar() {
            const checkboxes = document.querySelectorAll('.title-select:checked');
            const tableCheckbox = document.getElementById('tableSelectAll');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const selectedCount = checkboxes.length;
            const batchBar = document.getElementById('batchBar');
            const selectedCountSpan = document.getElementById('selectedCount');
            const batchDeleteBtn = document.getElementById('batchDeleteBtn');
            
            if (selectedCountSpan) {
                selectedCountSpan.textContent = selectedCount;
            }
            
            const totalCheckboxes = document.querySelectorAll('.title-select').length;
            if (tableCheckbox) {
                tableCheckbox.checked = selectedCount > 0 && selectedCount === totalCheckboxes;
                tableCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCheckboxes;
            }
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = selectedCount > 0 && selectedCount === totalCheckboxes;
                selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCheckboxes;
            }
            
            if (batchBar) {
                if (selectedCount > 0) {
                    batchBar.style.display = 'flex';
                    if (batchDeleteBtn) {
                        batchDeleteBtn.disabled = false;
                    }
                } else {
                    batchBar.style.display = 'none';
                    if (batchDeleteBtn) {
                        batchDeleteBtn.disabled = true;
                    }
                }
            }
        }

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.title-select');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBatchBar();
        }

        function clearAllSelections() {
            const checkboxes = document.querySelectorAll('.title-select');
            checkboxes.forEach(cb => {
                cb.checked = false;
            });
            updateBatchBar();
        }

        function confirmBatchDelete() {
            const checkboxes = document.querySelectorAll('.title-select:checked');
            if (checkboxes.length === 0) return;
            
            showConfirmationModal({
                title: 'Delete Multiple Titles',
                message: `Are you sure you want to delete <strong>${checkboxes.length}</strong> title(s)?<br><br><span style="color: #dc3545;">This action cannot be undone. All associated papers, comments, and files will be permanently deleted.</span>`,
                confirmText: 'Yes, Delete All',
                onConfirm: function() {
                    document.getElementById('batchDeleteForm').submit();
                },
                type: 'delete'
            });
        }

        function confirmSingleDelete(titleId, titleName) {
            showConfirmationModal({
                title: 'Delete Title',
                message: `Are you sure you want to delete "<strong>${titleName}</strong>"?<br><br><span style="color: #dc3545;">This action cannot be undone. All associated papers, comments, and files will be permanently deleted.</span>`,
                confirmUrl: `?delete=${titleId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`,
                confirmText: 'Yes, Delete',
                type: 'delete'
            });
        }

        // Auto-submit filters when dropdowns change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
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
    </script>
</body>
</html>