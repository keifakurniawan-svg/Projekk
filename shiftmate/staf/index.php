<?php
/* Modul 5 & 8 : READ data staf (JOIN divisi) + pencarian + filter + pagination */
require_once __DIR__ . '/../config/koneksi.php';
require_role(['admin', 'head_store']);

$judul = 'Data Staf';
$aktif = 'staf';
$q      = trim($_GET['q'] ?? '');
$idDiv  = (int)($_GET['divisi'] ?? 0);
$status = $_GET['status'] ?? '';
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$batas  = 8;

$where = []; $tipe = ''; $param = [];
if ($q !== '') { $where[] = "(s.nama_lengkap LIKE ? OR s.kode_staf LIKE ? OR s.email LIKE ?)"; $like = "%$q%"; $tipe .= 'sss'; array_push($param, $like, $like, $like); }
if ($idDiv > 0) { $where[] = "s.id_divisi = ?"; $tipe .= 'i'; $param[] = $idDiv; }
if (in_array($status, ['Aktif', 'Nonaktif'], true)) { $where[] = "s.status = ?"; $tipe .= 's'; $param[] = $status; }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total  = (int)q1($konek, "SELECT COUNT(*) AS n FROM staf s $sqlWhere", $tipe, $param)['n'];
$jmlHal = max(1, (int)ceil($total / $batas));
$hal    = min($hal, $jmlHal);
$mulai  = ($hal - 1) * $batas;

$data = q($konek,
    "SELECT s.id_staf, s.kode_staf, s.nama_lengkap, s.email, s.no_hp, s.foto, s.status, s.tgl_bergabung, d.nama_divisi, u.role
     FROM staf s INNER JOIN divisi d ON s.id_divisi = d.id_divisi LEFT JOIN users u ON u.id_staf = s.id_staf
     $sqlWhere ORDER BY d.id_divisi, s.nama_lengkap LIMIT ?, ?", $tipe . 'ii', array_merge($param, [$mulai, $batas]));
$daftarDivisi = q($konek, "SELECT * FROM divisi ORDER BY id_divisi");

function urlHal($h) { $p = $_GET; $p['hal'] = $h; return '?' . http_build_query($p); }
include __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label small mb-1">Cari (nama / kode / email)</label><input type="text" name="q" class="form-control" value="<?= e($q) ?>"></div>
    <div class="col-md-3"><label class="form-label small mb-1">Divisi</label>
      <select name="divisi" class="form-select"><option value="0">Semua</option>
        <?php foreach ($daftarDivisi as $d): ?><option value="<?= $d['id_divisi'] ?>" <?= $idDiv === (int)$d['id_divisi'] ? 'selected' : '' ?>><?= e($d['nama_divisi']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select"><option value="">Semua</option>
        <?php foreach (['Aktif', 'Nonaktif'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-brand flex-fill"><i class="bi bi-search"></i> Cari</button><a class="btn btn-outline-secondary" href="index.php">Reset</a></div>
  </form>
</div></div>

<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-secondary"><?= $total ?> staf ditemukan</div>
    <a href="form.php" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Tambah Staf</a>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Staf</th><th>Kode</th><th>Divisi</th><th>Bergabung</th><th>Akun</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$data): ?><tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada data.</td></tr><?php endif; ?>
    <?php foreach ($data as $r): ?>
      <tr>
        <td><div class="d-flex align-items-center gap-3"><?= avatar($r['nama_lengkap'], $r['foto']) ?><div><div class="fw-semibold"><?= e($r['nama_lengkap']) ?></div><div class="small text-secondary"><?= e($r['email']) ?></div></div></div></td>
        <td><?= e($r['kode_staf']) ?></td><td><?= e($r['nama_divisi']) ?></td><td><?= tgl_indo($r['tgl_bergabung']) ?></td>
        <td><?= $r['role'] ? '<span class="badge text-bg-light border">' . e(label_role($r['role'])) . '</span>' : '<span class="text-secondary small">Belum ada</span>' ?></td>
        <td><?= badge_status($r['status']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= $r['id_staf'] ?>" title="Ubah"><i class="bi bi-pencil"></i></a>
          <form class="d-inline" method="post" action="hapus.php" data-confirm="Hapus <?= e($r['nama_lengkap']) ?>? Jadwal, handover, dan akunnya ikut terhapus. Pertimbangkan menonaktifkan saja.">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id_staf'] ?>"><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if ($jmlHal > 1): ?>
  <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">
    <li class="page-item <?= $hal <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= urlHal($hal - 1) ?>">&laquo;</a></li>
    <?php for ($i = 1; $i <= $jmlHal; $i++): ?><li class="page-item <?= $i === $hal ? 'active' : '' ?>"><a class="page-link" href="<?= urlHal($i) ?>"><?= $i ?></a></li><?php endfor; ?>
    <li class="page-item <?= $hal >= $jmlHal ? 'disabled' : '' ?>"><a class="page-link" href="<?= urlHal($hal + 1) ?>">&raquo;</a></li>
  </ul></nav>
  <?php endif; ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
