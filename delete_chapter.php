<?php
// delete_chapter.php - SUPER SAFE VERSION
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

$chapter_id = $_POST['chapter_id'] ?? 0;

if (!$chapter_id) {
    echo json_encode(['success' => false, 'message' => 'Chapter ID tidak valid']);
    exit;
}

// DAPATKAN NAMA CHAPTER SEBELUM HAPUS
$chapter_sql = "SELECT chapter_name FROM chapter WHERE chapter_id = ?";
$stmt = $koneksi->prepare($chapter_sql);
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$chapter_result = $stmt->get_result();

if ($chapter_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Chapter tidak ditemukan']);
    exit;
}

$chapter = $chapter_result->fetch_assoc();
$chapter_name = $chapter['chapter_name'];
$stmt->close();

try {
    // OPTION 1: Hapus dengan chapter_id (jika data sudah benar)
    $delete_pages_sql = "DELETE FROM chapter_page WHERE chapter_id = ?";
    $stmt = $koneksi->prepare($delete_pages_sql);
    $stmt->bind_param("i", $chapter_id);
    $stmt->execute();
    $deleted_by_id = $stmt->affected_rows;
    $stmt->close();

    // OPTION 2: Jika tidak ada yang terhapus, hapus dengan nama + writer
    if ($deleted_by_id == 0) {
        $user_sql = "SELECT username FROM users WHERE user_id = ?";
        $stmt = $koneksi->prepare($user_sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user = $user_result->fetch_assoc();
        $username = $user['username'];
        $stmt->close();

        $delete_pages_sql = "DELETE FROM chapter_page WHERE chapter_page_chapter = ? AND chapter_page_writer = ?";
        $stmt = $koneksi->prepare($delete_pages_sql);
        $stmt->bind_param("ss", $chapter_name, $username);
        $stmt->execute();
        $deleted_by_name = $stmt->affected_rows;
        $stmt->close();
    }

    // Hapus chapter
    $delete_chapter_sql = "DELETE FROM chapter WHERE chapter_id = ?";
    $stmt = $koneksi->prepare($delete_chapter_sql);
    $stmt->bind_param("i", $chapter_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true, 
        'message' => 'Chapter berhasil dihapus',
        'deleted_pages' => $deleted_by_id + $deleted_by_name
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal menghapus chapter: ' . $e->getMessage()
    ]);
}
?>