<?php
// includes/privacy-modals.php
// Privacy Policy and Terms of Service Modals - Clean Professional Style
?>

<!-- Privacy Policy Modal -->
<div class="modal-overlay" id="privacyModal">
    <div class="modal-card">
        <!-- Close button -->
        <button class="modal-close" onclick="closePrivacyModal()">
            <span class="material-symbols-outlined">close</span>
        </button>
        
        <!-- Modal header -->
        <div class="modal-header">
            <span class="material-symbols-outlined modal-header-icon">shield</span>
            <h2>Privacy Policy</h2>
            <p class="modal-date">Last updated: February 26, 2026</p>
        </div>
        
        <!-- Modal body -->
        <div class="modal-body">
            <div class="policy-section">
                <h3>Introduction</h3>
                <p>Welcome to KLD Capstone Tracker. We respect your privacy and are committed to protecting your personal data. This privacy policy explains how we collect, use, and safeguard your information.</p>
            </div>
            
            <div class="policy-section">
                <h3>Information We Collect</h3>
                <ul>
                    <li><span class="list-title">Full Name & ID Number:</span> To identify you within the system</li>
                    <li><span class="list-title">Email Address:</span> For communication and password recovery</li>
                    <li><span class="list-title">Department:</span> To categorize your academic affiliation</li>
                    <li><span class="list-title">Capstone Titles & Papers:</span> Your research submissions</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>How We Use Your Information</h3>
                <ul>
                    <li>Create and manage your account</li>
                    <li>Facilitate capstone title submissions and reviews</li>
                    <li>Connect students with advisers</li>
                    <li>Communicate important updates and deadlines</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>Data Security</h3>
                <p>We implement appropriate security measures including password hashing, secure HTTPS connection, and access controls to protect your personal information.</p>
            </div>
            
            <div class="policy-section">
                <h3>Contact Us</h3>
                <p>For questions about this privacy policy, please contact:</p>
                <p class="contact-email">kldcapstonetracker@gmail.com</p>
            </div>
        </div>
        
        <!-- Modal footer -->
        <div class="modal-footer">
            <button class="modal-btn" onclick="closePrivacyModal()">I Understand</button>
        </div>
    </div>
</div>

<!-- Terms of Service Modal -->
<div class="modal-overlay" id="termsModal">
    <div class="modal-card">
        <!-- Close button -->
        <button class="modal-close" onclick="closeTermsModal()">
            <span class="material-symbols-outlined">close</span>
        </button>
        
        <!-- Modal header -->
        <div class="modal-header">
            <span class="material-symbols-outlined modal-header-icon">description</span>
            <h2>Terms of Service</h2>
            <p class="modal-date">Last updated: February 26, 2026</p>
        </div>
        
        <!-- Modal body -->
        <div class="modal-body">
            <div class="policy-section">
                <h3>Acceptance of Terms</h3>
                <p>By accessing or using KLD Capstone Tracker, you agree to be bound by these Terms of Service.</p>
            </div>
            
            <div class="policy-section">
                <h3>User Accounts</h3>
                <ul>
                    <li>Provide accurate and complete information</li>
                    <li>Keep your login credentials secure</li>
                    <li>Notify us of any unauthorized access</li>
                    <li>You are responsible for all activities under your account</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>Acceptable Use</h3>
                <ul>
                    <li>Submit only original work (no plagiarism)</li>
                    <li>Do not impersonate other users</li>
                    <li>Do not attempt to bypass security measures</li>
                    <li>Respect academic integrity policies</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>Intellectual Property</h3>
                <p>You retain ownership of your capstone titles and uploaded papers. By submitting content, you grant us license to display and manage your submissions for academic purposes.</p>
            </div>
            
            <div class="policy-section">
                <h3>Contact</h3>
                <p>For questions about these terms, contact the system administrator:</p>
                <p class="contact-email">kldcapstonetracker@gmail.com</p>
            </div>
        </div>
        
        <!-- Modal footer -->
        <div class="modal-footer">
            <button class="modal-btn" onclick="closeTermsModal()">I Understand</button>
        </div>
    </div>
</div>

