-- Migration untuk fitur tracking barang

ALTER TABLE produk
  ADD COLUMN status ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif') NOT NULL DEFAULT 'tersedia',
  ADD COLUMN kondisi ENUM('baik','rusak','diperbaiki','usang','lainnya') NOT NULL DEFAULT 'baik',
  ADD COLUMN id_gudang INT NULL,
  ADD COLUMN lokasi_custom VARCHAR(255) NULL,
  ADD COLUMN id_user INT NULL,
  ADD COLUMN tersedia TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN last_tracked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS tracking_barang (
  id_tracking INT NOT NULL AUTO_INCREMENT,
  id_produk INT NOT NULL,
  kode_barang VARCHAR(100) DEFAULT NULL,
  status_sebelum ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif') DEFAULT NULL,
  status_sesudah ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif') DEFAULT NULL,
  kondisi_sebelum ENUM('baik','rusak','diperbaiki','usang','lainnya') DEFAULT NULL,
  kondisi_sesudah ENUM('baik','rusak','diperbaiki','usang','lainnya') DEFAULT NULL,
  lokasi_sebelum VARCHAR(255) DEFAULT NULL,
  lokasi_sesudah VARCHAR(255) DEFAULT NULL,
  id_user_sebelum INT DEFAULT NULL,
  id_user_sesudah INT DEFAULT NULL,
  id_user_terkait INT DEFAULT NULL,
  activity_type ENUM('tambah','pindah','pinjam','kembali','perbaikan','rusak','update','keluarmasuk','arsip') NOT NULL,
  note TEXT DEFAULT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_user_changed INT DEFAULT NULL,
  PRIMARY KEY (id_tracking),
  INDEX idx_id_produk (id_produk),
  INDEX idx_id_user_sebelum (id_user_sebelum),
  INDEX idx_id_user_sesudah (id_user_sesudah),
  INDEX idx_id_user_terkait (id_user_terkait),
  INDEX idx_id_user_changed (id_user_changed),
  CONSTRAINT tracking_produk_fk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
  CONSTRAINT tracking_user_sebelum_fk FOREIGN KEY (id_user_sebelum) REFERENCES user(id_user) ON DELETE SET NULL,
  CONSTRAINT tracking_user_sesudah_fk FOREIGN KEY (id_user_sesudah) REFERENCES user(id_user) ON DELETE SET NULL,
  CONSTRAINT tracking_user_terkait_fk FOREIGN KEY (id_user_terkait) REFERENCES user(id_user) ON DELETE SET NULL,
  CONSTRAINT tracking_user_changed_fk FOREIGN KEY (id_user_changed) REFERENCES user(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS peminjaman (
  id_peminjaman INT NOT NULL AUTO_INCREMENT,
  id_produk INT NOT NULL,
  id_user INT NOT NULL,
  jumlah INT NOT NULL,
  id_gudang INT DEFAULT NULL,
  tanggal_pinjam DATE NOT NULL,
  tanggal_kembali_rencana DATE DEFAULT NULL,
  tanggal_kembali_aktual DATE DEFAULT NULL,
  status ENUM('dipinjam','kembali','terlambat','dibatalkan') NOT NULL DEFAULT 'dipinjam',
  catatan TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_user_created INT DEFAULT NULL,
  PRIMARY KEY (id_peminjaman),
  INDEX idx_id_produk (id_produk),
  INDEX idx_id_user (id_user),
  INDEX idx_id_gudang (id_gudang),
  INDEX idx_id_user_created (id_user_created),
  CONSTRAINT peminjaman_produk_fk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
  CONSTRAINT peminjaman_user_fk FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE CASCADE,
  CONSTRAINT peminjaman_gudang_fk FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang) ON DELETE SET NULL,
  CONSTRAINT peminjaman_user_created_fk FOREIGN KEY (id_user_created) REFERENCES user(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Jika Anda ingin langsung menyesuaikan data lama, jalankan:
-- UPDATE produk p
-- JOIN stokgudang sg ON sg.id_produk = p.id_produk
-- SET p.id_gudang = sg.id_gudang;


-- Perbarui skema default pada dump lama.
