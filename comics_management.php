<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    header("Location: login.php");
    exit();
}

// Cek status user
if ($_SESSION['status'] === 'SUSPEND') {
    session_destroy();
    header("Location: login.php?error=suspended");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Deteksi struktur tabel comic
$check_columns_sql = "SHOW COLUMNS FROM comic";
$check_result = $koneksi->query($check_columns_sql);
$comic_columns = [];
while ($row = $check_result->fetch_assoc()) {
    $comic_columns[] = $row['Field'];
}

// Tentukan nama kolom ID dan writer
$id_column = in_array('comic_id', $comic_columns) ? 'comic_id' : 'id';
$writer_column = in_array('comic_writer', $comic_columns) ? 'comic_writer' : 'writer';

// Deteksi struktur tabel chapter
$check_chapter_sql = "SHOW COLUMNS FROM chapter";
$check_chapter_result = $koneksi->query($check_chapter_sql);
$chapter_columns = [];
while ($row = $check_chapter_result->fetch_assoc()) {
    $chapter_columns[] = $row['Field'];
}

// Tentukan kolom comic_id dan writer di tabel chapter
$chapter_comic_column = in_array('comic_id', $chapter_columns) ? 'comic_id' : 'chapter_comic';
$chapter_writer_column = in_array('chapter_writer', $chapter_columns) ? 'chapter_writer' : 'writer';

// Query untuk mendapatkan semua komik milik writer
$comics_sql = "SELECT * FROM comic WHERE $writer_column = ? ORDER BY $id_column DESC";
$stmt_comics = $koneksi->prepare($comics_sql);
if (!$stmt_comics) {
    die("Error preparing query: " . $koneksi->error);
}
$stmt_comics->bind_param("s", $username);
$stmt_comics->execute();
$comics_result = $stmt_comics->get_result();
$total_comics = $comics_result->num_rows;

// Query untuk menghitung total chapter per komik
$chapters_count_sql = "SELECT $chapter_comic_column, COUNT(*) as chapter_count 
                       FROM chapter 
                       WHERE $chapter_writer_column = ? 
                       GROUP BY $chapter_comic_column";
$stmt_chapters = $koneksi->prepare($chapters_count_sql);
$chapter_counts = [];
if ($stmt_chapters) {
    $stmt_chapters->bind_param("s", $username);
    $stmt_chapters->execute();
    $chapters_result = $stmt_chapters->get_result();
    while ($row = $chapters_result->fetch_assoc()) {
        $chapter_counts[$row[$chapter_comic_column]] = $row['chapter_count'];
    }
    $stmt_chapters->close();
}

// Query untuk menghitung total pembelian per komik
$purchases_count_sql = "SELECT transaction_comic, COUNT(*) as purchase_count 
                        FROM transactions 
                        WHERE transaction_writer = ? 
                        GROUP BY transaction_comic";
$stmt_purchases = $koneksi->prepare($purchases_count_sql);
$purchase_counts = [];
if ($stmt_purchases) {
    $stmt_purchases->bind_param("i", $user_id);
    $stmt_purchases->execute();
    $purchases_result = $stmt_purchases->get_result();
    while ($row = $purchases_result->fetch_assoc()) {
        $purchase_counts[$row['transaction_comic']] = $row['purchase_count'];
    }
    $stmt_purchases->close();
}

// Handle pencarian
$search_query = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
    $search_sql = "SELECT * FROM comic 
                   WHERE $writer_column = ? 
                   AND (comic_title LIKE ? OR comic_genre LIKE ?) 
                   ORDER BY $id_column DESC";
    $search_param = "%$search_query%";
    
    $stmt_comics->close();
    $stmt_comics = $koneksi->prepare($search_sql);
    if ($stmt_comics) {
        $stmt_comics->bind_param("sss", $username, $search_param, $search_param);
        $stmt_comics->execute();
        $comics_result = $stmt_comics->get_result();
        $total_comics = $comics_result->num_rows;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Komik - ShrineComics</title>
    <link rel="stylesheet" href="style_writer.css">
    <link rel="stylesheet" href="comics_management.css">
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="top-navigation">
        <div class="nav-container">
            <div class="nav-brand">
                <div class="logo">
                    <span class="logo-icon">📚</span>
                    <span class="logo-text">ShrineComics</span>
                </div>
                <span class="role-badge">Writer</span>
            </div>
            
            <div class="nav-user">
                <div class="user-menu">
                    <button class="user-toggle" id="userToggle">
                        <div class="user-avatar">
                            <span class="avatar-icon">👤</span>
                        </div>
                        <span class="username"><?php echo htmlspecialchars($username); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <strong><?php echo htmlspecialchars($username); ?></strong>
                            <span class="user-email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <span class="item-icon">⚙️</span>
                            Pengaturan Profile
                        </a>
                        <a href="change_password.php" class="dropdown-item">
                            <span class="item-icon">🔒</span>
                            Ganti Password
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="dashboard_writer.php" class="dropdown-item">
                            <span class="item-icon">📊</span>
                            Dashboard
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout-item" onclick="return confirm('Yakin ingin logout?')">
                            <span class="item-icon">🚪</span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Management Header -->
    <div class="management-header">
        <div class="header-content">
            <div class="header-title">
                <h1>Kelola Semua Komik</h1>
                <p class="subtitle">Kelola dan edit semua komik yang telah Anda buat</p>
            </div>
            <div class="header-actions">
                <a href="add_comic.php" class="btn btn-primary btn-large">
                    <span class="btn-icon">+</span>
                    Buat Komik Baru
                </a>
                <a href="dashboard_writer.php" class="btn btn-outline">
                    <span class="btn-icon">←</span>
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Management Actions -->
        <div class="management-actions">
            <div class="search-box">
                <form method="GET" action="comics_management.php" class="search-form">
                    <input type="text" name="search" placeholder="Cari komik berdasarkan judul atau genre..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="search-btn">Cari</button>
                </form>
            </div>
            
            <div class="filter-sort">
                <select id="sortSelect">
                    <option value="newest">Terbaru</option>
                    <option value="oldest">Terlama</option>
                    <option value="title_asc">Judul A-Z</option>
                    <option value="title_desc">Judul Z-A</option>
                </select>
                
                <span class="badge"><?php echo $total_comics; ?> komik</span>
            </div>
        </div>

        <!-- Comics Grid -->
        <div class="comics-grid-management">
            <?php if ($comics_result->num_rows > 0): ?>
                <?php while ($comic = $comics_result->fetch_assoc()): 
                    $comic_id = $comic[$id_column];
                    $chapter_count = $chapter_counts[$comic_id] ?? 0;
                    $purchase_count = $purchase_counts[$comic_id] ?? 0;
                    $created_date = $comic['created_at'] ?? $comic['comic_date'] ?? 'now';
                ?>
                    <div class="comic-card-management" data-comic-id="<?php echo $comic_id; ?>">
                        <div class="comic-cover-management">
                            <?php if (!empty($comic['comic_banner'])): ?>
                                <img src="<?php echo htmlspecialchars($comic['comic_banner']); ?>" 
                                     alt="<?php echo htmlspecialchars($comic['comic_title']); ?>"
                                     onerror="this.src='assets/images/default-banner.jpg'">
                            <?php else: ?>
                                <div class="no-cover">
                                    <span>📚</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="comic-actions-overlay-management">
                                <button class="btn-action-management btn-edit" onclick="editComic(<?php echo $comic_id; ?>)" title="Edit Komik">
                                    <span class="btn-icon">✏️</span>
                                </button>
                                <button class="btn-action-management btn-delete" onclick="deleteComic(<?php echo $comic_id; ?>)" title="Hapus Komik">
                                    <span class="btn-icon">🗑️</span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="comic-info-management">
                            <h4 class="comic-title-management"><?php echo htmlspecialchars($comic['comic_title']); ?></h4>
                            <p class="comic-genre-management"><?php echo htmlspecialchars($comic['comic_genre'] ?? 'Umum'); ?></p>
                            
                            <div class="comic-stats-management">
                                <span class="stat"><?php echo $chapter_count; ?> chapter</span>
                                <span class="stat"><?php echo $purchase_count; ?> pembelian</span>
                                <span class="stat"><?php echo date('M Y', strtotime($created_date)); ?></span>
                            </div>
                            
                            <div class="comic-footer-actions-management">
                                <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="btn-action-full btn-manage">
                                    <span class="btn-icon">👁️</span>
                                    Kelola
                                </a>
                                <a href="chapters_management.php?comic_id=<?php echo $comic_id; ?>" class="btn-action-full btn-chapters">
                                    <span class="btn-icon">📖</span>
                                    Chapter
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state-management">
                    <div class="empty-icon-management">📚</div>
                    <h3><?php echo empty($search_query) ? 'Belum Ada Komik' : 'Komik Tidak Ditemukan'; ?></h3>
                    <p>
                        <?php if (empty($search_query)): ?>
                            Mulai perjalanan kreatif Anda dengan membuat komik pertama
                        <?php else: ?>
                            Tidak ada komik yang sesuai dengan pencarian "<?php echo htmlspecialchars($search_query); ?>"
                        <?php endif; ?>
                    </p>
                    <a href="add_comic.php" class="btn btn-primary">
                        <span class="btn-icon">+</span>
                        Buat Komik Baru
                    </a>
                    <?php if (!empty($search_query)): ?>
                        <a href="comics_management.php" class="btn btn-outline">
                            <span class="btn-icon">↶</span>
                            Tampilkan Semua Komik
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="comics_management.js"></script>
</body>
</html>

<?php
$stmt_comics->close();
$koneksi->close();
?>