<?php
session_start();
require_once 'koneksi.php';

// Debug: Log session status
error_log("Reader accessed - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// Cek jika user belum login
if (!isset($_SESSION['user_id'])) {
    error_log("Redirecting to login - No user_id in session");
    header("Location: login.php");
    exit;
}

// Verifikasi user di database
$check_sql = "SELECT user_id, username, role, point FROM users WHERE user_id = ?";
$check_stmt = $koneksi->prepare($check_sql);

if (!$check_stmt) {
    error_log("Database error: " . $koneksi->error);
    die("Terjadi kesalahan sistem. Silakan refresh halaman.");
}

$check_stmt->bind_param("i", $_SESSION['user_id']);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows !== 1) {
    session_destroy();
    die("
        <script>
            alert('Sesi telah berakhir. Silakan login kembali.');
            window.location.href = 'login.php';
        </script>
    ");
}

$user = $check_result->fetch_assoc();
$check_stmt->close();

// Genre yang tersedia
$genres = ['adventure', 'romance', 'sci-fi', 'comedy'];
$selected_genre = $_GET['genre'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Query komik berdasarkan genre dan search
$comics_sql = "SELECT * FROM comic WHERE 1=1";
$params = [];
$types = "";

if ($selected_genre !== 'all') {
    $comics_sql .= " AND comic_genre LIKE ?";
    $params[] = "%$selected_genre%";
    $types .= "s";
}

if (!empty($search_query)) {
    $comics_sql .= " AND (comic_title LIKE ? OR comic_writer LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= "ss";
}

$comics_sql .= " ORDER BY comic_id DESC LIMIT 20";

$comics_stmt = $koneksi->prepare($comics_sql);
if (!empty($params)) {
    $comics_stmt->bind_param($types, ...$params);
}

if (!$comics_stmt->execute()) {
    error_log("Query failed: " . $comics_stmt->error);
    $comics = [];
} else {
    $comics_result = $comics_stmt->get_result();
    $comics = $comics_result->fetch_all(MYSQLI_ASSOC);
}
$comics_stmt->close();

// Hitung jumlah chapter untuk setiap komik
$comic_chapter_counts = [];
foreach ($comics as $comic) {
    $count_sql = "SELECT COUNT(*) as total FROM chapter WHERE chapter_comic = ?";
    $count_stmt = $koneksi->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param("s", $comic['comic_title']);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $comic_chapter_counts[$comic['comic_id']] = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $comic_chapter_counts[$comic['comic_id']] = 0;
    }
}

// Hitung total komik per genre untuk stats
$genre_stats = [];
foreach ($genres as $genre) {
    $count_sql = "SELECT COUNT(*) as total FROM comic WHERE comic_genre LIKE ?";
    $count_stmt = $koneksi->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param("s", $genre);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $genre_stats[$genre] = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $genre_stats[$genre] = 0;
    }
}

