<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role reader
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'reader') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chapter_id = $_POST['chapter_id'] ?? null;
    $comic_id = $_POST['comic_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    
    if (!$chapter_id || !$comic_id) {
        header("Location: dashboard_reader.php");
        exit();
    }
    
    // Get chapter details
    $chapter_sql = "SELECT * FROM chapter WHERE chapter_id = ?";
    $chapter_stmt = $koneksi->prepare($chapter_sql);
    $chapter_stmt->bind_param("i", $chapter_id);
    $chapter_stmt->execute();
    $chapter_result = $chapter_stmt->get_result();
    
    if ($chapter_result->num_rows === 0) {
        header("Location: dashboard_reader.php");
        exit();
    }
    
    $chapter = $chapter_result->fetch_assoc();
    $chapter_stmt->close();
    
    // Get user points
    $user_sql = "SELECT point FROM users WHERE user_id = ?";
    $user_stmt = $koneksi->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_points = $user_data['point'] ?? 0;
    $user_stmt->close();
    
    // Check if user has enough points
    if ($user_points >= $chapter['chapter_price']) {
        // Deduct points
        $new_points = $user_points - $chapter['chapter_price'];
        $update_sql = "UPDATE users SET point = ? WHERE user_id = ?";
        $update_stmt = $koneksi->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_points, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Record purchase
        $purchase_sql = "INSERT INTO purchases (user_id, chapter_id, purchase_date, amount_paid) VALUES (?, ?, NOW(), ?)";
        $purchase_stmt = $koneksi->prepare($purchase_sql);
        $purchase_stmt->bind_param("iii", $user_id, $chapter_id, $chapter['chapter_price']);
        $purchase_stmt->execute();
        $purchase_stmt->close();
        
        // Redirect to reading page
        header("Location: show_comic.php?id=" . $comic_id . "&chapter_id=" . $chapter_id . "&purchased=1");
        exit();
    } else {
        header("Location: show_comic.php?id=" . $comic_id . "&chapter_id=" . $chapter_id . "&error=insufficient_points");
        exit();
    }
}

header("Location: dashboard_reader.php");
exit();
?>