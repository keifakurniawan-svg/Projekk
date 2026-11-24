<?php
/* CRUD master shift (jam kerja, warna, shift wajib) */
require_once __DIR__ . '/../config/koneksi.php';
require_role(['admin', 'head_store']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_shift'] ?? ''); $mulai = $_POST['jam_mulai'] ?? ''; $selesai = $_POST['jam_selesai'] ?? '';
    $warna = $_POST['warna'] ?? '#c8873f'; $wajib = isset($_POST['wajib']) ? 1 : 0;
    try {
        if ($aksi === 'simpan') {
            $ok = $nama !== '' && preg_match('/^\d{2}:\d{2}$/', $mulai) && preg_match('/^\d{2}:\d{2}$/', $selesai) && preg_match('/^#[0-9a-fA-F]{6}$/', $warna);
            if (!$ok) { flash('danger', 'Lengkapi nama, jam, dan warna shift dengan benar.'); }
            elseif ($selesai <= $mulai) { flash('danger', 'Jam selesai harus setelah jam mulai (shift tidak boleh melewati tengah malam).'); }
            elseif ($id > 0) { ex($konek, "UPDATE shift_master SET nama_shift=?, jam_mulai=?, jam_selesai=?, warna=?, wajib=? WHERE id_shift=?", 'ssssii', [$nama, $mulai, $selesai, $warna, $wajib, $id]); flash('success', 'Shift diperbarui.'); }
            else { ex($konek, "INSERT INTO shift_master (nama_shift, jam_mulai, jam_selesai, warna, wajib) VALUES (?,?,?,?,?)", 'ssssi', [$nama, $mulai, $selesai, $warna, $wajib]); flash('success', 'Shift ditambahkan.'); }
        } elseif ($aksi === 'hapus') {
            ex($konek, "DELETE FROM shift_master WHERE id_shift = ?", 'i', [$id]);
            flash('success', 'Shift dihapus.');
        }
    } catch (mysqli_sql_exception $ex) { flash('danger', 'Shift tidak dapat dihapus karena sudah dipakai pada jadwal.'); }
    redirect('shift/index.php');
}

$edit = ['id_shift' => 0, 'nama_shift' => '', 'jam_mulai' => '07:00:00', 'jam_selesai' => '15:00:00', 'warna' => '#c8873f', 'wajib' => 0];
if (isset($_GET['edit'])) $edit = q1($konek, "SELECT * FROM shift_master WHERE id_shift = ?", 'i', [(int)$_GET['edit']]) ?: $edit;
$data = q($konek, "SELECT sm.*, (SELECT COUNT(*) FROM jadwal j WHERE j.id_shift = sm.id_shift) AS dipakai FROM shift_master sm ORDER BY sm.jam_mulai");

$judul = 'Master Shift'; $aktif = 'shift';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <h2 class="h6 fw-bold mb-3"><?= $edit['id_shift'] ? 'Ubah Shift' : 'Tambah Shift' ?></h2>
    <form method="post" class="needs-validation" novalidate>
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)$edit['id_shift'] ?>">
      <div class="mb-3"><label class="form-label" for="nama_shift">Nama Shift</label><input class="form-control" id="nama_shift" name="nama_shift" value="<?= e($edit['nama_shift']) ?>" required maxlength="30"><div class="invalid-feedback">Wajib diisi.</div></div>
      <div class="row g-2 mb-3">
        <div class="col"><label class="form-label" for="jam_mulai">Mulai</label><input type="time" class="form-control" id="jam_mulai" name="jam_mulai" value="<?= e(jam($edit['jam_mulai'])) ?>" required></div>
        <div class="col"><label class="form-label" for="jam_selesai">Selesai</label><input type="time" class="form-control" id="jam_selesai" name="jam_selesai" value="<?= e(jam($edit['jam_selesai'])) ?>" required></div>
      </div>
      <div class="mb-3"><label class="form-label" for="warna">Warna label</label><input type="color" class="form-control form-control-color" id="warna" name="warna" value="<?= e($edit['warna']) ?>"></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="wajib" name="wajib" <?= $edit['wajib'] ? 'checked' : '' ?>><label class="form-check-label" for="wajib">Shift wajib tercover (buka/tutup)</label></div>
      <button class="btn btn-brand">Simpan</button>
      <?php if ($edit['id_shift']): ?><a class="btn btn-outline-secondary" href="index.php">Batal</a><?php endif; ?>
    </form>
  </div></div></div>
  <div class="col-lg-8"><div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Shift</th><th>Jam</th><th>Wajib</th><th>Dipakai</th><th class="text-end">Aksi</th></tr></thead><tbody>
    <?php foreach ($data as $r): ?>
      <tr><td><?= chip_shift($r['nama_shift'], $r['warna']) ?></td><td><?= jam($r['jam_mulai']) ?> - <?= jam($r['jam_selesai']) ?></td>
        <td><?= $r['wajib'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-secondary">-</span>' ?></td><td><?= $r['dipakai'] ?>x</td>
        <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id_shift'] ?>"><i class="bi bi-pencil"></i></a>
          <form class="d-inline" method="post" data-confirm="Hapus shift <?= e($r['nama_shift']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $r['id_shift'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
