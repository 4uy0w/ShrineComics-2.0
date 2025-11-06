<?php
session_start();
require_once 'koneksi.php';

// Cek authentication dan role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$chapter_id = $_GET['chapter_id'] ?? 0;

// Ambil data chapter dan komik
$chapter_sql = "SELECT c.*, co.comic_id, co.comic_title, co.comic_writer 
                FROM chapter c 
                JOIN comic co ON c.chapter_comic = co.comic_title 
                WHERE c.chapter_id = ?";
$stmt = $koneksi->prepare($chapter_sql);
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$chapter_result = $stmt->get_result();

if ($chapter_result->num_rows === 0) {
    die("Chapter tidak ditemukan!");
}

$chapter = $chapter_result->fetch_assoc();
$stmt->close();

// Cek apakah user adalah writer komik ini
$user_sql = "SELECT username, role FROM users WHERE user_id = ?";
$stmt = $koneksi->prepare($user_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

if ($user['role'] !== 'writer' || $user['username'] !== $chapter['comic_writer']) {
    die("Anda tidak memiliki akses untuk mengupload halaman chapter ini!");
}

// Ambil halaman yang sudah diupload
$pages_sql = "SELECT * FROM chapter_page WHERE chapter_page_chapter = ? ORDER BY chapter_page_number ASC";
$stmt = $koneksi->prepare($pages_sql);
$stmt->bind_param("s", $chapter['chapter_name']);
$stmt->execute();
$pages_result = $stmt->get_result();
$existing_pages = $pages_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_existing_pages = count($existing_pages);
$next_page_number = $total_existing_pages + 1;

// Proses upload file
$upload_success = null;
$upload_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['chapter_pages'])) {
    $uploaded_files = $_FILES['chapter_pages'];
    $uploaded_count = 0;
    $current_page_number = $next_page_number;
    
    // Konfigurasi upload
    $upload_dir = "chapter_pages/";
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Process each uploaded file
    for ($i = 0; $i < count($uploaded_files['name']); $i++) {
        if ($uploaded_files['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $uploaded_files['name'][$i];
            $file_tmp = $uploaded_files['tmp_name'][$i];
            $file_size = $uploaded_files['size'][$i];
            $file_type = $uploaded_files['type'][$i];
            
            // Validasi file
            if (!in_array($file_type, $allowed_types)) {
                $upload_error = "File type tidak didukung. Hanya JPEG, JPG, PNG, WebP yang diizinkan.";
                continue;
            }
            
            if ($file_size > $max_size) {
                $upload_error = "File terlalu besar. Maksimal 5MB per file.";
                continue;
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = "chapter_{$chapter_id}_page_{$current_page_number}." . $file_extension;
            $destination = $upload_dir . $new_filename;
            
            // Upload file
            if (move_uploaded_file($file_tmp, $destination)) {
                // Simpan ke database
                $insert_sql = "INSERT INTO chapter_page (chapter_page_number, chapter_page_image, chapter_page_chapter, chapter_page_writer) 
                              VALUES (?, ?, ?, ?)";
                $stmt = $koneksi->prepare($insert_sql);
                $stmt->bind_param("isss", $current_page_number, $destination, $chapter['chapter_name'], $user['username']);
                
                if ($stmt->execute()) {
                    $uploaded_count++;
                    $current_page_number++;
                } else {
                    $upload_error = "Gagal menyimpan data halaman ke database.";
                    // Hapus file yang sudah diupload jika gagal simpan ke database
                    unlink($destination);
                }
                $stmt->close();
            } else {
                $upload_error = "Gagal mengupload file: " . $file_name;
            }
        }
    }
    
    if ($uploaded_count > 0) {
        $upload_success = "Berhasil mengupload {$uploaded_count} halaman baru!";
        // Update total halaman di tabel chapter
        $new_total_pages = $total_existing_pages + $uploaded_count;
        $update_sql = "UPDATE chapter SET chapter_page = ? WHERE chapter_id = ?";
        $stmt = $koneksi->prepare($update_sql);
        $stmt->bind_param("ii", $new_total_pages, $chapter_id);
        $stmt->execute();
        $stmt->close();
        
        // Refresh data halaman
        $stmt = $koneksi->prepare($pages_sql);
        $stmt->bind_param("s", $chapter['chapter_name']);
        $stmt->execute();
        $pages_result = $stmt->get_result();
        $existing_pages = $pages_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $total_existing_pages = count($existing_pages);
        $next_page_number = $total_existing_pages + 1;
    } elseif (!$upload_error) {
        $upload_error = "Tidak ada file yang berhasil diupload.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Halaman - <?= htmlspecialchars($chapter['chapter_name']) ?></title>
    <link rel="stylesheet" href="style_comic_detail.css">
    <link rel="stylesheet" href="style_upload_pages.css">
</head>
<body>
    <nav class="top-navigation">
        <div class="nav-container">
            <a href="edit_chapter.php?chapter_id=<?= $chapter_id ?>" class="btn btn-back-dashboard">
                ← Kembali ke Edit Chapter
            </a>
            <h1 class="nav-title">Upload Halaman Baru</h1>
        </div>
    </nav>

    <div class="upload-container">
        <!-- Header Info -->
        <div class="upload-header">
            <h1>🖼️ Upload Halaman Baru</h1>
            <p>Chapter: <strong><?= htmlspecialchars($chapter['chapter_name']) ?></strong></p>
            <p>Komik: <strong><?= htmlspecialchars($chapter['comic_title']) ?></strong></p>
        </div>

        <!-- Upload Info -->
        <div class="upload-info">
            <h3>📋 Informasi Upload</h3>
            <p><strong>Halaman saat ini:</strong> <?= $total_existing_pages ?> halaman</p>
            <p><strong>Halaman berikutnya:</strong> Mulai dari halaman #<?= $next_page_number ?></p>
            <p><strong>Format yang didukung:</strong> JPEG, JPG, PNG, WebP (maks. 5MB per file)</p>
            <p><strong>Tips:</strong> Upload beberapa halaman sekaligus untuk efisiensi</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($upload_success): ?>
        <div class="success-message">
            ✅ <?= htmlspecialchars($upload_success) ?>
        </div>
        <?php endif; ?>

        <?php if ($upload_error): ?>
        <div class="error-message">
            ❌ <?= htmlspecialchars($upload_error) ?>
        </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
            <div class="form-group">
                <label class="upload-label">Pilih Halaman Baru</label>
                
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="upload-icon">📁</div>
                    <h3>Klik atau drag & drop file di sini</h3>
                    <p>Pilih beberapa file gambar untuk halaman chapter</p>
                    <input type="file" name="chapter_pages[]" id="chapter_pages" 
                           class="file-input" multiple 
                           accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                
                <div class="selected-files" id="selectedFiles">
                    <!-- Selected files will appear here -->
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">
                    <span class="btn-icon">🚀</span>
                    Upload Halaman Baru
                </button>
            </div>
        </form>

        <!-- Existing Pages -->
        <div class="existing-pages">
            <h3>📑 Halaman yang Sudah Diupload (<?= $total_existing_pages ?>)</h3>
            
            <?php if ($total_existing_pages > 0): ?>
            <div class="pages-grid">
                <?php foreach ($existing_pages as $page): ?>
                <div class="page-card">
                    <img src="<?= htmlspecialchars($page['chapter_page_image']) ?>" 
                         alt="Halaman <?= $page['chapter_page_number'] ?>" 
                         class="page-image"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGVlMmU2Ii8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIE5vdCBGb3VuZDwvdGV4dD48L3N2Zz4='">
                    <div class="page-info">
                        <div class="page-number">Halaman #<?= $page['chapter_page_number'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-pages">
                <div class="empty-icon">📄</div>
                <h3>Belum Ada Halaman</h3>
                <p>Upload halaman pertama untuk chapter ini</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="script_upload_pages.js"></script>
</body>
</html>