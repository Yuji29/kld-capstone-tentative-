<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])){
    header('Location: auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user info
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';

// Initialize default values
$total_titles = 0;
$pending_review = 0;
$total_departments = 0;
$active_titles = 0;
$deadlines = [];
$total_deadlines = 0;
$recent_titles = [];

try {
    // Get global statistics (for all users)
    $total_titles = $db->query("SELECT COUNT(*) FROM capstone_titles")->fetchColumn() ?: 0;
    $pending_review = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status = 'pending_review'")->fetchColumn() ?: 0;
    $total_departments = $db->query("SELECT COUNT(DISTINCT department) FROM users WHERE department IS NOT NULL AND department != ''")->fetchColumn() ?: 0;
    $active_titles = $db->query("SELECT COUNT(*) FROM capstone_titles WHERE status = 'active'")->fetchColumn() ?: 0;

    // Get role-specific statistics and data
    if($role == 'adviser') {
        // Adviser-specific stats
        $my_pending = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE adviser_id = ? AND status = 'pending_review'");
        $my_pending->execute([$user_id]);
        $my_pending_count = $my_pending->fetchColumn() ?: 0;
        
        $my_active = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE adviser_id = ? AND status = 'active'");
        $my_active->execute([$user_id]);
        $my_active_count = $my_active->fetchColumn() ?: 0;
        
        $my_completed = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE adviser_id = ? AND status = 'completed'");
        $my_completed->execute([$user_id]);
        $my_completed_count = $my_completed->fetchColumn() ?: 0;
        
        $my_total = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE adviser_id = ?");
        $my_total->execute([$user_id]);
        $my_total_count = $my_total->fetchColumn() ?: 0;
        
        // Get adviser's recent titles
        $recent_query = "SELECT ct.*, 
                                u.full_name as student_name,
                                c.name as category_name
                         FROM capstone_titles ct
                         JOIN users u ON ct.student_id = u.id
                         LEFT JOIN categories c ON ct.category_id = c.id
                         WHERE ct.adviser_id = ?
                         ORDER BY ct.updated_at DESC 
                         LIMIT 5";
        $recent_stmt = $db->prepare($recent_query);
        $recent_stmt->execute([$user_id]);
        $recent_titles = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif($role == 'student') {
        // Student-specific stats
        $my_pending = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE student_id = ? AND status = 'pending_review'");
        $my_pending->execute([$user_id]);
        $my_pending_count = $my_pending->fetchColumn() ?: 0;
        
        $my_active = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE student_id = ? AND status = 'active'");
        $my_active->execute([$user_id]);
        $my_active_count = $my_active->fetchColumn() ?: 0;
        
        $my_completed = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE student_id = ? AND status = 'completed'");
        $my_completed->execute([$user_id]);
        $my_completed_count = $my_completed->fetchColumn() ?: 0;
        
        $my_total = $db->prepare("SELECT COUNT(*) FROM capstone_titles WHERE student_id = ?");
        $my_total->execute([$user_id]);
        $my_total_count = $my_total->fetchColumn() ?: 0;
        
        // Get student's recent titles
        $recent_query = "SELECT ct.*, 
                                a.full_name as adviser_name,
                                c.name as category_name
                         FROM capstone_titles ct
                         LEFT JOIN users a ON ct.adviser_id = a.id
                         LEFT JOIN categories c ON ct.category_id = c.id
                         WHERE ct.student_id = ?
                         ORDER BY ct.updated_at DESC 
                         LIMIT 5";
        $recent_stmt = $db->prepare($recent_query);
        $recent_stmt->execute([$user_id]);
        $recent_titles = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Admin - get all recent titles
        $recent_query = "SELECT ct.*, 
                                u.full_name as student_name,
                                c.name as category_name,
                                a.full_name as adviser_name
                         FROM capstone_titles ct
                         LEFT JOIN users u ON ct.student_id = u.id
                         LEFT JOIN categories c ON ct.category_id = c.id
                         LEFT JOIN users a ON ct.adviser_id = a.id
                         ORDER BY ct.updated_at DESC 
                         LIMIT 5";
        $recent_stmt = $db->query($recent_query);
        $recent_titles = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get upcoming deadlines with visibility rules applied
    if($role === 'student') {
        $deadline_query = "SELECT * FROM deadlines 
                           WHERE is_active = 1 
                           AND deadline_date >= CURDATE()
                           AND (
                               visibility_type = 'all' 
                               OR (
                                   visibility_type = 'specific_students' 
                                   AND FIND_IN_SET(?, visible_to_students)
                               )
                           )
                           ORDER BY deadline_date ASC 
                           LIMIT 3";
        $stmt = $db->prepare($deadline_query);
        $stmt->execute([$user_id]);
        $deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_deadlines_query = "SELECT COUNT(*) FROM deadlines 
                                  WHERE is_active = 1 
                                  AND deadline_date >= CURDATE()
                                  AND (
                                      visibility_type = 'all' 
                                      OR (
                                          visibility_type = 'specific_students' 
                                          AND FIND_IN_SET(?, visible_to_students)
                                      )
                                  )";
        $stmt_total = $db->prepare($total_deadlines_query);
        $stmt_total->execute([$user_id]);
        $total_deadlines = $stmt_total->fetchColumn() ?: 0;
    } 
    elseif($role === 'adviser' || $role === 'admin') {
        $deadline_query = "SELECT * FROM deadlines 
                           WHERE is_active = 1 
                           AND deadline_date >= CURDATE()
                           ORDER BY deadline_date ASC 
                           LIMIT 3";
        $deadlines = $db->query($deadline_query)->fetchAll(PDO::FETCH_ASSOC);
        
        $total_deadlines = $db->query("SELECT COUNT(*) FROM deadlines WHERE is_active = 1 AND deadline_date >= CURDATE()")->fetchColumn() ?: 0;
    }
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $my_pending_count = $my_active_count = $my_completed_count = $my_total_count = 0;
}

// Determine the appropriate link for deadlines
if($role === 'admin') {
    $deadlines_view_link = 'admin/deadlines.php';
} elseif($role === 'adviser') {
    $deadlines_view_link = 'adviser/deadlines.php';
} else {
    $deadlines_view_link = 'titles/deadlines.php';
}

$pending_total = $my_pending_count ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - KLD Capstone Title Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/navigation.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/user-dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <!-- Include Navigation -->
    <?php include 'includes/dashboard-nav.php'; ?>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-item">
            <span class="material-symbols-outlined">person</span>
            <span><?php echo htmlspecialchars($full_name); ?> (<?php echo ucfirst($role); ?>)</span>
        </div>
        <a href="auth/logout.php" class="mobile-menu-item logout">
            <span class="material-symbols-outlined">logout</span>
            Logout
        </a>
    </div>

    <main class="dashboard-main">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($full_name); ?>!</h1>
            <p>
                <?php 
                if($role == 'admin') {
                    echo 'Manage, track, and explore academic research with ease.';
                } elseif($role == 'adviser') {
                    echo 'Review and manage your advisees\' capstone projects.';
                } else {
                    echo 'Track your capstone journey from proposal to completion.';
                }
                ?>
            </p>
            
            <div class="action-buttons">
                <?php if($role == 'student'): ?>
                    <a href="titles/add.php?return=dashboard" class="btn-primary">
                        <span class="material-symbols-outlined">add_circle</span>
                        New Capstone Title
                    </a>
                <?php endif; ?>
    
                <?php if($role == 'adviser'): ?>
                    <div class="teacher-buttons">
                        <a href="adviser/pending.php" class="btn-primary">
                            <span class="material-symbols-outlined">rate_review</span>
                            Review Titles <?php echo $pending_total > 0 ? '(' . $pending_total . ')' : ''; ?>
                        </a>
        
                        <a href="adviser/deadlines.php" class="btn-deadline">
                            <span class="material-symbols-outlined">event</span>
                            Manage Deadlines
                        </a>
                    </div>
                <?php endif; ?>
    
                <?php if($role == 'admin'): ?>
                    <a href="admin/dashboard.php" class="btn-primary">
                        <span class="material-symbols-outlined">admin_panel_settings</span>
                        Admin Panel
                    </a>
                    <a href="admin/deadlines.php" class="btn-deadline">
                        <span class="material-symbols-outlined">event</span>
                        Manage Deadlines
                    </a>
                <?php endif; ?>
    
                <a href="titles/browse.php" class="btn-outline">
                    <span class="material-symbols-outlined">search</span>
                    Browse All Titles
                </a>
            </div>
        </div>

        <!-- Global Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_titles); ?></h3>
                    <p>Total Titles</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">pending_actions</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($pending_review); ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">school</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_departments); ?></h3>
                    <p>Departments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($active_titles); ?></h3>
                    <p>Active Titles</p>
                </div>
            </div>
        </div>

        <!-- Role-Specific Statistics -->
        <?php if($role == 'adviser' || $role == 'student'): ?>
        <div class="my-stats-section">
            <h3>
                <span class="material-symbols-outlined">person</span>
                <?php echo ($role == 'adviser') ? 'My Advisee Statistics' : 'My Title Statistics'; ?>
            </h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="material-symbols-outlined">pending_actions</span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $my_pending_count ?: '0'; ?></h3>
                        <p><?php echo ($role == 'adviser') ? 'Pending Review' : 'My Pending'; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="material-symbols-outlined">play_circle</span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $my_active_count ?: '0'; ?></h3>
                        <p><?php echo ($role == 'adviser') ? 'Active Advisees' : 'My Active'; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="material-symbols-outlined">check_circle</span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $my_completed_count ?: '0'; ?></h3>
                        <p><?php echo ($role == 'adviser') ? 'Completed' : 'My Completed'; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $my_total_count ?: '0'; ?></h3>
                        <p><?php echo ($role == 'adviser') ? 'Total Advised' : 'My Total'; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming Deadlines -->
        <?php if(!empty($deadlines)): ?>
        <div class="section-header">
            <h2>
                <span class="material-symbols-outlined">event</span>
                Upcoming Deadlines
                <?php if($total_deadlines > 3): ?>
                    <span class="badge">+<?php echo $total_deadlines - 3; ?> more</span>
                <?php endif; ?>
            </h2>
            <a href="<?php echo $deadlines_view_link; ?>" class="view-link">
                View all
                <span class="material-symbols-outlined">arrow_forward</span>
            </a>
        </div>
        
        <div class="deadlines-grid">
            <?php foreach($deadlines as $dl): 
                $days_left = max(0, ceil((strtotime($dl['deadline_date']) - time()) / 86400));
                $is_urgent = $days_left <= 7;
                
                $cat_color = '#2D5A27';
                $category_lower = strtolower($dl['category'] ?? '');
                if($category_lower == 'submission') $cat_color = '#2D5A27';
                elseif($category_lower == 'presentation') $cat_color = '#f5a623';
                elseif($category_lower == 'approval') $cat_color = '#3498db';
                elseif($category_lower == 'paper') $cat_color = '#9b59b6';
                elseif($category_lower == 'other') $cat_color = '#95a9c2';
            ?>
            <div class="deadline-card <?php echo $is_urgent ? 'urgent' : ''; ?>">
                <?php if(!empty($dl['category'])): ?>
                    <span class="deadline-category" style="background: <?php echo $cat_color; ?>">
                        <?php echo ucfirst($dl['category']); ?>
                    </span>
                <?php endif; ?>
                
                <h4><?php echo htmlspecialchars($dl['title']); ?></h4>
                
                <div class="deadline-meta">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <?php echo date('F j, Y', strtotime($dl['deadline_date'])); ?>
                    <?php if(!empty($dl['deadline_time']) && $dl['deadline_time'] !== '00:00:00'): ?>
                        <span style="display: flex; align-items: center; gap: 2px;">
                            <span class="material-symbols-outlined">schedule</span>
                            <?php echo date('h:i A', strtotime($dl['deadline_time'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if(!empty($dl['description'])): ?>
                    <p class="deadline-description">
                        <?php echo htmlspecialchars(substr($dl['description'], 0, 80)) . (strlen($dl['description']) > 80 ? '...' : ''); ?>
                    </p>
                <?php endif; ?>
                
                <div class="deadline-footer">
                    <span class="days-left <?php echo $is_urgent ? 'urgent' : 'normal'; ?>">
                        <span class="material-symbols-outlined"><?php echo $is_urgent ? 'warning' : 'event'; ?></span>
                        <?php echo $days_left; ?> day(s) left
                    </span>
                    
                    <?php if($role == 'admin'): ?>
                        <a href="admin/deadlines.php?edit=<?php echo $dl['id']; ?>" style="color: #7f947c;">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Titles Section -->
        <div class="section-header">
            <h2>
                <span class="material-symbols-outlined">list_alt</span>
                <?php 
                if($role == 'adviser') {
                    echo 'Your Advisees\' Recent Titles';
                } elseif($role == 'student') {
                    echo 'Your Recent Titles';
                } else {
                    echo 'Recent Capstone Titles';
                }
                ?>
            </h2>
            <a href="titles/browse.php" class="view-link">
                View all
                <span class="material-symbols-outlined">arrow_forward</span>
            </a>
        </div>

        <div class="table-responsive">
            <table class="tracker-table">
                <thead>
                    <tr>
                        <th>Capstone Title</th>
                        <?php if($role == 'adviser'): ?>
                            <th>Student</th>
                        <?php elseif($role == 'student'): ?>
                            <th>Adviser</th>
                        <?php else: ?>
                            <th>Student</th>
                            <th>Adviser</th>
                        <?php endif; ?>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Last Update</th>
                        <th></th>
                    </thead>
                <tbody>
                    <?php if(empty($recent_titles)): ?>
                        <tr>
                            <td colspan="<?php echo ($role == 'admin') ? '7' : '6'; ?>" style="text-align: center; padding: 40px;">
                                <span class="material-symbols-outlined" style="font-size: 48px; color: #ccc;">inbox</span>
                                <p style="color: #666; margin-top: 10px;">
                                    <?php 
                                    if($role == 'adviser') {
                                        echo 'No titles assigned to you yet.';
                                    } elseif($role == 'student') {
                                        echo 'You haven\'t created any titles yet.';
                                    } else {
                                        echo 'No capstone titles yet.';
                                    }
                                    ?>
                                </p>
                                <?php if($role == 'student'): ?>
                                    <a href="titles/add.php" class="btn-primary" style="margin-top: 15px;">Add Your First Title</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($recent_titles as $title): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($title['title']); ?></strong></td>
                            
                            <?php if($role == 'adviser'): ?>
                                <td><?php echo htmlspecialchars($title['student_name']); ?></td>
                            <?php elseif($role == 'student'): ?>
                                <td><?php echo htmlspecialchars($title['adviser_name'] ?? 'Not Assigned'); ?></td>
                            <?php else: ?>
                                <td><?php echo htmlspecialchars($title['student_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($title['adviser_name'] ?? 'Not Assigned'); ?></td>
                            <?php endif; ?>
                            
                            <td><?php echo htmlspecialchars($title['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $title['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($title['updated_at'])); ?></td>
                            <td>
                                <a href="titles/view.php?id=<?php echo $title['id']; ?>" style="color: #7f947c;">
                                    <span class="material-symbols-outlined">visibility</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if(!empty($recent_titles)): ?>
        <p class="table-footer">
            Showing <?php echo count($recent_titles); ?> recent titles
        </p>
        <?php endif; ?>
    </main>

    <!-- Include Footer -->
    <?php include 'includes/dashboard-footer.php'; ?>

    <script>
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (hamburgerBtn && mobileMenu) {
            hamburgerBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('active');
            });
        }

        document.addEventListener('click', function(event) {
            if (mobileMenu && hamburgerBtn && !mobileMenu.contains(event.target) && !hamburgerBtn.contains(event.target)) {
                mobileMenu.classList.remove('active');
            }
        });

        // Force page to reload when using back button
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>