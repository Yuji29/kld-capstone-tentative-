<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: ../auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get all categories for filter
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $db->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

// Get user's own titles (for students) - THIS IS FOR THE QUICK STATS SECTION
$my_titles = [];
if($role === 'student') {
    $my_query = "SELECT id, title, status FROM capstone_titles WHERE student_id = ? ORDER BY created_at DESC LIMIT 5";
    $my_stmt = $db->prepare($my_query);
    $my_stmt->execute([$user_id]);
    $my_titles = $my_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get pending requests (for advisers)
$pending_requests = [];
if($role === 'adviser') {
    $pending_query = "SELECT ct.id as capstone_id, ct.title, u.full_name as student_name 
                      FROM capstone_titles ct
                      JOIN users u ON ct.student_id = u.id
                      WHERE ct.adviser_id = ? 
                      AND ct.status = 'pending_review'
                      ORDER BY ct.created_at DESC
                      LIMIT 3";
    $pending_stmt = $db->prepare($pending_query);
    $pending_stmt->execute([$user_id]);
    $pending_requests = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Build the main query for ALL titles - SHOW EVERYTHING
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

// Add search filter
if(!empty($search)) {
    $query .= " AND (ct.title LIKE ? OR ct.abstract LIKE ? OR u.full_name LIKE ? OR u.id_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add category filter
if($category_id > 0) {
    $query .= " AND ct.category_id = ?";
    $params[] = $category_id;
}

// UPDATED: Show ALL titles regardless of role, but filter by status if selected
if(!empty($status)) {
    $query .= " AND ct.status = ?";
    $params[] = $status;
}

// Add sorting
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
    case 'popular':
        $query .= " ORDER BY paper_count DESC, comment_count DESC";
        break;
    default: // newest
        $query .= " ORDER BY ct.created_at DESC";
        break;
}

// Add pagination
$query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

// Execute main query
$stmt = $db->prepare($query);
$stmt->execute($params);
$titles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$total_results = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
$total_pages = ceil($total_results / $per_page);

// Set variables for navigation include
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Capstone Titles - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/titles/browse.css?v=<?php echo time(); ?>">
    <style>
        /* Additional styles for better visibility of all statuses */
        .status-badge.pending_review,
        .status-badge.revisions {
            cursor: help;
        }
        
        .status-badge.pending_review:hover::after,
        .status-badge.revisions:hover::after {
            content: attr(title);
            position: absolute;
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 10;
            transform: translateY(-100%);
            margin-top: -5px;
        }
        
        .filter-note {
            background: #e9f2e7;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-note .material-symbols-outlined {
            font-size: 18px;
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
        <!-- Header Section -->
        <div class="header">
            <div>
                <h1>Browse Capstone Titles</h1>
                <p class="header-subtitle">Explore all research projects and thesis works</p>
            </div>
            <div class="header-actions">
                <?php if($role === 'student'): ?>
                    <a href="add.php" class="btn-primary">
                        <span class="material-symbols-outlined">add</span>
                        Submit New Title
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats / Info Cards -->
        <?php if($role === 'student' && !empty($my_titles)): ?>
        <div class="quick-stats">
            <div class="stat-card">
                <span class="material-symbols-outlined">description</span>
                <div>
                    <h3><?php echo count($my_titles); ?></h3>
                    <p>Your Titles</p>
                </div>
            </div>
            <a href="?status=active" class="stat-card link-card">
                <span class="material-symbols-outlined">play_circle</span>
                <div>
                    <h3>Active</h3>
                    <p>View active titles</p>
                </div>
            </a>
            <a href="?status=completed" class="stat-card link-card">
                <span class="material-symbols-outlined">check_circle</span>
                <div>
                    <h3>Completed</h3>
                    <p>View completed works</p>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Pending Requests for Advisers -->
        <?php if($role === 'adviser' && !empty($pending_requests)): ?>
        <div class="pending-section">
            <h2>
                <span class="material-symbols-outlined">pending_actions</span>
                Pending Adviser Requests
            </h2>
            <div class="pending-grid">
                <?php foreach($pending_requests as $request): ?>
                <div class="pending-card">
                    <div class="pending-card-header">
                        <span class="badge warning">Pending</span>
                    </div>
                    <h4><?php echo htmlspecialchars($request['title']); ?></h4>
                    <p class="student-name">
                        <span class="material-symbols-outlined">person</span>
                        <?php echo htmlspecialchars($request['student_name']); ?>
                    </p>
                    <div class="pending-actions">
                        <a href="view.php?id=<?php echo $request['capstone_id']; ?>" class="btn-view">
                            <span class="material-symbols-outlined">visibility</span>
                            Review
                        </a>
                        <a href="../adviser/pending.php" class="btn-manage">
                            <span class="material-symbols-outlined">manage_accounts</span>
                            Manage All
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters and Search Section -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
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
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- UPDATED: Status filter now shows all statuses for everyone -->
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="pending_review" <?php echo $status === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="revisions" <?php echo $status === 'revisions' ? 'selected' : ''; ?>>Revisions</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>

                    <select name="sort" class="filter-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
                        <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
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

        <!-- Filter Note - Shows current filter context -->
        <div class="filter-note">
            <span class="material-symbols-outlined">info</span>
            <span>
                Showing all titles 
                <?php if($status): ?>with status <strong><?php echo ucfirst(str_replace('_', ' ', $status)); ?></strong><?php endif; ?>
                <?php if($category_id > 0): ?>in selected category<?php endif; ?>.
                <em>All titles are visible to everyone.</em>
            </span>
        </div>

        <!-- Results Summary -->
        <div class="results-summary">
            <p>
                Showing <strong><?php echo count($titles); ?></strong> of 
                <strong><?php echo $total_results; ?></strong> titles
            </p>
        </div>

        <!-- Titles Grid -->
        <?php if(empty($titles)): ?>
            <div class="empty-state">
                <span class="material-symbols-outlined">search_off</span>
                <h3>No titles found</h3>
                <p>Try adjusting your search or filter criteria</p>
                <?php if($role === 'student'): ?>
                    <a href="add.php" class="btn-primary">
                        <span class="material-symbols-outlined">add</span>
                        Submit Your First Title
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="titles-grid">
                <?php foreach($titles as $title): 
                    $is_owner = ($role === 'student' && $title['student_id'] == $user_id);
                    $is_adviser = ($role === 'adviser' && $title['adviser_id'] == $user_id);
                    
                    // Add tooltips for pending/revision status
                    $status_tooltip = '';
                    if($title['status'] === 'pending_review') {
                        $status_tooltip = 'title="This title is waiting for adviser review"';
                    } elseif($title['status'] === 'revisions') {
                        $status_tooltip = 'title="This title needs revisions"';
                    }
                ?>
                <div class="title-card <?php echo $is_owner ? 'owner-card' : ''; ?> <?php echo $is_adviser ? 'adviser-card' : ''; ?>">
                    <div class="card-header">
                        <div class="category-badge" style="background: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>20; color: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>;">
                            <?php echo htmlspecialchars($title['category_name'] ?? 'Uncategorized'); ?>
                        </div>
                        <div class="status-badge <?php echo $title['status']; ?>" <?php echo $status_tooltip; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                        </div>
                    </div>

                    <h3 class="title">
                        <a href="view.php?id=<?php echo $title['id']; ?>">
                            <?php echo htmlspecialchars($title['title']); ?>
                        </a>
                    </h3>

                    <div class="author-info">
                        <span class="material-symbols-outlined">person</span>
                        <span>
                            <?php echo htmlspecialchars($title['student_name'] ?? 'Unknown'); ?>
                            <?php if($title['student_id_number']): ?>
                                <small>(<?php echo htmlspecialchars($title['student_id_number']); ?>)</small>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if($title['adviser_name']): ?>
                    <div class="adviser-info">
                        <span class="material-symbols-outlined">school</span>
                        <span>Adviser: <?php echo htmlspecialchars($title['adviser_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="abstract-preview">
                        <?php echo htmlspecialchars(substr($title['abstract'] ?? '', 0, 150)) . '...'; ?>
                    </div>

                    <div class="card-footer">
                        <div class="meta-stats">
                            <span class="stat" title="Papers">
                                <span class="material-symbols-outlined">description</span>
                                <?php echo $title['paper_count']; ?>
                            </span>
                            <span class="stat" title="Comments">
                                <span class="material-symbols-outlined">comment</span>
                                <?php echo $title['comment_count']; ?>
                            </span>
                            <span class="stat" title="Date">
                                <span class="material-symbols-outlined">calendar_today</span>
                                <?php echo date('M d, Y', strtotime($title['created_at'])); ?>
                            </span>
                        </div>
                        
                        <div class="card-actions">
                            <a href="view.php?id=<?php echo $title['id']; ?>" class="btn-view">
                                <span class="material-symbols-outlined">visibility</span>
                                View
                            </a>
                            
                            <?php if($role === 'admin'): ?>
                                <a href="../admin/edit-title.php?id=<?php echo $title['id']; ?>" class="btn-edit">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                            <?php endif; ?>
                            
                            <?php if($is_adviser && $title['status'] === 'pending_review'): ?>
                                <a href="../adviser/review.php?id=<?php echo $title['id']; ?>" class="btn-review">
                                    <span class="material-symbols-outlined">rate_review</span>
                                    Review
                                </a>
                            <?php endif; ?>
                            
                            <?php if($is_owner && $title['status'] === 'revisions'): ?>
                                <a href="edit.php?id=<?php echo $title['id']; ?>" class="btn-revise">
                                    <span class="material-symbols-outlined">edit_note</span>
                                    Revise
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if($is_owner): ?>
                        <div class="owner-badge">Your Title</div>
                    <?php endif; ?>
                    <?php if($is_adviser): ?>
                        <div class="adviser-badge">You are Adviser</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" class="page-link">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" class="page-link">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Include Footer -->
    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
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

        // Auto-submit filters when dropdowns change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
    </script>
</body>
</html>