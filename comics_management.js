// comics_management.js

// Fungsi untuk mengedit komik
function editComic(comicId) {
    window.location.href = 'edit_comic.php?id=' + comicId;
}

// Fungsi untuk menghapus komik
function deleteComic(comicId) {
    const comicTitle = document.querySelector(`[data-comic-id="${comicId}"] .comic-title-management`)?.textContent || 'komik ini';
    
    if (confirm(`Apakah Anda yakin ingin menghapus "${comicTitle}"?\n\nTindakan ini tidak dapat dibatalkan dan semua chapter yang terkait juga akan dihapus.`)) {
        // Tampilkan loading state
        const comicCard = document.querySelector(`.comic-card-management[data-comic-id="${comicId}"]`);
        if (comicCard) {
            comicCard.style.opacity = '0.6';
            comicCard.style.pointerEvents = 'none';
        }
        
        // Kirim permintaan AJAX untuk menghapus komik
        fetch('delete_comic.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'comic_id=' + comicId
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Hapus kartu komik dari DOM dengan animasi
                if (comicCard) {
                    comicCard.style.transition = 'all 0.4s ease';
                    comicCard.style.transform = 'scale(0.8) translateY(20px)';
                    comicCard.style.opacity = '0';
                    
                    setTimeout(() => {
                        comicCard.remove();
                        showNotification(`"${comicTitle}" berhasil dihapus`, 'success');
                        
                        // Perbarui jumlah komik di badge
                        updateComicsCount(-1);
                        
                        // Jika tidak ada komik lagi, tampilkan empty state
                        checkEmptyState();
                    }, 400);
                }
            } else {
                throw new Error(data.message || 'Gagal menghapus komik');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan saat menghapus komik: ' + error.message, 'error');
            
            // Reset loading state
            if (comicCard) {
                comicCard.style.opacity = '1';
                comicCard.style.pointerEvents = 'auto';
            }
        });
    }
}

// Fungsi untuk menampilkan notifikasi
function showNotification(message, type = 'info') {
    // Hapus notifikasi sebelumnya jika ada
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
    
    // Styles untuk notifikasi
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
    
    // Animasi masuk
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove setelah 5 detik
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

// Fungsi untuk memperbarui jumlah komik di badge
function updateComicsCount(change) {
    const badge = document.querySelector('.filter-sort .badge');
    if (badge) {
        const currentCount = parseInt(badge.textContent.replace(' komik', ''));
        const newCount = Math.max(0, currentCount + change);
        badge.textContent = newCount + ' komik';
        
        // Animasi badge
        badge.style.transform = 'scale(1.1)';
        setTimeout(() => {
            badge.style.transform = 'scale(1)';
        }, 300);
    }
}

// Fungsi untuk memeriksa dan menampilkan empty state jika diperlukan
function checkEmptyState() {
    const comicsGrid = document.querySelector('.comics-grid-management');
    const comicCards = comicsGrid.querySelectorAll('.comic-card-management');
    
    if (comicCards.length === 0 && !comicsGrid.querySelector('.empty-state-management')) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state-management';
        emptyState.innerHTML = `
            <div class="empty-icon-management">📚</div>
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

// Fungsi untuk mengurutkan komik
function sortComics(sortBy) {
    const comicsGrid = document.querySelector('.comics-grid-management');
    const comicCards = Array.from(comicsGrid.querySelectorAll('.comic-card-management'));
    
    if (comicCards.length === 0) return;
    
    comicCards.sort((a, b) => {
        const titleA = a.querySelector('.comic-title-management').textContent.toLowerCase();
        const titleB = b.querySelector('.comic-title-management').textContent.toLowerCase();
        const dateTextA = a.querySelector('.comic-stats-management .stat:last-child').textContent;
        const dateTextB = b.querySelector('.comic-stats-management .stat:last-child').textContent;
        
        // Simple date parsing (assuming format like "Mar 2024")
        const dateA = new Date(dateTextA);
        const dateB = new Date(dateTextB);
        
        switch (sortBy) {
            case 'newest':
                return dateB - dateA;
            case 'oldest':
                return dateA - dateB;
            case 'title_asc':
                return titleA.localeCompare(titleB);
            case 'title_desc':
                return titleB.localeCompare(titleA);
            default:
                return 0;
        }
    });
    
    // Animasi penghapusan dan penambahan ulang
    comicCards.forEach(card => {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
    });
    
    setTimeout(() => {
        // Hapus semua kartu komik
        comicCards.forEach(card => card.remove());
        
        // Tambahkan kembali kartu komik yang sudah diurutkan
        comicCards.forEach((card, index) => {
            comicsGrid.appendChild(card);
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        showNotification(`Komik diurutkan berdasarkan: ${getSortLabel(sortBy)}`, 'info');
    }, 300);
}

// Fungsi untuk mendapatkan label pengurutan
function getSortLabel(sortBy) {
    const labels = {
        'newest': 'Terbaru',
        'oldest': 'Terlama',
        'title_asc': 'Judul A-Z',
        'title_desc': 'Judul Z-A'
    };
    return labels[sortBy] || 'Terbaru';
}

// Event listener untuk dropdown pengurutan
document.addEventListener('DOMContentLoaded', function() {
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortComics(this.value);
        });
    }
    
    // Animasi masuk untuk kartu komik
    const comicCards = document.querySelectorAll('.comic-card-management');
    comicCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
    
    // Enhanced search form interaction
    const searchForm = document.querySelector('.search-form');
    const searchInput = searchForm?.querySelector('input');
    
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            this.parentElement.parentElement.style.boxShadow = '0 8px 30px rgba(102, 126, 234, 0.2)';
            this.parentElement.parentElement.style.borderColor = '#667eea';
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentElement.parentElement.style.boxShadow = '0 4px 20px rgba(0,0,0,0.08)';
            this.parentElement.parentElement.style.borderColor = '#e8e8e8';
        });
    }
    
    console.log('Comics management JavaScript loaded successfully');
});

// Export untuk testing (jika diperlukan)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        editComic,
        deleteComic,
        sortComics,
        showNotification,
        updateComicsCount,
        checkEmptyState
    };
}