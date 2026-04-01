-- Hybrid Inventory Upgrade Migration
-- Safe idempotent structure additions for consumable + asset support

/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE */;
/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- 1) tipe_barang in produk
ALTER TABLE produk 
  ADD COLUMN IF NOT EXISTS tipe_barang ENUM('consumable','asset') NOT NULL DEFAULT 'consumable';

-- Optional lokasi master table for generic location management
CREATE TABLE IF NOT EXISTS lokasi (
  id_lokasi INT NOT NULL AUTO_INCREMENT,
  nama_lokasi VARCHAR(255) NOT NULL,
  keterangan TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_lokasi),
  UNIQUE KEY uq_lokasi_nama (nama_lokasi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) unit_barang table: per physical asset unit
CREATE TABLE IF NOT EXISTS unit_barang (
  id_unit_barang INT NOT NULL AUTO_INCREMENT,
  id_produk INT NOT NULL,
  serial_number VARCHAR(100) NULL,
  kode_qrcode VARCHAR(255) NULL,
  id_gudang INT NULL,
  id_lokasi INT NULL,
  lokasi_custom VARCHAR(255) NULL,
  status ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif') NOT NULL DEFAULT 'tersedia',
  kondisi ENUM('baik','rusak','diperbaiki','usang','lainnya') NOT NULL DEFAULT 'baik',
  id_user INT NULL,
  tersedia TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id_unit_barang),
  UNIQUE KEY uq_unit_barang_qrcode (kode_qrcode),
  UNIQUE KEY uq_unit_barang_serial (serial_number),
  INDEX idx_unit_barang_produk (id_produk),
  INDEX idx_unit_barang_gudang (id_gudang),
  INDEX idx_unit_barang_lokasi (id_lokasi),
  INDEX idx_unit_barang_user (id_user),
  CONSTRAINT fk_unit_barang_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
  CONSTRAINT fk_unit_barang_gudang FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang) ON DELETE SET NULL,
  CONSTRAINT fk_unit_barang_lokasi FOREIGN KEY (id_lokasi) REFERENCES lokasi(id_lokasi) ON DELETE SET NULL,
  CONSTRAINT fk_unit_barang_user FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) riwayat_unit_barang table: unit movement and usage history
CREATE TABLE IF NOT EXISTS riwayat_unit_barang (
  id_riwayat INT NOT NULL AUTO_INCREMENT,
  id_unit_barang INT NOT NULL,
  id_produk INT NOT NULL,
  activity_type ENUM('tambah','pinjam','kembali','pindah','perbaikan','rusak','update','arsip') NOT NULL,
  status_sebelum ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif') NULL,
  status_sesudah ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif') NULL,
  kondisi_sebelum ENUM('baik','rusak','diperbaiki','usang','lainnya') NULL,
  kondisi_sesudah ENUM('baik','rusak','diperbaiki','usang','lainnya') NULL,
  lokasi_sebelum VARCHAR(255) NULL,
  lokasi_sesudah VARCHAR(255) NULL,
  id_user_sebelum INT NULL,
  id_user_sesudah INT NULL,
  id_user_terkait INT NULL,
  id_user_changed INT NULL,
  note TEXT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_riwayat),
  INDEX idx_riwayat_unit (id_unit_barang),
  INDEX idx_riwayat_produk (id_produk),
  INDEX idx_riwayat_user_changed (id_user_changed),
  CONSTRAINT fk_riwayat_unit_barang_unit FOREIGN KEY (id_unit_barang) REFERENCES unit_barang(id_unit_barang) ON DELETE CASCADE,
  CONSTRAINT fk_riwayat_unit_barang_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
  CONSTRAINT fk_riwayat_unit_barang_user_changed FOREIGN KEY (id_user_changed) REFERENCES user(id_user) ON DELETE SET NULL,
  CONSTRAINT fk_riwayat_unit_barang_user_sebelum FOREIGN KEY (id_user_sebelum) REFERENCES user(id_user) ON DELETE SET NULL,
  CONSTRAINT fk_riwayat_unit_barang_user_sesudah FOREIGN KEY (id_user_sesudah) REFERENCES user(id_user) ON DELETE SET NULL,
  CONSTRAINT fk_riwayat_unit_barang_user_terkait FOREIGN KEY (id_user_terkait) REFERENCES user(id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
