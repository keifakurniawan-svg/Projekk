<?php
/* Template atas: sidebar + topbar + banner handover. Set $judul dan $aktif sebelum include. */
$judul = $judul ?? 'Dashboard';
$aktif = $aktif ?? '';
$role  = $_SESSION['role'] ?? 'staf';
$idStafLogin = (int)($_SESSION['id_staf'] ?? 0);
$menu = [
  ['dashboard', 'dashboard.php',        'bi-grid-1x2',        'Dashboard',       ['admin','head_store','staf']],
  ['jadwal',    'jadwal/index.php',     'bi-calendar-week',   'Jadwal Shift',    ['admin','head_store','staf']],
  ['atur',      'jadwal/atur.php',      'bi-pencil-square',   'Susun Jadwal',    ['admin','head_store']],
  ['tukar',     'tukar/index.php',      'bi-arrow-left-right','Tukar Shift',     ['admin','head_store','staf']],
  ['handover',  'handover/index.php',   'bi-journal-text',    'Log Handover',    ['admin','head_store','staf']],
  ['staf',      'staf/index.php',       'bi-people',          'Data Staf',       ['admin','head_store']],
  ['divisi',    'divisi/index.php',     'bi-diagram-3',       'Divisi',          ['admin','head_store']],
  ['shift',     'shift/index.php',      'bi-clock',           'Master Shift',    ['admin','head_store']],
];
$bannerTunggak = [];
if ($role === 'staf' && $idStafLogin) { $bannerTunggak = handover_tunggak($konek, $idStafLogin); }
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul) ?> | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
  <script>document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('tema') || 'light');</script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= BASE_URL ?>/dashboard.php"><span class="brand-mark"><i class="bi bi-cup-hot"></i></span><?= APP_NAME ?></a>
    <nav class="nav flex-column mt-3">
      <?php foreach ($menu as $m): if (!in_array($role, $m[4], true)) continue; ?>
        <a class="nav-link <?= $aktif === $m[0] ? 'active' : '' ?>" href="<?= BASE_URL . '/' . $m[1] ?>">
          <i class="bi <?= $m[2] ?>"></i><span><?= $m[3] ?></span>
          <?php if ($m[0] === 'handover' && $bannerTunggak): ?><span class="badge text-bg-danger ms-auto"><?= count($bannerTunggak) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="small text-uppercase opacity-75">Masuk sebagai</div>
      <div class="fw-semibold"><?= e($_SESSION['nama'] ?? '') ?></div>
      <span class="badge text-bg-light mt-1"><?= e(label_role($role)) ?></span>
    </div>
  </aside>

  <div class="content-wrap">
    <header class="topbar">
      <button class="btn btn-sm btn-outline-secondary d-lg-none" id="btnSidebar" aria-label="Menu"><i class="bi bi-list"></i></button>
      <h1 class="h5 mb-0"><?= e($judul) ?></h1>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="small text-secondary d-none d-md-inline" id="jam"></span>
        <button class="btn btn-sm btn-outline-secondary" id="btnTema" title="Ganti tema"><i class="bi bi-moon-stars"></i></button>
        <a class="btn btn-sm btn-outline-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
      </div>
    </header>
    <main class="p-3 p-md-4">
      <?php if ($bannerTunggak && $aktif !== 'handover'): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
          <i class="bi bi-exclamation-octagon-fill fs-5"></i>
          <div class="flex-fill">Anda memiliki <strong><?= count($bannerTunggak) ?> handover</strong> yang belum diisi. Handover wajib diisi di akhir shift.</div>
          <a class="btn btn-sm btn-danger" href="<?= BASE_URL ?>/handover/index.php">Isi sekarang</a>
        </div>
      <?php endif; ?>
      <?php if ($f = flash()): ?>
        <div class="alert alert-<?= e($f[0]) ?> alert-dismissible fade show auto-dismiss" role="alert">
          <?= e($f[1]) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
