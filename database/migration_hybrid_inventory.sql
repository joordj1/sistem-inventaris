-- Priority 1 migration: role, deskripsi barang, catatan, dan audit log

ALTER TABLE user
  MODIFY COLUMN role ENUM('admin','petugas','viewer','leader','user') NOT NULL DEFAULT 'viewer';

UPDATE user
SET role = 'petugas'
WHERE role IN ('leader', 'user');

ALTER TABLE user
  MODIFY COLUMN role ENUM('admin','petugas','viewer') NOT NULL DEFAULT 'viewer';

ALTER TABLE produk
  ADD COLUMN IF NOT EXISTS deskripsi TEXT NULL AFTER nama_produk;

CREATE TABLE IF NOT EXISTS catatan_inventaris (
  id_catatan INT NOT NULL AUTO_INCREMENT,
  tipe_target ENUM('produk','transaksi','unit','gudang') NOT NULL DEFAULT 'produk',
  kategori_catatan ENUM('umum','kerusakan','selisih','servis','transaksi','bug') NOT NULL DEFAULT 'umum',
  judul VARCHAR(150) DEFAULT NULL,
  catatan TEXT NOT NULL,
  id_produk INT DEFAULT NULL,
  id_transaksi INT DEFAULT NULL,
  id_unit_barang INT DEFAULT NULL,
  id_gudang INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_catatan),
  INDEX idx_catatan_target (tipe_target, kategori_catatan),
  INDEX idx_catatan_produk (id_produk),
  INDEX idx_catatan_transaksi (id_transaksi),
  INDEX idx_catatan_unit (id_unit_barang),
  INDEX idx_catatan_gudang (id_gudang),
  INDEX idx_catatan_created_by (created_by),
  INDEX idx_catatan_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id_log INT NOT NULL AUTO_INCREMENT,
  id_user INT DEFAULT NULL,
  role_user VARCHAR(50) DEFAULT NULL,
  action_name VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id INT DEFAULT NULL,
  entity_label VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  id_produk INT DEFAULT NULL,
  id_transaksi INT DEFAULT NULL,
  id_unit_barang INT DEFAULT NULL,
  id_gudang INT DEFAULT NULL,
  metadata_json LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  INDEX idx_activity_entity (entity_type, entity_id),
  INDEX idx_activity_action (action_name),
  INDEX idx_activity_user (id_user),
  INDEX idx_activity_produk (id_produk),
  INDEX idx_activity_transaksi (id_transaksi),
  INDEX idx_activity_unit (id_unit_barang),
  INDEX idx_activity_gudang (id_gudang),
  INDEX idx_activity_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
