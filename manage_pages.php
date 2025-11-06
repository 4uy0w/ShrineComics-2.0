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
    die("Anda tidak memiliki akses untuk mengelola halaman chapter ini!");
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

// Proses update urutan halaman via AJAX akan ditangani oleh JavaScript
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Halaman - <?= htmlspecialchars($chapter['chapter_name']) ?></title>
    <link rel="stylesheet" href="style_comic_detail.css">
    <link rel="stylesheet" href="style_manage_pages.css">
</head>
<body>
    <nav class="top-navigation">
        <div class="nav-container">
            <a href="edit_chapter.php?chapter_id=<?= $chapter_id ?>" class="btn btn-back-dashboard">
                ← Kembali ke Edit Chapter
            </a>
            <h1 class="nav-title">Kelola Halaman Chapter</h1>
            <div class="nav-actions">
                <a href="upload_chapter_pages.php?chapter_id=<?= $chapter_id ?>" class="btn btn-primary">
                    <span class="btn-icon">➕</span>
                    Tambah Halaman
                </a>
            </div>
        </div>
    </nav>

    <div class="manage-container">
        <!-- Header Info -->
        <div class="manage-header">
            <h1>📑 Kelola Halaman Chapter</h1>
            <p>Chapter: <strong><?= htmlspecialchars($chapter['chapter_name']) ?></strong></p>
            <p>Komik: <strong><?= htmlspecialchars($chapter['comic_title']) ?></strong></p>
            <p>Total Halaman: <strong><?= $total_existing_pages ?></strong></p>
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <h3>🔄 Cara Mengatur Halaman:</h3>
            <ul>
                <li><strong>Drag & Drop</strong> halaman untuk mengubah urutan</li>
                <li>Klik <strong>🗑️</strong> untuk menghapus halaman</li>
                <li>Urutan akan otomatis tersimpan</li>
                <li>Klik <strong>Simpan Perubahan</strong> untuk menyimpan permanen</li>
            </ul>
        </div>

        <!-- Pages Container -->
        <div class="pages-management">
            <div class="management-header">
                <h3>Daftar Halaman (<?= $total_existing_pages ?>)</h3>
                <div class="management-actions">
                    <button class="btn btn-outline" id="resetOrder">
                        <span class="btn-icon">🔄</span>
                        Reset Urutan
                    </button>
                    <button class="btn btn-primary" id="saveOrder">
                        <span class="btn-icon">💾</span>
                        Simpan Perubahan
                    </button>
                </div>
            </div>

            <?php if ($total_existing_pages > 0): ?>
            <div class="pages-list" id="pagesList">
                <?php foreach ($existing_pages as $page): ?>
                <div class="page-item" data-page-id="<?= $page['chapter_page_id'] ?>">
                    <div class="page-handle">
                        <span class="handle-icon">⋮⋮</span>
                    </div>
                    <div class="page-preview">
                        <img src="<?= htmlspecialchars($page['chapter_page_image']) ?>" 
                             alt="Halaman <?= $page['chapter_page_number'] ?>" 
                             class="page-thumbnail"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGVlMmU2Ii8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxMiIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIE5vdCBGb3VuZDwvdGV4dD48L3N2Zz4='">
                    </div>
                    <div class="page-info">
                        <div class="page-number-display">
                            <span class="current-number">#<?= $page['chapter_page_number'] ?></span>
                            <span class="new-number" style="display: none;"></span>
                        </div>
                        <div class="page-filename">
                            <?= basename($page['chapter_page_image']) ?>
                        </div>
                        <div class="page-actions">
                            <button class="btn-icon btn-view" onclick="previewPage(<?= $page['chapter_page_id'] ?>, '<?= htmlspecialchars($page['chapter_page_image']) ?>')" title="Preview">
                                👁️
                            </button>
                            <button class="btn-icon btn-delete" onclick="deletePage(<?= $page['chapter_page_id'] ?>)" title="Hapus">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-preview">
                <h4>Preview Urutan Baru:</h4>
                <div class="order-list" id="orderPreview">
                    <!-- Order preview will be updated by JavaScript -->
                </div>
            </div>
            <?php else: ?>
            <div class="empty-pages">
                <div class="empty-icon">📄</div>
                <h3>Belum Ada Halaman</h3>
                <p>Silakan upload halaman terlebih dahulu</p>
                <a href="upload_chapter_pages.php?chapter_id=<?= $chapter_id ?>" class="btn btn-primary">
                    <span class="btn-icon">🖼️</span>
                    Upload Halaman Pertama
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Preview Halaman</h3>
                <span class="close-modal" onclick="closePreviewModal()">&times;</span>
            </div>
            <div class="modal-body">
                <img id="previewImage" src="" alt="Preview Halaman" class="preview-img">
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closePreviewModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script src="script_manage_pages.js"></script>
</body>
</html>