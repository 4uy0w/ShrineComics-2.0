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
$reply_id = intval($input['reply_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'reader';

if ($reply_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Reply ID tidak valid']);
    exit();
}

try {
    // Cek ownership
    $check_sql = "SELECT user_id, username FROM comment_replies WHERE reply_id = ?";
    $stmt_check = $koneksi->prepare($check_sql);
    $stmt_check->bind_param("i", $reply_id);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Balasan tidak ditemukan']);
        exit();
    }
    
    $reply_data = $check_result->fetch_assoc();
    $stmt_check->close();
    
    // Validasi: hanya pemilik balasan atau admin yang bisa hapus
    if ($user_role !== 'admin' && $reply_data['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Anda hanya bisa menghapus balasan sendiri']);
        exit();
    }
    
    // Hapus balasan
    $delete_sql = "DELETE FROM comment_replies WHERE reply_id = ?";
    $stmt_delete = $koneksi->prepare($delete_sql);
    $stmt_delete->bind_param("i", $reply_id);
    
    if ($stmt_delete->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Balasan berhasil dihapus'
        ]);
    } else {
        throw new Exception("Gagal menghapus balasan");
    }
    
    $stmt_delete->close();
    
} catch (Exception $e) {
    error_log("Error in delete_reply.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
}

$koneksi->close();
?>