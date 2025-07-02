-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 12, 2024 at 02:10 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


--
-- Database: `inventaris_barang`
--

-- --------------------------------------------------------

--
-- Table structure for table `gudang`
--

CREATE TABLE `gudang` (
  `id_gudang` int NOT NULL,
  `nama_gudang` varchar(100) NOT NULL,
  `lokasi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gudang`
--

INSERT INTO `gudang` (`id_gudang`, `nama_gudang`, `lokasi`) VALUES
(7, 'Gudang Pakaian', 'Jl. Asem Raya No. 11'),
(8, 'Gudang Bahan', 'Jl. Merdeka 10');

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`) VALUES
(18, 'Bahan Grade A'),
(19, 'Bahan Grade B'),
(20, 'Kemeja'),
(21, 'Kaos');

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id_produk` int NOT NULL,
  `kode_produk` varchar(50) NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `kategori_id` int DEFAULT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `jumlah_stok` int NOT NULL DEFAULT '0',
  `satuan` varchar(50) NOT NULL,
  `total_nilai` decimal(15,2) GENERATED ALWAYS AS ((`jumlah_stok` * `harga_satuan`)) STORED,
  `gambar_produk` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id_produk`, `kode_produk`, `nama_produk`, `kategori_id`, `harga_satuan`, `jumlah_stok`, `satuan`, `gambar_produk`) VALUES
(50, 'P-001', 'Kain Katun Premium', 18, '100000.00', 50, 'M', 'kain katun.jpeg'),
(51, 'P-002', 'Kain Katun Polyester Campuran', 19, '90000.00', 50, 'M', 'KAIN-POLYESTER.png'),
(52, 'P-003', 'Kemeja Pria', 20, '150000.00', 50, 'Pcs', 'kemeja pria.jpeg'),
(53, 'P-004', 'Kaos Unisex', 21, '80000.00', 30, 'Pcs', 'kaos.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `stokgudang`
--

CREATE TABLE `stokgudang` (
  `id_stok_gudang` int NOT NULL,
  `gudang_id` int NOT NULL,
  `produk_id` int NOT NULL,
  `jumlah_stok` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stokgudang`
--

INSERT INTO `stokgudang` (`id_stok_gudang`, `gudang_id`, `produk_id`, `jumlah_stok`) VALUES
(31, 8, 50, 8000),
(32, 8, 51, 0),
(33, 7, 52, 0),
(34, 7, 53, 0);

-- --------------------------------------------------------

--
-- Table structure for table `stoktransaksi`
--

CREATE TABLE `stoktransaksi` (
  `id_transaksi` int NOT NULL,
  `produk_id` int NOT NULL,
  `no_invoice` varchar(100) DEFAULT NULL,
  `tipe_transaksi` enum('masuk','keluar') NOT NULL,
  `jumlah` int NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stoktransaksi`
--

INSERT INTO `stoktransaksi` (`id_transaksi`, `produk_id`, `no_invoice`, `tipe_transaksi`, `jumlah`, `tanggal`, `keterangan`) VALUES
(21, 50, 'INV-001', 'masuk', 100, '2024-11-12', 'Pembelian dari PT. Indonusa\r\n'),
(22, 51, 'INV-002', 'masuk', 80, '2024-11-12', 'Pembelian dari PT. Indahbutik'),
(23, 50, 'INV-003', 'keluar', 50, '2024-11-13', 'Produk keluar gudang untuk di buatkan kemeja 50Pcs'),
(24, 52, 'INV-005', 'masuk', 50, '2024-11-14', 'Barang Masuk Dari Pabrik'),
(25, 51, 'INV-006', 'keluar', 30, '2024-11-12', 'Barang Keluar Untuk Dibuatkan kaos sebanyak 50 Pcs'),
(26, 53, 'INV-007', 'masuk', 30, '2024-11-13', 'Barang masuk Dari Gudang');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id_user` int NOT NULL,
  `nama` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('leader','admin') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id_user`, `nama`, `username`, `password`, `email`, `role`, `created_at`) VALUES
(2, 'leader1', 'leader1', '2b1e3590458a6e6014c0141b8cd13fe4', 'leader@gmail.com', 'leader', '2024-11-10 15:49:33'),
(6, 'admin1', 'admin1', 'e00cf25ad42683b3df678c61f42c6bda', 'admin1@gmail.com', 'admin', '2024-11-10 17:47:17'),
(8, 'leader2', 'leader2', '2e11722f670391d487f4c29183a3d099', 'leader2@gmail.com', 'leader', '2024-11-11 04:51:46'),
(9, 'admin2', 'admin2', 'c84258e9c39059a89ab77d846ddab909', 'admin2@gmail.com', 'admin', '2024-11-12 13:55:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `gudang`
--
ALTER TABLE `gudang`
  ADD PRIMARY KEY (`id_gudang`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD UNIQUE KEY `kode_produk` (`kode_produk`),
  ADD KEY `kategori_id` (`kategori_id`);

--
-- Indexes for table `stokgudang`
--
ALTER TABLE `stokgudang`
  ADD PRIMARY KEY (`id_stok_gudang`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `stoktransaksi`
--
ALTER TABLE `stoktransaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD UNIQUE KEY `no_invoice` (`no_invoice`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `gudang`
--
ALTER TABLE `gudang`
  MODIFY `id_gudang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `stokgudang`
--
ALTER TABLE `stokgudang`
  MODIFY `id_stok_gudang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `stoktransaksi`
--
ALTER TABLE `stoktransaksi`
  MODIFY `id_transaksi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id_kategori`) ON DELETE SET NULL;

--
-- Constraints for table `stokgudang`
--
ALTER TABLE `stokgudang`
  ADD CONSTRAINT `stokgudang_ibfk_1` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`id_gudang`) ON DELETE CASCADE,
  ADD CONSTRAINT `stokgudang_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE;

--
-- Constraints for table `stoktransaksi`
--
ALTER TABLE `stoktransaksi`
  ADD CONSTRAINT `stoktransaksi_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
