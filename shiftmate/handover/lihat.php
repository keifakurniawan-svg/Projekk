<?php
/* Detail handover. Head store / admin dapat memberi tanggapan. */
require_once __DIR__ . '/../config/koneksi.php';
require_login();

$idSaya = (int)($_SESSION['id_staf'] ?? 0);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$h = q1($konek,
    "SELECT h.*, j.tanggal, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai, s.nama_lengkap, s.foto, d.nama_divisi, rv.nama_lengkap AS peninjau
     FROM handover h JOIN jadwal j ON h.id_jadwal = j.id_jadwal JOIN shift_master sm ON j.id_shift = sm.id_shift
     JOIN staf s ON h.id_staf = s.id_staf JOIN divisi d ON s.id_divisi = d.id_divisi LEFT JOIN staf rv ON h.ditinjau_oleh = rv.id_staf
     WHERE h.id_handover = ?", 'i', [$id]);
if (!$h || (!is_manager() && (int)$h['id_staf'] !== $idSaya)) { flash('danger', 'Handover tidak ditemukan.'); redirect('handover/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_manager()) {
    csrf_check();
    $tanggapan = trim($_POST['catatan_head'] ?? '');
    $peninjau = $idSaya ?: null;
    ex($konek, "UPDATE handover SET catatan_head = ?, ditinjau_oleh = ?, waktu_tinjau = NOW() WHERE id_handover = ?", 'sii', [$tanggapan ?: null, $peninjau, $id]);
    flash('success', 'Tanggapan disimpan.');
    redirect('handover/lihat.php?id=' . $id);
}

$judul = 'Detail Handover';
$aktif = 'handover';
include __DIR__ . '/../includes/header.php';
function blok($judul, $isi) { return '<div class="mb-3"><div class="small text-uppercase text-secondary fw-semibold">' . $judul . '</div><div>' . ($isi !== null && $isi !== '' ? nl2br(e($isi)) : '<span class="text-secondary">-</span>') . '</div></div>'; }
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-3"><?= avatar($h['nama_lengkap'], $h['foto']) ?>
          <div><div class="fw-semibold"><?= e($h['nama_lengkap']) ?></div><div class="small text-secondary"><?= e($h['nama_divisi']) ?></div></div></div>
        <div class="text-end"><?= chip_shift($h['nama_shift'], $h['warna'], $h['jam_mulai'], $h['jam_selesai']) ?><div class="small text-secondary mt-1"><?= e(hari_indo($h['tanggal'])) ?>, <?= tgl_indo($h['tanggal']) ?></div></div>
      </div>
      <div class="row">
        <div class="col-md-6"><?= blok('Tingkat keramaian', $h['tingkat_keramaian']) ?></div>
        <div class="col-md-6"><?= blok('Kondisi area kerja', $h['kondisi_area']) ?></div>
      </div>
      <div class="mb-3"><div class="small text-uppercase text-secondary fw-semibold">Checklist penutupan</div>
        <?php foreach (['cek_kebersihan' => 'Area dibersihkan', 'cek_peralatan' => 'Peralatan disimpan', 'cek_stok' => 'Stok & kas dicek'] as $k => $l): ?>
          <span class="badge text-bg-<?= $h[$k] ? 'success' : 'secondary' ?> me-1"><i class="bi bi-<?= $h[$k] ? 'check' : 'x' ?>"></i> <?= $l ?></span>
        <?php endforeach; ?></div>
      <?= blok('Stok menipis', $h['stok_menipis']) ?>
      <?= blok('Kendala operasional', $h['kendala']) ?>
      <?= blok('Catatan serah terima', $h['catatan']) ?>
      <div class="small text-secondary">Diisi pada <?= waktu_indo($h['waktu_isi']) ?></div>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-chat-left-text"></i> Tanggapan Head Store</h2>
      <?php if (is_manager()): ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
          <textarea class="form-control mb-2" name="catatan_head" rows="5" placeholder="Umpan balik atau tindak lanjut..."><?= e($h['catatan_head']) ?></textarea>
          <button class="btn btn-brand w-100">Simpan tanggapan</button>
        </form>
        <?php if ($h['waktu_tinjau']): ?><div class="small text-secondary mt-2">Terakhir ditinjau <?= waktu_indo($h['waktu_tinjau']) ?><?= $h['peninjau'] ? ' oleh ' . e($h['peninjau']) : '' ?></div><?php endif; ?>
      <?php else: ?>
        <?= $h['catatan_head'] ? '<p class="mb-0">' . nl2br(e($h['catatan_head'])) . '</p>' : '<p class="text-secondary mb-0">Belum ada tanggapan.</p>' ?>
      <?php endif; ?>
    </div></div>
    <a class="btn btn-outline-secondary mt-3" href="index.php"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
