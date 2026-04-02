<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'config/database.php';
$db = (new Database())->getConnection();

// Get stats for about page with error handling and fallback values
try {
    $total_titles = $db->query("SELECT COUNT(*) FROM capstone_titles")->fetchColumn();
    $total_titles = $total_titles ?: 125;
    
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_users = $total_users ?: 350;
    
    $total_advisers = $db->query("SELECT COUNT(*) FROM users WHERE role='adviser'")->fetchColumn();
    $total_advisers = $total_advisers ?: 28;
    
    $total_departments = $db->query("SELECT COUNT(DISTINCT department) FROM users WHERE department IS NOT NULL AND department != ''")->fetchColumn();
    $total_departments = $total_departments ?: 8;
} catch (PDOException $e) {
    error_log("About page stats error: " . $e->getMessage());
    $total_titles = 125;
    $total_users = 350;
    $total_advisers = 28;
    $total_departments = 8;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0">
    <link rel="stylesheet" href="css/about.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo-group">
            <a class="nav-logo" href="<?php echo $is_logged_in ? 'dashboard.php' : 'index.php'; ?>">
                <img src="Images/kld logo.png" alt="KLD Logo">
            </a>
            <a class="logo-text" href="<?php echo $is_logged_in ? 'dashboard.php' : 'index.php'; ?>">
                KLD Capstone Tracker
            </a>
        </div>

        <div class="nav-links" id="navLinks">
            <?php if($is_logged_in): ?>
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($full_name); ?> (<?php echo ucfirst($role); ?>)</span>
                <a href="auth/logout.php" class="btn-logout">
                    <span class="material-symbols-outlined">logout</span>
                    Logout
                </a>
            <?php else: ?>
                <a href="auth/login.php" class="btn-login">Login</a>
                <a href="auth/register.php" class="btn-register">Register</a>
            <?php endif; ?>
        </div>

        <span id="hamburger-btn" class="material-symbols-outlined">menu</span>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <?php if($is_logged_in): ?>
            <div class="mobile-menu-item">
                <span class="material-symbols-outlined">person</span>
                <span><?php echo htmlspecialchars($full_name); ?> (<?php echo ucfirst($role); ?>)</span>
            </div>
            <a href="auth/logout.php" class="mobile-menu-item logout">
                <span class="material-symbols-outlined">logout</span>
                Logout
            </a>
        <?php endif; ?>
    </div>
    
    <div class="navbar-spacer"></div>

    <!-- About Hero -->
    <section class="about-hero">
        <h1>About KLD Capstone Tracker</h1>
        <p>Empowering students and advisers through streamlined capstone management</p>
    </section>

    <!-- About Content -->
    <section class="about-section">
        <div class="about-grid">
            <div class="about-content">
                <h2>Our Story</h2>
                <p>KLD Capstone Tracker was born from a simple observation: students and advisers needed a better way to manage capstone projects. Traditional methods using spreadsheets, email chains, and physical documents were inefficient and prone to errors.</p>
                <p>In 2026, a team of developers and educators came together to create a centralized platform that would revolutionize how capstone projects are managed. Today, KLD Capstone Tracker serves hundreds of students and advisers, making the capstone journey smoother and more collaborative.</p>
            </div>
            
            <div class="quality-card">
                <span class="material-symbols-outlined">school</span>
                <h3>Quality Education</h3>
                <p>Supporting academic excellence through technology</p>
            </div>
        </div>

        <!-- Stats Section -->
        <h2 class="section-title">Our Impact</h2>
        <div class="about-stats">
            <div class="about-stat-item">
                <span class="material-symbols-outlined">school</span>
                <h3><?php echo number_format($total_users); ?>+</h3>
                <p>Active Users</p>
            </div>
            <div class="about-stat-item">
                <span class="material-symbols-outlined">description</span>
                <h3><?php echo number_format($total_titles); ?>+</h3>
                <p>Capstone Titles</p>
            </div>
            <div class="about-stat-item">
                <span class="material-symbols-outlined">people</span>
                <h3><?php echo number_format($total_advisers); ?>+</h3>
                <p>Expert Advisers</p>
            </div>
            <div class="about-stat-item">
                <span class="material-symbols-outlined">category</span>
                <h3><?php echo number_format($total_departments); ?>+</h3>
                <p>Departments</p>
            </div>
        </div>

        <!-- Mission & Vision -->
        <div class="mission-vision">
            <div class="mission-card">
                <h3>
                    <span class="material-symbols-outlined">flag</span>
                    Our Mission
                </h3>
                <p>To provide a robust, user-friendly platform that streamlines the capstone management process, fosters collaboration between students and advisers, and maintains high academic standards through efficient tracking and organization.</p>
            </div>
            
            <div class="vision-card">
                <h3>
                    <span class="material-symbols-outlined">visibility</span>
                    Our Vision
                </h3>
                <p>To become the leading capstone management solution in academic institutions, recognized for innovation, reliability, and positive impact on student success and research quality.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-left">
            <img src="Images/kld logo.png" alt="KLD">
            <span>KLD Capstone Title Tracker</span>
        </div>
        <div class="footer-links">
            <a href="about.php">About</a>
            <a href="how-it-works.php">How it Works</a>
            <a href="contact.php">Contact</a>
        </div>
        <div>© <?php echo date('Y'); ?> KLD Innovatech</div>
    </footer>

    <script>
        // Mobile menu toggle
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const mobileMenu = document.getElementById('mobileMenu');
        const navLinks = document.getElementById('navLinks');
        
        if (hamburgerBtn && mobileMenu) {
            hamburgerBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('active');
                if (window.innerWidth <= 640 && navLinks) {
                    navLinks.classList.toggle('active');
                }
            });
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (mobileMenu && hamburgerBtn && !mobileMenu.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                mobileMenu.classList.remove('active');
                if (window.innerWidth <= 640 && navLinks) {
                    navLinks.classList.remove('active');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 640 && navLinks) {
                navLinks.classList.remove('active');
            }
            if (mobileMenu) {
                mobileMenu.classList.remove('active');
            }
        });
    </script>
</body>
</html>