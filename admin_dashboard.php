<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Ambil data statistik untuk dashboard
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_writers_query = "SELECT COUNT(*) as total FROM users WHERE role = 'writer'";
$total_readers_query = "SELECT COUNT(*) as total FROM users WHERE role = 'reader'";
$total_comics_query = "SELECT COUNT(*) as total FROM comic";
$pending_requests_query = "SELECT COUNT(*) as total FROM point_requests WHERE status = 'pending'";
$recent_users_query = "SELECT username, role, join_date FROM users ORDER BY join_date DESC LIMIT 5";
$recent_requests_query = "
    SELECT pr.*, u.username 
    FROM point_requests pr 
    JOIN users u ON pr.user_id = u.user_id 
    ORDER BY pr.request_date DESC 
    LIMIT 5
";

$total_users = $koneksi->query($total_users_query)->fetch_assoc()['total'];
$total_writers = $koneksi->query($total_writers_query)->fetch_assoc()['total'];
$total_readers = $koneksi->query($total_readers_query)->fetch_assoc()['total'];
$total_comics = $koneksi->query($total_comics_query)->fetch_assoc()['total'];
$pending_requests = $koneksi->query($pending_requests_query)->fetch_assoc()['total'];
$recent_users = $koneksi->query($recent_users_query);
$recent_requests = $koneksi->query($recent_requests_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ShrineComics</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="admin-navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <h2>ShrineComics Admin</h2>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php" class="nav-link active">Dashboard</a>
                <a href="admin_management.php" class="nav-link">Manajemen User</a>
                <a href="admin_point_requests.php" class="nav-link">Permintaan Point</a>
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div> 
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="admin-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>Panel Administrasi ShrineComics - Kelola sistem komik Anda</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-trend">+<?php echo $recent_users->num_rows; ?> baru</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✍️</div>
                <div class="stat-info">
                    <h3><?php echo $total_writers; ?></h3>
                    <p>Writer</p>
                </div>
                <div class="stat-trend">Penulis Aktif</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📖</div>
                <div class="stat-info">
                    <h3><?php echo $total_readers; ?></h3>
                    <p>Reader</p>
                </div>
                <div class="stat-trend">Pembaca</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <h3><?php echo $total_comics; ?></h3>
                    <p>Total Komik</p>
                </div>
                <div class="stat-trend">Komik Tersedia</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3><?php echo $pending_requests; ?></h3>
                    <p>Pending Requests</p>
                </div>
                <div class="stat-trend" style="background: #fff3cd; color: #856404;">
                    Menunggu Approval
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Actions -->
        <div class="content-grid">
            <!-- Recent Users -->
            <div class="content-card">
                <div class="card-header">
                    <h3>User Baru Bergabung</h3>
                    <a href="admin_management.php" class="view-all">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if ($recent_users->num_rows > 0): ?>
                        <div class="user-list">
                            <?php while($user = $recent_users->fetch_assoc()): ?>
                                <div class="user-item">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <span class="user-role <?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>
                                    <div class="user-date">
                                        <?php echo date('d M Y', strtotime($user['join_date'])); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">Tidak ada user baru</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Point Requests -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Permintaan Point Terbaru</h3>
                    <a href="admin_point_requests.php" class="view-all">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if ($recent_requests->num_rows > 0): ?>
                        <div class="user-list">
                            <?php while($request = $recent_requests->fetch_assoc()): ?>
                                <div class="user-item">
                                    <div class="user-avatar" style="background: <?php echo $request['status'] == 'pending' ? '#ffc107' : '#28a745'; ?>;">
                                        💰
                                    </div>
                                    <div class="user-details">
                                        <strong><?php echo htmlspecialchars($request['username']); ?></strong>
                                        <span style="color: #007bff; font-weight: 500;">
                                            +<?php echo number_format($request['point_amount']); ?> points
                                        </span>
                                    </div>
                                    <div class="user-date">
                                        <span style="font-size: 0.7rem; padding: 2px 6px; background: <?php 
                                            echo $request['status'] == 'pending' ? '#fff3cd' : 
                                                 ($request['status'] == 'approved' ? '#d4edda' : '#f8d7da'); 
                                        ?>; color: <?php 
                                            echo $request['status'] == 'pending' ? '#856404' : 
                                                 ($request['status'] == 'approved' ? '#155724' : '#721c24'); 
                                        ?>; border-radius: 8px;">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">Tidak ada permintaan point</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="action-grid">
                    <a href="admin_management.php" class="action-card">
                        <div class="action-icon">👥</div>
                        <div class="action-text">
                            <strong>Manajemen User</strong>
                            <p>Kelola semua user</p>
                        </div>
                    </a>
                    
                    <a href="admin_management.php?action=add_point" class="action-card">
                        <div class="action-icon">⭐</div>
                        <div class="action-text">
                            <strong>Tambah Point</strong>
                            <p>Berikan point ke user</p>
                        </div>
                    </a>
                    
                    <a href="admin_point_requests.php" class="action-card">
                        <div class="action-icon">💰</div>
                        <div class="action-text">
                            <strong>Permintaan Point</strong>
                            <p>Lihat request top-up</p>
                        </div>
                    </a>
                    
                    <a href="admin_point_requests.php?filter=pending" class="action-card">
                        <div class="action-icon">✅</div>
                        <div class="action-text">
                            <strong>Approve Point</strong>
                            <p>Proses request</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="content-card">
            <div class="card-header">
                <h3>System Status</h3>
            </div>
            <div class="card-body">
                <div class="status-grid">
                    <div class="status-item online">
                        <div class="status-indicator"></div>
                        <div class="status-info">
                            <strong>Database</strong>
                            <p>Connected</p>
                        </div>
                    </div>
                    <div class="status-item online">
                        <div class="status-indicator"></div>
                        <div class="status-info">
                            <strong>Server</strong>
                            <p>Running</p>
                        </div>
                    </div>
                    <div class="status-item online">
                        <div class="status-indicator"></div>
                        <div class="status-info">
                            <strong>Point System</strong>
                            <p>Operational</p>
                        </div>
                    </div>
                    <div class="status-item <?php echo $pending_requests > 0 ? 'online' : 'online'; ?>">
                        <div class="status-indicator" style="background: <?php echo $pending_requests > 0 ? '#ffc107' : '#28a745'; ?>"></div>
                        <div class="status-info">
                            <strong>Pending Requests</strong>
                            <p><?php echo $pending_requests; ?> menunggu</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script_admin.js"></script>
</body>
</html>