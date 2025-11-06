<?php
session_start();
require_once 'koneksi.php';

// Set header JSON first
header('Content-Type: application/json');

// Cek authentication dan role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

$chapter_id = $input['chapter_id'] ?? 0;
$page_order = $input['page_order'] ?? [];

if (empty($page_order)) {
    echo json_encode(['success' => false, 'message' => 'No page order data received']);
    exit;
}

// Cek apakah user memiliki akses ke chapter ini
$chapter_sql = "SELECT c.*, co.comic_writer FROM chapter c 
                JOIN comic co ON c.chapter_comic = co.comic_title 
                WHERE c.chapter_id = ?";
$stmt = $koneksi->prepare($chapter_sql);
$stmt->bind_param("i", $chapter_id);
$stmt->execute();
$chapter_result = $stmt->get_result();

if ($chapter_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Chapter tidak ditemukan']);
    exit;
}

$chapter = $chapter_result->fetch_assoc();
$stmt->close();

// Cek ownership
$user_sql = "SELECT username FROM users WHERE user_id = ?";
$stmt = $koneksi->prepare($user_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

if ($user['username'] !== $chapter['comic_writer']) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengubah chapter ini']);
    exit;
}

try {
    // Start transaction
    $koneksi->begin_transaction();
    
    // Update each page number
    foreach ($page_order as $page) {
        $page_id = intval($page['id']);
        $new_number = intval($page['number']);
        
        // Validasi data
        if ($page_id <= 0 || $new_number <= 0) {
            throw new Exception("Invalid page data: ID {$page_id}, Number {$new_number}");
        }
        
        $update_sql = "UPDATE chapter_page SET chapter_page_number = ? WHERE chapter_page_id = ?";
        $stmt = $koneksi->prepare($update_sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $koneksi->error);
        }
        
        $stmt->bind_param("ii", $new_number, $page_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for page {$page_id}: " . $stmt->error);
        }
        $stmt->close();
    }
    
    // Commit transaction
    $koneksi->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Urutan halaman berhasil diperbarui!',
        'updated_count' => count($page_order)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $koneksi->rollback();
    
    error_log("Update page order error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal memperbarui urutan: ' . $e->getMessage()
    ]);
}

// Close connection
$koneksi->close();
?>