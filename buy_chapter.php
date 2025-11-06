<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

// Validasi method request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Validasi input
$chapter_id = $_POST['chapter_id'] ?? 0;
$chapter_price = $_POST['chapter_price'] ?? 0;

if ($chapter_id <= 0 || $chapter_price < 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

try {
    // Mulai transaction
    $koneksi->begin_transaction();
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? 'reader';
    
    // 1. Dapatkan data user
    $user_sql = "SELECT user_id, username, point, role FROM users WHERE user_id = ?";
    $stmt = $koneksi->prepare($user_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        throw new Exception("User tidak ditemukan");
    }
    
    $user = $user_result->fetch_assoc();
    $stmt->close();

    // 2. Dapatkan data chapter dan comic
    $chapter_sql = "
        SELECT ch.*, c.comic_id, c.comic_title, c.comic_writer 
        FROM chapter ch 
        JOIN comic c ON ch.chapter_comic = c.comic_title 
        WHERE ch.chapter_id = ?
    ";
    $stmt = $koneksi->prepare($chapter_sql);
    $stmt->bind_param("i", $chapter_id);
    $stmt->execute();
    $chapter_result = $stmt->get_result();
    
    if ($chapter_result->num_rows === 0) {
        throw new Exception("Chapter tidak ditemukan");
    }
    
    $chapter_data = $chapter_result->fetch_assoc();
    $stmt->close();

    // 3. CEK: Jika user adalah writer DAN komik ini miliknya, beri akses gratis
    if ($user['role'] === 'writer' && $user['username'] === $chapter_data['comic_writer']) {
        // Writer pemilik komik - akses gratis
        echo json_encode([
            'success' => true, 
            'message' => 'Akses gratis sebagai penulis komik',
            'new_balance' => $user['point'],
            'free_access' => true
        ]);
        exit;
    }

    // 4. Untuk READER atau WRITER yang beli komik orang lain: lanjut proses pembelian
    // Cek apakah user sudah memiliki chapter ini
    $check_ownership_sql = "SELECT * FROM user_library WHERE user_id = ? AND chapter_id = ?";
    $stmt = $koneksi->prepare($check_ownership_sql);
    $stmt->bind_param("ii", $user_id, $chapter_id);
    $stmt->execute();
    $ownership_result = $stmt->get_result();
    
    if ($ownership_result->num_rows > 0) {
        throw new Exception("Anda sudah memiliki chapter ini");
    }
    $stmt->close();
    
    // 5. Cek saldo point user (kecuali chapter gratis)
    if ($chapter_price > 0 && $user['point'] < $chapter_price) {
        throw new Exception("Point tidak cukup. Dibutuhkan: " . $chapter_price . " point, Anda memiliki: " . $user['point'] . " point");
    }
    
    // 6. Cek status chapter
    if ($chapter_data['chapter_status'] !== 'upload') {
        throw new Exception("Chapter belum tersedia untuk dibaca");
    }
    
    // 7. Dapatkan writer_id (penerima point)
    $writer_sql = "SELECT user_id, point FROM users WHERE username = ? AND role = 'writer'";
    $stmt = $koneksi->prepare($writer_sql);
    $stmt->bind_param("s", $chapter_data['comic_writer']);
    $stmt->execute();
    $writer_result = $stmt->get_result();
    
    if ($writer_result->num_rows === 0) {
        throw new Exception("Penulis tidak ditemukan");
    }
    
    $writer = $writer_result->fetch_assoc();
    $writer_id = $writer['user_id'];
    $writer_current_points = $writer['point'];
    $stmt->close();

    // === DEBUG INFO ===
    error_log("=== PROSES PEMBELIAN CHAPTER ===");
    error_log("Pembeli: " . $user['username'] . " (ID: " . $user_id . ", Role: " . $user['role'] . ")");
    error_log("Saldo awal pembeli: " . $user['point'] . " points");
    error_log("Writer: " . $chapter_data['comic_writer'] . " (ID: " . $writer_id . ")");
    error_log("Saldo awal writer: " . $writer_current_points . " points");
    error_log("Harga chapter: " . $chapter_price . " points");
    
    // 8. Jika chapter berbayar, proses transfer point
    if ($chapter_price > 0) {
        // Kurangi point pembeli (reader/writer yang beli komik orang lain)
        $update_buyer_sql = "UPDATE users SET point = point - ? WHERE user_id = ?";
        $stmt = $koneksi->prepare($update_buyer_sql);
        $stmt->bind_param("ii", $chapter_price, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal mengurangi point pembeli");
        }
        $stmt->close();
        
        // Tambah point writer
        $update_writer_sql = "UPDATE users SET point = point + ? WHERE user_id = ?";
        $stmt = $koneksi->prepare($update_writer_sql);
        $stmt->bind_param("ii", $chapter_price, $writer_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menambah point writer");
        }
        $stmt->close();
        
        $new_balance = $user['point'] - $chapter_price;

        // === DEBUG AFTER TRANSFER ===
        error_log("Saldo akhir pembeli: " . $new_balance . " points");
        error_log("Saldo akhir writer: " . ($writer_current_points + $chapter_price) . " points");
        
    } else {
        // Chapter gratis, saldo tetap
        $new_balance = $user['point'];
        error_log("Chapter GRATIS - tidak ada transfer point");
    }
    
    // 9. Catat transaksi
    $transaction_sql = "
        INSERT INTO transactions 
        (transaction_reader, transaction_writer, transaction_comic, transaction_chapter, transaction_point, transaction_date, transaction_status) 
        VALUES (?, ?, ?, ?, ?, NOW(), 'success')
    ";
    $stmt = $koneksi->prepare($transaction_sql);
    $comic_id = $chapter_data['comic_id'];
    $transaction_point = $chapter_price; // Bisa 0 untuk chapter gratis
    $stmt->bind_param("iiiii", $user_id, $writer_id, $comic_id, $chapter_id, $transaction_point);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal mencatat transaksi");
    }
    
    $transaction_id = $stmt->insert_id;
    $stmt->close();
    
    // 10. Tambah ke library user
    $library_sql = "
        INSERT INTO user_library 
        (user_id, chapter_id, comic_id, transaction_id, purchase_date) 
        VALUES (?, ?, ?, ?, NOW())
    ";
    $stmt = $koneksi->prepare($library_sql);
    $stmt->bind_param("iiii", $user_id, $chapter_id, $comic_id, $transaction_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menambah ke library");
    }
    $stmt->close();
    
    // Commit transaction
    $koneksi->commit();

    error_log("=== TRANSAKSI BERHASIL ===");
    
    // Update session
    $_SESSION['user_points'] = $new_balance;
    
    // Response sukses
    echo json_encode([
        'success' => true, 
        'message' => $chapter_price > 0 ? 'Pembelian berhasil! Point berkurang ' . $chapter_price . ' points.' : 'Chapter gratis berhasil ditambahkan!',
        'new_balance' => $new_balance,
        'transaction_id' => $transaction_id,
        'chapter_id' => $chapter_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction jika ada error
    $koneksi->rollback();
    
    error_log("=== ERROR: " . $e->getMessage() . " ===");
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>