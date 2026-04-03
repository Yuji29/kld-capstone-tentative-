<?php
// includes/dashboard-nav.php
// Reusable dashboard navigation for logged-in users

// Get user data from SESSION first (this is the source of truth)
$user_email = $_SESSION['email'] ?? 'user@example.com';
$user_fullname = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
$user_avatar = '';

// Make sure we have database connection for avatar only
if (!isset($db)) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

// ONLY fetch avatar from database (not email or name)
if (isset($db) && isset($_SESSION['user_id'])) {
    try {
        $user_query = "SELECT avatar FROM users WHERE id = :user_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $user_stmt->execute();
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data && !empty($user_data['avatar'])) {
            $user_avatar = $user_data['avatar'];
        }
    } catch (PDOException $e) {
        error_log("Avatar fetch error: " . $e->getMessage());
    }
}

// Get user initials for fallback avatar
$name_parts = explode(' ', $user_fullname);
if (count($name_parts) >= 2) {
    $user_initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $user_initials = strtoupper(substr($user_fullname, 0, 2));
}
?>

<!-- Navigation -->
<nav class="navbar">
    <div class="logo-group">
        <a class="nav-logo" href="/kld-capstone/dashboard.php">
            <img src="/kld-capstone/Images/kld logo.png" alt="KLD Logo">
        </a>
        <a class="logo-text" href="/kld-capstone/dashboard.php">KLD Capstone Tracker</a>
    </div>

    <!-- Desktop User Dropdown (hidden on mobile) -->
    <div class="user-dropdown desktop-only">
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
                    <div class="dropdown-user-role"><?php echo ucfirst($role); ?></div>
                    <div class="dropdown-user-email"><?php echo htmlspecialchars($user_email); ?></div>
                </div>
            </div>
            <a href="/kld-capstone/manage-account.php" class="dropdown-item">
                <span class="material-symbols-outlined">settings</span>
                Manage Account
            </a>
            <a href="/kld-capstone/profile.php" class="dropdown-item">
                <span class="material-symbols-outlined">account_circle</span>
                Profile
            </a>
            <div class="dropdown-divider"></div>
            <a href="/kld-capstone/auth/logout.php" class="dropdown-item logout-item">
                <span class="material-symbols-outlined">logout</span>
                Log Out
            </a>
        </div>
    </div>

    <!-- Hamburger Menu Button (visible on mobile only) -->
    <span id="hamburger-btn" class="material-symbols-outlined">menu</span>
</nav>

<!-- Mobile Menu (visible on mobile only) -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <div class="mobile-menu-name"><?php echo htmlspecialchars($user_fullname); ?></div>
        <div class="mobile-menu-email"><?php echo htmlspecialchars($user_email); ?></div>
    </div>
    <a href="/kld-capstone/manage-account.php" class="mobile-menu-item">
        <span class="material-symbols-outlined">settings</span>
        Manage Account
    </a>
    <a href="/kld-capstone/profile.php" class="mobile-menu-item">
        <span class="material-symbols-outlined">account_circle</span>
        Profile
    </a>
    <a href="/kld-capstone/auth/logout.php" class="mobile-menu-item logout">
        <span class="material-symbols-outlined">logout</span>
        Log Out
    </a>
</div>

<style>
    /* ===== RESET & BASE STYLES ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

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
    }

    .logo-text {
        color: #ffffff;
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 1.3rem;
        text-decoration: none;
    }

    /* Hide logo text on mobile */
    @media (max-width: 768px) {
        .logo-text {
            display: none;
        }
    }

    /* Desktop only elements */
    .desktop-only {
        display: flex;
    }

    /* Hide desktop dropdown on mobile */
    @media (max-width: 768px) {
        .desktop-only {
            display: none !important;
        }
    }

    /* ===== USER DROPDOWN (DESKTOP ONLY) ===== */
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

    /* DROPDOWN OVERLAYS EVERYTHING */
    .user-dropdown-menu {
        position: fixed;
        top: 70px;
        right: 20px;
        min-width: 260px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        z-index: 99999 !important;
        display: none;
        overflow: hidden;
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

    .dropdown-user-email {
        font-size: 11px;
        color: #888;
        margin-top: 2px;
        word-break: break-all;
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

    /* ===== HAMBURGER MENU BUTTON ===== */
    #hamburger-btn {
        display: none;
        font-size: 2rem;
        cursor: pointer;
        color: white;
    }

    /* Show hamburger button on mobile only */
    @media (max-width: 768px) {
        #hamburger-btn {
            display: block;
        }
    }

    /* ===== MOBILE MENU ===== */
    .mobile-menu {
        display: none;
        position: fixed;
        top: 70px;
        right: 10px;
        left: auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        z-index: 99999 !important;
        min-width: 260px;
        overflow: hidden;
        animation: fadeIn 0.2s ease;
    }

    .mobile-menu.active {
        display: block;
    }

    .mobile-menu-header {
        padding: 16px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .mobile-menu-name {
        font-weight: 600;
        color: #1a1a2e;
        font-size: 15px;
        margin-bottom: 4px;
    }

    .mobile-menu-email {
        font-size: 12px;
        color: #6c757d;
    }

    .mobile-menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #333;
        text-decoration: none;
        font-size: 14px;
        transition: background 0.2s ease;
    }

    .mobile-menu-item:hover {
        background: #f8f9fa;
    }

    .mobile-menu-item .material-symbols-outlined {
        font-size: 20px;
        color: #6c757d;
    }

    .mobile-menu-item.logout {
        color: #dc3545;
        border-top: 1px solid #e9ecef;
    }

    .mobile-menu-item.logout:hover {
        background: #fff5f5;
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

    /* Ensure all content below navbar has proper margin */
    body {
        padding-top: 80px;
        background: #f5f7f5;
    }
    
    @media (max-width: 768px) {
        body {
            padding-top: 70px;
        }
    }
</style>

<script>
// Toggle user dropdown menu (desktop only)
function toggleUserDropdown(event) {
    event.stopPropagation();
    const menu = document.getElementById('userDropdownMenu');
    // Close mobile menu if open
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileMenu) {
        mobileMenu.classList.remove('active');
    }
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
    }
});

// Mobile menu toggle
const hamburgerBtn = document.getElementById('hamburger-btn');
const mobileMenu = document.getElementById('mobileMenu');

if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        // Close user dropdown if open
        const userDropdown = document.getElementById('userDropdownMenu');
        if (userDropdown) {
            userDropdown.classList.remove('show');
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
    }
});
</script>