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

$message = '';
$error = '';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$version = isset($_GET['version']) ? $_GET['version'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Validate dates
if($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = '';
}
if($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = '';
}

// Build query with filters
$query = "SELECT SQL_CALC_FOUND_ROWS id_number, full_name, email, agreed_at, privacy_version, ip_address, user_agent 
          FROM users 
          WHERE agreed_privacy = 1";
$params = [];

if(!empty($search)) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR id_number LIKE ? OR ip_address LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if(!empty($date_from)) {
    $query .= " AND DATE(agreed_at) >= ?";
    $params[] = $date_from;
}

if(!empty($date_to)) {
    $query .= " AND DATE(agreed_at) <= ?";
    $params[] = $date_to;
}

if(!empty($version)) {
    $query .= " AND privacy_version = ?";
    $params[] = $version;
}

$query .= " ORDER BY agreed_at DESC";

// FIX: Use integer casting for LIMIT and OFFSET
$query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

// Execute query with error handling
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $total_results = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
    $total_pages = ceil($total_results / $per_page);
} catch (PDOException $e) {
    error_log("Consent logs query error: " . $e->getMessage());
    $users = [];
    $total_results = 0;
    $total_pages = 0;
    $_SESSION['flash_message'] = "Error loading consent logs.";
    $_SESSION['flash_type'] = "error";
}

