<?php
/* SHIFT SWAP: staf mengajukan -> rekan menerima -> head store menyetujui -> jadwal ditukar otomatis */
require_once __DIR__ . '/../config/koneksi.php';
require_login();

$idSaya  = (int)($_SESSION['id_staf'] ?? 0);
$hariIni = date('Y-m-d');
const AKTIF = "('Menunggu Rekan','Menunggu Head Store')";

/* Validasi aturan tukar shift. Mengembalikan [pesanError|null, barisPengaju, barisTujuan] */
function validasi_tukar($konek, $jp, $jt) {
    $sql = "SELECT j.id_jadwal, j.id_staf, j.id_shift, j.tanggal, s.id_divisi FROM jadwal j JOIN staf s ON j.id_staf = s.id_staf WHERE j.id_jadwal = ?";
    $p = q1($konek, $sql, 'i', [$jp]);
    $t = q1($konek, $sql, 'i', [$jt]);
    if (!$p || !$t)                          return ['Jadwal tidak ditemukan atau sudah berubah.', null, null];
    if ((int)$p['id_staf'] === (int)$t['id_staf']) return ['Tidak dapat menukar shift dengan diri sendiri.', null, null];
    if ((int)$p['id_divisi'] !== (int)$t['id_divisi']) return ['Tukar shift hanya boleh dengan rekan satu divisi.', null, null];
    $hari = date('Y-m-d');
    if ($p['tanggal'] <= $hari || $t['tanggal'] <= $hari) return ['Tukar shift hanya untuk jadwal mulai besok (H+1).', null, null];
    if ($p['tanggal'] === $t['tanggal']) {
        if ((int)$p['id_shift'] === (int)$t['id_shift']) return ['Kedua staf sudah memiliki shift yang sama pada hari itu.', null, null];
    } else {
        if (q1($konek, "SELECT 1 FROM jadwal WHERE id_staf = ? AND tanggal = ?", 'is', [$t['id_staf'], $p['tanggal']]))
            return ['Rekan sudah memiliki shift pada tanggal ' . tgl_indo($p['tanggal']) . '.', null, null];
        if (q1($konek, "SELECT 1 FROM jadwal WHERE id_staf = ? AND tanggal = ?", 'is', [$p['id_staf'], $t['tanggal']]))
            return ['Anda sudah memiliki shift pada tanggal ' . tgl_indo($t['tanggal']) . '.', null, null];
    }
    return [null, $p, $t];
}
function jadwal_dipakai_tukar($konek, $idJadwal) {
    return (bool)q1($konek, "SELECT 1 FROM tukar_shift WHERE status IN " . AKTIF . " AND (id_jadwal_pengaju = ? OR id_jadwal_tujuan = ?)", 'ii', [$idJadwal, $idJadwal]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $idT  = (int)($_POST['id'] ?? 0);

    if ($aksi === 'ajukan' && $idSaya) {
        $jp = (int)($_POST['jadwal_saya'] ?? 0);
        $jt = (int)($_POST['jadwal_tujuan'] ?? 0);
        $alasan = trim($_POST['alasan'] ?? '');
        [$err, $p, $t] = validasi_tukar($konek, $jp, $jt);
        if (!$err && (int)$p['id_staf'] !== $idSaya) $err = 'Jadwal yang dipilih bukan milik Anda.';
        if (!$err && strlen($alasan) < 5) $err = 'Alasan minimal 5 karakter.';
        if (!$err && (jadwal_dipakai_tukar($konek, $jp) || jadwal_dipakai_tukar($konek, $jt))) $err = 'Salah satu shift sedang dalam proses pengajuan tukar lain.';
        if (!$err && handover_tunggak($konek, $idSaya)) $err = 'Selesaikan handover yang tertunggak sebelum mengajukan tukar shift.';
        if ($err) { flash('danger', $err); }
        else {
            ex($konek, "INSERT INTO tukar_shift (id_jadwal_pengaju, id_jadwal_tujuan, id_pengaju, id_tujuan, tgl_p, id_shift_p, tgl_t, id_shift_t, alasan, tgl_pengajuan)
                        VALUES (?,?,?,?,?,?,?,?,?, NOW())",
               'iiiisisis', [$jp, $jt, $p['id_staf'], $t['id_staf'], $p['tanggal'], $p['id_shift'], $t['tanggal'], $t['id_shift'], $alasan]);
            flash('success', 'Pengajuan tukar shift terkirim. Menunggu persetujuan rekan.');
        }
    } elseif (in_array($aksi, ['terima', 'tolak_rekan'], true) && $idSaya) {
        $baru = $aksi === 'terima' ? 'Menunggu Head Store' : 'Ditolak';
        $n = ex($konek, "UPDATE tukar_shift SET status = ?, tgl_keputusan = IF(? = 'Ditolak', NOW(), NULL)
                         WHERE id_tukar = ? AND id_tujuan = ? AND status = 'Menunggu Rekan'", 'ssii', [$baru, $baru, $idT, $idSaya]);
        flash($n ? 'success' : 'danger', $n ? ($aksi === 'terima' ? 'Anda menyetujui. Pengajuan diteruskan ke head store.' : 'Pengajuan ditolak.') : 'Pengajuan tidak dapat diproses.');
    } elseif ($aksi === 'batal' && $idSaya) {
        $n = ex($konek, "UPDATE tukar_shift SET status = 'Dibatalkan', tgl_keputusan = NOW()
                         WHERE id_tukar = ? AND id_pengaju = ? AND status IN " . AKTIF, 'ii', [$idT, $idSaya]);
        flash($n ? 'success' : 'danger', $n ? 'Pengajuan dibatalkan.' : 'Pengajuan tidak dapat dibatalkan.');
    } elseif (in_array($aksi, ['setuju', 'tolak'], true) && is_manager()) {
        $tk = q1($konek, "SELECT * FROM tukar_shift WHERE id_tukar = ? AND status = 'Menunggu Head Store'", 'i', [$idT]);
        $catatan = trim($_POST['catatan_head'] ?? '');
        if (!$tk) { flash('danger', 'Pengajuan tidak ditemukan atau sudah diproses.'); }
        elseif ($aksi === 'tolak') {
            ex($konek, "UPDATE tukar_shift SET status = 'Ditolak', catatan_head = ?, tgl_keputusan = NOW() WHERE id_tukar = ?", 'si', [$catatan ?: null, $idT]);
            flash('success', 'Pengajuan tukar shift ditolak.');
        } else {
            [$err, $p, $t] = validasi_tukar($konek, $tk['id_jadwal_pengaju'], $tk['id_jadwal_tujuan']);
            // jadwal harus masih sama dengan saat diajukan
            if (!$err && ((int)$p['id_staf'] !== (int)$tk['id_pengaju'] || (int)$t['id_staf'] !== (int)$tk['id_tujuan'] || $p['tanggal'] !== $tk['tgl_p'] || $t['tanggal'] !== $tk['tgl_t']
                || (int)$p['id_shift'] !== (int)$tk['id_shift_p'] || (int)$t['id_shift'] !== (int)$tk['id_shift_t'])) $err = 'Jadwal telah berubah sejak diajukan. Tolak pengajuan dan minta diajukan ulang.';
            if ($err) { flash('danger', $err); }
            else {
                mysqli_begin_transaction($konek);
                try {
                    if ($p['tanggal'] === $t['tanggal']) {            // hari sama: tukar jenis shift
                        ex($konek, "UPDATE jadwal SET id_shift = ? WHERE id_jadwal = ?", 'ii', [$t['id_shift'], $p['id_jadwal']]);
                        ex($konek, "UPDATE jadwal SET id_shift = ? WHERE id_jadwal = ?", 'ii', [$p['id_shift'], $t['id_jadwal']]);
                    } else {                                           // hari beda: tukar pemilik jadwal
                        ex($konek, "UPDATE jadwal SET id_staf = ? WHERE id_jadwal = ?", 'ii', [$t['id_staf'], $p['id_jadwal']]);
                        ex($konek, "UPDATE jadwal SET id_staf = ? WHERE id_jadwal = ?", 'ii', [$p['id_staf'], $t['id_jadwal']]);
                    }
                    ex($konek, "UPDATE tukar_shift SET status = 'Disetujui', catatan_head = ?, tgl_keputusan = NOW() WHERE id_tukar = ?", 'si', [$catatan ?: null, $idT]);
                    mysqli_commit($konek);
                    flash('success', 'Tukar shift disetujui. Jadwal kedua staf sudah diperbarui otomatis.');
                } catch (mysqli_sql_exception $ex) {
                    mysqli_rollback($konek);
                    flash('danger', 'Gagal menukar jadwal. Tidak ada perubahan yang disimpan.');
                }
            }
        }
    }
    redirect('tukar/index.php');
}

/* ---------- Data tampilan ---------- */
$dasar = "SELECT t.*, sp.nama_lengkap AS nama_p, st.nama_lengkap AS nama_t, dv.nama_divisi,
                 shp.nama_shift AS shift_p, shp.warna AS warna_p, shp.jam_mulai AS mulai_p, shp.jam_selesai AS selesai_p,
                 sht.nama_shift AS shift_t, sht.warna AS warna_t, sht.jam_mulai AS mulai_t, sht.jam_selesai AS selesai_t
          FROM tukar_shift t
          JOIN staf sp ON t.id_pengaju = sp.id_staf JOIN staf st ON t.id_tujuan = st.id_staf
          JOIN divisi dv ON sp.id_divisi = dv.id_divisi
          JOIN shift_master shp ON t.id_shift_p = shp.id_shift JOIN shift_master sht ON t.id_shift_t = sht.id_shift";

$antrianHead = is_manager() ? q($konek, $dasar . " WHERE t.status = 'Menunggu Head Store' ORDER BY t.tgl_pengajuan") : [];
$masuk  = $idSaya ? q($konek, $dasar . " WHERE t.id_tujuan = ? AND t.status = 'Menunggu Rekan' ORDER BY t.tgl_pengajuan", 'i', [$idSaya]) : [];
$riwayat = is_manager()
    ? q($konek, $dasar . " ORDER BY t.tgl_pengajuan DESC LIMIT 30")
    : ($idSaya ? q($konek, $dasar . " WHERE t.id_pengaju = ? OR t.id_tujuan = ? ORDER BY t.tgl_pengajuan DESC LIMIT 30", 'ii', [$idSaya, $idSaya]) : []);

$jadwalSaya = []; $kandidat = []; $tanggalSaya = [];
if ($idSaya) {
    $jadwalSaya = q($konek,
        "SELECT j.id_jadwal, j.tanggal, j.id_shift, sm.nama_shift, sm.jam_mulai, sm.jam_selesai
         FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift
         WHERE j.id_staf = ? AND j.tanggal > ? AND j.tanggal <= DATE_ADD(?, INTERVAL 30 DAY)
           AND j.id_jadwal NOT IN (SELECT id_jadwal_pengaju FROM tukar_shift WHERE status IN " . AKTIF . ")
           AND j.id_jadwal NOT IN (SELECT id_jadwal_tujuan FROM tukar_shift WHERE status IN " . AKTIF . ")
         ORDER BY j.tanggal", 'iss', [$idSaya, $hariIni, $hariIni]);
    $rows = q($konek,
        "SELECT j.id_jadwal, j.tanggal, j.id_shift, sm.nama_shift, s.nama_lengkap
         FROM jadwal j JOIN staf s ON j.id_staf = s.id_staf JOIN shift_master sm ON j.id_shift = sm.id_shift
         WHERE s.id_divisi = (SELECT id_divisi FROM staf WHERE id_staf = ?) AND s.id_staf <> ? AND s.status = 'Aktif'
           AND j.tanggal > ? AND j.tanggal <= DATE_ADD(?, INTERVAL 30 DAY)
           AND j.id_jadwal NOT IN (SELECT id_jadwal_pengaju FROM tukar_shift WHERE status IN " . AKTIF . ")
           AND j.id_jadwal NOT IN (SELECT id_jadwal_tujuan FROM tukar_shift WHERE status IN " . AKTIF . ")
         ORDER BY j.tanggal, s.nama_lengkap", 'iiss', [$idSaya, $idSaya, $hariIni, $hariIni]);
    foreach ($rows as $r) $kandidat[] = ['id_jadwal' => (string)$r['id_jadwal'], 'tanggal' => $r['tanggal'], 'id_shift' => (string)$r['id_shift'],
                                          'label' => tgl_pendek($r['tanggal']) . ' | ' . $r['nama_shift'] . ' | ' . $r['nama_lengkap']];
    $tanggalSaya = array_column(q($konek, "SELECT tanggal FROM jadwal WHERE id_staf = ? AND tanggal > ?", 'is', [$idSaya, $hariIni]), 'tanggal');
}
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

function kartu_tukar($r, $saya) {   // ringkasan pertukaran
    return '<div class="small"><strong>' . e($r['nama_p']) . '</strong> melepas ' . tgl_pendek($r['tgl_p']) . ' ' . chip_shift($r['shift_p'], $r['warna_p']) .
           ' <i class="bi bi-arrow-left-right mx-1"></i> <strong>' . e($r['nama_t']) . '</strong> melepas ' . tgl_pendek($r['tgl_t']) . ' ' . chip_shift($r['shift_t'], $r['warna_t']) . '</div>';
}

$judul = 'Tukar Shift';
$aktif = 'tukar';
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <?php if ($idSaya): ?>
  <div class="col-lg-4">
    <div class="card"><div class="card-body">
      <h2 class="h6 fw-bold mb-1">Ajukan Tukar Shift</h2>
      <p class="small text-secondary">Pilih shift Anda, lalu shift rekan satu divisi yang ingin ditukar.</p>
      <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?><input type="hidden" name="aksi" value="ajukan">
        <div class="mb-3"><label class="form-label" for="jadwal_saya">Shift saya (mulai besok)</label>
          <select class="form-select" id="jadwal_saya" name="jadwal_saya" required>
            <option value="">-- pilih shift saya --</option>
            <?php foreach ($jadwalSaya as $j): ?>
              <option value="<?= $j['id_jadwal'] ?>" data-shift="<?= $j['id_shift'] ?>" data-tanggal="<?= $j['tanggal'] ?>"><?= e(tgl_pendek($j['tanggal'])) ?> | <?= e($j['nama_shift']) ?> (<?= jam($j['jam_mulai']) ?>-<?= jam($j['jam_selesai']) ?>)</option>
            <?php endforeach; ?>
          </select><div class="invalid-feedback">Pilih shift Anda.</div></div>
        <div class="mb-1"><label class="form-label" for="jadwal_tujuan">Ditukar dengan</label>
          <select class="form-select" id="jadwal_tujuan" name="jadwal_tujuan" required disabled><option value="">-- pilih shift rekan --</option></select>
          <div class="invalid-feedback">Pilih shift rekan.</div></div>
        <div class="small text-brand mb-3" id="infoTukar">&nbsp;</div>
        <div class="mb-3"><label class="form-label" for="alasan">Alasan</label>
          <textarea class="form-control" id="alasan" name="alasan" rows="3" required minlength="5"></textarea>
          <div class="invalid-feedback">Isi alasan (min. 5 karakter).</div></div>
        <button class="btn btn-brand w-100" <?= $jadwalSaya ? '' : 'disabled' ?>>Kirim Pengajuan</button>
        <?php if (!$jadwalSaya): ?><div class="small text-secondary mt-2">Tidak ada shift mendatang yang dapat ditukar.</div><?php endif; ?>
      </form>
      <script type="application/json" id="dataKandidat"><?= json_encode($kandidat, $jsonFlags) ?></script>
      <script type="application/json" id="dataTanggalSaya"><?= json_encode($tanggalSaya, $jsonFlags) ?></script>
    </div></div>
  </div>
  <?php endif; ?>

  <div class="<?= $idSaya ? 'col-lg-8' : 'col-12' ?>">
    <?php if ($masuk): ?>
    <div class="card border-warning mb-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-bell-fill text-warning"></i> Menunggu Tanggapan Anda</h2>
      <?php foreach ($masuk as $r): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
          <div><?= kartu_tukar($r, $idSaya) ?><div class="small text-secondary">Alasan: <?= e($r['alasan']) ?></div></div>
          <div class="text-nowrap">
            <form class="d-inline" method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="terima"><input type="hidden" name="id" value="<?= $r['id_tukar'] ?>"><button class="btn btn-sm btn-success">Terima</button></form>
            <form class="d-inline" method="post" data-confirm="Tolak ajakan tukar shift ini?"><?= csrf_field() ?><input type="hidden" name="aksi" value="tolak_rekan"><input type="hidden" name="id" value="<?= $r['id_tukar'] ?>"><button class="btn btn-sm btn-outline-danger">Tolak</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div></div>
    <?php endif; ?>

    <?php if (is_manager()): ?>
    <div class="card border-info mb-3"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><i class="bi bi-clipboard-check text-info"></i> Antrian Persetujuan Head Store <span class="badge text-bg-info"><?= count($antrianHead) ?></span></h2>
      <?php if (!$antrianHead): ?><p class="text-secondary mb-0">Tidak ada pengajuan yang menunggu.</p><?php endif; ?>
      <?php foreach ($antrianHead as $r): ?>
        <div class="py-2 border-bottom">
          <div class="d-flex justify-content-between flex-wrap gap-1"><?= kartu_tukar($r, 0) ?><span class="badge text-bg-light border"><?= e($r['nama_divisi']) ?></span></div>
          <div class="small text-secondary mb-2">Alasan: <?= e($r['alasan']) ?></div>
          <form method="post" class="d-flex flex-wrap gap-2">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_tukar'] ?>">
            <input class="form-control form-control-sm flex-fill" name="catatan_head" placeholder="Catatan head store (opsional)" maxlength="200">
            <button class="btn btn-sm btn-success" name="aksi" value="setuju"><i class="bi bi-check-lg"></i> Setujui</button>
            <button class="btn btn-sm btn-outline-danger" name="aksi" value="tolak"><i class="bi bi-x-lg"></i> Tolak</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
      <h2 class="h6 fw-bold mb-3"><?= is_manager() ? 'Riwayat Pengajuan' : 'Riwayat Tukar Shift Saya' ?></h2>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Pertukaran</th><th>Status</th><th>Diajukan</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php if (!$riwayat): ?><tr><td colspan="4" class="text-center text-secondary py-3">Belum ada pengajuan.</td></tr><?php endif; ?>
        <?php foreach ($riwayat as $r): ?>
          <tr>
            <td><?= kartu_tukar($r, $idSaya) ?><?php if ($r['catatan_head']): ?><div class="small text-secondary">Catatan head: <?= e($r['catatan_head']) ?></div><?php endif; ?></td>
            <td><?= badge_status($r['status']) ?></td>
            <td class="small text-nowrap"><?= waktu_indo($r['tgl_pengajuan']) ?></td>
            <td class="text-end">
              <?php if ($idSaya && (int)$r['id_pengaju'] === $idSaya && in_array($r['status'], ['Menunggu Rekan', 'Menunggu Head Store'], true)): ?>
                <form class="d-inline" method="post" data-confirm="Batalkan pengajuan ini?"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= $r['id_tukar'] ?>"><button class="btn btn-sm btn-outline-secondary">Batalkan</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
