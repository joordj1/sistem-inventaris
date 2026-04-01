<?php
session_start();
require '../koneksi/koneksi.php';

$no_invoice = $_POST['no_invoice'];
$id_produk = $_POST['kode_produk'];
$jumlah = $_POST['jumlah'];
$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];

// Validasi tipe item (hanya consumable boleh masuk stok)
$produk = $koneksi->query("SELECT tipe_barang FROM produk WHERE id_produk = '$id_produk'")->fetch_assoc();
if (!$produk || $produk['tipe_barang'] !== 'consumable') {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Hanya produk consumable yang bisa diproses sebagai barang masuk");
    exit();
}

// Cek apakah No Invoice sudah ada
$cekInvoice = $koneksi->query("SELECT * FROM stoktransaksi WHERE no_invoice = '$no_invoice'");
if ($cekInvoice->num_rows > 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=No Invoice sudah ada");
    exit();
}

// Tambahkan data barang masuk ke tabel stoktransaksi
$query = "INSERT INTO stoktransaksi (no_invoice, id_produk, jumlah, tanggal, keterangan, tipe_transaksi) 
          VALUES ('$no_invoice', '$id_produk', '$jumlah', '$tanggal', '$keterangan', 'masuk')";
$koneksi->query($query);

// Update jumlah stok di tabel produk
$koneksi->query("UPDATE produk SET jumlah_stok = jumlah_stok + $jumlah, status = 'tersedia', tersedia = 1, last_tracked_at = NOW() WHERE id_produk = '$id_produk'");

// catat tracking
$produk = $koneksi->query("SELECT kode_produk, status, kondisi, lokasi_custom, id_gudang, id_user FROM produk WHERE id_produk = '$id_produk'")->fetch_assoc();
$operator = $_SESSION['id_user'] ?? null;
$location = '';
if ($produk['id_gudang']) {
    $lok = $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = " . intval($produk['id_gudang']))->fetch_assoc();
    $location = $lok['nama_gudang'] ?? '';
} elseif (!empty($produk['lokasi_custom'])) {
    $location = $produk['lokasi_custom'];
}
log_tracking_history($koneksi, [
    'id_produk' => $id_produk,
    'kode_produk' => $produk['kode_produk'] ?? null,
    'status_sebelum' => $produk['status'],
    'status_sesudah' => 'tersedia',
    'kondisi_sebelum' => $produk['kondisi'],
    'kondisi_sesudah' => $produk['kondisi'],
    'lokasi_sebelum' => $location,
    'lokasi_sesudah' => $location,
    'id_user_sebelum' => $produk['id_user'],
    'id_user_sesudah' => $produk['id_user'],
    'id_user_terkait' => $produk['id_user'],
    'activity_type' => 'keluarmasuk',
    'note' => $keterangan,
    'id_user_changed' => $operator
]);

header("Location: ../index.php?page=barang_masuk&success=Barang masuk berhasil ditambahkan");
?>
