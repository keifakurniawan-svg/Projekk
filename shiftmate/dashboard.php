<?php
/* Dashboard: ringkasan operasional hari ini (query agregat + JOIN) */
require_once __DIR__ . '/config/koneksi.php';
require_login();

$judul = 'Dashboard';
$aktif = 'dashboard';
$idSaya  = (int)($_SESSION['id_staf'] ?? 0);
$hariIni = date('Y-m-d');
function angka($konek, $sql, $tipe = '', $param = []) { return (int)q1($konek, $sql, $tipe, $param)['n']; }

include __DIR__ . '/includes/header.php';

if (is_manager()):
    $totalStaf  = angka($konek, "SELECT COUNT(*) AS n FROM staf WHERE status = 'Aktif'");
    $bertugas   = angka($konek, "SELECT COUNT(*) AS n FROM jadwal WHERE tanggal = ?", 's', [$hariIni]);
    $tukarMenunggu = angka($konek, "SELECT COUNT(*) AS n FROM tukar_shift WHERE status = 'Menunggu Head Store'");
    $tunggak    = angka($konek, "SELECT COUNT(*) AS n FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift LEFT JOIN handover h ON h.id_jadwal = j.id_jadwal
                                 WHERE h.id_handover IS NULL AND j.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND CURDATE() AND TIMESTAMP(j.tanggal, sm.jam_selesai) < NOW()");

    $roster = q($konek,
        "SELECT sm.id_shift, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai, s.nama_lengkap, s.foto, d.nama_divisi
         FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift JOIN staf s ON j.id_staf = s.id_staf JOIN divisi d ON s.id_divisi = d.id_divisi
         WHERE j.tanggal = ? ORDER BY sm.jam_mulai, d.id_divisi, s.nama_lengkap", 's', [$hariIni]);
    $perShift = [];
    foreach ($roster as $r) $perShift[$r['id_shift']]['info'] = $r + [];
    foreach ($roster as $r) $perShift[$r['id_shift']]['staf'][] = $r;

    // peringatan kekurangan staf 7 hari ke depan pada shift wajib
    $divisiMin = q($konek, "SELECT * FROM divisi WHERE min_per_shift > 0 ORDER BY id_divisi");
    $shiftWajib = q($konek, "SELECT * FROM shift_master WHERE wajib = 1 ORDER BY jam_mulai");
    $cacah = [];
    foreach (q($konek, "SELECT s.id_divisi, j.tanggal, j.id_shift, COUNT(*) AS n FROM jadwal j JOIN staf s ON j.id_staf = s.id_staf
                        WHERE s.status = 'Aktif' AND j.tanggal BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY) GROUP BY s.id_divisi, j.tanggal, j.id_shift", 'ss', [$hariIni, $hariIni]) as $c)
        $cacah[$c['id_divisi']][$c['tanggal']][$c['id_shift']] = (int)$c['n'];
    $peringatan = [];
    for ($i = 0; $i < 7; $i++) {
        $tg = tambah_hari($hariIni, $i);
        foreach ($divisiMin as $dv) foreach ($shiftWajib as $sh) {
            $n = $cacah[$dv['id_divisi']][$tg][$sh['id_shift']] ?? 0;
            if ($n < (int)$dv['min_per_shift']) $peringatan[] = ['tgl' => $tg, 'divisi' => $dv['nama_divisi'], 'shift' => $sh['nama_shift'], 'n' => $n, 'min' => (int)$dv['min_per_shift']];
        }
    }
    $perhatian = q($konek,
        "SELECT h.id_handover, h.kondisi_area, h.kendala, h.stok_menipis, j.tanggal, s.nama_lengkap, sm.nama_shift, sm.warna
         FROM handover h JOIN jadwal j ON h.id_jadwal = j.id_jadwal JOIN staf s ON h.id_staf = s.id_staf JOIN shift_master sm ON j.id_shift = sm.id_shift
         WHERE h.catatan_head IS NULL AND (h.kondisi_area <> 'Bersih & Rapi' OR h.kendala IS NOT NULL OR h.stok_menipis IS NOT NULL)
         ORDER BY j.tanggal DESC, h.waktu_isi DESC LIMIT 5");
?>
<div class="row g-3 mb-3">
  <?php foreach ([['Staf Aktif', $totalStaf, 'bi-people', ''], ['Bertugas Hari Ini', $bertugas, 'bi-person-check', ''],
                  ['Tukar Shift Menunggu', $tukarMenunggu, 'bi-arrow-left-right', 'tukar/index.php'], ['Handover Tertunggak', $tunggak, 'bi-journal-x', 'handover/index.php']] as $k): ?>
  <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="text-secondary small"><?= $k[0] ?></div><div class="angka"><?= $k[1] ?></div><?php if ($k[3]): ?><a class="small text-brand" href="<?= $k[3] ?>">Lihat</a><?php endif; ?></div>
    <div class="ikon"><i class="bi <?= $k[2] ?>"></i></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-body">
      <h2 class="h6 fw-bold mb-3">Roster Hari Ini &middot; <?= hari_indo($hariIni) ?>, <?= tgl_indo($hariIni) ?></h2>
      <?php if (!$perShift): ?><p class="text-secondary">Belum ada jadwal hari ini. <a href="jadwal/atur.php">Susun jadwal</a>.</p><?php endif; ?>
      <?php foreach ($perShift as $blok): $i = $blok['info']; ?>
        <div class="mb-3">
          <div class="mb-2"><?= chip_shift($i['nama_shift'], $i['warna'], $i['jam_mulai'], $i['jam_selesai']) ?> <span class="small text-secondary ms-1"><?= count($blok['staf']) ?> staf</span></div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($blok['staf'] as $s): ?>
              <div class="d-flex align-items-center gap-2 border rounded-pill pe-3 p-1"><?= avatar($s['nama_lengkap'], $s['foto']) ?><div class="lh-sm"><div class="small fw-semibold"><?= e($s['nama_lengkap']) ?></div><div class="text-secondary" style="font-size:.7rem"><?= e($s['nama_divisi']) ?></div></div></div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3 <?= $peringatan ? 'border-danger' : '' ?>"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-exclamation-triangle <?= $peringatan ? 'text-danger' : 'text-success' ?>"></i> Cakupan Staf 7 Hari</h2>
      <?php if (!$peringatan): ?><p class="text-secondary mb-0">Semua shift wajib terpenuhi minggu ini.</p><?php endif; ?>
      <?php foreach (array_slice($peringatan, 0, 6) as $p): ?>
        <div class="d-flex justify-content-between border-bottom py-1 small"><span><?= tgl_pendek($p['tgl']) ?> &middot; <?= e($p['divisi']) ?> &middot; <?= e($p['shift']) ?></span><span class="text-danger fw-semibold"><?= $p['n'] ?>/<?= $p['min'] ?></span></div>
      <?php endforeach; ?>
      <?php if (count($peringatan) > 6): ?><div class="small text-secondary mt-1">dan <?= count($peringatan) - 6 ?> lainnya</div><?php endif; ?>
      <?php if ($peringatan): ?><a class="btn btn-sm btn-outline-secondary mt-3" href="jadwal/atur.php">Atur jadwal</a><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body">
      <h2 class="h6 fw-bold mb-3">Handover Perlu Perhatian</h2>
      <?php if (!$perhatian): ?><p class="text-secondary mb-0">Tidak ada catatan yang menunggu tanggapan.</p><?php endif; ?>
      <?php foreach ($perhatian as $h): ?>
        <a class="d-block text-decoration-none text-reset border-bottom py-2" href="handover/lihat.php?id=<?= $h['id_handover'] ?>">
          <div class="d-flex justify-content-between"><span class="fw-semibold"><?= e($h['nama_lengkap']) ?></span><?= chip_shift($h['nama_shift'], $h['warna']) ?></div>
          <div class="small text-secondary"><?= tgl_pendek($h['tanggal']) ?> &middot; <?= e($h['kendala'] ?: ($h['stok_menipis'] ?: $h['kondisi_area'])) ?></div></a>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>

<?php else:
    $hari = q1($konek, "SELECT j.id_jadwal, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift WHERE j.id_staf = ? AND j.tanggal = ?", 'is', [$idSaya, $hariIni]);
    $depan = q($konek, "SELECT j.tanggal, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift
                        WHERE j.id_staf = ? AND j.tanggal > ? ORDER BY j.tanggal LIMIT 7", 'is', [$idSaya, $hariIni]);
    $masuk = angka($konek, "SELECT COUNT(*) AS n FROM tukar_shift WHERE id_tujuan = ? AND status = 'Menunggu Rekan'", 'i', [$idSaya]);
    $belum = handover_belum($konek, $idSaya);
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shift-hero mb-3" style="--c:<?= $hari ? e($hari['warna']) : '#94a3b8' ?>"><div class="card-body">
      <div class="small text-secondary"><?= hari_indo($hariIni) ?>, <?= tgl_indo($hariIni) ?></div>
      <?php if ($hari): ?>
        <div class="d-flex flex-wrap align-items-center gap-3 mt-1"><div class="display-6 fw-bold">Shift <?= e($hari['nama_shift']) ?></div><?= chip_shift($hari['nama_shift'], $hari['warna'], $hari['jam_mulai'], $hari['jam_selesai']) ?></div>
        <div class="text-brand mt-1" id="countdown" data-mulai="<?= $hariIni . ' ' . $hari['jam_mulai'] ?>" data-selesai="<?= $hariIni . ' ' . $hari['jam_selesai'] ?>"></div>
      <?php else: ?><div class="display-6 fw-bold mt-1">Hari ini libur</div><div class="text-secondary">Nikmati waktu istirahatmu.</div><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body">
      <h2 class="h6 fw-bold mb-3">Jadwal Mendatang</h2>
      <?php if (!$depan): ?><p class="text-secondary mb-0">Belum ada jadwal mendatang.</p><?php endif; ?>
      <?php foreach ($depan as $d): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><?= e(hari_indo($d['tanggal'])) ?>, <?= tgl_indo($d['tanggal']) ?></div>
          <div><?= chip_shift($d['nama_shift'], $d['warna'], $d['jam_mulai'], $d['jam_selesai']) ?></div></div>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary mt-3" href="jadwal/index.php">Lihat jadwal tim</a>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3 <?= $belum ? 'border-warning' : '' ?>"><div class="card-body">
      <h2 class="h6 fw-bold mb-2"><i class="bi bi-journal-text"></i> Handover</h2>
      <?php if ($belum): ?><p class="mb-2"><?= count($belum) ?> handover menunggu diisi.</p><a class="btn btn-brand btn-sm" href="handover/index.php">Isi handover</a>
      <?php else: ?><p class="text-secondary small mb-0"><i class="bi bi-check-circle text-success"></i> Semua handover terisi. Form terbuka 60 menit sebelum shift berakhir.</p><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body d-flex justify-content-between align-items-center">
      <div><h2 class="h6 fw-bold mb-1"><i class="bi bi-arrow-left-right"></i> Tukar Shift</h2><div class="small text-secondary"><?= $masuk ? $masuk . ' ajakan menunggu tanggapan Anda' : 'Tidak ada ajakan tukar shift' ?></div></div>
      <a class="btn btn-outline-secondary btn-sm" href="tukar/index.php"><?= $masuk ? 'Tanggapi' : 'Ajukan' ?></a>
    </div></div>
  </div>
</div>
<?php endif;
include __DIR__ . '/includes/footer.php'; ?>
