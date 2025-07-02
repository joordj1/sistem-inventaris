<?php
include '../koneksi/koneksi.php';

$no_invoice = $_POST['no_invoice'];
$produk_id = $_POST['kode_produk'];
$jumlah = $_POST['jumlah'];
$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];

// Query untuk mengurangi stok produk
$queryKurangiStok = "UPDATE produk SET jumlah_stok = jumlah_stok - $jumlah WHERE id_produk = $produk_id";
$koneksi->query($queryKurangiStok);

// Simpan data barang keluar ke tabel stoktransaksi
$queryInsertTransaksi = "INSERT INTO stoktransaksi (no_invoice, produk_id, jumlah, tanggal, keterangan, tipe_transaksi) 
                         VALUES ('$no_invoice', $produk_id, $jumlah, '$tanggal', '$keterangan', 'keluar')";
$koneksi->query($queryInsertTransaksi);

header('Location: ../index.php?page=barang_keluar');
exit();
?>
