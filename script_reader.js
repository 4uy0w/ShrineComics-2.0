// ===== FUNGSI UTAMA NAVIGASI KOMIK =====

/**
 * Fungsi untuk membuka detail komik
 * @param {number} comicId - ID komik yang akan dilihat
 */
function viewComicDetail(comicId) {
    console.log('🚀 Membuka detail comic dengan ID:', comicId);
    
    // Validasi comic ID
    if (!comicId || comicId === 0 || isNaN(comicId)) {
        console.error('❌ Error: Comic ID tidak valid:', comicId);
        showNotification('Error: Komik tidak valid', 'error');
        return;
    }
    
    // Redirect ke halaman detail komik
    window.location.href = `comic_detail.php?id=${comicId}`;
}

/**
 * Fungsi alternatif untuk melihat detail komik
 * @param {number} comicId - ID komik yang akan dilihat
 */
function showDetails(comicId) {
    viewComicDetail(comicId);
}

/**
 * Fungsi untuk membaca komik (jika berbeda dengan detail)
 * @param {number} comicId - ID komik yang akan dibaca
 */
function readComic(comicId) {
    console.log('📖 Membaca comic dengan ID:', comicId);
    // Redirect ke halaman baca komik jika berbeda
    window.location.href = `read_comic.php?id=${comicId}`;
}

// ===== FUNGSI FILTER DAN PENCARIAN =====

/**
 * Filter komik berdasarkan genre
 * @param {string} genre - Genre yang dipilih
 */
function filterByGenre(genre) {
    console.log('🎭 Filter by genre:', genre);
    const url = new URL(window.location);
    url.searchParams.set('genre', genre);
    url.searchParams.delete('page'); // Remove pagination if exists
    window.location.href = url.toString();
}

/**
 * Hapus pencarian dan reset filter
 */
function clearSearch() {
    console.log('🔄 Clearing search');
    const url = new URL(window.location);
    url.searchParams.delete('search');
    window.location.href = url.toString();
}

// ===== FUNGSI POINT SYSTEM =====

/**
 * Tampilkan modal riwayat points
 */
function showPointHistory() {
    console.log('💰 Showing point history');
    const modal = document.getElementById('pointHistoryModal');
    if (modal) {
        modal.style.display = 'block';
        
        // Add escape key listener
        const escapeHandler = function(e) {
            if (e.key === 'Escape') {
                closeModal('pointHistoryModal');
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
    }
}

/**
 * Tutup modal
 * @param {string} modalId - ID modal yang akan ditutup
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Auto-update points display
 * @param {number} newPoints - Jumlah points baru
 */
function updatePointsDisplay(newPoints) {
    const pointElements = document.querySelectorAll('.point-value, .stat-number:last-child');
    pointElements.forEach(element => {
        element.textContent = newPoints.toLocaleString();
    });
    
    // Add animation
    pointElements.forEach(element => {
        element.style.transform = 'scale(1.2)';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 300);
    });
}

/**
 * Simulasi dapat points (untuk testing)
 * @param {number} amount - Jumlah points
 * @param {string} reason - Alasan dapat points
 */
function simulateEarnPoints(amount, reason) {
    const currentPoints = parseInt(document.querySelector('.point-value').textContent.replace(/,/g, '')) || 0;
    const newPoints = currentPoints + amount;
    
    updatePointsDisplay(newPoints);
    showNotification(`🎉 +${amount} points! ${reason}`, 'success');
}

// ===== FUNGSI NOTIFICATION SYSTEM =====

/**
 * Tampilkan notifikasi
 * @param {string} message - Pesan notifikasi
 * @param {string} type - Tipe notifikasi (success, error, info, warning)
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="notification-message">${message}</span>
        <button class="notification-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : type === 'warning' ? '#fff3cd' : '#d1ecf1'};
        color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : type === 'warning' ? '#856404' : '#0c5460'};
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
        border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : type === 'warning' ? '#ffeaa7' : '#bee5eb'};
        max-width: 400px;
    `;
    
    // Add close button styles
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// ===== EVENT LISTENERS DAN INITIALIZATION =====

/**
 * Initialize semua event listeners
 */
function initializeEventListeners() {
    // Add click event to entire comic card for details
    const comicCards = document.querySelectorAll('.comic-card');
    comicCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Only trigger if not clicking on buttons
            if (!e.target.closest('.comic-actions-bottom')) {
                const comicId = this.getAttribute('data-comic-id');
                if (comicId) {
                    console.log('🖱️ Card clicked, opening comic ID:', comicId);
                    viewComicDetail(parseInt(comicId));
                }
            }
        });
    });
    
    console.log(`✅ Loaded ${comicCards.length} comic cards`);
    
    // Point display click event
    const pointDisplay = document.querySelector('.point-display');
    if (pointDisplay) {
        pointDisplay.addEventListener('click', function() {
            showPointHistory();
        });
        
        // Add tooltip
        pointDisplay.title = 'Klik untuk melihat riwayat points';
    }
    
    // Click outside modal to close
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    }
}

/**
 * Check points status dan tampilkan warning jika rendah
 */
function checkPointsStatus() {
    const currentPoints = parseInt(document.querySelector('.point-value').textContent.replace(/,/g, '')) || 0;
    if (currentPoints < 10) {
        setTimeout(() => {
            showNotification('💡 Points Anda hampir habis! Baca lebih banyak chapter untuk dapat points.', 'warning');
        }, 3000);
    }
}

/**
 * Setup keyboard shortcuts
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + P untuk point history
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            showPointHistory();
        }
        
        // Ctrl/Cmd + K untuk focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape untuk close modal
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                }
            });
        }
    });
}

/**
 * Add CSS animations untuk notifications
 */
function addNotificationStyles() {
    const style = document.createElement('style');
    style.textContent = `
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
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// ===== MAIN INITIALIZATION =====

/**
 * Initialize seluruh sistem reader
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Dashboard Reader initialized');
    
    // Setup semua komponen
    addNotificationStyles();
    initializeEventListeners();
    setupKeyboardShortcuts();
    checkPointsStatus();
    
    console.log('✅ Reader system loaded successfully');
});

// Export functions untuk global access
window.viewComicDetail = viewComicDetail;
window.showDetails = showDetails;
window.readComic = readComic;
window.filterByGenre = filterByGenre;
window.clearSearch = clearSearch;
window.showPointHistory = showPointHistory;
window.closeModal = closeModal;
window.showNotification = showNotification;

/**
 * Fungsi untuk membuka halaman profile
 */
function openProfile() {
    console.log('👤 Opening user profile');
    window.location.href = 'profile.php';
}

// Tambahkan ke export functions
window.openProfile = openProfile;