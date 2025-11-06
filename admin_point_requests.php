<?php
session_start();
include 'koneksi.php';

// Cek login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Process approve/reject request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action']; // 'approve' or 'reject'
    
    if ($action === 'approve') {
        // Get request data
        $request_sql = "SELECT pr.*, u.username, u.point as current_points 
                       FROM point_requests pr 
                       JOIN users u ON pr.user_id = u.user_id 
                       WHERE pr.request_id = ?";
        $request_stmt = $koneksi->prepare($request_sql);
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_data = $request_stmt->get_result()->fetch_assoc();
        $request_stmt->close();
        
        if ($request_data) {
            // Calculate new points
            $new_points = $request_data['current_points'] + $request_data['point_amount'];
            
            // Update user points
            $update_sql = "UPDATE users SET point = ? WHERE user_id = ?";
            $update_stmt = $koneksi->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_points, $request_data['user_id']);
            
            if ($update_stmt->execute()) {
                // Update request status
                $status_sql = "UPDATE point_requests SET status = 'approved', 
                              processed_date = NOW(), processed_by = ? 
                              WHERE request_id = ?";
                $status_stmt = $koneksi->prepare($status_sql);
                $status_stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
                $status_stmt->execute();
                $status_stmt->close();
                
                $_SESSION['success'] = "✅ Request approved! +" . number_format($request_data['point_amount']) . " points ditambahkan ke " . $request_data['username'];
            } else {
                $_SESSION['error'] = "❌ Gagal update points user";
            }
            $update_stmt->close();
        } else {
            $_SESSION['error'] = "❌ Data request tidak ditemukan";
        }
        
    } elseif ($action === 'reject') {
        // Update request status to rejected
        $reject_sql = "UPDATE point_requests SET status = 'rejected', 
                       processed_date = NOW(), processed_by = ? 
                       WHERE request_id = ?";
        $reject_stmt = $koneksi->prepare($reject_sql);
        $reject_stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
        
        if ($reject_stmt->execute()) {
            $_SESSION['success'] = "❌ Request ditolak";
        } else {
            $_SESSION['error'] = "❌ Gagal menolak request";
        }
        $reject_stmt->close();
    }
    
    header("Location: admin_point_requests.php");
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT pr.*, u.username, u.email, u.telephone_number,
               admin.username as processed_by_name
        FROM point_requests pr 
        JOIN users u ON pr.user_id = u.user_id 
        LEFT JOIN users admin ON pr.processed_by = admin.user_id 
        WHERE 1=1";
$params = [];
$types = '';

if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " AND pr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR pr.telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

$sql .= " ORDER BY pr.request_date DESC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'approved' THEN point_amount ELSE 0 END) as total_approved_points
    FROM point_requests";
