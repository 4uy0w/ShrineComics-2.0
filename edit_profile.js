// JavaScript untuk halaman edit profile
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.edit-profile-form');
    const photoInput = document.getElementById('photo_profile');
    const currentAvatar = document.querySelector('.current-avatar');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const currentPassword = document.getElementById('current_password');

    // Preview foto profil saat memilih file baru
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validasi ukuran file
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file maksimal 2MB');
                photoInput.value = '';
                return;
            }

            // Validasi tipe file
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Format file harus JPG, PNG, atau GIF');
                photoInput.value = '';
                return;
            }

            // Create preview
            const reader = new FileReader();
            
            reader.onload = function(e) {
                currentAvatar.src = e.target.result;
            };
            
            reader.readAsDataURL(file);
        }
    });

    // Validasi password match
    function validatePasswordMatch() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.style.borderColor = '#dc3545';
            } else {
                confirmPassword.style.borderColor = '#28a745';
            }
        } else {
            confirmPassword.style.borderColor = '#e1e5e9';
        }
    }

    // Toggle required fields untuk password
    function togglePasswordRequirements() {
        const anyPasswordFilled = currentPassword.value || newPassword.value || confirmPassword.value;
        
        if (anyPasswordFilled) {
            currentPassword.required = true;
            newPassword.required = true;
            confirmPassword.required = true;
        } else {
            currentPassword.required = false;
            newPassword.required = false;
            confirmPassword.required = false;
        }
    }

    // Event listeners
    newPassword.addEventListener('input', validatePasswordMatch);
    confirmPassword.addEventListener('input', validatePasswordMatch);
    
    currentPassword.addEventListener('input', togglePasswordRequirements);
    newPassword.addEventListener('input', togglePasswordRequirements);
    confirmPassword.addEventListener('input', togglePasswordRequirements);

    // Validasi sebelum submit
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const errors = [];

        // Validasi required fields
        const requiredFields = [
            { element: document.getElementById('username'), name: 'Username' },
            { element: document.getElementById('email'), name: 'Email' }
        ];

        requiredFields.forEach(field => {
            if (!field.element.value.trim()) {
                errors.push(`${field.name} harus diisi`);
                field.element.style.borderColor = '#dc3545';
                isValid = false;
            }
        });

        // Validasi password jika ada yang diisi
        const passwordFields = [currentPassword, newPassword, confirmPassword];
        const anyPasswordFilled = passwordFields.some(field => field.value.trim());
        const allPasswordsFilled = passwordFields.every(field => field.value.trim());

        if (anyPasswordFilled && !allPasswordsFilled) {
            errors.push('Semua field password harus diisi untuk mengubah password');
            isValid = false;
        }

        if (newPassword.value && newPassword.value.length < 6) {
            errors.push('Password baru minimal 6 karakter');
            isValid = false;
        }

        if (newPassword.value !== confirmPassword.value) {
            errors.push('Konfirmasi password tidak sesuai');
            isValid = false;
        }

        // Jika ada error, tampilkan alert dan prevent submit
        if (!isValid) {
            e.preventDefault();
            alert('Silakan perbaiki error berikut:\n' + errors.join('\n'));
        }
    });

    // Real-time validation untuk username length
    const username = document.getElementById('username');
    username.addEventListener('input', function() {
        if (this.value.length > 0 && this.value.length < 3) {
            this.style.borderColor = '#dc3545';
        } else if (this.value.length >= 3) {
            this.style.borderColor = '#28a745';
        } else {
            this.style.borderColor = '#e1e5e9';
        }
    });

    console.log('Edit profile form loaded successfully');
});