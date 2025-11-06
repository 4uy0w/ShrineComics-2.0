// Tab Navigation
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.nav-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Add active class to current button and pane
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });
    
    // Comic card hover effects
    const comicCards = document.querySelectorAll('.comic-card');
    comicCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Load more functionality (placeholder)
    const loadMoreButtons = document.querySelectorAll('.load-more');
    loadMoreButtons.forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            alert(`Loading more ${target}...`);
            // Implement AJAX load more functionality here
        });
    });
    
    // Edit profile button functionality
    const editProfileBtn = document.querySelector('.btn-primary');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function() {
            alert('Edit profile functionality would open a modal or redirect to edit page');
            // Implement edit profile modal or redirect
        });
    }
    
    // Settings button functionality
    const settingsBtn = document.querySelector('.btn-secondary');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', function() {
            alert('Settings functionality would open settings page');
            // Implement settings redirect
        });
    }
    
    // Add smooth transitions
    const style = document.createElement('style');
    style.textContent = `
        .comic-card, .transaction-item, .comment-item, .info-group {
            transition: all 0.3s ease;
        }
        
        .tab-pane {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
});

// Additional utility functions
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}

// Export functions for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { formatDate, truncateText };
}
// Tab Navigation
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.nav-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Add active class to current button and pane
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });
    
    // Comic card hover effects
    const comicCards = document.querySelectorAll('.comic-card');
    comicCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Read Again button functionality
    const readAgainButtons = document.querySelectorAll('.btn-read-again');
    readAgainButtons.forEach(button => {
        button.addEventListener('click', function() {
            const comicId = this.getAttribute('data-comic-id');
            const comicTitle = this.closest('.comic-card').querySelector('.comic-title').textContent;
            
            // Tampilkan konfirmasi atau langsung redirect
            if (confirm(`Lanjutkan membaca "${comicTitle}"?`)) {
                // Redirect ke halaman baca komik
                window.location.href = `comic_detail.php?comic_id=${comicId}`;
            }
        });
    });
    
    // Load more functionality (placeholder)
    const loadMoreButtons = document.querySelectorAll('.load-more');
    loadMoreButtons.forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            alert(`Loading more ${target}...`);
            // Implement AJAX load more functionality here
        });
    });
    
    // Edit profile button functionality
    const editProfileBtn = document.querySelector('.btn-primary');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function() {
            alert('Edit profile functionality would open a modal or redirect to edit page');
            // Implement edit profile modal or redirect
        });
    }
    
    // Settings button functionality
    const settingsBtn = document.querySelector('.btn-secondary');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', function() {
            alert('Settings functionality would open settings page');
            // Implement settings redirect
        });
    }
    
    // Add smooth transitions
    const style = document.createElement('style');
    style.textContent = `
        .comic-card, .transaction-item, .comment-item, .info-group {
            transition: all 0.3s ease;
        }
        
        .tab-pane {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
});

// Additional utility functions
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}

// Export functions for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { formatDate, truncateText };
}