<?php
// fix_chapter_pages.php
require_once 'koneksi.php';

echo "=== FIXING CHAPTER PAGES ===<br>";

// 1. Pastikan kolom chapter_id ada
$check_column = "SHOW COLUMNS FROM chapter_page LIKE 'chapter_id'";
$result = $koneksi->query($check_column);
if ($result->num_rows == 0) {
    echo "Adding chapter_id column...<br>";
    $koneksi->query("ALTER TABLE chapter_page ADD COLUMN chapter_id INT");
}

// 2. Update chapter_id untuk semua records
$update_sql = "UPDATE chapter_page cp
               JOIN chapter c ON cp.chapter_page_chapter = c.chapter_name 
               AND cp.chapter_page_writer = c.chapter_writer
               SET cp.chapter_id = c.chapter_id
               WHERE cp.chapter_id IS NULL OR cp.chapter_id = 0";

$result = $koneksi->query($update_sql);
echo "Updated records: " . $koneksi->affected_rows . "<br>";

// 3. Cek hasil
$check_sql = "SELECT COUNT(*) as null_count FROM chapter_page WHERE chapter_id IS NULL";
$result = $koneksi->query($check_sql);
$null_count = $result->fetch_assoc()['null_count'];
echo "Remaining NULL chapter_id: " . $null_count . "<br>";

echo "=== FIX COMPLETE ===";
?>