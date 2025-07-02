<?php
// Menghubungkan ke database
include '../koneksi/koneksi.php';

$id_gudang = $_GET['id_gudang'];

// Mengecek apakah ada produk di gudang ini
$query = "SELECT COUNT(*) AS total_produk FROM StokGudang WHERE gudang_id = $id_gudang";
$result = $koneksi->query($query);
$row = $result->fetch_assoc();

// Mengembalikan hasil dalam format JSON
if ($row['total_produk'] > 0) {
    echo json_encode(['hasProduk' => true]);
} else {
    echo json_encode(['hasProduk' => false]);
}
?>
