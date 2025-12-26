/**
 * Login Page JavaScript - COMPLETE FIX
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const loginBtn = document.getElementById('login-btn');
    const loginError = document.getElementById('login-error');
    
    if (!loginForm || !loginBtn) {
        console.error('Login form or button not found');
        return;
    }
    
    // Initialize notifications if available
    if (typeof initNotifications === 'function') {
        initNotifications();
    }
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Clear previous error
        if (loginError) {
            loginError.style.display = 'none';
            loginError.textContent = '';
        }
        
        // Show loading state
        const originalText = loginBtn.innerHTML;
        loginBtn.innerHTML = 'Logging in...';
        loginBtn.disabled = true;
        
        // Get form data
        const formData = new FormData(this);
        
        // Send AJAX request
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                // Not JSON - read as text
                return response.text().then(text => {
                    console.log('Server returned (first 200 chars):', text.substring(0, 200));
                    
                    // Try to parse anyway (sometimes content-type is wrong)
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        // If login succeeded, server might have redirected
                        if (response.ok || response.redirected) {
                            return { 
                                success: true, 
                                redirect: 'memberDashboard.php',
                                message: 'Login successful (redirected)'
                            };
                        }
                        throw new Error('Server returned invalid response');
                    }
                });
            }
        })
        .then(data => {
            console.log('Login response:', data);
            
            // Reset button
            loginBtn.innerHTML = originalText;
            loginBtn.disabled = false;
            
            if (data.success) {
                console.log('Login successful! Redirecting to:', data.redirect);
                
                // Show success message
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Login successful!', 'success', 2000);
                }
                
                // Redirect after short delay
                setTimeout(() => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.href = 'memberDashboard.php';
                    }
                }, 1500);
                
            } else {
                // Show error message
                const errorMsg = data.message || 'Login failed. Please check your credentials.';
                console.error('Login failed:', errorMsg);
                
                // Show in error div
                if (loginError) {
                    loginError.textContent = errorMsg;
                    loginError.style.display = 'block';
                }
                
                // Show toast notification
                if (typeof showToast === 'function') {
                    showToast(errorMsg, 'error', 5000);
                } else {
                    alert(errorMsg);
                }
            }
        })
        .catch(error => {
            console.error('Login error:', error);
            
            // Reset button
            loginBtn.innerHTML = originalText;
            loginBtn.disabled = false;
            
            // Determine error message
            let userMessage = 'Login error. Please try again.';
            
            if (error.message.includes('JSON') || error.message.includes('invalid')) {
                userMessage = 'Server error. Please try traditional login.';
                
                // Fallback to traditional form submission
                setTimeout(() => {
                    console.log('Falling back to traditional form submission');
                    loginForm.removeEventListener('submit', arguments.callee);
                    loginForm.submit();
                }, 2000);
            }
            
            // Show error
            if (loginError) {
                loginError.textContent = userMessage;
                loginError.style.display = 'block';
            }
            
            if (typeof showToast === 'function') {
                showToast(userMessage, 'error', 5000);
            }
        });
    });
});