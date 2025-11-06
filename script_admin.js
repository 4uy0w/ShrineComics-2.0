// admin_script.js - Simple and Clean

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initAdminPage();
});

function initAdminPage() {
    console.log('Admin dashboard initialized');
    
    // Add click handlers for action cards
    setupActionCards();
    
    // Add any other initialization here
}

function setupActionCards() {
    const actionCards = document.querySelectorAll('.action-card');
    
    actionCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // If link is #, prevent default and show notification
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
                showNotification('Fitur sedang dalam pengembangan!', 'info');
            }
        });
    });
}

// Simple notification system
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 0.375rem;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        background: ${getNotificationColor(type)};
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function getNotificationColor(type) {
    const colors = {
        'info': '#007bff',
        'success': '#28a745',
        'warning': '#ffc107',
        'error': '#dc3545'
    };
    return colors[type] || colors['info'];
}

// Add CSS for notification animation
const notificationStyles = `
@keyframes slideIn {
    from { 
        transform: translateX(100%); 
        opacity: 0; 
    }
    to { 
        transform: translateX(0); 
        opacity: 1; 
    }
}
`;

// Inject styles if not already present
if (!document.querySelector('#notification-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'notification-styles';
    styleSheet.textContent = notificationStyles;
    document.head.appendChild(styleSheet);
}

// Utility functions
const AdminUtils = {
    // Format number with commas
    formatNumber: function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    },
    
    // Format date to Indonesian format
    formatDate: function(dateString) {
        const options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        };
        return new Date(dateString).toLocaleDateString('id-ID', options);
    },
    
    // Show loading state
    showLoading: function(element) {
        element.disabled = true;
        element.innerHTML = 'Loading...';
    },
    
    // Hide loading state
    hideLoading: function(element, originalText) {
        element.disabled = false;
        element.innerHTML = originalText;
    }
};
// Management Page Functions
function openAddPointsModal(userId, username, currentPoints) {
    const modal = document.getElementById('pointsModal');
    const form = document.getElementById('pointsForm');
    
    // Set values
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_username').value = username;
    document.getElementById('modal_current_points').value = currentPoints;
    document.getElementById('points').value = '';
    document.getElementById('modal_new_points').value = currentPoints;
    
    // Show modal
    modal.style.display = 'block';
    
    // Add event listener for points input
    document.getElementById('points').addEventListener('input', function() {
        const pointsToAdd = parseInt(this.value) || 0;
        const newTotal = currentPoints + pointsToAdd;
        document.getElementById('modal_new_points').value = newTotal;
    });
}

function closeAddPointsModal() {
    const modal = document.getElementById('pointsModal');
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('pointsModal');
    if (event.target === modal) {
        closeAddPointsModal();
    }
});

// Initialize management page
function initManagementPage() {
    console.log('Management page initialized');
    
    // Add any management-specific initialization here
}

// Auto-initialize based on page
if (window.location.pathname.includes('admin_management.php')) {
    document.addEventListener('DOMContentLoaded', initManagementPage);
}