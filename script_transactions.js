// Transactions History JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeFilters();
    initializeModal();
});

// Filter functionality
function initializeFilters() {
    const filtersForm = document.getElementById('filtersForm');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    // Date validation
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (this.value && dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
        });
        
        dateTo.addEventListener('change', function() {
            if (this.value && dateFrom.value && this.value < dateFrom.value) {
                dateFrom.value = this.value;
            }
        });
    }
    
    // Auto-submit on some filter changes
    const autoSubmitFilters = document.querySelectorAll('#status, #comic');
    autoSubmitFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            if (filtersForm) {
                filtersForm.submit();
            }
        });
    });
}

// Reset filters
function resetFilters() {
    window.location.href = 'transactions_history.php';
}

// Modal functionality
function initializeModal() {
    const modal = document.getElementById('transactionModal');
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

// Show transaction detail modal
function showTransactionDetail(transactionId) {
    const modal = document.getElementById('transactionModal');
    const modalBody = document.getElementById('modalBody');
    
    // Show loading state
    modalBody.innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <div class="loading"></div>
            <p>Memuat detail transaksi...</p>
        </div>
    `;
    
    modal.style.display = 'block';
    
    // Fetch transaction details via AJAX
    fetch(`get_transaction_detail.php?id=${transactionId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                modalBody.innerHTML = formatTransactionDetail(data.transaction);
            } else {
                modalBody.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: #dc3545;">
                        <h4>Error</h4>
                        <p>${data.message || 'Gagal memuat detail transaksi'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #dc3545;">
                    <h4>Error</h4>
                    <p>Terjadi kesalahan saat memuat detail transaksi</p>
                </div>
            `;
        });
}

// Format transaction detail for modal
function formatTransactionDetail(transaction) {
    const statusText = {
        'success': 'Berhasil',
        'pending': 'Pending',
        'failed': 'Gagal'
    };
    
    const statusClass = {
        'success': 'success',
        'pending': 'pending', 
        'failed': 'failed'
    };
    
    return `
        <div class="transaction-detail-modal">
            <div class="detail-section">
                <h4>Informasi Transaksi</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>ID Transaksi</label>
                        <span>#${transaction.transaction_id}</span>
                    </div>
                    <div class="detail-item">
                        <label>Tanggal</label>
                        <span>${formatDateTime(transaction.transaction_date)}</span>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <span class="status-badge ${statusClass[transaction.transaction_status]}">
                            ${statusText[transaction.transaction_status]}
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Jumlah</label>
                        <span class="amount positive" style="font-size: 1.1rem; font-weight: 700;">
                            +${numberFormat(transaction.transaction_point)} poin
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Informasi Komik</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Judul Komik</label>
                        <span>${escapeHtml(transaction.comic_title)}</span>
                    </div>
                    <div class="detail-item">
                        <label>Chapter</label>
                        <span>Chapter ${transaction.chapter_number} - ${escapeHtml(transaction.chapter_name)}</span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Informasi Pembeli</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Username</label>
                        <span>${escapeHtml(transaction.buyer_name)}</span>
                    </div>
                </div>
            </div>
            
            ${transaction.transaction_status === 'failed' ? `
                <div class="detail-section">
                    <h4>Informasi Error</h4>
                    <div class="error-notice">
                        <p>Transaksi ini gagal diproses. Silakan hubungi administrator untuk informasi lebih lanjut.</p>
                    </div>
                </div>
            ` : ''}
        </div>
        
        <style>
            .transaction-detail-modal {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .detail-section h4 {
                margin: 0 0 1rem 0;
                color: #212529;
                font-weight: 600;
                border-bottom: 2px solid #007bff;
                padding-bottom: 0.5rem;
            }
            
            .detail-grid {
                display: grid;
                gap: 1rem;
            }
            
            .detail-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                background: #f8f9fa;
                border-radius: 8px;
            }
            
            .detail-item label {
                font-weight: 500;
                color: #495057;
            }
            
            .detail-item span {
                color: #212529;
                font-weight: 500;
            }
            
            .error-notice {
                background: #f8d7da;
                color: #721c24;
                padding: 1rem;
                border-radius: 8px;
                border: 1px solid #f5c6cb;
            }
            
            .error-notice p {
                margin: 0;
            }
        </style>
    `;
}

// Close modal
function closeModal() {
    const modal = document.getElementById('transactionModal');
    modal.style.display = 'none';
}

// Export to CSV
function exportToCSV() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    
    // Show loading state on button
    const exportBtn = document.querySelector('button[onclick="exportToCSV()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<span class="btn-icon">⏳</span>Mempersiapkan CSV...';
    exportBtn.classList.add('loading');
    
    fetch(`export_transactions.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.blob();
        })
        .then(blob => {
            // Create download link
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `transaksi-${new Date().toISOString().split('T')[0]}.csv`;
            
            document.body.appendChild(a);
            a.click();
            
            // Cleanup
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            // Restore button
            exportBtn.innerHTML = originalText;
            exportBtn.classList.remove('loading');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal mengekspor data. Silakan coba lagi.');
            
            // Restore button
            exportBtn.innerHTML = originalText;
            exportBtn.classList.remove('loading');
        });
}

// Utility functions
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function numberFormat(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Print transaction history
function printTransactions() {
    window.print();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + F for focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('input[type="search"], input[name="comic"]');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Escape to close modal
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Format transaction detail for modal - Updated Version
function formatTransactionDetail(transaction) {
    const statusText = {
        'success': 'Berhasil',
        'pending': 'Pending',
        'failed': 'Gagal'
    };
    
    const statusClass = {
        'success': 'success',
        'pending': 'pending', 
        'failed': 'failed'
    };
    
    // Calculate buyer membership duration
    const joinDate = new Date(transaction.buyer_join_date);
    const now = new Date();
    const monthsDiff = (now.getFullYear() - joinDate.getFullYear()) * 12 + (now.getMonth() - joinDate.getMonth());
    const membershipDuration = monthsDiff < 12 ? 
        `${monthsDiff} bulan` : 
        `${Math.floor(monthsDiff / 12)} tahun ${monthsDiff % 12} bulan`;
    
    return `
        <div class="transaction-detail-modal">
            <div class="detail-section">
                <h4>📋 Informasi Transaksi</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>ID Transaksi</label>
                        <span class="transaction-id">#${transaction.transaction_id}</span>
                    </div>
                    <div class="detail-item">
                        <label>Tanggal & Waktu</label>
                        <span>${transaction.formatted_date}</span>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <span class="status-badge ${statusClass[transaction.transaction_status]}">
                            ${statusText[transaction.transaction_status]}
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Jumlah Poin</label>
                        <span class="amount positive" style="font-size: 1.1rem; font-weight: 700;">
                            +${numberFormat(transaction.transaction_point)} poin
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>📚 Informasi Komik</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Judul Komik</label>
                        <span class="comic-title">${escapeHtml(transaction.comic_title)}</span>
                    </div>
                    <div class="detail-item">
                        <label>Chapter</label>
                        <span>Chapter ${transaction.chapter_number} - ${escapeHtml(transaction.chapter_name)}</span>
                    </div>
                    <div class="detail-item">
                        <label>Harga Chapter</label>
                        <span>${numberFormat(transaction.chapter_price)} poin</span>
                    </div>
                    <div class="detail-item">
                        <label>Total Penjualan Komik</label>
                        <span>${numberFormat(transaction.comic_total_sales)} chapter terjual</span>
                    </div>
                    <div class="detail-item">
                        <label>Total Pendapatan Komik</label>
                        <span class="amount positive">${numberFormat(transaction.comic_total_earnings)} poin</span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>👤 Informasi Pembeli</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Username</label>
                        <span class="buyer-name">${escapeHtml(transaction.buyer_name)}</span>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <span class="buyer-email">${escapeHtml(transaction.buyer_email)}</span>
                    </div>
                    <div class="detail-item">
                        <label>Tanggal Bergabung</label>
                        <span>${transaction.formatted_buyer_join_date}</span>
                    </div>
                    <div class="detail-item">
                        <label>Lama Keanggotaan</label>
                        <span>${membershipDuration}</span>
                    </div>
                </div>
            </div>
            
            ${transaction.recent_transactions && transaction.recent_transactions.length > 0 ? `
                <div class="detail-section">
                    <h4>🕒 Transaksi Terbaru dari Pembeli Ini</h4>
                    <div class="recent-transactions">
                        ${transaction.recent_transactions.map(trans => `
                            <div class="recent-item">
                                <div class="recent-comic">${escapeHtml(trans.comic_title)}</div>
                                <div class="recent-details">
                                    <span class="recent-chapter">Chapter ${trans.chapter_number}</span>
                                    <span class="recent-points">+${numberFormat(trans.transaction_point)} poin</span>
                                    <span class="recent-date">${trans.transaction_date}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
            
            ${transaction.transaction_status === 'failed' ? `
                <div class="detail-section">
                    <h4>❌ Informasi Error</h4>
                    <div class="error-notice">
                        <p><strong>Transaksi ini gagal diproses.</strong> Kemungkinan penyebab:</p>
                        <ul>
                            <li>Saldo poin pembeli tidak mencukupi</li>
                            <li>Masalah jaringan atau sistem</li>
                            <li>Chapter sudah tidak tersedia</li>
                        </ul>
                        <p>Silakan hubungi administrator untuk informasi lebih lanjut.</p>
                    </div>
                </div>
            ` : ''}
            
            ${transaction.transaction_status === 'pending' ? `
                <div class="detail-section">
                    <h4>⏳ Status Pending</h4>
                    <div class="pending-notice">
                        <p>Transaksi ini sedang dalam proses verifikasi. Biasanya membutuhkan waktu 1-5 menit untuk diproses.</p>
                    </div>
                </div>
            ` : ''}
        </div>
        
        <style>
            .transaction-detail-modal {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .detail-section h4 {
                margin: 0 0 1rem 0;
                color: #212529;
                font-weight: 600;
                border-bottom: 2px solid #007bff;
                padding-bottom: 0.5rem;
            }
            
            .detail-grid {
                display: grid;
                gap: 0.75rem;
            }
            
            .detail-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #007bff;
            }
            
            .detail-item label {
                font-weight: 500;
                color: #495057;
                font-size: 0.9rem;
            }
            
            .detail-item span {
                color: #212529;
                font-weight: 500;
                text-align: right;
            }
            
            .transaction-id {
                font-family: 'Courier New', monospace;
                background: #e9ecef;
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
                font-weight: 700;
            }
            
            .comic-title, .buyer-name, .buyer-email {
                font-weight: 600;
                color: #007bff;
            }
            
            .error-notice {
                background: #f8d7da;
                color: #721c24;
                padding: 1rem;
                border-radius: 8px;
                border: 1px solid #f5c6cb;
            }
            
            .error-notice ul {
                margin: 0.5rem 0;
                padding-left: 1.5rem;
            }
            
            .error-notice li {
                margin: 0.25rem 0;
            }
            
            .pending-notice {
                background: #fff3cd;
                color: #856404;
                padding: 1rem;
                border-radius: 8px;
                border: 1px solid #ffeaa7;
            }
            
            .recent-transactions {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                max-height: 200px;
                overflow-y: auto;
            }
            
            .recent-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                background: #ffffff;
                border: 1px solid #dee2e6;
                border-radius: 6px;
            }
            
            .recent-comic {
                font-weight: 500;
                color: #212529;
                flex: 1;
            }
            
            .recent-details {
                display: flex;
                gap: 1rem;
                align-items: center;
                font-size: 0.85rem;
                color: #6c757d;
            }
            
            .recent-chapter {
                background: #e9ecef;
                padding: 0.2rem 0.5rem;
                border-radius: 12px;
                font-size: 0.8rem;
            }
            
            .recent-points {
                color: #28a745;
                font-weight: 500;
            }
            
            .recent-date {
                font-size: 0.8rem;
            }
            
            @media (max-width: 768px) {
                .detail-item {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.25rem;
                }
                
                .detail-item span {
                    text-align: left;
                }
                
                .recent-item {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.5rem;
                }
                
                .recent-details {
                    width: 100%;
                    justify-content: space-between;
                }
            }
        </style>
    `;
}
// Enhanced Export to CSV function
function exportToCSV() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    
    // Show loading state on button
    const exportBtn = document.querySelector('button[onclick="exportToCSV()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<span class="btn-icon">⏳</span>Mempersiapkan CSV...';
    exportBtn.disabled = true;
    exportBtn.classList.add('loading');
    
    // Show progress indicator
    showExportProgress();
    
    // Add timestamp to avoid cache
    params.append('export_timestamp', new Date().getTime());
    
    fetch(`export_transactions.php?${params.toString()}`, {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.blob();
    })
    .then(blob => {
        // Check if blob is valid
        if (blob.size === 0) {
            throw new Error('File export kosong');
        }
        
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        
        // Generate filename with date and filters
        const filename = generateExportFilename();
        a.download = filename;
        
        document.body.appendChild(a);
        a.click();
        
        // Cleanup
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        // Show success message
        showExportSuccess(filename, blob.size);
        
    })
    .catch(error => {
        console.error('Export Error:', error);
        showExportError(error.message);
    })
    .finally(() => {
        // Restore button
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        exportBtn.classList.remove('loading');
        
        // Hide progress
        hideExportProgress();
    });
}

// Generate filename based on filters
function generateExportFilename() {
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toHoursMinuteString();
    
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status');
    const comic = params.get('comic');
    const dateFrom = params.get('date_from');
    const dateTo = params.get('date_to');
    
    let filename = `transaksi_${dateStr}_${timeStr}`;
    
    if (status && status !== 'all') {
        filename += `_${status}`;
    }
    
    if (comic) {
        const comicSlug = comic.substring(0, 20).replace(/[^a-zA-Z0-9]/g, '_');
        filename += `_${comicSlug}`;
    }
    
    if (dateFrom && dateTo) {
        filename += `_${dateFrom}_to_${dateTo}`;
    } else if (dateFrom) {
        filename += `_from_${dateFrom}`;
    } else if (dateTo) {
        filename += `_to_${dateTo}`;
    }
    
    return filename + '.csv';
}

// Progress indicator functions
function showExportProgress() {
    // Remove existing progress indicator if any
    hideExportProgress();
    
    const progress = document.createElement('div');
    progress.id = 'exportProgress';
    progress.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #ffffff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1001;
        min-width: 250px;
    `;
    
    progress.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="loading" style="width: 20px; height: 20px;"></div>
            <div>
                <div style="font-weight: 600; color: #212529;">Mengekspor Data...</div>
                <div style="font-size: 0.85rem; color: #6c757d;">Mempersiapkan file CSV</div>
            </div>
        </div>
    `;
    
    document.body.appendChild(progress);
}

function hideExportProgress() {
    const existingProgress = document.getElementById('exportProgress');
    if (existingProgress) {
        existingProgress.remove();
    }
}

// Success notification
function showExportSuccess(filename, fileSize) {
    const sizeInKB = (fileSize / 1024).toFixed(1);
    
    // Remove existing notification if any
    const existingNotification = document.getElementById('exportNotification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.id = 'exportNotification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #d4edda;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1001;
        min-width: 300px;
        animation: slideInRight 0.3s ease;
    `;
    
    notification.innerHTML = `
        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
            <div style="color: #155724; font-size: 1.2rem;">✅</div>
            <div style="flex: 1;">
                <div style="font-weight: 600; color: #155724;">Export Berhasil!</div>
                <div style="font-size: 0.85rem; color: #155724; margin-top: 0.25rem;">
                    File <strong>${filename}</strong> telah diunduh<br>
                    <small>Ukuran: ${sizeInKB} KB</small>
                </div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #155724; padding: 0;">
                ×
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Error notification
function showExportError(errorMessage) {
    // Remove existing notification if any
    const existingNotification = document.getElementById('exportNotification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.id = 'exportNotification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1001;
        min-width: 300px;
        animation: slideInRight 0.3s ease;
    `;
    
    notification.innerHTML = `
        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
            <div style="color: #721c24; font-size: 1.2rem;">❌</div>
            <div style="flex: 1;">
                <div style="font-weight: 600; color: #721c24;">Export Gagal!</div>
                <div style="font-size: 0.85rem; color: #721c24; margin-top: 0.25rem;">
                    ${errorMessage}<br>
                    <small>Silakan coba lagi atau hubungi administrator.</small>
                </div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #721c24; padding: 0;">
                ×
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 8 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 8000);
}

// Utility function to format time
Date.prototype.toHoursMinuteString = function() {
    return this.getHours().toString().padStart(2, '0') + 
           this.getMinutes().toString().padStart(2, '0');
};

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(style);