// Get statistics with error handling
try {
    $total_consents = $db->query("SELECT COUNT(*) FROM users WHERE agreed_privacy = 1")->fetchColumn();
    $unique_ips = $db->query("SELECT COUNT(DISTINCT ip_address) FROM users WHERE agreed_privacy = 1 AND ip_address IS NOT NULL")->fetchColumn();
    $versions = $db->query("SELECT DISTINCT privacy_version FROM users WHERE agreed_privacy = 1 AND privacy_version IS NOT NULL ORDER BY privacy_version DESC")->fetchAll(PDO::FETCH_COLUMN);
    $latest_consent = $db->query("SELECT MAX(agreed_at) FROM users WHERE agreed_privacy = 1")->fetchColumn();
    
    // Get today's consents
    $today = date('Y-m-d');
    $today_consents = $db->prepare("SELECT COUNT(*) FROM users WHERE agreed_privacy = 1 AND DATE(agreed_at) = ?");
    $today_consents->execute([$today]);
    $today_count = $today_consents->fetchColumn();
} catch (PDOException $e) {
    error_log("Consent logs stats error: " . $e->getMessage());
    $total_consents = $unique_ips = $today_count = 0;
    $versions = [];
    $latest_consent = null;
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
    <title>Consent Logs - Admin - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet" href="../css/admin/admin-consent-logs.css?v=<?php echo time(); ?>">
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
                <h1>Consent Logs</h1>
                <p class="header-subtitle">Track user privacy consent and agreements</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">security</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($total_consents); ?></h3>
                    <p>Total Consents</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">today</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($today_count); ?></h3>
                    <p>Today</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">public</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($unique_ips); ?></h3>
                    <p>Unique IPs</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">update</span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $latest_consent ? date('M d, Y', strtotime($latest_consent)) : 'N/A'; ?></h3>
                    <p>Latest Consent</p>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="search-box">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input type="text" 
                           name="search" 
                           placeholder="Search by name, email, ID, or IP..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <?php if(!empty($search)): ?>
                        <a href="?" class="clear-search">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="filter-group">
                    <input type="date" 
                           name="date_from" 
                           class="filter-select" 
                           value="<?php echo $date_from; ?>" 
                           placeholder="From date">
                    
                    <input type="date" 
                           name="date_to" 
                           class="filter-select" 
                           value="<?php echo $date_to; ?>" 
                           placeholder="To date">
                    
                    <select name="version" class="filter-select">
                        <option value="">All Versions</option>
                        <?php foreach($versions as $ver): ?>
                            <option value="<?php echo htmlspecialchars($ver); ?>" <?php echo $version == $ver ? 'selected' : ''; ?>>
                                Version <?php echo htmlspecialchars($ver); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-filter">
                        <span class="material-symbols-outlined">filter_alt</span>
                        Apply Filters
                    </button>
                    
                    <?php if(!empty($search) || !empty($date_from) || !empty($date_to) || !empty($version)): ?>
                        <a href="?" class="clear-filters">Clear All</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="results-summary">
            <p>
                Showing <strong><?php echo count($users); ?></strong> of 
                <strong><?php echo number_format($total_results); ?></strong> consent records
            </p>
        </div>

        <!-- Consent Logs Table -->
        <div class="title-card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-outlined">history</span>
                    Consent Records
                </h2>
                <span class="item-count"><?php echo number_format($total_results); ?> records</span>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Name</th>
                            <th>ID Number</th>
                            <th>Email</th>
                            <th>Version</th>
                            <th>IP Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <span class="material-symbols-outlined">privacy_tip</span>
                                    <p>No consent records found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($user['agreed_at'])); ?></strong>
                                    <br>
                                    <small><?php echo date('h:i A', strtotime($user['agreed_at'])); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['id_number']); ?></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="version-badge">
                                        v<?php echo htmlspecialchars($user['privacy_version']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ip-address" title="<?php echo htmlspecialchars($user['ip_address'] ?? ''); ?>">
                                        <?php 
                                        $ip = $user['ip_address'] ?? 'N/A';
                                        echo strlen($ip) > 15 ? substr($ip, 0, 15) . '...' : $ip;
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-view-details" 
                                            onclick="showUserDetails(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <span class="material-symbols-outlined">info</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&version=<?php echo urlencode($version); ?>" class="page-link">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&version=<?php echo urlencode($version); ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&version=<?php echo urlencode($version); ?>" class="page-link">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal" id="userDetailsModal">
        <div class="modal-content">
            <h3>User Details</h3>
            
            <div class="modal-body">
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Full Name
                            <p id="detail-name"></p>
                        </label>
                    </div>
                    
                    <div class="detail-item">
                        <label>ID Number
                            <p id="detail-id"></p>
                        </label>
                    </div>
                    
                    <div class="detail-item">
                        <label>Email
                            <p id="detail-email"></p>
                        </label>
                    </div>
                    
                    <div class="detail-item">
                        <label>Privacy Version
                            <p id="detail-version"></p>
                        </label>
                    </div>
                    
                    <div class="detail-item">
                        <label>Consent Date
                            <p id="detail-date"></p>
                        </label>
                    </div>
                    
                    <div class="detail-item">
                        <label>Consent Time
                            <p id="detail-time"></p>
                        </label>
                    </div>
                    
                    <div class="detail-item full-width">
                        <label>IP Address
                            <p id="detail-ip"></p>
                        </label>
                    </div>
                    
                    <div class="detail-item full-width">
                        <label>User Agent
                            <p id="detail-useragent" class="user-agent"></p>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button type="button" onclick="closeUserDetails()">Close</button>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include '../includes/dashboard-footer.php'; ?>

    <script>
        // Show user details in modal
        function showUserDetails(user) {
            document.getElementById('detail-name').textContent = user.full_name || 'N/A';
            document.getElementById('detail-id').textContent = user.id_number || 'N/A';
            document.getElementById('detail-email').textContent = user.email || 'N/A';
            document.getElementById('detail-version').textContent = user.privacy_version ? 'v' + user.privacy_version : 'N/A';
            
            if (user.agreed_at) {
                const date = new Date(user.agreed_at);
                document.getElementById('detail-date').textContent = date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                document.getElementById('detail-time').textContent = date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    second: '2-digit'
                });
            } else {
                document.getElementById('detail-date').textContent = 'N/A';
                document.getElementById('detail-time').textContent = 'N/A';
            }
            
            document.getElementById('detail-ip').textContent = user.ip_address || 'N/A';
            document.getElementById('detail-useragent').textContent = user.user_agent || 'N/A';
            
            document.getElementById('userDetailsModal').classList.add('active');
        }

        function closeUserDetails() {
            document.getElementById('userDetailsModal').classList.remove('active');
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('userDetailsModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>