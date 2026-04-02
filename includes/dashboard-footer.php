<?php
// includes/dashboard-footer.php
// Reusable dashboard footer for all pages
?>
<!-- Footer -->
<footer class="footer">
    <div class="footer-left">
        <img src="/kld-capstone/Images/kld logo.png" alt="KLD logo">
        <span>KLD Capstone Title Tracker</span>
    </div>
    <div class="footer-links">
        <a href="../about.php">About</a>
        <a href="../how-it-works.php">How it Works</a>
        <a href="../contact.php">Contact</a>
    </div>
    <div>© 2026 KLD Innovatech</div>
</footer>

<style>
    /* ===== FOOTER STYLES ===== */
    /* See footer section below for main footer styling - matches homepage exactly with underline hover effect */
    .footer {
        background: #1a1a1a;
        color: #b0d0b0;
        padding: 2rem 1.5rem;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        border-top: 2px solid var(--primary-color);
        width: 100%;
        margin-top: auto;
        box-sizing: border-box;
    }

    .footer-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .footer-left img {
        height: 2rem;
        width: auto;
    }

    .footer-links {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .footer-links a {
        color: #b0d0b0;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s;
        position: relative;
        padding-bottom: 2px;
    }

    .footer-links a::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 1px;
        background-color: white;
        transition: width 0.3s ease;
    }

    .footer-links a:hover {
        color: white;
    }

    .footer-links a:hover::after {
        width: 100%;
    }

    .footer-copyright {
        font-size: 0.9rem;
    }

    /* Desktop footer layout - links in row */
    @media (min-width: 769px) {
        .footer-links {
            display: flex;
            flex-direction: row;
            gap: 30px;
        }
    }

    @media (max-width: 768px) {
        .footer {
            flex-direction: column;
            text-align: center;
            padding: 2rem 1.5rem;
        }

        .footer-left {
            flex-direction: row;
            justify-content: center;
        }

        .footer-links {
            justify-content: center;
        }
    }

    @media (max-width: 640px) {
        .footer {
            flex-direction: column;
            text-align: center;
        }

        .footer-left {
            justify-content: center;
        }

        .footer-links {
            justify-content: center;
        }
    }

    @media (max-width: 360px) {
        .footer-links {
            gap: 8px;
        }

        .footer-links a {
            font-size: 0.8rem;
            padding: 6px 2px;
        }
    }

        /* Small mobile adjustments */
        @media (max-width: 480px) {
            .footer {
                padding: 1.5rem 1rem;
            }

            .footer-left span {
                font-size: 0.95rem;
            }

            .footer-links {
                gap: 1.5rem;
            }

            .footer-links a {
                font-size: 0.85rem;
            }

            .footer > div:last-child {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 360px) {
            .footer-links {
                gap: 1rem;
            }

            .footer-links a {
                font-size: 0.8rem;
            }
        }
</style>