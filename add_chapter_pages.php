<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$chapter_id = $_GET['chapter_id'] ?? null;
$comic_id = $_GET['comic_id'] ?? null;

// Cek jika chapter_id tidak ada
if (!$chapter_id || !$comic_id) {
    header("Location: dashboard_writer.php");
    exit();
}

// Cek apakah chapter milik user yang login
$sql = "SELECT c.chapter_id, c.chapter_name, c.chapter_comic, com.comic_title 
        FROM chapter c 
        JOIN comic com ON c.chapter_comic = com.comic_title 
        WHERE c.chapter_id = ? AND c.chapter_writer = ? AND com.comic_id = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("isi", $chapter_id, $username, $comic_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard_writer.php");
    exit();
}

$chapter = $result->fetch_assoc();
$stmt->close();

$errors = [];
$success = '';
$uploaded_pages = [];

// Get existing pages count
$pages_count_sql = "SELECT COUNT(*) as total_pages FROM chapter_page WHERE chapter_id = ?";
$pages_count_stmt = $koneksi->prepare($pages_count_sql);
$pages_count_stmt->bind_param("i", $chapter_id);
$pages_count_stmt->execute();
$pages_count_result = $pages_count_stmt->get_result();
$total_pages = $pages_count_result->fetch_assoc()['total_pages'];
$pages_count_stmt->close();

$next_page_number = $total_pages + 1;

// Proses upload halaman
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file upload
    if (isset($_FILES['chapter_pages']) && is_array($_FILES['chapter_pages']['name'])) {
        $uploaded_files = $_FILES['chapter_pages'];
        $start_page = intval($_POST['start_page_number']);
        $current_page = $start_page;
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Create upload directory
        $upload_dir = 'uploads/chapters/' . $chapter_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $success_count = 0;
        
        foreach ($_FILES['chapter_pages']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['chapter_pages']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['chapter_pages']['name'][$key];
                $file_size = $_FILES['chapter_pages']['size'][$key];
                $file_type = $_FILES['chapter_pages']['type'][$key];
                
                // Validasi file
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "File $file_name: Format tidak didukung. Hanya JPEG, JPG, PNG, GIF.";
                    continue;
                }
                
                if ($file_size > $max_size) {
                    $errors[] = "File $file_name: Ukuran file melebihi 5MB.";
                    continue;
                }
                
                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = $current_page . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // Upload file
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    // Simpan ke database
                    $insert_sql = "INSERT INTO chapter_page (chapter_page_number, chapter_page_image, chapter_page_chapter, chapter_page_writer, chapter_id) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $koneksi->prepare($insert_sql);
                    
                    $insert_stmt->bind_param("isssi", $current_page, $upload_path, $chapter['chapter_name'], $username, $chapter_id);
                    if ($insert_stmt->execute()) {
                        $success_count++;
                        $uploaded_pages[] = [
                            'number' => $current_page,
                            'filename' => $new_filename
                        ];
                    } else {
                        $errors[] = "Gagal menyimpan halaman $current_page ke database.";
                        // Hapus file yang sudah diupload
                        unlink($upload_path);
                    }
                    $insert_stmt->close();
                } else {
                    $errors[] = "Gagal mengupload file $file_name.";
                }
                
                $current_page++;
            }
        }
        
        if ($success_count > 0) {
            // Update chapter page count
            $update_sql = "UPDATE chapter SET chapter_page = ? WHERE chapter_id = ?";
            $update_stmt = $koneksi->prepare($update_sql);
            $new_page_count = $total_pages + $success_count;
            $update_stmt->bind_param("ii", $new_page_count, $chapter_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $success = "Berhasil mengupload $success_count halaman!";
            $total_pages = $new_page_count;
            $next_page_number = $total_pages + 1;
        }
    } else {
        $errors[] = "Tidak ada file yang dipilih untuk diupload.";
    }
}

// Get existing pages untuk ditampilkan
$existing_pages_sql = "SELECT * FROM chapter_page WHERE chapter_id = ? ORDER BY chapter_page_number";
$existing_pages_stmt = $koneksi->prepare($existing_pages_sql);
$existing_pages_stmt->bind_param("i", $chapter_id);
$existing_pages_stmt->execute();
$existing_pages = $existing_pages_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Halaman Chapter - ShrineComics</title>
    <link rel="stylesheet" href="style_add_chapter_pages.css">
