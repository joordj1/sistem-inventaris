-- ==============================
-- CONFIG
-- ==============================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ==============================
-- MASTER
-- ==============================

CREATE TABLE IF NOT EXISTS bidang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_bidang VARCHAR(150) NOT NULL UNIQUE,
  kode_bidang VARCHAR(50) UNIQUE,
  status ENUM('aktif','nonaktif') DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gudang (
  id_gudang INT AUTO_INCREMENT PRIMARY KEY,
  nama_gudang VARCHAR(100) NOT NULL,
  lokasi VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kategori (
  id_kategori INT AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user (
  id_user INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(255) NOT NULL,
  username VARCHAR(255) UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE,
  role ENUM('admin','petugas','viewer','leader','user') DEFAULT 'user',
  status ENUM('aktif','nonaktif') DEFAULT 'aktif',
  kategori_user ENUM('staff','dosen','mahasiswa','umum') DEFAULT 'umum',
  bidang_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME,
  FOREIGN KEY (bidang_id) REFERENCES bidang(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================
-- PRODUK
-- ==============================

CREATE TABLE IF NOT EXISTS produk (
  id_produk INT AUTO_INCREMENT PRIMARY KEY,
  kode_produk VARCHAR(50) UNIQUE,
  nama_produk VARCHAR(100) NOT NULL,
  deskripsi TEXT,
  id_kategori INT,
  id_gudang INT,
  id_user INT,
  dipinjam_oleh INT,

  status ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif') DEFAULT 'tersedia',
  kondisi ENUM('baik','rusak','diperbaiki','usang','lainnya') DEFAULT 'baik',
  tipe_barang ENUM('consumable','asset') DEFAULT 'consumable',

  jumlah_stok INT DEFAULT 0,
  harga_default DECIMAL(15,2) DEFAULT 0,
  harga_satuan DECIMAL(15,2),

  FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE SET NULL,
  FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang) ON DELETE SET NULL,
  FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE SET NULL,
  FOREIGN KEY (dipinjam_oleh) REFERENCES user(id_user) ON DELETE SET NULL,

  CONSTRAINT chk_produk_jumlah CHECK (jumlah_stok >= 0)
) ENGINE=InnoDB;

-- ==============================
-- STOK
-- ==============================

CREATE TABLE IF NOT EXISTS stokgudang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_gudang INT,
  id_produk INT,
  jumlah_stok INT DEFAULT 0,
  FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang),
  FOREIGN KEY (id_produk) REFERENCES produk(id_produk),
  CONSTRAINT chk_stok CHECK (jumlah_stok >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stoktransaksi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_produk INT,
  tipe_transaksi ENUM('masuk','keluar'),
  jumlah INT,
  FOREIGN KEY (id_produk) REFERENCES produk(id_produk),
  CONSTRAINT chk_transaksi CHECK (jumlah > 0)
) ENGINE=InnoDB;

-- ==============================
-- UNIT
-- ==============================

CREATE TABLE IF NOT EXISTS unit_barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_produk INT,
  serial_number VARCHAR(100),
  kode_qrcode VARCHAR(255),
  qr_hash VARCHAR(64),
  FOREIGN KEY (id_produk) REFERENCES produk(id_produk)
) ENGINE=InnoDB;

-- ==============================
-- TRANSAKSI UTAMA
-- ==============================

CREATE TABLE IF NOT EXISTS peminjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_produk INT,
  id_user INT,
  FOREIGN KEY (id_produk) REFERENCES produk(id_produk),
  FOREIGN KEY (id_user) REFERENCES user(id_user)
) ENGINE=InnoDB;

-- ==============================
-- DATA AWAL
-- ==============================

INSERT IGNORE INTO gudang (id_gudang,nama_gudang,lokasi) VALUES
(7,'Gudang Pakaian','Jl. Asem Raya'),
(8,'Gudang Bahan','Jl. Merdeka');

INSERT IGNORE INTO kategori (id_kategori,nama_kategori) VALUES
(18,'Bahan A'),
(19,'Bahan B');

INSERT IGNORE INTO user (id_user,nama,username,password,role) VALUES
(1,'admin','admin','123','admin');