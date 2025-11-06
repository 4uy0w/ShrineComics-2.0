<?php
session_start();
include 'koneksi.php';

// Set header JSON pertama kali
header('Content-Type: application/json');

// Cek authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'writer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Cek method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validasi input
$comic_id = $_POST['comic_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if ($comic_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid comic ID']);
    exit();
}

try {
    // Cek kepemilikan komik
    $check_sql = "SELECT comic_id, comic_title FROM comic WHERE comic_id = ? AND comic_writer = ?";
    $stmt = $koneksi->prepare($check_sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $koneksi->error);
    }
    
    $stmt->bind_param("is", $comic_id, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Komik tidak ditemukan atau Anda tidak memiliki akses']);
        exit();
    }
    
    $comic = $result->fetch_assoc();
    $comic_title = $comic['comic_title'];
    $stmt->close();
    
    // Mulai transaction
    $koneksi->begin_transaction();
    
    // Dapatkan judul komik untuk menghapus chapter
    $get_comic_title_sql = "SELECT comic_title FROM comic WHERE comic_id = ?";
    $stmt = $koneksi->prepare($get_comic_title_sql);
    $stmt->bind_param("i", $comic_id);
    $stmt->execute();
    $comic_data = $stmt->get_result()->fetch_assoc();
    $comic_title_for_chapters = $comic_data['comic_title'];
    $stmt->close();
    
    // Hapus chapters terkait
    $delete_chapters_sql = "DELETE FROM chapter WHERE chapter_comic = ?";
    $stmt = $koneksi->prepare($delete_chapters_sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $koneksi->error);
    }
    
    $stmt->bind_param("s", $comic_title_for_chapters);
    $chapters_deleted = $stmt->execute();
    $stmt->close();
    
    if (!$chapters_deleted) {
        throw new Exception("Gagal menghapus chapters terkait");
    }
    
    // Hapus komik
    $delete_comic_sql = "DELETE FROM comic WHERE comic_id = ?";
    $stmt = $koneksi->prepare($delete_comic_sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $koneksi->error);
    }
    
    $stmt->bind_param("i", $comic_id);
    $comic_deleted = $stmt->execute();
    $stmt->close();
    
    if ($comic_deleted) {
        $koneksi->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Komik '{$comic_title}' berhasil dihapus",
            'comic_id' => $comic_id
        ]);
    } else {
        throw new Exception("Gagal menghapus komik dari database");
    }
    
} catch (Exception $e) {
    // Rollback transaction jika ada error
    if (isset($koneksi) && $koneksi) {
        $koneksi->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_code' => 'DELETE_ERROR'
    ]);
}

// Tutup koneksi
if (isset($koneksi) && $koneksi) {
    $koneksi->close();
}
?>