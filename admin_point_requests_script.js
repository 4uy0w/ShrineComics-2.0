/**
 * Admin Point Requests Management
 * Enhanced functionality for point requests page
 */

document.addEventListener('DOMContentLoaded', function() {
    initPointRequests();
});

function initPointRequests() {
    console.log('🔄 Admin Point Requests initialized');
    
    // Add hover effects to table rows
    enhanceTableInteractions();
    
    // Add loading states to buttons
    setupButtonLoading();
    
    // Add keyboard shortcuts
    setupKeyboardShortcuts();
    
    // Auto-refresh every 30 seconds if there are pending requests
    startAutoRefresh();
}

/**
 * Enhance table interactions
 */
function enhanceTableInteractions() {
    const tableRows = document.querySelectorAll('.request-row');
    
    tableRows.forEach(row => {
        // Add click effect
        row.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-action')) {
                this.classList.toggle('row-expanded');
            }
        });
        
        // Add double click to expand notes
        row.addEventListener('dblclick', function() {
            const notesRow = this.nextElementSibling;
            if (notesRow && notesRow.classList.contains('notes-row')) {
                notesRow.style.display = notesRow.style.display === 'none' ? '' : 'none';
            }
        });
    });
}

/**
 * Setup loading states for action buttons
 */
function setupButtonLoading() {
    const actionForms = document.querySelectorAll('.action-form');
    
    actionForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = this.querySelector('.btn-action');
            if (button) {
                showButtonLoading(button);
            }
        });
    });
}

/**
 * Show loading state on button
 */
function showButtonLoading(button) {
    const originalHTML = button.innerHTML;
    button.innerHTML = '⏳ Memproses...';
    button.disabled = true;
    
    // Store original content for restoration
    button.setAttribute('data-original', originalHTML);
    
    // Re-enable after 5 seconds (fallback)
    setTimeout(() => {
        if (button.disabled) {
            hideButtonLoading(button);
        }
    }, 5000);
}

/**
 * Hide loading state from button
 */
function hideButtonLoading(button) {
    const originalHTML = button.getAttribute('data-original');
    if (originalHTML) {
        button.innerHTML = originalHTML;
    }
    button.disabled = false;
}

/**
 * Setup keyboard shortcuts
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + R to refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            refreshTable();
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                searchInput.form.submit();
            }
        }
    });
}

/**
 * Refresh table data
 */
function refreshTable() {
    console.log('🔄 Refreshing table...');
    
    // Show loading indicator
    const tableWrapper = document.querySelector('.table-wrapper');
    tableWrapper.style.opacity = '0.7';
    tableWrapper.style.pointerEvents = 'none';
    
    // Reload page after a short delay to show feedback
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

/**
 * Export requests data
 */
function exportRequests() {
    console.log('📊 Exporting requests...');
    
    // Simple CSV export implementation
    const table = document.querySelector('.requests-table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Add headers
    const headers = [];
    table.querySelectorAll('th').forEach(th => {
        headers.push(`"${th.textContent.trim()}"`);
    });
    csv.push(headers.join(','));
    
    // Add data rows (skip notes rows)
    rows.forEach(row => {
        if (row.classList.contains('request-row')) {
            const rowData = [];
            row.querySelectorAll('td').forEach(td => {
                let text = td.textContent.trim();
                // Remove extra whitespace and newlines
                text = text.replace(/\s+/g, ' ');
                rowData.push(`"${text}"`);
            });
            csv.push(rowData.join(','));
        }
    });
    
    // Create and download CSV file
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `point-requests-${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification('✅ Data berhasil diexport!', 'success');
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `custom-notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: ${getNotificationTextColor(type)};
        padding: 0;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        border: 1px solid ${getNotificationBorderColor(type)};
        max-width: 400px;
    `;
    
    // Add content styles
    const content = notification.querySelector('.notification-content');
    content.style.cssText = `
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    `;
    
    // Add close button styles
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.3s ease;
    `;
    
    closeBtn.addEventListener('mouseover', function() {
        this.style.background = 'rgba(0,0,0,0.1)';
    });
    
    closeBtn.addEventListener('mouseout', function() {
        this.style.background = 'none';
    });
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

/**
 * Get notification color based on type
 */
function getNotificationColor(type) {
    const colors = {
        'success': '#d4edda',
        'error': '#f8d7da',
        'info': '#d1ecf1',
        'warning': '#fff3cd'
    };
    return colors[type] || colors['info'];
}

/**
 * Get notification text color based on type
 */
function getNotificationTextColor(type) {
    const colors = {
        'success': '#155724',
        'error': '#721c24',
        'info': '#0c5460',
        'warning': '#856404'
    };
    return colors[type] || colors['info'];
}

/**
 * Get notification border color based on type
 */
function getNotificationBorderColor(type) {
    const colors = {
        'success': '#c3e6cb',
        'error': '#f5c6cb',
        'info': '#bee5eb',
        'warning': '#ffeaa7'
    };
    return colors[type] || colors['info'];
}

/**
 * Start auto-refresh for pending requests
 */
function startAutoRefresh() {
    const pendingCount = document.querySelector('.stat-card.pending .stat-number');
    if (pendingCount && parseInt(pendingCount.textContent) > 0) {
        console.log('⏰ Auto-refresh enabled (30s interval)');
        
        setInterval(() => {
            refreshTable();
        }, 30000); // 30 seconds
    }
}

/**
 * Filter table by status (client-side)
 */
function filterTable(status) {
    const rows = document.querySelectorAll('.request-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (status === 'all' || row.classList.contains(`status-${status}`)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update count display
    const countElement = document.querySelector('.table-count');
    if (countElement) {
        countElement.textContent = `${visibleCount} request ditemukan`;
    }
    
    showNotification(`Menampilkan ${visibleCount} request`, 'info');
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .row-expanded {
        background: #e7f3ff !important;
        border-left: 4px solid #007bff !important;
    }
    
    .custom-notification {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
`;
document.head.appendChild(style);

// Make functions globally available
window.refreshTable = refreshTable;
window.exportRequests = exportRequests;
window.filterTable = filterTable;
window.showNotification = showNotification;