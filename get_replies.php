<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

$comment_id = intval($_GET['comment_id'] ?? 0);

if ($comment_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Comment ID tidak valid'
    ]);
    exit();
}

try {
    // Ambil semua balasan untuk komentar tertentu
    $replies_sql = "SELECT * FROM comment_replies WHERE comment_id = ? AND status = 'approved' ORDER BY created_at ASC";
    $stmt = $koneksi->prepare($replies_sql);
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $replies_result = $stmt->get_result();
    $replies = $replies_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Generate HTML untuk balasan
    $replies_html = '';
    foreach ($replies as $reply) {
        $reply_time = date('d M Y H:i', strtotime($reply['created_at']));
        $replies_html .= `
        <div class="reply-card" data-reply-id="${$reply['reply_id']}">
            <div class="reply-header">
                <span class="reply-author">${$reply['username']}</span>
                <span class="reply-time">${$reply_time}</span>
            </div>
            <div class="reply-content">
                <p class="reply-text">${htmlspecialchars($reply['reply_text'])}</p>
            </div>
            <div class="reply-actions">
                <button class="btn-icon" onclick="CommentSystem.deleteReply(${$reply['reply_id']})" title="Hapus Balasan">🗑️</button>
            </div>
        </div>
        `;
    }
    
    echo json_encode([
        'success' => true,
        'replies' => $replies,
        'replies_html' => $replies_html,
        'count' => count($replies)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_replies.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem'
    ]);
}

$koneksi->close();
?>