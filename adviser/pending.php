<?php
session_start();
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../config/database.php';

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

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get flash messages from session
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Get filter parameter with validation
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$valid_filters = ['all', 'pending', 'active', 'revisions', 'completed'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

// Get all titles for this adviser with different statuses
try {
    $query = "SELECT ct.*, 
                     u.full_name as student_name, 
                     u.id_number, 
                     c.name as category_name,
                     c.color as category_color,
                     (SELECT COUNT(*) FROM papers WHERE title_id = ct.id) as paper_count
              FROM capstone_titles ct
              JOIN users u ON ct.student_id = u.id
              LEFT JOIN categories c ON ct.category_id = c.id
              WHERE ct.adviser_id = ?";

    // Apply filter
    switch($filter) {
        case 'pending':
            $query .= " AND ct.status = 'pending_review'";
            break;
        case 'active':
            $query .= " AND ct.status = 'active'";
            break;
        case 'revisions':
            $query .= " AND ct.status = 'revisions'";
            break;
        case 'completed':
            $query .= " AND ct.status = 'completed'";
            break;
        default:
            // 'all' - no filter
            break;
    }

    $query .= " ORDER BY 
                CASE ct.status
                    WHEN 'pending_review' THEN 1
                    WHEN 'revisions' THEN 2
                    WHEN 'active' THEN 3
                    WHEN 'completed' THEN 4
                    ELSE 5
                END,
                ct.updated_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $titles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get status counts (separate query for accuracy)
    $count_query = "SELECT 
                        SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                        SUM(CASE WHEN status = 'revisions' THEN 1 ELSE 0 END) as revisions_count,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                        COUNT(*) as total_count
                    FROM capstone_titles 
                    WHERE adviser_id = ?";
    
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute([$user_id]);
    $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);

    $status_counts = [
        'pending_review' => $counts['pending_count'] ?? 0,
        'active' => $counts['active_count'] ?? 0,
        'revisions' => $counts['revisions_count'] ?? 0,
        'completed' => $counts['completed_count'] ?? 0
    ];
    
    $total_titles = $counts['total_count'] ?? 0;

} catch (PDOException $e) {
    error_log("Failed to fetch titles: " . $e->getMessage());
    $titles = [];
    $status_counts = [
        'pending_review' => 0,
        'active' => 0,
        'revisions' => 0,
        'completed' => 0
    ];
    $total_titles = 0;
    $_SESSION['flash_message'] = "Error loading titles.";
    $_SESSION['flash_type'] = "error";
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
    <title>My Titles - Adviser Dashboard</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/adviser/adviser-pending.css?v=<?php echo time(); ?>">
    <style>
        /* Button color fixes - matching the theme */
        .btn-complete {
            background: var(--primary-color, #2D5A27);
            border-color: var(--primary-color, #2D5A27);
            color: white;
            flex: 1;
        }
        
        .btn-complete:hover {
            background: var(--primary-dark, #1e3d1a);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(45, 90, 39, 0.3);
        }
        
        .btn-success {
            background: var(--primary-color, #2D5A27);
            border-color: var(--primary-color, #2D5A27);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--primary-dark, #1e3d1a);
        }
        
        .btn-warning {
            background: #fd7e14;
            border-color: #fd7e14;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e06b00;
        }
        
        /* Stats card cursor fix */
        .stat-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(45, 90, 39, 0.15);
        }
        
        /* Responsive stats grid */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Remove duplicate arrow buttons */
        .btn-icon {
            display: none;
        }
        
        /* Make main buttons full width on mobile */
        @media (max-width: 768px) {
            .card-actions {
                flex-direction: column;
            }
            
            .btn-primary, .btn-warning, .btn-success, .btn-complete, .btn-outline {
                width: 100%;
                justify-content: center;
            }
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
        <div class="header">
            <div>
                <h1>My Titles</h1>
                <p class="header-subtitle">Manage and review your assigned capstone titles</p>
            </div>
        </div>

        <!-- Messages from session flash -->
        <?php if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Legacy URL parameter support -->
        <?php if(isset($_GET['reviewed']) && $_GET['reviewed'] == 1 && empty($flash_message)): ?>
            <div class="message">
                <span class="material-symbols-outlined">check_circle</span>
                Review submitted successfully!
            </div>
        <?php endif; ?>

        <!-- Statistics Cards - Fixed click handlers -->
        <div class="stats-grid">
            <div class="stat-card <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="window.location.href='?filter=all'">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_titles; ?></h3>
                    <p>All Titles</p>
                </div>
            </div>

            <div class="stat-card <?php echo $filter === 'pending' ? 'active' : ''; ?>" onclick="window.location.href='?filter=pending'">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">pending_actions</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $status_counts['pending_review']; ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>

            <div class="stat-card <?php echo $filter === 'revisions' ? 'active' : ''; ?>" onclick="window.location.href='?filter=revisions'">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">edit_note</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $status_counts['revisions']; ?></h3>
                    <p>Needs Revisions</p>
                </div>
            </div>

            <div class="stat-card <?php echo $filter === 'active' ? 'active' : ''; ?>" onclick="window.location.href='?filter=active'">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">play_circle</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $status_counts['active']; ?></h3>
                    <p>Active</p>
                </div>
            </div>

            <div class="stat-card <?php echo $filter === 'completed' ? 'active' : ''; ?>" onclick="window.location.href='?filter=completed'">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $status_counts['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All Titles
                <span class="tab-count"><?php echo $total_titles; ?></span>
            </a>
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <span class="status-indicator pending"></span>
                Pending Review
                <span class="tab-count"><?php echo $status_counts['pending_review']; ?></span>
            </a>
            <a href="?filter=revisions" class="filter-tab <?php echo $filter === 'revisions' ? 'active' : ''; ?>">
                <span class="status-indicator revisions"></span>
                Needs Revisions
                <span class="tab-count"><?php echo $status_counts['revisions']; ?></span>
            </a>
            <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                <span class="status-indicator active"></span>
                Active
                <span class="tab-count"><?php echo $status_counts['active']; ?></span>
            </a>
            <a href="?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                <span class="status-indicator completed"></span>
                Completed
                <span class="tab-count"><?php echo $status_counts['completed']; ?></span>
            </a>
        </div>

        <?php if(empty($titles)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <span class="material-symbols-outlined">inbox</span>
                <h3>No Titles Found</h3>
                <p>
                    <?php if($filter === 'all'): ?>
                        You don't have any assigned titles yet.
                    <?php elseif($filter === 'pending'): ?>
                        No titles pending your review.
                    <?php elseif($filter === 'revisions'): ?>
                        No titles needing revisions.
                    <?php elseif($filter === 'active'): ?>
                        No active titles.
                    <?php elseif($filter === 'completed'): ?>
                        No completed titles.
                    <?php endif; ?>
                </p>
                <a href="../dashboard.php" class="btn-primary">
                    <span class="material-symbols-outlined">dashboard</span>
                    Return to Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Titles Grid -->
            <div class="titles-grid">
                <?php foreach($titles as $title): ?>
                    <div class="title-card <?php echo $title['status']; ?>">
                        <div class="card-header">
                            <div class="title-badges">
                                <span class="category-badge" style="background: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>20; color: <?php echo htmlspecialchars($title['category_color'] ?? '#2D5A27'); ?>;">
                                    <?php echo htmlspecialchars($title['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                                <span class="status-badge <?php echo $title['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $title['status'])); ?>
                                </span>
                            </div>
                            <?php if($title['paper_count'] > 0): ?>
                                <span class="paper-count" title="Papers uploaded">
                                    <span class="material-symbols-outlined">description</span>
                                    <?php echo $title['paper_count']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <h3 class="title">
                            <a href="../titles/view.php?id=<?php echo $title['id']; ?>">
                                <?php echo htmlspecialchars($title['title']); ?>
                            </a>
                        </h3>
                        
                        <div class="student-info">
                            <span class="material-symbols-outlined">person</span>
                            <div>
                                <strong><?php echo htmlspecialchars($title['student_name']); ?></strong>
                                <small>ID: <?php echo htmlspecialchars($title['id_number']); ?></small>
                            </div>
                        </div>
                        
                        <div class="meta-info">
                            <div class="meta-item">
                                <span class="material-symbols-outlined">calendar_today</span>
                                <span>Submitted: <?php echo date('M d, Y', strtotime($title['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="material-symbols-outlined">update</span>
                                <span>Updated: <?php echo date('M d, Y', strtotime($title['updated_at'])); ?></span>
                            </div>
                        </div>
                        
                        <?php if(!empty($title['abstract'])): ?>
                            <div class="abstract-preview">
                                <?php echo htmlspecialchars(substr($title['abstract'], 0, 100)) . '...'; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-actions">
                            <?php if($title['status'] === 'pending_review'): ?>
                                <a href="review.php?id=<?php echo $title['id']; ?>" class="btn-primary">
                                    <span class="material-symbols-outlined">rate_review</span>
                                    Review Now
                                </a>
                            <?php elseif($title['status'] === 'revisions'): ?>
                                <a href="../titles/view.php?id=<?php echo $title['id']; ?>" class="btn-warning">
                                    <span class="material-symbols-outlined">edit_note</span>
                                    Check Revisions
                                </a>
                            <?php elseif($title['status'] === 'active'): ?>
                                <a href="../titles/view.php?id=<?php echo $title['id']; ?>" class="btn-success">
                                    <span class="material-symbols-outlined">visibility</span>
                                    View Progress
                                </a>
                            <a href="javascript:void(0);" 
                               class="btn-complete" 
                               onclick="showConfirmationModal({
                                   title: 'Mark as Complete',
                                   message: 'Are you sure you want to mark this title as completed?',
                                   confirmUrl: 'update-status.php?id=<?php echo $title['id']; ?>&status=completed&csrf_token=<?php echo $_SESSION['csrf_token']; ?>',
                                   confirmText: 'Yes, Complete',
                                   type: 'submit'
                                }); return false;">
                                <span class="material-symbols-outlined">check_circle</span>
                                Complete
                            </a>
                            <?php elseif($title['status'] === 'completed'): ?>
                                <a href="../titles/view.php?id=<?php echo $title['id']; ?>" class="btn-outline">
                                    <span class="material-symbols-outlined">visibility</span>
                                    View Details
                                </a>
                            <?php endif; ?>
                            
                            <!-- Removed duplicate arrow button -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <div class="summary">
                <p>Showing <strong><?php echo count($titles); ?></strong> 
                   <?php 
                   if($filter === 'all') echo 'titles';
                   elseif($filter === 'pending') echo 'pending review';
                   elseif($filter === 'revisions') echo 'titles needing revisions';
                   elseif($filter === 'active') echo 'active titles';
                   elseif($filter === 'completed') echo 'completed titles';
                   ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Include Footer -->
    <?php include '../includes/dashboard-footer.php'; ?>

    <!-- Include Confirmation Modal -->
    <?php include '../includes/confirmation-modal.php'; ?>

    <!-- Create update-status.php if it doesn't exist -->
    <?php
    // Check if update-status.php exists, if not, create a note
    if (!file_exists('update-status.php')) {
        echo '<!-- Note: update-status.php needs to be created -->';
    }
    ?>

    <script>
        // Track any pending actions
        document.addEventListener('DOMContentLoaded', function() {
            if (performance.navigation.type === 1) {
                sessionStorage.removeItem('form_submitted');
            }
            
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('reviewed')) {
                sessionStorage.setItem('form_submitted', 'true');
            }
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