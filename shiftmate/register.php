<?php
/* Modul 7 : Register - password_hash() + prepared statement.
   Akun hanya dapat dibuat oleh staf yang kode stafnya sudah didaftarkan head store. */
require_once __DIR__ . '/config/koneksi.php';
if (!empty($_SESSION['login'])) redirect('dashboard.php');

$galat = [];
$nilai = ['kode' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $kode  = strtoupper(trim($_POST['kode_staf'] ?? ''));
    $user  = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $nilai = ['kode' => $kode, 'username' => $user];

    if (!preg_match('/^[A-Za-z0-9._]{4,30}$/', $user)) $galat[] = 'Username 4-30 karakter (huruf, angka, titik, garis bawah).';
    if (strlen($pass) < 6) $galat[] = 'Password minimal 6 karakter.';
    if ($pass !== $pass2)  $galat[] = 'Konfirmasi password tidak sama.';

    if (!$galat) {
        $stmt = mysqli_prepare($konek, "SELECT id_staf, status FROM staf WHERE kode_staf = ?");
        mysqli_stmt_bind_param($stmt, 's', $kode);
        mysqli_stmt_execute($stmt);
        $st = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$st || $st['status'] !== 'Aktif') {
            $galat[] = 'Kode staf tidak ditemukan atau tidak aktif. Hubungi head store.';
        } else {
            $cek = mysqli_prepare($konek, "SELECT 1 FROM users WHERE username = ? OR id_staf = ?");
            mysqli_stmt_bind_param($cek, 'si', $user, $st['id_staf']);
            mysqli_stmt_execute($cek);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($cek))) {
                $galat[] = 'Username sudah dipakai atau staf tersebut sudah memiliki akun.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $ins = mysqli_prepare($konek, "INSERT INTO users (username, password, role, id_staf) VALUES (?, ?, 'staf', ?)");
                mysqli_stmt_bind_param($ins, 'ssi', $user, $hash, $st['id_staf']);
                mysqli_stmt_execute($ins);
                flash('success', 'Akun berhasil dibuat. Silakan masuk.');
                redirect('index.php');
            }
        }
    }
}

$judul = 'Daftar Akun';
include __DIR__ . '/includes/auth_head.php';
?>
<h1 class="h3 fw-bold mb-1">Buat akun staf</h1>
<p class="text-secondary mb-4">Gunakan kode staf yang diberikan head store (contoh: SM-009).</p>

<?php if ($galat): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($galat as $g) echo '<li>' . e($g) . '</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" class="needs-validation" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="kode_staf">Kode Staf</label>
    <input type="text" class="form-control" id="kode_staf" name="kode_staf" value="<?= e($nilai['kode']) ?>" required placeholder="SM-009">
    <div class="invalid-feedback">Kode staf wajib diisi.</div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="username">Username</label>
    <input type="text" class="form-control" id="username" name="username" value="<?= e($nilai['username']) ?>" required pattern="[A-Za-z0-9._]{4,30}">
    <div class="invalid-feedback">4-30 karakter: huruf, angka, titik, atau garis bawah.</div>
  </div>
  <div class="mb-2">
    <label class="form-label" for="password">Password</label>
    <input type="password" class="form-control" id="password" name="password" required minlength="6">
    <div class="invalid-feedback">Minimal 6 karakter.</div>
  </div>
  <div class="mb-3">
    <div class="strength"><div id="strengthBar"></div></div>
    <div class="small text-secondary mt-1" id="strengthLabel">&nbsp;</div>
  </div>
  <div class="mb-4">
    <label class="form-label" for="password2">Ulangi Password</label>
    <input type="password" class="form-control" id="password2" name="password2" required>
    <div class="invalid-feedback">Konfirmasi password harus sama.</div>
  </div>
  <button class="btn btn-brand w-100 py-2" type="submit">Daftar</button>
</form>
<p class="text-center mt-3">Sudah punya akun? <a href="index.php" class="text-brand fw-semibold">Masuk</a></p>
<?php include __DIR__ . '/includes/auth_foot.php'; ?>
