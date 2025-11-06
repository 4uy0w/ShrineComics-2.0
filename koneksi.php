<?php
$host = "localhost";
$username = "Hikari";
$password = "1234";
$database = "ShrineComics";

// Membuat koneksi
$koneksi = new mysqli($host, $username, $password, $database);

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Set charset ke utf8
$koneksi->set_charset("utf8");

// ✅ TAMBAHKAN FUNGSI executeQuery DI SINI
function executeQuery($sql, $params = [], $types = "") {
    global $koneksi;
    
    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return ["success" => false, "error" => $koneksi->error];
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            // Auto-detect parameter types
            $types = "";
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= "i";
                } elseif (is_double($param)) {
                    $types .= "d";
                } else {
                    $types .= "s";
                }
            }
        }
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return ["success" => true, "data" => $data];
        } else {
            // Untuk INSERT, UPDATE, DELETE
            $stmt->close();
            return ["success" => true, "affected_rows" => $stmt->affected_rows];
        }
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ["success" => false, "error" => $error];
    }
}

// ✅ FUNGSI BARU: getSingleRow untuk mengambil satu row
function getSingleRow($sql, $params = [], $types = "") {
    $result = executeQuery($sql, $params, $types);
    if ($result["success"] && !empty($result["data"])) {
        return $result["data"][0];
    }
    return null;
}
?>