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
$join_date = $_SESSION['join_date'] ?? date('Y-m-d');

// Query point terbaru dari database
$user_sql = "SELECT point, email FROM users WHERE user_id = ?";
$stmt_user = $koneksi->prepare($user_sql);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();

if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $points = $user_data['point'];
    $email = $user_data['email'];
    $_SESSION['point'] = $points;
} else {
    $points = 0;
    $email = '';
}
$stmt_user->close();

// Hitung statistik
$comics_count_sql = "SELECT COUNT(*) as total FROM comic WHERE comic_writer = ?";
$stmt_comics = $koneksi->prepare($comics_count_sql);
$stmt_comics->bind_param("s", $username);
$stmt_comics->execute();
$comics_result = $stmt_comics->get_result();
$total_comics = $comics_result->fetch_assoc()['total'];
$stmt_comics->close();

$chapters_count_sql = "SELECT COUNT(*) as total FROM chapter WHERE chapter_writer = ?";
$stmt_chapters = $koneksi->prepare($chapters_count_sql);
$stmt_chapters->bind_param("s", $username);
$stmt_chapters->execute();
$chapters_result = $stmt_chapters->get_result();
$total_chapters = $chapters_result->fetch_assoc()['total'];
$stmt_chapters->close();

// Hitung total pendapatan dari transaksi
$earnings_sql = "SELECT COALESCE(SUM(transaction_point), 0) as total_earnings 
                 FROM transactions 
                 WHERE transaction_writer = ?";
$stmt_earnings = $koneksi->prepare($earnings_sql);
$stmt_earnings->bind_param("i", $user_id);
$stmt_earnings->execute();
$earnings_result = $stmt_earnings->get_result();
$total_earnings = $earnings_result->fetch_assoc()['total_earnings'];
$stmt_earnings->close();

// Query komik milik writer
$comics_sql = "SELECT * FROM comic WHERE comic_writer = ? ORDER BY comic_id DESC LIMIT 6";
$stmt_comics_list = $koneksi->prepare($comics_sql);
$stmt_comics_list->bind_param("s", $username);
$stmt_comics_list->execute();
$comics_list_result = $stmt_comics_list->get_result();

// Query transaksi terbaru
$transactions_sql = "SELECT t.*, c.comic_title, ch.chapter_name, ch.chapter_number, u.username as buyer_name
                    FROM transactions t
                    JOIN comic c ON t.transaction_comic = c.comic_id
                    JOIN chapter ch ON t.transaction_chapter = ch.chapter_id
                    JOIN users u ON t.transaction_reader = u.user_id
                    WHERE t.transaction_writer = ?
                    ORDER BY t.transaction_date DESC
                    LIMIT 15";
$stmt_transactions = $koneksi->prepare($transactions_sql);
$stmt_transactions->bind_param("i", $user_id);
$stmt_transactions->execute();
$transactions_result = $stmt_transactions->get_result();

