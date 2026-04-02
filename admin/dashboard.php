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

// Initialize default values
$total_users = $total_students = $total_advisers = $total_admins = 0;
$total_titles = $total_departments = $total_categories = 0;
$pending_titles = $active_titles = $completed_titles = 0;
$recent_users = $recent_titles = $recent_deadlines = [];

// Get statistics with error handling
try {
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_students = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $total_advisers = $db->query("SELECT COUNT(*) FROM users WHERE role='adviser'")->fetchColumn();
    $total_admins = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    
    $total_titles = $db->query("SELECT COUNT(*) FROM capstone_titles")->fetchColumn();
    $total_departments = $db->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $total_categories = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    
    $pending_titles = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status='pending_review'")->fetchColumn();
    $active_titles = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status='active'")->fetchColumn();
    $completed_titles = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status='completed'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading statistics.";
    $_SESSION['flash_type'] = "error";
}

// Get recent users with error handling
try {
    $recent_users = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent users error: " . $e->getMessage());
    $recent_users = [];
}

// Get recent titles with error handling
try {
    $recent_titles = $db->query("SELECT ct.*, u.full_name as student_name 
                                 FROM capstone_titles ct 
                                 LEFT JOIN users u ON ct.student_id = u.id 
                                 ORDER BY ct.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent titles error: " . $e->getMessage());
    $recent_titles = [];
}

// Get recent deadlines with error handling
try {
    $recent_deadlines = $db->query("SELECT d.*, u.full_name as creator_name 
                                    FROM deadlines d 
                                    LEFT JOIN users u ON d.created_by = u.id 
                                    ORDER BY d.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent deadlines error: " . $e->getMessage());
    $recent_deadlines = [];
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
    <title>Admin Dashboard - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-dashboard.css?v=<?php echo time(); ?>">
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
                <h1>Admin Dashboard</h1>
                <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($full_name); ?>!</p>
            </div>
        </div>

        <!-- FLASH MESSAGES SECTION -->
        <?php 
        $flash_message = $_SESSION['flash_message'] ?? '';
        $flash_type = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        if($flash_message): 
        ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
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
                    <span class="material-symbols-outlined">description</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_titles; ?></h3>
                    <p>Total Titles</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">category</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_departments; ?></h3>
                    <p>Departments</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">bookmark</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_categories; ?></h3>
                    <p>Categories</p>
                </div>
            </div>

        </div>

        <!-- Quick Actions -->
        <div class="title-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">bolt</span>
                    Quick Actions
                </h2>
            </div>
            
            <div class="quick-actions-grid">
                <a href="users.php" class="quick-action-card">
                    <span class="material-symbols-outlined">person_add</span>
                    <div>
                        <h4>Manage Users</h4>
                        <p>Add, edit, or remove users</p>
                    </div>
                </a>
                
                <a href="departments.php" class="quick-action-card">
                    <span class="material-symbols-outlined">category</span>
                    <div>
                        <h4>Manage Departments</h4>
                        <p>Organize by department</p>
                    </div>
                </a>
                
                <a href="category.php" class="quick-action-card">
                    <span class="material-symbols-outlined">bookmark</span>
                    <div>
                        <h4>Manage Categories</h4>
                        <p>Create and edit categories</p>
                    </div>
                </a>
                
                <a href="../titles/browse.php" class="quick-action-card">
                    <span class="material-symbols-outlined">search</span>
                    <div>
                        <h4>Browse All Titles</h4>
                        <p>View all capstone projects</p>
                    </div>
                </a>
                
                <a href="add-title.php" class="quick-action-card">
                    <span class="material-symbols-outlined">add_circle</span>
                    <div>
                        <h4>Add New Title</h4>
                        <p>Create a new capstone title</p>
                    </div>
                </a>
                
                <a href="deadlines.php" class="quick-action-card">
                    <span class="material-symbols-outlined">event</span>
                    <div>
                        <h4>Manage Deadlines</h4>
                        <p>Set important dates</p>
                    </div>
                </a>
                
                <a href="consent-logs.php" class="quick-action-card">
                    <span class="material-symbols-outlined">security</span>
                    <div>
                        <h4>Consent Logs</h4>
                        <p>View privacy agreements</p>
                    </div>
                </a>

                <a href="manage-titles.php" class="quick-action-card">
                    <span class="material-symbols-outlined">delete_sweep</span>
                    <div>
                        <h4>Manage Titles</h4>
                        <p>Batch delete and manage all titles</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Activity Grid -->
        <div class="recent-grid">
            <!-- Recent Users -->
            <div class="title-card">
                <div class="card-header">
                    <h2>
                        <span class="material-symbols-outlined">group</span>
                        Recent Users
                    </h2>
                    <a href="users.php" class="view-all-link">
                        View All
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                </div>
                
                <div class="recent-list">
                    <?php if(empty($recent_users)): ?>
                        <div class="empty-message">No users found</div>
                    <?php else: ?>
                        <?php foreach($recent_users as $user): ?>
                        <div class="recent-item">
                            <div class="recent-item-avatar">
                                <span class="material-symbols-outlined">person</span>
                            </div>
                            <div class="recent-item-info">
                                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p>
                                    <span class="role-badge <?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                    <span class="item-meta">ID: <?php echo htmlspecialchars($user['id_number']); ?></span>
                                </p>
                            </div>
                            <div class="recent-item-date">
                                <?php echo date('M d', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Titles -->
            <div class="title-card">
                <div class="card-header">
                    <h2>
                        <span class="material-symbols-outlined">description</span>
                        Recent Titles
                    </h2>
                    <a href="../titles/browse.php" class="view-all-link">
                        View All
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                </div>
                
                <div class="recent-list">
                    <?php if(empty($recent_titles)): ?>
                        <div class="empty-message">No titles found</div>
                    <?php else: ?>
                        <?php foreach($recent_titles as $title): ?>
                        <div class="recent-item">
                            <div class="recent-item-info">
                                <h4><?php echo htmlspecialchars(substr($title['title'], 0, 40)) . '...'; ?></h4>
                                <p>
                                    <span class="status-badge <?php echo $title['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                                    </span>
                                    <span class="item-meta">by <?php echo htmlspecialchars($title['student_name'] ?? 'Unknown'); ?></span>
                                </p>
                            </div>
                            <div class="recent-item-date">
                                <?php echo date('M d', strtotime($title['created_at'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Deadlines -->
            <div class="title-card">
                <div class="card-header">
                    <h2>
                        <span class="material-symbols-outlined">event</span>
                        Recent Deadlines
                    </h2>
                    <a href="deadlines.php" class="view-all-link">
                        View All
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                </div>
                
                <div class="recent-list">
                    <?php if(empty($recent_deadlines)): ?>
                        <div class="empty-message">No deadlines found</div>
                    <?php else: ?>
                        <?php foreach($recent_deadlines as $deadline): 
                            $deadline_datetime = $deadline['deadline_date'] . ' ' . ($deadline['deadline_time'] ?? '23:59:00');
                            $is_past = strtotime($deadline_datetime) < time();
                        ?>
                        <div class="recent-item">
                            <div class="recent-item-info">
                                <h4><?php echo htmlspecialchars($deadline['title']); ?></h4>
                                <p>
                                    <span class="status-badge <?php echo $is_past ? 'past' : ($deadline['is_active'] ? 'active' : 'inactive'); ?>">
                                        <?php 
                                        if($is_past) echo 'Past';
                                        else echo $deadline['is_active'] ? 'Active' : 'Inactive';
                                        ?>
                                    </span>
                                    <span class="item-meta"><?php echo date('M d, Y', strtotime($deadline['deadline_date'])); ?></span>
                                </p>
                            </div>
                            <div class="recent-item-date">
                                by <?php echo htmlspecialchars($deadline['creator_name'] ?? 'System'); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
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
    </script>
</body>
</html>