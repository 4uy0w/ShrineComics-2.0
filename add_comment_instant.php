<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu untuk berkomentar'
    ]);
    exit();
}

// Validasi input
if (empty($_POST['comic_id']) || empty($_POST['comic_title']) || empty($_POST['comment_sender_text'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak lengkap'
    ]);
    exit();
}

$comic_id = $_POST['comic_id'];
$comic_title = $_POST['comic_title'];
$comic_writer = $_POST['comic_writer'];
$comment_sender_name = $_POST['comment_sender_name'];
$comment_sender_text = trim($_POST['comment_sender_text']);

// AMBIL EMAIL USER DARI DATABASE untuk memastikan consistency
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT email FROM users WHERE user_id = ?";
$stmt_user = $koneksi->prepare($user_sql);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'User tidak ditemukan'
    ]);
    exit();
}

$user_data = $user_result->fetch_assoc();
$comment_sender_email = $user_data['email']; // Gunakan email dari database, bukan dari session
$stmt_user->close();

// Validasi panjang komentar
if (strlen($comment_sender_text) > 1000) {
    echo json_encode([
        'success' => false,
        'message' => 'Komentar terlalu panjang. Maksimal 1000 karakter.'
    ]);
    exit();
}

if (empty($comment_sender_text)) {
    echo json_encode([
        'success' => false,
        'message' => 'Komentar tidak boleh kosong'
    ]);
    exit();
}

try {
    // Insert komentar dengan status langsung approved
    $sql = "INSERT INTO comment (
        comment_sender_name, 
        comment_sender_email, 
        comment_sender_text, 
        comment_comic_name, 
        comment_comic_writer,
        comment_comic_dest,
        status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param(
        "ssssss", 
        $comment_sender_name,
        $comment_sender_email, // Email dari database, bukan input user
        $comment_sender_text,
        $comic_title,
        $comic_writer,
        $comic_title
    );
    
    if ($stmt->execute()) {
        $comment_id = $stmt->insert_id;
        
        // Ambil data komentar yang baru dibuat untuk response
        $comment_sql = "SELECT * FROM comment WHERE comment_id = ?";
        $stmt_comment = $koneksi->prepare($comment_sql);
        $stmt_comment->bind_param("i", $comment_id);
        $stmt_comment->execute();
        $comment_result = $stmt_comment->get_result();
        $new_comment = $comment_result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Komentar berhasil dikirim!',
            'comment_data' => [
                'comment_id' => $new_comment['comment_id'],
                'comment_sender_name' => $new_comment['comment_sender_name'],
                'comment_sender_text' => $new_comment['comment_sender_text'],
                'created_at' => $new_comment['created_at'],
                'status' => $new_comment['status'],
                'comment_likes' => $new_comment['comment_likes'] ?? 0,
                'comment_views' => $new_comment['comment_views'] ?? 0
            ]
        ]);
        
        $stmt_comment->close();
    } else {
        throw new Exception("Gagal menyimpan komentar: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in add_comment_instant.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}

$koneksi->close();
?>