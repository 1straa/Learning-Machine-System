// Modal Optimization JavaScript
// Add this to responsive_dashboard.js or create a new modal_optimization.js file

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // MODAL OPTIMIZATION
    // ========================================
    
    // Function to center and optimize modal
    function optimizeModal(modal) {
        const modalDialog = modal.querySelector('.modal-dialog');
        if (!modalDialog) return;
        
        // Add centered class if not present
        if (!modalDialog.classList.contains('modal-dialog-centered')) {
            modalDialog.classList.add('modal-dialog-centered');
        }
        
        // Calculate optimal max-height for modal body
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            const windowHeight = window.innerHeight;
            const maxHeight = Math.min(windowHeight * 0.7, 600);
            modalBody.style.maxHeight = maxHeight + 'px';
        }
    }
    
    // Optimize all modals on page load
    const allModals = document.querySelectorAll('.modal');
    allModals.forEach(modal => {
        optimizeModal(modal);
        
        // Re-optimize when modal is shown
        modal.addEventListener('show.bs.modal', function() {
            optimizeModal(this);
            document.body.style.overflow = 'hidden';
        });
        
        // Cleanup when modal is hidden
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.style.overflow = '';
        });
    });
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const visibleModal = document.querySelector('.modal.show');
            if (visibleModal) {
                optimizeModal(visibleModal);
            }
        }, 250);
    });
    
    // ========================================
    // MODAL FORM ENHANCEMENTS
    // ========================================
    
    // Auto-focus first input in modal
    allModals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const firstInput = this.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        });
    });
    
    // Prevent modal from closing when clicking inside modal-content
    allModals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                // Clicked on backdrop
                const bsModal = bootstrap.Modal.getInstance(this);
                if (bsModal) {
                    bsModal.hide();
                }
            }
        });
    });
    
    // ========================================
    // SMOOTH SCROLLING IN MODALS
    // ========================================
    
    // Add smooth scrolling to modal bodies
    const modalBodies = document.querySelectorAll('.modal-body');
    modalBodies.forEach(body => {
        body.style.scrollBehavior = 'smooth';
    });
    
    // ========================================
    // KEYBOARD NAVIGATION
    // ========================================
    
    document.addEventListener('keydown', function(e) {
        // Close modal on Escape
        if (e.key === 'Escape') {
            const visibleModal = document.querySelector('.modal.show');
            if (visibleModal) {
                const bsModal = bootstrap.Modal.getInstance(visibleModal);
                if (bsModal) {
                    bsModal.hide();
                }
            }
        }
    });
    
    // ========================================
    // MODAL LOADING STATE
    // ========================================
    
    window.showModalLoading = function(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            const loadingHTML = `
                <div class="text-center py-5 modal-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading...</p>
                </div>
            `;
            modalBody.innerHTML = loadingHTML;
        }
    };
    
    window.hideModalLoading = function(modalId, content) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            const loadingDiv = modalBody.querySelector('.modal-loading');
            if (loadingDiv) {
                if (content) {
                    modalBody.innerHTML = content;
                } else {
                    loadingDiv.remove();
                }
            }
        }
    };
    
    // ========================================
    // FORM VALIDATION IN MODALS
    // ========================================
    
    const modalForms = document.querySelectorAll('.modal form');
    modalForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Add error feedback if not exists
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'This field is required.';
                        field.parentNode.insertBefore(feedback, field.nextSibling);
                    }
                } else {
                    field.classList.remove('is-invalid');
                    const feedback = field.nextElementSibling;
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                const firstError = this.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            }
        });
        
        // Clear validation on input
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const feedback = this.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.remove();
                }
            });
        });
    });
    
    // ========================================
    // MODAL ANIMATION IMPROVEMENTS
    // ========================================
    
    allModals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
            this.style.display = 'flex';
            setTimeout(() => {
                this.classList.add('show');
            }, 10);
        });
    });
    
    // ========================================
    // PREVENT BODY SCROLL WHEN MODAL IS OPEN
    // ========================================
    
    let scrollPosition = 0;
    
    allModals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
            scrollPosition = window.pageYOffset;
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.top = `-${scrollPosition}px`;
            document.body.style.width = '100%';
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('position');
            document.body.style.removeProperty('top');
            document.body.style.removeProperty('width');
            window.scrollTo(0, scrollPosition);
        });
    });
    
    // ========================================
    // RESPONSIVE MODAL ADJUSTMENTS
    // ========================================
    
    function adjustModalForScreenSize() {
        const screenWidth = window.innerWidth;
        
        allModals.forEach(modal => {
            const modalDialog = modal.querySelector('.modal-dialog');
            if (!modalDialog) return;
            
            if (screenWidth < 576) {
                // Extra small screens
                modalDialog.style.margin = '0.5rem';
                modalDialog.style.maxWidth = 'calc(100% - 1rem)';
            } else if (screenWidth < 768) {
                // Small screens
                modalDialog.style.margin = '1rem auto';
                modalDialog.style.maxWidth = '95%';
            } else {
                // Normal screens - use CSS defaults
                modalDialog.style.margin = '';
                modalDialog.style.maxWidth = '';
            }
        });
    }
    
    // Initial adjustment
    adjustModalForScreenSize();
    
    // Adjust on resize
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(adjustModalForScreenSize, 250);
    });
    
    console.log('Modal optimization initialized successfully');
});