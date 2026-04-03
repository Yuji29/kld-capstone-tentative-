<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'config/database.php';
$db = (new Database())->getConnection();

// Get stats with error handling
try {
    $total_titles = $db->query("SELECT COUNT(*) FROM capstone_titles")->fetchColumn() ?: 125;
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 350;
} catch (PDOException $e) {
    error_log("How it works stats error: " . $e->getMessage());
    $total_titles = 125;
    $total_users = 350;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0">
    <link rel="stylesheet" href="css/how-it-works.css?v=<?php echo time(); ?>">
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

    <!-- Hero Section -->
    <section class="how-hero">
        <h1>How It Works</h1>
        <p>A simple, streamlined process for students, advisers, and administrators</p>
    </section>

    <!-- Process Timeline -->
    <section class="process-section">
        <div class="process-container">
            
            <!-- Step 1: For Students -->
            <div class="process-step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-header">
                        <span class="material-symbols-outlined step-icon">school</span>
                        <h2>For Students</h2>
                    </div>
                    <div class="step-grid">
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">edit_note</span>
                            </div>
                            <h3>Submit Title</h3>
                            <p>Create your capstone title proposal with abstract and team members. Submit for adviser review.</p>
                        </div>
                        <div class="step-arrow">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">rate_review</span>
                            </div>
                            <h3>Receive Feedback</h3>
                            <p>Get feedback from your adviser. Make revisions if needed until approved.</p>
                        </div>
                        <div class="step-arrow">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">upload_file</span>
                            </div>
                            <h3>Upload Papers</h3>
                            <p>Once approved, upload your capstone papers and documents for final review.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: For Advisers -->
            <div class="process-step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-header">
                        <span class="material-symbols-outlined step-icon">rate_review</span>
                        <h2>For Advisers</h2>
                    </div>
                    <div class="step-grid">
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">notifications</span>
                            </div>
                            <h3>Get Notified</h3>
                            <p>Receive notifications when students submit titles or papers for review.</p>
                        </div>
                        <div class="step-arrow">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">rate_review</span>
                            </div>
                            <h3>Review & Feedback</h3>
                            <p>Review submissions, provide comments, and request revisions or approve.</p>
                        </div>
                        <div class="step-arrow">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">check_circle</span>
                            </div>
                            <h3>Track Progress</h3>
                            <p>Monitor student progress and mark titles as completed when finished.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: For Administrators -->
            <div class="process-step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <div class="step-header">
                        <span class="material-symbols-outlined step-icon">admin_panel_settings</span>
                        <h2>For Administrators</h2>
                    </div>
                    <div class="step-grid">
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">people</span>
                            </div>
                            <h3>Manage Users</h3>
                            <p>Add or remove users, assign roles, and manage department structures.</p>
                        </div>
                        <div class="step-arrow">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">category</span>
                            </div>
                            <h3>Oversee Categories</h3>
                            <p>Manage research categories and ensure proper organization of titles.</p>
                        </div>
                        <div class="step-arrow">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </div>
                        <div class="step-card">
                            <div class="step-card-icon">
                                <span class="material-symbols-outlined">assessment</span>
                            </div>
                            <h3>Generate Reports</h3>
                            <p>View analytics, track completion rates, and generate system reports.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Start Guide -->
    <section class="quick-start">
        <h2 class="section-title">Quick Start Guide</h2>
        <div class="guide-cards">
            <div class="guide-card">
                <span class="guide-icon">1</span>
                <h3>Create Account</h3>
                <p>Sign up as a student or adviser using your institutional email.</p>
                <a href="auth/register.php" class="guide-link">Register Now →</a>
            </div>
            <div class="guide-card">
                <span class="guide-icon">2</span>
                <h3>Submit or Review</h3>
                <p>Students submit titles; advisers review and provide feedback.</p>
            </div>
            <div class="guide-card">
                <span class="guide-icon">3</span>
                <h3>Track Progress</h3>
                <p>Monitor status from submission to completion.</p>
            </div>
            <div class="guide-card">
                <span class="guide-icon">4</span>
                <h3>Complete & Archive</h3>
                <p>Mark titles as completed and archive for future reference.</p>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <h2 class="section-title">Frequently Asked Questions</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <h3>How long does the review process take?</h3>
                <p>Typically 3-5 business days, depending on adviser availability and complexity of the title.</p>
            </div>
            <div class="faq-item">
                <h3>Can I have multiple capstone titles?</h3>
                <p>Yes, students can submit multiple titles, but only one active title at a time.</p>
            </div>
            <div class="faq-item">
                <h3>What file formats are accepted for papers?</h3>
                <p>PDF, DOC, DOCX, and TXT files are accepted. Maximum file size is 25MB.</p>
            </div>
            <div class="faq-item">
                <h3>How do I change my adviser?</h3>
                <p>Contact the system administrator to request an adviser change.</p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2>Ready to get started?</h2>
            <p>Join <?php echo number_format($total_users); ?>+ users already using KLD Capstone Tracker</p>
            <div class="cta-buttons">
                <a href="auth/register.php" class="btn-cta-primary">Create Account</a>
                <a href="contact.php" class="btn-cta-secondary">Contact Us</a>
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
</body>
</html>