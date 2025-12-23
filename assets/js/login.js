/**
 * Login Page JavaScript
 * Handles AJAX login form submission
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Login page loaded');
    
    const loginForm = document.getElementById('login-form');
    const loginBtn = document.getElementById('login-btn');
    const loginError = document.getElementById('login-error');
    
    if (!loginForm || !loginBtn) {
        console.error('Login form elements not found');
        return;
    }
    
    // AJAX Login
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Login form submitted');
        
        // Clear previous error
        if (loginError) {
            loginError.style.display = 'none';
            loginError.textContent = '';
        }
        
        // Get form data
        const formData = new FormData(this);
        
        // Show loading state
        const originalText = loginBtn.textContent;
        loginBtn.textContent = '';
        loginBtn.classList.add('auth-btn-loading');
        loginBtn.disabled = true;
        
        // Add loading indicator
        const spinner = document.createElement('span');
        spinner.className = 'auth-btn-spinner';
        spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        loginBtn.appendChild(spinner);
        
        // Send AJAX request
        fetch('login.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Response received');
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Login response:', data);
            
            // Reset button
            resetLoginButton(originalText);
            
            if (data.success) {
                // Show success toast
                showToast(data.message || 'Login successful!', 'success');
                
                // If needs verification, show additional message
                if (data.needs_verification) {
                    setTimeout(() => {
                        showToast('Please verify your email to access all features.', 'warning');
                    }, 1500);
                }
                
                // Redirect after delay
                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard.php';
                }, 1000);
            } else {
                // Show error
                const errorMsg = data.message || 'Login failed. Please check your credentials.';
                showToast(errorMsg, 'error');
                
                // Also show in error div
                if (loginError) {
                    loginError.textContent = errorMsg;
                    loginError.style.display = 'block';
                }
            }
        })
        .catch(error => {
            // Reset button
            resetLoginButton(originalText);
            
            console.error('Login fetch error:', error);
            showToast('Login failed. Please check your connection and try again.', 'error');
            
            // Show error in error div
            if (loginError) {
                loginError.textContent = 'Network error. Please check your connection.';
                loginError.style.display = 'block';
            }
        });
    });
    
    // Function to reset login button to original state
    function resetLoginButton(originalText) {
        if (!loginBtn) return;
        
        loginBtn.textContent = originalText;
        loginBtn.classList.remove('auth-btn-loading');
        loginBtn.disabled = false;
        
        // Remove spinner if it exists
        const spinner = loginBtn.querySelector('.auth-btn-spinner');
        if (spinner) {
            spinner.remove();
        }
    }
    
    // Add keyboard shortcut (Enter key on password field submits form)
    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                loginForm.requestSubmit();
            }
        });
    }
    
    // Auto-focus on username field if empty
    const usernameField = document.getElementById('username');
    if (usernameField && !usernameField.value.trim()) {
        setTimeout(() => {
            usernameField.focus();
        }, 100);
    }
    
    // Toggle password visibility (optional feature)
    addPasswordToggle();
});

/**
 * Adds password visibility toggle functionality
 */
function addPasswordToggle() {
    const passwordField = document.getElementById('password');
    if (!passwordField) return;
    
    // Create toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'password-toggle';
    toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
    toggleBtn.setAttribute('aria-label', 'Show password');
    
    // Insert after password field
    passwordField.parentNode.style.position = 'relative';
    passwordField.parentNode.appendChild(toggleBtn);
    
    // Toggle password visibility
    toggleBtn.addEventListener('click', function() {
        const isPassword = passwordField.type === 'password';
        passwordField.type = isPassword ? 'text' : 'password';
        this.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
}

// Make functions available globally if needed
window.resetLoginButton = function(originalText) {
    const loginBtn = document.getElementById('login-btn');
    if (!loginBtn) return;
    
    loginBtn.textContent = originalText;
    loginBtn.classList.remove('auth-btn-loading');
    loginBtn.disabled = false;
    
    const spinner = loginBtn.querySelector('.auth-btn-spinner');
    if (spinner) {
        spinner.remove();
    }
};