$formatted_join_date = date('d M Y', strtotime($join_date));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Writer - ShrineComics</title>
    <link rel="stylesheet" href="style_writer.css">
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
                            <span class="user-email"><?php echo htmlspecialchars($email); ?></span>
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
                        <a href="logout.php" class="dropdown-item logout-item" onclick="return confirm('Yakin ingin logout?')">
                            <span class="item-icon">🚪</span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Header Section -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>Selamat Datang, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                    <p class="subtitle">Kelola komik dan pantau perkembangan karya Anda</p>
                </div>
                <div class="header-actions">
                    <a href="add_comic.php" class="btn btn-primary btn-large">
                        <span class="btn-icon">+</span>
                        Buat Komik Baru
                    </a>
                    <a href="comics_management.php" class="btn btn-outline">
                        <span class="btn-icon">📋</span>
                        Kelola Semua Komik
                    </a>
                </div>
            </div>
        </header>

        <!-- Quick Stats -->
        <section class="quick-stats">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">💰</div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo number_format($points); ?></h3>
                        <p class="stat-label">Poin Saat Ini</p>
                        <span class="stat-trend">Saldo tersedia</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">📚</div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo number_format($total_comics); ?></h3>
                        <p class="stat-label">Total Komik</p>
                        <span class="stat-trend">Karya diterbitkan</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">📖</div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo number_format($total_chapters); ?></h3>
                        <p class="stat-label">Total Chapter</p>
                        <span class="stat-trend">Konten dibuat</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">💸</div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo number_format($total_earnings); ?></h3>
                        <p class="stat-label">Total Pendapatan</p>
                        <span class="stat-trend">Poin diperoleh</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content Grid -->
        <main class="dashboard-main">
            <div class="main-grid">
                <!-- Komik Terbaru Section -->
                <section class="comics-section">
                    <div class="section-header">
                        <h2>📚 Komik Saya</h2>
                        <div class="section-actions">
                            <span class="badge"><?php echo $total_comics; ?> komik</span>
                            <a href="comics_management.php" class="btn-link">Lihat Semua →</a>
                        </div>
                    </div>

                    <div class="comics-grid">
                        <?php if ($comics_list_result->num_rows > 0): ?>
                            <?php while ($comic = $comics_list_result->fetch_assoc()): ?>
                                <div class="comic-card" data-comic-id="<?php echo $comic['comic_id']; ?>">
                                    <div class="comic-cover">
                                        <?php if (!empty($comic['comic_banner'])): ?>
                                            <img src="<?php echo htmlspecialchars($comic['comic_banner']); ?>" 
                                                 alt="<?php echo htmlspecialchars($comic['comic_title']); ?>"
                                                 onerror="this.src='assets/images/default-banner.jpg'">
                                        <?php else: ?>
                                            <div class="no-cover">
                                                <span>📚</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Tombol Edit & Hapus pada Cover -->
                                        <div class="comic-actions-overlay">
                                            <button class="btn-action btn-edit" onclick="editComic(<?php echo $comic['comic_id']; ?>)" title="Edit Komik">
                                                <span class="btn-icon">✏️</span>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deleteComic(<?php echo $comic['comic_id']; ?>)" title="Hapus Komik">
                                                <span class="btn-icon">🗑️</span>
                                            </button>
                                        </div>
                                        
                                        <div class="comic-overlay">
                                            <a href="comic_detail.php?id=<?php echo $comic['comic_id']; ?>" class="btn-overlay">
                                                Kelola Komik
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="comic-info">
                                        <h4 class="comic-title"><?php echo htmlspecialchars($comic['comic_title']); ?></h4>
                                        <p class="comic-genre"><?php echo htmlspecialchars($comic['comic_genre'] ?? 'Umum'); ?></p>
                                        <div class="comic-stats">
                                            <span class="stat"><?php echo $comic['comic_chapter'] ?? 0; ?> chapter</span>
                                            <span class="stat"><?php echo date('M Y', strtotime($comic['created_at'] ?? 'now')); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Tombol Aksi di Bawah Card
                                    <div class="comic-footer-actions">
                                        <a href="comic_detail.php?id=<?php echo $comic['comic_id']; ?>" class="btn-action btn-manage">
                                            <span class="btn-icon">👁️</span>
                                            Kelola
                                        </a>
                                        <button class="btn-action btn-edit" onclick="editComic(<?php echo $comic['comic_id']; ?>)">
                                            <span class="btn-icon">✏️</span>
                                            Edit
                                        </button>
                                        <button class="btn-action btn-delete" onclick="deleteComic(<?php echo $comic['comic_id']; ?>)">
                                            <span class="btn-icon">🗑️</span>
                                            Hapus
                                        </button>
                                    </div>-->
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">📚</div>
                                <h3>Belum Ada Komik</h3>
                                <p>Mulai perjalanan kreatif Anda dengan membuat komik pertama</p>
                                <a href="add_comic.php" class="btn btn-primary">
                                    <span class="btn-icon">+</span>
                                    Buat Komik Pertama
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Transaksi Terbaru Section -->
                <section class="transactions-section">
                    <div class="section-header">
                        <h2>💸 Transaksi Terbaru</h2>
                        <div class="section-actions">
                            <span class="badge"><?php echo $transactions_result->num_rows; ?> transaksi</span>
                            <a href="transactions_history.php" class="btn-link">Lihat Semua →</a>
                        </div>
                    </div>

                    <div class="table-container">
                        <?php if ($transactions_result->num_rows > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="text-left">Komik & Chapter</th>
                                        <th class="text-center">Pembeli</th>
                                        <th class="text-center">Tanggal</th>
                                        <th class="text-right">Jumlah</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transaction = $transactions_result->fetch_assoc()): ?>
                                        <tr class="table-row">
                                            <td class="text-left">
                                                <div class="transaction-detail">
                                                    <strong class="comic-title"><?php echo htmlspecialchars($transaction['comic_title']); ?></strong>
                                                    <div class="chapter-info">
                                                        <span class="chapter-number">Chapter <?php echo $transaction['chapter_number']; ?></span>
                                                        <span class="chapter-separator">•</span>
                                                        <span class="chapter-name"><?php echo htmlspecialchars($transaction['chapter_name']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="buyer-tag"><?php echo htmlspecialchars($transaction['buyer_name']); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="date-time">
                                                    <div class="date"><?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></div>
                                                    <div class="time"><?php echo date('H:i', strtotime($transaction['transaction_date'])); ?></div>
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                <span class="amount positive">+<?php echo number_format($transaction['transaction_point']); ?> poin</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="status-badge success">Berhasil</span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">💸</div>
                                <h3>Belum Ada Transaksi</h3>
                                <p>Transaksi akan muncul di sini ketika ada pembeli yang membeli chapter komik Anda</p>
                                <div class="empty-tips">
                                    <p><strong>Tips:</strong> Promosikan komik Anda untuk mendapatkan pembeli pertama!</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>

        <!-- Quick Actions Footer -->
        <footer class="quick-actions">
            <div class="actions-grid">
                <a href="add_comic.php" class="action-card">
                    <span class="action-icon">➕</span>
                    <span class="action-text">Komik Baru</span>
                </a>
                <!--
                <a href="add_chapter.php" class="action-card">
                    <span class="action-icon">📖</span>
                    <span class="action-text">Tambah Chapter</span>
                </a>-->
                <a href="profile.php" class="action-card">
                    <span class="action-icon">👤</span>
                    <span class="action-text">Profile Saya</span>
                </a>
                <a href="help.php" class="action-card">
                    <span class="action-icon">❓</span>
                    <span class="action-text">Bantuan</span>
                </a>
            </div>
        </footer>
    </div>

    <script src="script_writer.js"></script>
</body>
</html>

<?php
$stmt_comics_list->close();
$stmt_transactions->close();
$koneksi->close();
?>