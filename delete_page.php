<?php
session_start();
include 'koneksi.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Cek method request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Handle both JSON and FormData input
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    // JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $page_id = $input['page_id'] ?? null;
} else {
    // FormData input
    $page_id = $_POST['page_id'] ?? null;
}

$username = $_SESSION['username'];

// Validasi input - hanya page_id yang wajib
if (!$page_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameter: page_id']);
    exit();
}

// Validasi numeric ID
if (!is_numeric($page_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameter format']);
    exit();
}

try {
    // Cek kepemilikan page - cari chapter_id dari page_id
    $check_sql = "SELECT cp.chapter_page_id, cp.chapter_page_image, cp.chapter_page_number, 
                         cp.chapter_page_chapter, c.chapter_id
                  FROM chapter_page cp
                  JOIN chapter c ON cp.chapter_page_chapter = c.chapter_name
                  WHERE cp.chapter_page_id = ? 
                    AND cp.chapter_page_writer = ?";
    
    $check_stmt = $koneksi->prepare($check_sql);
    $check_stmt->bind_param("is", $page_id, $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Page not found or access denied']);
        exit();
    }
    
    $page_data = $check_result->fetch_assoc();
    $chapter_id = $page_data['chapter_id'];
    $check_stmt->close();
    
    // Mulai transaction
    $koneksi->begin_transaction();
    
    // Hapus file fisik
    $file_deleted = false;
    if (!empty($page_data['chapter_page_image']) && file_exists($page_data['chapter_page_image'])) {
        $file_deleted = unlink($page_data['chapter_page_image']);
        if (!$file_deleted) {
            error_log("Failed to delete file: " . $page_data['chapter_page_image']);
        }
    }
    
    // Hapus dari database
    $delete_sql = "DELETE FROM chapter_page WHERE chapter_page_id = ?";
    $delete_stmt = $koneksi->prepare($delete_sql);
    $delete_stmt->bind_param("i", $page_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('Failed to delete from database: ' . $delete_stmt->error);
    }
    $delete_stmt->close();
    
    // Update chapter page count
    $update_sql = "UPDATE chapter SET chapter_page = chapter_page - 1 WHERE chapter_id = ?";
    $update_stmt = $koneksi->prepare($update_sql);
    $update_stmt->bind_param("i", $chapter_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update chapter count: ' . $update_stmt->error);
    }
    $update_stmt->close();
    
    // Commit transaction
    $koneksi->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Halaman berhasil dihapus!',
        'file_deleted' => $file_deleted
    ]);
    
} catch (Exception $e) {
    // Rollback transaction jika error
    $koneksi->rollback();
    
    error_log("Delete page error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$koneksi->close();
?>