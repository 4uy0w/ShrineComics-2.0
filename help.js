// help.js

document.addEventListener('DOMContentLoaded', function() {
    // FAQ Toggle Functionality
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        
        question.addEventListener('click', () => {
            // Close all other FAQ items
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Toggle current item
            item.classList.toggle('active');
        });
    });

    // Help Category Cards Interaction
    const helpCategories = document.querySelectorAll('.help-category-card');
    
    helpCategories.forEach(card => {
        card.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            showCategoryArticles(category);
        });
    });

    // Contact Form Handling
    const supportForm = document.getElementById('supportForm');
    
    if (supportForm) {
        supportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const subject = formData.get('subject');
            const message = formData.get('message');
            
            // Simple validation
            if (!subject || !message) {
                showNotification('Harap isi semua field yang wajib diisi', 'error');
                return;
            }
            
            // Simulate form submission
            simulateFormSubmission(formData);
        });
    }

    // Search Functionality (for future implementation)
    initializeSearch();
    
    console.log('Help page JavaScript loaded successfully');
});

// Show articles for specific category
function showCategoryArticles(category) {
    const categoryTitles = {
        'komik': 'Manajemen Komik',
        'chapter': 'Manajemen Chapter', 
        'monetization': 'Monetisasi & Penghasilan',
        'account': 'Akun & Profile'
    };
    
    const message = `Fitur pencarian artikel untuk "${categoryTitles[category]}" akan segera tersedia. Untuk sementara, silakan lihat FAQ di bawah atau hubungi support.`;
    showNotification(message, 'info');
}

// Simulate form submission
function simulateFormSubmission(formData) {
    const submitBtn = document.querySelector('#supportForm button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<span class="btn-icon">⏳</span>Mengirim...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Show success message
        showNotification('Pertanyaan Anda telah dikirim! Tim support akan membalas dalam 24 jam.', 'success');
        
        // Reset form
        document.getElementById('supportForm').reset();
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        // Log form data (in real app, this would be sent to server)
        console.log('Form data:', {
            subject: formData.get('subject'),
            message: formData.get('message'),
            attachment: formData.get('attachment') ? 'File attached' : 'No file'
        });
    }, 2000);
}

// Initialize search functionality
function initializeSearch() {
    // This would be implemented when search feature is added
    console.log('Search functionality ready for implementation');
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        'success': '✅',
        'error': '❌', 
        'info': 'ℹ️',
        'warning': '⚠️'
    };
    
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${icons[type] || icons.info}</span>
            <span class="notification-message">${message}</span>
        </div>
    `;
    
    // Styles for notification
    const styles = {
        'success': { bg: '#d4edda', color: '#155724', border: '#28a745' },
        'error': { bg: '#f8d7da', color: '#721c24', border: '#dc3545' },
        'info': { bg: '#d1ecf1', color: '#0c5460', border: '#17a2b8' },
        'warning': { bg: '#fff3cd', color: '#856404', border: '#ffc107' }
    };
    
    const style = styles[type] || styles.info;
    
    notification.style.cssText = `
        position: fixed;
        top: 25px;
        right: 25px;
        background: ${style.bg};
        color: ${style.color};
        padding: 1.25rem 1.75rem;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        z-index: 10000;
        border-left: 5px solid ${style.border};
        transform: translateX(400px);
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        max-width: 450px;
        font-weight: 500;
        backdrop-filter: blur(10px);
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 400);
    }, 5000);
    
    // Close on click
    notification.addEventListener('click', () => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 400);
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + / to focus search (when implemented)
    if (e.ctrlKey && e.key === '/') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Escape to close modals or notifications
    if (e.key === 'Escape') {
        const notification = document.querySelector('.notification');
        if (notification) {
            notification.remove();
        }
    }
});

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showCategoryArticles,
        simulateFormSubmission,
        showNotification
    };
}