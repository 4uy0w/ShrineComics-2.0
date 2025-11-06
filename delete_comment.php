<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu'
    ]);
    exit();
}

// Cek apakah data JSON diterima
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['comment_id']) || empty($input['comment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Comment ID tidak valid'
    ]);
    exit();
}

$comment_id = intval($input['comment_id']);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'] ?? 'reader';

try {
    // Ambil data komentar untuk validasi ownership
    $check_sql = "SELECT comment_sender_name, comment_comic_name FROM comment WHERE comment_id = ?";
    $stmt_check = $koneksi->prepare($check_sql);
    $stmt_check->bind_param("i", $comment_id);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Komentar tidak ditemukan'
        ]);
        exit();
    }
    
    $comment_data = $check_result->fetch_assoc();
    $comment_author = $comment_data['comment_sender_name'];
    $comic_title = $comment_data['comment_comic_name'];
    $stmt_check->close();
    
    // Validasi: User hanya bisa menghapus komentar sendiri atau admin/writer bisa menghapus
    $can_delete = false;
    $delete_reason = "";
    
    if ($user_role === 'admin') {
        // Admin bisa menghapus semua komentar
        $can_delete = true;
        $delete_reason = "dihapus oleh admin";
    } elseif ($comment_author === $username) {
        // User hanya bisa menghapus komentar sendiri
        $can_delete = true;
        $delete_reason = "dihapus oleh penulis";
    } else {
        // Cek jika user adalah writer dari komik tersebut
        $comic_sql = "SELECT comic_writer FROM comic WHERE comic_title = ?";
        $stmt_comic = $koneksi->prepare($comic_sql);
        $stmt_comic->bind_param("s", $comic_title);
        $stmt_comic->execute();
        $comic_result = $stmt_comic->get_result();
        
        if ($comic_result->num_rows > 0) {
            $comic_data = $comic_result->fetch_assoc();
            if ($comic_data['comic_writer'] === $username) {
                // Writer bisa menghapus komentar di komik mereka sendiri
                $can_delete = true;
                $delete_reason = "dihapus oleh penulis komik";
            }
        }
        $stmt_comic->close();
    }
    
    if (!$can_delete) {
        echo json_encode([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini'
        ]);
        exit();
    }
    
    // OPTION 1: Soft delete (update status) - DIREKOMENDASIKAN
    $delete_sql = "UPDATE comment SET status = 'rejected' WHERE comment_id = ?";
    $stmt_delete = $koneksi->prepare($delete_sql);
    $stmt_delete->bind_param("i", $comment_id);
    
    // OPTION 2: Hard delete (hapus permanen) - uncomment baris berikut jika ingin hard delete
    // $delete_sql = "DELETE FROM comment WHERE comment_id = ?";
    // $stmt_delete = $koneksi->prepare($delete_sql);
    // $stmt_delete->bind_param("i", $comment_id);
    
    if ($stmt_delete->execute()) {
        // HAPUS BAGIAN ACTIVITY LOG karena tabel tidak ada
        // Cukup return success response
        
        echo json_encode([
            'success' => true,
            'message' => 'Komentar berhasil dihapus',
            'deleted_by' => $username,
            'delete_reason' => $delete_reason,
            'comment_id' => $comment_id
        ]);
    } else {
        throw new Exception("Gagal menghapus komentar: " . $stmt_delete->error);
    }
    
    $stmt_delete->close();
    
} catch (Exception $e) {
    error_log("Error in delete_comment.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}

$koneksi->close();
?>