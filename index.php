<?php
require_once 'config/database.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Initialize default values
$total_titles = 0;
$total_advisers = 0;
$total_departments = 0;

try {
    $db = (new Database())->getConnection();
    
    if ($db) {
        $total_titles = $db->query("SELECT COUNT(*) FROM capstone_titles")->fetchColumn();
        $total_advisers = $db->query("SELECT COUNT(*) FROM users WHERE role='adviser'")->fetchColumn();
        $total_departments = $db->query("SELECT COUNT(DISTINCT department) FROM users WHERE department IS NOT NULL AND department != ''")->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Index page stats error: " . $e->getMessage());
    // Keep default values (0) if error occurs
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0">
    <link rel="stylesheet" href="css/index.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo-group">
            <a class="nav-logo" href="index.php">
                <img src="Images/kld logo.png" alt="KLD Logo">
            </a>
            <a class="logo-text" href="index.php">KLD Capstone Tracker</a>
        </div>
    
        <div class="nav-links" id="navLinks">
            <a href="auth/login.php" class="btn-login">Login</a>
            <a href="auth/register.php" class="btn-register">Register</a>
        </div>

        <span id="hamburger-btn" class="material-symbols-outlined">menu</span>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>KLD Capstone Tracker</h1>
            <h2>Track, Manage & Showcase Capstone Research</h2>
            <p>A centralized platform for students and advisers to manage capstone titles, track progress, and collaborate effectively.</p>

            <!-- Stats -->
            <div class="stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($total_titles); ?>+</span>
                    <span class="stat-label">Active Titles</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($total_advisers); ?>+</span>
                    <span class="stat-label">Advisers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo number_format($total_departments); ?>+</span>
                    <span class="stat-label">Departments</span>
                </div>
            </div>

            <!-- Buttons -->
            <div class="button-group">
                <a href="auth/register.php" class="btn btn-primary">
                    Get Started
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
                <a href="#features" class="btn btn-outline">
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="features">
        <div class="features-container">
            <div class="section-title">
                <h2>Why Choose KLD Capstone Tracker?</h2>
                <p>Designed for students, advisers, and administrators</p>
            </div>

            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="material-symbols-outlined">school</span>
                    </div>
                    <h3>For Students</h3>
                    <p>Submit capstone titles, track approval status, upload papers, and collaborate with advisers seamlessly.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="material-symbols-outlined">rate_review</span>
                    </div>
                    <h3>For Advisers</h3>
                    <p>Review submissions, provide feedback, monitor student progress, and approve final papers.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="material-symbols-outlined">admin_panel_settings</span>
                    </div>
                    <h3>For Administrators</h3>
                    <p>Manage users, oversee departments, generate reports, and ensure system integrity.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="material-symbols-outlined">category</span>
                    </div>
                    <h3>Categorized Research</h3>
                    <p>Browse capstone titles by department, category, or status for easy discovery.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <div class="cta-content">
            <h2>Ready to Get Started?</h2>
            <p>Join KLD Capstone Tracker today and streamline your capstone management process.</p>
            <div class="cta-buttons">
                <a href="auth/register.php" class="btn-cta-primary">Create Account</a>
                <a href="auth/login.php" class="btn-cta-outline">Sign In</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-left">
            <img src="Images/kld logo.png" alt="KLD">
            <span>KLD Capstone Tracker</span>
        </div>
        <div class="footer-links">
            <a href="about.php">About</a>
            <a href="how-it-works.php">How it Works</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="footer-copyright">© <?php echo date('Y'); ?> KLD Innovatech</div>
    </footer>

    <script>
    // Mobile menu toggle
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const navLinks = document.getElementById('navLinks');
    
    if (hamburgerBtn && navLinks) {
        hamburgerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            navLinks.classList.toggle('active');
        });
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
                if (navLinks) navLinks.classList.remove('active');
            }
        });
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (navLinks && hamburgerBtn) {
            if (!navLinks.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                navLinks.classList.remove('active');
            }
        }
    });

    // Handle back button cache
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    </script>
</body>
</html>