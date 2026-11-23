<?php
require_once '../config/database.php';
requireAdmin(); // Khusus Admin

$pageTitle = 'Manajemen Pengguna';
$pageSubtitle = 'Kelola akun admin dan kasir';

// Handle Aksi (Tambah / Edit / Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    
    try {
        if ($act === 'tambah') {
            $nama     = trim($_POST['nama'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = $_POST['role'] ?? 'kasir';
            
            if (!$nama || !$username || !$password) throw new Exception('Semua kolom wajib diisi.');
            if (strlen($password) < 6) throw new Exception('Password minimal 6 karakter.');
            
            $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $cek->execute([$username]);
            if ($cek->fetch()) throw new Exception('Username sudah dipakai.');
            
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)")
                ->execute([$nama, $username, $hash, $role]);
                
            flash('success', 'Pengguna baru berhasil ditambahkan.');
            
        } elseif ($act === 'reset_password') {
            $id       = (int)$_POST['id'];
            $password = $_POST['password_baru'] ?? '';
            
            if (strlen($password) < 6) throw new Exception('Password minimal 6 karakter.');
            
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $id]);
            
            flash('success', 'Password pengguna berhasil direset.');
            
        } elseif ($act === 'hapus') {
            $id = (int)$_POST['id'];
            if ($id === user()['id']) throw new Exception('Anda tidak bisa menghapus akun sendiri.');
            
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            flash('success', 'Pengguna berhasil dihapus.');
        }
    } catch (Exception $e) {
        flash('error', $e->getMessage());
    }
    redirect('users.php');
}

// Ambil semua user
$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, nama ASC")->fetchAll();

require_once '../includes/header.php';
?>

<div class="card">
  <div class="card-head">
    <h2>👥 Daftar Pengguna</h2>
    <button class="btn btn-primary" onclick="document.getElementById('modalTambah').classList.add('show')">+ Tambah Pengguna</button>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nama</th>
          <th>Username</th>
          <th class="text-center">Role</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="strong"><?= e($u['nama']) ?></td>
          <td><code><?= e($u['username']) ?></code></td>
          <td class="text-center">
            <?php if ($u['role'] === 'admin'): ?>
              <span class="tag tag-green">ADMIN</span>
            <?php else: ?>
              <span class="tag tag-blue">KASIR</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="btn btn-outline btn-sm" onclick="bukaReset(<?= $u['id'] ?>, '<?= e($u['nama']) ?>')">🔑 Reset Pass</button>
            <?php if ($u['id'] !== user()['id']): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Hapus user ini?')">
                <input type="hidden" name="act" value="hapus">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn btn-danger btn-sm">🗑</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal-overlay no-print" id="modalTambah">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Tambah Pengguna Baru</h3>
      <button class="modal-close" onclick="document.getElementById('modalTambah').classList.remove('show')">×</button>
    </div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="act" value="tambah">
        <div class="form-group" style="margin-bottom:10px;">
          <label>Nama Lengkap</label>
          <input type="text" name="nama" required class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label>Username</label>
          <input type="text" name="username" required class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label>Password</label>
          <input type="password" name="password" required minlength="6" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label>Role</label>
          <select name="role" required class="form-control">
            <option value="kasir">Kasir</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalTambah').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL RESET PASSWORD -->
<div class="modal-overlay no-print" id="modalReset">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Reset Password</h3>
      <button class="modal-close" onclick="document.getElementById('modalReset').classList.remove('show')">×</button>
    </div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="act" value="reset_password">
        <input type="hidden" name="id" id="resetId">
        <p>Reset password untuk: <strong id="resetNama">-</strong></p>
        <div class="form-group" style="margin-top:10px;">
          <label>Password Baru</label>
          <input type="password" name="password_baru" required minlength="6" class="form-control">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalReset').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function bukaReset(id, nama) {
  document.getElementById('resetId').value = id;
  document.getElementById('resetNama').textContent = nama;
  document.getElementById('modalReset').classList.add('show');
}
</script>

<?php require_once '../includes/footer.php'; ?>