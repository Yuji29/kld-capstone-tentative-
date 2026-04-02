<?php
session_start();
date_default_timezone_set('Asia/Manila');
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

// Only students should access this page
if($role !== 'student'){
    header('Location: ../dashboard.php');
    exit;
}

// Initialize default values
$deadlines = [];
$upcoming_deadlines = [];
$past_deadlines = [];

// Get all deadlines with visibility rules applied
try {
    $query = "SELECT d.*, u.full_name as creator_name,
              DATEDIFF(d.deadline_date, CURDATE()) as days_remaining
              FROM deadlines d 
              LEFT JOIN users u ON d.created_by = u.id 
              WHERE d.is_active = 1 
              AND (
                  d.visibility_type = 'all' 
                  OR (
                      d.visibility_type = 'specific_students' 
                      AND FIND_IN_SET(?, d.visible_to_students)
                  )
              )
              ORDER BY d.deadline_date ASC, d.deadline_time ASC";

    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Student deadlines query error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading deadlines.";
    $_SESSION['flash_type'] = "error";
    $deadlines = [];
}

// Separate upcoming and past deadlines
$upcoming_deadlines = [];
$past_deadlines = [];
$current_datetime = date('Y-m-d H:i:s');

foreach($deadlines as $deadline) {
    $deadline_datetime = $deadline['deadline_date'] . ' ' . ($deadline['deadline_time'] ?? '23:59:00');
    if($deadline_datetime >= $current_datetime) {
        $upcoming_deadlines[] = $deadline;
    } else {
        $past_deadlines[] = $deadline;
    }
}

// Sort upcoming by closest first
usort($upcoming_deadlines, function($a, $b) {
    $a_datetime = $a['deadline_date'] . ' ' . ($a['deadline_time'] ?? '23:59:00');
    $b_datetime = $b['deadline_date'] . ' ' . ($b['deadline_time'] ?? '23:59:00');
    return strtotime($a_datetime) - strtotime($b_datetime);
});

