<?php
session_start();
require_once 'koneksi.php';

// Cek jika user belum login, redirect ke login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = getSingleRow("SELECT * FROM users WHERE user_id = ?", [$user_id], "i");

// Cek jika user bukan reader, redirect ke profile
if ($user_data['role'] !== 'reader') {
    header("Location: profile.php");
    exit();
}

$errors = [];
$success = '';

// Proses request top-up
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telephone = trim($_POST['telephone']);
    $point_amount = intval($_POST['point_amount']);
    $payment_method = trim($_POST['payment_method']);
    $additional_notes = trim($_POST['additional_notes']);

    // Validasi
    if (empty($telephone)) {
        $errors['telephone'] = "Nomor telepon harus diisi";
    } else {
        // Bersihkan format dari dash dan spasi sebelum membandingkan
        $cleaned_telephone = preg_replace('/\D/', '', $telephone);
        $cleaned_db_telephone = preg_replace('/\D/', '', $user_data['telephone_number']);
        
        if ($cleaned_telephone !== $cleaned_db_telephone) {
            $errors['telephone'] = "Nomor telepon tidak sesuai dengan profil Anda";
        }
    }

    if (empty($point_amount)) {
        $errors['point_amount'] = "Jumlah point harus diisi";
    } elseif ($point_amount < 1000) {
        $errors['point_amount'] = "Minimum top-up 1000 point";
    } elseif ($point_amount > 100000) {
        $errors['point_amount'] = "Maksimum top-up 100.000 point";
    }

    if (empty($payment_method)) {
        $errors['payment_method'] = "Metode pembayaran harus dipilih";
    }

    // Jika tidak ada error, simpan request ke database
    if (empty($errors)) {
        $status = 'pending';

        // PERBAIKAN: Query dengan 6 placeholder (?)
        $sql = "INSERT INTO point_requests (user_id, telephone, point_amount, payment_method, additional_notes, request_date, status) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        
        // Debug: Hitung placeholder
        $placeholder_count = substr_count($sql, '?');
        error_log("SQL Placeholders: " . $placeholder_count);
        error_log("SQL: " . $sql);
        
        $stmt = $koneksi->prepare($sql);
        if ($stmt) {
            // PERBAIKAN: "isiss" = 5 karakter type, tapi kita butuh 6 parameter
            // Sebenarnya kita punya 6 placeholder di SQL, jadi butuh 6 parameter
            
            // Hitung parameter yang akan di-binding:
            // 1. user_id (i)
            // 2. telephone (s) 
            // 3. point_amount (i)
            // 4. payment_method (s)
            // 5. additional_notes (s)
            // 6. status (s)
            // TOTAL: 6 parameter -> "isiss"
            
            $stmt->bind_param("isisss", 
                $user_id, 
                $telephone, 
                $point_amount, 
                $payment_method, 
                $additional_notes, 
                $status
            );
            
            if ($stmt->execute()) {
                $success = "Request top-up point berhasil dikirim! Menunggu konfirmasi admin.";
                // Reset form
                $_POST = array();
            } else {
                $errors['general'] = "Terjadi kesalahan saat mengirim request: " . $stmt->error;
                error_log("Database error: " . $stmt->error);
            }
            
            $stmt->close();
        } else {
            $errors['general'] = "Terjaki kesalahan dalam persiapan query: " . $koneksi->error;
            error_log("Prepare statement error: " . $koneksi->error);
        }
    }
}

// Ambil riwayat request user
$history_query = "
    SELECT * FROM point_requests 
    WHERE user_id = ? 
    ORDER BY request_date DESC 
    LIMIT 5
