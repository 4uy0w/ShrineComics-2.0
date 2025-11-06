<?php
session_start();
include 'koneksi.php';

// Set header JSON pertama kali
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'writer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $user_sql = "SELECT point FROM users WHERE user_id = ?";
    $stmt = $koneksi->prepare($user_sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $koneksi->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        echo json_encode([
            'success' => true, 
            'points' => $user_data['point'],
            'user_id' => $user_id
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_code' => 'POINTS_FETCH_ERROR'
    ]);
}

$koneksi->close();
?>