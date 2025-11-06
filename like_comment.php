<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$comment_id = intval($input['comment_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if ($comment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Comment ID tidak valid']);
    exit();
}

try {
    // Buat tabel comment_likes dengan struktur yang benar
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS comment_likes (
            like_id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            username VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_comment_user (comment_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    if (!$koneksi->query($create_table_sql)) {
        throw new Exception("Gagal membuat tabel comment_likes: " . $koneksi->error);
    }
    
    // Cek apakah komentar ada
    $check_sql = "SELECT comment_id FROM comment WHERE comment_id = ? AND status = 'approved'";
    $stmt_check = $koneksi->prepare($check_sql);
    $stmt_check->bind_param("i", $comment_id);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Komentar tidak ditemukan']);
        exit();
    }
    $stmt_check->close();
    
    // Cek status like saat ini - GUNAKAN like_id BUKAN id
    $check_like_sql = "SELECT like_id FROM comment_likes WHERE comment_id = ? AND user_id = ?";
    $stmt_check_like = $koneksi->prepare($check_like_sql);
    $stmt_check_like->bind_param("ii", $comment_id, $user_id);
    $stmt_check_like->execute();
    $like_exists = $stmt_check_like->get_result()->num_rows > 0;
    $stmt_check_like->close();
    
    if ($like_exists) {
        // UNLIKE - hapus like
        $delete_sql = "DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?";
        $stmt_delete = $koneksi->prepare($delete_sql);
        $stmt_delete->bind_param("ii", $comment_id, $user_id);
        
        if ($stmt_delete->execute()) {
            $action = 'unlike';
            $message = 'Like dihapus';
        } else {
            throw new Exception("Gagal menghapus like: " . $stmt_delete->error);
        }
        $stmt_delete->close();
    } else {
        // LIKE - tambah like
        $insert_sql = "INSERT INTO comment_likes (comment_id, user_id, username) VALUES (?, ?, ?)";
        $stmt_insert = $koneksi->prepare($insert_sql);
        $stmt_insert->bind_param("iis", $comment_id, $user_id, $username);
        
        if ($stmt_insert->execute()) {
            $action = 'like';
            $message = 'Like berhasil ditambahkan';
        } else {
            throw new Exception("Gagal menambahkan like: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    }
    
    // Hitung total likes terbaru
    $count_sql = "SELECT COUNT(*) as total_likes FROM comment_likes WHERE comment_id = ?";
    $stmt_count = $koneksi->prepare($count_sql);
    $stmt_count->bind_param("i", $comment_id);
    $stmt_count->execute();
    $count_result = $stmt_count->get_result();
    $total_likes = $count_result->fetch_assoc()['total_likes'];
    $stmt_count->close();
    
    // Update kolom comment_likes di tabel comment untuk konsistensi
    $update_comment_sql = "UPDATE comment SET comment_likes = ? WHERE comment_id = ?";
    $stmt_update = $koneksi->prepare($update_comment_sql);
    $stmt_update->bind_param("ii", $total_likes, $comment_id);
    $stmt_update->execute();
    $stmt_update->close();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'action' => $action,
        'likes_count' => $total_likes,
        'is_liked' => ($action === 'like')
    ]);
    
} catch (Exception $e) {
    error_log("Error in like_comment.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}

$koneksi->close();
?>