// Upload pages functionality
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('chapter_pages');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const filePreview = document.getElementById('filePreview');
    const fileList = document.getElementById('fileList');
    const startPageNumber = document.getElementById('start_page_number');
    const form = document.querySelector('.upload-form');

    let selectedFiles = [];

    // Drag and drop functionality
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        handleFiles(files);
    });

    // Click to select files
    fileUploadArea.addEventListener('click', function() {
        fileInput.click();
    });

    // File input change
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    // Handle selected files
    function handleFiles(files) {
        selectedFiles = Array.from(files);
        
        if (selectedFiles.length > 0) {
            updateFilePreview();
            filePreview.style.display = 'block';
        } else {
            filePreview.style.display = 'none';
        }
    }

    // Update file preview
    function updateFilePreview() {
        fileList.innerHTML = '';
        
        selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            
            const fileInfo = document.createElement('div');
            fileInfo.innerHTML = `
                <div class="file-name">${file.name}</div>
                <div class="file-size">${formatFileSize(file.size)}</div>
            `;
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'delete-btn';
            removeBtn.textContent = 'Hapus';
            removeBtn.onclick = function() {
                selectedFiles.splice(index, 1);
                updateFilePreview();
                
                if (selectedFiles.length === 0) {
                    filePreview.style.display = 'none';
                    fileInput.value = '';
                }
            };
            
            fileItem.appendChild(fileInfo);
            fileItem.appendChild(removeBtn);
            fileList.appendChild(fileItem);
        });
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const errors = [];

        // Check if files are selected
        if (selectedFiles.length === 0) {
            errors.push('Pilih minimal 1 file halaman');
            isValid = false;
        }

        // Check file count
        if (selectedFiles.length > 50) {
            errors.push('Maksimal 50 file sekaligus');
            isValid = false;
        }

        // Check file sizes and types
        selectedFiles.forEach(file => {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (!allowedTypes.includes(file.type)) {
                errors.push(`File ${file.name}: Hanya JPEG, JPG, PNG, GIF yang diizinkan`);
                isValid = false;
            }

            if (file.size > maxSize) {
                errors.push(`File ${file.name}: Ukuran maksimal 5MB`);
                isValid = false;
            }
        });

        // Check start page number
        if (startPageNumber.value < 1) {
            errors.push('Nomor halaman mulai harus lebih dari 0');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            alert('Silakan perbaiki error berikut:\n' + errors.join('\n'));
        }
    });

    console.log('Upload pages form loaded successfully');
});

// Delete page function
// Delete page function - FIXED VERSION
function deletePage(pageId, chapterId, comicId) {
    if (confirm('Apakah Anda yakin ingin menghapus halaman ini?')) {
        // Show loading state
        const deleteBtn = event.target;
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<div class="loader-spinner-small"></div> Menghapus...';
        deleteBtn.disabled = true;

        // Create form data for POST request (lebih reliable daripada DELETE)
        const formData = new FormData();
        formData.append('page_id', pageId);
        formData.append('chapter_id', chapterId);
        formData.append('comic_id', comicId);
        
        fetch('delete_page.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Hapus element dari DOM
                const pageElement = document.querySelector(`[data-page-id="${pageId}"]`);
                if (pageElement) {
                    pageElement.remove();
                }
                
                // Reload halaman untuk update counter
                setTimeout(() => {
                    location.reload();
                }, 1000);
                
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
}

// Tambahkan CSS untuk loading spinner kecil
const style = document.createElement('style');
style.textContent = `
    .loader-spinner-small {
        width: 16px;
        height: 16px;
        border: 2px solid #ffffff;
        border-top: 2px solid transparent;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: inline-block;
        margin-right: 5px;
    }
`;
document.head.appendChild(style);