<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$comic_id = $_GET['id'] ?? null;

if (!$comic_id) {
    header("Location: dashboard_writer.php");
    exit();
}

// Cek apakah komik milik user yang login
$sql = "SELECT * FROM comic WHERE comic_id = ? AND comic_writer = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("is", $comic_id, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard_writer.php");
    exit();
}

$comic = $result->fetch_assoc();
$stmt->close();

$errors = [];
$success = '';
$current_banner = $comic['comic_banner'];

// Daftar genre yang tersedia
$available_genres = ['comedy', 'romance', 'sci-fi', 'adventure'];

// Proses update komik
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comic_title = trim($_POST['comic_title']);
    $comic_genre = trim($_POST['comic_genre']);
    $comic_comment = trim($_POST['comic_comment']);
    $comic_banner = $current_banner; // Default ke banner saat ini

    // Validasi
    if (empty($comic_title)) {
        $errors['comic_title'] = "Judul komik harus diisi";
    } elseif (strlen($comic_title) < 3) {
        $errors['comic_title'] = "Judul komik minimal 3 karakter";
    }

    if (empty($comic_genre)) {
        $errors['comic_genre'] = "Genre komik harus dipilih";
    } elseif (!in_array($comic_genre, $available_genres)) {
        $errors['comic_genre'] = "Genre tidak valid";
    }

    // Cek judul komik sudah ada (kecuali untuk komik ini)
    if (empty($errors)) {
        $check_title = "SELECT comic_id FROM comic WHERE comic_title = ? AND comic_id != ?";
        $stmt = $koneksi->prepare($check_title);
        $stmt->bind_param("si", $comic_title, $comic_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['comic_title'] = "Judul komik sudah digunakan";
        }
        $stmt->close();
    }

    // Handle upload banner baru
    if (isset($_FILES['comic_banner']) && $_FILES['comic_banner']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file_type = $_FILES['comic_banner']['type'];
        $file_size = $_FILES['comic_banner']['size'];
        $file_name = $_FILES['comic_banner']['name'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors['comic_banner'] = "Hanya file JPEG, JPG, PNG, dan GIF yang diizinkan";
        } elseif ($file_size > $max_size) {
            $errors['comic_banner'] = "Ukuran file maksimal 2MB";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $safe_username = preg_replace('/[^a-zA-Z0-9]/', '_', $username);
            $filename = 'banner_' . uniqid() . '_' . $safe_username . '.' . $file_extension;
            $upload_path = 'uploads/banners/' . $filename;
            
            // Create directory if not exists
            if (!is_dir('uploads/banners')) {
                mkdir('uploads/banners', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['comic_banner']['tmp_name'], $upload_path)) {
                // Hapus banner lama jika ada dan bukan default
                if (!empty($current_banner) && file_exists($current_banner) && strpos($current_banner, 'uploads/banners/') !== false) {
                    unlink($current_banner);
                }
                $comic_banner = $upload_path;
            } else {
                $errors['comic_banner'] = "Gagal mengupload banner. Pastikan folder uploads writable.";
            }
        }
    } elseif (isset($_FILES['comic_banner']) && $_FILES['comic_banner']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['comic_banner'] = "Error upload file: " . getUploadError($_FILES['comic_banner']['error']);
    }

    // Handle remove banner
    if (isset($_POST['remove_banner']) && $_POST['remove_banner'] === '1') {
        if (!empty($current_banner) && file_exists($current_banner) && strpos($current_banner, 'uploads/banners/') !== false) {
            unlink($current_banner);
        }
        $comic_banner = '';
    }

    // Jika tidak ada error, update ke database
    if (empty($errors)) {
        $sql = "UPDATE comic SET comic_title = ?, comic_genre = ?, comic_comment = ?, comic_banner = ? WHERE comic_id = ?";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("ssssi", $comic_title, $comic_genre, $comic_comment, $comic_banner, $comic_id);
        
        if ($stmt->execute()) {
            $success = "🎉 Komik <strong>'{$comic_title}'</strong> berhasil diperbarui!";
            
            // Update data komik yang ditampilkan
            $comic['comic_title'] = $comic_title;
            $comic['comic_genre'] = $comic_genre;
            $comic['comic_comment'] = $comic_comment;
            $comic['comic_banner'] = $comic_banner;
            $current_banner = $comic_banner;
        } else {
            $errors['general'] = "Terjadi kesalahan saat memperbarui komik. Silakan coba lagi.";
            // Hapus file banner baru jika gagal update ke database
            if ($comic_banner !== $current_banner && !empty($comic_banner) && file_exists($comic_banner)) {
                unlink($comic_banner);
            }
        }
        
        $stmt->close();
    }
}

