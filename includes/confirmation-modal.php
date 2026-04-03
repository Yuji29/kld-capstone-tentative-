<?php
?>
<!-- Confirmation Modal Component -->
<div id="confirmation-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <span class="material-symbols-outlined modal-icon" id="modal-icon">warning</span>
            <h3 id="modal-title">Confirm Action</h3>
        </div> 
        <div class="modal-body">
            <p id="modal-message">Are you sure you want to proceed?</p>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-cancel" id="modal-cancel">Cancel</button>
            <button class="modal-btn modal-confirm" id="modal-confirm">Confirm</button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-container {
    background: white;
    border-radius: 30px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.4s ease;
    overflow: hidden;
    border: 1px solid #e2efdf;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 25px 30px 15px;
    text-align: center;
}

.modal-icon {
    font-size: 60px;
    margin-bottom: 10px;
    display: inline-block;
}

.modal-header h3 {
    color: var(--primary-dark, #1e3d1a);
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-body {
    padding: 0 30px 20px;
    text-align: center;
}

.modal-body p {
    color: #666;
    font-size: 1rem;
    line-height: 1.6;
}

.modal-footer {
    padding: 20px 30px 30px;
    display: flex;
    gap: 15px;
    justify-content: center;
    border-top: 1px solid #e2efdf;
}

.modal-btn {
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    flex: 1;
}

.modal-cancel {
    background: #e9ecf0;
    color: #4a5a6e;
}

.modal-cancel:hover {
    background: #d5dae0;
    transform: translateY(-2px);
}

.modal-confirm {
    background: #dc3545;
    color: white;
}

.modal-confirm:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
}

.modal-confirm.delete {
    background: #dc3545;
}

.modal-confirm.submit {
    background: var(--primary-color, #2D5A27);
}

.modal-confirm.submit:hover {
    background: var(--primary-dark, #1e3d1a);
}

/* Mobile Responsive */
@media (max-width: 500px) {
    .modal-container {
        width: 95%;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-btn {
        width: 100%;
    }
}
</style>

<script>
// Store callback globally
let modalConfirmCallback = null;
let modalConfirmUrl = null;

// Modal functionality
function showConfirmationModal(options) {
    const modal = document.getElementById('confirmation-modal');
    const titleEl = document.getElementById('modal-title');
    const messageEl = document.getElementById('modal-message');
    const confirmBtn = document.getElementById('modal-confirm');
    const iconEl = document.getElementById('modal-icon');
    
    // Reset callback
    modalConfirmCallback = null;
    modalConfirmUrl = null;
    
    // Set default values
    titleEl.textContent = options.title || 'Confirm Action';
    messageEl.innerHTML = options.message || 'Are you sure you want to proceed?';
    confirmBtn.textContent = options.confirmText || 'Confirm';
    
    // Store callback or URL
    if (options.onConfirm && typeof options.onConfirm === 'function') {
        modalConfirmCallback = options.onConfirm;
        modalConfirmUrl = null;
    } else if (options.confirmUrl) {
        modalConfirmUrl = options.confirmUrl;
        modalConfirmCallback = null;
    }
    
    // Set icon based on type
    if (options.type === 'delete') {
        iconEl.textContent = 'delete';
        iconEl.style.color = '#dc3545';
        confirmBtn.classList.add('delete');
        confirmBtn.classList.remove('submit');
    } else if (options.type === 'submit') {
        iconEl.textContent = 'send';
        iconEl.style.color = '#2D5A27';
        confirmBtn.classList.add('submit');
        confirmBtn.classList.remove('delete');
    } else if (options.type === 'warning') {
        iconEl.textContent = 'warning';
        iconEl.style.color = '#ffc107';
    } else {
        iconEl.textContent = 'help';
        iconEl.style.color = '#2D5A27';
    }
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Close when clicking outside
    modal.onclick = function(e) {
        if (e.target === modal) {
            closeModal();
        }
    };
}

function closeModal() {
    const modal = document.getElementById('confirmation-modal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    modalConfirmCallback = null;
    modalConfirmUrl = null;
}

// Confirm button handler
document.getElementById('modal-confirm').addEventListener('click', function(e) {
    e.preventDefault();
    
    if (modalConfirmCallback) {
        modalConfirmCallback();
    } else if (modalConfirmUrl) {
        window.location.href = modalConfirmUrl;
    }
    
    closeModal();
});

// Cancel button handler
document.getElementById('modal-cancel').addEventListener('click', function(e) {
    e.preventDefault();
    closeModal();
});

// Close with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>