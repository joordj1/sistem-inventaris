<?php
include '../koneksi/koneksi.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $namaGudang = $koneksi->real_escape_string($_POST['namaGudang']);
    $lokasiGudang = $koneksi->real_escape_string($_POST['lokasiGudang']);
    
    // Query untuk menyimpan data gudang
    $query = "INSERT INTO Gudang (nama_gudang, lokasi) VALUES ('$namaGudang', '$lokasiGudang')";
    
    if ($koneksi->query($query) === TRUE) {
        // Jika berhasil, kembali ke halaman data gudang
        header('Location: ../index.php?page=data_gudang');
        exit;
    } else {
        echo "Error: " . $query . "<br>" . $koneksi->error;
    }
}
?>
