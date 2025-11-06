<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$comic_id = $_GET['id'] ?? null;
$chapter_id = $_GET['chapter_id'] ?? null;

if (!$comic_id) {
    header("Location: dashboard_reader.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Get comic details
$sql = "SELECT * FROM comic WHERE comic_id = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $comic_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard_reader.php");
    exit();
}

$comic = $result->fetch_assoc();
$stmt->close();

// Get all chapters for this comic
$chapters_sql = "SELECT * FROM chapter WHERE chapter_comic = ? ORDER BY chapter_number";
$chapters_stmt = $koneksi->prepare($chapters_sql);
$chapters_stmt->bind_param("s", $comic['comic_title']);
$chapters_stmt->execute();
$chapters = $chapters_stmt->get_result();

// Jika tidak ada chapter_id yang dipilih, ambil chapter pertama
if (!$chapter_id && $chapters->num_rows > 0) {
    $first_chapter = $chapters->fetch_assoc();
    $chapter_id = $first_chapter['chapter_id'];
    // Reset pointer untuk digunakan lagi
    $chapters->data_seek(0);
}

// Get selected chapter details
$current_chapter = null;
$chapter_pages = [];

if ($chapter_id) {
    $chapter_sql = "SELECT * FROM chapter WHERE chapter_id = ? AND chapter_comic = ?";
    $chapter_stmt = $koneksi->prepare($chapter_sql);
    $chapter_stmt->bind_param("is", $chapter_id, $comic['comic_title']);
    $chapter_stmt->execute();
    $chapter_result = $chapter_stmt->get_result();
    
    if ($chapter_result->num_rows === 1) {
        $current_chapter = $chapter_result->fetch_assoc();
        
        // Get pages for this chapter
        $pages_sql = "SELECT * FROM chapter_page WHERE chapter_page_chapter = ? ORDER BY chapter_page_number";
        $pages_stmt = $koneksi->prepare($pages_sql);
        $pages_stmt->bind_param("s", $current_chapter['chapter_name']);
        $pages_stmt->execute();
        $chapter_pages = $pages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $pages_stmt->close();
    }
    $chapter_stmt->close();
}

// Get next and previous chapter
$prev_chapter = null;
$next_chapter = null;

