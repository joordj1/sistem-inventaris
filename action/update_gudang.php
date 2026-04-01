<?php
include '../koneksi/koneksi.php';

// Mendapatkan data dari form
$id_gudang = $_POST['id_gudang'];
$nama_gudang = $_POST['nama_gudang'];
$lokasi = $_POST['lokasi'];

// Query untuk update data gudang
$query = "UPDATE gudang SET nama_gudang = '$nama_gudang', lokasi = '$lokasi' WHERE id_gudang = '$id_gudang'";

if ($koneksi->query($query) === TRUE) {
    header("Location: ../index.php?page=data_gudang"); // Redirect ke halaman data gudang setelah update
    exit;
} else {
    echo "Error: " . $query . "<br>" . $koneksi->error;
}
?>