// Set variables for navigation include
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Deadlines - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/titles/deadlines.css?v=<?php echo time(); ?>">
    <style>
        /* Additional styles for search functionality */
        .deadlines-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .search-wrapper {
            position: relative;
            min-width: 280px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 20px;
            pointer-events: none;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 30px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(45, 90, 39, 0.1);
        }
        
        .search-input:hover {
            border-color: var(--primary-color);
        }
        
        .clear-search {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #999;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .clear-search:hover {
            background: #f0f0f0;
            color: #666;
        }
        
        .clear-search .material-symbols-outlined {
            font-size: 18px;
        }
        
        .deadline-count {
            background: #e9f2e7;
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .no-results-row {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
        }
        
        .no-results-row .material-symbols-outlined {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .no-results-row h3 {
            color: var(--primary-dark);
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .no-results-row p {
            color: #666;
            font-size: 1rem;
        }
        
        /* Hide elements when searching */
        .deadline-card.hidden,
        .deadline-list-item.hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            .deadlines-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-controls {
                width: 100%;
            }
            
            .search-wrapper {
                width: 100%;
                min-width: unset;
            }
        }
    </style>
</head>
<body>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>
    <div class="zen-bg-element"></div>

    <?php include '../includes/dashboard-nav.php'; ?>

    <div class="deadline-container">
        <div class="header">
            <div>
                <h1>My Deadlines</h1>
                <p class="subtitle">Track important dates and submissions</p>
            </div>
        </div>

        <?php 
        $flash_message = $_SESSION['flash_message'] ?? '';
        $flash_type = $_SESSION['flash_type'] ?? '';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        if($flash_message): ?>
            <div class="<?php echo $flash_type === 'success' ? 'message' : 'error'; ?>">
                <span class="material-symbols-outlined"><?php echo $flash_type === 'success' ? 'check_circle' : 'error'; ?></span>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <?php if(empty($deadlines)): ?>
            <div class="empty-state">
                <span class="material-symbols-outlined">event_busy</span>
                <h3>No Deadlines Yet</h3>
                <p>There are no deadlines assigned to you at the moment.</p>
            </div>
        <?php else: ?>
        
        <!-- Search Bar -->
        <div class="deadlines-header">
            <h2>
                <span class="material-symbols-outlined">event</span>
                Your Deadlines
                <span class="deadline-count"><?php echo count($deadlines); ?> total</span>
            </h2>
            <div class="header-controls">
                <div class="search-wrapper">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input type="text" 
                           id="deadlineSearch" 
                           class="search-input" 
                           placeholder="Search deadlines..."
                           onkeyup="searchDeadlines()">
                    <button class="clear-search" id="clearSearch" onclick="clearSearch()" style="display: none;">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
        </div>

            <!-- Upcoming Deadlines Section -->
            <?php if(!empty($upcoming_deadlines)): ?>
            <div class="upcoming-section" id="upcomingSection">
                <h2>
                    <span class="material-symbols-outlined">upcoming</span>
                    Upcoming Deadlines
                </h2>
                <div class="upcoming-grid" id="upcomingGrid">
                    <?php foreach($upcoming_deadlines as $deadline): 
                        $days_left = $deadline['days_remaining'];
                        $is_urgent = $days_left <= 3;
                        $deadline_datetime = $deadline['deadline_date'] . ' ' . ($deadline['deadline_time'] ?? '23:59:00');
                        
                        // Create searchable text
                        $searchable_title = strtolower($deadline['title']);
                        $searchable_category = strtolower($deadline['category'] ?? '');
                        $searchable_description = strtolower($deadline['description'] ?? '');
                    ?>
                    <div class="deadline-card <?php echo $is_urgent ? 'urgent' : ''; ?>" 
                         data-title="<?php echo htmlspecialchars($searchable_title); ?>"
                         data-category="<?php echo htmlspecialchars($searchable_category); ?>"
                         data-description="<?php echo htmlspecialchars($searchable_description); ?>">
                        <span class="category-badge"><?php echo htmlspecialchars($deadline['category'] ?? 'General'); ?></span>
                        <?php if($is_urgent): ?>
                            <span class="urgent-badge">
                                <span class="material-symbols-outlined">warning</span>
                                Urgent
                            </span>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($deadline['title']); ?></h3>
                        <?php if(!empty($deadline['description'])): ?>
                            <p class="description"><?php echo nl2br(htmlspecialchars($deadline['description'])); ?></p>
                        <?php endif; ?>
                        <div class="date-info">
                            <span class="material-symbols-outlined">calendar_month</span>
                            <?php echo date('F j, Y', strtotime($deadline['deadline_date'])); ?>
                            <?php if(!empty($deadline['deadline_time']) && $deadline['deadline_time'] !== '00:00:00'): ?>
                                <span class="material-symbols-outlined">schedule</span>
                                <?php echo date('g:i A', strtotime($deadline['deadline_time'])); ?>
                            <?php endif; ?>
                        </div>
                        <div class="days-left <?php echo $is_urgent ? 'urgent' : 'normal'; ?>">
                            <?php echo $days_left; ?> day<?php echo $days_left != 1 ? 's' : ''; ?> remaining
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Past Deadlines Section -->
            <?php if(!empty($past_deadlines)): ?>
            <div class="all-deadlines" id="pastSection">
                <h2>
                    <span class="material-symbols-outlined">history</span>
                    Past Deadlines
                </h2>
                <div class="deadlines-list" id="pastList">
                    <?php foreach($past_deadlines as $deadline): 
                        $days_left = $deadline['days_remaining'];
                        
                        // Create searchable text
                        $searchable_title = strtolower($deadline['title']);
                        $searchable_category = strtolower($deadline['category'] ?? '');
                        $searchable_description = strtolower($deadline['description'] ?? '');
                    ?>
                    <div class="deadline-list-item past"
                         data-title="<?php echo htmlspecialchars($searchable_title); ?>"
                         data-category="<?php echo htmlspecialchars($searchable_category); ?>"
                         data-description="<?php echo htmlspecialchars($searchable_description); ?>">
                        <div class="deadline-info">
                            <h4><?php echo htmlspecialchars($deadline['title']); ?></h4>
                            <?php if(!empty($deadline['description'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($deadline['description'])); ?></p>
                            <?php endif; ?>
                            <div class="deadline-meta">
                                <span class="meta-item">
                                    <span class="material-symbols-outlined">calendar_month</span>
                                    <?php echo date('F j, Y', strtotime($deadline['deadline_date'])); ?>
                                </span>
                                <?php if(!empty($deadline['deadline_time']) && $deadline['deadline_time'] !== '00:00:00'): ?>
                                    <span class="meta-item">
                                        <span class="material-symbols-outlined">schedule</span>
                                        <?php echo date('g:i A', strtotime($deadline['deadline_time'])); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="meta-item">
                                    <span class="material-symbols-outlined">category</span>
                                    <?php echo htmlspecialchars($deadline['category'] ?? 'General'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="deadline-status">
                            <span class="days-badge past">Passed</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- No Results Message (hidden by default) -->
            <div id="noResultsMessage" class="no-results-row" style="display: none;">
                <span class="material-symbols-outlined">search_off</span>
                <h3>No deadlines found</h3>
                <p>Try adjusting your search criteria</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
        // Search functionality
        function searchDeadlines() {
            const searchTerm = document.getElementById('deadlineSearch').value.toLowerCase().trim();
            const clearBtn = document.getElementById('clearSearch');
            
            // Show/hide clear button
            if (searchTerm.length > 0) {
                clearBtn.style.display = 'flex';
            } else {
                clearBtn.style.display = 'none';
            }
            
            // Get all deadline elements
            const upcomingCards = document.querySelectorAll('#upcomingGrid .deadline-card');
            const pastItems = document.querySelectorAll('#pastList .deadline-list-item');
            
            let visibleCount = 0;
            
            // Search in upcoming deadlines
            upcomingCards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const category = card.getAttribute('data-category') || '';
                const description = card.getAttribute('data-description') || '';
                const searchableText = title + ' ' + category + ' ' + description;
                
                if (searchTerm === '' || searchableText.includes(searchTerm)) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });
            
            // Search in past deadlines
            pastItems.forEach(item => {
                const title = item.getAttribute('data-title') || '';
                const category = item.getAttribute('data-category') || '';
                const description = item.getAttribute('data-description') || '';
                const searchableText = title + ' ' + category + ' ' + description;
                
                if (searchTerm === '' || searchableText.includes(searchTerm)) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });
            
            // Show/hide sections based on visibility
            const upcomingSection = document.getElementById('upcomingSection');
            const pastSection = document.getElementById('pastSection');
            const noResultsMessage = document.getElementById('noResultsMessage');
            
            const upcomingVisible = Array.from(upcomingCards).some(card => !card.classList.contains('hidden'));
            const pastVisible = Array.from(pastItems).some(item => !item.classList.contains('hidden'));
            
            if (upcomingSection) {
                upcomingSection.style.display = upcomingVisible ? 'block' : 'none';
            }
            
            if (pastSection) {
                pastSection.style.display = pastVisible ? 'block' : 'none';
            }
            
            // Show no results message if nothing is visible
            if (visibleCount === 0 && searchTerm !== '') {
                noResultsMessage.style.display = 'block';
            } else {
                noResultsMessage.style.display = 'none';
            }
        }
        
        function clearSearch() {
            document.getElementById('deadlineSearch').value = '';
            searchDeadlines();
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