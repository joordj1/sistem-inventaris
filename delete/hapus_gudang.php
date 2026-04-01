<?php
include '../koneksi/koneksi.php';

$id_gudang = $_GET['id_gudang'];

// Query untuk menghapus data gudang
$query = "DELETE FROM Gudang WHERE id_gudang = $id_gudang";
if ($koneksi->query($query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus gudang']);
}
?>
