<?php
// includes/public-nav.php
// Navigation for public pages - auto detects if user is logged in

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_fullname = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'user';

// Get user avatar if logged in
$user_avatar = '';
$user_initials = '';

if ($is_logged_in && isset($_SESSION['user_id'])) {
    // Try to get avatar from database
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $avatar_query = "SELECT avatar FROM users WHERE id = :user_id";
        $avatar_stmt = $db->prepare($avatar_query);
        $avatar_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $avatar_stmt->execute();
        $user_data = $avatar_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data && !empty($user_data['avatar'])) {
            $user_avatar = $user_data['avatar'];
        }
    } catch (PDOException $e) {
        error_log("Avatar fetch error: " . $e->getMessage());
    }
    
    // Get user initials
    $name_parts = explode(' ', $user_fullname);
    if (count($name_parts) >= 2) {
        $user_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
    } else {
        $user_initials = strtoupper(substr($user_fullname, 0, 2));
    }
}
?>

<nav class="navbar">
    <div class="logo-group">
        <a class="nav-logo" href="/kld-capstone/index.php">
            <img src="/kld-capstone/Images/kld logo.png" alt="KLD Logo">
        </a>
        <a class="logo-text" href="/kld-capstone/index.php">KLD Capstone Tracker</a>
    </div>

    <div class="nav-links" id="navLinks">
        <?php if($is_logged_in): ?>
            <!-- Show user dropdown when logged in -->
            <div class="user-dropdown">
                <button class="user-dropdown-btn" onclick="toggleUserDropdown(event)">
                    <?php if($user_avatar): ?>
                        <img src="/kld-capstone/<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" class="user-avatar-img">
                    <?php else: ?>
                        <div class="user-avatar-initials">
                            <?php echo $user_initials; ?>
                        </div>
                    <?php endif; ?>
                    <span class="user-name"><?php echo htmlspecialchars($user_fullname); ?></span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <div class="dropdown-header">
                        <?php if($user_avatar): ?>
                            <img src="/kld-capstone/<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" class="dropdown-avatar">
                        <?php else: ?>
                            <div class="dropdown-avatar-initials">
                                <?php echo $user_initials; ?>
                            </div>
                        <?php endif; ?>
                        <div class="dropdown-user-info">
                            <div class="dropdown-user-name"><?php echo htmlspecialchars($user_fullname); ?></div>
                            <div class="dropdown-user-role"><?php echo ucfirst($user_role); ?></div>
                        </div>
                    </div>
                    <?php if($user_role === 'admin'): ?>
                        <a href="/kld-capstone/admin/dashboard.php" class="dropdown-item">
                            <span class="material-symbols-outlined">dashboard</span>
                            Admin Dashboard
                        </a>
                    <?php elseif($user_role === 'adviser'): ?>
                        <a href="/kld-capstone/adviser/dashboard.php" class="dropdown-item">
                            <span class="material-symbols-outlined">dashboard</span>
                            Adviser Dashboard
                        </a>
                    <?php else: ?>
                        <a href="/kld-capstone/dashboard.php" class="dropdown-item">
                            <span class="material-symbols-outlined">dashboard</span>
                            Dashboard
                        </a>
                    <?php endif; ?>
                    <a href="/kld-capstone/profile.php" class="dropdown-item">
                        <span class="material-symbols-outlined">account_circle</span>
                        Profile
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/kld-capstone/auth/logout.php" class="dropdown-item logout-item">
                        <span class="material-symbols-outlined">logout</span>
                        Logout
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Show login/register buttons when not logged in -->
            <div class="auth-buttons">
                <a href="/kld-capstone/auth/login.php" class="btn-login">Login</a>
                <a href="/kld-capstone/auth/register.php" class="btn-register">Register</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hamburger Menu Button (visible on mobile only) -->
    <span id="hamburger-btn" class="material-symbols-outlined">menu</span>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <a href="/kld-capstone/index.php" class="mobile-menu-item">Home</a>
    <a href="/kld-capstone/about.php" class="mobile-menu-item">About</a>
    <a href="/kld-capstone/how-it-works.php" class="mobile-menu-item">How It Works</a>
    <a href="/kld-capstone/contact.php" class="mobile-menu-item">Contact</a>
    <div class="mobile-divider"></div>
    
    <?php if($is_logged_in): ?>
        <a href="/kld-capstone/dashboard.php" class="mobile-menu-item">Dashboard</a>
        <a href="/kld-capstone/profile.php" class="mobile-menu-item">Profile</a>
        <a href="/kld-capstone/auth/logout.php" class="mobile-menu-item logout">Logout</a>
    <?php else: ?>
        <a href="/kld-capstone/auth/login.php" class="mobile-menu-item">Login</a>
        <a href="/kld-capstone/auth/register.php" class="mobile-menu-item register">Register</a>
    <?php endif; ?>
