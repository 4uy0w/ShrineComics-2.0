<?php
// Include koneksi database
require_once 'koneksi.php';

// Start session untuk role management
session_start();

// Ambil ID komik dari URL
$comic_id = $_GET['id'] ?? 1;

// Query data komik menggunakan koneksi langsung
$comic_sql = "SELECT * FROM comic WHERE comic_id = ?";
$stmt = $koneksi->prepare($comic_sql);
$stmt->bind_param("i", $comic_id);
$stmt->execute();
$comic_result = $stmt->get_result();

if ($comic_result->num_rows === 0) {
    die("Komik tidak ditemukan!");
}

$comic = $comic_result->fetch_assoc();
$stmt->close();

// Cek role user dan ownership komik
$is_writer = false;
$can_add_chapter = false;
$user_points = 0;
$user_id = $_SESSION['user_id'] ?? 0;
$is_comic_owner = false;
$user_role = $_SESSION['user_role'] ?? 'reader';
$username = $_SESSION['username'] ?? '';

// DIBAWAH QUERY KOMENTAR, TAMBAHKAN:

// Query untuk mendapatkan status like user dan total likes per komentar
$user_id = $_SESSION['user_id'] ?? 0;
$comments_with_likes = [];

if (!empty($comments)) {
    foreach ($comments as $comment) {
        $comment_id = $comment['comment_id'];
        
        // Cek apakah user sudah like komentar ini
        $like_status_sql = "SELECT 1 FROM comment_likes WHERE comment_id = ? AND user_id = ?";
        $stmt_like = $koneksi->prepare($like_status_sql);
        $stmt_like->bind_param("ii", $comment_id, $user_id);
        $stmt_like->execute();
        $like_result = $stmt_like->get_result();
        $is_liked = $like_result->num_rows > 0;
        $stmt_like->close();
        
        // Hitung total likes untuk komentar ini
        $like_count_sql = "SELECT COUNT(*) as like_count FROM comment_likes WHERE comment_id = ?";
        $stmt_count = $koneksi->prepare($like_count_sql);
        $stmt_count->bind_param("i", $comment_id);
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $like_count = $count_result->fetch_assoc()['like_count'];
        $stmt_count->close();
        
        // Tambahkan data like ke komentar
        $comments_with_likes[] = array_merge($comment, [
            'is_liked' => $is_liked,
            'like_count' => $like_count
        ]);
    }
    
    // Ganti array comments dengan yang sudah include like data
    $comments = $comments_with_likes;
}

if (isset($_SESSION['user_id'])) {
    $user_sql = "SELECT role, username, point FROM users WHERE user_id = ?";
    $stmt = $koneksi->prepare($user_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
        $is_writer = ($user['role'] === 'writer');
        $can_add_chapter = $is_writer && ($user['username'] === $comic['comic_writer']);
        $user_points = $user['point'] ?? 0;
        $is_comic_owner = $is_writer && ($user['username'] === $comic['comic_writer']);
        $username = $user['username'];
        $user_email = $user['email'];
    }
    $stmt->close();
}

// PERBAIKAN BUG: Query chapters yang berbeda untuk writer vs reader
if ($is_comic_owner) {
    // Untuk writer pemilik komik: hanya ambil chapter yang mereka buat
    $chapters_sql = "
        SELECT c.*, 
               1 as is_owned,  -- Writer otomatis memiliki semua chapter mereka
               'success' as transaction_status
        FROM chapter c 
        WHERE c.chapter_comic = ? AND c.chapter_writer = ?
        ORDER BY c.chapter_number ASC
    ";
    $stmt = $koneksi->prepare($chapters_sql);
    $stmt->bind_param("ss", $comic['comic_title'], $username);
} else {
    // Untuk reader dan writer lain: query original dengan join
    $chapters_sql = "
        SELECT c.*, 
               COALESCE(ul.user_id, 0) as is_owned,
               COALESCE(t.transaction_status, '') as transaction_status
        FROM chapter c 
        LEFT JOIN user_library ul ON c.chapter_id = ul.chapter_id AND ul.user_id = ?
        LEFT JOIN transactions t ON c.chapter_id = t.transaction_chapter AND t.transaction_reader = ?
        WHERE c.chapter_comic = ? 
        ORDER BY c.chapter_number ASC
    ";
    $stmt = $koneksi->prepare($chapters_sql);
    $user_id_param = $user_id;
    $stmt->bind_param("iis", $user_id_param, $user_id_param, $comic['comic_title']);
}

