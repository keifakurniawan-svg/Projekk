<?php
/* Modul 5 : DELETE staf (POST + CSRF) */
require_once __DIR__ . '/../config/koneksi.php';
require_role(['admin', 'head_store']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('staf/index.php');
csrf_check();
$id = (int)($_POST['id'] ?? 0);

if ($id === (int)($_SESSION['id_staf'] ?? 0)) { flash('danger', 'Anda tidak dapat menghapus data diri sendiri.'); redirect('staf/index.php'); }
$row = q1($konek, "SELECT foto FROM staf WHERE id_staf = ?", 'i', [$id]);
if ($row) {
    ex($konek, "DELETE FROM staf WHERE id_staf = ?", 'i', [$id]);
    hapus_foto($row['foto']);
    flash('success', 'Data staf berhasil dihapus.');
} else {
    flash('danger', 'Data tidak ditemukan.');
}
redirect('staf/index.php');
