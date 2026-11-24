-- ============================================================
--  ShiftMate HRIS Staf F&B  |  Basis data: shiftmate_db
--  Import melalui phpMyAdmin (tab Import)
-- ============================================================
CREATE DATABASE IF NOT EXISTS shiftmate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shiftmate_db;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS handover, tukar_shift, jadwal, users, staf, shift_master, divisi;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE divisi (
  id_divisi      INT(3) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama_divisi    VARCHAR(30) NOT NULL,
  min_per_shift  INT(2) NOT NULL DEFAULT 1       -- kebutuhan minimum staf per shift wajib
) ENGINE=InnoDB;

CREATE TABLE shift_master (
  id_shift     INT(3) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama_shift   VARCHAR(30) NOT NULL,
  jam_mulai    TIME NOT NULL,
  jam_selesai  TIME NOT NULL,
  warna        CHAR(7) NOT NULL DEFAULT '#c8873f',
  wajib        TINYINT(1) NOT NULL DEFAULT 0     -- 1 = shift ini wajib tercover (buka/tutup)
) ENGINE=InnoDB;

CREATE TABLE staf (
  id_staf       INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kode_staf     VARCHAR(10) NOT NULL UNIQUE,
  nama_lengkap  VARCHAR(80) NOT NULL,
  email         VARCHAR(80) NOT NULL,
  no_hp         VARCHAR(20) NOT NULL,
  tgl_bergabung DATE NOT NULL,
  status        ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  foto          VARCHAR(120) DEFAULT NULL,
  id_divisi     INT(3) NOT NULL,
  FOREIGN KEY (id_divisi) REFERENCES divisi(id_divisi) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE users (
  id_user   INT(5) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username  VARCHAR(30) NOT NULL UNIQUE,
  password  VARCHAR(255) NOT NULL,                -- hasil password_hash()
  role      ENUM('admin','head_store','staf') NOT NULL DEFAULT 'staf',
  id_staf   INT(5) DEFAULT NULL,
  FOREIGN KEY (id_staf) REFERENCES staf(id_staf) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE jadwal (
  id_jadwal INT(7) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_staf   INT(5) NOT NULL,
  id_shift  INT(3) NOT NULL,
  tanggal   DATE NOT NULL,
  UNIQUE KEY uq_staf_tanggal (id_staf, tanggal),  -- satu shift per staf per hari
  KEY idx_tanggal (tanggal),
  FOREIGN KEY (id_staf)  REFERENCES staf(id_staf) ON DELETE CASCADE,
  FOREIGN KEY (id_shift) REFERENCES shift_master(id_shift) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tukar_shift (
  id_tukar           INT(6) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_jadwal_pengaju  INT(7) NOT NULL,
  id_jadwal_tujuan   INT(7) NOT NULL,
  id_pengaju         INT(5) NOT NULL,
  id_tujuan          INT(5) NOT NULL,
  tgl_p              DATE NOT NULL,              -- snapshot jadwal pengaju saat diajukan
  id_shift_p         INT(3) NOT NULL,
  tgl_t              DATE NOT NULL,              -- snapshot jadwal rekan saat diajukan
  id_shift_t         INT(3) NOT NULL,
  alasan             TEXT NOT NULL,
  status             ENUM('Menunggu Rekan','Menunggu Head Store','Disetujui','Ditolak','Dibatalkan') NOT NULL DEFAULT 'Menunggu Rekan',
  catatan_head       TEXT DEFAULT NULL,
  tgl_pengajuan      DATETIME NOT NULL,
  tgl_keputusan      DATETIME DEFAULT NULL,
  FOREIGN KEY (id_jadwal_pengaju) REFERENCES jadwal(id_jadwal) ON DELETE CASCADE,
  FOREIGN KEY (id_jadwal_tujuan)  REFERENCES jadwal(id_jadwal) ON DELETE CASCADE,
  FOREIGN KEY (id_pengaju) REFERENCES staf(id_staf) ON DELETE CASCADE,
  FOREIGN KEY (id_tujuan)  REFERENCES staf(id_staf) ON DELETE CASCADE,
  FOREIGN KEY (id_shift_p) REFERENCES shift_master(id_shift),
  FOREIGN KEY (id_shift_t) REFERENCES shift_master(id_shift)
) ENGINE=InnoDB;

CREATE TABLE handover (
  id_handover        INT(7) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_jadwal          INT(7) NOT NULL UNIQUE,      -- satu handover per shift
  id_staf            INT(5) NOT NULL,
  tingkat_keramaian  ENUM('Sepi','Normal','Ramai','Sangat Ramai') NOT NULL,
  kondisi_area       ENUM('Bersih & Rapi','Perlu Dirapikan','Bermasalah') NOT NULL,
  cek_kebersihan     TINYINT(1) NOT NULL DEFAULT 0,
  cek_peralatan      TINYINT(1) NOT NULL DEFAULT 0,
  cek_stok           TINYINT(1) NOT NULL DEFAULT 0,
  stok_menipis       TEXT DEFAULT NULL,
  kendala            TEXT DEFAULT NULL,
  catatan            TEXT NOT NULL,
  waktu_isi          DATETIME NOT NULL,
  catatan_head       TEXT DEFAULT NULL,
  ditinjau_oleh      INT(5) DEFAULT NULL,
  waktu_tinjau       DATETIME DEFAULT NULL,
  FOREIGN KEY (id_jadwal) REFERENCES jadwal(id_jadwal) ON DELETE CASCADE,
  FOREIGN KEY (id_staf)   REFERENCES staf(id_staf) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------- DATA AWAL ----------------
INSERT INTO divisi (nama_divisi, min_per_shift) VALUES ('Barista',1),('Kitchen',1),('Service',1),('Manajemen',0);

INSERT INTO shift_master (nama_shift, jam_mulai, jam_selesai, warna, wajib) VALUES
 ('Pagi','07:00:00','15:00:00','#f59e0b',1),
 ('Siang','11:00:00','19:00:00','#0ea5e9',0),
 ('Malam','15:00:00','23:00:00','#6366f1',1);

INSERT INTO staf (id_staf,kode_staf,nama_lengkap,email,no_hp,tgl_bergabung,status,id_divisi) VALUES
 (1,'SM-001','Raka Pratama','raka@shiftmate.id','081200000001','2023-01-09','Aktif',4),
 (2,'SM-002','Sinta Maharani','sinta@shiftmate.id','081200000002','2023-03-01','Aktif',1),
 (3,'SM-003','Bagas Wicaksono','bagas@shiftmate.id','081200000003','2023-06-12','Aktif',1),
 (4,'SM-004','Laras Ayu','laras@shiftmate.id','081200000004','2024-02-05','Aktif',1),
 (5,'SM-005','Dewi Anggraini','dewi@shiftmate.id','081200000005','2023-04-17','Aktif',2),
 (6,'SM-006','Fajar Nugroho','fajar@shiftmate.id','081200000006','2023-09-04','Aktif',2),
 (7,'SM-007','Mira Salsabila','mira@shiftmate.id','081200000007','2024-05-20','Aktif',2),
 (8,'SM-008','Dimas Saputra','dimas@shiftmate.id','081200000008','2023-07-10','Aktif',3),
 (9,'SM-009','Nadia Putri','nadia@shiftmate.id','081200000009','2024-01-15','Aktif',3),
 (10,'SM-010','Yoga Aditya','yoga@shiftmate.id','081200000010','2024-08-12','Aktif',3);

-- Password: admin123 | head12345 | staf12345
INSERT INTO users (username,password,role,id_staf) VALUES
 ('admin','$2y$12$RKUbZQrdL4NZHc0lik6pouycSCK2OvD21OQUh9S51yhcAaz2LyccC','admin',NULL),
 ('raka.head','$2y$12$fEsmiCgjKsjoI5mwLAjoluA4i6EtxosRf7LbugkpMiUHqANaqaqwi','head_store',1),
 ('sinta','$2y$12$DI84LuKfRQVEZ/iG9Su4GOa6tv2rFKiP3OoNoJXurQEeNL0ISuDcO','staf',2),
 ('bagas','$2y$12$DI84LuKfRQVEZ/iG9Su4GOa6tv2rFKiP3OoNoJXurQEeNL0ISuDcO','staf',3),
 ('dimas','$2y$12$DI84LuKfRQVEZ/iG9Su4GOa6tv2rFKiP3OoNoJXurQEeNL0ISuDcO','staf',8);

-- Jadwal: dari Senin minggu lalu sampai Minggu 2 minggu ke depan (relatif terhadap tanggal impor)
SET @senin = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);

INSERT INTO jadwal (id_staf,id_shift,tanggal) VALUES
 (1,1,DATE_ADD(@senin, INTERVAL -7 DAY)),
 (3,3,DATE_ADD(@senin, INTERVAL -7 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL -7 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL -7 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL -7 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL -7 DAY)),
 (9,1,DATE_ADD(@senin, INTERVAL -7 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL -6 DAY)),
 (2,1,DATE_ADD(@senin, INTERVAL -6 DAY)),
 (4,3,DATE_ADD(@senin, INTERVAL -6 DAY)),
 (5,3,DATE_ADD(@senin, INTERVAL -6 DAY)),
 (6,1,DATE_ADD(@senin, INTERVAL -6 DAY)),
 (9,1,DATE_ADD(@senin, INTERVAL -6 DAY)),
 (10,3,DATE_ADD(@senin, INTERVAL -6 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL -5 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL -5 DAY)),
 (3,1,DATE_ADD(@senin, INTERVAL -5 DAY)),
 (6,1,DATE_ADD(@senin, INTERVAL -5 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL -5 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL -5 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL -5 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL -4 DAY)),
 (3,1,DATE_ADD(@senin, INTERVAL -4 DAY)),
 (4,3,DATE_ADD(@senin, INTERVAL -4 DAY)),
 (5,3,DATE_ADD(@senin, INTERVAL -4 DAY)),
 (7,1,DATE_ADD(@senin, INTERVAL -4 DAY)),
 (8,1,DATE_ADD(@senin, INTERVAL -4 DAY)),
 (9,3,DATE_ADD(@senin, INTERVAL -4 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL -3 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL -3 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL -3 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL -3 DAY)),
 (6,3,DATE_ADD(@senin, INTERVAL -3 DAY)),
 (9,3,DATE_ADD(@senin, INTERVAL -3 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL -3 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL -2 DAY)),
 (2,1,DATE_ADD(@senin, INTERVAL -2 DAY)),
 (3,3,DATE_ADD(@senin, INTERVAL -2 DAY)),
 (6,3,DATE_ADD(@senin, INTERVAL -2 DAY)),
 (7,1,DATE_ADD(@senin, INTERVAL -2 DAY)),
 (8,1,DATE_ADD(@senin, INTERVAL -2 DAY)),
 (10,3,DATE_ADD(@senin, INTERVAL -2 DAY)),
 (3,3,DATE_ADD(@senin, INTERVAL -1 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL -1 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL -1 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL -1 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL -1 DAY)),
 (9,1,DATE_ADD(@senin, INTERVAL -1 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 0 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL 0 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL 0 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL 0 DAY)),
 (6,3,DATE_ADD(@senin, INTERVAL 0 DAY)),
 (9,3,DATE_ADD(@senin, INTERVAL 0 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL 0 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 1 DAY)),
 (2,1,DATE_ADD(@senin, INTERVAL 1 DAY)),
 (3,3,DATE_ADD(@senin, INTERVAL 1 DAY)),
 (6,3,DATE_ADD(@senin, INTERVAL 1 DAY)),
 (7,1,DATE_ADD(@senin, INTERVAL 1 DAY)),
 (8,1,DATE_ADD(@senin, INTERVAL 1 DAY)),
 (10,3,DATE_ADD(@senin, INTERVAL 1 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 2 DAY)),
 (3,3,DATE_ADD(@senin, INTERVAL 2 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL 2 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL 2 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL 2 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL 2 DAY)),
 (9,1,DATE_ADD(@senin, INTERVAL 2 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 3 DAY)),
 (2,1,DATE_ADD(@senin, INTERVAL 3 DAY)),
 (4,3,DATE_ADD(@senin, INTERVAL 3 DAY)),
 (5,3,DATE_ADD(@senin, INTERVAL 3 DAY)),
 (6,1,DATE_ADD(@senin, INTERVAL 3 DAY)),
 (9,1,DATE_ADD(@senin, INTERVAL 3 DAY)),
 (10,3,DATE_ADD(@senin, INTERVAL 3 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 4 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL 4 DAY)),
 (3,1,DATE_ADD(@senin, INTERVAL 4 DAY)),
 (6,1,DATE_ADD(@senin, INTERVAL 4 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL 4 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL 4 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL 4 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 5 DAY)),
 (3,1,DATE_ADD(@senin, INTERVAL 5 DAY)),
 (4,3,DATE_ADD(@senin, INTERVAL 5 DAY)),
 (5,3,DATE_ADD(@senin, INTERVAL 5 DAY)),
 (7,1,DATE_ADD(@senin, INTERVAL 5 DAY)),
 (8,1,DATE_ADD(@senin, INTERVAL 5 DAY)),
 (9,3,DATE_ADD(@senin, INTERVAL 5 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL 6 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL 6 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL 6 DAY)),
 (6,3,DATE_ADD(@senin, INTERVAL 6 DAY)),
 (9,3,DATE_ADD(@senin, INTERVAL 6 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL 6 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 7 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL 7 DAY)),
 (3,1,DATE_ADD(@senin, INTERVAL 7 DAY)),
 (6,1,DATE_ADD(@senin, INTERVAL 7 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL 7 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL 7 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL 7 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 8 DAY)),
 (3,1,DATE_ADD(@senin, INTERVAL 8 DAY)),
 (4,3,DATE_ADD(@senin, INTERVAL 8 DAY)),
 (5,3,DATE_ADD(@senin, INTERVAL 8 DAY)),
 (7,1,DATE_ADD(@senin, INTERVAL 8 DAY)),
 (8,1,DATE_ADD(@senin, INTERVAL 8 DAY)),
 (9,3,DATE_ADD(@senin, INTERVAL 8 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 9 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL 9 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL 9 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL 9 DAY)),
 (6,3,DATE_ADD(@senin, INTERVAL 9 DAY)),
 (9,3,DATE_ADD(@senin, INTERVAL 9 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL 9 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 10 DAY)),
 (2,1,DATE_ADD(@senin, INTERVAL 10 DAY)),
 (3,3,DATE_ADD(@senin, INTERVAL 10 DAY)),
 (6,3,DATE_ADD(@senin, INTERVAL 10 DAY)),
 (7,1,DATE_ADD(@senin, INTERVAL 10 DAY)),
 (8,1,DATE_ADD(@senin, INTERVAL 10 DAY)),
 (10,3,DATE_ADD(@senin, INTERVAL 10 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 11 DAY)),
 (3,3,DATE_ADD(@senin, INTERVAL 11 DAY)),
 (4,1,DATE_ADD(@senin, INTERVAL 11 DAY)),
 (5,1,DATE_ADD(@senin, INTERVAL 11 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL 11 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL 11 DAY)),
 (9,1,DATE_ADD(@senin, INTERVAL 11 DAY)),
 (1,1,DATE_ADD(@senin, INTERVAL 12 DAY)),
 (2,1,DATE_ADD(@senin, INTERVAL 12 DAY)),
 (4,3,DATE_ADD(@senin, INTERVAL 12 DAY)),
 (5,3,DATE_ADD(@senin, INTERVAL 12 DAY)),
 (6,1,DATE_ADD(@senin, INTERVAL 12 DAY)),
 (9,1,DATE_ADD(@senin, INTERVAL 12 DAY)),
 (10,3,DATE_ADD(@senin, INTERVAL 12 DAY)),
 (2,3,DATE_ADD(@senin, INTERVAL 13 DAY)),
 (3,1,DATE_ADD(@senin, INTERVAL 13 DAY)),
 (6,1,DATE_ADD(@senin, INTERVAL 13 DAY)),
 (7,3,DATE_ADD(@senin, INTERVAL 13 DAY)),
 (8,3,DATE_ADD(@senin, INTERVAL 13 DAY)),
 (10,1,DATE_ADD(@senin, INTERVAL 13 DAY));


-- Handover shift yang sudah lewat (Dewi/SM-005 sengaja dikosongkan sebagai contoh handover tertunggak)
INSERT INTO handover (id_jadwal,id_staf,tingkat_keramaian,kondisi_area,cek_kebersihan,cek_peralatan,cek_stok,stok_menipis,kendala,catatan,waktu_isi)
SELECT j.id_jadwal, j.id_staf,
       ELT(1 + (j.id_jadwal MOD 4), 'Sepi','Normal','Ramai','Normal'),
       'Bersih & Rapi', 1, 1, 1,
       IF(j.id_jadwal MOD 5 = 0, 'Susu full cream tinggal 2 karton', NULL),
       IF(j.id_jadwal MOD 7 = 0, 'Mesin espresso sempat error, sudah di-reset', NULL),
       'Area kerja sudah dibersihkan, peralatan disimpan, dan kondisi siap diserahterimakan ke shift berikutnya.',
       TIMESTAMP(j.tanggal, s.jam_selesai)
FROM jadwal j JOIN shift_master s ON j.id_shift = s.id_shift
WHERE j.tanggal < CURDATE() AND j.id_staf <> 5;


-- Contoh pengajuan tukar shift (tukar jenis shift pada hari yang sama)
INSERT INTO tukar_shift (id_jadwal_pengaju,id_jadwal_tujuan,id_pengaju,id_tujuan,tgl_p,id_shift_p,tgl_t,id_shift_t,alasan,status,tgl_pengajuan)
SELECT a.id_jadwal,b.id_jadwal,a.id_staf,b.id_staf,a.tanggal,a.id_shift,b.tanggal,b.id_shift,'Ada keperluan keluarga di sore hari.','Menunggu Rekan',NOW()
FROM jadwal a JOIN jadwal b ON b.id_staf=2 AND b.tanggal=a.tanggal
WHERE a.id_staf=3 AND a.tanggal=DATE_ADD(@senin, INTERVAL 10 DAY);
INSERT INTO tukar_shift (id_jadwal_pengaju,id_jadwal_tujuan,id_pengaju,id_tujuan,tgl_p,id_shift_p,tgl_t,id_shift_t,alasan,status,tgl_pengajuan)
SELECT a.id_jadwal,b.id_jadwal,a.id_staf,b.id_staf,a.tanggal,a.id_shift,b.tanggal,b.id_shift,'Jadwal kuliah pengganti.','Menunggu Head Store',NOW()
FROM jadwal a JOIN jadwal b ON b.id_staf=9 AND b.tanggal=a.tanggal
WHERE a.id_staf=8 AND a.tanggal=DATE_ADD(@senin, INTERVAL 11 DAY);
