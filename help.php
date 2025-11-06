<?php
session_start();
include 'koneksi.php';

// Cek apakah user sudah login dan role writer
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'writer') {
    header("Location: login.php");
    exit();
}

// Cek status user
if ($_SESSION['status'] === 'SUSPEND') {
    session_destroy();
    header("Location: login.php?error=suspended");
    exit();
}

$username = $_SESSION['username'];
$email = $_SESSION['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bantuan - ShrineComics</title>
    <link rel="stylesheet" href="style_writer.css">
    <link rel="stylesheet" href="help.css">
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="top-navigation">
        <div class="nav-container">
            <div class="nav-brand">
                <div class="logo">
                    <span class="logo-icon">📚</span>
                    <span class="logo-text">ShrineComics</span>
                </div>
                <span class="role-badge">Writer</span>
            </div>
            
            <div class="nav-user">
                <div class="user-menu">
                    <button class="user-toggle" id="userToggle">
                        <div class="user-avatar">
                            <span class="avatar-icon">👤</span>
                        </div>
                        <span class="username"><?php echo htmlspecialchars($username); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <strong><?php echo htmlspecialchars($username); ?></strong>
                            <span class="user-email"><?php echo htmlspecialchars($email); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <span class="item-icon">⚙️</span>
                            Pengaturan Profile
                        </a>
                        <a href="change_password.php" class="dropdown-item">
                            <span class="item-icon">🔒</span>
                            Ganti Password
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="dashboard_writer.php" class="dropdown-item">
                            <span class="item-icon">📊</span>
                            Dashboard
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout-item" onclick="return confirm('Yakin ingin logout?')">
                            <span class="item-icon">🚪</span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Help Header -->
    <div class="help-header">
        <div class="header-content">
            <div class="header-title">
                <h1>Pusat Bantuan</h1>
                <p class="subtitle">Temukan jawaban untuk pertanyaan Anda tentang platform ShrineComics</p>
            </div>
            <div class="header-actions">
                <a href="dashboard_writer.php" class="btn btn-outline">
                    <span class="btn-icon">←</span>
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Quick Help Section -->
        <section class="quick-help-section">
            <div class="section-header">
                <h2>🆘 Bantuan Cepat</h2>
                <p>Pilih kategori bantuan yang Anda butuhkan</p>
            </div>
            
            <div class="help-categories">
                <div class="help-category-card" data-category="komik">
                    <div class="category-icon">📚</div>
                    <h3>Manajemen Komik</h3>
                    <p>Cara membuat, mengedit, dan mengelola komik</p>
                    <span class="category-badge">5 artikel</span>
                </div>
                
                <div class="help-category-card" data-category="chapter">
                    <div class="category-icon">📖</div>
                    <h3>Manajemen Chapter</h3>
                    <p>Panduan membuat dan mengatur chapter</p>
                    <span class="category-badge">4 artikel</span>
                </div>
                <!--
                <div class="help-category-card" data-category="monetization">
                    <div class="category-icon">💰</div>
                    <h3>Monetisasi & Penghasilan</h3>
                    <p>Cara mendapatkan penghasilan dari komik</p>
                    <span class="category-badge">3 artikel</span>
                </div>
-->
                
                <div class="help-category-card" data-category="account">
                    <div class="category-icon">👤</div>
                    <h3>Akun & Profile</h3>
                    <p>Pengaturan akun dan keamanan</p>
                    <span class="category-badge">4 artikel</span>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="faq-section">
            <div class="section-header">
                <h2>❓ Pertanyaan Umum (FAQ)</h2>
                <p>Pertanyaan yang sering diajukan oleh writer</p>
            </div>
            
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Bagaimana cara membuat komik baru?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Untuk membuat komik baru:</p>
                        <ol>
                            <li>Klik tombol "Buat Komik Baru" di dashboard</li>
                            <li>Isi judul komik dan pilih genre</li>
                            <li>Upload banner komik (opsional)</li>
                            <li>Tambahkan deskripsi yang menarik</li>
                            <li>Klik "Simpan" untuk membuat komik</li>
                        </ol>
                    </div>
                </div>
                <!--
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Bagaimana sistem monetisasi bekerja?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Sistem monetisasi ShrineComics:</p>
                        <ul>
                            <li>Setiap chapter berbayar akan menghasilkan poin</li>
                            <li>Pembaca membeli chapter dengan poin mereka</li>
                            <li>Anda mendapatkan 70% dari setiap penjualan</li>
                            <li>Poin dapat ditarik atau digunakan di platform</li>
                            <li>Minimum penarikan: 10.000 poin</li>
                        </ul>
                    </div>
                </div>
-->
                
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Bagaimana cara menambahkan chapter baru?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Langkah menambahkan chapter:</p>
                        <ol>
                            <li>Pergi ke halaman detail komik</li>
                            <li>Klik "Tambah Chapter"</li>
                            <li>Isi nomor chapter dan judul</li>
                            <li>Upload konten chapter (gambar/teks)</li>
                            <li>Atur harga chapter (gratis/berbayar)</li>
                            <li>Klik "Publish" untuk menerbitkan</li>
                        </ol>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Bagaimana cara menarik penghasilan?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Proses penarikan penghasilan:</p>
                        <ul>
                            <li>Pastikan saldo poin minimal 10.000</li>
                            <li>Pergi ke halaman "Penarikan" di profile</li>
                            <li>Pilih metode penarikan (bank/ewallet)</li>
                            <li>Isi jumlah poin yang ingin ditarik</li>
                            <li>Proses akan selesai dalam 1-3 hari kerja</li>
                        </ul>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Apa yang harus dilakukan jika komik ditolak?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Jika komik ditolak:</p>
                        <ul>
                            <li>Periksa email untuk alasan penolakan</li>
                            <li>Perbaiki masalah yang disebutkan</li>
                            <li>Pastikan konten tidak melanggar guidelines</li>
                            <li>Submit ulang komik setelah diperbaiki</li>
                            <li>Hubungi support jika perlu bantuan lebih</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Support Section -->
        <section class="contact-section">
            <div class="section-header">
                <h2>📞 Butuh Bantuan Lebih Lanjut?</h2>
                <p>Tim support kami siap membantu Anda</p>
            </div>
            
            <div class="contact-methods">
                <div class="contact-card">
                    <div class="contact-icon">📧</div>
                    <h3>Email Support</h3>
                    <p>support@shrinecomics.com</p>
                    <small>Response time: 24 jam</small>
                </div>
                
                <div class="contact-card">
                    <div class="contact-icon">💬</div>
                    <h3>Live Chat</h3>
                    <p>Buka jam 09:00 - 17:00 WIB</p>
                    <small>Senin - Jumat</small>
                </div>
                
                <div class="contact-card">
                    <div class="contact-icon">📚</div>
                    <h3>Documentation</h3>
                    <p>Panduan lengkap platform</p>
                    <small>Selalu diperbarui</small>
                </div>
            </div>

            <!-- Contact Form -->
            <!--
            <div class="contact-form-container">
                <h3>Kirim Pertanyaan</h3>
                <form class="contact-form" id="supportForm">
                    <div class="form-group">
                        <label for="subject">Subjek</label>
                        <select id="subject" name="subject" required>
                            <option value="">Pilih subjek...</option>
                            <option value="technical">Masalah Teknis</option>
                            <option value="monetization">Pertanyaan Monetisasi</option>
                            <option value="content">Masalah Konten</option>
                            <option value="account">Masalah Akun</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Pesan Detail</label>
                        <textarea id="message" name="message" rows="5" placeholder="Jelaskan masalah atau pertanyaan Anda secara detail..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="attachment">Lampiran (Opsional)</label>
                        <input type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        <small>Maksimal 5MB. Format: JPG, PNG, PDF, DOC</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-large">
                        <span class="btn-icon">📨</span>
                        Kirim Pertanyaan
                    </button>
                </form>
            </div>-->
        </section>

        <!-- Quick Links -->
        <section class="quick-links-section">
            <div class="section-header">
                <h2>🔗 Tautan Cepat</h2>
            </div>
            
            <div class="quick-links">
                <a href="terms.php" class="quick-link">Syarat & Ketentuan</a>
                <a href="privacy.php" class="quick-link">Kebijakan Privasi</a>
                <a href="guidelines.php" class="quick-link">Panduan Konten</a>
                <a href="community.php" class="quick-link">Komunitas Writer</a>
            </div>
        </section>
    </div>

    <script src="help.js"></script>
</body>
</html>

<?php
$koneksi->close();
?>