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
} else {
    $points = 0;
    $email = '';
}
$stmt_user->close();

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_comic = $_GET['comic'] ?? '';

// Build query with filters
$transactions_sql = "SELECT t.*, c.comic_title, ch.chapter_name, ch.chapter_number, u.username as buyer_name
                    FROM transactions t
                    JOIN comic c ON t.transaction_comic = c.comic_id
                    JOIN chapter ch ON t.transaction_chapter = ch.chapter_id
                    JOIN users u ON t.transaction_reader = u.user_id
                    WHERE t.transaction_writer = ?";

$params = [$user_id];
$param_types = "i";

// Add status filter
if (!empty($filter_status) && $filter_status !== 'all') {
    $transactions_sql .= " AND t.transaction_status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

// Add date range filter
if (!empty($filter_date_from)) {
    $transactions_sql .= " AND t.transaction_date >= ?";
    $params[] = $filter_date_from;
    $param_types .= "s";
}

if (!empty($filter_date_to)) {
    $transactions_sql .= " AND t.transaction_date <= ?";
    $params[] = $filter_date_to;
    $param_types .= "s";
}

// Add comic filter
if (!empty($filter_comic)) {
    $transactions_sql .= " AND c.comic_title LIKE ?";
    $params[] = "%$filter_comic%";
    $param_types .= "s";
}

$transactions_sql .= " ORDER BY t.transaction_date DESC";

// Get total count for pagination
$count_sql = str_replace(
    "SELECT t.*, c.comic_title, ch.chapter_name, ch.chapter_number, u.username as buyer_name",
    "SELECT COUNT(*) as total",
    $transactions_sql
);

$stmt_count = $koneksi->prepare($count_sql);
if ($params) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_transactions = $count_result->fetch_assoc()['total'];
$stmt_count->close();

// Pagination
$per_page = 20;
$total_pages = ceil($total_transactions / $per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

$transactions_sql .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

// Execute main query
$stmt_transactions = $koneksi->prepare($transactions_sql);
if ($params) {
    $stmt_transactions->bind_param($param_types, ...$params);
}
$stmt_transactions->execute();
$transactions_result = $stmt_transactions->get_result();

// Get writer's comics for filter dropdown
$comics_sql = "SELECT comic_id, comic_title FROM comic WHERE comic_writer = ? ORDER BY comic_title";
$stmt_comics = $koneksi->prepare($comics_sql);
$stmt_comics->bind_param("s", $username);
$stmt_comics->execute();
$comics_result = $stmt_comics->get_result();

// Calculate total earnings from filtered results
$earnings_sql = "SELECT COALESCE(SUM(transaction_point), 0) as total_earnings 
                 FROM transactions 
                 WHERE transaction_writer = ?";
$stmt_earnings = $koneksi->prepare($earnings_sql);
$stmt_earnings->bind_param("i", $user_id);
$stmt_earnings->execute();
$earnings_result = $stmt_earnings->get_result();
$total_earnings = $earnings_result->fetch_assoc()['total_earnings'];
$stmt_earnings->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histori Transaksi - ShrineComics</title>
    <link rel="stylesheet" href="style_writer.css">
    <link rel="stylesheet" href="style_transactions.css">
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
                    <h1>💸 Histori Transaksi</h1>
                    <p class="subtitle">Lihat dan kelola semua transaksi penjualan komik Anda</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard_writer.php" class="btn btn-outline">
                        <span class="btn-icon">←</span>
                        Kembali ke Dashboard
                    </a>
                    <button class="btn btn-primary" onclick="exportToCSV()">
                        <span class="btn-icon">📊</span>
                        Export CSV
                    </button>
                </div>
            </div>
        </header>

        <!-- Summary Cards -->
        <section class="summary-cards">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">💰</div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo number_format($points); ?></h3>
                        <p class="stat-label">Poin Saat Ini</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">💸</div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo number_format($total_earnings); ?></h3>
                        <p class="stat-label">Total Pendapatan</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">📊</div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?php echo number_format($total_transactions); ?></h3>
                        <p class="stat-label">Total Transaksi</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters Section -->
        <section class="filters-section">
            <div class="filters-card">
                <h3>Filter Transaksi</h3>
                <form method="GET" class="filters-form" id="filtersForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status">Status Transaksi</label>
                            <select name="status" id="status" class="filter-select">
                                <option value="all">Semua Status</option>
                                <option value="success" <?php echo $filter_status === 'success' ? 'selected' : ''; ?>>Berhasil</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="comic">Komik</label>
                            <select name="comic" id="comic" class="filter-select">
                                <option value="">Semua Komik</option>
                                <?php while ($comic = $comics_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($comic['comic_title']); ?>" 
                                        <?php echo $filter_comic === $comic['comic_title'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($comic['comic_title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="date_from">Tanggal Mulai</label>
                            <input type="date" name="date_from" id="date_from" 
                                   value="<?php echo htmlspecialchars($filter_date_from); ?>" 
                                   class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to">Tanggal Akhir</label>
                            <input type="date" name="date_to" id="date_to" 
                                   value="<?php echo htmlspecialchars($filter_date_to); ?>" 
                                   class="filter-input">
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-icon">🔍</span>
                                Terapkan Filter
                            </button>
                            <button type="button" onclick="resetFilters()" class="btn btn-outline">
                                <span class="btn-icon">🔄</span>
                                Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Transactions Table -->
        <section class="transactions-section">
            <div class="section-header">
                <h2>📋 Daftar Transaksi</h2>
                <div class="section-actions">
                    <span class="badge"><?php echo number_format($total_transactions); ?> transaksi ditemukan</span>
                </div>
            </div>

            <div class="table-container">
                <?php if ($transactions_result->num_rows > 0): ?>
                    <table class="data-table" id="transactionsTable">
                        <thead>
                            <tr>
                                <th class="text-left">Komik & Chapter</th>
                                <th class="text-center">Pembeli</th>
                                <th class="text-center">Tanggal</th>
                                <th class="text-right">Jumlah</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
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
                                        <span class="status-badge <?php echo $transaction['transaction_status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'success' => 'Berhasil',
                                                'pending' => 'Pending', 
                                                'failed' => 'Gagal'
                                            ];
                                            echo $status_text[$transaction['transaction_status']] ?? $transaction['transaction_status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn-action btn-info" 
                                                onclick="showTransactionDetail(<?php echo $transaction['transaction_id']; ?>)"
                                                title="Lihat Detail">
                                            <span class="btn-icon">👁️</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" class="pagination-btn">
                                    ← Sebelumnya
                                </a>
                            <?php endif; ?>

                            <span class="pagination-info">
                                Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?>
                            </span>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" class="pagination-btn">
                                    Selanjutnya →
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">💸</div>
                        <h3>Tidak Ada Transaksi</h3>
                        <p>
                            <?php if (!empty($filter_status) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_comic)): ?>
                                Tidak ditemukan transaksi yang sesuai dengan filter yang dipilih.
                            <?php else: ?>
                                Belum ada transaksi yang tercatat. Transaksi akan muncul di sini ketika ada pembeli yang membeli chapter komik Anda.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($filter_status) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_comic)): ?>
                            <button onclick="resetFilters()" class="btn btn-primary">
                                <span class="btn-icon">🔄</span>
                                Tampilkan Semua Transaksi
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Transaction Detail Modal -->
    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Transaksi</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Detail will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <script src="script_transactions.js"></script>
</body>
</html>

<?php
$stmt_transactions->close();
$stmt_comics->close();
$koneksi->close();
?>