<?php
session_start();
include '../koneksi/koneksi.php';

$no_invoice = $_POST['no_invoice'];
$id_produk = $_POST['kode_produk'];
$jumlah = $_POST['jumlah'];
$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];

// Validasi tipe item (hanya consumable boleh keluar stok)
$produkTipe = $koneksi->query("SELECT tipe_barang FROM produk WHERE id_produk = $id_produk")->fetch_assoc();
if (!$produkTipe || $produkTipe['tipe_barang'] !== 'consumable') {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Hanya produk consumable yang bisa diproses sebagai barang keluar");
    exit();
}

// Ambil status awal untuk tracking
$produk = $koneksi->query("SELECT kode_produk, status, kondisi, lokasi_custom, id_gudang, id_user, jumlah_stok FROM produk WHERE id_produk = $id_produk")->fetch_assoc();

// Query untuk mengurangi stok produk
$queryKurangiStok = "UPDATE produk SET jumlah_stok = jumlah_stok - $jumlah, status = CASE WHEN jumlah_stok - $jumlah <= 0 THEN 'dipinjam' ELSE status END, tersedia = CASE WHEN jumlah_stok - $jumlah <= 0 THEN 0 ELSE tersedia END, last_tracked_at = NOW() WHERE id_produk = $id_produk";
$koneksi->query($queryKurangiStok);

// Simpan data barang keluar ke tabel stoktransaksi
$queryInsertTransaksi = "INSERT INTO stoktransaksi (no_invoice, id_produk, jumlah, tanggal, keterangan, tipe_transaksi) 
                         VALUES ('$no_invoice', $id_produk, $jumlah, '$tanggal', '$keterangan', 'keluar')";
$koneksi->query($queryInsertTransaksi);

// catat tracking
$operator = $_SESSION['id_user'] ?? null;
$location = '';
if ($produk['id_gudang']) {
    $lok = $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = " . intval($produk['id_gudang']))->fetch_assoc();
    $location = $lok['nama_gudang'] ?? '';
} elseif (!empty($produk['lokasi_custom'])) {
    $location = $produk['lokasi_custom'];
}
$status_sesudah = ($produk['jumlah_stok'] - $jumlah <= 0) ? 'dipinjam' : 'tersedia';
log_tracking_history($koneksi, [
    'id_produk' => $id_produk,
    'kode_produk' => $produk['kode_produk'] ?? null,
    'status_sebelum' => $produk['status'],
    'status_sesudah' => $status_sesudah,
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

header('Location: ../index.php?page=barang_keluar');
exit();
?>