// Helper function untuk error upload
function getUploadError($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi ukuran maksimal server)',
        UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi ukuran maksimal form)',
        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
    ];
    return $errors[$error_code] ?? 'Unknown error';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Komik - <?php echo htmlspecialchars($comic['comic_title']); ?> - ShrineComics</title>
    <link rel="stylesheet" href="style_edit_comic.css">
</head>
<body>
    <div class="edit-comic-container">
        <div class="edit-comic-card">
            <!-- Header Section -->
            <div class="edit-comic-header">
                <div class="header-content">
                    <h1>Edit Komik</h1>
                    <p>Perbarui informasi komik "<?php echo htmlspecialchars($comic['comic_title']); ?>"</p>
                </div>
                <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="back-btn">
                    <span>←</span>
                    Kembali ke Detail
                </a>
            </div>

            <!-- Success Message -->
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <div class="success-icon">✅</div>
                    <div class="success-content">
                        <h3>Berhasil!</h3>
                        <p><?php echo $success; ?></p>
                        <div class="success-actions">
                            <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="action-btn primary">
                                <span>👁️</span>
                                Lihat Komik
                            </a>
                            <a href="dashboard_writer.php" class="action-btn secondary">
                                <span>📊</span>
                                Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <div class="error-icon">⚠️</div>
                    <div class="error-content">
                        <h3>Terjadi Kesalahan</h3>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <?php if (!empty($error)): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Edit Komik -->
            <form method="POST" action="edit_comic.php?id=<?php echo $comic_id; ?>" class="edit-comic-form" enctype="multipart/form-data" novalidate>
                <!-- Judul Komik -->
                <div class="form-group">
                    <label for="comic_title" class="form-label">
                        Judul Komik <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="comic_title" 
                           name="comic_title" 
                           value="<?php echo isset($_POST['comic_title']) ? htmlspecialchars($_POST['comic_title']) : htmlspecialchars($comic['comic_title']); ?>" 
                           class="form-input <?php echo isset($errors['comic_title']) ? 'error' : ''; ?>"
                           placeholder="Masukkan judul komik yang menarik"
                           required
                           minlength="3"
                           maxlength="512">
                    <?php if (isset($errors['comic_title'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['comic_title']); ?></span>
                    <?php else: ?>
                        <span class="help-text">Minimal 3 karakter, maksimal 512 karakter</span>
                    <?php endif; ?>
                </div>

                <!-- Genre -->
                <div class="form-group">
                    <label for="comic_genre" class="form-label">
                        Genre <span class="required">*</span>
                    </label>
                    <select id="comic_genre" 
                            name="comic_genre" 
                            class="form-select <?php echo isset($errors['comic_genre']) ? 'error' : ''; ?>"
                            required>
                        <option value="">Pilih Genre Komik</option>
                        <?php foreach ($available_genres as $genre): ?>
                            <option value="<?php echo $genre; ?>" 
                                <?php echo ((isset($_POST['comic_genre']) ? $_POST['comic_genre'] : $comic['comic_genre']) === $genre) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($genre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['comic_genre'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['comic_genre']); ?></span>
                    <?php else: ?>
                        <span class="help-text">Pilih genre yang paling sesuai dengan komik Anda</span>
                    <?php endif; ?>
                </div>

                <!-- Deskripsi -->
                <div class="form-group">
                    <label for="comic_comment" class="form-label">
                        Deskripsi Komik
                    </label>
                    <textarea id="comic_comment" 
                              name="comic_comment" 
                              rows="5"
                              class="form-textarea <?php echo isset($errors['comic_comment']) ? 'error' : ''; ?>"
                              placeholder="Ceritakan sedikit tentang komik Anda..."><?php echo isset($_POST['comic_comment']) ? htmlspecialchars($_POST['comic_comment']) : htmlspecialchars($comic['comic_comment'] ?? ''); ?></textarea>
                    <div class="textarea-info">
                        <span class="help-text">Deskripsi akan ditampilkan di halaman detail komik</span>
                        <span class="char-count" id="charCount">0/1000 karakter</span>
                    </div>
                </div>

                <!-- Banner Upload -->
                <div class="form-group">
                    <label for="comic_banner" class="form-label">
                        Banner Komik
                    </label>
                    
                    <!-- Current Banner Preview -->
                    <?php if (!empty($current_banner)): ?>
                    <div class="current-banner-section">
                        <h4>Banner Saat Ini:</h4>
                        <div class="current-banner-preview">
                            <img src="<?php echo htmlspecialchars($current_banner); ?>" 
                                 alt="Current banner" 
                                 class="current-banner-image">
                            <div class="banner-actions">
                                <label class="remove-banner-btn">
                                    <input type="checkbox" name="remove_banner" value="1">
                                    <span class="checkmark"></span>
                                    Hapus Banner
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="file-upload-wrapper">
                        <div class="file-upload-area" id="fileUploadArea">
                            <div class="upload-placeholder">
                                <div class="upload-icon">🖼️</div>
                                <div class="upload-text">
                                    <p class="upload-title">Klik untuk memilih banner baru atau drag & drop</p>
                                    <p class="upload-subtitle">Format: JPEG, JPG, PNG, GIF | Maksimal: 2MB</p>
                                    <?php if (empty($current_banner)): ?>
                                        <p class="upload-note">📝 Saat ini tidak ada banner</p>
                                    <?php else: ?>
                                        <p class="upload-note">📝 Upload banner baru untuk mengganti yang saat ini</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="file" 
                                   id="comic_banner" 
                                   name="comic_banner" 
                                   accept="image/jpeg, image/jpg, image/png, image/gif"
                                   class="file-input">
                        </div>
                        
                        <!-- New Image Preview -->
                        <div class="image-preview" id="imagePreview">
                            <img src="" alt="Preview banner baru" class="preview-image" id="previewImage">
                            <div class="preview-overlay">
                                <button type="button" class="preview-remove" id="removePreview">×</button>
                            </div>
                            <div class="preview-placeholder" id="previewPlaceholder">
                                <span class="preview-text">Preview banner baru akan muncul di sini</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($errors['comic_banner'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['comic_banner']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        <span class="btn-icon">💾</span>
                        Simpan Perubahan
                    </button>
                    <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="cancel-btn">
                        <span class="btn-icon">❌</span>
                        Batal
                    </a>
                    <a href="delete_comic.php?id=<?php echo $comic_id; ?>" class="delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus komik ini? Tindakan ini tidak dapat dibatalkan!')">
                        <span class="btn-icon">🗑️</span>
                        Hapus Komik
                    </a>
                </div>

                <!-- Form Info -->
                <div class="form-info">
                    <p>💡 <strong>Tips:</strong> Pastikan informasi komik selalu up-to-date untuk menarik lebih banyak pembaca!</p>
                </div>
            </form>
        </div>
    </div>

    <script src="script_edit_comic.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>