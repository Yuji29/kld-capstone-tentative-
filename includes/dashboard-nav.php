<?php
// includes/dashboard-nav.php
// Reusable dashboard navigation for logged-in users
?>
<!-- Navigation -->
<nav class="navbar">
    <div class="logo-group">
        <a class="nav-logo" href="/kld-capstone/dashboard.php">
            <img src="/kld-capstone/Images/kld logo.png" alt="KLD Logo">
        </a>
        <a class="logo-text" href="/kld-capstone/dashboard.php">KLD Capstone Tracker</a>
    </div>

    <div class="nav-links" id="navLinks">
        <span class="welcome-text">Welcome, <?php echo htmlspecialchars($full_name ?? 'User'); ?> (<?php echo ucfirst($role ?? 'user'); ?>)</span>
        <a href="/kld-capstone/auth/logout.php" class="btn-logout">
            <span class="material-symbols-outlined">logout</span>
            Logout
        </a>
    </div>

    <span id="hamburger-btn" class="material-symbols-outlined">menu</span>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-item">
        <span class="material-symbols-outlined">person</span>
        <span><?php echo htmlspecialchars($full_name ?? 'User'); ?> (<?php echo ucfirst($role ?? 'user'); ?>)</span>
    </div>
    <a href="/kld-capstone/auth/logout.php" class="mobile-menu-item logout">
        <span class="material-symbols-outlined">logout</span>
        Logout
    </a>
</div>

<style>
    /* ===== NAVIGATION STYLES - EXACT MATCH WITH INDEX.CSS ===== */
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        max-width: 100%;
        background: rgba(18, 26, 18, 0.98);
        backdrop-filter: blur(10px);
        border-bottom: 2px solid var(--primary-color, #2D5A27);
        padding: 0.75rem 2rem;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        min-height: 80px;
        box-sizing: border-box;
        overflow-x: hidden;
    }

    .navbar:hover {
        border-bottom-color: var(--accent-color, #4CAF50);
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
        max-width: 70%;
        flex-shrink: 1;
    }

    .nav-logo img {
        height: 2.5rem;
        width: auto;
        flex-shrink: 0;
        filter: brightness(1.1);
        transition: transform 0.3s ease;
    }

    .nav-logo:hover img {
        transform: scale(1.1) rotate(5deg);
    }

    .logo-text {
        color: var(--text-light, #ffffff);
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: 1.3rem;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        transition: color 0.2s;
        letter-spacing: -0.02em;
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
        background-color: var(--accent-color, #4CAF50);
        transition: width 0.3s ease;
    }

    .logo-text:hover::after {
        width: 100%;
    }

    .logo-text:hover {
        color: var(--accent-color, #4CAF50);
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 30px;
        flex-shrink: 0;
    }

    .nav-links a {
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        padding: 0.6rem 1.5rem;
        border-radius: 50px;
        transition: all 0.3s ease;
        white-space: nowrap;
        letter-spacing: 0.3px;
        font-family: 'Poppins', sans-serif;
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

    .welcome-text {
        color: var(--text-light, #ffffff);
        font-weight: 500;
        font-size: 1rem;
        position: relative;
        padding-bottom: 5px;
        transition: color 0.3s ease;
        white-space: nowrap;
    }

    .welcome-text::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background-color: var(--accent-color, #4CAF50);
        transition: width 0.3s ease;
    }

    .welcome-text:hover {
        color: var(--accent-color, #4CAF50);
    }

    .welcome-text:hover::after {
        width: 100%;
    }

    /* Logout Button - styled like register button from index.php */
    .btn-logout {
        background: var(--primary-color, #2D5A27);
        border: 2px solid var(--primary-color, #2D5A27);
        color: var(--text-light, #ffffff);
        padding: 8px 24px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        overflow: hidden;
        font-size: 0.95rem;
    }

    .btn-logout::before {
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

    .btn-logout:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-logout:hover {
        background: var(--primary-dark, #1e3d1a);
        border-color: var(--primary-dark, #1e3d1a);
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(45, 90, 39, 0.4);
    }

    .btn-logout .material-symbols-outlined {
        font-size: 20px;
        transition: transform 0.3s ease;
    }

    .btn-logout:hover .material-symbols-outlined {
        transform: translateX(3px);
    }

    #hamburger-btn {
        color: var(--text-light, #ffffff);
        cursor: pointer;
        font-size: 2rem;
        display: none;
        transition: transform 0.3s ease;
    }

    #hamburger-btn:hover {
        transform: scale(1.1);
        color: var(--accent-color, #4CAF50);
    }

    /* Mobile Menu */
    .mobile-menu {
        display: none;
        position: fixed;
        top: 80px;
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

    /* ===== MOBILE NAVIGATION STYLES - BIGGER BUTTONS ===== */
    @media (max-width: 925px) {
         .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            max-width: 100vw;
            background: rgba(18, 26, 18, 0.98);
            backdrop-filter: blur(10px);
            padding: 2rem 1.5rem;
            flex-direction: column;
            align-items: center;
            gap: 1.2rem;
            border-top: 2px solid var(--primary-color, #2D5A27);
            box-shadow: 0 15px 25px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
            z-index: 999;
            box-sizing: border-box;
        }

        .logo-text {
            display: none;
        }

        .nav-links.active {
            display: flex;
        }

        .nav-links a, .nav-links .welcome-text {
            width: 100%;
            text-align: center;
            padding: 1rem 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 60px;
            margin: 0.25rem 0;
            box-sizing: border-box;
        }

        .welcome-text {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid var(--primary-color, #2D5A27);
            color: white;
            white-space: normal;
            word-break: break-word;
        }

        .btn-logout {
            width: 100%;
            justify-content: center;
            background: #dc3545;
            border: 2px solid #dc3545;
        }

        #hamburger-btn {
            display: block;
            font-size: 2.5rem;
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
    }

    @media (max-width: 640px) {
        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            max-width: 100vw;
            background: rgba(18, 26, 18, 0.98);
            backdrop-filter: blur(10px);
            padding: 2rem 1.5rem;
            flex-direction: column;
            align-items: center;
            gap: 1.2rem;
            border-top: 2px solid var(--primary-color, #2D5A27);
            box-shadow: 0 15px 25px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
            z-index: 999;
            box-sizing: border-box;
        }

        .logo-text {
            display: none;
        }

        .nav-links.active {
            display: flex;
        }

        .nav-links a, .nav-links .welcome-text {
            width: 100%;
            text-align: center;
            padding: 1rem 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 60px;
            margin: 0.25rem 0;
            box-sizing: border-box;
        }

        .welcome-text {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid var(--primary-color, #2D5A27);
            color: white;
            white-space: normal;
            word-break: break-word;
        }

        .btn-logout {
            width: 100%;
            justify-content: center;
            background: #dc3545;
            border: 2px solid #dc3545;
        }

        #hamburger-btn {
            display: block;
            font-size: 2.5rem;
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
    }

    /* Logo adjustments for mobile */
    @media (max-width: 768px) {
        .logo-group {
            max-width: 85%;
        }
    }

    @media (max-width: 480px) {
        .logo-text {
            font-size: 1.1rem;
        }
        .nav-logo img {
            height: 2.8rem !important;
        }
        .logo-group {
            max-width: 90%;
        }
    }

    @media (max-width: 360px) {
        .logo-text {
            font-size: 1rem;
        }
        .nav-logo img {
            height: 2.5rem !important;
        }
    }
</style>