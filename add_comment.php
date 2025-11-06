<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

// Validasi input
$required_fields = ['comic_id', 'comic_title', 'comic_writer', 'comment_sender_name', 'comment_sender_text'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap: ' . $field]);
        exit;
    }
}

$comic_id = $_POST['comic_id'];
$comic_title = $_POST['comic_title'];
$comic_writer = $_POST['comic_writer'];
$comment_sender_name = $_POST['comment_sender_name'];
$comment_sender_email = $_POST['comment_sender_email'] ?? '';
$comment_sender_text = trim($_POST['comment_sender_text']);

// Validasi panjang komentar
if (strlen($comment_sender_text) < 1) {
    echo json_encode(['success' => false, 'message' => 'Komentar tidak boleh kosong']);
    exit;
}

if (strlen($comment_sender_text) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Komentar terlalu panjang. Maksimal 1000 karakter']);
    exit;
}

try {
    // Debug: Tampilkan data yang akan diinsert
    error_log("Inserting comment: " . print_r([
        'name' => $comment_sender_name,
        'email' => $comment_sender_email,
        'text' => substr($comment_sender_text, 0, 100) . '...',
        'comic' => $comic_title,
        'writer' => $comic_writer,
        'dest' => $comic_id
    ], true));

    // Cek struktur tabel dan sesuaikan query
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
    
    $bind_result = $stmt->bind_param(
        "ssssss", 
        $comment_sender_name,
        $comment_sender_email,
        $comment_sender_text,
        $comic_title,
        $comic_writer,
        $comic_id
    );
    
    if (!$bind_result) {
        throw new Exception("Bind failed: " . $stmt->error);
    }
    
    $execute_result = $stmt->execute();
    
    if ($execute_result) {
        $comment_id = $stmt->insert_id;
        
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
    error_log("Comment error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

// Tutup koneksi
$koneksi->close();
?>