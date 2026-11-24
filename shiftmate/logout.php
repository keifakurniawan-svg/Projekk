<?php
/* Modul 6 : mengakhiri session */
require_once __DIR__ . '/config/koneksi.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
session_start();
flash('info', 'Anda telah keluar dari sistem.');
redirect('index.php');
