<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Set header untuk file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="transaksi_' . date('Y-m-d') . '.csv"');

// Output langsung ke browser
$output = fopen('php://output', 'w');

// Add BOM untuk UTF-8 compatibility
fputs($output, "\xEF\xBB\xBF");

// Header CSV
$headers = [
    'ID Transaksi',
    'Judul Komik', 
    'Chapter',
    'Nama Chapter',
    'Pembeli',
    'Jumlah Poin',
    'Status',
    'Tanggal Transaksi',
    'Harga Chapter',
    'Pendapatan Bersih'
];

fputcsv($output, $headers);

// Filter parameters (sama seperti di transactions_history.php)
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_comic = $_GET['comic'] ?? '';

// Build query dengan filters
$transactions_sql = "SELECT 
                        t.transaction_id,
                        c.comic_title,
                        ch.chapter_number,
                        ch.chapter_name,
                        ch.chapter_price,
                        u.username as buyer_name,
                        t.transaction_point,
                        t.transaction_status,
                        t.transaction_date
                    FROM transactions t
                    JOIN comic c ON t.transaction_comic = c.comic_id
                    JOIN chapter ch ON t.transaction_chapter = ch.chapter_id
                    JOIN users u ON t.transaction_reader = u.user_id
                    WHERE t.transaction_writer = ?";

$params = [$user_id];
$param_types = "i";

// Add status filter
if (!empty($filter_status) && $filter_status !== 'all') {
    $transactions_sql .= " AND t.transaction_status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

// Add date range filter
if (!empty($filter_date_from)) {
    $transactions_sql .= " AND t.transaction_date >= ?";
    $params[] = $filter_date_from;
    $param_types .= "s";
}

if (!empty($filter_date_to)) {
    $transactions_sql .= " AND t.transaction_date <= ?";
    $params[] = $filter_date_to . ' 23:59:59'; // Sampai akhir hari
    $param_types .= "s";
}

// Add comic filter
if (!empty($filter_comic)) {
    $transactions_sql .= " AND c.comic_title LIKE ?";
    $params[] = "%$filter_comic%";
    $param_types .= "s";
}

$transactions_sql .= " ORDER BY t.transaction_date DESC";

// Prepare and execute query
$stmt = $koneksi->prepare($transactions_sql);

if ($params) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Counter untuk statistik
$total_transactions = 0;
$total_earnings = 0;
$total_points = 0;

// Data rows
while ($transaction = $result->fetch_assoc()) {
    // Format status
    $status_text = [
        'success' => 'Berhasil',
        'pending' => 'Pending',
        'failed' => 'Gagal'
    ];
    
    $status = $status_text[$transaction['transaction_status']] ?? $transaction['transaction_status'];
    
    // Format tanggal
    $formatted_date = date('d/m/Y H:i', strtotime($transaction['transaction_date']));
    
    // Pendapatan bersih (untuk transaksi sukses)
    $net_earnings = $transaction['transaction_status'] === 'success' ? $transaction['transaction_point'] : 0;
    
    $row = [
        $transaction['transaction_id'],
        $transaction['comic_title'],
        'Chapter ' . $transaction['chapter_number'],
        $transaction['chapter_name'],
        $transaction['buyer_name'],
        $transaction['transaction_point'],
        $status,
        $formatted_date,
        $transaction['chapter_price'],
        $net_earnings
    ];
    
    fputcsv($output, $row);
    
    // Update counters
    $total_transactions++;
    if ($transaction['transaction_status'] === 'success') {
        $total_earnings += $transaction['transaction_point'];
    }
    $total_points += $transaction['transaction_point'];
}

// Add summary rows
fputcsv($output, []); // Empty row
fputcsv($output, ['SUMMARY', '', '', '', '', '', '', '', '', '']);
fputcsv($output, ['Total Transaksi:', $total_transactions, '', '', '', '', '', '', '', '']);
fputcsv($output, ['Total Pendapatan Bersih:', $total_earnings . ' poin', '', '', '', '', '', '', '', '']);
fputcsv($output, ['Total Poin Tertransaksi:', $total_points . ' poin', '', '', '', '', '', '', '', '']);
fputcsv($output, ['Rata-rata Pendapatan per Transaksi:', $total_transactions > 0 ? round($total_earnings / $total_transactions, 2) . ' poin' : '0 poin', '', '', '', '', '', '', '', '']);

// Add filter information
fputcsv($output, []); // Empty row
fputcsv($output, ['FILTER YANG DIGUNAKAN', '', '', '', '', '', '', '', '', '']);
fputcsv($output, ['Tanggal Export:', date('d/m/Y H:i'), '', '', '', '', '', '', '', '']);
fputcsv($output, ['User:', $username, '', '', '', '', '', '', '', '']);

if (!empty($filter_status) && $filter_status !== 'all') {
    fputcsv($output, ['Filter Status:', $filter_status, '', '', '', '', '', '', '', '']);
}

if (!empty($filter_date_from)) {
    fputcsv($output, ['Filter Tanggal Mulai:', $filter_date_from, '', '', '', '', '', '', '', '']);
}

if (!empty($filter_date_to)) {
    fputcsv($output, ['Filter Tanggal Akhir:', $filter_date_to, '', '', '', '', '', '', '', '']);
}

if (!empty($filter_comic)) {
    fputcsv($output, ['Filter Komik:', $filter_comic, '', '', '', '', '', '', '', '']);
}

fclose($output);
$stmt->close();
$koneksi->close();
exit();
?>