// Manage Pages JavaScript
class ManagePages {
    constructor() {
        this.pagesList = document.getElementById('pagesList');
        this.orderPreview = document.getElementById('orderPreview');
        this.saveButton = document.getElementById('saveOrder');
        this.resetButton = document.getElementById('resetOrder');
        this.draggedItem = null;
        this.originalOrder = [];
        this.currentOrder = [];
        
        this.init();
    }
    
    init() {
        this.loadOriginalOrder();
        this.bindEvents();
        this.updateOrderPreview();
        console.log('📑 Manage Pages initialized');
    }
    
    loadOriginalOrder() {
        const pageItems = this.pagesList.querySelectorAll('.page-item');
        this.originalOrder = Array.from(pageItems).map(item => ({
            id: parseInt(item.dataset.pageId),
            number: parseInt(item.querySelector('.current-number').textContent.replace('#', ''))
        }));
        this.currentOrder = [...this.originalOrder];
    }
    
    bindEvents() {
        // Drag and drop events
        if (this.pagesList) {
            this.setupDragAndDrop();
        }
        
        // Save order button
        if (this.saveButton) {
            this.saveButton.addEventListener('click', () => {
                this.savePageOrder();
            });
        }
        
        // Reset order button
        if (this.resetButton) {
            this.resetButton.addEventListener('click', () => {
                this.resetPageOrder();
            });
        }
    }
    
