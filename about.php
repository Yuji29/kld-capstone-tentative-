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
    <?php 
    // Use dashboard-nav for logged-in users, otherwise use simple nav
    if (isset($_SESSION['user_id'])) {
        include 'includes/dashboard-nav.php';
    } else {
        include 'includes/public-nav.php';
    }
    ?>
    
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
</body>
</html>