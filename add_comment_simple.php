<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');
ob_start();

try {
    // Validasi session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Silakan login terlebih dahulu");
    }

    $user_id = $_SESSION['user_id'];
    $comment_sender_text = trim($_POST['comment_sender_text'] ?? '');
    $comic_title = $_POST['comic_title'] ?? '';
    $comic_writer = $_POST['comic_writer'] ?? '';
    $comic_id = $_POST['comic_id'] ?? '';

    // Validasi
    if (empty($comment_sender_text)) {
        throw new Exception("Komentar tidak boleh kosong");
    }

    // DAPATKAN DATA USER
    $user_sql = "SELECT username, email FROM users WHERE user_id = ?";
    $user_stmt = $koneksi->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        throw new Exception("User tidak ditemukan");
    }
    
    $user = $user_result->fetch_assoc();
    $comment_sender_name = $user['username'];
    $comment_sender_email = $user['email'];
    $user_stmt->close();

    // VERIFIKASI: Cek apakah comic_title ada di tabel comic
    $comic_sql = "SELECT comic_title FROM comic WHERE comic_id = ?";
    $comic_stmt = $koneksi->prepare($comic_sql);
    $comic_stmt->bind_param("i", $comic_id);
    $comic_stmt->execute();
    $comic_result = $comic_stmt->get_result();
    
    if ($comic_result->num_rows === 0) {
        throw new Exception("Komik tidak ditemukan");
    }
    
    $comic_data = $comic_result->fetch_assoc();
    $actual_comic_title = $comic_data['comic_title']; // Gunakan comic_title dari database
    $comic_stmt->close();

    echo "Debug: " . $actual_comic_title . " vs " . $comic_title;

    // Insert komentar - GUNAKAN actual_comic_title untuk comment_comic_dest
    $sql = "INSERT INTO comment (
        comment_sender_name, 
        comment_sender_email, 
        comment_sender_text, 
        comment_comic_name, 
        comment_comic_writer,
        comment_comic_dest, 
        status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $koneksi->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $koneksi->error);
    }
    
    // comment_comic_dest harus sama dengan comic_title yang ada di tabel comic
    $bind_result = $stmt->bind_param("ssssss", 
        $comment_sender_name,
        $comment_sender_email,
        $comment_sender_text,
        $comic_title,        // comment_comic_name
        $comic_writer,       // comment_comic_writer  
        $actual_comic_title  // comment_comic_dest - HARUS SAMA DENGAN comic_title di tabel comic
    );
    
    if (!$bind_result) {
        throw new Exception("Bind failed: " . $stmt->error);
    }
    
    $execute_result = $stmt->execute();
    
    if ($execute_result) {
        $comment_id = $stmt->insert_id;
        
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Komentar berhasil dikirim! Menunggu persetujuan moderator.',
            'comment_id' => $comment_id
        ]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>