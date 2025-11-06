<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$errors = [];
$success = '';
$show_options = false;
$new_comic_id = null;

// Daftar genre yang tersedia
$available_genres = ['comedy', 'romance', 'sci-fi', 'adventure'];

// Proses tambah komik
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comic_title = trim($_POST['comic_title']);
    $comic_genre = trim($_POST['comic_genre']);
    $comic_comment = trim($_POST['comic_comment']);
    $comic_banner = '';

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

    // Cek judul komik sudah ada
    if (empty($errors)) {
        $check_title = "SELECT comic_title FROM comic WHERE comic_title = ?";
        $stmt = $koneksi->prepare($check_title);
        $stmt->bind_param("s", $comic_title);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['comic_title'] = "Judul komik sudah digunakan";
        }
        $stmt->close();
    }

    // Handle upload banner
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
                $comic_banner = $upload_path;
            } else {
                $errors['comic_banner'] = "Gagal mengupload banner. Pastikan folder uploads writable.";
            }
        }
    } elseif (isset($_FILES['comic_banner']) && $_FILES['comic_banner']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['comic_banner'] = "Error upload file: " . getUploadError($_FILES['comic_banner']['error']);
    }

    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        $comic_chapter = 0; // Default chapter count
        
        $sql = "INSERT INTO comic (comic_title, comic_writer, comic_chapter, comic_banner, comic_genre, comic_comment) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("ssisss", $comic_title, $username, $comic_chapter, $comic_banner, $comic_genre, $comic_comment);
        
        if ($stmt->execute()) {
            $new_comic_id = $stmt->insert_id;
            $success = "🎉 Komik <strong>'{$comic_title}'</strong> berhasil ditambahkan!";
            $show_options = true;
            
            // Reset form values
            $_POST = array();
        } else {
            $errors['general'] = "Terjadi kesalahan saat menambah komik. Silakan coba lagi.";
            // Hapus file banner jika gagal simpan ke database
            if (!empty($comic_banner) && file_exists($comic_banner)) {
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
    <title>Tambah Komik Baru - ShrineComics</title>
    <link rel="stylesheet" href="style_add_comic.css">
</head>
<body>
    <div class="add-comic-container">
        <div class="add-comic-card">
            <!-- Header Section -->
            <div class="add-comic-header">
                <div class="header-content">
                    <h1>Tambah Komik Baru</h1>
                    <p>Isi detail komik Anda dengan informasi yang menarik</p>
                </div>
                <a href="dashboard_writer.php" class="back-btn">
                    <span>←</span>
                    Kembali ke Dashboard
                </a>
            </div>

            <!-- Success Message dengan Pilihan -->
            <?php if (!empty($success) && $show_options): ?>
                <div class="success-message">
                    <div class="success-icon">✅</div>
                    <div class="success-content">
                        <h3>Berhasil!</h3>
                        <p><?php echo $success; ?></p>
                        
                        <div class="success-options">
                            <p class="options-title">Apa yang ingin Anda lakukan selanjutnya?</p>
                            <div class="option-buttons">
                                <a href="add_chapter.php?comic_id=<?php echo $new_comic_id; ?>&first_chapter=true" 
                                   class="option-btn primary">
                                    <span>📖</span>
                                    Buat Chapter Pertama
                                </a>
                                <a href="comic_detail.php?id=<?php echo $new_comic_id; ?>" 
                                   class="option-btn secondary">
                                    <span>👁️</span>
                                    Lihat Detail Komik
                                </a>
                                <a href="dashboard_writer.php" 
                                   class="option-btn secondary">
                                    <span>📊</span>
                                    Kembali ke Dashboard
                                </a>
                                <a href="add_comic.php" 
                                   class="option-btn secondary">
                                    <span>➕</span>
                                    Tambah Komik Lain
                                </a>
                            </div>
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

            <!-- Form Tambah Komik (hanya tampil jika belum sukses) -->
            <?php if (!$show_options): ?>
            <form method="POST" action="add_comic.php" class="add-comic-form" enctype="multipart/form-data" novalidate>
                <!-- Judul Komik -->
                <div class="form-group">
                    <label for="comic_title" class="form-label">
                        Judul Komik <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="comic_title" 
                           name="comic_title" 
                           value="<?php echo isset($_POST['comic_title']) ? htmlspecialchars($_POST['comic_title']) : ''; ?>" 
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
                                <?php echo (isset($_POST['comic_genre']) && $_POST['comic_genre'] === $genre) ? 'selected' : ''; ?>>
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
                              placeholder="Ceritakan sedikit tentang komik Anda..."><?php echo isset($_POST['comic_comment']) ? htmlspecialchars($_POST['comic_comment']) : ''; ?></textarea>
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
                    
                    <div class="file-upload-wrapper">
                        <div class="file-upload-area" id="fileUploadArea">
                            <div class="upload-placeholder">
                                <div class="upload-icon">🖼️</div>
                                <div class="upload-text">
                                    <p class="upload-title">Klik untuk memilih banner atau drag & drop</p>
                                    <p class="upload-subtitle">Format: JPEG, JPG, PNG, GIF | Maksimal: 2MB</p>
                                </div>
                            </div>
                            <input type="file" 
                                   id="comic_banner" 
                                   name="comic_banner" 
                                   accept="image/jpeg, image/jpg, image/png, image/gif"
                                   class="file-input">
                        </div>
                        
                        <!-- Image Preview -->
                        <div class="image-preview" id="imagePreview">
                            <img src="" alt="Preview banner" class="preview-image" id="previewImage">
                            <div class="preview-overlay">
                                <button type="button" class="preview-remove" id="removePreview">×</button>
                            </div>
                            <div class="preview-placeholder" id="previewPlaceholder">
                                <span class="preview-text">Preview banner akan muncul di sini</span>
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
                        <span class="btn-icon">➕</span>
                        Tambah Komik
                    </button>
                    <a href="dashboard_writer.php" class="cancel-btn">
                        <span class="btn-icon">↩️</span>
                        Batal
                    </a>
                </div>

                <!-- Form Info -->
                <div class="form-info">
                    <p>📝 <strong>Tips:</strong> Pastikan judul komik unik dan deskripsi menarik untuk menarik pembaca!</p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="script_add_comic.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>