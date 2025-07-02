<?php
include '../koneksi/koneksi.php'; 

if (isset($_GET['id_produk'])) {
    $id_produk = $_GET['id_produk'];

    // Query untuk menghapus produk dari tabel produk
    $sql = "DELETE FROM produk WHERE id_produk = $id_produk";

    if ($koneksi->query($sql) === TRUE) {
        // Mengembalikan respons JSON jika penghapusan berhasil
        echo json_encode(['success' => true]);
    } else {
        // Mengembalikan respons JSON jika penghapusan gagal
        echo json_encode(['success' => false]);
    }
}
?>