<!-- Modal JavaScript -->
<script>
    // Open Privacy Policy Modal
    function openPrivacyModal() {
        document.getElementById('privacyModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    // Close Privacy Policy Modal
    function closePrivacyModal() {
        document.getElementById('privacyModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Open Terms of Service Modal
    function openTermsModal() {
        document.getElementById('termsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    // Close Terms of Service Modal
    function closeTermsModal() {
        document.getElementById('termsModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const privacyModal = document.getElementById('privacyModal');
        const termsModal = document.getElementById('termsModal');
        
        if (event.target == privacyModal) {
            privacyModal.classList.remove('active');
            document.body.style.overflow = '';
        }
        if (event.target == termsModal) {
            termsModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.getElementById('privacyModal').classList.remove('active');
            document.getElementById('termsModal').classList.remove('active');
            document.body.style.overflow = '';
        }
    });
</script>

<!-- Modal Styles - Clean Professional -->
<style>
    /* Modal Overlay */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        z-index: 10000;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    /* Modal Card */
    .modal-card {
        background: white;
        width: 100%;
        max-width: 550px;
        border-radius: 28px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        position: relative;
        animation: modalFadeIn 0.3s ease;
        overflow: hidden;
        border: 1px solid #e2efdf;
    }
    
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(10px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    /* Close button */
    .modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #f0f7f0;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        color: var(--primary-color);
        z-index: 10;
    }
    
    .modal-close:hover {
        background: var(--primary-color);
        color: white;
        transform: rotate(90deg);
    }
    
    .modal-close .material-symbols-outlined {
        font-size: 20px;
    }
    
    /* Modal header */
    .modal-header {
        text-align: center;
        padding: 40px 30px 20px;
        border-bottom: 2px solid #e2efdf;
    }
    
    .modal-header-icon {
        font-size: 48px;
        color: var(--primary-color);
        background: #e9f2e7;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
    }
    
    .modal-header h2 {
        color: var(--primary-dark);
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .modal-date {
        color: #4d6a4a;
        font-size: 0.85rem;
        opacity: 0.8;
        margin-bottom: 0;
    }
    
    /* Modal body */
    .modal-body {
        padding: 20px 30px;
        max-height: 350px;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    
    /* Custom scrollbar */
    .modal-body::-webkit-scrollbar {
        width: 6px;
    }
    
    .modal-body::-webkit-scrollbar-track {
        background: #e2efdf;
        border-radius: 10px;
    }
    
    .modal-body::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 10px;
    }
    
    .modal-body::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }
    
    /* Policy sections */
    .policy-section {
        margin-bottom: 25px;
    }
    
    .policy-section h3 {
        color: var(--primary-color);
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 10px;
        position: relative;
        padding-left: 12px;
    }
    
    .policy-section h3::before {
        content: '';
        position: absolute;
        left: 0;
        top: 4px;
        bottom: 4px;
        width: 4px;
        background: var(--primary-color);
        border-radius: 2px;
    }
    
    .policy-section p {
        color: #4d6a4a;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 0;
    }
    
    .policy-section ul {
        margin: 5px 0 0 0;
        padding: 0;
        list-style: none;
    }
    
    .policy-section li {
        color: #4d6a4a;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 8px;
        position: relative;
        padding-left: 20px;
    }
    
    .policy-section li::before {
        content: "•";
        color: var(--primary-color);
        font-weight: bold;
        position: absolute;
        left: 5px;
    }
    
    .list-title {
        font-weight: 600;
        color: var(--primary-dark);
    }
    
    .contact-email {
        color: var(--primary-color);
        font-weight: 500;
        margin-top: 5px;
    }
    
    /* Modal footer */
    .modal-footer {
        padding: 20px 30px 30px;
        text-align: center;
        border-top: 2px solid #e2efdf;
    }
    
    .modal-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 12px 35px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(45, 90, 39, 0.3);
        min-width: 160px;
    }
    
    .modal-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(45, 90, 39, 0.4);
    }
    
    /* Mobile responsive */
    @media (max-width: 600px) {
        .modal-card {
            border-radius: 24px;
        }
        
        .modal-header {
            padding: 30px 20px 15px;
        }
        
        .modal-header-icon {
            width: 60px;
            height: 60px;
            font-size: 36px;
        }
        
        .modal-header h2 {
            font-size: 1.5rem;
        }
        
        .modal-body {
            padding: 15px 20px;
            max-height: 300px;
        }
        
        .modal-footer {
            padding: 15px 20px 25px;
        }
        
        .modal-btn {
            padding: 10px 30px;
            font-size: 0.9rem;
        }
    }
</style>