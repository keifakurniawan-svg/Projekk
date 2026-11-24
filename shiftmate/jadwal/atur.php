<?php
/* DYNAMIC SHIFT SCHEDULING: editor jadwal mingguan per divisi (head store / admin) */
require_once __DIR__ . '/../config/koneksi.php';
require_role(['admin', 'head_store']);

$hariIni = date('Y-m-d');
$senin   = senin_minggu($_GET['minggu'] ?? $_POST['minggu'] ?? $hariIni);
$minggu  = [];
for ($i = 0; $i < 7; $i++) $minggu[] = tambah_hari($senin, $i);

$daftarDivisi = mysqli_fetch_all(mysqli_query($konek, "SELECT * FROM divisi ORDER BY id_divisi"), MYSQLI_ASSOC);
$idDivisi = (int)($_GET['divisi'] ?? $_POST['divisi'] ?? ($daftarDivisi[0]['id_divisi'] ?? 0));
$divisi = null;
foreach ($daftarDivisi as $d) if ((int)$d['id_divisi'] === $idDivisi) $divisi = $d;
if (!$divisi) { $divisi = $daftarDivisi[0]; $idDivisi = (int)$divisi['id_divisi']; }

$shifts = mysqli_fetch_all(mysqli_query($konek, "SELECT * FROM shift_master ORDER BY jam_mulai"), MYSQLI_ASSOC);
$idShiftValid = array_column($shifts, 'id_shift');

$st = mysqli_prepare($konek, "SELECT id_staf, nama_lengkap, foto FROM staf WHERE status = 'Aktif' AND id_divisi = ? ORDER BY nama_lengkap");
mysqli_stmt_bind_param($st, 'i', $idDivisi);
mysqli_stmt_execute($st);
$daftarStaf = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
$idStafValid = array_map('intval', array_column($daftarStaf, 'id_staf'));

$kembali = 'jadwal/atur.php?minggu=' . $senin . '&divisi=' . $idDivisi;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $data = $_POST['jadwal'] ?? [];
        $hapus = mysqli_prepare($konek, "DELETE FROM jadwal WHERE id_staf = ? AND tanggal = ?");
        $isi   = mysqli_prepare($konek, "INSERT INTO jadwal (id_staf, id_shift, tanggal) VALUES (?, ?, ?)
                                         ON DUPLICATE KEY UPDATE id_shift = VALUES(id_shift)");
        mysqli_begin_transaction($konek);
        try {
            $diubah = 0;
            foreach ($idStafValid as $idS) {
                foreach ($minggu as $tgl) {
                    if ($tgl < $hariIni) continue;                       // jadwal masa lalu dikunci
                    $nilai = $data[$idS][$tgl] ?? '';
                    if ($nilai === '') {
                        mysqli_stmt_bind_param($hapus, 'is', $idS, $tgl);
                        mysqli_stmt_execute($hapus);
                    } elseif (in_array((int)$nilai, array_map('intval', $idShiftValid), true)) {
                        $idSh = (int)$nilai;
                        mysqli_stmt_bind_param($isi, 'iis', $idS, $idSh, $tgl);
                        mysqli_stmt_execute($isi);
                    }
                    $diubah++;
                }
            }
            mysqli_commit($konek);
            flash('success', 'Jadwal ' . $divisi['nama_divisi'] . ' minggu ' . tgl_indo($senin) . ' berhasil disimpan.');
        } catch (mysqli_sql_exception $ex) {
            mysqli_rollback($konek);
            flash('danger', 'Jadwal gagal disimpan. Silakan coba lagi.');
        }
    } elseif ($aksi === 'salin') {
        // Salin jadwal minggu sebelumnya (hanya untuk tanggal yang belum lewat)
        $sebelum = tambah_hari($senin, -7);
        $akhirSebelum = tambah_hari($senin, -1);
        $q = mysqli_prepare($konek,
            "INSERT INTO jadwal (id_staf, id_shift, tanggal)
             SELECT j.id_staf, j.id_shift, DATE_ADD(j.tanggal, INTERVAL 7 DAY)
             FROM jadwal j JOIN staf s ON j.id_staf = s.id_staf
             WHERE s.id_divisi = ? AND s.status = 'Aktif' AND j.tanggal BETWEEN ? AND ?
               AND DATE_ADD(j.tanggal, INTERVAL 7 DAY) >= CURDATE()
             ON DUPLICATE KEY UPDATE id_shift = VALUES(id_shift)");
        mysqli_stmt_bind_param($q, 'iss', $idDivisi, $sebelum, $akhirSebelum);
        mysqli_stmt_execute($q);
        flash('success', 'Jadwal minggu sebelumnya disalin ke minggu ini (' . mysqli_stmt_affected_rows($q) . ' entri).');
    }
    redirect($kembali);
}