$stats_result = $koneksi->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permintaan Point - ShrineComics</title>
    <link rel="stylesheet" href="admin_point_requests_style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="admin-navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <h2>ShrineComics Admin</h2>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
                <a href="admin_management.php" class="nav-link">Manajemen User</a>
                <a href="admin_point_requests.php" class="nav-link active">Permintaan Point</a>
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="container">
            <!-- Header -->
            <div class="page-header">
                <h1>Permintaan Top-Up Point</h1>
                <p>Kelola dan proses request top-up point dari readers</p>
            </div>

            <!-- Notifications -->
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📨</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Requests</div>
                    </div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Menunggu</div>
                    </div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['approved']; ?></div>
                        <div class="stat-label">Disetujui</div>
                    </div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-icon">❌</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                        <div class="stat-label">Ditolak</div>
                    </div>
                </div>
                <div class="stat-card points">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_approved_points']); ?></div>
                        <div class="stat-label">Total Points</div>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="action-bar">
                <div class="search-box">
                    <form method="GET" class="search-form">
                        <div class="search-input-group">
                            <input type="text" name="search" placeholder="Cari user, email, atau telepon..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn">🔍</button>
                        </div>
                    </form>
                </div>
                <div class="filter-controls">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>⏳ Menunggu</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✅ Disetujui</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>❌ Ditolak</option>
                        </select>
                        <a href="admin_point_requests.php" class="btn-reset">🔄 Reset</a>
                    </form>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <h3>Daftar Permintaan Point</h3>
                        <span class="table-count"><?php echo $requests->num_rows; ?> request ditemukan</span>
                    </div>
                    <div class="table-actions">
                        <button class="btn-export" onclick="exportRequests()">📊 Export</button>
                        <button class="btn-refresh" onclick="refreshTable()">🔄 Refresh</button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <?php if ($requests->num_rows > 0): ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th class="col-user">User</th>
                                    <th class="col-amount">Jumlah Point</th>
                                    <th class="col-method">Metode</th>
                                    <th class="col-contact">Kontak</th>
                                    <th class="col-date">Tanggal</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-actions">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($request = $requests->fetch_assoc()): ?>
                                <tr class="request-row status-<?php echo $request['status']; ?>">
                                    <td class="col-user">
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($request['username'], 0, 1)); ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name"><?php echo htmlspecialchars($request['username']); ?></div>
                                                <div class="user-email"><?php echo htmlspecialchars($request['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="col-amount">
                                        <div class="amount-display">
                                            <span class="amount-value">+<?php echo number_format($request['point_amount']); ?></span>
                                            <span class="amount-label">points</span>
                                        </div>
                                    </td>
                                    
                                    <td class="col-method">
                                        <span class="payment-method <?php echo $request['payment_method']; ?>">
                                            <?php 
                                            $payment_methods = [
                                                'transfer_bank' => '🏦 Transfer',
                                                'e_wallet' => '📱 E-Wallet',
                                                'virtual_account' => '💳 Virtual Account',
                                                'qris' => '📲 QRIS'
                                            ];
                                            echo $payment_methods[$request['payment_method']] ?? ucfirst($request['payment_method']);
                                            ?>
                                        </span>
                                    </td>
                                    
                                    <td class="col-contact">
                                        <div class="contact-info">
                                            <div class="phone-number"><?php echo htmlspecialchars($request['telephone']); ?></div>
                                            <?php if (!empty($request['additional_notes'])): ?>
                                                <div class="contact-notes" title="<?php echo htmlspecialchars($request['additional_notes']); ?>">
                                                    📝 Catatan
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="col-date">
                                        <div class="date-display">
                                            <div class="date-main"><?php echo date('d M Y', strtotime($request['request_date'])); ?></div>
                                            <div class="date-time"><?php echo date('H:i', strtotime($request['request_date'])); ?></div>
                                        </div>
                                    </td>
                                    
                                    <td class="col-status">
                                        <div class="status-display">
                                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                                <?php 
                                                $status_text = [
                                                    'pending' => '⏳ Menunggu',
                                                    'approved' => '✅ Disetujui', 
                                                    'rejected' => '❌ Ditolak'
                                                ];
                                                echo $status_text[$request['status']];
                                                ?>
                                            </span>
                                            <?php if ($request['processed_date']): ?>
                                                <div class="processor-info">
                                                    <small>by <?php echo $request['processed_by_name'] ?? 'Admin'; ?></small>
                                                    <small><?php echo date('d/m H:i', strtotime($request['processed_date'])); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="col-actions">
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <div class="action-buttons">
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-action btn-approve" 
                                                            onclick="return confirm('Approve <?php echo number_format($request['point_amount']); ?> points untuk <?php echo htmlspecialchars($request['username']); ?>?')">
                                                        <span class="btn-icon">✅</span>
                                                        <span class="btn-text">Approve</span>
                                                    </button>
                                                </form>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn-action btn-reject"
                                                            onclick="return confirm('Tolak request dari <?php echo htmlspecialchars($request['username']); ?>?')">
                                                        <span class="btn-icon">❌</span>
                                                        <span class="btn-text">Reject</span>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="action-completed">
                                                <span class="completed-text">
                                                    <?php echo $request['status'] === 'approved' ? 'Telah Disetujui' : 'Telah Ditolak'; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Notes Row -->
                                <?php if (!empty($request['additional_notes'])): ?>
                                <tr class="notes-row">
                                    <td colspan="7">
                                        <div class="request-notes">
                                            <strong>📝 Catatan:</strong>
                                            <?php echo htmlspecialchars($request['additional_notes']); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <h3>Tidak ada permintaan point</h3>
                            <p>Tidak ditemukan request point dengan filter yang dipilih</p>
                            <a href="admin_point_requests.php" class="btn-reset">🔄 Tampilkan Semua</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Table Footer -->
                <div class="table-footer">
                    <div class="table-summary">
                        Menampilkan <?php echo $requests->num_rows; ?> dari <?php echo $stats['total']; ?> total request
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="admin_point_requests_script.js"></script>
</body>
</html>