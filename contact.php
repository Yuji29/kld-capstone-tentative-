<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'config/database.php';
require_once 'includes/notification-mailer.php';

$db = (new Database())->getConnection();
$message_sent = false;
$error_message = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Save to database
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('unread', 'read', 'replied') DEFAULT 'unread'
            )");
            
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            
            // Send email notification to admin using Mailer class
            $mailer = new Mailer();
            
            // Check if mailer is configured
            if ($mailer->isConfigured()) {
                // Admin email
                $admin_email = "kldcapstonetracker@gmail.com";
                $admin_name = "KLD Capstone Admin";
                
                // Create email content
                $email_subject = "New Contact Form Message: $subject";
                
                $email_body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 20px auto; background: #f9f9f9; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                        .header { background: #2D5A27; color: white; padding: 30px 20px; text-align: center; }
                        .header h2 { margin: 0; font-size: 24px; }
                        .content { background: white; padding: 30px; }
                        .info-box {
                            background: #f8fbf8;
                            border-left: 4px solid #2D5A27;
                            padding: 20px;
                            margin: 20px 0;
                            border-radius: 8px;
                        }
                        .field { margin-bottom: 15px; }
                        .label { font-weight: 600; color: #2D5A27; margin-bottom: 5px; }
                        .value { 
                            background: #f5f5f5; 
                            padding: 12px; 
                            border-radius: 8px; 
                            margin-top: 5px;
                            border: 1px solid #e0e0e0;
                        }
                        .footer { 
                            text-align: center; 
                            padding: 25px; 
                            background: #f0f0f0;
                            color: #666;
                            font-size: 13px;
                            border-top: 1px solid #ddd;
                        }
                        @media (max-width: 600px) {
                            .content { padding: 20px; }
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>KLD Capstone Tracker</h2>
                            <p style='margin: 5px 0 0; opacity: 0.9;'>New Contact Form Submission</p>
                        </div>
                        <div class='content'>
                            <h3>Hello Admin,</h3>
                            
                            <p>You have received a new message from the contact form.</p>
                            
                            <div class='info-box'>
                                <div class='field'>
                                    <div class='label'>📋 Name:</div>
                                    <div class='value'>" . htmlspecialchars($name) . "</div>
                                </div>
                                
                                <div class='field'>
                                    <div class='label'>📧 Email:</div>
                                    <div class='value'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></div>
                                </div>
                                
                                <div class='field'>
                                    <div class='label'>📌 Subject:</div>
                                    <div class='value'>" . htmlspecialchars($subject) . "</div>
                                </div>
                                
                                <div class='field'>
                                    <div class='label'>💬 Message:</div>
                                    <div class='value'>" . nl2br(htmlspecialchars($message)) . "</div>
                                </div>
                                
                                <div class='field'>
                                    <div class='label'>⏰ Received:</div>
                                    <div class='value'>" . date('F j, Y, g:i a') . "</div>
                                </div>
                            </div>
                            
                            <p style='margin-top: 25px;'>
                                <a href='mailto:" . htmlspecialchars($email) . "' style='display: inline-block; padding: 12px 25px; background: #2D5A27; color: white; text-decoration: none; border-radius: 30px; font-weight: 600;'>Reply to " . htmlspecialchars($name) . "</a>
                            </p>
                            
                            <p style='color: #666; font-size: 14px; margin-top: 25px;'>
                                You can view all messages in the admin dashboard.
                            </p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " KLD Innovatech. All rights reserved.</p>
                            <p>This is an automated notification from your contact form.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Send email
                $mailer->sendCustomEmail($admin_email, $admin_name, $email_subject, $email_body);
            }
            
            $message_sent = true;
            
        } catch (PDOException $e) {
            error_log("Contact form database error: " . $e->getMessage());
            $error_message = 'Failed to send message. Please try again.';
        } catch (Exception $e) {
            error_log("Contact form error: " . $e->getMessage());
            $error_message = 'Failed to send message. Please try again.';
        }
    }
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
    <title>Contact Us - KLD Capstone Tracker</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0">
    <link rel="stylesheet" href="css/contact.css?v=<?php echo time(); ?>">
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
                <span class="welcome-text">Welcome, <span><?php echo htmlspecialchars($full_name); ?></span> (<?php echo ucfirst($role); ?>)</span>
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

    <!-- Navbar Spacer -->
    <div class="navbar-spacer"></div>

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

    <!-- Contact Hero -->
    <section class="contact-hero">
        <h1>Contact Us</h1>
        <p>Get in touch with our team for questions, support, or partnership opportunities</p>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <?php if ($message_sent): ?>
            <!-- Success Message -->
            <div class="success-message">
                <span class="material-symbols-outlined">check_circle</span>
                <h2>Message Sent Successfully!</h2>
                <p>Thank you for reaching out. Our team will get back to you within 24-48 hours.</p>
                <a href="contact.php" class="btn-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Send Another Message
                </a>
            </div>
        <?php else: ?>
            <!-- Contact Grid -->
            <div class="contact-grid">
                
                <!-- LEFT COLUMN: Contact Information -->
                <div class="contact-info">
                    <h2>Get in Touch</h2>
                    
                    <div class="info-cards">
                        <!-- Visit Us Card -->
                        <div class="info-card">
                            <div class="info-icon">
                                <span class="material-symbols-outlined">location_on</span>
                            </div>
                            <div class="info-details">
                                <h3>Visit Us</h3>
                                <p>Kolehiyo ng Lungsod ng Dasmarinas<br>Burol Main<br>Dasmarinas, Philippines 4114</p>
                            </div>
                        </div>

                        <!-- Email Us Card -->
                        <div class="info-card">
                            <div class="info-icon">
                                <span class="material-symbols-outlined">mail</span>
                            </div>
                            <div class="info-details">
                                <h3>Email Us</h3>
                                <p><a href="mailto:kldcapstonetracker@gmail.com">kldcapstonetracker@gmail.com</a></p>
                                <p><a href="mailto:information@kld.edu.ph">information@kld.edu.ph</a></p>
                            </div>
                        </div>

                        <!-- Office Hours Card -->
                        <div class="info-card">
                            <div class="info-icon">
                                <span class="material-symbols-outlined">schedule</span>
                            </div>
                            <div class="info-details">
                                <h3>Office Hours</h3>
                                <p>Monday - Saturday: 7:00 AM - 5:00 PM</p>
                                <p>Sunday: Closed</p>
                            </div>
                        </div>
                    </div>

                    <!-- Map Container -->
                    <div class="map-container">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3865.607231077821!2d120.94810611113544!3d14.334232586063308!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d50ea6b3dc99%3A0xd75cfac7573df574!2sKolehiyo%20ng%20Lungsod%20ng%20Dasmari%C3%B1as!5e0!3m2!1sen!2sph!4v1772249486914!5m2!1sen!2sph" 
                            width="100%" 
                            height="300" 
                            style="border:0; border-radius: 16px;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                        
                        <a href="https://maps.google.com/?q=Kolehiyo+ng+Lungsod+ng+Dasmarinas" target="_blank" class="map-directions">
                            <span class="material-symbols-outlined">directions</span>
                            Get Directions
                        </a>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Contact Form -->
                <div class="contact-form-container">
                    <h2>Send Us a Message</h2>
                    
                    <?php if ($error_message): ?>
                        <div class="error-alert">
                            <span class="material-symbols-outlined">error</span>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="contact-form">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required autocomplete="name"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                   placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required autocomplete="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="your@email.com">
                        </div>

                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <input type="text" id="subject" name="subject" required 
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                   placeholder="What is this regarding?">
                        </div>

                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" required 
                                      placeholder="Please provide details about your inquiry..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            <span class="material-symbols-outlined">send</span>
                            Send Message
                        </button>

                        <p style="text-align: center; margin-top: 15px; color: #888; font-size: 0.85rem;">
                            We'll respond within 24-48 hours
                        </p>
                    </form>
                </div>

            </div>
        <?php endif; ?>
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