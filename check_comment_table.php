<?php
require_once 'koneksi.php';

echo "<h3>Struktur Tabel Comment:</h3>";
$result = $koneksi->query("DESCRIBE comment");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// Cek data sample
echo "<h3>Data Sample di Tabel Comment:</h3>";
$sample = $koneksi->query("SELECT * FROM comment LIMIT 3");
if ($sample->num_rows > 0) {
    echo "<table border='1'>";
    $first = true;
    while ($row = $sample->fetch_assoc()) {
        if ($first) {
            echo "<tr>";
            foreach (array_keys($row) as $key) {
                echo "<th>{$key}</th>";
            }
            echo "</tr>";
            $first = false;
        }
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Tidak ada data sample";
}
?>