// Total semua komik
$total_comics = 0;
$total_sql = "SELECT COUNT(*) as total FROM comic";
$total_result = $koneksi->query($total_sql);
if ($total_result) {
    $total_comics = $total_result->fetch_assoc()['total'];
    $total_result->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reader - ShrineComics</title>
    <link rel="stylesheet" href="style_reader.css">
</head>
<body>
    <!-- Header -->
    <header class="reader-header">
        <div class="reader-nav">
            <div class="logo">
                <h1>📚 ShrineComics</h1>
                <span class="subtitle">Digital Comic Reader</span>
            </div>
            
            <!-- Search Box -->
            <div class="search-container">
                <form method="GET" class="search-form">
                    <input type="hidden" name="genre" value="<?= $selected_genre ?>">
                    <div class="search-box">
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" 
                               placeholder="Cari judul atau penulis..." class="search-input">
                        <button type="submit" class="search-btn">🔍</button>
                    </div>
                </form>
            </div>

            <div class="user-menu">
                <!-- Point Display -->
                <div class="point-display">
                    <div class="point-icon">💰</div>
                    <div class="point-info">
                        <span class="point-label">Points</span>
                        <span class="point-value"><?= number_format($user['point'] ?? 0) ?></span>
                    </div>
                </div>
                
                <div class="user-info">
                    <span class="welcome-text">Halo,</span>
                    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                </div>

                <a href="profile.php" class="nav-btn profile-btn">
                    👤 Profile
                </a>
                <a href="logout.php" class="nav-btn logout-btn">🚪 Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="reader-main">
        <!-- Welcome Section dengan Stats -->
        <section class="welcome-section">
            <div class="welcome-content">
                <h2>🎉 Selamat Datang di ShrineComics!</h2>
                <p>Temukan dan baca komik favorit Anda dari berbagai genre</p>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $total_comics ?></div>
                            <div class="stat-label">Total Komik</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎨</div>
                        <div class="stat-content">
                            <div class="stat-number">4</div>
                            <div class="stat-label">Genre</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($user['point'] ?? 0) ?></div>
                            <div class="stat-label">Points Anda</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-content">
                            <div class="stat-number">4.5</div>
                            <div class="stat-label">Rating Avg</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Genre Filter Section -->
        <section class="genre-section">
            <div class="section-header">
                <h3>🎭 Pilih Genre Favorit</h3>
                <div class="genre-stats">
                    Menampilkan <strong><?= count($comics) ?> komik</strong>
                    <?php if($selected_genre !== 'all'): ?>
                    dalam genre <strong><?= ucfirst($selected_genre) ?></strong>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Genre Filter Buttons -->
            <div class="genre-filters">
                <button class="genre-btn <?= $selected_genre === 'all' ? 'active' : '' ?>" 
                        onclick="filterByGenre('all')">
                    <span class="genre-icon">🌟</span>
                    <span class="genre-name">Semua Genre</span>
                    <span class="genre-count"><?= $total_comics ?></span>
                </button>
                
                <?php foreach($genres as $genre): ?>
                <button class="genre-btn <?= $selected_genre === $genre ? 'active' : '' ?>" 
                        onclick="filterByGenre('<?= $genre ?>')">
                    <span class="genre-icon">
                        <?php 
                        $icons = [
                            'adventure' => '🏔️',
                            'romance' => '💖', 
                            'sci-fi' => '🚀',
                            'comedy' => '😂'
                        ];
                        echo $icons[$genre] ?? '📖';
                        ?>
                    </span>
                    <span class="genre-name"><?= ucfirst($genre) ?></span>
                    <span class="genre-count"><?= $genre_stats[$genre] ?? 0 ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Comics Grid Section -->
        <section class="comics-section">
            <?php if(!empty($comics)): ?>
            <div class="comics-grid">
                <?php foreach($comics as $comic): ?>
                <div class="comic-card" data-comic-id="<?= $comic['comic_id'] ?>">
                    <div class="comic-cover-container">
                        <img src="<?= htmlspecialchars($comic['comic_banner'] ?? 'placeholder.jpg') ?>" 
                             alt="<?= htmlspecialchars($comic['comic_title']) ?>" 
                             class="comic-cover"
                             onerror="this.src='placeholder.jpg'">
                    </div>
                    
                    <div class="comic-info">
                        <h3 class="comic-title"><?= htmlspecialchars($comic['comic_title']) ?></h3>
                        
                        <div class="comic-meta">
                            <span class="comic-writer">✍️ <?= htmlspecialchars($comic['comic_writer']) ?></span>
                            <span class="comic-genre <?= strtolower(str_replace(' ', '-', $comic['comic_genre'])) ?>">
                                <?= htmlspecialchars($comic['comic_genre']) ?>
                            </span>
                        </div>
                        
                        <div class="comic-stats">
                            <span class="stat-item">📖 <?= $comic_chapter_counts[$comic['comic_id']] ?? 0 ?> Chapter</span>
                            <span class="stat-item">⭐ <?= number_format(rand(35, 50)/10, 1) ?></span>
                        </div>
                        
                        <div class="comic-actions-bottom">
                            <button class="read-btn" onclick="viewComicDetail(<?= $comic['comic_id'] ?>)">
                                📖 Baca Sekarang
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-comics">
                <div class="no-comics-icon">😴</div>
                <h3>Tidak ada komik ditemukan</h3>
                <p>
                    <?php if(!empty($search_query)): ?>
                    Tidak ditemukan komik untuk "<strong><?= htmlspecialchars($search_query) ?></strong>"
                    <?php else: ?>
                    Tidak ada komik dalam genre <strong><?= ucfirst($selected_genre) ?></strong>
                    <?php endif; ?>
                </p>
                <div class="no-comics-actions">
                    <button class="genre-btn active" onclick="filterByGenre('all')">
                        🌟 Tampilkan Semua Komik
                    </button>
                    <?php if(!empty($search_query)): ?>
                    <button class="genre-btn" onclick="clearSearch()">
                        🔄 Hapus Pencarian
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Point History Modal -->
    <div id="pointHistoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📊 Riwayat Points</h3>
                <span class="modal-close" onclick="closeModal('pointHistoryModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="point-history-list">
                    <div class="history-item">
                        <div class="history-icon">📖</div>
                        <div class="history-details">
                            <span class="history-action">Membaca Chapter "Pertemuan Pertama"</span>
                            <span class="history-date">Hari ini, 14:30</span>
                        </div>
                        <div class="history-points positive">+1</div>
                    </div>
                    <div class="history-item">
                        <div class="history-icon">💬</div>
                        <div class="history-details">
                            <span class="history-action">Memberi komentar</span>
                            <span class="history-date">Kemarin, 09:15</span>
                        </div>
                        <div class="history-points positive">+2</div>
                    </div>
                    <div class="history-item">
                        <div class="history-icon">⭐</div>
                        <div class="history-details">
                            <span class="history-action">Rating komik "Space Adventure"</span>
                            <span class="history-date">2 hari lalu</span>
                        </div>
                        <div class="history-points positive">+1</div>
                    </div>
                    <div class="history-item">
                        <div class="history-icon">🎁</div>
                        <div class="history-details">
                            <span class="history-action">Daily Login Bonus</span>
                            <span class="history-date">3 hari lalu</span>
                        </div>
                        <div class="history-points positive">+5</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script_reader.js"></script>
</body>
</html>