# ShiftMate: Web HRIS Staf F&B

**Judul proyek:** Sistem Informasi HRIS untuk Penjadwalan Shift dan Manajemen Staf Operasional Kafe
**Judul simpel:** ShiftMate: Web HRIS Staf F&B

## Fokus fitur utama
1. **Dynamic Shift Scheduling** (`jadwal/atur.php`): head store menyusun jadwal mingguan per divisi (Barista, Kitchen, Service). Sel jadwal berwarna sesuai shift, baris cakupan per hari dihitung langsung oleh JavaScript dan memberi peringatan jika shift wajib kekurangan staf. Tersedia isi cepat satu minggu dan salin jadwal minggu lalu. Tanggal yang sudah lewat dikunci.
2. **Shift Swap** (`tukar/index.php`): staf mengajukan tukar dengan rekan satu divisi, rekan menerima atau menolak, lalu head store menyetujui. Saat disetujui, jadwal kedua staf ditukar otomatis dalam satu transaksi database. Sistem memvalidasi bentrok jadwal dan perubahan jadwal sejak diajukan.
3. **Log Handover** (`handover/`): form catatan akhir shift (keramaian, kondisi area, checklist penutupan, stok menipis, kendala, catatan serah terima). Form dibuka 60 menit sebelum shift berakhir, banner peringatan muncul bagi staf yang menunggak, dan staf yang menunggak tidak dapat mengajukan tukar shift. Head store memantau kepatuhan per tanggal dan memberi tanggapan.

## Cara menjalankan
1. Pasang XAMPP (Apache + MySQL + PHP 8+), nyalakan Apache dan MySQL.
2. Salin folder `shiftmate` ke `C:\xampp\htdocs\shiftmate` (jika nama folder berbeda, ubah `BASE_URL` di `config/koneksi.php`).
3. Di phpMyAdmin, tab **Import**, impor `database/shiftmate_db.sql`. Jadwal contoh dibuat relatif terhadap tanggal impor, jadi segera tampil di minggu berjalan.
4. Buka `http://localhost/shiftmate/` (butuh internet untuk Bootstrap CDN).

## Akun demo
| Peran | Username | Password |
|---|---|---|
| Admin | admin | admin123 |
| Head Store | raka.head | head12345 |
| Staf (Barista) | sinta, bagas | staf12345 |
| Staf (Service) | dimas | staf12345 |

Skenario uji cepat:
- Login `sinta`: ada ajakan tukar shift dari Bagas yang menunggu tanggapan. Terima.
- Login `raka.head`: setujui tukar shift Dimas dan Nadia di menu Tukar Shift, lalu cek hasilnya di Jadwal Shift.
- Login `raka.head`, menu Log Handover: Dewi (SM-005) sengaja memiliki handover tertunggak.
- Login `raka.head`, menu Susun Jadwal: kosongkan satu shift wajib dan lihat peringatan cakupan berubah merah.
- Daftar akun baru memakai kode staf yang belum punya akun, misalnya SM-009.

## Pemetaan ke materi modul
| Modul | Penerapan |
|---|---|
| 1. HTML | Form (text, date, time, radio, checkbox, select, file, textarea, color), tabel |
| 2. CSS & Bootstrap | `assets/css/style.css`, grid jadwal, card, badge, pagination, mode gelap |
| 3. JavaScript | `assets/js/app.js`: DOM, event, validasi form, cakupan jadwal dinamis, dropdown bertingkat tukar shift, hitung mundur shift |
| 4. PHP dasar | Variabel, array, fungsi, perulangan, percabangan (`includes/functions.php`) |
| 5. PHP & MySQL | `config/koneksi.php`, CRUD staf/divisi/shift/jadwal dengan MySQLi |
| 6. Cookie & Session | Session login, pesan flash, cookie `sm_username` |
| 7. Authentication | `register.php`, `index.php`: `password_hash()`, `password_verify()`, prepared statement, `require_role()` |
| 8. Akses DB lanjut & upload | JOIN banyak tabel, LEFT JOIN, transaksi, upload foto staf |
| Studi Kasus | Login + session, form input, tampil data, pencarian dan filter, pagination |

## Struktur folder
```
shiftmate/
├── index.php · register.php · logout.php · dashboard.php
├── config/koneksi.php
├── includes/ (functions, header, footer, auth_head, auth_foot)
├── jadwal/ (index, atur) · tukar/ · handover/ (index, isi, lihat)
├── staf/ · divisi/ · shift/
├── assets/css/style.css · assets/js/app.js
├── uploads/
└── database/shiftmate_db.sql
```
