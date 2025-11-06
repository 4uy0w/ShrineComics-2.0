<?php
session_start();
include 'koneksi.php';

// Set header JSON pertama untuk memastikan tidak ada output lain
header('Content-Type: application/json');

// Error reporting untuk debugging
error_reporting(0); // Nonaktifkan error reporting untuk menghindari output tidak diinginkan

try {
    $comic_id = intval($_GET['comic_id'] ?? 0);
    $last_check = intval($_GET['last_check'] ?? 0);
    
    if ($comic_id <= 0) {
        throw new Exception("Comic ID tidak valid");
    }

    // Ambil judul komik berdasarkan ID
    $comic_sql = "SELECT comic_title FROM comic WHERE comic_id = ?";
    $stmt = $koneksi->prepare($comic_sql);
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed");
    }
    
    $stmt->bind_param("i", $comic_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed");
    }
    
    $comic_result = $stmt->get_result();
    
    if ($comic_result->num_rows === 0) {
        throw new Exception("Komik tidak ditemukan");
    }
    
    $comic = $comic_result->fetch_assoc();
    $stmt->close();

    // Build query untuk komentar baru
    $comments_sql = "SELECT 
                        comment_id,
                        comment_sender_name,
                        comment_sender_text,
                        comment_comic_name,
                        status,
                        created_at,
                        comment_likes,
                        comment_views
                    FROM comment 
                    WHERE comment_comic_name = ? 
                    AND status = 'approved'";
    
    // Tambahkan filter waktu jika last_check ada dan valid
    $params = [$comic['comic_title']];
    $param_types = "s";
    
    if ($last_check > 0) {
        $last_check_date = date('Y-m-d H:i:s', $last_check / 1000);
        $comments_sql .= " AND created_at > ?";
        $params[] = $last_check_date;
        $param_types .= "s";
    }
    
    $comments_sql .= " ORDER BY created_at DESC LIMIT 20";
    
    $stmt = $koneksi->prepare($comments_sql);
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed");
    }
    
    // Bind parameters
    $stmt->bind_param($param_types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed");
    }
    
    $comments_result = $stmt->get_result();
    $new_comments = [];
    
    while ($comment = $comments_result->fetch_assoc()) {
        // Format data untuk konsistensi
        $new_comments[] = [
            'comment_id' => $comment['comment_id'],
            'comment_sender_name' => $comment['comment_sender_name'],
            'comment_sender_text' => $comment['comment_sender_text'],
            'comment_comic_name' => $comment['comment_comic_name'],
            'status' => $comment['status'],
            'created_at' => $comment['created_at'],
            'comment_likes' => $comment['comment_likes'] ?? 0,
            'comment_views' => $comment['comment_views'] ?? 0
        ];
    }
    
    $stmt->close();

    // Format response
    $response = [
        'success' => true,
        'new_comments' => $new_comments,
        'count' => count($new_comments),
        'last_check' => time() * 1000, // Convert to milliseconds
        'comic_title' => $comic['comic_title'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Output JSON dan pastikan tidak ada output lain
    echo json_encode($response);
    exit();
    
} catch (Exception $e) {
    // Log error untuk debugging
    error_log("Error in check_new_comments.php: " . $e->getMessage());
    
    // Return error response dalam format JSON
    $error_response = [
        'success' => false,
        'message' => $e->getMessage(),
        'new_comments' => [],
        'count' => 0,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($error_response);
    exit();
}

// Pastikan koneksi ditutup
if (isset($koneksi)) {
    $koneksi->close();
}
?>