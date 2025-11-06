<?php
session_start();
require_once 'koneksi.php';

// Cek jika user belum login, redirect ke login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Ambil data user saat ini
$user_data = getSingleRow("SELECT * FROM users WHERE user_id = ?", [$user_id], "i");

if (!$user_data) {
    die("User tidak ditemukan");
}

// Handle foto profil
$current_photo = 'default-avatar.jpg';
if (!empty($user_data['photo_profile'])) {
    $photo_path = 'uploads/profiles/' . $user_data['photo_profile'];
    if (file_exists($photo_path)) {
        $current_photo = $photo_path;
    }
}

// Proses update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $address = trim($_POST['address']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validasi required fields
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

    // Cek username sudah ada (kecuali untuk user ini)
    if (empty($errors)) {
        $check_username = "SELECT username FROM users WHERE username = ? AND user_id != ?";
        $stmt = $koneksi->prepare($check_username);
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['username'] = "Username sudah digunakan";
        }
        $stmt->close();
    }

    // Cek email sudah ada (kecuali untuk user ini)
    if (empty($errors)) {
        $check_email = "SELECT email FROM users WHERE email = ? AND user_id != ?";
        $stmt = $koneksi->prepare($check_email);
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['email'] = "Email sudah terdaftar";
        }
        $stmt->close();
    }

    // Cek telephone sudah ada (kecuali untuk user ini)
    if (!empty($telephone)) {
        $check_telephone = "SELECT telephone_number FROM users WHERE telephone_number = ? AND user_id != ?";
        $stmt = $koneksi->prepare($check_telephone);
        $stmt->bind_param("si", $telephone, $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['telephone'] = "Nomor telepon sudah terdaftar";
        }
        $stmt->close();
    }

    // Validasi password jika ingin mengubah password
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors['current_password'] = "Password saat ini harus diisi untuk mengubah password";
        } elseif ($current_password !== $user_data['password']) {
            $errors['current_password'] = "Password saat ini tidak sesuai";
        }

        if (empty($new_password)) {
            $errors['new_password'] = "Password baru harus diisi";
        } elseif (strlen($new_password) < 6) {
            $errors['new_password'] = "Password baru minimal 6 karakter";
        }

        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Konfirmasi password tidak sesuai";
        }
    }

    // Handle upload foto profil
    $photo_profile = $user_data['photo_profile']; // Tetap gunakan foto lama jika tidak diupdate
    
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
            $new_photo_profile = 'profile_' . uniqid() . '.' . $file_extension;
            $upload_path = 'uploads/profiles/' . $new_photo_profile;

            // Create directory if not exists
            if (!is_dir('uploads/profiles')) {
                mkdir('uploads/profiles', 0777, true);
            }

            // Hapus foto lama jika ada (kecuali default)
            if (!empty($user_data['photo_profile']) && $user_data['photo_profile'] !== 'default-avatar.jpg') {
                $old_photo_path = 'uploads/profiles/' . $user_data['photo_profile'];
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            }

            // Move uploaded file
            if (move_uploaded_file($_FILES['photo_profile']['tmp_name'], $upload_path)) {
                $photo_profile = $new_photo_profile;
            } else {
                $errors['photo_profile'] = "Gagal mengupload foto profil.";
            }
        }
    } elseif ($_FILES['photo_profile']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['photo_profile'] = "Terjadi error saat upload file.";
    }

    // Jika tidak ada error, update database
    if (empty($errors)) {
        // Tentukan field yang akan diupdate
        if (!empty($new_password)) {
            // Update dengan password baru
            $sql = "UPDATE users SET username = ?, email = ?, telephone_number = ?, address = ?, photo_profile = ?, password = ? WHERE user_id = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("ssssssi", $username, $email, $telephone, $address, $photo_profile, $new_password, $user_id);
        } else {
            // Update tanpa mengubah password
            $sql = "UPDATE users SET username = ?, email = ?, telephone_number = ?, address = ?, photo_profile = ? WHERE user_id = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("sssssi", $username, $email, $telephone, $address, $photo_profile, $user_id);
        }
        
        if ($stmt->execute()) {
            $success = "Profile berhasil diupdate!";
            
            // Update session username jika berubah
            if ($username !== $_SESSION['username']) {
                $_SESSION['username'] = $username;
            }
            
            // Refresh user data
            $user_data = getSingleRow("SELECT * FROM users WHERE user_id = ?", [$user_id], "i");
            
            // Update current photo
            if (!empty($user_data['photo_profile'])) {
                $photo_path = 'uploads/profiles/' . $user_data['photo_profile'];
                if (file_exists($photo_path)) {
                    $current_photo = $photo_path;
                }
            }
        } else {
            $errors['general'] = "Terjadi kesalahan saat update profile. Silakan coba lagi.";
        }
        
        $stmt->close();
    }
}

