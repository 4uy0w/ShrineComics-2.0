<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu untuk membalas komentar'
    ]);
    exit();
}

// Validasi input
if (empty($_POST['parent_comment_id']) || empty($_POST['reply_text'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak lengkap'
    ]);
    exit();
}

$parent_comment_id = intval($_POST['parent_comment_id']);
$reply_text = trim($_POST['reply_text']);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Validasi panjang balasan
if (strlen($reply_text) > 500) {
    echo json_encode([
        'success' => false,
        'message' => 'Balasan terlalu panjang. Maksimal 500 karakter.'
    ]);
    exit();
}

if (empty($reply_text)) {
    echo json_encode([
        'success' => false,
        'message' => 'Balasan tidak boleh kosong'
    ]);
    exit();
}

try {
    // Cek apakah komentar parent ada
    $check_sql = "SELECT comment_id FROM comment WHERE comment_id = ? AND status = 'approved'";
    $stmt_check = $koneksi->prepare($check_sql);
    $stmt_check->bind_param("i", $parent_comment_id);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Komentar tidak ditemukan'
        ]);
        exit();
    }
    $stmt_check->close();
    
    // Insert balasan
    $insert_sql = "INSERT INTO comment_replies (comment_id, user_id, username, reply_text, status) VALUES (?, ?, ?, ?, 'approved')";
    $stmt_insert = $koneksi->prepare($insert_sql);
    $stmt_insert->bind_param("iiss", $parent_comment_id, $user_id, $username, $reply_text);
    
    if ($stmt_insert->execute()) {
        $reply_id = $stmt_insert->insert_id;
        
        // Ambil data balasan yang baru dibuat
        $reply_sql = "SELECT * FROM comment_replies WHERE reply_id = ?";
        $stmt_reply = $koneksi->prepare($reply_sql);
        $stmt_reply->bind_param("i", $reply_id);
        $stmt_reply->execute();
        $new_reply = $stmt_reply->get_result()->fetch_assoc();
        $stmt_reply->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Balasan berhasil dikirim!',
            'reply_data' => $new_reply
        ]);
    } else {
        throw new Exception("Gagal menyimpan balasan: " . $stmt_insert->error);
    }
    
    $stmt_insert->close();
    
} catch (Exception $e) {
    error_log("Error in add_reply.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}

$koneksi->close();
?>