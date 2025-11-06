<?php
session_start();
include 'koneksi.php';

// Cek jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'writer') {
        header("Location: dashboard_writer.php");
    } else if ($_SESSION['role'] === 'reader') {
        header("Location: dashboard_reader.php");
    } else if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php"); // NEW: Redirect admin
    }
    exit();
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    // Query untuk mencari user (reader, writer, atau admin) - UPDATED
    $sql = "SELECT user_id, username, password, role, status FROM users WHERE username = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verifikasi password
        if ($input_password === $user['password']) {
            
            // Cek status user
            if ($user['status'] === 'SUSPEND') {
                $error = "Akun Anda ditangguhkan. Silakan hubungi administrator.";
            } else {
                // Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = $user['status'];
                
                // Update status menjadi LOGIN
                $update_sql = "UPDATE users SET status = 'LOGIN' WHERE user_id = ?";
                $update_stmt = $koneksi->prepare($update_sql);
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                
                // Redirect berdasarkan role - UPDATED dengan admin
                if ($user['role'] === 'writer') {
                    header("Location: dashboard_writer.php");
                } else if ($user['role'] === 'reader') {
                    header("Location: dashboard_reader.php");
                } else if ($user['role'] === 'admin') { // NEW: Admin redirect
                    header("Location: admin_dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Username atau password salah.";
        }
    } else {
        $error = "Username tidak ditemukan.";
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ShrineComics</title>
    <link rel="stylesheet" href="style_login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>ShrineComics</h1>
                <p>Login to Your Account</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="login-footer">
                <p>Login sebagai <strong>Writer</strong>, <strong>Reader</strong>, atau <strong>Admin</strong></p> <!-- UPDATED -->
                <p class="register-link">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
            </div>
        </div>
    </div>
</body>
</html>