if ($current_chapter) {
    // Previous chapter
    $prev_sql = "SELECT chapter_id FROM chapter WHERE chapter_comic = ? AND chapter_number < ? ORDER BY chapter_number DESC LIMIT 1";
    $prev_stmt = $koneksi->prepare($prev_sql);
    $prev_stmt->bind_param("si", $comic['comic_title'], $current_chapter['chapter_number']);
    $prev_stmt->execute();
    $prev_result = $prev_stmt->get_result();
    if ($prev_result->num_rows > 0) {
        $prev_chapter = $prev_result->fetch_assoc();
    }
    $prev_stmt->close();
    
    // Next chapter
    $next_sql = "SELECT chapter_id FROM chapter WHERE chapter_comic = ? AND chapter_number > ? ORDER BY chapter_number ASC LIMIT 1";
    $next_stmt = $koneksi->prepare($next_sql);
    $next_stmt->bind_param("si", $comic['comic_title'], $current_chapter['chapter_number']);
    $next_stmt->execute();
    $next_result = $next_stmt->get_result();
    if ($next_result->num_rows > 0) {
        $next_chapter = $next_result->fetch_assoc();
    }
    $next_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baca <?php echo htmlspecialchars($comic['comic_title']); ?> - ShrineComics</title>
    <link rel="stylesheet" href="style_show_comic.css">
</head>
<body>
    <!-- Reading Progress Bar -->
    <div class="reading-progress" id="readingProgress">
        <div class="progress-bar" id="progressBar"></div>
    </div>

    <div class="comic-reader-container">
        <!-- Header -->
        <header class="reader-header">
            <div class="header-content">
                <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="back-btn">
                    <span>←</span>
                    Kembali ke Detail
                </a>
                <div class="comic-info">
                    <h1><?php echo htmlspecialchars($comic['comic_title']); ?></h1>
                    <?php if ($current_chapter): ?>
                        <p class="chapter-info">
                            Chapter <?php echo $current_chapter['chapter_number']; ?>: 
                            <?php echo htmlspecialchars($current_chapter['chapter_name']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <button class="btn-menu" id="menuToggle">
                        <span>📋</span>
                        Daftar Chapter
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="reader-main">
            <!-- Chapter Navigation -->
            <?php if ($current_chapter): ?>
            <div class="chapter-navigation">
                <div class="nav-buttons">
                    <?php if ($prev_chapter): ?>
                        <a href="show_comic.php?id=<?php echo $comic_id; ?>&chapter_id=<?php echo $prev_chapter['chapter_id']; ?>" class="nav-btn prev">
                            <span>←</span>
                            Chapter Sebelumnya
                        </a>
                    <?php else: ?>
                        <span class="nav-btn prev disabled">
                            <span>←</span>
                            Chapter Sebelumnya
                        </span>
                    <?php endif; ?>
                    
                    <div class="chapter-selector">
                        <select id="chapterSelect" onchange="changeChapter(this.value)">
                            <option value="">Pilih Chapter...</option>
                            <?php 
                            $chapters->data_seek(0); // Reset pointer
                            while ($chapter = $chapters->fetch_assoc()): ?>
                                <option value="<?php echo $chapter['chapter_id']; ?>" 
                                    <?php echo $chapter['chapter_id'] == $chapter_id ? 'selected' : ''; ?>>
                                    Chapter <?php echo $chapter['chapter_number']; ?>: <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <?php if ($next_chapter): ?>
                        <a href="show_comic.php?id=<?php echo $comic_id; ?>&chapter_id=<?php echo $next_chapter['chapter_id']; ?>" class="nav-btn next">
                            Chapter Selanjutnya
                            <span>→</span>
                        </a>
                    <?php else: ?>
                        <span class="nav-btn next disabled">
                            Chapter Selanjutnya
                            <span>→</span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Comic Pages -->
            <?php if ($current_chapter): ?>
            <div class="comic-pages" id="comicPages">
                <?php if (count($chapter_pages) > 0): ?>
                    <?php foreach ($chapter_pages as $index => $page): ?>
                        <div class="page-container" data-page="<?php echo $index + 1; ?>">
                            <div class="page-number">
                                Halaman <?php echo $page['chapter_page_number']; ?>
                            </div>
                            <img src="<?php echo htmlspecialchars($page['chapter_page_image']); ?>" 
                                 alt="Halaman <?php echo $page['chapter_page_number']; ?> - <?php echo htmlspecialchars($comic['comic_title']); ?>"
                                 class="comic-page"
                                 loading="lazy"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjEyMDAiIHZpZXdCb3g9IjAgMCA4MDAgMTIwMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iODAwIiBoZWlnaHQ9IjEyMDAiIGZpbGw9IiNGM0Y0RjYiLz48dGV4dCB4PSI0MDAiIHk9IjYwMCIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE4IiBmaWxsPSIjOTk5OTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5HYWdhbCBtZW11YWwgZG93bmxvYWQgZ2FtYmFyPC90ZXh0Pjwvc3ZnPg=='">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-pages">
                        <div class="no-pages-icon">📄</div>
                        <h3>Belum Ada Halaman</h3>
                        <p>Chapter ini belum memiliki halaman yang diupload.</p>
                        <?php if ($role === 'writer'): ?>
                            <a href="add_chapter_pages.php?chapter_id=<?php echo $chapter_id; ?>&comic_id=<?php echo $comic_id; ?>" class="btn-primary">
                                Upload Halaman
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Bottom Navigation -->
            <?php if ($current_chapter && count($chapter_pages) > 0): ?>
            <div class="bottom-navigation">
                <div class="nav-buttons">
                    <?php if ($prev_chapter): ?>
                        <a href="show_comic.php?id=<?php echo $comic_id; ?>&chapter_id=<?php echo $prev_chapter['chapter_id']; ?>" class="nav-btn prev">
                            <span>←</span>
                            Chapter Sebelumnya
                        </a>
                    <?php else: ?>
                        <span class="nav-btn prev disabled">
                            <span>←</span>
                            Chapter Sebelumnya
                        </span>
                    <?php endif; ?>
                    
                    <div class="page-controls">
                        <button class="control-btn" id="zoomOut" title="Zoom Out">
                            <span>🔍-</span>
                        </button>
                        <button class="control-btn" id="zoomReset" title="Reset Zoom">
                            <span>🔍</span>
                        </button>
                        <button class="control-btn" id="zoomIn" title="Zoom In">
                            <span>🔍+</span>
                        </button>
                    </div>
                    
                    <?php if ($next_chapter): ?>
                        <a href="show_comic.php?id=<?php echo $comic_id; ?>&chapter_id=<?php echo $next_chapter['chapter_id']; ?>" class="nav-btn next">
                            Chapter Selanjutnya
                            <span>→</span>
                        </a>
                    <?php else: ?>
                        <span class="nav-btn next disabled">
                            Chapter Selanjutnya
                            <span>→</span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>

        <!-- Chapter List Sidebar -->
        <aside class="chapters-sidebar" id="chaptersSidebar">
            <div class="sidebar-header">
                <h3>Daftar Chapter</h3>
                <button class="close-sidebar" id="closeSidebar">×</button>
            </div>
            <div class="chapters-list">
                <?php 
                $chapters->data_seek(0); // Reset pointer
                while ($chapter = $chapters->fetch_assoc()): 
                ?>
                    <a href="show_comic.php?id=<?php echo $comic_id; ?>&chapter_id=<?php echo $chapter['chapter_id']; ?>" 
                       class="chapter-item <?php echo $chapter['chapter_id'] == $chapter_id ? 'active' : ''; ?>">
                        <div class="chapter-number">
                            Chapter <?php echo $chapter['chapter_number']; ?>
                        </div>
                        <div class="chapter-details">
                            <h4><?php echo htmlspecialchars($chapter['chapter_name']); ?></h4>
                            <div class="chapter-meta">
                                <span class="pages"><?php echo $chapter['chapter_page']; ?> Halaman</span>
                                <?php if ($chapter['chapter_price'] > 0): ?>
                                    <span class="price">💰 <?php echo $chapter['chapter_price']; ?> Points</span>
                                <?php else: ?>
                                    <span class="price free">🆓 Gratis</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($role === 'writer'): ?>
                        <div class="chapter-actions">
                            <button class="btn-delete-small" 
                                    onclick="event.preventDefault(); deleteChapter(<?php echo $chapter['chapter_id']; ?>, <?php echo $comic_id; ?>)"
                                    title="Hapus Chapter">
                                🗑️
                            </button>
                        </div>
                        <?php endif; ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </aside>

        <!-- Overlay for sidebar -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
    </div>

    <script src="script_show_comic.js"></script>
</body>
</html>

<?php
$chapters_stmt->close();
$koneksi->close();
?>