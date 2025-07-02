<?php
include '../koneksi/koneksi.php';

$id_transaksi = $_POST['id_transaksi'];
$no_invoice = $_POST['no_invoice'];
$produk_id = $_POST['kode_produk'];
$jumlah = $_POST['jumlah'];
$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];

// Update data barang masuk
$query = "UPDATE stoktransaksi SET no_invoice = ?, produk_id = ?, jumlah = ?, tanggal = ?, keterangan = ? WHERE id_transaksi = ?";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("siissi", $no_invoice, $produk_id, $jumlah, $tanggal, $keterangan, $id_transaksi);

if ($stmt->execute()) {
    header("Location: ../index.php?page=barang_masuk&status=success");
} else {
    header("Location: ../index.php?page=barang_masuk&status=error");
}
?>
