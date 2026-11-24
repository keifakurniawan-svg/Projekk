<?php
/* Jadwal mingguan (tampilan baca). Menggabungkan staf, divisi, jadwal, dan master shift dengan JOIN. */
require_once __DIR__ . '/../config/koneksi.php';
require_login();

$judul = 'Jadwal Shift';
$aktif = 'jadwal';
$hariIni = date('Y-m-d');
$senin   = senin_minggu($_GET['minggu'] ?? $hariIni);
$minggu  = [];
for ($i = 0; $i < 7; $i++) $minggu[] = tambah_hari($senin, $i);
$idDivisi = (int)($_GET['divisi'] ?? 0);
$idSaya   = (int)($_SESSION['id_staf'] ?? 0);

$daftarDivisi = mysqli_fetch_all(mysqli_query($konek, "SELECT * FROM divisi ORDER BY id_divisi"), MYSQLI_ASSOC);

$sql = "SELECT s.id_staf, s.nama_lengkap, s.foto, d.nama_divisi FROM staf s JOIN divisi d ON s.id_divisi = d.id_divisi WHERE s.status = 'Aktif'";
if ($idDivisi) $sql .= " AND s.id_divisi = ?";
$sql .= " ORDER BY d.id_divisi, s.nama_lengkap";
$st = mysqli_prepare($konek, $sql);
if ($idDivisi) mysqli_stmt_bind_param($st, 'i', $idDivisi);
mysqli_stmt_execute($st);
$daftarStaf = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);

$akhir = $minggu[6];
$st = mysqli_prepare($konek,
    "SELECT j.id_staf, j.tanggal, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai
     FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift WHERE j.tanggal BETWEEN ? AND ?");
mysqli_stmt_bind_param($st, 'ss', $senin, $akhir);
mysqli_stmt_execute($st);
$peta = [];
$res = mysqli_stmt_get_result($st);
while ($r = mysqli_fetch_assoc($res)) $peta[$r['id_staf']][$r['tanggal']] = $r;

$shiftList = mysqli_query($konek, "SELECT * FROM shift_master ORDER BY jam_mulai");

function urlJadwal($ubah) { $p = array_merge($_GET, $ubah); return '?' . http_build_query($p); }

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link <?= !$idDivisi ? 'active' : '' ?>" href="<?= urlJadwal(['divisi' => 0]) ?>">Semua</a></li>
    <?php foreach ($daftarDivisi as $d): ?>
      <li class="nav-item"><a class="nav-link <?= $idDivisi === (int)$d['id_divisi'] ? 'active' : '' ?>" href="<?= urlJadwal(['divisi' => $d['id_divisi']]) ?>"><?= e($d['nama_divisi']) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="btn-group">
    <a class="btn btn-outline-secondary" href="<?= urlJadwal(['minggu' => tambah_hari($senin, -7)]) ?>"><i class="bi bi-chevron-left"></i></a>
    <a class="btn btn-outline-secondary" href="<?= urlJadwal(['minggu' => $hariIni]) ?>">Minggu ini</a>
    <a class="btn btn-outline-secondary" href="<?= urlJadwal(['minggu' => tambah_hari($senin, 7)]) ?>"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>

<div class="card"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h2 class="h6 fw-bold mb-0">Minggu <?= tgl_indo($senin) ?> &ndash; <?= tgl_indo($akhir) ?></h2>
    <div class="d-flex gap-2 flex-wrap">
      <?php while ($sh = mysqli_fetch_assoc($shiftList)) echo chip_shift($sh['nama_shift'], $sh['warna'], $sh['jam_mulai'], $sh['jam_selesai']); ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table grid-jadwal align-middle mb-0">
      <thead><tr>
        <th>Staf</th>
        <?php foreach ($minggu as $tgl): ?><th class="<?= $tgl === $hariIni ? 'hari-ini' : '' ?>"><?= hari_indo($tgl) ?><div class="fw-normal small"><?= date('j', strtotime($tgl)) . ' ' . NAMA_BULAN[(int)date('n', strtotime($tgl))] ?></div></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php $divisiSebelum = ''; foreach ($daftarStaf as $s):
            if (!$idDivisi && $s['nama_divisi'] !== $divisiSebelum): $divisiSebelum = $s['nama_divisi']; ?>
        <tr class="table-light"><td colspan="8" class="small fw-bold text-uppercase text-secondary"><?= e($divisiSebelum) ?></td></tr>
      <?php endif; ?>
        <tr class="<?= (int)$s['id_staf'] === $idSaya ? 'baris-saya' : '' ?>">
          <td><div class="d-flex align-items-center gap-2"><?= avatar($s['nama_lengkap'], $s['foto']) ?>
            <div><div class="fw-semibold"><?= e($s['nama_lengkap']) ?><?= (int)$s['id_staf'] === $idSaya ? ' <span class="badge text-bg-warning">Saya</span>' : '' ?></div>
            <div class="small text-secondary"><?= e($s['nama_divisi']) ?></div></div></div></td>
          <?php foreach ($minggu as $tgl): $c = $peta[$s['id_staf']][$tgl] ?? null; ?>
            <td class="<?= $tgl === $hariIni ? 'hari-ini' : '' ?>">
              <?= $c ? chip_shift($c['nama_shift'], $c['warna']) . '<div class="small text-secondary">' . jam($c['jam_mulai']) . '-' . jam($c['jam_selesai']) . '</div>' : '<span class="chip-libur">Libur</span>' ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$daftarStaf): ?><tr><td colspan="8" class="text-center text-secondary py-4">Belum ada staf.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (is_manager()): ?>
    <div class="mt-3"><a class="btn btn-brand" href="atur.php?minggu=<?= $senin ?><?= $idDivisi ? '&divisi=' . $idDivisi : '' ?>"><i class="bi bi-pencil-square"></i> Susun jadwal minggu ini</a></div>
  <?php endif; ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
