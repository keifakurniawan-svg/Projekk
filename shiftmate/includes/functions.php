<?php
/* Fungsi bantu: session, flash, CSRF, tanggal, upload, aturan handover */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const NAMA_BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function e($teks) { return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8'); }

function redirect($path) {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

/* Flash message: flash('success','..') untuk set, flash() untuk ambil */
function flash($type = null, $pesan = null) {
    if ($type !== null) { $_SESSION['flash'] = [$type, $pesan]; return null; }
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

/* Modul 7 : pembatasan akses halaman */
function require_login() {
    if (empty($_SESSION['login'])) {
        flash('warning', 'Silakan login terlebih dahulu.');
        redirect('index.php');
    }
}
function require_role(array $roles) {
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        flash('danger', 'Anda tidak memiliki hak akses ke halaman tersebut.');
        redirect('dashboard.php');
    }
}
function is_manager() { return in_array($_SESSION['role'] ?? '', ['admin', 'head_store'], true); }
function label_role($r) { return ['admin' => 'Admin', 'head_store' => 'Head Store', 'staf' => 'Staf'][$r] ?? $r; }

/* Perlindungan CSRF */
function csrf_field() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

/* ---------- Tanggal & waktu ---------- */
function tgl_indo($tanggal) {
    if (!$tanggal) return '-';
    $t = strtotime($tanggal);
    return date('d', $t) . ' ' . NAMA_BULAN[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function hari_indo($tanggal) { return NAMA_HARI[(int)date('w', strtotime($tanggal))]; }
function tgl_pendek($tanggal) { $t = strtotime($tanggal); return substr(hari_indo($tanggal), 0, 3) . ', ' . date('j', $t) . ' ' . NAMA_BULAN[(int)date('n', $t)]; }
function jam($waktu) { return $waktu ? substr($waktu, 0, 5) : '-'; }
function waktu_indo($dt) { return $dt ? tgl_indo($dt) . ' ' . date('H:i', strtotime($dt)) : '-'; }

/* Senin dari minggu yang memuat $tanggal */
function senin_minggu($tanggal) {
    $t = strtotime($tanggal);
    return date('Y-m-d', strtotime('-' . ((int)date('N', $t) - 1) . ' days', $t));
}
function tambah_hari($tanggal, $n) { return date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days', strtotime($tanggal))); }

/* ---------- Tampilan ---------- */
function badge_status($status) {
    $map = ['Aktif' => 'success', 'Nonaktif' => 'secondary', 'Menunggu Rekan' => 'warning', 'Menunggu Head Store' => 'info',
            'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'secondary'];
    return '<span class="badge text-bg-' . ($map[$status] ?? 'secondary') . '">' . e($status) . '</span>';
}
function chip_shift($nama, $warna, $mulai = null, $selesai = null) {
    $jamTeks = $mulai ? ' <small>' . jam($mulai) . '-' . jam($selesai) . '</small>' : '';
    return '<span class="chip-shift" style="--c:' . e($warna) . '">' . e($nama) . $jamTeks . '</span>';
}
function inisial($nama) {
    $p = preg_split('/\s+/', trim($nama));
    return strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : ''));
}
function avatar($nama, $foto, $besar = false) {
    $kelas = 'avatar' . ($besar ? ' avatar-lg' : '');
    if ($foto) return '<img class="' . $kelas . '" src="' . foto_url($foto) . '" alt="">';
    return '<span class="' . $kelas . '">' . e(inisial($nama)) . '</span>';
}

/* ---------- Aturan handover ---------- */
/* Handover dibuka 60 menit sebelum shift berakhir dan tetap wajib diisi sesudahnya. */
function handover_terbuka($tanggal, $jamSelesai) {
    return time() >= strtotime("$tanggal $jamSelesai") - 3600;
}
function handover_terlambat($tanggal, $jamSelesai) {
    return time() > strtotime("$tanggal $jamSelesai");
}
/* Daftar shift 14 hari terakhir (sampai hari ini) yang handover-nya belum diisi */
function handover_belum($konek, $idStaf) {
    $s = mysqli_prepare($konek,
        "SELECT j.id_jadwal, j.tanggal, sm.nama_shift, sm.warna, sm.jam_mulai, sm.jam_selesai
         FROM jadwal j JOIN shift_master sm ON j.id_shift = sm.id_shift
         LEFT JOIN handover h ON h.id_jadwal = j.id_jadwal
         WHERE j.id_staf = ? AND h.id_handover IS NULL
           AND j.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND CURDATE()
         ORDER BY j.tanggal, sm.jam_mulai");
    mysqli_stmt_bind_param($s, 'i', $idStaf);
    mysqli_stmt_execute($s);
    $hasil = [];
    $res = mysqli_stmt_get_result($s);
    while ($r = mysqli_fetch_assoc($res)) {
        if (handover_terbuka($r['tanggal'], $r['jam_selesai'])) {
            $r['terlambat'] = handover_terlambat($r['tanggal'], $r['jam_selesai']);
            $hasil[] = $r;
        }
    }
    return $hasil;
}
function handover_tunggak($konek, $idStaf) {
    return array_values(array_filter(handover_belum($konek, $idStaf), fn($r) => $r['terlambat']));
}

/* ---------- Upload foto (Modul 8) ---------- */
function upload_foto($berkas, &$galat) {
    if ($berkas['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($berkas['error'] !== UPLOAD_ERR_OK) { $galat = 'Unggah foto gagal.'; return false; }
    if ($berkas['size'] > 2 * 1024 * 1024)  { $galat = 'Ukuran foto maksimal 2 MB.'; return false; }
    $ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
    $mime     = mime_content_type($berkas['tmp_name']);
    $izin     = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($izin[$ekstensi]) || $izin[$ekstensi] !== $mime) { $galat = 'Format foto harus JPG, PNG, atau WEBP.'; return false; }
    $namaBaru = 'stf_' . bin2hex(random_bytes(6)) . '.' . $ekstensi;
    if (!move_uploaded_file($berkas['tmp_name'], __DIR__ . '/../uploads/' . $namaBaru)) {
        $galat = 'Foto tidak dapat disimpan. Periksa izin folder uploads.'; return false;
    }
    return $namaBaru;
}
function hapus_foto($nama) {
    if ($nama && is_file(__DIR__ . '/../uploads/' . $nama)) { unlink(__DIR__ . '/../uploads/' . $nama); }
}
function foto_url($nama) { return $nama ? BASE_URL . '/uploads/' . rawurlencode($nama) : null; }

/* ---------- Pembungkus prepared statement (Modul 7 : cegah SQL injection) ---------- */
function q($konek, $sql, $tipe = '', $param = []) {            // semua baris
    $s = mysqli_prepare($konek, $sql);
    if ($param) mysqli_stmt_bind_param($s, $tipe, ...$param);
    mysqli_stmt_execute($s);
    $r = mysqli_stmt_get_result($s);
    return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : [];
}
function q1($konek, $sql, $tipe = '', $param = []) {           // satu baris atau null
    $baris = q($konek, $sql, $tipe, $param);
    return $baris[0] ?? null;
}
function ex($konek, $sql, $tipe = '', $param = []) {           // INSERT / UPDATE / DELETE
    $s = mysqli_prepare($konek, $sql);
    if ($param) mysqli_stmt_bind_param($s, $tipe, ...$param);
    mysqli_stmt_execute($s);
    return mysqli_stmt_affected_rows($s);
}
