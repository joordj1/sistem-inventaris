<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=kategori_barang',
]);

if (isset($_GET['id_kategori'])) {
    $id_kategori = intval($_GET['id_kategori']);

    // Cek apakah ada produk terkait dengan kategori ini
    $checkProductQuery = "SELECT COUNT(*) AS product_count FROM produk WHERE id_kategori = $id_kategori";
    $result = $koneksi->query($checkProductQuery);
    $row = $result->fetch_assoc();

    if ($row['product_count'] > 0) {
        // Jika ada produk terkait, kirimkan pesan kesalahan
        echo json_encode([
            'success' => false,
            'message' => 'Kategori tidak dapat dihapus, terdapat produk dengan kategori ini. Ubah terlebih dahulu kategori pada produk tersebut.'
        ]);
    } else {
        // Jika tidak ada produk terkait, lanjutkan penghapusan kategori
        $deleteQuery = "DELETE FROM kategori WHERE id_kategori = $id_kategori";
        if ($koneksi->query($deleteQuery) === TRUE) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus kategori.']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID kategori tidak ditemukan.']);
}
?>
