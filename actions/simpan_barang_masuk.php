<?php
require '../koneksi/koneksi.php';

$no_invoice = $_POST['no_invoice'];
$produk_id = $_POST['kode_produk'];
$jumlah = $_POST['jumlah'];
$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];

// Cek apakah No Invoice sudah ada
$cekInvoice = $koneksi->query("SELECT * FROM stoktransaksi WHERE no_invoice = '$no_invoice'");
if ($cekInvoice->num_rows > 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=No Invoice sudah ada");
    exit();
}

// Tambahkan data barang masuk ke tabel stoktransaksi
$query = "INSERT INTO stoktransaksi (no_invoice, produk_id, jumlah, tanggal, keterangan, tipe_transaksi) 
          VALUES ('$no_invoice', '$produk_id', '$jumlah', '$tanggal', '$keterangan', 'masuk')";
$koneksi->query($query);

// Update jumlah stok di tabel produk
$koneksi->query("UPDATE produk SET jumlah_stok = jumlah_stok + $jumlah WHERE id_produk = '$produk_id'");

header("Location: ../index.php?page=barang_masuk&success=Barang masuk berhasil ditambahkan");
?>
