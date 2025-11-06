<?php
session_start();
include 'koneksi.php';

// Cek jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'writer') {
        header("Location: dashboard_writer.php");
    } else if ($_SESSION['role'] === 'reader') {
        header("Location: dashboard_reader.php");
    }
    exit();
}

$errors = [];
$success = '';

// Proses registrasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $telephone = trim($_POST['telephone']);
    $address = trim($_POST['address']);

    // Validasi
    if (empty($username)) {
        $errors['username'] = "Username harus diisi";
    } elseif (strlen($username) < 3) {
        $errors['username'] = "Username minimal 3 karakter";
    }

    if (empty($email)) {
        $errors['email'] = "Email harus diisi";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Format email tidak valid";
    }

    if (empty($password)) {
        $errors['password'] = "Password harus diisi";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password minimal 6 karakter";
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Konfirmasi password tidak sesuai";
    }

    if (empty($role)) {
        $errors['role'] = "Pilih role";
    }

    // Validasi file upload
    $photo_profile = null;
    if (isset($_FILES['photo_profile']) && $_FILES['photo_profile']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_type = $_FILES['photo_profile']['type'];
        $file_size = $_FILES['photo_profile']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $errors['photo_profile'] = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
        } elseif ($file_size > $max_size) {
            $errors['photo_profile'] = "Ukuran file maksimal 2MB.";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($_FILES['photo_profile']['name'], PATHINFO_EXTENSION);
            $photo_profile = 'profile_' . uniqid() . '.' . $file_extension;
            $upload_path = 'uploads/profiles/' . $photo_profile;

            // Create directory if not exists
            if (!is_dir('uploads/profiles')) {
                mkdir('uploads/profiles', 0777, true);
            }

            // Move uploaded file
            if (!move_uploaded_file($_FILES['photo_profile']['tmp_name'], $upload_path)) {
                $errors['photo_profile'] = "Gagal mengupload foto profil.";
            }
        }
    } elseif ($_FILES['photo_profile']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['photo_profile'] = "Terjadi error saat upload file.";
    }

    // Cek username sudah ada
    if (empty($errors)) {
        $check_username = "SELECT username FROM users WHERE username = ?";
        $stmt = $koneksi->prepare($check_username);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['username'] = "Username sudah digunakan";
        }
        $stmt->close();
    }

    // Cek email sudah ada
    if (empty($errors)) {
        $check_email = "SELECT email FROM users WHERE email = ?";
        $stmt = $koneksi->prepare($check_email);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['email'] = "Email sudah terdaftar";
        }
        $stmt->close();
    }

    // Cek telephone sudah ada (jika diisi)
    if (!empty($telephone)) {
        $check_telephone = "SELECT telephone_number FROM users WHERE telephone_number = ?";
        $stmt = $koneksi->prepare($check_telephone);
        $stmt->bind_param("s", $telephone);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['telephone'] = "Nomor telepon sudah terdaftar";
        }
        $stmt->close();
    }

    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        $join_date = date('Y-m-d');
        $status = 'LOGOUT';
        $point = 0; // Default point untuk user baru

        $sql = "INSERT INTO users (username, password, email, address, telephone_number, role, status, join_date, point, photo_profile) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("ssssssssis", $username, $password, $email, $address, $telephone, $role, $status, $join_date, $point, $photo_profile);
        
        if ($stmt->execute()) {
            $success = "Registrasi berhasil! Silakan login.";
            // Reset form
            $_POST = array();
        } else {
            $errors['general'] = "Terjadi kesalahan saat registrasi. Silakan coba lagi.";
            // Hapus file yang sudah diupload jika gagal insert
            if ($photo_profile && file_exists('uploads/profiles/' . $photo_profile)) {
                unlink('uploads/profiles/' . $photo_profile);
            }
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ShrineComics</title>
    <link rel="stylesheet" href="style_register.css">
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1>ShrineComics</h1>
                <p>Buat Akun Baru</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                    <br><a href="login.php" class="login-link">Login di sini</a>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" class="register-form" enctype="multipart/form-data">
                <!-- Photo Profile Upload -->
                <div class="form-group full-width">
                    <label for="photo_profile">Foto Profil (Opsional)</label>
                    <div class="photo-upload-container">
                        <div class="photo-preview" id="photoPreview">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12C13.6569 12 15 10.6569 15 9C15 7.34315 13.6569 6 12 6C10.3431 6 9 7.34315 9 9C9 10.6569 10.3431 12 12 12Z" stroke="#666" stroke-width="2"/>
                                <path d="M20 17.589C20 17.589 18.309 15 12 15C5.691 15 4 17.589 4 17.589V19C4 20.1046 4.89543 21 6 21H18C19.1046 21 20 20.1046 20 19V17.589Z" stroke="#666" stroke-width="2"/>
                            </svg>
                            <span>Preview akan muncul di sini</span>
                        </div>
                        <div class="upload-controls">
                            <input type="file" id="photo_profile" name="photo_profile" accept="image/*" class="file-input">
                            <label for="photo_profile" class="upload-btn">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 10V12.6667C14 13.0203 13.8595 13.3594 13.6095 13.6095C13.3594 13.8595 13.0203 14 12.6667 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M11.3333 5.33333L8 2L4.66667 5.33333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M8 2V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Pilih Foto
                            </label>
                            <span class="file-info">Maksimal 2MB (JPG, PNG, GIF)</span>
                        </div>
                    </div>
                    <?php if (isset($errors['photo_profile'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['photo_profile']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               class="<?php echo isset($errors['username']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['username'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['username']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               class="<?php echo isset($errors['email']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required 
                               class="<?php echo isset($errors['password']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['password'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['password']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               class="<?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="telephone">Nomor Telepon</label>
                        <input type="tel" id="telephone" name="telephone" 
                               value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>"
                               class="<?php echo isset($errors['telephone']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['telephone'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['telephone']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required class="<?php echo isset($errors['role']) ? 'error' : ''; ?>">
                            <option value="">Pilih Role</option>
                            <option value="reader" <?php echo (isset($_POST['role']) && $_POST['role'] === 'reader') ? 'selected' : ''; ?>>Reader</option>
                            <option value="writer" <?php echo (isset($_POST['role']) && $_POST['role'] === 'writer') ? 'selected' : ''; ?>>Writer</option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['role']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="address">Alamat</label>
                    <textarea id="address" name="address" rows="3" 
                              class="<?php echo isset($errors['address']) ? 'error' : ''; ?>"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    <?php if (isset($errors['address'])): ?>
                        <span class="error-text"><?php echo htmlspecialchars($errors['address']); ?></span>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="register-btn">Daftar</button>
            </form>
            
            <div class="register-footer">
                <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
            </div>
        </div>
    </div>

    <script src="script_register.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>