// Validasi real-time pada form register
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.register-form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const username = document.getElementById('username');
    const email = document.getElementById('email');
    const photoInput = document.getElementById('photo_profile');
    const photoPreview = document.getElementById('photoPreview');

    // Default preview state
    function setDefaultPreview() {
        photoPreview.innerHTML = `
            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 12C13.6569 12 15 10.6569 15 9C15 7.34315 13.6569 6 12 6C10.3431 6 9 7.34315 9 9C9 10.6569 10.3431 12 12 12Z" stroke="#666" stroke-width="2"/>
                <path d="M20 17.589C20 17.589 18.309 15 12 15C5.691 15 4 17.589 4 17.589V19C4 20.1046 4.89543 21 6 21H18C19.1046 21 20 20.1046 20 19V17.589Z" stroke="#666" stroke-width="2"/>
            </svg>
            <div class="preview-text">Klik untuk memilih foto</div>
        `;
        photoPreview.classList.remove('has-image');
    }

    // Set default preview on load
    setDefaultPreview();

    // Preview foto profil - SIMPLIFIED VERSION
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            // Validasi ukuran file
            if (file.size > 2 * 1024 * 1024) {
                showPhotoError('Ukuran file maksimal 2MB');
                photoInput.value = '';
                setDefaultPreview();
                return;
            }

            // Validasi tipe file
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                showPhotoError('Format file harus JPG, PNG, atau GIF');
                photoInput.value = '';
                setDefaultPreview();
                return;
            }

            // Clear any existing errors
            clearPhotoError();

            // Create preview
            const reader = new FileReader();
            
            reader.onload = function(e) {
                photoPreview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview Foto Profil">
                    <div class="hover-text">Ganti Foto</div>
                `;
                photoPreview.classList.add('has-image');
            };
            
            reader.onerror = function() {
                showPhotoError('Gagal membaca file');
                setDefaultPreview();
            };
            
            reader.readAsDataURL(file);
        } else {
            setDefaultPreview();
        }
    });

    // Click on photo preview to trigger file input
    photoPreview.addEventListener('click', function() {
        photoInput.click();
    });

    function showPhotoError(message) {
        // Remove previous error
        clearPhotoError();
        
        // Add new error
        const errorSpan = document.createElement('span');
        errorSpan.className = 'error-text';
        errorSpan.textContent = message;
        errorSpan.style.display = 'block';
        errorSpan.style.marginTop = '5px';
        
        photoInput.parentNode.appendChild(errorSpan);
    }

    function clearPhotoError() {
        const existingError = photoInput.parentNode.querySelector('.error-text');
        if (existingError) {
            existingError.remove();
        }
    }

    // Validasi password match
    function validatePasswordMatch() {
        if (password.value && confirmPassword.value) {
            if (password.value !== confirmPassword.value) {
                confirmPassword.style.borderColor = '#dc3545';
                if (!document.getElementById('confirm-error')) {
                    const errorSpan = document.createElement('span');
                    errorSpan.id = 'confirm-error';
                    errorSpan.className = 'error-text';
                    errorSpan.textContent = 'Konfirmasi password tidak sesuai';
                    confirmPassword.parentNode.appendChild(errorSpan);
                }
            } else {
                confirmPassword.style.borderColor = '#28a745';
                const errorSpan = document.getElementById('confirm-error');
                if (errorSpan) {
                    errorSpan.remove();
                }
            }
        }
    }

    // Validasi panjang username
    function validateUsername() {
        if (username.value.length > 0 && username.value.length < 3) {
            username.style.borderColor = '#dc3545';
        } else if (username.value.length >= 3) {
            username.style.borderColor = '#28a745';
        } else {
            username.style.borderColor = '#e1e5e9';
        }
    }

    // Validasi format email
    function validateEmail() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email.value && !emailRegex.test(email.value)) {
            email.style.borderColor = '#dc3545';
        } else if (email.value && emailRegex.test(email.value)) {
            email.style.borderColor = '#28a745';
        } else {
            email.style.borderColor = '#e1e5e9';
        }
    }

    // Event listeners untuk real-time validation
    password.addEventListener('input', validatePasswordMatch);
    confirmPassword.addEventListener('input', validatePasswordMatch);
    username.addEventListener('input', validateUsername);
    email.addEventListener('input', validateEmail);

    // Validasi sebelum submit
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const errors = [];

        // Validasi required fields
        const requiredFields = [
            { element: username, name: 'Username' },
            { element: email, name: 'Email' },
            { element: password, name: 'Password' },
            { element: confirmPassword, name: 'Konfirmasi Password' },
            { element: document.getElementById('role'), name: 'Role' }
        ];

        requiredFields.forEach(field => {
            if (!field.element.value.trim()) {
                errors.push(`${field.name} harus diisi`);
                field.element.style.borderColor = '#dc3545';
                isValid = false;
            }
        });

        // Validasi password match
        if (password.value !== confirmPassword.value) {
            errors.push('Konfirmasi password tidak sesuai');
            isValid = false;
        }

        // Validasi panjang password
        if (password.value && password.value.length < 6) {
            errors.push('Password minimal 6 karakter');
            isValid = false;
        }

        // Validasi file
        if (photoInput.files.length > 0) {
            const file = photoInput.files[0];
            if (file.size > 2 * 1024 * 1024) {
                errors.push('Ukuran foto profil maksimal 2MB');
                isValid = false;
            }
            
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                errors.push('Format foto profil harus JPG, PNG, atau GIF');
                isValid = false;
            }
        }

        if (!isValid) {
            e.preventDefault();
            alert('Silakan perbaiki error berikut:\n' + errors.join('\n'));
        }
    });

    console.log('Register form loaded successfully');
});