</head>
<body>
    <div class="upload-pages-container">
        <div class="upload-pages-card">
            <!-- Header -->
            <div class="upload-pages-header">
                <h1>Upload Halaman Chapter</h1>
                <p>
                    <strong>Komik:</strong> <?php echo htmlspecialchars($chapter['comic_title']); ?> | 
                    <strong>Chapter:</strong> <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                </p>
                <div class="header-actions">
                    <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="back-btn">← Kembali ke Komik</a>
                    <a href="dashboard_writer.php" class="back-btn">← Dashboard</a>
                </div>
            </div>

            <!-- Stats Info -->
            <div class="upload-stats">
                <div class="stat-item">
                    <span class="stat-label">Total Halaman:</span>
                    <span class="stat-value"><?php echo $total_pages; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Halaman Berikutnya:</span>
                    <span class="stat-value"><?php echo $next_page_number; ?></span>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <h3>✅ <?php echo $success; ?></h3>
                    <?php if (!empty($uploaded_pages)): ?>
                        <p>Halaman yang berhasil diupload:</p>
                        <ul>
                            <?php foreach ($uploaded_pages as $page): ?>
                                <li>Halaman <?php echo $page['number']; ?> - <?php echo $page['filename']; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <h3>❌ Terjadi Kesalahan</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Upload Form -->
            <form method="POST" action="add_chapter_pages.php?chapter_id=<?php echo $chapter_id; ?>&comic_id=<?php echo $comic_id; ?>" 
                  class="upload-form" enctype="multipart/form-data" id="uploadForm">
                
                <div class="form-group">
                    <label for="start_page_number">Mulai dari Halaman Nomor:</label>
                    <input type="number" id="start_page_number" name="start_page_number" 
                           value="<?php echo $next_page_number; ?>" min="1" required>
                    <small class="help-text">Nomor halaman akan digunakan untuk urutan membaca</small>
                </div>
                
                <div class="form-group">
                    <label for="chapter_pages">Pilih File Halaman:</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="upload-placeholder">
                            <span class="upload-icon">📁</span>
                            <p>Klik untuk memilih file atau drag & drop file di sini</p>
                            <small>Format: JPEG, JPG, PNG, GIF | Maksimal: 5MB per file</small>
                        </div>
                        <input type="file" id="chapter_pages" name="chapter_pages[]" 
                               multiple accept="image/jpeg, image/jpg, image/png, image/gif" 
                               class="file-input">
                    </div>
                    <div class="file-preview" id="filePreview">
                        <h4>File yang dipilih:</h4>
                        <div class="file-list" id="fileList"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span class="btn-icon">📤</span>
                        Upload Halaman
                    </button>
                    <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="cancel-btn">
                        <span class="btn-icon">←</span>
                        Selesai
                    </a>
                </div>
            </form>

            <!-- Existing Pages -->
            <?php if ($existing_pages->num_rows > 0): ?>
                <div class="existing-pages">
                    <h3>Halaman yang Sudah Diupload</h3>
                    <div class="pages-grid" id="pagesGrid">
                        <?php while ($page = $existing_pages->fetch_assoc()): ?>
                            <div class="page-item" data-page-id="<?php echo $page['chapter_page_id']; ?>">
                                <div class="page-thumbnail">
                                    <img src="<?php echo htmlspecialchars($page['chapter_page_image']); ?>" 
                                         alt="Halaman <?php echo $page['chapter_page_number']; ?>"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDEwMCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCIgaGVpZ2h0PSIxNTAiIGZpbGw9IiNGM0Y0RjYiLz48dGV4dCB4PSI1MCIgeT0iNzUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxMiIgZmlsbD0jOTk5OTk5IHRleHQtYW5jaG9yPSJtaWRkbGUiPkhhbWFuYW4gPHQ+c3Bhbj48L3NwYW4+PC90Pjx0PjxzcGFuPjwvc3Bhbj48L3Q+PHA+PHNwYW4+PC9zcGFuPjwvcD48L3RleHQ+PC9zdmc+'">
                                </div>
                                <div class="page-info">
                                    <span class="page-number">Halaman <?php echo $page['chapter_page_number']; ?></span>
                                    <button type="button" class="delete-btn" 
                                            onclick="deletePage(<?php echo $page['chapter_page_id']; ?>, <?php echo $chapter_id; ?>, <?php echo $comic_id; ?>)">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-pages">
                    <p>📄 Belum ada halaman yang diupload untuk chapter ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="script_add_chapter_pages.js"></script>
</body>
</html>

<?php
$existing_pages_stmt->close();
$koneksi->close();
?>

ini adalah upload_chapter_pages.php