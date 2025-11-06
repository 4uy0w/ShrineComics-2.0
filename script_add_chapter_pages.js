// Upload pages functionality
class ChapterPagesUploader {
    constructor() {
        this.selectedFiles = [];
        this.init();
    }

    init() {
        this.initializeFileUpload();
        this.initializeFormSubmission();
        this.initializeEventListeners();
    }

    initializeFileUpload() {
        const fileInput = document.getElementById('chapter_pages');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const filePreview = document.getElementById('filePreview');
        const fileList = document.getElementById('fileList');

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            this.handleFiles(files);
        });

        // Click to select files
        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });
    }

    handleFiles(files) {
        this.selectedFiles = Array.from(files);
        this.updateFilePreview();
        
        if (this.selectedFiles.length > 0) {
            document.getElementById('filePreview').style.display = 'block';
        } else {
            document.getElementById('filePreview').style.display = 'none';
        }
    }

    updateFilePreview() {
        const fileList = document.getElementById('fileList');
        fileList.innerHTML = '';
        
        this.selectedFiles.forEach((file, index) => {
            const fileItem = this.createFileItem(file, index);
            fileList.appendChild(fileItem);
        });
    }

    createFileItem(file, index) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        fileInfo.innerHTML = `
            <div class="file-name">${file.name}</div>
            <div class="file-size">${this.formatFileSize(file.size)}</div>
            <div class="file-type">${file.type}</div>
        `;
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'file-remove';
        removeBtn.innerHTML = '×';
        removeBtn.onclick = () => {
            this.selectedFiles.splice(index, 1);
            this.updateFilePreview();
            
            if (this.selectedFiles.length === 0) {
                document.getElementById('filePreview').style.display = 'none';
                document.getElementById('chapter_pages').value = '';
            }
        };
        
        fileItem.appendChild(fileInfo);
        fileItem.appendChild(removeBtn);
        return fileItem;
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    initializeFormSubmission() {
        const form = document.getElementById('uploadForm');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', (e) => {
            const errors = this.validateForm();
            
            if (errors.length > 0) {
                e.preventDefault();
                this.showErrors(errors);
            } else {
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="btn-icon">⏳</span> Mengupload...';
            }
        });
    }

    validateForm() {
        const errors = [];

        // Check if files are selected
        if (this.selectedFiles.length === 0) {
            errors.push('Pilih minimal 1 file halaman');
        }

        // Check file sizes and types
        this.selectedFiles.forEach(file => {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (!allowedTypes.includes(file.type)) {
                errors.push(`File ${file.name}: Hanya JPEG, JPG, PNG, GIF yang diizinkan`);
            }

            if (file.size > maxSize) {
                errors.push(`File ${file.name}: Ukuran maksimal 5MB`);
            }
        });

        // Check start page number
        const startPage = document.getElementById('start_page_number');
        if (startPage.value < 1) {
            errors.push('Nomor halaman mulai harus lebih dari 0');
        }

        return errors;
    }

    showErrors(errors) {
        const errorHtml = errors.map(error => 
            `<li>${error}</li>`
        ).join('');
        
        const errorAlert = `
            <div class="error-alert">
                <strong>⚠️ Silakan perbaiki error berikut:</strong>
                <ul>${errorHtml}</ul>
            </div>
        `;
        
        // Remove existing error alerts
        const existingError = document.querySelector('.error-alert');
        if (existingError) {
            existingError.remove();
        }
        
        // Insert error message at the top of the form
        const form = document.getElementById('uploadForm');
        form.insertAdjacentHTML('afterbegin', errorAlert);

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    initializeEventListeners() {
        console.log('Chapter pages uploader initialized');
    }
}

// Delete page function
function deletePage(pageId, chapterId, comicId) {
    if (!confirm('Apakah Anda yakin ingin menghapus halaman ini?')) {
        return;
    }

    const deleteBtn = event.target;
    const originalText = deleteBtn.innerHTML;
    
    // Show loading state
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = 'Menghapus...';

    // Create form data
    const formData = new FormData();
    formData.append('page_id', pageId);
    formData.append('chapter_id', chapterId);
    formData.append('comic_id', comicId);
    formData.append('action', 'delete_page');
    
    fetch('delete_page.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network error');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove page element with animation
            const pageElement = document.querySelector(`[data-page-id="${pageId}"]`);
            if (pageElement) {
                pageElement.style.opacity = '0';
                pageElement.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    pageElement.remove();
                    // Reload page to update counters
                    location.reload();
                }, 300);
            } else {
                location.reload();
            }
        } else {
            alert('Gagal menghapus halaman: ' + data.message);
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat menghapus halaman');
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ChapterPagesUploader();
});