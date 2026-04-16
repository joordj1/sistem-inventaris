ALTER TABLE produk
  ADD COLUMN IF NOT EXISTS dipinjam_oleh INT NULL AFTER id_user,
  ADD COLUMN IF NOT EXISTS tanggal_pinjam DATETIME NULL AFTER dipinjam_oleh,
  ADD COLUMN IF NOT EXISTS tanggal_kembali DATETIME NULL AFTER tanggal_pinjam;

CREATE TABLE IF NOT EXISTS perbaikan_barang (
  id_perbaikan INT NOT NULL AUTO_INCREMENT,
  id_produk INT NOT NULL,
  id_unit_barang INT DEFAULT NULL,
  tanggal_mulai DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tanggal_selesai DATETIME DEFAULT NULL,
  deskripsi TEXT DEFAULT NULL,
  status ENUM('proses','selesai','tidak_dapat_diperbaiki') NOT NULL DEFAULT 'proses',
  created_by INT DEFAULT NULL,
  updated_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_perbaikan),
  KEY idx_perbaikan_produk (id_produk),
  KEY idx_perbaikan_unit (id_unit_barang),
  KEY idx_perbaikan_status (status),
  KEY idx_perbaikan_created_by (created_by),
  KEY idx_perbaikan_updated_by (updated_by),
  CONSTRAINT fk_perbaikan_produk_foundation FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_perbaikan_unit_foundation FOREIGN KEY (id_unit_barang) REFERENCES unit_barang(id_unit_barang) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_perbaikan_created_by_foundation FOREIGN KEY (created_by) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_perbaikan_updated_by_foundation FOREIGN KEY (updated_by) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE produk
  ADD KEY idx_produk_id_gudang (id_gudang),
  ADD KEY idx_produk_id_user (id_user),
  ADD KEY idx_produk_dipinjam_oleh (dipinjam_oleh),
  ADD CONSTRAINT fk_produk_gudang_foundation FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_produk_user_foundation FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_produk_dipinjam_oleh_foundation FOREIGN KEY (dipinjam_oleh) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE OR REPLACE VIEW users AS
SELECT
  id_user AS id,
  nama,
  username,
  password,
  role,
  status,
  kategori_user,
  bidang_id AS bidang
FROM user;

CREATE OR REPLACE VIEW barang AS
SELECT
  p.id_produk AS id,
  p.kode_produk AS kode_barang,
  p.nama_produk AS nama,
  p.deskripsi,
  p.id_gudang AS gudang_id,
  COALESCE(p.dipinjam_oleh, p.id_user) AS dipinjam_oleh,
  p.tanggal_pinjam,
  p.tanggal_kembali,
  CASE
    WHEN LOWER(TRIM(COALESCE(p.status, ''))) IN ('dipinjam', 'sedang digunakan', 'digunakan') THEN 'dipinjam'
    WHEN LOWER(TRIM(COALESCE(p.status, ''))) IN ('rusak') THEN 'rusak'
    WHEN LOWER(TRIM(COALESCE(p.status, ''))) IN ('dalam perbaikan', 'perbaikan', 'diperbaiki') THEN 'diperbaiki'
    ELSE 'tersedia'
  END AS status
FROM produk p;

CREATE OR REPLACE VIEW log_barang AS
SELECT
  tb.id_tracking AS id,
  tb.id_produk AS barang_id,
  COALESCE(tb.id_user_changed, tb.id_user_sesudah, tb.id_user_terkait, tb.id_user_sebelum) AS user_id,
  tb.activity_type AS aksi,
  COALESCE(tb.changed_at, tb.created_at) AS tanggal,
  tb.note AS catatan
FROM tracking_barang tb;

CREATE OR REPLACE VIEW mutasi AS
SELECT
  d.id AS id,
  d.produk_id AS barang_id,
  h.gudang_asal_id AS dari_gudang,
  h.gudang_tujuan_id AS ke_gudang,
  h.tanggal_mutasi AS tanggal
FROM mutasi_barang_detail d
INNER JOIN mutasi_barang h ON h.id = d.mutasi_id;

CREATE OR REPLACE VIEW serah_terima AS
SELECT
  d.id AS id,
  d.produk_id AS barang_id,
  h.pihak_penerima_user_id AS user_id,
  h.tanggal_serah_terima AS tanggal,
  h.status
FROM serah_terima_detail d
INNER JOIN serah_terima_barang h ON h.id = d.serah_terima_id;

CREATE OR REPLACE VIEW perbaikan AS
SELECT
  id_perbaikan AS id,
  id_produk AS barang_id,
  tanggal_mulai,
  tanggal_selesai,
  deskripsi,
  status
FROM perbaikan_barang;