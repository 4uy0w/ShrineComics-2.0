// JavaScript untuk halaman top-up point - VERSI SIMPLIFIED
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.topup-form');
    const telephoneInput = document.getElementById('telephone');
    const pointAmountInput = document.getElementById('point_amount');
    const paymentMethodSelect = document.getElementById('payment_method');

    // Format telephone input (opsional, bisa dihapus jika bermasalah)
    telephoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        // Hapus formatting untuk sementara
        e.target.value = value;
    });

    // Validasi point amount
    pointAmountInput.addEventListener('input', function(e) {
        const value = parseInt(e.target.value) || 0;
        
        if (value < 1000 || value > 100000) {
            this.style.borderColor = '#dc3545';
        } else {
            this.style.borderColor = '#28a745';
        }
    });

    // HAPUS validasi telepon real-time di JavaScript untuk sementara
    // Biarkan PHP yang menangani validasi

    // Validasi sebelum submit - VERSI SEDERHANA
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const errors = [];

        // Validasi required fields saja
        if (!telephoneInput.value.trim()) {
            errors.push('Nomor telepon harus diisi');
            telephoneInput.style.borderColor = '#dc3545';
            isValid = false;
        }

        if (!pointAmountInput.value.trim()) {
            errors.push('Jumlah point harus diisi');
            pointAmountInput.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            const pointAmount = parseInt(pointAmountInput.value);
            if (pointAmount < 1000) {
                errors.push('Minimum top-up 1000 point');
                isValid = false;
            } else if (pointAmount > 100000) {
                errors.push('Maksimum top-up 100.000 point');
                isValid = false;
            }
        }

        if (!paymentMethodSelect.value) {
            errors.push('Metode pembayaran harus dipilih');
            paymentMethodSelect.style.borderColor = '#dc3545';
            isValid = false;
        }

        // Jika ada error, tampilkan alert dan prevent submit
        if (!isValid) {
            e.preventDefault();
            alert('Silakan perbaiki error berikut:\n' + errors.join('\n'));
        } else {
            // Tampilkan konfirmasi
            const confirmation = confirm(
                `Konfirmasi Request Top-Up:\n\n` +
                `Jumlah Point: ${parseInt(pointAmountInput.value).toLocaleString()} points\n` +
                `Metode Bayar: ${paymentMethodSelect.options[paymentMethodSelect.selectedIndex].text}\n\n` +
                `Request akan dikirim ke admin untuk verifikasi.`
            );
            
            if (!confirmation) {
                e.preventDefault();
            }
        }
    });

    console.log('Top-up point form loaded successfully');
});