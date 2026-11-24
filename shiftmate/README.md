
Sistem Informasi HRIS untuk Penjadwalan Shift dan Manajemen Staf Operasional Kafe
ShiftMate: Web HRIS Staf F&B

## Fokus fitur utama
1. **Dynamic Shift Scheduling** (`jadwal/atur.php`): head store menyusun jadwal mingguan per divisi (Barista, Kitchen, Service). Sel jadwal berwarna sesuai shift, baris cakupan per hari dihitung langsung oleh JavaScript dan memberi peringatan jika shift wajib kekurangan staf. Tersedia isi cepat satu minggu dan salin jadwal minggu lalu. Tanggal yang sudah lewat dikunci.
2. **Shift Swap** (`tukar/index.php`): staf mengajukan tukar dengan rekan satu divisi, rekan menerima atau menolak, lalu head store menyetujui. Saat disetujui, jadwal kedua staf ditukar otomatis dalam satu transaksi database. Sistem memvalidasi bentrok jadwal dan perubahan jadwal sejak diajukan.
3. **Log Handover** (`handover/`): form catatan akhir shift (keramaian, kondisi area, checklist penutupan, stok menipis, kendala, catatan serah terima). Form dibuka 60 menit sebelum shift berakhir, banner peringatan muncul bagi staf yang menunggak, dan staf yang menunggak tidak dapat mengajukan tukar shift. Head store memantau kepatuhan per tanggal dan memberi tanggapan.

## Akun demo
| Peran | Username | Password |
|---|---|---|
| Admin | admin | admin123 |
| Head Store | kepa | head12345 |
| Staf (Barista) | jepp | staf12345 |


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
