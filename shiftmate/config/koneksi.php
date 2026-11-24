<?php
/* Modul 5 & 8 : koneksi PHP ke MySQL memakai MySQLi */
define('BASE_URL', '/shiftmate');       // sesuaikan dengan nama folder di htdocs
define('APP_NAME', 'ShiftMate');
date_default_timezone_set('Asia/Jakarta');

$hostname = 'localhost';
$username = 'root';
$password = '';
$database = 'shiftmate_db';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // error -> exception
try {
    $konek = mysqli_connect($hostname, $username, $password, $database);
    mysqli_set_charset($konek, 'utf8mb4');
    mysqli_query($konek, "SET time_zone = '+07:00'");           // samakan dengan WIB
} catch (mysqli_sql_exception $ex) {
    die('Koneksi gagal: pastikan MySQL aktif dan database shiftmate_db sudah diimpor.');
}

require_once __DIR__ . '/../includes/functions.php';
