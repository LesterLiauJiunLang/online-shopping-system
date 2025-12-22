/**
 * Admin Users Management JavaScript
 * Handles AJAX actions for member management with clear feedback
 */

// Create a global event listener using event delegation
document.addEventListener('click', function(e) {
    // Check if the clicked element or its parent has the ajax-action-btn class
    const button = e.target.closest('.ajax-action-btn');
    if (button) {
        e.preventDefault();
        e.stopPropagation();
        
        const action = button.getAttribute('data-action');
        const userId = button.getAttribute('data-user-id');
        const row = document.getElementById('member-row-' + userId);
        const statusCell = document.getElementById('status-' + userId);
        const verificationCell = document.getElementById('verification-' + userId);
        
        if (!action || !userId) return;
        
        // Confirm action
        let confirmMessage = '';
        switch(action) {
            case 'activate':
                confirmMessage = 'Are you sure you want to activate this member\'s account?';
                break;
            case 'deactivate':
                confirmMessage = 'Are you sure you want to deactivate this member\'s account?';
                break;
            case 'delete':
                confirmMessage = 'WARNING: This will permanently delete the member. This action cannot be undone!\n\nAre you absolutely sure?';
                break;
            case 'resend_verification':
                confirmMessage = 'Resend verification email to this member?';
                break;
            default:
                confirmMessage = 'Are you sure?';
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Show loading overlay
        const ajaxLoading = document.getElementById('ajaxLoading');
        if (ajaxLoading) {
            ajaxLoading.style.display = 'flex';
        }
        
        // Disable button during processing
        button.disabled = true;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Processing...</span>';
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                          window.currentCsrfToken || 
                          '';
        
        // Prepare form data
        const formData = new FormData();
        formData.append('ajax_action', action);
        formData.append('user_id', userId);
        formData.append('csrf_token', csrfToken);
        
        console.log('Sending request with:', { action, userId, csrfToken: csrfToken.substring(0, 10) + '...' });
        
        // Send AJAX request
        fetch('users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Response:', data);
            
            // Show notification
            if (typeof showToast === 'function') {
                showToast(data.message, data.type || 'success');
            } else {
                alert(data.message);
            }
            
            if (data.success) {
                // Update UI based on action
                switch(action) {
                    case 'activate':
                        // Update button to deactivate
                        const deactivateBtn = document.createElement('button');
                        deactivateBtn.className = 'action-btn deactivate-btn ajax-action-btn';
                        deactivateBtn.setAttribute('data-action', 'deactivate');
                        deactivateBtn.setAttribute('data-user-id', userId);
                        deactivateBtn.setAttribute('title', 'Deactivate Member Account');
                        deactivateBtn.innerHTML = '<i class="fas fa-user-slash"></i><span>Deactivate</span>';
                        
                        // Update status badge
                        if (statusCell) {
                            statusCell.innerHTML = `
                                <span class="status-badge active">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Active</span>
                                </span>
                            `;
                        }
                        
                        // Remove inactive class from row
                        if (row) {
                            row.classList.remove('inactive');
                        }
                        
                        // Hide delete button (since active members can't be deleted)
                        const deleteBtn = row.querySelector('.delete-btn');
                        if (deleteBtn) {
                            deleteBtn.style.display = 'none';
                        }
                        
                        // Replace button
                        button.parentNode.replaceChild(deactivateBtn, button);
                        break;
                        
                    case 'deactivate':
                        // Update button to activate
                        const activateBtn = document.createElement('button');
                        activateBtn.className = 'action-btn activate-btn ajax-action-btn';
                        activateBtn.setAttribute('data-action', 'activate');
                        activateBtn.setAttribute('data-user-id', userId);
                        activateBtn.setAttribute('title', 'Activate Member Account');
                        activateBtn.innerHTML = '<i class="fas fa-user-check"></i><span>Activate</span>';
                        
                        // Update status badge
                        if (statusCell) {
                            statusCell.innerHTML = `
                                <span class="status-badge inactive">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Inactive</span>
                                </span>
                            `;
                        }
                        
                        // Add inactive class to row
                        if (row) {
                            row.classList.add('inactive');
                        }
                        
                        // Show delete button for inactive users
                        const actionButtons = row.querySelector('.action-buttons');
                        if (actionButtons && !row.querySelector('.delete-btn')) {
                            const newDeleteBtn = document.createElement('button');
                            newDeleteBtn.className = 'action-btn delete-btn ajax-action-btn';
                            newDeleteBtn.setAttribute('data-action', 'delete');
                            newDeleteBtn.setAttribute('data-user-id', userId);
                            newDeleteBtn.setAttribute('title', 'Delete Member Permanently');
                            newDeleteBtn.innerHTML = '<i class="fas fa-trash"></i><span>Delete</span>';
                            actionButtons.appendChild(newDeleteBtn);
                        }
                        
                        // Replace button
                        button.parentNode.replaceChild(activateBtn, button);
                        break;
                        
                    case 'delete':
                        // Remove row from table
                        if (row) {
                            row.style.transition = 'all 0.3s';
                            row.style.opacity = '0';
                            row.style.height = '0';
                            row.style.overflow = 'hidden';
                            
                            setTimeout(() => {
                                row.remove();
                                
                                // Update total count
                                const totalCount = document.querySelector('.total-count');
                                if (totalCount) {
                                    const currentText = totalCount.textContent;
                                    const match = currentText.match(/Showing (\d+) of (\d+) members/);
                                    if (match) {
                                        const showing = parseInt(match[1]) - 1;
                                        const total = parseInt(match[2]) - 1;
                                        totalCount.textContent = `Showing ${showing} of ${total} members`;
                                    }
                                }
                            }, 300);
                        }
                        break;
                        
                    case 'resend_verification':
                        // Extract code from message if present
                        const codeMatch = data.message.match(/\b\d{6}\b/);
                        if (codeMatch && verificationCell) {
                            const existingCode = verificationCell.querySelector('.verification-code');
                            if (existingCode) {
                                existingCode.textContent = `Code: ${codeMatch[0]}`;
                            } else {
                                const codeDiv = document.createElement('div');
                                codeDiv.className = 'verification-code';
                                codeDiv.textContent = `Code: ${codeMatch[0]}`;
                                verificationCell.querySelector('.verification-badge').appendChild(codeDiv);
                            }
                        }
                        // Restore original button state
                        button.disabled = false;
                        button.innerHTML = originalHTML;
                        break;
                }
            } else {
                // If action failed, restore button state
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('An error occurred. Please try again.', 'error');
            } else {
                alert('An error occurred. Please try again.');
            }
            // Restore button state on error
            button.disabled = false;
            button.innerHTML = originalHTML;
        })
        .finally(() => {
            // Hide loading overlay
            const ajaxLoading = document.getElementById('ajaxLoading');
            if (ajaxLoading) {
                ajaxLoading.style.display = 'none';
            }
        });
    }
});

// This part handles the initial page load and other non-ajax functionality
document.addEventListener('DOMContentLoaded', function() {
    // Remove filter tags
    document.querySelectorAll('.tag-remove').forEach(removeBtn => {
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = this.getAttribute('href');
        });
    });
    
    // Search input debounce
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 800);
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                searchInput.closest('form').submit();
            }
        }
    });
});

// If toast function is not defined, create a simple one
if (typeof showToast === 'undefined') {
    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.setAttribute('data-type', type);
        toast.innerHTML = `
            <div class="notification-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</div>
            <div class="notification-message">${message}</div>
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Add to body
        document.body.appendChild(toast);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5000);
    }
}