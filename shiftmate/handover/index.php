<?php
/* LOG HANDOVER: staf melihat kewajiban handover; head store memantau kepatuhan per tanggal */
require_once __DIR__ . '/../config/koneksi.php';
require_login();

$judul = 'Log Handover';
$aktif = 'handover';
$idSaya = (int)($_SESSION['id_staf'] ?? 0);
$hariIni = date('Y-m-d');

$tgl = $_GET['tgl'] ?? $hariIni;
if (!strtotime($tgl)) $tgl = $hariIni;
$idDivisi = (int)($_GET['divisi'] ?? 0);

$belumSaya = $idSaya ? handover_belum($konek, $idSaya) : [];
$riwayatSaya = $idSaya ? q($konek,
    "SELECT h.id_handover, h.tingkat_keramaian, h.kondisi_area, h.waktu_isi, h.catatan_head, j.tanggal, sm.nama_shift, sm.warna
     FROM handover h JOIN jadwal j ON h.id_jadwal = j.id_jadwal JOIN shift_master sm ON j.id_shift = sm.id_shift
     WHERE h.id_staf = ? ORDER BY j.tanggal DESC, sm.jam_mulai DESC LIMIT 15", 'i', [$idSaya]) : [];

if (is_manager()) {
    $daftarDivisi = q($konek, "SELECT * FROM divisi ORDER BY id_divisi");
    $sql = "SELECT j.id_jadwal, j.tanggal, s.nama_lengkap, s.foto, d.nama_divisi, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai,
                   h.id_handover, h.tingkat_keramaian, h.kondisi_area, h.kendala, h.waktu_isi, h.catatan_head
            FROM jadwal j JOIN staf s ON j.id_staf = s.id_staf JOIN divisi d ON s.id_divisi = d.id_divisi
            JOIN shift_master sm ON j.id_shift = sm.id_shift LEFT JOIN handover h ON h.id_jadwal = j.id_jadwal
            WHERE j.tanggal = ?" . ($idDivisi ? " AND s.id_divisi = ?" : "") . " ORDER BY sm.jam_mulai, d.id_divisi, s.nama_lengkap";
    $monitor = $idDivisi ? q($konek, $sql, 'si', [$tgl, $idDivisi]) : q($konek, $sql, 's', [$tgl]);
    $tunggak = q($konek,
        "SELECT s.nama_lengkap, d.nama_divisi, COUNT(*) AS jml
         FROM jadwal j JOIN staf s ON j.id_staf = s.id_staf JOIN divisi d ON s.id_divisi = d.id_divisi
         JOIN shift_master sm ON j.id_shift = sm.id_shift LEFT JOIN handover h ON h.id_jadwal = j.id_jadwal
         WHERE h.id_handover IS NULL AND j.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND CURDATE()
           AND TIMESTAMP(j.tanggal, sm.jam_selesai) < NOW()
         GROUP BY s.id_staf ORDER BY jml DESC, s.nama_lengkap");
    $terisi = 0; $belum = 0; $kendala = 0;
    foreach ($monitor as $m) {
        if ($m['id_handover']) { $terisi++; if ($m['kondisi_area'] !== 'Bersih & Rapi' || $m['kendala']) $kendala++; }
        elseif (handover_terlambat($m['tanggal'], $m['jam_selesai'])) $belum++;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<?php if ($idSaya): ?>
<div class="card mb-3 <?= $belumSaya ? 'border-danger' : '' ?>"><div class="card-body">
  <h2 class="h6 fw-bold mb-3"><i class="bi bi-journal-plus"></i> Handover yang Perlu Diisi</h2>
  <?php if (!$belumSaya): ?><p class="text-secondary mb-0"><i class="bi bi-check-circle text-success"></i> Tidak ada handover tertunda. Handover dibuka 60 menit sebelum shift berakhir.</p><?php endif; ?>
  <?php foreach ($belumSaya as $b): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
      <div><?= chip_shift($b['nama_shift'], $b['warna'], $b['jam_mulai'], $b['jam_selesai']) ?>
        <span class="ms-2"><?= e(hari_indo($b['tanggal'])) ?>, <?= tgl_indo($b['tanggal']) ?></span>
        <?= $b['terlambat'] ? '<span class="badge text-bg-danger ms-2">Tertunggak</span>' : '<span class="badge text-bg-warning ms-2">Dapat diisi</span>' ?></div>
      <a class="btn btn-sm btn-brand" href="isi.php?jadwal=<?= $b['id_jadwal'] ?>">Isi handover</a>
    </div>
  <?php endforeach; ?>
</div></div>
<?php endif; ?>

<?php if (is_manager()): ?>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-3"><label class="form-label small mb-1">Tanggal</label><input type="date" class="form-control" name="tgl" value="<?= e($tgl) ?>"></div>
    <div class="col-md-3"><label class="form-label small mb-1">Divisi</label>
      <select class="form-select" name="divisi"><option value="0">Semua</option>
        <?php foreach ($daftarDivisi as $d): ?><option value="<?= $d['id_divisi'] ?>" <?= $idDivisi === (int)$d['id_divisi'] ? 'selected' : '' ?>><?= e($d['nama_divisi']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><button class="btn btn-brand w-100">Tampilkan</button></div>
  </form>
</div></div>

<div class="row g-3 mb-3">
  <div class="col-4"><div class="card stat-card"><div class="card-body"><div class="text-secondary small">Shift terjadwal</div><div class="angka"><?= count($monitor) ?></div></div></div></div>
  <div class="col-4"><div class="card stat-card"><div class="card-body"><div class="text-secondary small">Handover terisi</div><div class="angka text-success"><?= $terisi ?></div></div></div></div>
  <div class="col-4"><div class="card stat-card"><div class="card-body"><div class="text-secondary small">Belum diisi (lewat jam)</div><div class="angka text-danger"><?= $belum ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <h2 class="h6 fw-bold mb-3">Log <?= hari_indo($tgl) ?>, <?= tgl_indo($tgl) ?> <?= $kendala ? '<span class="badge text-bg-warning ms-1">' . $kendala . ' perlu perhatian</span>' : '' ?></h2>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Staf</th><th>Shift</th><th>Keramaian</th><th>Status</th><th class="text-end">Detail</th></tr></thead>
        <tbody>
        <?php if (!$monitor): ?><tr><td colspan="5" class="text-center text-secondary py-3">Tidak ada jadwal pada tanggal ini.</td></tr><?php endif; ?>
        <?php foreach ($monitor as $m): ?>
          <tr>
            <td><div class="d-flex align-items-center gap-2"><?= avatar($m['nama_lengkap'], $m['foto']) ?><div><div class="fw-semibold"><?= e($m['nama_lengkap']) ?></div><div class="small text-secondary"><?= e($m['nama_divisi']) ?></div></div></div></td>
            <td><?= chip_shift($m['nama_shift'], $m['warna'], $m['jam_mulai'], $m['jam_selesai']) ?></td>
            <td><?= $m['id_handover'] ? e($m['tingkat_keramaian']) : '-' ?></td>
            <td>
              <?php if ($m['id_handover']): ?>
                <span class="badge text-bg-success">Terisi</span>
                <?php if ($m['kondisi_area'] !== 'Bersih & Rapi' || $m['kendala']): ?><span class="badge text-bg-warning">Ada catatan</span><?php endif; ?>
                <?= $m['catatan_head'] ? '<i class="bi bi-chat-left-text text-secondary" title="Sudah ditanggapi"></i>' : '' ?>
              <?php elseif (handover_terlambat($m['tanggal'], $m['jam_selesai'])): ?><span class="badge text-bg-danger">Belum diisi</span>
              <?php elseif (handover_terbuka($m['tanggal'], $m['jam_selesai'])): ?><span class="badge text-bg-info">Dapat diisi</span>
              <?php else: ?><span class="badge text-bg-secondary">Belum waktunya</span><?php endif; ?>
            </td>
            <td class="text-end"><?= $m['id_handover'] ? '<a class="btn btn-sm btn-outline-primary" href="lihat.php?id=' . $m['id_handover'] . '">Buka</a>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-exclamation-triangle text-danger"></i> Tertunggak 14 Hari Terakhir</h2>
      <?php if (!$tunggak): ?><p class="text-secondary mb-0">Semua handover terisi.</p><?php endif; ?>
      <?php foreach ($tunggak as $t): ?>
        <div class="d-flex justify-content-between border-bottom py-2"><div><div class="fw-semibold"><?= e($t['nama_lengkap']) ?></div><div class="small text-secondary"><?= e($t['nama_divisi']) ?></div></div>
          <span class="badge text-bg-danger align-self-center"><?= $t['jml'] ?> shift</span></div>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>
<?php endif; ?>

<?php if ($idSaya): ?>
<div class="card mt-3"><div class="card-body">
  <h2 class="h6 fw-bold mb-3">Riwayat Handover Saya</h2>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Tanggal</th><th>Shift</th><th>Keramaian</th><th>Kondisi Area</th><th>Tanggapan Head</th><th></th></tr></thead>
    <tbody>
    <?php if (!$riwayatSaya): ?><tr><td colspan="6" class="text-center text-secondary py-3">Belum ada handover.</td></tr><?php endif; ?>
    <?php foreach ($riwayatSaya as $r): ?>
      <tr><td><?= tgl_pendek($r['tanggal']) ?></td><td><?= chip_shift($r['nama_shift'], $r['warna']) ?></td><td><?= e($r['tingkat_keramaian']) ?></td><td><?= e($r['kondisi_area']) ?></td>
          <td><?= $r['catatan_head'] ? '<i class="bi bi-chat-left-text"></i> Ada' : '<span class="text-secondary">-</span>' ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="lihat.php?id=<?= $r['id_handover'] ?>">Lihat</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div></div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
