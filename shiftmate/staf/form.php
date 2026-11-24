<?php
/* Modul 5 & 8 : CREATE dan UPDATE staf + upload foto */
require_once __DIR__ . '/../config/koneksi.php';
require_role(['admin', 'head_store']);

$id = (int)($_GET['id'] ?? 0);
$edit = $id > 0;
$data = ['kode_staf' => '', 'nama_lengkap' => '', 'email' => '', 'no_hp' => '', 'tgl_bergabung' => date('Y-m-d'), 'status' => 'Aktif', 'foto' => null, 'id_divisi' => ''];
if ($edit) {
    $data = q1($konek, "SELECT * FROM staf WHERE id_staf = ?", 'i', [$id]);
    if (!$data) { flash('danger', 'Data staf tidak ditemukan.'); redirect('staf/index.php'); }
} else {
    $n = (int)q1($konek, "SELECT COALESCE(MAX(id_staf),0) + 1 AS n FROM staf")['n'];
    $data['kode_staf'] = 'SM-' . str_pad($n, 3, '0', STR_PAD_LEFT);          // usulan kode otomatis
}

$galat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['kode_staf', 'nama_lengkap', 'email', 'no_hp', 'tgl_bergabung', 'status'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['kode_staf'] = strtoupper($data['kode_staf']);
    $data['id_divisi'] = (int)($_POST['id_divisi'] ?? 0);

    if (!preg_match('/^[A-Z0-9-]{3,10}$/', $data['kode_staf'])) $galat[] = 'Kode staf 3-10 karakter (huruf besar, angka, strip).';
    if ($data['nama_lengkap'] === '') $galat[] = 'Nama lengkap wajib diisi.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Format email tidak valid.';
    if (!preg_match('/^[0-9+]{8,20}$/', $data['no_hp'])) $galat[] = 'No. HP hanya angka (8-20 digit).';
    if (!strtotime($data['tgl_bergabung'])) $galat[] = 'Tanggal bergabung tidak valid.';
    if (!in_array($data['status'], ['Aktif', 'Nonaktif'], true)) $galat[] = 'Status tidak valid.';
    if ($data['id_divisi'] < 1) $galat[] = 'Divisi wajib dipilih.';
    if (!$galat && q1($konek, "SELECT 1 FROM staf WHERE kode_staf = ? AND id_staf <> ?", 'si', [$data['kode_staf'], $id])) $galat[] = 'Kode staf sudah dipakai.';

    $fotoBaru = null;
    if (!$galat) { $err = ''; $fotoBaru = upload_foto($_FILES['foto'], $err); if ($fotoBaru === false) $galat[] = $err; }

    if (!$galat) {
        if ($edit) {
            $fotoSimpan = $fotoBaru ?: $data['foto'];
            ex($konek, "UPDATE staf SET kode_staf=?, nama_lengkap=?, email=?, no_hp=?, tgl_bergabung=?, status=?, foto=?, id_divisi=? WHERE id_staf=?",
               'sssssssii', [$data['kode_staf'], $data['nama_lengkap'], $data['email'], $data['no_hp'], $data['tgl_bergabung'], $data['status'], $fotoSimpan, $data['id_divisi'], $id]);
            if ($fotoBaru && $data['foto']) hapus_foto($data['foto']);
            flash('success', 'Data staf berhasil diperbarui.');
        } else {
            ex($konek, "INSERT INTO staf (kode_staf, nama_lengkap, email, no_hp, tgl_bergabung, status, foto, id_divisi) VALUES (?,?,?,?,?,?,?,?)",
               'sssssssi', [$data['kode_staf'], $data['nama_lengkap'], $data['email'], $data['no_hp'], $data['tgl_bergabung'], $data['status'], $fotoBaru, $data['id_divisi']]);
            flash('success', 'Staf baru ditambahkan. Berikan kode ' . $data['kode_staf'] . ' agar ia dapat mendaftar akun.');
        }
        redirect('staf/index.php');
    }
}

$daftarDivisi = q($konek, "SELECT * FROM divisi ORDER BY id_divisi");
$judul = $edit ? 'Ubah Data Staf' : 'Tambah Staf';
$aktif = 'staf';
include __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="card-body">
  <?php if ($galat): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($galat as $g) echo '<li>' . e($g) . '</li>'; ?></ul></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-3 text-center">
        <div id="previewFoto" class="mb-2"><?php if ($data['foto']): ?><img class="avatar avatar-lg" src="<?= foto_url($data['foto']) ?>" alt=""><?php else: ?><span class="avatar avatar-lg"><i class="bi bi-person fs-1"></i></span><?php endif; ?></div>
        <label class="form-label small" for="foto">Foto (JPG/PNG/WEBP, maks 2 MB)</label>
        <input type="file" class="form-control form-control-sm" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
      </div>
      <div class="col-md-9"><div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="kode_staf">Kode Staf</label>
          <input class="form-control" id="kode_staf" name="kode_staf" value="<?= e($data['kode_staf']) ?>" required pattern="[A-Za-z0-9-]{3,10}"><div class="invalid-feedback">3-10 karakter.</div></div>
        <div class="col-md-8"><label class="form-label" for="nama_lengkap">Nama Lengkap</label>
          <input class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= e($data['nama_lengkap']) ?>" required maxlength="80"><div class="invalid-feedback">Nama wajib diisi.</div></div>
        <div class="col-md-6"><label class="form-label" for="email">Email</label>
          <input type="email" class="form-control" id="email" name="email" value="<?= e($data['email']) ?>" required><div class="invalid-feedback">Email tidak valid.</div></div>
        <div class="col-md-6"><label class="form-label" for="no_hp">No. HP</label>
          <input class="form-control" id="no_hp" name="no_hp" value="<?= e($data['no_hp']) ?>" required pattern="[0-9+]{8,20}"><div class="invalid-feedback">Hanya angka, 8-20 digit.</div></div>
        <div class="col-md-6"><label class="form-label" for="id_divisi">Divisi</label>
          <select class="form-select" id="id_divisi" name="id_divisi" required><option value="">-- pilih --</option>
            <?php foreach ($daftarDivisi as $d): ?><option value="<?= $d['id_divisi'] ?>" <?= (int)$data['id_divisi'] === (int)$d['id_divisi'] ? 'selected' : '' ?>><?= e($d['nama_divisi']) ?></option><?php endforeach; ?>
          </select><div class="invalid-feedback">Pilih divisi.</div></div>
        <div class="col-md-6"><label class="form-label" for="tgl_bergabung">Tanggal Bergabung</label>
          <input type="date" class="form-control" id="tgl_bergabung" name="tgl_bergabung" value="<?= e($data['tgl_bergabung']) ?>" required></div>
        <div class="col-12"><label class="form-label d-block">Status</label>
          <?php foreach (['Aktif', 'Nonaktif'] as $s): ?>
            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="status" id="st<?= $s ?>" value="<?= $s ?>" <?= $data['status'] === $s ? 'checked' : '' ?> required><label class="form-check-label" for="st<?= $s ?>"><?= $s ?></label></div>
          <?php endforeach; ?>
          <div class="form-text">Staf nonaktif tidak muncul di jadwal dan tidak dapat login.</div></div>
      </div></div>
    </div>
    <hr>
    <div class="d-flex gap-2 justify-content-end"><a href="index.php" class="btn btn-outline-secondary">Batal</a><button class="btn btn-brand"><i class="bi bi-check-lg"></i> Simpan</button></div>
  </form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