</div>

<style>
    /* ===== NAVIGATION STYLES ===== */
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        background: rgba(18, 26, 18, 0.98);
        backdrop-filter: blur(10px);
        border-bottom: 2px solid #2D5A27;
        padding: 0.75rem 2rem;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 80px;
    }

    @media (max-width: 768px) {
        .navbar {
            padding: 0.75rem 1rem;
            min-height: 70px;
        }
    }

    .logo-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .nav-logo img {
        height: 2.5rem;
        width: auto;
        transition: transform 0.3s ease;
    }

    .nav-logo:hover img {
        transform: scale(1.1) rotate(5deg);
    }

    .logo-text {
        color: #ffffff;
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 1.3rem;
        text-decoration: none;
        position: relative;
        padding-bottom: 5px;
    }

    .logo-text::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background-color: #4CAF50;
        transition: width 0.3s ease;
    }

    .logo-text:hover::after {
        width: 100%;
    }

    .logo-text:hover {
        color: #4CAF50;
    }

    @media (max-width: 768px) {
        .logo-text {
            display: none;
        }
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 30px;
    }

    .nav-links a {
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        padding: 0.6rem 1rem;
        border-radius: 50px;
        transition: all 0.3s ease;
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }

    .nav-links a::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .nav-links a:hover::before {
        width: 300px;
        height: 300px;
    }

    .nav-links a:hover {
        color: #4CAF50;
    }

    .auth-buttons {
        display: flex;
        gap: 10px;
        margin-left: 10px;
    }

    .btn-login {
        background: transparent;
        border: 2px solid #2D5A27;
        color: white !important;
    }

    .btn-login:hover {
        background: #2D5A27;
        color: white !important;
        transform: translateY(-2px);
    }

    .btn-register {
        background: #2D5A27;
        border: 2px solid #2D5A27;
        color: white !important;
    }

    .btn-register:hover {
        background: #1e3d1a;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(45, 90, 39, 0.3);
    }

    /* User Dropdown Styles (same as dashboard-nav) */
    .user-dropdown {
        position: relative;
        display: inline-block;
    }

    .user-dropdown-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 40px;
        transition: all 0.3s ease;
        color: #ffffff;
    }

    .user-dropdown-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .user-avatar-img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
    }

    .user-avatar-initials {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #2D5A27;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
        color: white;
    }

    .user-name {
        font-weight: 500;
        font-size: 14px;
    }

    .dropdown-arrow {
        font-size: 10px;
        color: #aaa;
    }

    .user-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        min-width: 260px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        z-index: 99999 !important;
        display: none;
        overflow: hidden;
        margin-top: 10px;
    }

    .user-dropdown-menu.show {
        display: block;
        animation: dropdownFadeIn 0.2s ease;
    }

    @keyframes dropdownFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .dropdown-header {
        padding: 16px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .dropdown-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
    }

    .dropdown-avatar-initials {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #2D5A27;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        color: white;
    }

    .dropdown-user-info {
        flex: 1;
    }

    .dropdown-user-name {
        font-weight: 600;
        color: #1a1a2e;
        font-size: 15px;
        margin-bottom: 4px;
    }

    .dropdown-user-role {
        font-size: 12px;
        color: #6c757d;
        text-transform: capitalize;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        text-decoration: none;
        color: #333;
        font-size: 14px;
        transition: background 0.2s ease;
    }

    .dropdown-item:hover {
        background: #f8f9fa;
    }

    .dropdown-item .material-symbols-outlined {
        font-size: 20px;
        color: #6c757d;
    }

    .dropdown-divider {
        height: 1px;
        background: #e9ecef;
        margin: 8px 0;
    }

    .logout-item {
        color: #dc3545;
    }

    .logout-item .material-symbols-outlined {
        color: #dc3545;
    }

    .logout-item:hover {
        background: #fff5f5;
    }

    #hamburger-btn {
        display: none;
        font-size: 2rem;
        cursor: pointer;
        color: white;
        transition: transform 0.3s ease;
    }

    #hamburger-btn:hover {
        transform: scale(1.1);
        color: #4CAF50;
    }

    @media (max-width: 768px) {
        #hamburger-btn {
            display: block;
        }
    }

    /* Mobile Menu */
    .mobile-menu {
        display: none;
        position: fixed;
        top: 70px;
        right: 20px;
        background: white;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 1000;
        min-width: 200px;
        border: 1px solid #e2efdf;
        animation: fadeIn 0.3s ease;
    }

    .mobile-menu.active {
        display: block;
    }

    .mobile-menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        color: #333;
        text-decoration: none;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .mobile-menu-item:hover {
        background: #f0f7f0;
        transform: translateX(5px);
    }

    .mobile-menu-item.logout {
        color: #dc3545;
        border-top: 1px solid #e0e0e0;
        margin-top: 10px;
        padding-top: 15px;
    }

    .mobile-menu-item.logout:hover {
        background: #fee7e7;
    }

    .mobile-menu-item.register {
        color: #2D5A27;
        border-top: 1px solid #e0e0e0;
        margin-top: 10px;
        padding-top: 15px;
    }

    .mobile-divider {
        height: 1px;
        background: #e2efdf;
        margin: 10px 15px;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile navigation styles */
    @media (max-width: 925px) {
        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: rgba(18, 26, 18, 0.98);
            backdrop-filter: blur(10px);
            padding: 2rem 1.5rem;
            flex-direction: column;
            align-items: center;
            gap: 1.2rem;
            border-top: 2px solid #2D5A27;
            box-shadow: 0 15px 25px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
            z-index: 999;
        }

        .nav-links.active {
            display: flex;
        }

        .nav-links a {
            width: 100%;
            text-align: center;
            padding: 1rem 1.5rem;
            font-size: 1.2rem;
        }

        .auth-buttons {
            flex-direction: column;
            width: 100%;
            margin-left: 0;
        }

        .btn-login, .btn-register {
            width: 100%;
            text-align: center;
            justify-content: center;
        }
        
        .user-dropdown {
            width: 100%;
        }
        
        .user-dropdown-btn {
            width: 100%;
            justify-content: center;
        }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    body {
        padding-top: 80px;
    }

    @media (max-width: 768px) {
        body {
            padding-top: 70px;
        }
    }
</style>

<script>
// Toggle user dropdown menu
function toggleUserDropdown(event) {
    event.stopPropagation();
    const menu = document.getElementById('userDropdownMenu');
    if (menu) {
        menu.classList.toggle('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.user-dropdown');
    const menu = document.getElementById('userDropdownMenu');
    
    if (dropdown && !dropdown.contains(event.target)) {
        if (menu) {
            menu.classList.remove('show');
        }
    }
});

// Close dropdown on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const menu = document.getElementById('userDropdownMenu');
        if (menu) {
            menu.classList.remove('show');
        }
        const mobileMenu = document.getElementById('mobileMenu');
        if (mobileMenu) {
            mobileMenu.classList.remove('active');
        }
        const navLinks = document.getElementById('navLinks');
        if (navLinks) {
            navLinks.classList.remove('active');
        }
    }
});

// Mobile menu toggle
const hamburgerBtn = document.getElementById('hamburger-btn');
const mobileMenu = document.getElementById('mobileMenu');
const navLinks = document.getElementById('navLinks');

if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        if (navLinks) {
            navLinks.classList.toggle('active');
        }
        if (mobileMenu) {
            mobileMenu.classList.toggle('active');
        }
    });
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    if (mobileMenu && hamburgerBtn && 
        !mobileMenu.contains(event.target) && 
        !hamburgerBtn.contains(event.target)) {
        mobileMenu.classList.remove('active');
        if (navLinks) {
            navLinks.classList.remove('active');
        }
    }
});
</script>