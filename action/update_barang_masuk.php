<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=barang_masuk',
]);

$id_transaksi = intval($_POST['id_transaksi'] ?? 0);
$no_invoice = trim((string) ($_POST['no_invoice'] ?? ''));
$id_produk = intval($_POST['kode_produk'] ?? 0);
$jumlah = intval($_POST['jumlah'] ?? 0);
$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$keterangan = trim((string) ($_POST['keterangan'] ?? ''));
$harga_satuan = preg_replace('/[^0-9]/', '', (string) ($_POST['harga_satuan'] ?? ''));

// Update data barang masuk
$query = "UPDATE stoktransaksi SET no_invoice = ?, id_produk = ?, jumlah = ?";
$types = "sii";
$params = [$no_invoice, $id_produk, $jumlah];

if (schema_has_column_now($koneksi, 'stoktransaksi', 'harga_satuan') && $harga_satuan !== '') {
    $query .= ", harga_satuan = ?";
    $types .= "i";
    $params[] = intval($harga_satuan);
}

$query .= ", tanggal = ?, keterangan = ? WHERE id_transaksi = ?";
$types .= "ssi";
$params[] = $tanggal;
$params[] = $keterangan;
$params[] = $id_transaksi;

$stmt = $koneksi->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    header("Location: ../index.php?page=barang_masuk&status=success");
} else {
    header("Location: ../index.php?page=barang_masuk&status=error");
}
?>
