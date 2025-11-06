<?php
session_start();
include 'koneksi.php';

// Set header JSON
header('Content-Type: application/json');

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Cek apakah parameter ID ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Transaction ID is required'
    ]);
    exit();
}

$transaction_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    // Query untuk mendapatkan detail transaksi
    $sql = "SELECT 
                t.*, 
                c.comic_title, 
                ch.chapter_name, 
                ch.chapter_number,
                ch.chapter_price,
                u_buyer.username as buyer_name,
                u_buyer.email as buyer_email,
                u_buyer.join_date as buyer_join_date,
                u_writer.username as writer_name
            FROM transactions t
            JOIN comic c ON t.transaction_comic = c.comic_id
            JOIN chapter ch ON t.transaction_chapter = ch.chapter_id
            JOIN users u_buyer ON t.transaction_reader = u_buyer.user_id
            JOIN users u_writer ON t.transaction_writer = u_writer.user_id
            WHERE t.transaction_id = ? AND t.transaction_writer = ?";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("ii", $transaction_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Transaksi tidak ditemukan atau Anda tidak memiliki akses'
        ]);
        exit();
    }
    
    $transaction = $result->fetch_assoc();
    
    // Format data untuk response
    $formatted_transaction = [
        'transaction_id' => $transaction['transaction_id'],
        'comic_title' => $transaction['comic_title'],
        'chapter_name' => $transaction['chapter_name'],
        'chapter_number' => $transaction['chapter_number'],
        'chapter_price' => $transaction['chapter_price'],
        'buyer_name' => $transaction['buyer_name'],
        'buyer_email' => $transaction['buyer_email'],
        'buyer_join_date' => $transaction['buyer_join_date'],
        'writer_name' => $transaction['writer_name'],
        'transaction_point' => $transaction['transaction_point'],
        'transaction_date' => $transaction['transaction_date'],
        'transaction_status' => $transaction['transaction_status'],
        'formatted_date' => date('d F Y H:i', strtotime($transaction['transaction_date'])),
        'formatted_buyer_join_date' => date('d F Y', strtotime($transaction['buyer_join_date']))
    ];
    
    // Get additional transaction statistics for this comic
    $stats_sql = "SELECT 
                    COUNT(*) as total_sales,
                    SUM(transaction_point) as total_earnings
                  FROM transactions 
                  WHERE transaction_comic = ? 
                  AND transaction_writer = ?
                  AND transaction_status = 'success'";
    
    $stmt_stats = $koneksi->prepare($stats_sql);
    $stmt_stats->bind_param("ii", $transaction['transaction_comic'], $user_id);
    $stmt_stats->execute();
    $stats_result = $stmt_stats->get_result();
    $stats = $stats_result->fetch_assoc();
    
    $formatted_transaction['comic_total_sales'] = $stats['total_sales'] ?? 0;
    $formatted_transaction['comic_total_earnings'] = $stats['total_earnings'] ?? 0;
    
    // Get recent transactions from this buyer
    $recent_sql = "SELECT 
                    t.transaction_id,
                    c.comic_title,
                    ch.chapter_number,
                    t.transaction_point,
                    t.transaction_date
                  FROM transactions t
                  JOIN comic c ON t.transaction_comic = c.comic_id
                  JOIN chapter ch ON t.transaction_chapter = ch.chapter_id
                  WHERE t.transaction_reader = ?
                  AND t.transaction_writer = ?
                  AND t.transaction_status = 'success'
                  ORDER BY t.transaction_date DESC
                  LIMIT 5";
    
    $stmt_recent = $koneksi->prepare($recent_sql);
    $stmt_recent->bind_param("ii", $transaction['transaction_reader'], $user_id);
    $stmt_recent->execute();
    $recent_result = $stmt_recent->get_result();
    
    $recent_transactions = [];
    while ($recent = $recent_result->fetch_assoc()) {
        $recent_transactions[] = [
            'transaction_id' => $recent['transaction_id'],
            'comic_title' => $recent['comic_title'],
            'chapter_number' => $recent['chapter_number'],
            'transaction_point' => $recent['transaction_point'],
            'transaction_date' => date('d M Y', strtotime($recent['transaction_date']))
        ];
    }
    
    $formatted_transaction['recent_transactions'] = $recent_transactions;
    
    echo json_encode([
        'success' => true,
        'transaction' => $formatted_transaction
    ]);
    
    $stmt->close();
    $stmt_stats->close();
    $stmt_recent->close();
    
} catch (Exception $e) {
    error_log("Error in get_transaction_detail.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ]);
}

$koneksi->close();
?>