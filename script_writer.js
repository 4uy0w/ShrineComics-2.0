// ===== DASHBOARD WRITER JAVASCRIPT =====

// User Dropdown Functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeUserDropdown();
    initializeComicCards();
    startAutoRefresh();
    console.log('Dashboard Writer JS loaded successfully');
});

// User Dropdown Management
function initializeUserDropdown() {
    const userToggle = document.getElementById('userToggle');
    const userDropdown = document.getElementById('userDropdown');

    if (userToggle && userDropdown) {
        userToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            userDropdown.classList.remove('show');
        });

        // Close dropdown on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                userDropdown.classList.remove('show');
            }
        });
    }
}

// Comic Cards Management
function initializeComicCards() {
    // Add tabindex for accessibility
    const comicCards = document.querySelectorAll('.comic-card');
    comicCards.forEach(card => {
        card.setAttribute('tabindex', '0');
        
        // Add keyboard navigation
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const comicId = this.dataset.comicId;
                window.location.href = `comic_detail.php?id=${comicId}`;
            }
        });
    });
}

// Comic Management Functions
function editComic(comicId) {
    console.log('Edit comic:', comicId);
    
    showNotification('Membuka editor komik...', 'info');
    
    // Redirect to edit page
    setTimeout(() => {
        window.location.href = `edit_comic.php?id=${comicId}`;
    }, 500);
}

function deleteComic(comicId) {
    console.log('Delete comic:', comicId);
    
    const comicCard = document.querySelector(`[data-comic-id="${comicId}"]`);
    const comicTitle = comicCard?.querySelector('.comic-title')?.textContent || 'komik ini';
    
    // Confirmation dialog
    if (!confirm(`Apakah Anda yakin ingin menghapus "${comicTitle}"?\n\nTindakan ini tidak dapat dibatalkan dan semua chapter akan ikut terhapus!`)) {
        return;
    }
    
    showNotification('Menghapus komik...', 'info');
    
    // Send delete request
    const formData = new FormData();
    formData.append('comic_id', comicId);
    
    fetch('delete_comic.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to debug
        return response.text().then(text => {
            console.log('Raw response:', text);
            
            if (!text) {
                throw new Error('Empty response from server');
            }
            
            try {
                return JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data);
        
        if (data.success) {
            showNotification(data.message, 'success');
            removeComicCard(comicId);
            updateComicCount(-1);
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Gagal menghapus komik: ' + error.message, 'error');
    });
}

function removeComicCard(comicId) {
    const comicCard = document.querySelector(`[data-comic-id="${comicId}"]`);
    if (comicCard) {
        // Add removal animation
        comicCard.style.transition = 'all 0.3s ease';
        comicCard.style.opacity = '0';
        comicCard.style.transform = 'translateX(-100%)';
        comicCard.style.height = '0';
        comicCard.style.margin = '0';
        comicCard.style.padding = '0';
        comicCard.style.overflow = 'hidden';
        comicCard.style.border = 'none';
        
        // Remove from DOM after animation
        setTimeout(() => {
            comicCard.remove();
            checkEmptyComicsState();
        }, 300);
    }
}

function updateComicCount(change) {
    // Update badge count
    const badge = document.querySelector('.comics-section .badge');
    if (badge) {
        const currentCount = parseInt(badge.textContent) || 0;
        const newCount = Math.max(0, currentCount + change);
        badge.textContent = `${newCount} komik`;
    }
    
    // Update stat card
    const statCard = document.querySelector('.stats-grid .stat-card:nth-child(2) .stat-value');
    if (statCard) {
        const currentCount = parseInt(statCard.textContent.replace(/,/g, '')) || 0;
        const newCount = Math.max(0, currentCount + change);
        statCard.textContent = newCount.toLocaleString();
    }
}

function checkEmptyComicsState() {
    const comicsGrid = document.querySelector('.comics-grid');
    const comicCards = comicsGrid.querySelectorAll('.comic-card');
    
    if (comicCards.length === 0 && !comicsGrid.querySelector('.empty-state')) {
        // Show empty state if no comics left
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        emptyState.innerHTML = `
            <div class="empty-icon">📚</div>
            <h3>Belum Ada Komik</h3>
            <p>Mulai perjalanan kreatif Anda dengan membuat komik pertama</p>
            <a href="add_comic.php" class="btn btn-primary">
                <span class="btn-icon">+</span>
                Buat Komik Pertama
            </a>
        `;
        comicsGrid.appendChild(emptyState);
    }
}

// Points Auto Refresh
function startAutoRefresh() {
    // Refresh points every 30 seconds
    setInterval(refreshPoints, 30000);
}

function refreshPoints() {
    fetch('get_writer_points.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                if (!text) {
                    throw new Error('Empty response from server');
                }
                return JSON.parse(text);
            });
        })
        .then(data => {
            if (data.success) {
                updatePointsDisplay(data.points);
            }
        })
        .catch(error => {
            console.error('Error refreshing points:', error);
        });
}

function updatePointsDisplay(points) {
    const pointElements = document.querySelectorAll('.stat-value');
    if (pointElements[0]) {
        pointElements[0].textContent = points.toLocaleString();
    }
}

// Notification System
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `custom-notification ${type}`;
    notification.innerHTML = `
        <span class="notification-icon">${getNotificationIcon(type)}</span>
        <span class="notification-message">${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 4000);
    
    // Click to dismiss
    notification.addEventListener('click', () => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    });
}

function getNotificationIcon(type) {
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
    };
    return icons[type] || '💡';
}

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + E to edit focused comic
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        const focusedCard = document.querySelector('.comic-card:focus-within');
        if (focusedCard) {
            const comicId = focusedCard.dataset.comicId;
            editComic(comicId);
        }
    }
    
    // Delete key to delete focused comic
    if (e.key === 'Delete') {
        const focusedCard = document.querySelector('.comic-card:focus-within');
        if (focusedCard) {
            e.preventDefault();
            const comicId = focusedCard.dataset.comicId;
            deleteComic(comicId);
        }
    }
    
    // Escape to close dropdowns
    if (e.key === 'Escape') {
        const userDropdown = document.getElementById('userDropdown');
        if (userDropdown) {
            userDropdown.classList.remove('show');
        }
    }
});

// Utility Functions
function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

// Error handling for uncaught errors
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    showNotification('Terjadi kesalahan sistem', 'error');
});

// Export functions for global access
window.dashboardWriter = {
    editComic,
    deleteComic,
    showNotification,
    refreshPoints
};

console.log('Dashboard Writer JavaScript initialized');