// Tentukan dashboard URL berdasarkan role
$dashboard_url = '';
$profile_url = 'profile.php';

switch ($user_data['role']) {
    case 'writer':
        $dashboard_url = 'dashboard_writer.php';
        break;
    case 'reader':
        $dashboard_url = 'dashboard_reader.php';
        break;
    case 'admin':
        $dashboard_url = 'dashboard_admin.php';
        break;
    default:
        $dashboard_url = 'dashboard.php';
        break;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - ShrineComics</title>
    <link rel="stylesheet" href="edit_profile.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="edit-profile-container">
        <!-- Header Navigation -->
        <div class="header-navigation">
            <a href="<?php echo $profile_url; ?>" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Profile
            </a>
            <a href="<?php echo $dashboard_url; ?>" class="dashboard-btn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2 4L8 1L14 4V11L8 14L2 11V4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M6 7.5H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 5.5V10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Dashboard
            </a>
        </div>

        <div class="edit-profile-card">
            <div class="edit-profile-header">
                <h1>Edit Profile</h1>
                <p>Update informasi profil Anda</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="edit_profile.php" class="edit-profile-form" enctype="multipart/form-data">
                <!-- Photo Profile Upload -->
                <div class="form-section">
                    <h3>Foto Profil</h3>
                    <div class="photo-upload-container">
                        <div class="current-photo">
                            <img src="<?php echo $current_photo; ?>" 
                                 alt="Current Profile Picture" 
                                 class="current-avatar"
                                 onerror="this.src='default-avatar.jpg'">
                        </div>
                        <div class="photo-upload-controls">
                            <input type="file" id="photo_profile" name="photo_profile" accept="image/*" class="file-input">
                            <label for="photo_profile" class="upload-btn">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 10V12.6667C14 13.0203 13.8595 13.3594 13.6095 13.6095C13.3594 13.8595 13.0203 14 12.6667 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M11.3333 5.33333L8 2L4.66667 5.33333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M8 2V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Ganti Foto
                            </label>
                            <span class="file-info">Maksimal 2MB (JPG, PNG, GIF)</span>
                            <?php if (isset($errors['photo_profile'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['photo_profile']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Informasi Dasar -->
                <div class="form-section">
                    <h3>Informasi Dasar</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo htmlspecialchars($user_data['username']); ?>"
                                   class="<?php echo isset($errors['username']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['username'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['username']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($user_data['email']); ?>"
                                   class="<?php echo isset($errors['email']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['email'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['email']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telephone">Nomor Telepon</label>
                            <input type="tel" id="telephone" name="telephone" 
                                   value="<?php echo htmlspecialchars($user_data['telephone_number'] ?: ''); ?>"
                                   class="<?php echo isset($errors['telephone']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['telephone'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['telephone']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <input type="text" id="role" value="<?php echo ucfirst($user_data['role']); ?>" disabled>
                            <small class="role-note">Role tidak dapat diubah</small>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="address">Alamat</label>
                        <textarea id="address" name="address" rows="3" 
                                  class="<?php echo isset($errors['address']) ? 'error' : ''; ?>"><?php echo htmlspecialchars($user_data['address'] ?: ''); ?></textarea>
                        <?php if (isset($errors['address'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['address']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Change Password (Optional) -->
                <div class="form-section">
                    <h3>Ubah Password</h3>
                    <p class="section-description">Kosongkan jika tidak ingin mengubah password</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password">Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="<?php echo isset($errors['current_password']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['current_password'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['current_password']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="<?php echo isset($errors['new_password']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['new_password'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['new_password']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="confirm_password">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="<?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                    <a href="<?php echo $profile_url; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script src="edit_profile.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>