$stmt->execute();
$chapters_result = $stmt->get_result();
$chapters = $chapters_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// HAPUS LOGIC OWNERSHIP YANG LAMA - sudah ditangani di query

// Query komentar dengan pagination dan sorting
$page = $_GET['comment_page'] ?? 1;
$comments_per_page = 10;
$offset = ($page - 1) * $comments_per_page;
$sort = $_GET['comment_sort'] ?? 'newest';

$sort_sql = "ORDER BY ";
switch($sort) {
    case 'oldest':
        $sort_sql .= "comment_id ASC";
        break;
    case 'most_liked':
        $sort_sql .= "comment_likes DESC, comment_id DESC";
        break;
    default:
        $sort_sql .= "comment_id DESC";
}

// GANTI query komentar yang lama dengan ini:
$comments_sql = "SELECT * FROM comment WHERE comment_comic_name = ? AND (status = 'approved' OR status = 'pending') $sort_sql LIMIT ? OFFSET ?";
$stmt = $koneksi->prepare($comments_sql);
$stmt->bind_param("sii", $comic['comic_title'], $comments_per_page, $offset);
$stmt->execute();
$comments_result = $stmt->get_result();
$comments = $comments_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Hitung total komentar untuk pagination - UPDATE juga
$total_comments_sql = "SELECT COUNT(*) as total FROM comment WHERE comment_comic_name = ? AND (status = 'approved' OR status = 'pending')";
$stmt = $koneksi->prepare($total_comments_sql);
$stmt->bind_param("s", $comic['comic_title']);
$stmt->execute();
$total_result = $stmt->get_result();
$total_comments = $total_result->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_comments / $comments_per_page);

// Hitung statistik
$total_chapters = count($chapters);
$latest_chapter = $chapters[0] ?? null;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($comic['comic_title']) ?> - ShrineComics</title>
    <link rel="stylesheet" href="style_comic_detail.css">
