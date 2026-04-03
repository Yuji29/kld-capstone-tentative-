<?php
// includes/privacy-modals.php
// Privacy Policy and Terms of Service Modals - Styled like Edit User Modal
?>

<!-- Privacy Policy Modal -->
<div class="modal" id="privacyModal">
    <div class="modal-content">
        <div class="modal-header-custom">
            <span class="material-symbols-outlined">privacy_tip</span>
            <h3>Privacy Policy</h3>
        </div>
        
        <div class="modal-body-custom">
            <div class="modal-date">Last updated: April 3, 2026</div>
            
            <div class="policy-section">
                <h4>Introduction</h4>
                <p>Welcome to KLD Capstone Tracker. We respect your privacy and are committed to protecting your personal data. This privacy policy explains how we collect, use, and safeguard your information.</p>
            </div>
            
            <div class="policy-section">
                <h4>Information We Collect</h4>
                <ul>
                    <li><strong>Full Name & ID Number:</strong> To identify you within the system</li>
                    <li><strong>Email Address:</strong> For communication, password recovery, and account verification</li>
                    <li><strong>Department:</strong> To categorize your academic affiliation</li>
                    <li><strong>Capstone Titles & Papers:</strong> Your research submissions and uploaded documents</li>
                    <li><strong>Profile Picture:</strong> Optional image to personalize your account</li>
                    <li><strong>IP Address & User Agent:</strong> For security and activity logging</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h4>How We Use Your Information</h4>
                <ul>
                    <li>Create and manage your account</li>
                    <li>Facilitate capstone title submissions and reviews</li>
                    <li>Connect students with advisers</li>
                    <li>Communicate important updates and deadlines</li>
                    <li>Send email verification codes (OTP) to confirm your identity</li>
                    <li>Track active sessions to detect unauthorized access</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h4>Email Verification (OTP)</h4>
                <p>To ensure the security of your account, we send a One-Time Password (OTP) verification code to your registered email address during registration and login. This helps us confirm your identity and prevent unauthorized access.</p>
            </div>
            
            <div class="policy-section">
                <h4>File Uploads</h4>
                <p>When you upload capstone papers, abstracts, or profile pictures, these files are stored securely on our servers. Accepted file formats include PDF, DOC, DOCX, JPG, PNG, and GIF. Files are retained for the duration of your account and deleted upon account termination.</p>
            </div>
            
            <div class="policy-section">
                <h4>Session Management</h4>
                <p>We track active sessions to help you monitor where your account is logged in. You can view and terminate active sessions from your account settings. Session data includes device type, IP address, location, and last activity time.</p>
            </div>
            
            <div class="policy-section">
                <h4>Data Security</h4>
                <p>We implement appropriate security measures including password hashing, secure HTTPS connection, OTP verification, session tracking, and access controls to protect your personal information.</p>
            </div>
            
            <div class="policy-section">
                <h4>Your Rights</h4>
                <ul>
                    <li>Access and update your personal information</li>
                    <li>Request deletion of your account and data</li>
                    <li>View and manage active sessions</li>
                    <li>Opt out of email notifications</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h4>Contact Us</h4>
                <p>For questions about this privacy policy, please contact:</p>
                <p class="contact-email">kldcapstonetracker@gmail.com</p>
            </div>
        </div>
        
        <div class="modal-footer-custom">
            <button type="button" class="btn-cancel" onclick="closePrivacyModal()">Close</button>
            <button type="button" class="btn-save" onclick="closePrivacyModal()">
                <span class="material-symbols-outlined">check_circle</span>
                I Understand
            </button>
        </div>
    </div>
</div>

<!-- Terms of Service Modal -->
<div class="modal" id="termsModal">
    <div class="modal-content">
        <div class="modal-header-custom">
            <span class="material-symbols-outlined">gavel</span>
            <h3>Terms of Service</h3>
        </div>
        
        <div class="modal-body-custom">
            <div class="modal-date">Last updated: April 3, 2026</div>
            
            <div class="policy-section">
                <h4>Acceptance of Terms</h4>
                <p>By accessing or using KLD Capstone Tracker, you agree to be bound by these Terms of Service.</p>
            </div>
            
            <div class="policy-section">
                <h4>User Accounts</h4>
                <ul>
                    <li>Provide accurate and complete information</li>
                    <li>Keep your login credentials secure</li>
                    <li>Notify us of any unauthorized access</li>
                    <li>You are responsible for all activities under your account</li>
                    <li>Email verification (OTP) is required to activate your account</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h4>Account Security</h4>
                <ul>
                    <li>OTP verification codes are valid for 15 minutes</li>
                    <li>You can view and terminate active sessions from your account settings</li>
                    <li>Report any suspicious activity immediately</li>
                    <li>Use a strong, unique password for your account</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h4>Acceptable Use</h4>
                <ul>
                    <li>Submit only original work (no plagiarism)</li>
                    <li>Do not impersonate other users</li>
                    <li>Do not attempt to bypass security measures</li>
                    <li>Respect academic integrity policies</li>
                    <li>Do not upload malicious files or viruses</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h4>File Upload Guidelines</h4>
                <ul>
                    <li>Maximum file size: 25MB per upload</li>
                    <li>Allowed formats: PDF, DOC, DOCX, TXT, JPG, PNG, GIF</li>
                    <li>You retain ownership of your uploaded files</li>
                    <li>Files may be reviewed by advisers and administrators</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h4>Intellectual Property</h4>
                <p>You retain ownership of your capstone titles and uploaded papers. By submitting content, you grant us license to display and manage your submissions for academic purposes.</p>
            </div>
            
            <div class="policy-section">
                <h4>Session Management</h4>
                <p>You are responsible for managing your active sessions. Terminate sessions you don't recognize. We reserve the right to terminate suspicious sessions to protect your account.</p>
            </div>
            
            <div class="policy-section">
                <h4>Termination</h4>
                <p>We may terminate or suspend your account immediately, without prior notice, for conduct that violates these Terms or poses a security risk.</p>
            </div>
            
            <div class="policy-section">
                <h4>Changes to Terms</h4>
                <p>We reserve the right to modify these terms at any time. Continued use of the service after changes constitutes acceptance of the new terms.</p>
            </div>
            
            <div class="policy-section">
                <h4>Contact</h4>
                <p>For questions about these terms, contact the system administrator:</p>
                <p class="contact-email">kldcapstonetracker@gmail.com</p>
            </div>
        </div>
        
        <div class="modal-footer-custom">
            <button type="button" class="btn-cancel" onclick="closeTermsModal()">Close</button>
            <button type="button" class="btn-save" onclick="closeTermsModal()">
                <span class="material-symbols-outlined">check_circle</span>
                I Understand
            </button>
        </div>
    </div>
