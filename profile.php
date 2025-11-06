<?php
session_start();
require_once 'koneksi.php';

// Cek jika user belum login, redirect ke login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Tampilkan success message jika ada
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = 'Profile berhasil diupdate!';
}

// Query data user menggunakan fungsi executeQuery yang sudah ada
$user_data = getSingleRow("SELECT * FROM users WHERE user_id = ?", [$user_id], "i");

if (!$user_data) {
    die("User tidak ditemukan");
}

// Handle foto profil
$photo_profile = 'default-avatar.jpg';
if (!empty($user_data['photo_profile'])) {
    $photo_path = 'uploads/profiles/' . $user_data['photo_profile'];
    if (file_exists($photo_path)) {
        $photo_profile = $photo_path;
    }
}

// Tentukan dashboard URL berdasarkan role
$dashboard_url = '';
$dashboard_text = 'Back to Dashboard';

switch ($user_data['role']) {
    case 'writer':
        $dashboard_url = 'dashboard_writer.php';
        $dashboard_text = 'Writer Dashboard';
        break;
    case 'reader':
        $dashboard_url = 'dashboard_reader.php';
        $dashboard_text = 'Reader Dashboard';
        break;
    case 'admin':
        $dashboard_url = 'dashboard_admin.php';
        $dashboard_text = 'Admin Dashboard';
        break;
    default:
        $dashboard_url = 'dashboard.php';
        break;
}

// Query koleksi komik user
$library_query = "
    SELECT c.comic_id, c.comic_title, c.comic_banner, c.comic_genre, 
           MAX(ul.purchase_date) as last_purchase_date, 
           COUNT(DISTINCT ul.chapter_id) as chapters_owned
    FROM user_library ul 
    JOIN comic c ON ul.comic_id = c.comic_id 
    WHERE ul.user_id = ? 
    GROUP BY c.comic_id, c.comic_title, c.comic_banner, c.comic_genre
    ORDER BY last_purchase_date DESC 
    LIMIT 6
";
$library_result = executeQuery($library_query, [$user_id], "i");

// Query riwayat transaksi
$transaction_query = "
    SELECT t.transaction_id, t.transaction_date, t.transaction_point, t.transaction_status,
           c.comic_title, ch.chapter_name
    FROM transactions t
    JOIN comic c ON t.transaction_comic = c.comic_id
    JOIN chapter ch ON t.transaction_chapter = ch.chapter_id
    WHERE t.transaction_reader = ?
    ORDER BY t.transaction_date DESC 
    LIMIT 5
";
$transaction_result = executeQuery($transaction_query, [$user_id], "i");

// Query komentar terbaru
$comment_query = "
    SELECT c.comment_id, c.comment_sender_text, c.comment_comic_name, 
           c.comment_comic_dest, com.comic_banner
    FROM comment c
    JOIN comic com ON c.comment_comic_name = com.comic_title
    WHERE c.comment_sender_name = ?
    ORDER BY c.comment_id DESC 
    LIMIT 4
";
$comment_result = executeQuery($comment_query, [$user_data['username']], "s");

// Query statistik user
$stats_query = "
    SELECT 
        COUNT(DISTINCT ul.comic_id) as total_comics,
        COUNT(DISTINCT ul.chapter_id) as total_chapters,
        SUM(t.transaction_point) as total_spent
    FROM user_library ul
    LEFT JOIN transactions t ON ul.user_id = t.transaction_reader
    WHERE ul.user_id = ?
";
$stats_result = executeQuery($stats_query, [$user_id], "i");
$user_stats = $stats_result['success'] && !empty($stats_result['data']) ? $stats_result['data'][0] : null;

// Query reading history (komik yang baru dibaca)
$reading_history_query = "
    SELECT DISTINCT c.comic_id, c.comic_title, c.comic_banner, 
           MAX(ul.purchase_date) as last_read
    FROM user_library ul 
    JOIN comic c ON ul.comic_id = c.comic_id 
    WHERE ul.user_id = ? 
    GROUP BY c.comic_id, c.comic_title, c.comic_banner
    ORDER BY last_read DESC 
    LIMIT 4
