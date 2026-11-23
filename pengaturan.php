<?php
// 1. Panggil koneksi database.
require_once 'config/database.php';

// 2. Pastikan user sudah login.
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$user_id) {
    header("Location: login.php");
    exit;
}

$pesan_sukses = '';
$pesan_error = '';

// 3. Proses Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil semua data dari form
    $new_username = trim($_POST['new_username'] ?? '');
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Validasi: Pastikan TIDAK ADA yang kosong
    if (empty($new_username) || empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $pesan_error = "Semua kolom wajib diisi! Anda harus mengisi Username Baru, Password Lama, dan Password Baru sekaligus.";
    } elseif ($new_pass !== $confirm_pass) {
        $pesan_error = "Konfirmasi password baru tidak cocok!";
    } elseif (strlen($new_pass) < 6) {
        $pesan_error = "Password baru minimal 6 karakter!";
    } else {
        try {
            // Cek apakah username baru sudah dipakai orang lain
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$new_username, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $pesan_error = "Username sudah digunakan oleh pengguna lain!";
            } else {
                // Ambil data user saat ini dari database
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                // Verifikasi password lama
                if ($user && password_verify($current_pass, $user['password'])) {
                    // Jika password lama benar, update KEDUANYA (Username & Password)
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $stmt->execute([$new_username, $new_hash, $user_id]);
                    
                    // Update session agar nama di sidebar langsung berubah
                    $_SESSION['username'] = $new_username; 
                    
                    $pesan_sukses = "Berhasil! Username dan Password Anda telah diperbarui.";
                } else {
                    $pesan_error = "Password lama salah! Perubahan dibatalkan.";
                }
            }
        } catch (PDOException $e) {
            $pesan_error = "Error Database: " . $e->getMessage();
        }
    }
}

// 4. Set judul halaman & panggil header
$pageTitle = 'Pengaturan Akun';
$pageSubtitle = 'Ubah Username dan Password Sekaligus';
require_once 'includes/header.php';
?>

<!-- ================= KONTEN HALAMAN ================= -->
<div class="content" style="padding: 20px;">
    
    <div style="max-width: 600px; margin: 0 auto;">
        
        <!-- Tampilkan Pesan Sukses/Error -->
        <?php if ($pesan_sukses): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px;">
                <i class="bi bi-check-circle-fill" style="font-size: 20px;"></i>
                <div><?= $pesan_sukses ?></div>
            </div>
        <?php endif; ?>

        <?php if ($pesan_error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px;">
                <i class="bi bi-exclamation-circle-fill" style="font-size: 20px;"></i>
                <div><?= $pesan_error ?></div>
            </div>
        <?php endif; ?>

        <!-- FORM GABUNGAN -->
        <div class="card" style="padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
                <i class="bi bi-shield-lock" style="margin-right: 8px;"></i> Form Perubahan Akun
            </h3>
            
            <form action="pengaturan.php" method="POST">
                
                <!-- Input Username Baru -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #475569;">Username Baru</label>
                    <input type="text" name="new_username" required 
                           placeholder="Masukkan username baru"
                           value="<?= htmlspecialchars($_POST['new_username'] ?? ($_SESSION['username'] ?? '')) ?>"
                           style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 20px; border-bottom: 1px dashed #cbd5e1;"></div>

                <!-- Input Password Lama -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #475569;">Password Lama (Wajib)</label>
                    <div style="position: relative;">
                        <input type="password" name="current_password" id="pass_lama" required 
                               placeholder="Masukkan password Anda saat ini"
                               style="width: 100%; padding: 10px 40px 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        <!-- Tombol Show Password -->
                        <i class="bi bi-eye toggle-password" data-target="pass_lama" 
                           style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 18px; color: #64748b;"></i>
                    </div>
                </div>

                <!-- Input Password Baru -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #475569;">Password Baru</label>
                    <div style="position: relative;">
                        <input type="password" name="new_password" id="pass_baru" required 
                               placeholder="Masukkan password baru (min. 6 karakter)"
                               style="width: 100%; padding: 10px 40px 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        <!-- Tombol Show Password -->
                        <i class="bi bi-eye toggle-password" data-target="pass_baru" 
                           style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 18px; color: #64748b;"></i>
                    </div>
                </div>

                <!-- Input Konfirmasi Password Baru -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #475569;">Konfirmasi Password Baru</label>
                    <div style="position: relative;">
                        <input type="password" name="confirm_password" id="pass_konfirmasi" required 
                               placeholder="Ulangi password baru"
                               style="width: 100%; padding: 10px 40px 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        <!-- Tombol Show Password -->
                        <i class="bi bi-eye toggle-password" data-target="pass_konfirmasi" 
                           style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 18px; color: #64748b;"></i>
                    </div>
                </div>
                
                <button type="submit" style="width: 100%; background: #047857; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 15px; transition: background 0.2s;">
                    Simpan Perubahan (Username & Password)
                </button>
            </form>
        </div>

    </div>
</div>

<!-- SCRIPT UNTUK SHOW/HIDE PASSWORD -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePasswordIcons = document.querySelectorAll('.toggle-password');
    
    togglePasswordIcons.forEach(icon => {
        icon.addEventListener('click', function() {
            // Ambil ID target input dari atribut data-target
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            
            // Cek tipe input saat ini
            if (input.type === 'password') {
                input.type = 'text';
                // Ganti ikon jadi mata dicoret
                this.classList.remove('bi-eye');
                this.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                // Ganti ikon jadi mata terbuka
                this.classList.remove('bi-eye-slash');
                this.classList.add('bi-eye');
            }
        });
    });
});
</script>

<?php
// 5. Panggil footer
require_once 'includes/footer.php';
?>