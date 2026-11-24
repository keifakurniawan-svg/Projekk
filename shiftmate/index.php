<?php
/* Modul 6 & 7 : Login memakai session, cookie, prepared statement, password_verify */
require_once __DIR__ . '/config/koneksi.php';
if (!empty($_SESSION['login'])) redirect('dashboard.php');

$galat = '';
$usernameCookie = $_COOKIE['sm_username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($konek,
        "SELECT u.id_user, u.username, u.password, u.role, u.id_staf, s.nama_lengkap, s.status
         FROM users u LEFT JOIN staf s ON u.id_staf = s.id_staf WHERE u.username = ?");
    mysqli_stmt_bind_param($stmt, 's', $user);
    mysqli_stmt_execute($stmt);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($baris && password_verify($pass, $baris['password'])) {
        if ($baris['status'] === 'Nonaktif') {
            $galat = 'Akun Anda dinonaktifkan. Hubungi head store.';
        } else {
            session_regenerate_id(true);
            $_SESSION['login']   = true;
            $_SESSION['id_user'] = $baris['id_user'];
            $_SESSION['username'] = $baris['username'];
            $_SESSION['role']    = $baris['role'];
            $_SESSION['id_staf'] = $baris['id_staf'];
            $_SESSION['nama']    = $baris['nama_lengkap'] ?: 'Administrator';

            if (!empty($_POST['ingat'])) setcookie('sm_username', $baris['username'], time() + 86400 * 30, '/');
            else setcookie('sm_username', '', time() - 3600, '/');

            flash('success', 'Halo, ' . $_SESSION['nama'] . '! Selamat bekerja.');
            redirect('dashboard.php');
        }
    } else {
        $galat = 'Username atau password salah.';
    }
    $usernameCookie = $user;
}

$judul = 'Masuk';
include __DIR__ . '/includes/auth_head.php';
?>
<h1 class="h3 fw-bold mb-1">Masuk ke ShiftMate</h1>
<p class="text-secondary mb-4">Gunakan akun staf Anda untuk melihat jadwal dan handover.</p>

<?php if ($f = flash()): ?><div class="alert alert-<?= e($f[0]) ?>"><?= e($f[1]) ?></div><?php endif; ?>
<?php if ($galat): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($galat) ?></div><?php endif; ?>

<form method="post" class="needs-validation" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="username">Username</label>
    <input type="text" class="form-control" id="username" name="username" value="<?= e($usernameCookie) ?>" required autofocus>
    <div class="invalid-feedback">Username wajib diisi.</div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="password">Password</label>
    <div class="input-group">
      <input type="password" class="form-control" id="password" name="password" required>
      <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password"><i class="bi bi-eye"></i></button>
      <div class="invalid-feedback">Password wajib diisi.</div>
    </div>
  </div>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="ingat" name="ingat" <?= $usernameCookie ? 'checked' : '' ?>>
    <label class="form-check-label" for="ingat">Ingat username saya</label>
  </div>
  <button class="btn btn-brand w-100 py-2" type="submit">Masuk</button>
</form>
<p class="text-center mt-3 mb-4">Staf baru? <a href="register.php" class="text-brand fw-semibold">Buat akun</a></p>

<div class="card card-body small bg-body-tertiary">
  <div class="fw-semibold mb-1">Akun demo</div>
  <div><code>admin</code> / admin123</div>
  <div><code>raka.head</code> / head12345 (Head Store)</div>
  <div><code>sinta</code>, <code>bagas</code>, <code>dimas</code> / staf12345 (Staf)</div>
</div>
<?php include __DIR__ . '/includes/auth_foot.php'; ?>
