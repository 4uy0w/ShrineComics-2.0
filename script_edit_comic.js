// Enhanced form functionality for edit comic
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.edit-comic-form');
    const comicTitle = document.getElementById('comic_title');
    const comicGenre = document.getElementById('comic_genre');
    const comicComment = document.getElementById('comic_comment');
    const charCount = document.getElementById('charCount');
    const fileInput = document.getElementById('comic_banner');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const imagePreview = document.getElementById('imagePreview');
    const previewImage = document.getElementById('previewImage');
    const previewPlaceholder = document.getElementById('previewPlaceholder');
    const previewOverlay = document.querySelector('.preview-overlay');
    const removePreview = document.getElementById('removePreview');
    const removeBannerCheckbox = document.querySelector('input[name="remove_banner"]');

    // Character counter for description
    if (comicComment && charCount) {
        comicComment.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = `${length}/1000 karakter`;
            
            if (length > 1000) {
                charCount.style.color = '#dc3545';
            } else if (length > 800) {
                charCount.style.color = '#ffc107';
            } else {
                charCount.style.color = '#6c757d';
            }
        });

        // Initialize character count
        comicComment.dispatchEvent(new Event('input'));
    }

    // Real-time validation for title
    if (comicTitle) {
        comicTitle.addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length >= 3) {
                this.style.borderColor = '#28a745';
            } else if (value.length > 0) {
                this.style.borderColor = '#ffc107';
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });

        comicTitle.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value.length > 0 && value.length < 3) {
                this.style.borderColor = '#dc3545';
            }
        });
    }

    // Real-time validation for genre
    if (comicGenre) {
        comicGenre.addEventListener('change', function() {
            if (this.value) {
                this.style.borderColor = '#28a745';
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });
    }

    // File upload functionality
    if (fileInput && fileUploadArea) {
        // Drag and drop
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
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelection(files[0]);
            }
        });

        // Click to select file
        fileUploadArea.addEventListener('click', function() {
            fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }

    // Handle file selection and preview
    function handleFileSelection(file) {
        if (file) {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                alert('Hanya file JPEG, JPG, PNG, dan GIF yang diizinkan');
                return;
            }

            if (file.size > maxSize) {
                alert('Ukuran file maksimal 2MB');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewImage.style.display = 'block';
                previewPlaceholder.style.display = 'none';
                previewOverlay.style.display = 'block';
                imagePreview.style.borderColor = '#28a745';
            };
            reader.readAsDataURL(file);
        }
    }

    // Remove preview
    if (removePreview) {
        removePreview.addEventListener('click', function(e) {
            e.preventDefault();
            fileInput.value = '';
            previewImage.style.display = 'none';
            previewPlaceholder.style.display = 'block';
            previewOverlay.style.display = 'none';
            imagePreview.style.borderColor = '#e1e5e9';
        });
    }

    // Remove banner confirmation
    if (removeBannerCheckbox) {
        removeBannerCheckbox.addEventListener('change', function() {
            if (this.checked) {
                const confirmed = confirm('Apakah Anda yakin ingin menghapus banner komik?');
                if (!confirmed) {
                    this.checked = false;
                }
            }
        });
    }

    // Form validation
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const errors = [];

            // Required fields validation
            const requiredFields = [
                { element: comicTitle, name: 'Judul komik' },
                { element: comicGenre, name: 'Genre' }
            ];

            requiredFields.forEach(field => {
                if (!field.element.value.trim()) {
                    errors.push(`${field.name} harus diisi`);
                    field.element.style.borderColor = '#dc3545';
                    isValid = false;
                }
            });

            // Title length validation
            if (comicTitle.value.trim().length < 3) {
                errors.push('Judul komik minimal 3 karakter');
                isValid = false;
            }

            // File validation (if file is selected)
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                const maxSize = 2 * 1024 * 1024;

                if (!allowedTypes.includes(file.type)) {
                    errors.push('Hanya file JPEG, JPG, PNG, dan GIF yang diizinkan');
                    isValid = false;
                }

                if (file.size > maxSize) {
                    errors.push('Ukuran file maksimal 2MB');
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
                
                // Show error messages in a nice way
                const errorHtml = errors.map(error => 
                    `<li style="margin: 5px 0; padding-left: 10px;">${error}</li>`
                ).join('');
                
                const errorAlert = `
                    <div style="
                        background: #f8d7da;
                        color: #721c24;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 10px 0;
                        border: 1px solid #f5c6cb;
                    ">
                        <strong>⚠️ Silakan perbaiki error berikut:</strong>
                        <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                            ${errorHtml}
                        </ul>
                    </div>
                `;
                
                // Insert error message at the top of the form
                const existingError = form.querySelector('.custom-error-alert');
                if (existingError) {
                    existingError.remove();
                }
                
                form.insertAdjacentHTML('afterbegin', 
                    `<div class="custom-error-alert">${errorAlert}</div>`
                );

                // Scroll to top of form
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    console.log('Edit comic form enhanced successfully');
});