</div>

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

<style>
    /* Modal Styles - Matching Edit User Modal */
    .modal {
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

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        width: 100%;
        max-width: 600px;
        max-height: 85vh;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        animation: modalFadeIn 0.3s ease;
        display: flex;
        flex-direction: column;
        overflow: hidden;
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

    /* Modal Header - Green Gradient */
    .modal-header-custom {
        background: linear-gradient(135deg, var(--primary-color, #2D5A27) 0%, var(--primary-dark, #1e3d1a) 100%);
        padding: 20px 30px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: none;
    }

    .modal-header-custom .material-symbols-outlined {
        font-size: 28px;
        color: white;
        background: rgba(255, 255, 255, 0.2);
        padding: 8px;
        border-radius: 12px;
    }

    .modal-header-custom h3 {
        color: white;
        font-size: 1.4rem;
        font-weight: 600;
        margin: 0;
    }

    /* Modal Body */
    .modal-body-custom {
        padding: 25px 30px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-date {
        font-size: 0.8rem;
        color: #888;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2efdf;
    }

    /* Policy Sections */
    .policy-section {
        margin-bottom: 25px;
    }

    .policy-section h4 {
        color: var(--primary-dark, #1e3d1a);
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .policy-section h4::before {
        content: '';
        width: 4px;
        height: 18px;
        background: var(--primary-color, #2D5A27);
        border-radius: 2px;
    }

    .policy-section p {
        color: #555;
        font-size: 0.9rem;
        line-height: 1.6;
        margin: 0;
    }

    .policy-section ul {
        margin: 5px 0 0 20px;
        padding: 0;
    }

    .policy-section li {
        color: #555;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 6px;
    }

    .policy-section li strong {
        color: var(--primary-dark, #1e3d1a);
    }

    .contact-email {
        color: var(--primary-color, #2D5A27);
        font-weight: 500;
        margin-top: 5px;
    }

    /* Modal Footer */
    .modal-footer-custom {
        padding: 20px 30px;
        border-top: 1px solid #e2efdf;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        background: #fafbfa;
    }

    .modal-footer-custom .btn-cancel {
        padding: 10px 24px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: transparent;
        border: 2px solid #ccc;
        color: #666;
    }

    .modal-footer-custom .btn-cancel:hover {
        background: #f0f0f0;
        border-color: #999;
        transform: translateY(-2px);
    }

    .modal-footer-custom .btn-save {
        padding: 10px 28px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: var(--primary-color, #2D5A27);
        border: none;
        color: white;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .modal-footer-custom .btn-save:hover {
        background: var(--primary-dark, #1e3d1a);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(45, 90, 39, 0.3);
    }

    .modal-footer-custom .btn-save .material-symbols-outlined {
        font-size: 18px;
        transition: transform 0.3s ease;
    }

    .modal-footer-custom .btn-save:hover .material-symbols-outlined {
        transform: rotate(360deg);
    }

    /* Custom Scrollbar */
    .modal-body-custom::-webkit-scrollbar {
        width: 8px;
    }

    .modal-body-custom::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .modal-body-custom::-webkit-scrollbar-thumb {
        background: var(--primary-color, #2D5A27);
        border-radius: 10px;
    }

    .modal-body-custom::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark, #1e3d1a);
    }

    /* Responsive */
    @media (max-width: 600px) {
        .modal-content {
            max-width: 95%;
            max-height: 90vh;
        }

        .modal-header-custom {
            padding: 15px 20px;
        }

        .modal-header-custom h3 {
            font-size: 1.2rem;
        }

        .modal-header-custom .material-symbols-outlined {
            font-size: 24px;
            padding: 6px;
        }

        .modal-body-custom {
            padding: 20px;
        }

        .modal-footer-custom {
            padding: 15px 20px;
            flex-direction: column;
        }

        .modal-footer-custom .btn-cancel,
        .modal-footer-custom .btn-save {
            width: 100%;
            justify-content: center;
        }
    }
</style>