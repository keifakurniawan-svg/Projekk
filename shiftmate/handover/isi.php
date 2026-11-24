<?php
/* Form handover & catatan shift - wajib diisi di akhir jam kerja */
require_once __DIR__ . '/../config/koneksi.php';
require_login();

$idSaya = (int)($_SESSION['id_staf'] ?? 0);
$idJ = (int)($_GET['jadwal'] ?? $_POST['jadwal'] ?? 0);

$j = q1($konek,
    "SELECT j.id_jadwal, j.id_staf, j.tanggal, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai, s.nama_lengkap, d.nama_divisi
     FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift JOIN staf s ON j.id_staf = s.id_staf JOIN divisi d ON s.id_divisi = d.id_divisi
     WHERE j.id_jadwal = ?", 'i', [$idJ]);

if (!$j || (int)$j['id_staf'] !== $idSaya) { flash('danger', 'Shift tidak ditemukan atau bukan milik Anda.'); redirect('handover/index.php'); }
if (q1($konek, "SELECT 1 FROM handover WHERE id_jadwal = ?", 'i', [$idJ])) { flash('info', 'Handover untuk shift ini sudah diisi.'); redirect('handover/index.php'); }
if (!handover_terbuka($j['tanggal'], $j['jam_selesai'])) {
    flash('warning', 'Handover baru dapat diisi mulai pukul ' . date('H:i', strtotime($j['tanggal'] . ' ' . $j['jam_selesai']) - 3600) . '.');
    redirect('handover/index.php');
}

$galat = [];
$d = ['tingkat_keramaian' => '', 'kondisi_area' => '', 'stok_menipis' => '', 'kendala' => '', 'catatan' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($d as $k => $_) $d[$k] = trim($_POST[$k] ?? '');
    $cek = [isset($_POST['cek_kebersihan']) ? 1 : 0, isset($_POST['cek_peralatan']) ? 1 : 0, isset($_POST['cek_stok']) ? 1 : 0];

    if (!in_array($d['tingkat_keramaian'], ['Sepi', 'Normal', 'Ramai', 'Sangat Ramai'], true)) $galat[] = 'Pilih tingkat keramaian.';
    if (!in_array($d['kondisi_area'], ['Bersih & Rapi', 'Perlu Dirapikan', 'Bermasalah'], true)) $galat[] = 'Pilih kondisi area kerja.';
    if (array_sum($cek) < 3) $galat[] = 'Seluruh checklist penutupan wajib dicentang.';
    if (mb_strlen($d['catatan']) < 20) $galat[] = 'Catatan serah terima minimal 20 karakter.';
    if ($d['kondisi_area'] === 'Bermasalah' && $d['kendala'] === '') $galat[] = 'Jelaskan kendala karena area kerja berstatus bermasalah.';

    if (!$galat) {
        ex($konek, "INSERT INTO handover (id_jadwal, id_staf, tingkat_keramaian, kondisi_area, cek_kebersihan, cek_peralatan, cek_stok, stok_menipis, kendala, catatan, waktu_isi)
                    VALUES (?,?,?,?,?,?,?,?,?,?, NOW())",
           'iissiiisss', [$idJ, $idSaya, $d['tingkat_keramaian'], $d['kondisi_area'], $cek[0], $cek[1], $cek[2], $d['stok_menipis'] ?: null, $d['kendala'] ?: null, $d['catatan']]);
        flash('success', 'Handover berhasil disimpan. Terima kasih, shift Anda selesai!');
        redirect('handover/index.php');
    }
}

$judul = 'Isi Handover';
$aktif = 'handover';
include __DIR__ . '/../includes/header.php';
?>
<div class="card shift-hero mb-3" style="--c:<?= e($j['warna']) ?>"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div><div class="small text-secondary"><?= e($j['nama_lengkap']) ?> &middot; <?= e($j['nama_divisi']) ?></div>
    <div class="h5 mb-0"><?= e(hari_indo($j['tanggal'])) ?>, <?= tgl_indo($j['tanggal']) ?></div></div>
  <?= chip_shift($j['nama_shift'], $j['warna'], $j['jam_mulai'], $j['jam_selesai']) ?>
</div></div>

<div class="card"><div class="card-body">
  <?php if ($galat): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($galat as $g) echo '<li>' . e($g) . '</li>'; ?></ul></div><?php endif; ?>
  <form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?><input type="hidden" name="jadwal" value="<?= $idJ ?>">

    <div class="mb-4">
      <label class="form-label fw-semibold">Tingkat keramaian shift</label>
      <div class="row g-2">
        <?php foreach (['Sepi' => 'bi-emoji-neutral', 'Normal' => 'bi-emoji-smile', 'Ramai' => 'bi-people', 'Sangat Ramai' => 'bi-fire'] as $o => $ik): ?>
          <div class="col-6 col-md-3 keramaian-opt">
            <input type="radio" name="tingkat_keramaian" id="k<?= md5($o) ?>" value="<?= $o ?>" <?= $d['tingkat_keramaian'] === $o ? 'checked' : '' ?> required>
            <label for="k<?= md5($o) ?>"><i class="bi <?= $ik ?> d-block fs-4"></i><?= $o ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label fw-semibold" for="kondisi_area">Kondisi area kerja</label>
        <select class="form-select" id="kondisi_area" name="kondisi_area" required>
          <option value="">-- pilih --</option>
          <?php foreach (['Bersih & Rapi', 'Perlu Dirapikan', 'Bermasalah'] as $o): ?><option <?= $d['kondisi_area'] === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
        </select><div class="invalid-feedback">Pilih kondisi area.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold d-block">Checklist penutupan <span class="text-danger">*</span></label>
        <div class="form-check"><input class="form-check-input cek-tutup" type="checkbox" id="cek_kebersihan" name="cek_kebersihan" <?= isset($_POST['cek_kebersihan']) ? 'checked' : '' ?>><label class="form-check-label" for="cek_kebersihan">Area kerja sudah dibersihkan</label></div>
        <div class="form-check"><input class="form-check-input cek-tutup" type="checkbox" id="cek_peralatan" name="cek_peralatan" <?= isset($_POST['cek_peralatan']) ? 'checked' : '' ?>><label class="form-check-label" for="cek_peralatan">Peralatan dicuci dan disimpan</label></div>
        <div class="form-check"><input class="form-check-input cek-tutup" type="checkbox" id="cek_stok" name="cek_stok" <?= isset($_POST['cek_stok']) ? 'checked' : '' ?>><label class="form-check-label" for="cek_stok">Stok &amp; kas sudah dicek</label></div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6"><label class="form-label fw-semibold" for="stok_menipis">Stok menipis / perlu dibeli <span class="fw-normal text-secondary">(opsional)</span></label>
        <textarea class="form-control" id="stok_menipis" name="stok_menipis" rows="3" placeholder="mis. Susu full cream tinggal 2 karton"><?= e($d['stok_menipis']) ?></textarea></div>
      <div class="col-md-6"><label class="form-label fw-semibold" for="kendala">Kendala operasional <span class="fw-normal text-secondary">(wajib jika area bermasalah)</span></label>
        <textarea class="form-control" id="kendala" name="kendala" rows="3" placeholder="mis. Grinder macet pukul 14.00"><?= e($d['kendala']) ?></textarea></div>
    </div>

    <div class="mb-4">
      <label class="form-label fw-semibold" for="catatan">Catatan serah terima untuk shift berikutnya</label>
      <textarea class="form-control" id="catatan" name="catatan" rows="4" required minlength="20" placeholder="Tuliskan hal penting yang perlu diketahui staf shift berikutnya..."><?= e($d['catatan']) ?></textarea>
      <div class="d-flex justify-content-between"><small class="text-secondary ms-auto" id="hitungCatatan"></small></div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <a class="btn btn-outline-secondary" href="index.php">Nanti dulu</a>
      <button class="btn btn-brand" type="submit" id="btnKirimHandover"><i class="bi bi-send"></i> Kirim Handover</button>
    </div>
  </form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
