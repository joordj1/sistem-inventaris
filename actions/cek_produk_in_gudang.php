<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas', 'viewer'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_gudang',
]);

$id_gudang = intval($_GET['id_gudang'] ?? 0);

// Mengecek apakah ada produk di gudang ini
$stmt = $koneksi->prepare("SELECT COUNT(*) AS total_produk FROM StokGudang WHERE id_gudang = ?");
$stmt->bind_param('i', $id_gudang);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// Mengembalikan hasil dalam format JSON
if ($row['total_produk'] > 0) {
    echo json_encode(['hasProduk' => true]);
} else {
    echo json_encode(['hasProduk' => false]);
}
?>
