<?php
require_once '../config/database.php';
requireLogin(); // Semua role bisa akses

$pageTitle = 'Profil Saya';
$pageSubtitle = 'Ubah username dan password Anda';

$userId = user()['id'];

// Ambil data user terbaru dari DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$u = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    
    try {
        if ($act === 'update_username') {
            $usernameBaru = trim($_POST['username'] ?? '');
            $passLama     = $_POST['password_lama'] ?? '';
            
            if ($usernameBaru === '' || $passLama === '') {
                throw new Exception('Username dan password lama wajib diisi.');
            }
            
            // Verifikasi password lama
            if (!password_verify($passLama, $u['password'])) {
                throw new Exception('Password lama salah.');
            }
            
            // Cek username unik
            $cek = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $cek->execute([$usernameBaru, $userId]);
            if ($cek->fetch()) {
                throw new Exception('Username sudah dipakai user lain.');
            }
            
            $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")
                ->execute([$usernameBaru, $userId]);
            
            // Update session
            $_SESSION['user']['username'] = $usernameBaru;
            
            flash('success', 'Username berhasil diubah.');
            redirect('profil.php');
            
        } elseif ($act === 'update_password') {
            $passLama     = $_POST['password_lama'] ?? '';
            $passBaru     = $_POST['password_baru'] ?? '';
            $passKonfirm  = $_POST['password_konfirm'] ?? '';
            
            if ($passLama === '' || $passBaru === '' || $passKonfirm === '') {
                throw new Exception('Semua kolom password wajib diisi.');
            }
            
            if (!password_verify($passLama, $u['password'])) {
                throw new Exception('Password lama salah.');
            }
            
            if (strlen($passBaru) < 6) {
                throw new Exception('Password baru minimal 6 karakter.');
            }
            
            if ($passBaru !== $passKonfirm) {
                throw new Exception('Konfirmasi password tidak cocok.');
            }
            
            // Simpan password apa adanya (teks biasa)
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$passBaru, $userId]);
            
            flash('success', 'Password berhasil diubah. Silakan login ulang jika perlu.');
            redirect('profil.php');
        }
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        redirect('profil.php');
    }
}

require_once '../includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
  <div class="card-head">
    <h2>👤 Profil Saya</h2>
  </div>
  <div class="card-body">
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
      <div style="width: 60px; height: 60px; background: #10b981; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
        <?= strtoupper(substr($u['nama'], 0, 1)) ?>
      </div>
      <div>
        <div style="font-weight: bold; font-size: 16px;"><?= e($u['nama']) ?></div>
        <div style="color: #64748b; font-size: 13px;">Role: <strong><?= ucfirst($u['role']) ?></strong></div>
      </div>
    </div>

    <!-- FORM UBAH USERNAME -->
    <form method="post" style="margin-bottom: 30px;">
      <input type="hidden" name="act" value="update_username">
      <h3 style="font-size: 15px; margin-bottom: 12px;">Ubah Username</h3>
      
      <div class="form-group" style="margin-bottom: 12px;">
        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Username Baru</label>
        <input type="text" name="username" value="<?= e($u['username']) ?>" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
      </div>
      
      <div class="form-group" style="margin-bottom: 15px;">
        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Password Anda (untuk verifikasi)</label>
        <input type="password" name="password_lama" required placeholder="Masukkan password saat ini" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
      </div>
      
      <button type="submit" class="btn btn-primary" style="padding: 9px 20px;">Simpan Username</button>
    </form>

    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;">

    <!-- FORM UBAH PASSWORD -->
    <form method="post">
      <input type="hidden" name="act" value="update_password">
      <h3 style="font-size: 15px; margin-bottom: 12px;">Ubah Password</h3>
      
      <div class="form-group" style="margin-bottom: 12px;">
        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Password Lama</label>
        <input type="password" name="password_lama" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
      </div>
      
      <div class="form-group" style="margin-bottom: 12px;">
        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Password Baru</label>
        <input type="password" name="password_baru" required minlength="6" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
      </div>
      
      <div class="form-group" style="margin-bottom: 15px;">
        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Konfirmasi Password Baru</label>
        <input type="password" name="password_konfirm" required minlength="6" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
      </div>
      
      <button type="submit" class="btn btn-primary" style="padding: 9px 20px;">Simpan Password</button>
    </form>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>