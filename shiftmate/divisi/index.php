<?php
/* CRUD divisi + kebutuhan minimum staf per shift wajib */
require_once __DIR__ . '/../config/koneksi.php';
require_role(['admin', 'head_store']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_divisi'] ?? ''); $min = (int)($_POST['min_per_shift'] ?? 0);
    try {
        if ($aksi === 'simpan') {
            if ($nama === '' || $min < 0 || $min > 20) { flash('danger', 'Nama wajib diisi dan minimum staf 0-20.'); redirect('divisi/index.php'); }
            if ($id > 0) { ex($konek, "UPDATE divisi SET nama_divisi=?, min_per_shift=? WHERE id_divisi=?", 'sii', [$nama, $min, $id]); flash('success', 'Divisi diperbarui.'); }
            else { ex($konek, "INSERT INTO divisi (nama_divisi, min_per_shift) VALUES (?, ?)", 'si', [$nama, $min]); flash('success', 'Divisi ditambahkan.'); }
        } elseif ($aksi === 'hapus') {
            ex($konek, "DELETE FROM divisi WHERE id_divisi = ?", 'i', [$id]);
            flash('success', 'Divisi dihapus.');
        }
    } catch (mysqli_sql_exception $ex) { flash('danger', 'Divisi tidak dapat dihapus karena masih memiliki staf.'); }
    redirect('divisi/index.php');
}

$edit = ['id_divisi' => 0, 'nama_divisi' => '', 'min_per_shift' => 1];
if (isset($_GET['edit'])) $edit = q1($konek, "SELECT * FROM divisi WHERE id_divisi = ?", 'i', [(int)$_GET['edit']]) ?: $edit;
$data = q($konek, "SELECT d.*, COUNT(s.id_staf) AS jml FROM divisi d LEFT JOIN staf s ON s.id_divisi = d.id_divisi GROUP BY d.id_divisi ORDER BY d.id_divisi");

$judul = 'Divisi'; $aktif = 'divisi';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <h2 class="h6 fw-bold mb-3"><?= $edit['id_divisi'] ? 'Ubah Divisi' : 'Tambah Divisi' ?></h2>
    <form method="post" class="needs-validation" novalidate>
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)$edit['id_divisi'] ?>">
      <div class="mb-3"><label class="form-label" for="nama_divisi">Nama Divisi</label>
        <input class="form-control" id="nama_divisi" name="nama_divisi" value="<?= e($edit['nama_divisi']) ?>" required maxlength="30"><div class="invalid-feedback">Wajib diisi.</div></div>
      <div class="mb-3"><label class="form-label" for="min_per_shift">Minimum staf per shift wajib</label>
        <input type="number" class="form-control" id="min_per_shift" name="min_per_shift" value="<?= (int)$edit['min_per_shift'] ?>" min="0" max="20" required>
        <div class="form-text">Dipakai editor jadwal untuk memberi peringatan kekurangan staf. Isi 0 untuk menonaktifkan.</div></div>
      <button class="btn btn-brand">Simpan</button>
      <?php if ($edit['id_divisi']): ?><a class="btn btn-outline-secondary" href="index.php">Batal</a><?php endif; ?>
    </form>
  </div></div></div>
  <div class="col-lg-8"><div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>#</th><th>Divisi</th><th>Min. per shift</th><th>Jumlah staf</th><th class="text-end">Aksi</th></tr></thead><tbody>
    <?php foreach ($data as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td class="fw-semibold"><?= e($r['nama_divisi']) ?></td><td><?= (int)$r['min_per_shift'] ?></td><td><span class="badge text-bg-light border"><?= $r['jml'] ?></span></td>
        <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id_divisi'] ?>"><i class="bi bi-pencil"></i></a>
          <form class="d-inline" method="post" data-confirm="Hapus divisi <?= e($r['nama_divisi']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $r['id_divisi'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