    setupDragAndDrop() {
        const pageItems = this.pagesList.querySelectorAll('.page-item');
        
        pageItems.forEach(item => {
            item.setAttribute('draggable', true);
            
            // Drag start
            item.addEventListener('dragstart', (e) => {
                this.draggedItem = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', item.innerHTML);
            });
            
            // Drag end
            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                pageItems.forEach(i => i.classList.remove('over'));
                this.draggedItem = null;
            });
            
            // Drag over
            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            
            // Drag enter
            item.addEventListener('dragenter', (e) => {
                e.preventDefault();
                item.classList.add('over');
            });
            
            // Drag leave
            item.addEventListener('dragleave', () => {
                item.classList.remove('over');
            });
            
            // Drop
            item.addEventListener('drop', (e) => {
                e.preventDefault();
                item.classList.remove('over');
                
                if (this.draggedItem !== item) {
                    // Get all items
                    const items = Array.from(this.pagesList.querySelectorAll('.page-item'));
                    const draggedIndex = items.indexOf(this.draggedItem);
                    const targetIndex = items.indexOf(item);
                    
                    if (draggedIndex < targetIndex) {
                        item.parentNode.insertBefore(this.draggedItem, item.nextSibling);
                    } else {
                        item.parentNode.insertBefore(this.draggedItem, item);
                    }
                    
                    this.updatePageNumbers();
                    this.updateOrderPreview();
                }
            });
        });
    }
    
    updatePageNumbers() {
        const pageItems = this.pagesList.querySelectorAll('.page-item');
        this.currentOrder = [];
        
        pageItems.forEach((item, index) => {
            const pageId = parseInt(item.dataset.pageId);
            const newNumber = index + 1;
            
            // Update display
            const currentNumber = item.querySelector('.current-number');
            const newNumberSpan = item.querySelector('.new-number');
            
            const originalNumber = parseInt(currentNumber.textContent.replace('#', ''));
            
            if (originalNumber !== newNumber) {
                currentNumber.style.display = 'none';
                newNumberSpan.style.display = 'block';
                newNumberSpan.textContent = `#${newNumber}`;
                newNumberSpan.title = `Dari #${originalNumber}`;
            } else {
                currentNumber.style.display = 'block';
                newNumberSpan.style.display = 'none';
            }
            
            this.currentOrder.push({
                id: pageId,
                number: newNumber
            });
        });
    }
    
    updateOrderPreview() {
        if (!this.orderPreview) return;
        
        this.orderPreview.innerHTML = '';
        
        this.currentOrder.forEach((page, index) => {
            const originalPage = this.originalOrder.find(p => p.id === page.id);
            const hasChanged = originalPage && originalPage.number !== page.number;
            
            const orderItem = document.createElement('div');
            orderItem.className = 'order-item';
            orderItem.innerHTML = `
                <span>Halaman ${page.number}</span>
                ${hasChanged ? 
                    `<small style="color: #28a745; margin-left: 5px;">(dari ${originalPage.number})</small>` : 
                    ''
                }
            `;
            
            if (hasChanged) {
                orderItem.style.background = '#d4edda';
                orderItem.style.borderColor = '#c3e6cb';
            }
            
            this.orderPreview.appendChild(orderItem);
        });
    }
    
    async savePageOrder() {
        if (!this.hasChanges()) {
            this.showAlert('Tidak ada perubahan yang perlu disimpan.', 'info');
            return;
        }
        
        const saveBtn = this.saveButton;
        const originalText = saveBtn.innerHTML;
        
        // Show loading
        saveBtn.innerHTML = '<span class="btn-loading">🔄</span> Menyimpan...';
        saveBtn.disabled = true;
        
        try {
            const response = await fetch('update_page_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    chapter_id: new URLSearchParams(window.location.search).get('chapter_id'),
                    page_order: this.currentOrder
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Urutan halaman berhasil disimpan!', 'success');
                this.originalOrder = [...this.currentOrder];
                this.updateOrderPreview();
                
                // Update current numbers to reflect new order
                const pageItems = this.pagesList.querySelectorAll('.page-item');
                pageItems.forEach((item, index) => {
                    const currentNumber = item.querySelector('.current-number');
                    const newNumberSpan = item.querySelector('.new-number');
                    
                    currentNumber.textContent = `#${index + 1}`;
                    currentNumber.style.display = 'block';
                    newNumberSpan.style.display = 'none';
                });
                
            } else {
                throw new Error(data.message || 'Gagal menyimpan urutan');
            }
            
        } catch (error) {
            console.error('Error saving page order:', error);
            this.showAlert('Gagal menyimpan urutan: ' + error.message, 'error');
        } finally {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    }
    
    resetPageOrder() {
        if (!this.hasChanges()) {
            this.showAlert('Urutan masih sama dengan original.', 'info');
            return;
        }
        
        if (!confirm('Yakin ingin mengembalikan urutan ke semula?')) {
            return;
        }
        
        // Reset to original order
        const pagesContainer = this.pagesList;
        pagesContainer.innerHTML = '';
        
        this.originalOrder.forEach(page => {
            const pageItem = document.querySelector(`[data-page-id="${page.id}"]`).cloneNode(true);
            pagesContainer.appendChild(pageItem);
        });
        
        // Reinitialize drag and drop
        this.setupDragAndDrop();
        this.currentOrder = [...this.originalOrder];
        this.updateOrderPreview();
        
        this.showAlert('Urutan telah direset ke semula.', 'success');
    }
    
    hasChanges() {
        if (this.originalOrder.length !== this.currentOrder.length) {
            return true;
        }
        
        return this.currentOrder.some((page, index) => {
            const originalPage = this.originalOrder[index];
            return !originalPage || page.id !== originalPage.id || page.number !== originalPage.number;
        });
    }
    
    showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.custom-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        const alert = document.createElement('div');
        alert.className = `custom-alert ${type}`;
        alert.innerHTML = `
            <span class="alert-icon">${this.getAlertIcon(type)}</span>
            <span class="alert-message">${message}</span>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Add styles
        Object.assign(alert.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: this.getAlertColor(type),
            color: this.getAlertTextColor(type),
            padding: '15px 20px',
            borderRadius: '8px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            zIndex: '10000',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
            maxWidth: '400px',
            border: `1px solid ${this.getAlertBorderColor(type)}`
        });
        
        const closeBtn = alert.querySelector('.alert-close');
        Object.assign(closeBtn.style, {
            background: 'none',
            border: 'none',
            fontSize: '1.2rem',
            cursor: 'pointer',
            padding: '0',
            marginLeft: 'auto'
        });
        
        document.body.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, 5000);
    }
    
    getAlertIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            info: 'ℹ️',
            warning: '⚠️'
        };
        return icons[type] || '💡';
    }
    
    getAlertColor(type) {
        const colors = {
            success: '#d4edda',
            error: '#f8d7da',
            info: '#d1ecf1',
            warning: '#fff3cd'
        };
        return colors[type] || '#e2e3e5';
    }
    
    getAlertTextColor(type) {
        const colors = {
            success: '#155724',
            error: '#721c24',
            info: '#0c5460',
            warning: '#856404'
        };
        return colors[type] || '#383d41';
    }
    
    getAlertBorderColor(type) {
        const colors = {
            success: '#c3e6cb',
            error: '#f5c6cb',
            info: '#bee5eb',
            warning: '#ffeaa7'
        };
        return colors[type] || '#d6d8db';
    }
}

// Global functions for page actions
function previewPage(pageId, imageUrl) {
    const modal = document.getElementById('previewModal');
    const previewImage = document.getElementById('previewImage');
    
    previewImage.src = imageUrl;
    previewImage.alt = `Preview Halaman ${pageId}`;
    modal.style.display = 'block';
}

function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    modal.style.display = 'none';
}

async function deletePage(pageId) {
    if (!confirm('Yakin ingin menghapus halaman ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }
    
    try {
        // Gunakan FormData untuk mengirim data
        const formData = new FormData();
        formData.append('page_id', pageId);
        
        const response = await fetch('delete_page.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Remove page from DOM
            const pageItem = document.querySelector(`[data-page-id="${pageId}"]`);
            if (pageItem) {
                pageItem.style.opacity = '0';
                pageItem.style.transform = 'translateX(-100%)';
                setTimeout(() => {
                    pageItem.remove();
                    // Update UI setelah penghapusan
                    if (window.managePages) {
                        window.managePages.updatePageNumbers();
                        window.managePages.updateOrderPreview();
                        window.managePages.showAlert('Halaman berhasil dihapus!', 'success');
                    }
                    // Refresh page untuk update total count
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }, 300);
            }
        } else {
            throw new Error(data.message || 'Gagal menghapus halaman');
        }
        
    } catch (error) {
        console.error('Error deleting page:', error);
        alert('Terjadi kesalahan saat menghapus halaman: ' + error.message);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.managePages = new ManagePages();
    
    // Close modal when clicking outside
    const modal = document.getElementById('previewModal');
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closePreviewModal();
        }
    });
});