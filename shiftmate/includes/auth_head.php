<?php /* Kepala HTML halaman login & register. Set $judul sebelum include. */ ?>
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
<div class="auth-page">
  <section class="auth-hero">
    <div class="d-flex align-items-center gap-2 mb-4">
      <span class="brand-mark"><i class="bi bi-cup-hot"></i></span>
      <span class="fs-4 fw-bold"><?= APP_NAME ?></span>
    </div>
    <h2 class="display-6 fw-bold">Jadwal rapi,<br>shift tenang.</h2>
    <p class="opacity-75 mb-0">Web HRIS untuk penjadwalan shift dan manajemen staf operasional kafe.</p>
    <ul>
      <li><i class="bi bi-calendar-week-fill"></i> Susun jadwal mingguan barista, kitchen, dan service</li>
      <li><i class="bi bi-arrow-left-right"></i> Tukar shift antar-staf dengan persetujuan head store</li>
      <li><i class="bi bi-journal-check"></i> Log handover wajib di akhir setiap shift</li>
      <li><i class="bi bi-shield-lock-fill"></i> Akses berbasis peran: Admin, Head Store, Staf</li>
    </ul>
  </section>
  <section class="auth-form">
    <div class="auth-box">
