/**
 * Email Verification JavaScript
 * Handles verification code input and resend functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus on the code input
    const codeInput = document.getElementById('verification_code');
    if (codeInput) {
        codeInput.focus();
        
        // Auto-advance to next input (for 6-digit code entry)
        codeInput.addEventListener('input', function(e) {
            if (this.value.length === 6) {
                // Validate it's all numbers
                if (/^\d{6}$/.test(this.value)) {
                    this.closest('form').submit();
                }
            }
        });
        
        // Only allow numbers
        codeInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // Resend code button
    const resendBtn = document.getElementById('resend-code');
    if (resendBtn) {
        // Initialize countdown from data attribute or default to 0
        let resendCooldown = resendBtn.dataset.cooldown ? parseInt(resendBtn.dataset.cooldown) : 0;
        let resendTimer = null;
        
        // Function to update button state
        function updateResendButton() {
            if (resendCooldown > 0) {
                resendBtn.innerHTML = `<i class="fas fa-clock"></i> Resend in ${resendCooldown}s`;
                resendBtn.disabled = true;
                resendCooldown--;
            } else {
                resendBtn.innerHTML = '<i class="fas fa-redo"></i> Resend Code';
                resendBtn.disabled = false;
                if (resendTimer) {
                    clearInterval(resendTimer);
                    resendTimer = null;
                }
            }
        }
        
        // Start countdown if needed
        updateResendButton();
        if (resendCooldown > 0) {
            resendTimer = setInterval(updateResendButton, 1000);
        }
        
        resendBtn.addEventListener('click', function() {
            if (this.disabled) return;
            
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            this.disabled = true;
            
            // Send AJAX request to resend verification code
            fetch('send_verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'resend_code=1'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'New verification code sent to your email.', 'success');
                    
                    // Reset countdown to 60 seconds
                    resendCooldown = 60;
                    updateResendButton();
                    resendTimer = setInterval(updateResendButton, 1000);
                    
                    // Clear and focus on code input
                    if (codeInput) {
                        codeInput.value = '';
                        codeInput.focus();
                    }
                } else {
                    showNotification(data.message || 'Failed to send new code.', 'error');
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to send new code. Please try again.', 'error');
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
    }
    
    // Form submission loading state
    const verifyForm = document.querySelector('.verification-form');
    if (verifyForm) {
        verifyForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-verify');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Show notification function
    function showNotification(message, type = 'info') {
        // Remove any existing temporary notifications
        const existingNotifications = document.querySelectorAll('.notification.temporary');
        existingNotifications.forEach(notification => {
            notification.remove();
        });
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type} temporary`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
            ${message}
        `;
        
        // Insert after card header or at top of card body
        const cardBody = document.querySelector('.card-body');
        if (cardBody) {
            // Insert after any existing notifications
            const existingNotifications = cardBody.querySelectorAll('.notification:not(.temporary)');
            if (existingNotifications.length > 0) {
                existingNotifications[existingNotifications.length - 1].insertAdjacentElement('afterend', notification);
            } else {
                cardBody.insertBefore(notification, cardBody.firstChild);
            }
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    }
    
    // Make showNotification available globally
    window.showNotification = showNotification;
});