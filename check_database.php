<?php
require_once 'koneksi.php';

echo "<h1>🔍 Check Database Comment System</h1>";

// 1. Check koneksi
echo "<h2>1. Database Connection</h2>";
if ($koneksi->connect_error) {
    echo "<p style='color: red;'>❌ Connection failed: " . $koneksi->connect_error . "</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Database connected successfully</p>";
}

// 2. Check if comment table exists
echo "<h2>2. Comment Table Check</h2>";
$result = $koneksi->query("SHOW TABLES LIKE 'comment'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✅ Table 'comment' exists</p>";
    
    // Show table structure
    $structure = $koneksi->query("DESCRIBE comment");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f8f9fa;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Table 'comment' does NOT exist!</p>";
    echo "<p>Creating table...</p>";
    
    // Create table if doesn't exist
    $create_table = "CREATE TABLE comment (
        comment_id INT AUTO_INCREMENT PRIMARY KEY,
        comment_sender_name VARCHAR(512) NOT NULL,
        comment_sender_email VARCHAR(512) NOT NULL,
        comment_sender_text TEXT,
        comment_comic_name VARCHAR(512) NOT NULL,
        comment_comic_writer VARCHAR(512) NOT NULL,
        comment_comic_dest VARCHAR(512) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($koneksi->query($create_table)) {
        echo "<p style='color: green;'>✅ Table 'comment' created successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create table: " . $koneksi->error . "</p>";
    }
}

// 3. Test insert
echo "<h2>3. Test Insert Data</h2>";
$test_sql = "INSERT INTO comment (
    comment_sender_name, 
    comment_sender_email, 
    comment_sender_text, 
    comment_comic_name,
    comment_comic_writer,
    comment_comic_dest
) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $koneksi->prepare($test_sql);
if ($stmt) {
    $test_name = "Hikari25";
    $test_email = "Hikari25@email.com";
    $test_text = "This is a test comment from database check";
    $test_comic = "test009";
    $test_writer = "admin";
    $test_dest = "test009";
    
    $stmt->bind_param("ssssss", $test_name, $test_email, $test_text, $test_comic, $test_writer, $test_dest);
    
    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;
        echo "<p style='color: green;'>✅ Test insert successful! Comment ID: $last_id</p>";
        
        // Show the inserted data
        $result = $koneksi->query("SELECT * FROM comment WHERE comment_id = $last_id");
        $data = $result->fetch_assoc();
        
        echo "<h3>Inserted Data:</h3>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        
        // Clean up
        $koneksi->query("DELETE FROM comment WHERE comment_id = $last_id");
        echo "<p>✅ Test data cleaned up</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Test insert failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: red;'>❌ Prepare failed: " . $koneksi->error . "</p>";
}

// 4. Show existing comments
echo "<h2>4. Existing Comments in Database</h2>";
$result = $koneksi->query("SELECT COUNT(*) as total FROM comment");
$row = $result->fetch_assoc();
echo "<p>Total comments in database: <strong>{$row['total']}</strong></p>";

if ($row['total'] > 0) {
    $result = $koneksi->query("SELECT * FROM comment ORDER BY comment_id DESC LIMIT 5");
    echo "<h3>Latest 5 Comments:</h3>";
    echo "<div style='display: grid; gap: 10px;'>";
    while ($comment = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: #f8f9fa;'>";
        echo "<strong>👤 {$comment['comment_sender_name']}</strong> ({$comment['comment_sender_email']})<br>";
        echo "📖 Comic: {$comment['comment_comic_name']}<br>";
        echo "💬 {$comment['comment_sender_text']}<br>";
        echo "🕒 " . ($comment['created_at'] ?? 'No date') . " | ID: {$comment['comment_id']}";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ No comments found in database</p>";
}

$koneksi->close();
?>