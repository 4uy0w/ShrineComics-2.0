<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$comic_id = $_GET['comic_id'] ?? null;
$first_chapter = $_GET['first_chapter'] ?? false;

// Cek jika comic_id tidak ada
if (!$comic_id) {
    header("Location: dashboard_writer.php");
    exit();
}

// Cek apakah komik milik user yang login
$sql = "SELECT comic_id, comic_title, comic_writer FROM comic WHERE comic_id = ? AND comic_writer = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("is", $comic_id, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Komik tidak ditemukan atau bukan milik user
    header("Location: dashboard_writer.php");
    exit();
}

$comic = $result->fetch_assoc();
$stmt->close();

// Debug: Pastikan data komik ada
if (!$comic || !isset($comic['comic_title'])) {
    die("Error: Data komik tidak valid");
}

$errors = [];
$success = '';

// Get next chapter number
$chapter_sql = "SELECT MAX(chapter_number) as max_chapter FROM chapter WHERE chapter_comic = ?";
$chapter_stmt = $koneksi->prepare($chapter_sql);
$chapter_stmt->bind_param("s", $comic['comic_title']);
$chapter_stmt->execute();
$chapter_result = $chapter_stmt->get_result();
$chapter_data = $chapter_result->fetch_assoc();
$next_chapter_number = ($chapter_data['max_chapter'] ?? 0) + 1;
$chapter_stmt->close();

// Proses tambah chapter
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chapter_name = trim($_POST['chapter_name']);
    $chapter_number = intval($_POST['chapter_number']);
    $chapter_price = intval($_POST['chapter_price']);
    $chapter_release_date = $_POST['chapter_release_date'];

    // Validasi
    if (empty($chapter_name)) {
        $errors['chapter_name'] = "Nama chapter harus diisi";
    }

    if ($chapter_number < 1) {
        $errors['chapter_number'] = "Nomor chapter harus lebih dari 0";
    }

    if ($chapter_price < 0) {
        $errors['chapter_price'] = "Harga chapter tidak boleh negatif";
    }

    if (empty($chapter_release_date)) {
        $errors['chapter_release_date'] = "Tanggal rilis harus diisi";
    }

    // Cek nama chapter sudah ada
    if (empty($errors)) {
        $check_chapter = "SELECT chapter_name FROM chapter WHERE chapter_name = ?";
        $stmt = $koneksi->prepare($check_chapter);
        $stmt->bind_param("s", $chapter_name);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['chapter_name'] = "Nama chapter sudah digunakan";
        }
        $stmt->close();
    }

    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        $chapter_status = 'upload';
        
        $sql = "INSERT INTO chapter (chapter_name, chapter_comic, chapter_page, chapter_price, chapter_writer, chapter_release_date, chapter_number, chapter_status) 
                VALUES (?, ?, 0, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        
        // Debug: Pastikan semua parameter valid
        if (!$comic['comic_title']) {
            die("Error: comic_title is null");
        }
        
        $stmt->bind_param("ssissss", 
            $chapter_name, 
            $comic['comic_title'], 
            $chapter_price, 
            $username, 
            $chapter_release_date, 
            $chapter_number, 
            $chapter_status
        );
        
        if ($stmt->execute()) {
            $new_chapter_id = $stmt->insert_id;
            
            // Update comic chapter count
            $update_sql = "UPDATE comic SET comic_chapter = comic_chapter + 1 WHERE comic_id = ?";
            $update_stmt = $koneksi->prepare($update_sql);
            $update_stmt->bind_param("i", $comic_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $stmt->close();
            
            // Redirect ke halaman upload halaman chapter
            header("Location: add_chapter_pages.php?chapter_id=" . $new_chapter_id . "&comic_id=" . $comic_id);
            exit();
        } else {
            $errors['general'] = "Terjadi kesalahan saat menambah chapter: " . $stmt->error;
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Chapter - ShrineComics</title>
    <link rel="stylesheet" href="style_add_chapter.css">
</head>
<body>
    <div class="add-chapter-container">
        <div class="add-chapter-card">
            <div class="add-chapter-header">
                <h1><?php echo $first_chapter ? 'Buat Chapter Pertama' : 'Tambah Chapter Baru'; ?></h1>
                <p>Komik: <strong><?php echo htmlspecialchars($comic['comic_title']); ?></strong></p>
                <div class="header-actions">
                    <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="back-btn">← Kembali ke Detail Komik</a>
                    <a href="dashboard_writer.php" class="back-btn">← Dashboard</a>
                </div>
            </div>
            
            <?php if ($first_chapter): ?>
                <div class="info-message">
                    <h3>🎉 Komik Berhasil Dibuat!</h3>
                    <p>Sekarang buat chapter pertama untuk komik <strong><?php echo htmlspecialchars($comic['comic_title']); ?></strong></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error-message">
                    <h3>❌ Terjadi Kesalahan</h3>
                    <p><?php echo htmlspecialchars($errors['general']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="add_chapter.php?comic_id=<?php echo $comic_id; ?>" class="add-chapter-form">
                <div class="form-group">
                    <label for="chapter_name">Nama Chapter *</label>
                    <input type="text" id="chapter_name" name="chapter_name" required 
                           value="<?php echo isset($_POST['chapter_name']) ? htmlspecialchars($_POST['chapter_name']) : ''; ?>"
                           class="<?php echo isset($errors['chapter_name']) ? 'error' : ''; ?>"
                           placeholder="Contoh: Chapter 1 - Awal Petualangan">
                    <?php if (isset($errors['chapter_name'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['chapter_name']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="chapter_number">Nomor Chapter *</label>
                        <input type="number" id="chapter_number" name="chapter_number" required 
                               value="<?php echo isset($_POST['chapter_number']) ? $_POST['chapter_number'] : $next_chapter_number; ?>"
                               class="<?php echo isset($errors['chapter_number']) ? 'error' : ''; ?>"
                               min="1">
                        <?php if (isset($errors['chapter_number'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['chapter_number']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group with-padding">
                        <label for="chapter_price">Harga Chapter (Points)</label>
                        <input type="number" id="chapter_price" name="chapter_price" 
                               value="<?php echo isset($_POST['chapter_price']) ? $_POST['chapter_price'] : '0'; ?>"
                               class="<?php echo isset($errors['chapter_price']) ? 'error' : ''; ?>"
                               min="0" placeholder="0">
                        <?php if (isset($errors['chapter_price'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['chapter_price']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="chapter_release_date">Tanggal Rilis *</label>
                    <input type="date" id="chapter_release_date" name="chapter_release_date" required 
                           value="<?php echo isset($_POST['chapter_release_date']) ? $_POST['chapter_release_date'] : date('Y-m-d'); ?>"
                           class="<?php echo isset($errors['chapter_release_date']) ? 'error' : ''; ?>">
                    <?php if (isset($errors['chapter_release_date'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['chapter_release_date']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Buat Chapter & Upload Halaman</button>
                    <a href="comic_detail.php?id=<?php echo $comic_id; ?>" class="cancel-btn">Nanti Saja</a>
                </div>
                
            </form>
        </div>
    </div>

    <script src="script_add_chapter.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>