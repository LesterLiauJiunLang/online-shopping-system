/**
 * Notification System
 * Version 2.0 - Improved with better positioning and queue system
 */

class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.container = null;
        this.init();
    }

    init() {
        // Create notification container
        this.container = document.createElement('div');
        this.container.className = 'notification-container';
        document.body.appendChild(this.container);

        // Auto-clean old notifications
        this.autoClean();
    }

    show(message, type = 'success', duration = 5000) {
        const id = Date.now() + Math.random();
        const notification = {
            id,
            message,
            type,
            duration,
            element: null
        };

        this.notifications.push(notification);
        this.render(notification);

        // Auto remove after duration
        setTimeout(() => {
            this.remove(id);
        }, duration);

        return id;
    }

    render(notification) {
        const { id, message, type, duration } = notification;

        // Get icon based on type
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ⓘ'
        };

        const icon = icons[type] || icons.info;

        // Create notification element
        const element = document.createElement('div');
        element.className = 'notification-toast';
        element.setAttribute('data-type', type);
        element.setAttribute('data-id', id);
        
        // Calculate remaining time
        const remainingSeconds = Math.ceil(duration / 1000);
        
        element.innerHTML = `
            <span class="notification-icon">${icon}</span>
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <span class="notification-time">${remainingSeconds}s</span>
            </div>
            <button type="button" class="notification-close" aria-label="Close notification">
                <span aria-hidden="true">×</span>
            </button>
            <div class="notification-progress">
                <div class="notification-progress-bar" style="animation-duration: ${duration}ms"></div>
            </div>
        `;

        // Add to container
        this.container.appendChild(element);
        notification.element = element;

        // Add event listener for close button
        const closeBtn = element.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => this.remove(id));

        // Update time counter
        this.updateTimeCounter(element, duration);

        // Add hover pause
        element.addEventListener('mouseenter', () => {
            element.classList.add('paused');
            const progressBar = element.querySelector('.notification-progress-bar');
            if (progressBar) {
                progressBar.style.animationPlayState = 'paused';
            }
        });

        element.addEventListener('mouseleave', () => {
            element.classList.remove('paused');
            const progressBar = element.querySelector('.notification-progress-bar');
            if (progressBar) {
                progressBar.style.animationPlayState = 'running';
            }
        });

        // Animate in
        setTimeout(() => {
            element.classList.add('show');
        }, 10);

        return element;
    }

    updateTimeCounter(element, duration) {
        const timeElement = element.querySelector('.notification-time');
        if (!timeElement) return;

        const startTime = Date.now();
        const endTime = startTime + duration;

        const update = () => {
            if (!element.parentNode) return;

            const now = Date.now();
            const remaining = Math.max(0, endTime - now);
            const seconds = Math.ceil(remaining / 1000);

            timeElement.textContent = `${seconds}s`;

            if (remaining > 0) {
                requestAnimationFrame(update);
            }
        };

        requestAnimationFrame(update);
    }

    remove(id) {
        const index = this.notifications.findIndex(n => n.id === id);
        if (index === -1) return;

        const notification = this.notifications[index];
        
        if (notification.element) {
            // Animate out
            notification.element.classList.remove('show');
            notification.element.classList.add('hiding');

            // Remove from DOM after animation
            setTimeout(() => {
                if (notification.element && notification.element.parentNode) {
                    notification.element.parentNode.removeChild(notification.element);
                }
            }, 300);
        }

        // Remove from array
        this.notifications.splice(index, 1);
    }

    removeAll() {
        this.notifications.forEach(notification => {
            if (notification.element && notification.element.parentNode) {
                notification.element.parentNode.removeChild(notification.element);
            }
        });
        this.notifications = [];
    }

    autoClean() {
        // Clean up notifications older than 10 minutes
        setInterval(() => {
            const now = Date.now();
            this.notifications = this.notifications.filter(notification => {
                const isOld = (now - notification.id) > 600000; // 10 minutes
                if (isOld && notification.element) {
                    notification.element.remove();
                }
                return !isOld;
            });
        }, 60000); // Check every minute
    }
}

// Initialize and export
let notificationSystem = null;

function initNotifications() {
    if (!notificationSystem) {
        notificationSystem = new NotificationSystem();
    }
    return notificationSystem;
}

// Global showToast function (backward compatible)
function showToast(message, type = 'success', duration = 5000) {
    if (!notificationSystem) {
        initNotifications();
    }
    return notificationSystem.show(message, type, duration);
}

// Export for global use
window.showToast = showToast;
window.initNotifications = initNotifications;
window.NotificationSystem = NotificationSystem;

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotifications);
} else {
    initNotifications();
}