";
$reading_history_result = executeQuery($reading_history_query, [$user_id], "i");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - ShrineComics</title>
    <link rel="stylesheet" href="profile.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="profile-container">
        <!-- Header Navigation -->
        <div class="header-navigation">
            <a href="<?php echo $dashboard_url; ?>" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php echo $dashboard_text; ?>
            </a>
        </div>

        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Header Profile -->
        <header class="profile-header">
            <div class="profile-info">
                <div class="avatar-section">
                    <img src="<?php echo $photo_profile; ?>" 
                         alt="Profile Picture" class="avatar"
                         onerror="this.onerror=null; this.src='default-avatar.jpg';">
                    <div class="online-status <?php echo strtolower($user_data['status']); ?>"></div>
                </div>
                <div class="user-details">
                    <h1 class="username"><?php echo htmlspecialchars($user_data['username']); ?></h1>
                    <p class="user-role">
                        <span class="role-badge <?php echo strtolower($user_data['role']); ?>">
                            <?php echo ucfirst($user_data['role']); ?>
                        </span>
                    </p>
                    <div class="user-stats">
                        <div class="stat">
                            <span class="stat-value"><?php echo $user_data['point']; ?></span>
                            <span class="stat-label">Points</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $user_stats ? $user_stats['total_comics'] : 0; ?></span>
                            <span class="stat-label">Comics</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $user_stats ? $user_stats['total_chapters'] : 0; ?></span>
                            <span class="stat-label">Chapters</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo date('M Y', strtotime($user_data['join_date'])); ?></span>
                            <span class="stat-label">Join Date</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="profile-actions">
                <a href="edit_profile.php" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.3333 1.99996C11.5084 1.82485 11.7163 1.686 11.945 1.59124C12.1737 1.49647 12.4189 1.44763 12.6667 1.44763C12.9144 1.44763 13.1596 1.49647 13.3883 1.59124C13.617 1.686 13.8249 1.82485 14 1.99996C14.1751 2.17507 14.314 2.38299 14.4087 2.6117C14.5035 2.84041 14.5523 3.08558 14.5523 3.33329C14.5523 3.58101 14.5035 3.82618 14.4087 4.05489C14.314 4.2836 14.1751 4.49152 14 4.66663L5.00001 13.6666L1.33334 14.6666L2.33334 11L11.3333 1.99996Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Edit Profile
                </a>
                <?php if ($user_data['role'] === 'reader'): ?>
                    <a href="topup_point.php" class="btn btn-success">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 1V15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                             <path d="M1 8H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Top-Up Point
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="profile-nav">
            <button class="nav-btn active" data-tab="overview">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 3H3C2.17157 3 1.5 3.67157 1.5 4.5V13.5C1.5 14.3284 2.17157 15 3 15H15C15.8284 15 16.5 14.3284 16.5 13.5V4.5C16.5 3.67157 15.8284 3 15 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M1.5 6H16.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Overview
            </button>
            <button class="nav-btn" data-tab="library">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 3H3V15H15V6H12V3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 3V6H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6 9H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6 12H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                My Library
            </button>
            <button class="nav-btn" data-tab="history">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 1C4.58172 1 1 4.58172 1 9C1 13.4183 4.58172 17 9 17C13.4183 17 17 13.4183 17 9C17 4.58172 13.4183 1 9 1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 4.5V9L12 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Reading History
            </button>
            <button class="nav-btn" data-tab="comments">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 8.33333C15 11.555 12.089 14.1111 8.5 14.1111C7.69371 14.1111 6.91796 13.9667 6.2 13.7L3 15L4.3 12.5667C3.57763 11.7472 3.08366 10.7453 2.86995 9.66311C2.65624 8.58095 2.73054 7.45702 3.085 6.41111" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6.5 6.5H11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6.5 9.5H9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                My Comments
            </button>
        </nav>

        <!-- Tab Content -->
        <main class="tab-content">
            <!-- Overview Tab -->
            <section id="overview" class="tab-pane active">
                <div class="overview-grid">
                    <!-- Quick Stats -->
                    <div class="stats-card">
                        <h3>Quick Stats</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-icon">📚</span>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo $user_stats ? $user_stats['total_comics'] : 0; ?></span>
                                    <span class="stat-label">Total Comics</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-icon">📖</span>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo $user_stats ? $user_stats['total_chapters'] : 0; ?></span>
                                    <span class="stat-label">Chapters Read</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-icon">💬</span>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo $comment_result['success'] ? count($comment_result['data']) : 0; ?></span>
                                    <span class="stat-label">Comments</span>
                                </div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-icon">⭐</span>
                                <div class="stat-info">
                                    <span class="stat-number"><?php echo $user_data['point']; ?></span>
                                    <span class="stat-label">Points</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="activity-card">
                        <h3>Recent Activity</h3>
                        <div class="activity-list">
                            <?php if ($library_result['success'] && !empty($library_result['data'])): ?>
                                <?php foreach(array_slice($library_result['data'], 0, 3) as $comic): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">📖</div>
                                    <div class="activity-content">
                                        <p>Read <strong><?php echo htmlspecialchars($comic['comic_title']); ?></strong></p>
                                        <span class="activity-time"><?php echo date('M d, Y', strtotime($comic['last_purchase_date'])); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-activity">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Profile Information -->
                    <div class="info-card">
                        <h3>Profile Information</h3>
                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label">Username:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Role:</span>
                                <span class="info-value role-badge <?php echo strtolower($user_data['role']); ?>">
                                    <?php echo ucfirst($user_data['role']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Join Date:</span>
                                <span class="info-value"><?php echo date('F d, Y', strtotime($user_data['join_date'])); ?></span>
                            </div>
                            <?php if (!empty($user_data['telephone_number'])): ?>
                            <div class="info-item">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['telephone_number']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- My Library Tab -->
            <section id="library" class="tab-pane">
                <div class="section-header">
                    <h2 class="section-title">My Comic Collection</h2>
                    <span class="section-count"><?php echo $library_result['success'] ? count($library_result['data']) : 0; ?> comics</span>
                </div>
                <div class="comic-grid">
                    <?php if ($library_result['success'] && !empty($library_result['data'])): ?>
                        <?php foreach($library_result['data'] as $comic): ?>
                        <div class="comic-card">
                            <div class="comic-cover-container">
                                <img src="<?php echo $comic['comic_banner'] ?: 'default-comic.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($comic['comic_title']); ?>" class="comic-cover"
                                     onerror="this.onerror=null; this.src='default-comic.jpg';">
                                <div class="comic-overlay">
                                    <button class="btn-read-again" data-comic-id="<?php echo $comic['comic_id']; ?>">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8 1C4.13401 1 1 4.13401 1 8C1 11.866 4.13401 15 8 15C11.866 15 15 11.866 15 8C15 4.13401 11.866 1 8 1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8 4V8L10.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        Baca Lagi
                                    </button>
                                </div>
                            </div>
                            <div class="comic-info">
                                <h3 class="comic-title"><?php echo htmlspecialchars($comic['comic_title']); ?></h3>
                                <p class="comic-genre"><?php echo htmlspecialchars($comic['comic_genre']); ?></p>
                                <div class="comic-meta">
                                    <span class="chapters"><?php echo $comic['chapters_owned']; ?> chapters</span>
                                    <span class="purchase-date"><?php echo date('M d, Y', strtotime($comic['last_purchase_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-container">
                            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M48 16L16 48" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 16L48 48" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 8H56V56H8V8Z" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="no-data">No comics in your library yet.</p>
                            <a href="browse.php" class="btn btn-primary">Browse Comics</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Reading History Tab -->
            <section id="history" class="tab-pane">
                <div class="section-header">
                    <h2 class="section-title">Reading History</h2>
                    <span class="section-count">Recently read comics</span>
                </div>
                <div class="history-grid">
                    <?php if ($reading_history_result['success'] && !empty($reading_history_result['data'])): ?>
                        <?php foreach($reading_history_result['data'] as $comic): ?>
                        <div class="history-card">
                            <img src="<?php echo $comic['comic_banner'] ?: 'default-comic.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($comic['comic_title']); ?>" class="history-cover"
                                 onerror="this.onerror=null; this.src='default-comic.jpg';">
                            <div class="history-info">
                                <h4 class="history-title"><?php echo htmlspecialchars($comic['comic_title']); ?></h4>
                                <span class="history-date">Last read: <?php echo date('M d, Y', strtotime($comic['last_read'])); ?></span>
                                <button class="btn-continue" data-comic-id="<?php echo $comic['comic_id']; ?>">
                                    Continue Reading
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-container">
                            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M48 16L16 48" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 16L48 48" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 8H56V56H8V8Z" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="no-data">No reading history yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Comments Tab -->
            <section id="comments" class="tab-pane">
                <div class="section-header">
                    <h2 class="section-title">My Comments</h2>
                    <span class="section-count"><?php echo $comment_result['success'] ? count($comment_result['data']) : 0; ?> comments</span>
                </div>
                <div class="comment-list">
                    <?php if ($comment_result['success'] && !empty($comment_result['data'])): ?>
                        <?php foreach($comment_result['data'] as $comment): ?>
                        <div class="comment-item">
                            <img src="<?php echo $comment['comic_banner'] ?: 'default-comic.jpg'; ?>" 
                                 alt="Comic Cover" class="comment-cover"
                                 onerror="this.onerror=null; this.src='default-comic.jpg';">
                            <div class="comment-content">
                                <h4 class="comment-comic"><?php echo htmlspecialchars($comment['comment_comic_name']); ?></h4>
                                <p class="comment-text"><?php echo htmlspecialchars($comment['comment_sender_text']); ?></p>
                                <span class="comment-dest">On: <?php echo htmlspecialchars($comment['comment_comic_dest']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-container">
                            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M48 16L16 48" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 16L48 48" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 8H56V56H8V8Z" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="no-data">No comments yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="profile.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>