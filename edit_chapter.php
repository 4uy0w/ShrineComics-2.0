<?php
session_start();
require_once 'koneksi.php';

// Cek authentication dan role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$chapter_id = $_GET['chapter_id'] ?? 0;

// Ambil data chapter
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
    die("Anda tidak memiliki akses untuk mengedit chapter ini!");
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chapter_number = $_POST['chapter_number'];
    $chapter_name = $_POST['chapter_name'];
    $chapter_page = $_POST['chapter_page'];
    $chapter_price = $_POST['chapter_price'] ?? 0;
    $chapter_release_date = $_POST['chapter_release_date'];
    $chapter_status = $_POST['chapter_status'];
    
    // Update ke database
    $update_sql = "UPDATE chapter SET 
                   chapter_number = ?,
                   chapter_name = ?,
                   chapter_page = ?,
                   chapter_price = ?,
                   chapter_release_date = ?,
                   chapter_status = ?
                   WHERE chapter_id = ?";
    
    $stmt = $koneksi->prepare($update_sql);
    $stmt->bind_param("isiissi", $chapter_number, $chapter_name, $chapter_page, $chapter_price, $chapter_release_date, $chapter_status, $chapter_id);
    
    if ($stmt->execute()) {
        $success = "Chapter berhasil diperbarui!";
        // Refresh data chapter
        $stmt->close();
        $stmt = $koneksi->prepare($chapter_sql);
        $stmt->bind_param("i", $chapter_id);
        $stmt->execute();
        $chapter_result = $stmt->get_result();
        $chapter = $chapter_result->fetch_assoc();
    } else {
        $error = "Gagal memperbarui chapter: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Chapter - <?= htmlspecialchars($chapter['chapter_name']) ?></title>
    <link rel="stylesheet" href="style_comic_detail.css">
    <link rel="stylesheet" href="style_edit_chapter.css">
</head>
<body>
    <nav class="top-navigation">
        <div class="nav-container">
            <a href="comic_detail.php?id=<?= $chapter['comic_id'] ?>" class="btn btn-back-dashboard">
                ← Kembali ke Detail Komik
            </a>
            <h1 class="nav-title">Edit Chapter</h1>
        </div>
    </nav>

    <div class="form-container">
        <div class="form-header">
            <h1>✏️ Edit Chapter</h1>
            <p>Komik: <strong><?= htmlspecialchars($chapter['comic_title']) ?></strong></p>
        </div>

        <div class="comic-info-banner">
            <h3>Informasi Chapter Saat Ini</h3>
            <p><strong>Chapter:</strong> #<?= $chapter['chapter_number'] ?> - <?= htmlspecialchars($chapter['chapter_name']) ?></p>
            <p><strong>Status:</strong> 
                <span class="status-badge status-<?= $chapter['chapter_status'] ?>">
                    <?= $chapter['chapter_status'] === 'upload' ? '✅ Uploaded' : '⏳ Pending' ?>
                </span>
            </p>
            <p><strong>Tanggal Rilis:</strong> <?= date('d M Y', strtotime($chapter['chapter_release_date'])) ?></p>
            <p><strong>Harga:</strong> <?= $chapter['chapter_price'] > 0 ? $chapter['chapter_price'] . ' points' : 'Gratis' ?></p>
        </div>

        <?php if (isset($success)): ?>
        <div class="success-message">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="error-message">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="chapter-form" id="editChapterForm">
            <div class="form-group">
                <label for="chapter_number">Nomor Chapter *</label>
                <input type="number" id="chapter_number" name="chapter_number" 
                       value="<?= htmlspecialchars($chapter['chapter_number']) ?>" 
                       min="1" required class="form-input">
            </div>

            <div class="form-group">
                <label for="chapter_name">Nama Chapter *</label>
                <input type="text" id="chapter_name" name="chapter_name" 
                       value="<?= htmlspecialchars($chapter['chapter_name']) ?>" 
                       placeholder="Judul chapter..." required class="form-input">
            </div>

            <div class="form-group">
                <label for="chapter_page">Jumlah Halaman *</label>
                <input type="number" id="chapter_page" name="chapter_page" 
                       value="<?= htmlspecialchars($chapter['chapter_page']) ?>" 
                       min="1" max="100" required class="form-input">
            </div>

            <div class="form-group">
                <label for="chapter_price">Harga Chapter (Points)</label>
                <input type="number" id="chapter_price" name="chapter_price" 
                       value="<?= htmlspecialchars($chapter['chapter_price']) ?>" 
                       min="0" max="1000" class="form-input">
                <small>Isi 0 untuk chapter gratis</small>
            </div>

            <div class="form-group">
                <label for="chapter_release_date">Tanggal Rilis *</label>
                <input type="date" id="chapter_release_date" name="chapter_release_date" 
                       value="<?= htmlspecialchars($chapter['chapter_release_date']) ?>" 
                       required class="form-input">
            </div>

            <div class="form-group">
                <label for="chapter_status">Status Chapter *</label>
                <select id="chapter_status" name="chapter_status" required class="form-input">
                    <option value="pending" <?= $chapter['chapter_status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                    <option value="upload" <?= $chapter['chapter_status'] === 'upload' ? 'selected' : '' ?>>✅ Uploaded</option>
                </select>
            </div>

            <div class="form-actions">
                <a href="comic_detail.php?id=<?= $chapter['comic_id'] ?>" class="btn btn-outline">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    <span class="btn-icon">💾</span>
                    Simpan Perubahan
                </button>
            </div>
        </form>
        
        <div class="page-management-section">
            <h3>Manajemen Halaman Chapter</h3>
            <p>Kelola halaman-halaman dalam chapter ini:</p>
            <div class="page-management-actions">
                <a href="upload_chapter_pages.php?chapter_id=<?= $chapter_id ?>" class="btn btn-outline">
                    <span class="btn-icon">🖼️</span>
                    Upload Halaman
                </a>
                <a href="manage_pages.php?chapter_id=<?= $chapter_id ?>" class="btn btn-outline">
                    <span class="btn-icon">📑</span>
                    Kelola Halaman
                </a>
            </div>
        </div>
    </div>

    <script src="script_edit_chapter.js"></script>
</body>
</html>