// jadwal yang sudah ada
$akhir = $minggu[6];
$st = mysqli_prepare($konek,
    "SELECT j.id_staf, j.tanggal, j.id_shift FROM jadwal j JOIN staf s ON j.id_staf = s.id_staf
     WHERE s.id_divisi = ? AND j.tanggal BETWEEN ? AND ?");
mysqli_stmt_bind_param($st, 'iss', $idDivisi, $senin, $akhir);
mysqli_stmt_execute($st);
$peta = [];
$res = mysqli_stmt_get_result($st);
while ($r = mysqli_fetch_assoc($res)) $peta[$r['id_staf']][$r['tanggal']] = $r['id_shift'];

$dataShiftJs = array_map(fn($s) => ['id' => (string)$s['id_shift'], 'nama' => $s['nama_shift'], 'warna' => $s['warna'], 'wajib' => (int)$s['wajib']], $shifts);

$judul = 'Susun Jadwal Shift';
$aktif = 'atur';
function urlAtur($ubah) { return '?' . http_build_query(array_merge(['minggu' => $GLOBALS['senin'], 'divisi' => $GLOBALS['idDivisi']], $ubah)); }
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <ul class="nav nav-pills">
    <?php foreach ($daftarDivisi as $d): ?>
      <li class="nav-item"><a class="nav-link <?= $idDivisi === (int)$d['id_divisi'] ? 'active' : '' ?>" href="<?= urlAtur(['divisi' => $d['id_divisi']]) ?>"><?= e($d['nama_divisi']) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="btn-group">
    <a class="btn btn-outline-secondary" href="<?= urlAtur(['minggu' => tambah_hari($senin, -7)]) ?>"><i class="bi bi-chevron-left"></i></a>
    <a class="btn btn-outline-secondary" href="<?= urlAtur(['minggu' => $hariIni]) ?>">Minggu ini</a>
    <a class="btn btn-outline-secondary" href="<?= urlAtur(['minggu' => tambah_hari($senin, 7)]) ?>"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>

<div class="card"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h2 class="h6 fw-bold mb-1"><?= e($divisi['nama_divisi']) ?> &middot; <?= tgl_indo($senin) ?> &ndash; <?= tgl_indo($akhir) ?></h2>
      <span class="small text-secondary">Minimum <?= (int)$divisi['min_per_shift'] ?> staf pada shift wajib (<?= e(implode(', ', array_column(array_filter($shifts, fn($s) => $s['wajib']), 'nama_shift'))) ?>).</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span id="ringkasanCov" class="badge text-bg-secondary">...</span>
      <form method="post" data-confirm="Salin jadwal minggu sebelumnya ke minggu ini? Jadwal yang sudah ada pada tanggal yang sama akan ditimpa.">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="salin"><input type="hidden" name="minggu" value="<?= $senin ?>"><input type="hidden" name="divisi" value="<?= $idDivisi ?>">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-files"></i> Salin minggu lalu</button>
      </form>
    </div>
  </div>

  <form method="post" id="formJadwal">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="minggu" value="<?= $senin ?>"><input type="hidden" name="divisi" value="<?= $idDivisi ?>">
    <div class="table-responsive">
      <table class="table grid-jadwal align-middle mb-0" id="editorJadwal" data-shifts="<?= e(json_encode($dataShiftJs)) ?>" data-min="<?= (int)$divisi['min_per_shift'] ?>">
        <thead><tr>
          <th>Staf <span class="fw-normal text-lowercase">/ isi cepat</span></th>
          <?php foreach ($minggu as $i => $tgl): ?>
            <th data-hari="<?= $i ?>" class="<?= $tgl === $hariIni ? 'hari-ini' : '' ?>"><?= hari_indo($tgl) ?><div class="fw-normal small"><?= date('j', strtotime($tgl)) . ' ' . NAMA_BULAN[(int)date('n', strtotime($tgl))] ?></div></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($daftarStaf as $s): ?>
          <tr>
            <td><div class="d-flex align-items-center gap-2"><?= avatar($s['nama_lengkap'], $s['foto']) ?>
              <div><div class="fw-semibold"><?= e($s['nama_lengkap']) ?></div>
              <select class="form-select form-select-sm mt-1" data-isi-baris aria-label="Isi cepat">
                <option value="__">Isi 1 minggu...</option><option value="">Libur semua</option>
                <?php foreach ($shifts as $sh): ?><option value="<?= $sh['id_shift'] ?>"><?= e($sh['nama_shift']) ?> semua</option><?php endforeach; ?>
              </select></div></div></td>
            <?php foreach ($minggu as $i => $tgl): $lalu = $tgl < $hariIni; $cur = $peta[$s['id_staf']][$tgl] ?? ''; ?>
              <td class="<?= $tgl === $hariIni ? 'hari-ini' : '' ?>">
                <select class="form-select form-select-sm sel-shift" data-hari="<?= $i ?>" name="jadwal[<?= $s['id_staf'] ?>][<?= $tgl ?>]" <?= $lalu ? 'disabled title="Tanggal sudah lewat"' : '' ?>>
                  <option value="">Libur</option>
                  <?php foreach ($shifts as $sh): ?>
                    <option value="<?= $sh['id_shift'] ?>" <?= (string)$cur === (string)$sh['id_shift'] ? 'selected' : '' ?>><?= e($sh['nama_shift']) ?> (<?= jam($sh['jam_mulai']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr id="barisCov" class="cov"><td class="fw-semibold text-secondary">Cakupan per hari</td><?php foreach ($minggu as $tgl): ?><td></td><?php endforeach; ?></tr>
        </tfoot>
      </table>
    </div>
    <?php if (!$daftarStaf): ?><p class="text-center text-secondary my-4">Divisi ini belum memiliki staf aktif.</p><?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <span class="small text-secondary"><i class="bi bi-lock"></i> Tanggal yang sudah lewat tidak dapat diubah.</span>
      <button class="btn btn-brand" type="submit"><i class="bi bi-check-lg"></i> Simpan Jadwal</button>
    </div>
  </form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