</head>
<body data-user-id="<?= $user_id ?>" 
      data-user-points="<?= $user_points ?>" 
      data-comic-id="<?= $comic_id ?>"
      data-is-writer="<?= $is_writer ? 'true' : 'false' ?>"
      data-is-comic-owner="<?= $is_comic_owner ? 'true' : 'false' ?>"
      data-user-role="<?= $user_role ?>"
      data-username="<?= htmlspecialchars($username) ?>"
      data-user-email="<?= htmlspecialchars($user_email ?? '') ?>">
    
    <!-- Navigation Header -->
    <nav class="top-navigation">
        <div class="nav-container">
            <?php if ($is_comic_owner): ?>
                <a href="dashboard_writer.php" class="btn btn-back-dashboard">
                    ← Kembali ke Dashboard Writer
                </a>
            <?php else: ?>
                <a href="dashboard_reader.php" class="btn btn-back-dashboard">
                    ← Kembali ke Dashboard
                </a>
            <?php endif; ?>
            
            <h1 class="nav-title">ShrineComics</h1>
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (!$is_comic_owner): ?>
                    <div class="user-points">
                        💰 <?= $user_points ?> points
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($can_add_chapter): ?>
                <a href="add_chapter.php?comic_id=<?= $comic_id ?>" class="btn btn-primary btn-small">
                    <span class="btn-icon">➕</span>
                    Tambah Chapter
                </a>
                <?php endif; ?>
                <a href="profile.php" class="btn btn-outline">👤 Profile</a>
            </div>
        </div>
    </nav>

    <div class="comic-detail-container">
        <!-- Writer Owner Banner -->
        <?php if ($is_comic_owner): ?>
        <div class="comic-owner-banner">
            <div class="owner-badge">✨ KOMIK MILIK ANDA</div>
            <p>Anda dapat membaca dan mengelola semua chapter secara gratis</p>
        </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="comic-header">
            <div class="comic-cover-section">
                <div class="comic-cover">
                    <img src="<?= htmlspecialchars($comic['comic_banner']) ?>" 
                         alt="<?= htmlspecialchars($comic['comic_title']) ?>" 
                         class="cover-image"
                         onerror="this.src='placeholder.jpg'">
                </div>
                
                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-item">
                        <span class="stat-icon">📊</span>
                        <span class="stat-text"><?= $total_chapters ?> Chapter</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-icon">💬</span>
                        <span class="stat-text"><?= $total_comments ?> Komentar</span>
                    </div>
                    <?php if (!$is_comic_owner): ?>
                    <div class="stat-item">
                        <span class="stat-icon">💰</span>
                        <span class="stat-text"><?= $user_points ?> Points</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="comic-info">
                <div class="comic-basic-info">
                    <h1 class="comic-title"><?= htmlspecialchars($comic['comic_title']) ?></h1>
                    <p class="comic-writer">Oleh: <span class="writer-name"><?= htmlspecialchars($comic['comic_writer']) ?></span></p>
                    
                    <div class="comic-meta">
                        <span class="genre-tag"><?= htmlspecialchars($comic['comic_genre']) ?></span>
                        <span class="status-badge">🟢 Ongoing</span>
                        <span class="rating-badge">⭐ 4.5</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="comic-main-actions">
                    <?php if($latest_chapter): ?>
                    <div class="primary-actions">
                        <?php 
                        $latest_owned = $latest_chapter['is_owned'] || ($latest_chapter['chapter_price'] ?? 0) == 0 || $is_comic_owner;
                        $latest_uploaded = ($latest_chapter['chapter_status'] ?? '') === 'upload';
                        $show_purchase_latest = !$is_comic_owner && !$latest_owned && ($latest_chapter['chapter_price'] ?? 0) > 0;
                        ?>
                        
                        <?php if($latest_uploaded && $latest_owned): ?>
                            <a href="read_chapter.php?chapter_id=<?= $latest_chapter['chapter_id'] ?>" class="btn btn-primary btn-extra-large">
                                <span class="btn-icon">📖</span>
                                <span class="btn-text">
                                    <strong><?= $is_comic_owner ? 'Baca Chapter Terbaru' : 'Baca Chapter Terbaru' ?></strong>
                                    <small>Chapter <?= $latest_chapter['chapter_number'] ?>: <?= htmlspecialchars($latest_chapter['chapter_name']) ?></small>
                                </span>
                            </a>
                        <?php elseif($latest_uploaded && $show_purchase_latest): ?>
                            <!-- Hanya tampilkan tombol beli untuk reader yang bukan pemilik -->
                            <button class="btn btn-warning btn-extra-large" 
                                    onclick="buyChapter(<?= $latest_chapter['chapter_id'] ?>, <?= $latest_chapter['chapter_price'] ?>, <?= $user_points ?>)">
                                <span class="btn-icon">💰</span>
                                <span class="btn-text">
                                    <strong>Beli Chapter Terbaru</strong>
                                    <small>Chapter <?= $latest_chapter['chapter_number'] ?> - <?= $latest_chapter['chapter_price'] ?> points</small>
                                </span>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-disabled btn-extra-large" disabled>
                                <span class="btn-icon">⏳</span>
                                <span class="btn-text">
                                    <strong>Chapter Sedang Diproses</strong>
                                    <small>Chapter <?= $latest_chapter['chapter_number'] ?> akan segera hadir</small>
                                </span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="primary-actions">
                        <button class="btn btn-disabled btn-extra-large" disabled>
                            <span class="btn-icon">⏳</span>
                            <span class="btn-text">
                                <strong>Belum Ada Chapter</strong>
                                <small>Chapter pertama sedang dalam persiapan</small>
                            </span>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="secondary-actions">
                        <?php if ($can_add_chapter): ?>
                        <a href="add_chapter.php?comic_id=<?= $comic_id ?>" class="btn btn-primary">
                            <span class="btn-icon">➕</span>
                            Tambah Chapter
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-secondary" onclick="toggleBookmark(<?= $comic_id ?>)">
                            ⭐ Bookmark
                        </button>
                        <button class="btn btn-outline" onclick="shareComic()">
                            📤 Share
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sinopsis Section -->
        <section class="synopsis-section">
            <h2 class="section-title">📖 Sinopsis Komik</h2>
            <div class="synopsis-content">
                <p class="synopsis-text"><?= nl2br(htmlspecialchars($comic['comic_comment'] ?: 'Deskripsi komik belum tersedia.')) ?></p>
            </div>
        </section>

        <!-- Chapters Grid Section - PERBAIKAN UTAMA BUG DISINI -->
        <section class="chapters-section">
            <div class="section-header">
                <h2 class="section-title">📚 Daftar Chapter (<?= $total_chapters ?>)</h2>
                
                <?php if ($can_add_chapter): ?>
                <div class="section-controls">
                    <a href="add_chapter.php?comic_id=<?= $comic_id ?>" class="btn btn-primary">
                        <span class="btn-icon">➕</span>
                        Tambah Chapter Baru
                    </a>
                    <div class="chapter-stats">
                        <span class="stat-badge">Total: <?= $total_chapters ?> chapter</span>
                        <?php if ($latest_chapter): ?>
                        <span class="stat-badge">Terbaru: Chapter <?= $latest_chapter['chapter_number'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($chapters)): ?>
            <div class="chapters-grid">
                <?php foreach($chapters as $index => $chapter): ?>
                <?php
                $chapter_price = $chapter['chapter_price'] ?? 0;
                $is_owned = $chapter['is_owned'] || $chapter_price == 0 || $is_comic_owner;
                $is_uploaded = ($chapter['chapter_status'] ?? '') === 'upload';
                $can_afford = $user_points >= $chapter_price;
                $show_purchase = !$is_comic_owner && !$is_owned && $chapter_price > 0;
                
                $card_class = 'chapter-card';
                if ($index === 0) $card_class .= ' latest';
                if ($is_owned) $card_class .= ' owned';
                if (!$is_owned && $chapter_price > 0 && !$is_comic_owner) $card_class .= ' purchasable';
                if ($is_comic_owner) $card_class .= ' writer-owned';
                ?>
                
                <div class="<?= $card_class ?>" data-chapter-id="<?= $chapter['chapter_id'] ?>">
                    <div class="chapter-header">
                        <div class="chapter-badge">
                            <?php if($index === 0): ?>
                            <!--<span class="badge-new">BARU</span>-->
                            <?php endif; ?>
                            <?php if($is_comic_owner): ?>
                            <span class="badge-writer">✍️ MILIK ANDA</span>
                            <?php elseif($is_owned): ?>
                            <span class="badge-owned">✅ DIMILIKI</span>
                            <?php endif; ?>
                            <span class="chapter-number">Chapter <?= $chapter['chapter_number'] ?></span>
                        </div>
                        <div class="chapter-price">
                            <?php if($is_comic_owner): ?>
                                <span class="writer-tag">✍️ Gratis untuk Anda</span>
                            <?php elseif($chapter_price > 0): ?>
                                <?php if($is_owned): ?>
                                <span class="owned-tag">✅ Sudah Dibeli</span>
                                <?php else: ?>
                                <span class="price-tag">💰 <?= $chapter_price ?> points</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="free-tag">🆓 Gratis</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="chapter-content">
                        <h3 class="chapter-title"><?= htmlspecialchars($chapter['chapter_name']) ?></h3>
                        <div class="chapter-meta">
                            <span class="meta-item">📅 <?= date('d M Y', strtotime($chapter['chapter_release_date'])) ?></span>
                            <span class="meta-item">📄 <?= $chapter['chapter_page'] ?> halaman</span>
                            <span class="meta-item status-<?= $chapter['chapter_status'] ?>"><?= $chapter['chapter_status'] === 'upload' ? '✅ Tersedia' : '⏳ Diproses' ?></span>
                        </div>
                    </div>
                    
                    <div class="chapter-actions">
                        <?php if($is_uploaded): ?>
                            <?php if($is_owned): ?>
                                <!-- Sudah dimiliki/gratis/writer - bisa baca -->
                                <a href="read_chapter.php?chapter_id=<?= $chapter['chapter_id'] ?>" class="btn btn-success btn-full">
                                    📖 Baca Chapter
                                </a>
                            <?php elseif($show_purchase): ?>
                                <!-- HANYA UNTUK READER: Belum dimiliki dan berbayar - tombol beli -->
                                <button class="btn btn-warning btn-full" 
                                        onclick="buyChapter(<?= $chapter['chapter_id'] ?>, <?= $chapter_price ?>, <?= $user_points ?>)"
                                        <?= !$can_afford ? 'disabled' : '' ?>>
                                    <?php if($can_afford): ?>
                                        💰 Beli Chapter - <?= $chapter_price ?> points
                                    <?php else: ?>
                                        ❌ Kurang <?= $chapter_price - $user_points ?> points
                                    <?php endif; ?>
                                </button>
                                
                                <?php if(!$can_afford): ?>
                                <div class="purchase-help">
                                    <small>💡 Top up point di halaman profile</small>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Untuk kasus lainnya -->
                                <a href="read_chapter.php?chapter_id=<?= $chapter['chapter_id'] ?>" class="btn btn-primary btn-full">
                                    📖 Baca Chapter
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-disabled btn-full" disabled>
                                ⏳ Sedang Diproses
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($can_add_chapter): ?>
                        <div class="chapter-management-actions">
                            <a href="edit_chapter.php?chapter_id=<?= $chapter['chapter_id'] ?>" class="btn-icon" title="Edit Chapter">
                                ✏️
                            </a>
                            <button class="btn-icon" onclick="deleteChapter(<?= $chapter['chapter_id'] ?>)" title="Hapus Chapter">
                                🗑️
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>Belum Ada Chapter</h3>
                <p>Chapter pertama sedang dalam persiapan.</p>
                <?php if ($can_add_chapter): ?>
                <div class="empty-actions">
                    <a href="add_chapter.php?comic_id=<?= $comic_id ?>" class="btn btn-primary">
                        <span class="btn-icon">➕</span>
                        Tambah Chapter Pertama
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Comments Section -->
        <section class="comments-section" id="comments">
            <div class="section-header">
                <h2 class="section-title">💬 Komentar Pembaca (<?= $total_comments ?>)</h2>
                
                <?php if (!empty($comments)): ?>
                <div class="comments-controls">
                    <select class="sort-select" onchange="sortComments(this.value)">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Terlama</option>
                        <option value="most_liked" <?= $sort === 'most_liked' ? 'selected' : '' ?>>Paling Disukai</option>
                    </select>
                    <button class="btn btn-outline btn-small" onclick="CommentSystem.manualRefreshComments()" title="Refresh Komentar">
                        🔄 Refresh
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="comments-refresh-controls">
                <div class="refresh-info">
                    ⚡ Auto-refresh: <span id="autoRefreshStatus">Aktif</span> 
                    (setiap 30 detik)
                </div>
                <button class="auto-refresh-toggle" id="autoRefreshToggle" onclick="CommentSystem.toggleAutoRefresh()">
                    Nonaktifkan
                </button>
            </div>
            
            <!-- Comment Form -->
            <?php if(isset($_SESSION['user_id'])): ?>
            <div class="comment-form-container">
                <div class="comment-form-header">
                    <div class="user-avatar-small">
                        <?= strtoupper(substr($username, 0, 1)) ?>
                    </div>
                    <h3>Tambah Komentar Baru</h3>
                </div>
                <form id="commentForm" method="POST" class="comment-form">
                    <input type="hidden" name="comic_id" value="<?= $comic_id ?>">
                    <input type="hidden" name="comic_title" value="<?= htmlspecialchars($comic['comic_title']) ?>">
                    <input type="hidden" name="comic_writer" value="<?= htmlspecialchars($comic['comic_writer']) ?>">
                    <input type="hidden" name="comment_sender_name" value="<?= htmlspecialchars($username) ?>">
                    <!--<input type="hidden" name="comment_sender_email" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">-->
                    
                    <div class="form-group">
                        <textarea name="comment_sender_text" class="form-textarea" 
                                  placeholder="Bagikan pendapat Anda tentang komik ini..." 
                                  rows="4" maxlength="1000" required></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span>/1000 karakter
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="CommentSystem.resetCommentForm()">Batal</button>
                        <button type="submit" class="btn btn-primary" id="submitCommentBtn">
                            <span class="btn-loading" id="commentLoading" style="display: none;">🔄</span>
                            <span id="commentBtnText">Kirim Komentar</span>
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="login-prompt-card">
                <div class="login-prompt-icon">💬</div>
                <div class="login-prompt-content">
                    <h3>Ingin Berkomentar?</h3>
                    <p>Login terlebih dahulu untuk berbagi pendapat tentang komik ini</p>
                    <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">
                        🔐 Login Sekarang
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Comments List -->
            <div class="comments-list-container">
                <?php if (!empty($comments)): ?>
                <div class="comments-list" id="commentsList">
                    <?php foreach($comments as $comment): ?>
                    <div class="comment-card" data-comment-id="<?= $comment['comment_id'] ?>">
                        <div class="comment-header">
                            <div class="comment-author">
                                <div class="author-avatar">
                                    <?= strtoupper(substr($comment['comment_sender_name'], 0, 1)) ?>
                                </div>
                                <div class="author-info">
                                    <span class="author-name <?= $comment['comment_sender_name'] === $comic['comic_writer'] ? 'owner' : '' ?>">
                                        <?= htmlspecialchars($comment['comment_sender_name']) ?>
                                        <?= $comment['comment_sender_name'] === $comic['comic_writer'] ? ' ✍️' : '' ?>
                                    </span>
                                    <span class="comment-meta">
                                        <span class="comment-time">
                                            <?= date('d M Y H:i', strtotime($comment['created_at'])) ?>
                                        </span>
                                        <?php if($comment['status'] === 'pending'): ?>
                                        <span class="comment-status pending">⏳ Menunggu Moderasi</span>
                                        <?php elseif($comment['status'] === 'approved'): ?>
                                        <span class="comment-status approved">✅ Disetujui</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if(isset($_SESSION['user_id']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['username'] === $comment['comment_sender_name'])): ?>
                            <div class="comment-actions"><!--
                                <button class="btn-icon" onclick="CommentSystem.reportComment(<?= $comment['comment_id'] ?>)" title="Laporkan">
                                    ⚠️
                                </button>-->
                                <?php if($_SESSION['user_role'] === 'admin'): ?>
                                <button class="btn-icon" onclick="CommentSystem.moderateComment(<?= $comment['comment_id'] ?>, 'approved')" title="Setujui">
                                    ✅
                                </button>
                                <button class="btn-icon" onclick="CommentSystem.moderateComment(<?= $comment['comment_id'] ?>, 'rejected')" title="Tolak">
                                    ❌
                                </button>
                                <?php endif; ?>
                                <?php if($_SESSION['username'] === $comment['comment_sender_name']): ?><!--
                                <button class="btn-icon" onclick="CommentSystem.editComment(<?= $comment['comment_id'] ?>)" title="Edit">
                                    ✏️
                                </button>-->
                                <button class="btn-icon" onclick="CommentSystem.deleteComment(<?= $comment['comment_id'] ?>)" title="Hapus">
                                    🗑️
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="comment-content">
                            <p class="comment-text"><?= nl2br(htmlspecialchars($comment['comment_sender_text'])) ?></p>
                        </div>
                        
                        <div class="comment-footer">
                            <button class="btn-like <?= $comment['is_liked'] ? 'liked' : '' ?>" onclick="CommentSystem.likeComment(<?= $comment['comment_id'] ?>)">
                                <span class="like-icon">👍</span>
                                <span class="like-count"><?= $comment['comment_likes'] ?? 0 ?></span>
                            </button>
                            <button class="btn-reply" onclick="CommentSystem.showReplyForm(<?= $comment['comment_id'] ?>)">
                                <span class="reply-icon">↩️</span>
                                Balas
                            </button>
                            <span class="comment-views">👁️ <?= $comment['comment_views'] ?? 0 ?></span>
                        </div><!--
                        <div class="reply-form-container" id="replyForm-<?= $comment['comment_id'] ?>" style="display: none;">
                            <form class="reply-form" onsubmit="CommentSystem.submitReply(event, <?= $comment['comment_id'] ?>)">
                                        <div class="form-group">
                                            <textarea name="reply_text" placeholder="Tulis balasan..." rows="2" maxlength="500" required></textarea>
                                            <div class="char-counter-small">
                                                <span class="reply-char-count">0</span>/500
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-outline btn-small" onclick="CommentSystem.hideReplyForm(<?= $comment['comment_id'] ?>)">Batal</button>
                                            <button type="submit" class="btn btn-primary btn-small">Kirim Balasan</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-outline btn-small" onclick="CommentSystem.hideReplyForm(<?= $comment['comment_id'] ?>)">Batal</button>
                                    <button type="submit" class="btn btn-primary btn-small">Kirim Balasan</button>
                                </div>
                            </form>
                        </div>-->
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="comments-pagination">
                    <div class="pagination-info">
                        Menampilkan <?= count($comments) ?> dari <?= $total_comments ?> komentar
                    </div>
                    <div class="pagination-controls">
                        <?php if($page > 1): ?>
                            <a href="?id=<?= $comic_id ?>&comment_page=<?= $page - 1 ?>&comment_sort=<?= $sort ?>#comments" class="btn btn-outline btn-small">
                                ← Sebelumnya
                            </a>
                        <?php endif; ?>
                        
                        <div class="page-numbers">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if($i == $page): ?>
                                    <span class="page-current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?id=<?= $comic_id ?>&comment_page=<?= $i ?>&comment_sort=<?= $sort ?>#comments" class="page-link"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?id=<?= $comic_id ?>&comment_page=<?= $page + 1 ?>&comment_sort=<?= $sort ?>#comments" class="btn btn-outline btn-small">
                                Selanjutnya →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-comments-state">
                    <div class="empty-icon">💬</div>
                    <h3>Belum Ada Komentar</h3>
                    <p>Jadilah yang pertama berbagi pendapat tentang komik ini!</p>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                    <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">
                        🔐 Login untuk Berkomentar
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Footer Navigation -->
        <footer class="page-footer">
            <div class="footer-nav">
                <?php if ($is_comic_owner): ?>
                    <a href="dashboard_writer.php" class="btn btn-outline">🏠 Dashboard Writer</a>
                <?php else: ?>
                    <a href="dashboard_reader.php" class="btn btn-outline">🏠 Dashboard</a>
                <?php endif; ?>
                
                <?php if ($can_add_chapter): ?>
                <a href="add_chapter.php?comic_id=<?= $comic_id ?>" class="btn btn-primary">
                    <span class="btn-icon">➕</span>
                    Tambah Chapter
                </a>
                <?php endif; ?>
            </div>
        </footer>
    </div>

    <!-- Purchase Modal (Hanya untuk reader) -->
    <?php if (!$is_comic_owner): ?>
    <div id="purchaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Konfirmasi Pembelian</h3>
                <span class="close-modal" onclick="closePurchaseModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="purchaseMessage"></p>
                <div class="purchase-details">
                    <div class="detail-item">
                        <span>Chapter:</span>
                        <span id="detailChapter"></span>
                    </div>
                    <div class="detail-item">
                        <span>Harga:</span>
                        <span id="detailPrice"></span>
                    </div>
                    <div class="detail-item">
                        <span>Point Anda:</span>
                        <span id="detailBalance"></span>
                    </div>
                    <div class="detail-item total">
                        <span>Sisa Point:</span>
                        <span id="detailRemaining"></span>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closePurchaseModal()">Batal</button>
                <button class="btn btn-warning" id="confirmPurchaseBtn">Beli Sekarang</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="script_comic_detail.js"></script>
</body>
</html>