";
$history_result = executeQuery($history_query, [$user_id], "i");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top-Up Point - ShrineComics</title>
    <link rel="stylesheet" href="topup_point.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="topup-container">
        <!-- Header Navigation -->
        <div class="header-navigation">
            <a href="profile.php" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Profile
            </a>
            <a href="dashboard_reader.php" class="dashboard-btn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2 4L8 1L14 4V11L8 14L2 11V4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6 7.5H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 5.5V10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Reader Dashboard
            </a>
        </div>

        <div class="topup-card">
            <div class="topup-header">
                <h1>Top-Up Point</h1>
                <p>Isi form berikut untuk request penambahan point</p>
                
                <!-- Current Point Info -->
                <div class="current-points">
                    <div class="points-display">
                        <span class="points-label">Point Anda Saat Ini:</span>
                        <span class="points-value"><?php echo number_format($user_data['point']); ?> points</span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="topup_point.php" class="topup-form">
                <div class="form-section">
                    <h3>Informasi Verifikasi</h3>
                    <div class="form-group">
                        <label for="telephone">Nomor Telepon *</label>
                        <input type="tel" id="telephone" name="telephone" required 
                               value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : htmlspecialchars($user_data['telephone_number'] ?? ''); ?>"
                               placeholder="Masukkan nomor telepon yang terdaftar"
                               class="<?php echo isset($errors['telephone']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['telephone'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['telephone']); ?></span>
                        <?php else: ?>
                            <span class="help-text">Harus sesuai dengan nomor telepon di profil Anda</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Detail Top-Up</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="point_amount">Jumlah Point *</label>
                            <input type="number" id="point_amount" name="point_amount" required 
                                   min="1000" max="100000" step="1000"
                                   value="<?php echo isset($_POST['point_amount']) ? htmlspecialchars($_POST['point_amount']) : ''; ?>"
                                   placeholder="Min. 1000 point"
                                   class="<?php echo isset($errors['point_amount']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['point_amount'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['point_amount']); ?></span>
                            <?php else: ?>
                                <span class="help-text">Minimal 1.000, maksimal 100.000 point</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Metode Pembayaran *</label>
                            <select id="payment_method" name="payment_method" required 
                                    class="<?php echo isset($errors['payment_method']) ? 'error' : ''; ?>">
                                <option value="">Pilih Metode</option>
                                <option value="transfer_bank" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'transfer_bank') ? 'selected' : ''; ?>>Transfer Bank</option>
                                <option value="e_wallet" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'e_wallet') ? 'selected' : ''; ?>>E-Wallet</option>
                                <option value="virtual_account" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'virtual_account') ? 'selected' : ''; ?>>Virtual Account</option>
                                <option value="qris" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'qris') ? 'selected' : ''; ?>>QRIS</option>
                            </select>
                            <?php if (isset($errors['payment_method'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['payment_method']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_notes">Catatan Tambahan (Opsional)</label>
                        <textarea id="additional_notes" name="additional_notes" rows="3" 
                                  placeholder="Contoh: Transfer dari BCA, bukti transfer sudah dikirim via WhatsApp..."
                                  class="<?php echo isset($errors['additional_notes']) ? 'error' : ''; ?>"><?php echo isset($_POST['additional_notes']) ? htmlspecialchars($_POST['additional_notes']) : ''; ?></textarea>
                        <?php if (isset($errors['additional_notes'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['additional_notes']); ?></span>
                        <?php else: ?>
                            <span class="help-text">Berikan informasi tambahan untuk mempermudah verifikasi</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-notice">
                    <div class="notice-icon">ℹ️</div>
                    <div class="notice-content">
                        <h4>Proses Top-Up Point</h4>
                        <ol>
                            <li>Isi form dengan data yang valid</li>
                            <li>Request akan dikirim ke admin untuk verifikasi</li>
                            <li>Admin akan memverifikasi data dan pembayaran</li>
                            <li>Point akan ditambahkan ke akun Anda setelah dikonfirmasi</li>
                            <li>Proses verifikasi biasanya memakan waktu 1-2 jam</li>
                        </ol>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 10V12.6667C14 13.0203 13.8595 13.3594 13.6095 13.6095C13.3594 13.8595 13.0203 14 12.6667 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M11.3333 5.33333L8 2L4.66667 5.33333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M8 2V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Kirim Request Top-Up
                    </button>
                </div>
            </form>
        </div>

        <!-- Request History -->
        <div class="history-section">
            <h2>Riwayat Request Top-Up</h2>
            <div class="history-list">
                <?php if ($history_result['success'] && !empty($history_result['data'])): ?>
                    <?php foreach($history_result['data'] as $request): ?>
                    <div class="history-item status-<?php echo $request['status']; ?>">
                        <div class="history-info">
                            <div class="history-main">
                                <span class="history-amount">+<?php echo number_format($request['point_amount']); ?> points</span>
                                <span class="history-method"><?php echo ucfirst(str_replace('_', ' ', $request['payment_method'])); ?></span>
                            </div>
                            <div class="history-details">
                                <span class="history-date"><?php echo date('d M Y H:i', strtotime($request['request_date'])); ?></span>
                                <span class="history-status <?php echo $request['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => 'Menunggu',
                                        'approved' => 'Disetujui',
                                        'rejected' => 'Ditolak'
                                    ];
                                    echo $status_text[$request['status']] ?? $request['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($request['additional_notes'])): ?>
                        <div class="history-notes">
                            <strong>Catatan:</strong> <?php echo htmlspecialchars($request['additional_notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-history">
                        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M24 4C12.954 4 4 12.954 4 24C4 35.046 12.954 44 24 44C35.046 44 44 35.046 44 24C44 12.954 35.046 4 24 4Z" stroke="#6c757d" stroke-width="2"/>
                            <path d="M24 12V24L32 28" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <p>Belum ada riwayat request top-up</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="topup_point.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>