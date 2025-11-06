<?php
// Include koneksi database
require_once 'koneksi.php';

// Ambil ID chapter dari URL
$chapter_id = $_GET['chapter_id'] ?? 0;

// Query data chapter
$chapter_sql = "SELECT * FROM chapter WHERE chapter_id = ?";
$stmt = $koneksi->prepare($chapter_sql);
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$chapter_result = $stmt->get_result();

if ($chapter_result->num_rows === 0) {
    die("Chapter tidak ditemukan!");
}

$chapter = $chapter_result->fetch_assoc();
$stmt->close();

// Query data komik untuk navigasi
$comic_sql = "SELECT * FROM comic WHERE comic_title = ?";
$stmt = $koneksi->prepare($comic_sql);
$stmt->bind_param("s", $chapter['chapter_comic']);
$stmt->execute();
$comic_result = $stmt->get_result();
$comic = $comic_result->fetch_assoc();
$stmt->close();

// Query halaman chapter
$pages_sql = "SELECT * FROM chapter_page WHERE chapter_page_chapter = ? ORDER BY chapter_page_number ASC";
$stmt = $koneksi->prepare($pages_sql);
$stmt->bind_param("s", $chapter['chapter_name']);
$stmt->execute();
$pages_result = $stmt->get_result();
$pages = $pages_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Query chapter sebelumnya dan berikutnya untuk navigasi
$all_chapters_sql = "SELECT chapter_id, chapter_number FROM chapter WHERE chapter_comic = ? ORDER BY chapter_number ASC";
$stmt = $koneksi->prepare($all_chapters_sql);
$stmt->bind_param("s", $chapter['chapter_comic']);
$stmt->execute();
$all_chapters_result = $stmt->get_result();
$all_chapters = $all_chapters_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cari chapter sebelumnya dan berikutnya
$prev_chapter = null;
$next_chapter = null;
$current_index = -1;

foreach ($all_chapters as $index => $chap) {
    if ($chap['chapter_id'] == $chapter_id) {
        $current_index = $index;
        break;
    }
}

if ($current_index > 0) {
    $prev_chapter = $all_chapters[$current_index - 1];
}
if ($current_index < count($all_chapters) - 1) {
    $next_chapter = $all_chapters[$current_index + 1];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baca <?= htmlspecialchars($chapter['chapter_name']) ?> - ShrineComics</title>
    <link rel="stylesheet" href="style_read_chapter.css">
</head>
<body>
    <!-- Navigation Header -->
    <header class="reader-header">
        <div class="reader-nav">
            <a href="comic_detail.php?id=<?= $comic['comic_id'] ?>" class="btn btn-back">
                ← Kembali ke Detail Komik
            </a>
            <h1 class="chapter-title"><?= htmlspecialchars($chapter['chapter_name']) ?></h1>
            <div class="chapter-info">
                <span class="comic-name"><?= htmlspecialchars($comic['comic_title']) ?></span>
                <span class="chapter-meta">Chapter <?= $chapter['chapter_number'] ?> • <?= $chapter['chapter_page'] ?> halaman</span>
            </div>
        </div>
    </header>

    <!-- Chapter Navigation -->
    <div class="chapter-navigation">
        <div class="nav-container">
            <?php if ($prev_chapter): ?>
            <a href="read_chapter.php?chapter_id=<?= $prev_chapter['chapter_id'] ?>" class="btn btn-nav btn-prev">
                ← Chapter <?= $prev_chapter['chapter_number'] - 1 ?>
            </a>
            <?php else: ?>
            <span class="btn btn-nav btn-disabled">← Chapter Sebelumnya</span>
            <?php endif; ?>

            <div class="chapter-controls">
                <button class="btn btn-control" onclick="zoomOut()">🔍-</button>
                <span class="zoom-level">100%</span>
                <button class="btn btn-control" onclick="zoomIn()">🔍+</button>
                <button class="btn btn-control" onclick="toggleFullscreen()">⛶</button>
            </div>

            <?php if ($next_chapter): ?>
            <a href="read_chapter.php?chapter_id=<?= $next_chapter['chapter_id'] ?>" class="btn btn-nav btn-next">
                Chapter <?= $next_chapter['chapter_number'] + 1 ?> →
            </a>
            <?php else: ?>
            <span class="btn btn-nav btn-disabled">Chapter Berikutnya →</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pages Container -->
    <div class="pages-container" id="pagesContainer">
        <?php if (!empty($pages)): ?>
            <?php foreach($pages as $page): ?>
            <div class="page-item">
                <img src="<?= htmlspecialchars($page['chapter_page_image']) ?>" 
                     alt="Halaman <?= $page['chapter_page_number'] ?>" 
                     class="comic-page"
                     loading="lazy"
                     onerror="this.src='placeholder-page.jpg'">
                <div class="page-number">Halaman <?= $page['chapter_page_number'] ?></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-pages">
                <div class="empty-icon">📄</div>
                <h3>Belum Ada Halaman</h3>
                <p>Halaman untuk chapter ini sedang dalam proses upload.</p>
                <a href="comic_detail.php?id=<?= $comic['comic_id'] ?>" class="btn btn-primary">Kembali ke Detail Komik</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-navigation">
        <div class="nav-container">
            <?php if ($prev_chapter): ?>
            <a href="read_chapter.php?chapter_id=<?= $prev_chapter['chapter_id'] ?>" class="btn btn-nav btn-prev">
                ← Chapter Sebelumnya
            </a>
            <?php else: ?>
            <span class="btn btn-nav btn-disabled">← Chapter Sebelumnya</span>
            <?php endif; ?>

            <a href="comic_detail.php?id=<?= $comic['comic_id'] ?>" class="btn btn-outline">
                📚 Detail Komik
            </a>

            <?php if ($next_chapter): ?>
            <a href="read_chapter.php?chapter_id=<?= $next_chapter['chapter_id'] ?>" class="btn btn-nav btn-next">
                Chapter Berikutnya →
            </a>
            <?php else: ?>
            <span class="btn btn-nav btn-disabled">Chapter Berikutnya →</span>
            <?php endif; ?>
        </div>
    </div>

    <script src="script_read_chapter.js"></script>
</body>
</html>