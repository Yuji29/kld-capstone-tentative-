<?php


session_start();
date_default_timezone_set('Asia/Manila');
require_once 'config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query based on role
$query = "SELECT ct.*, 
                 c.name as category_name,
                 c.color as category_color,
                 u.full_name as student_name,
                 u.id_number as student_id_number,
                 a.full_name as adviser_name,
                 (SELECT COUNT(*) FROM papers WHERE title_id = ct.id) as paper_count
          FROM capstone_titles ct
          LEFT JOIN categories c ON ct.category_id = c.id
          LEFT JOIN users u ON ct.student_id = u.id
          LEFT JOIN users a ON ct.adviser_id = a.id
          WHERE 1=1";

$params = [];

// Role-based filtering
if ($role === 'student') {
    $query .= " AND ct.student_id = :user_id";
    $params[':user_id'] = $user_id;
} elseif ($role === 'adviser') {
    $query .= " AND ct.adviser_id = :user_id";
    $params[':user_id'] = $user_id;
}
// Admin sees all titles (no additional filter)

if (!empty($search)) {
    $query .= " AND (ct.title LIKE :search OR u.full_name LIKE :search OR u.id_number LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $query .= " AND ct.status = :status";
    $params[':status'] = $status;
}

// Sorting
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

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$titles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for stats
$total_titles = count($titles);
$pending_count = 0;
$active_count = 0;
$completed_count = 0;
$revisions_count = 0;

foreach ($titles as $title) {
    switch ($title['status']) {
        case 'pending_review': $pending_count++; break;
        case 'active': $active_count++; break;
        case 'completed': $completed_count++; break;
        case 'revisions': $revisions_count++; break;
    }
}

$full_name = $_SESSION['full_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Titles - KLD Capstone</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="css/my-titles.css?v=<?php

 echo time(); ?>">
</head>
<body>
    <?php

 include 'includes/dashboard-nav.php'; ?>
    
    <div class="my-titles-container">
        <!-- Header Section (same as add.php and profile.php) -->
        <div class="header">
            <div>
                <h1>My Titles</h1>
                <p class="header-subtitle">View all capstone titles associated with your account</p>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php

 echo $total_titles; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php

 echo $pending_count; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php

 echo $active_count; ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php

 echo $completed_count; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="search-box">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input type="text" name="search" placeholder="Search by title or student..." value="<?php

 echo htmlspecialchars($search); ?>">
                </div>
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending_review" <?php

 echo $status === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                    <option value="active" <?php

 echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php

 echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="revisions" <?php

 echo $status === 'revisions' ? 'selected' : ''; ?>>Revisions</option>
                </select>
                <select name="sort" class="filter-select">
                    <option value="newest" <?php

 echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php

 echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="title_asc" <?php

 echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
                    <option value="title_desc" <?php

 echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
                    <option value="status" <?php

 echo $sort === 'status' ? 'selected' : ''; ?>>By Status</option>
                </select>
                <button type="submit" class="btn-filter">Apply Filters</button>
                <?php

 if (!empty($search) || !empty($status) || $sort !== 'newest'): ?>
                    <a href="my-titles.php" class="clear-filters">Clear</a>
                <?php

 endif; ?>
            </form>
        </div>
        
        <!-- Titles Table -->
        <div class="card">
            <div class="table-header">
                <h2>Your Capstone Titles</h2>
                <span class="item-count"><?php

 echo count($titles); ?> titles</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Student</th>
                            <th>Adviser</th>
                            <th>Status</th>
                            <th>Papers</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

 if (empty($titles)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <span class="material-symbols-outlined">inbox</span>
                                    <p>No titles found.</p>
                                    <?php

 if ($role === 'student'): ?>
                                        <p><a href="titles/add.php" style="color: #2D5A27;">Add your first title</a></p>
                                    <?php

 endif; ?>
                                </td>
                            </tr>
                        <?php

 else: ?>
                            <?php

 foreach ($titles as $title): ?>
                            <tr>
                                <td>
                                    <a href="titles/view.php?id=<?php

 echo $title['id']; ?>" class="title-link" target="_blank">
                                        <?php

 echo htmlspecialchars(substr($title['title'], 0, 60)) . (strlen($title['title']) > 60 ? '...' : ''); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php

 if ($title['category_name']): ?>
                                        <span class="category-badge"><?php

 echo htmlspecialchars($title['category_name']); ?></span>
                                    <?php

 else: ?>
                                        <span class="category-badge">Uncategorized</span>
                                    <?php

 endif; ?>
                                </td>
                                <td><?php

 echo htmlspecialchars($title['student_name'] ?? 'Unknown'); ?><br>
                                    <small><?php

 echo htmlspecialchars($title['student_id_number'] ?? ''); ?></small>
                                </td>
                                <td><?php

 echo htmlspecialchars($title['adviser_name'] ?? 'Not assigned'); ?></td>
                                <td>
                                    <span class="status-badge <?php

 echo $title['status']; ?>">
                                        <?php

 echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                                    </span>
                                </td>
                                <td><?php

 echo $title['paper_count']; ?></td>
                                <td><?php

 echo date('M d, Y', strtotime($title['created_at'])); ?></td>
                            </tr>
                            <?php

 endforeach; ?>
                        <?php

 endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <a href="profile.php" class="back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Profile
        </a>
    </div>

    <!-- Include Footer -->
    <?php include 'includes/dashboard-footer.php'; ?>
</body>
</html>