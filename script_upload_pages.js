// Upload Pages JavaScript
class UploadPages {
    constructor() {
        this.fileUploadArea = document.getElementById('fileUploadArea');
        this.fileInput = document.getElementById('chapter_pages');
        this.selectedFiles = document.getElementById('selectedFiles');
        this.uploadForm = document.getElementById('uploadForm');
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        console.log('📁 Upload Pages initialized');
    }
    
    bindEvents() {
        // Click to open file dialog
        this.fileUploadArea.addEventListener('click', () => {
            this.fileInput.click();
        });
        
        // Drag and drop events
        this.fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.fileUploadArea.classList.add('dragover');
        });
        
        this.fileUploadArea.addEventListener('dragleave', () => {
            this.fileUploadArea.classList.remove('dragover');
        });
        
        this.fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            this.fileUploadArea.classList.remove('dragover');
            this.fileInput.files = e.dataTransfer.files;
            this.updateSelectedFiles();
        });
        
        // File input change
        this.fileInput.addEventListener('change', () => {
            this.updateSelectedFiles();
        });
        
        // Form submission validation
        this.uploadForm.addEventListener('submit', (e) => {
            if (!this.validateFiles(e)) {
                e.preventDefault();
            }
        });
    }
    
    updateSelectedFiles() {
        this.selectedFiles.innerHTML = '';
        
        if (this.fileInput.files.length > 0) {
            const fileList = document.createElement('div');
            fileList.innerHTML = `<h4>File yang dipilih (${this.fileInput.files.length}):</h4>`;
            
            for (let i = 0; i < this.fileInput.files.length; i++) {
                const file = this.fileInput.files[i];
                const fileItem = this.createFileItem(file);
                fileList.appendChild(fileItem);
            }
            
            this.selectedFiles.appendChild(fileList);
            
            // Update upload area text
            this.fileUploadArea.querySelector('h3').textContent = 'File dipilih!';
            this.fileUploadArea.querySelector('p').textContent = `${this.fileInput.files.length} file siap diupload`;
            this.fileUploadArea.style.borderColor = '#28a745';
            this.fileUploadArea.style.background = '#f0fff4';
        } else {
            // Reset upload area text
            this.fileUploadArea.querySelector('h3').textContent = 'Klik atau drag & drop file di sini';
            this.fileUploadArea.querySelector('p').textContent = 'Pilih beberapa file gambar untuk halaman chapter';
            this.fileUploadArea.style.borderColor = '#dee2e6';
            this.fileUploadArea.style.background = '#fafafa';
        }
    }
    
    createFileItem(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <span>${this.escapeHtml(file.name)}</span>
            <span class="file-size">${this.formatFileSize(file.size)}</span>
        `;
        return fileItem;
    }
    
    validateFiles(e) {
        if (this.fileInput.files.length === 0) {
            this.showAlert('Silakan pilih minimal satu file untuk diupload.', 'error');
            return false;
        }
        
        // Validate each file
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        for (let i = 0; i < this.fileInput.files.length; i++) {
            const file = this.fileInput.files[i];
            
            // Check file type
            if (!allowedTypes.includes(file.type)) {
                this.showAlert(`File "${file.name}" tidak didukung. Hanya JPEG, JPG, PNG, WebP yang diizinkan.`, 'error');
                return false;
            }
            
            // Check file size
            if (file.size > maxSize) {
                this.showAlert(`File "${file.name}" terlalu besar. Maksimal 5MB per file.`, 'error');
                return false;
            }
        }
        
        // Show loading state
        const submitBtn = this.uploadForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="btn-loading">🔄</span> Mengupload...';
        submitBtn.disabled = true;
        
        // Re-enable button if form submission fails
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 5000);
        
        return true;
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.custom-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        const alert = document.createElement('div');
        alert.className = `custom-alert ${type}`;
        alert.innerHTML = `
            <span class="alert-icon">${type === 'error' ? '❌' : 'ℹ️'}</span>
            <span class="alert-message">${message}</span>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Add styles
        Object.assign(alert.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: type === 'error' ? '#f8d7da' : '#d1ecf1',
            color: type === 'error' ? '#721c24' : '#0c5460',
            padding: '15px 20px',
            borderRadius: '8px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            zIndex: '10000',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
            maxWidth: '400px',
            border: `1px solid ${type === 'error' ? '#f5c6cb' : '#bee5eb'}`
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
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.uploadPages = new UploadPages();
});

// Utility function for file validation
function validateImageFile(file) {
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const maxSize = 5 * 1024 * 1024;
    
    if (!allowedTypes.includes(file.type)) {
        return { isValid: false, message: 'Format file tidak didukung' };
    }
    
    if (file.size > maxSize) {
        return { isValid: false, message: 'File terlalu besar (maks. 5MB)' };
    }
    
    return { isValid: true };
}