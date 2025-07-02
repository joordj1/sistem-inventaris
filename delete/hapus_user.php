<?php
include '../koneksi/koneksi.php'; 

if (isset($_GET['id_user'])) {
    $id_user = $_GET['id_user'];

    // Query untuk menghapus user dari tabel user
    $sql = "DELETE FROM user WHERE id_user = $id_user";

    if ($koneksi->query($sql) === TRUE) {
        // Mengembalikan respons JSON jika penghapusan berhasil
        echo json_encode(['success' => true]);
    } else {
        // Mengembalikan respons JSON jika penghapusan gagal
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'id_user tidak ditemukan']);
}
?>
