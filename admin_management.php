<?php
session_start();
include 'koneksi.php';

// Cek login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Process add points
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_points'])) {
    $user_id = intval($_POST['user_id']);
    $points_to_add = intval($_POST['points']);
    
    // Validation
    if ($points_to_add > 0 && $points_to_add <= 100000) {
        // Get current points
        $current_sql = "SELECT username, point FROM users WHERE user_id = ?";
        $current_stmt = $koneksi->prepare($current_sql);
        $current_stmt->bind_param("i", $user_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        
        if ($current_result->num_rows === 1) {
            $user_data = $current_result->fetch_assoc();
            $new_points = $user_data['point'] + $points_to_add;
            
            // Update points
            $update_sql = "UPDATE users SET point = ? WHERE user_id = ?";
            $update_stmt = $koneksi->prepare($update_sql);
            $update_stmt->bind_param("ii", $new_points, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['success'] = "✅ Berhasil menambah $points_to_add point ke " . $user_data['username'];
            } else {
                $_SESSION['error'] = "❌ Gagal update database";
            }
            $update_stmt->close();
        } else {
            $_SESSION['error'] = "❌ User tidak ditemukan";
        }
        $current_stmt->close();
    } else {
        $_SESSION['error'] = "❌ Point harus antara 1-100000";
    }
    
    header("Location: admin_management.php");
    exit();
}

// Process update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = intval($_POST['user_id']);
    $new_status = $_POST['status'];
    
    $update_sql = "UPDATE users SET status = ? WHERE user_id = ?";
    $update_stmt = $koneksi->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "✅ Status berhasil diupdate";
    } else {
        $_SESSION['error'] = "❌ Gagal update status";
    }
    $update_stmt->close();
    
    header("Location: admin_management.php");
    exit();
}

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$sql .= " ORDER BY user_id DESC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

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
    <title>Manajemen User - ShrineComics</title>
    <link rel="stylesheet" href="admin_management_style.css">
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
                <a href="admin_management.php" class="nav-link active">Manajemen User</a>
                <a href="admin_point_requests.php" class="nav-link">Permintaan Point</a>
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
                <h1>Manajemen User</h1>
                <p>Kelola data user, status, dan sistem point</p>
            </div>

            <!-- Notifications -->
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Cari User:</label>
                        <input type="text" name="search" placeholder="Username atau email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Role:</label>
                        <select name="role">
                            <option value="">Semua Role</option>
                            <option value="reader" <?php echo $role_filter === 'reader' ? 'selected' : ''; ?>>Reader</option>
                            <option value="writer" <?php echo $role_filter === 'writer' ? 'selected' : ''; ?>>Writer</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="">Semua Status</option>
                            <option value="LOGIN" <?php echo $status_filter === 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                            <option value="LOGOUT" <?php echo $status_filter === 'LOGOUT' ? 'selected' : ''; ?>>Logout</option>
                            <option value="SUSPEND" <?php echo $status_filter === 'SUSPEND' ? 'selected' : ''; ?>>Suspend</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">🔍 Terapkan Filter</button>
                        <a href="admin_management.php" class="btn-reset">🔄 Reset</a>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-section">
                <div class="table-header">
                    <h3>📊 Daftar User (<?php echo $users->num_rows; ?> user ditemukan)</h3>
                </div>

                <div class="table-container">
                    <?php if ($users->num_rows > 0): ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Points</th>
                                    <th>Bergabung</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td class="user-id">#<?php echo $user['user_id']; ?></td>
                                    
                                    <td class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'LOGIN' => '🟢 Login',
                                                'LOGOUT' => '⚪ Logout', 
                                                'SUSPEND' => '🔴 Suspend'
                                            ];
                                            echo $status_text[$user['status']] ?? $user['status'];
                                            ?>
                                        </span>
                                    </td>
                                    
                                    <td class="points-cell">
                                        <div class="points-display">
                                            <strong><?php echo number_format($user['point']); ?></strong>
                                            <small>points</small>
                                        </div>
                                    </td>
                                    
                                    <td class="join-date">
                                        <?php echo date('d M Y', strtotime($user['join_date'])); ?>
                                    </td>
                                    
                                    <td class="actions-cell">
                                        <!-- Add Points Button -->
                                        <button class="btn-add-points" 
                                                onclick="openAddPointsModal(
                                                    <?php echo $user['user_id']; ?>,
                                                    '<?php echo htmlspecialchars($user['username']); ?>',
                                                    <?php echo $user['point']; ?>
                                                )">
                                            💰 + Point
                                        </button>
                                        
                                        <!-- Status Dropdown -->
                                        <div class="dropdown">
                                            <button class="btn-status">
                                                📊 Status ▼
                                            </button>
                                            <div class="dropdown-menu">
                                                <form method="POST" class="status-form">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <button type="submit" name="update_status" value="LOGIN" 
                                                            class="status-option <?php echo $user['status'] === 'LOGIN' ? 'active' : ''; ?>">
                                                        🟢 Login
                                                    </button>
                                                    <button type="submit" name="update_status" value="LOGOUT"
                                                            class="status-option <?php echo $user['status'] === 'LOGOUT' ? 'active' : ''; ?>">
                                                        ⚪ Logout
                                                    </button>
                                                    <button type="submit" name="update_status" value="SUSPEND"
                                                            class="status-option <?php echo $user['status'] === 'SUSPEND' ? 'active' : ''; ?>">
                                                        🔴 Suspend
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <div class="no-data-icon">📭</div>
                            <h3>Tidak ada user ditemukan</h3>
                            <p>Coba ubah filter pencarian atau reset filter</p>
                            <a href="admin_management.php" class="btn-reset">🔄 Reset Filter</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Points Modal -->
    <div id="pointsModal" class="modal">
        <div class="modal-overlay" onclick="closeAddPointsModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>💰 Tambah Point</h3>
                <button class="modal-close" onclick="closeAddPointsModal()">×</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="user_id" id="modalUserId">
                    
                    <div class="form-group">
                        <label>👤 Username:</label>
                        <input type="text" id="modalUsername" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>⭐ Current Points:</label>
                        <input type="text" id="modalCurrentPoints" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>🎯 Points to Add:</label>
                        <input type="number" name="points" class="form-input" min="1" max="100000" required 
                               placeholder="Masukkan jumlah point">
                        <div class="form-helper">Min: 1 point, Max: 100,000 point</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="closeAddPointsModal()">❌ Batal</button>
                        <button type="submit" name="add_points" class="btn-confirm">✅ Tambah Point</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="script_admin_management.js"></script>
</body>
</html>