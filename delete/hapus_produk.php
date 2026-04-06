<?php
include '../koneksi/koneksi.php'; 
require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_produk',
]);

if (isset($_GET['id_produk'])) {
    $id_produk = intval($_GET['id_produk']);

    $produk = null;
    $produkStmt = $koneksi->prepare("SELECT id_produk, kode_produk, nama_produk, id_gudang FROM produk WHERE id_produk = ?");
    if ($produkStmt) {
        $produkStmt->bind_param('i', $id_produk);
        $produkStmt->execute();
        $produkResult = $produkStmt->get_result();
        $produk = $produkResult ? $produkResult->fetch_assoc() : null;
    }

    $stmt = $koneksi->prepare("DELETE FROM produk WHERE id_produk = ?");
    $stmt->bind_param('i', $id_produk);

    if ($stmt->execute()) {
        log_activity($koneksi, [
            'id_user' => $_SESSION['id_user'] ?? null,
            'role_user' => get_current_user_role(),
            'action_name' => 'produk_hapus',
            'entity_type' => 'produk',
            'entity_id' => $id_produk,
            'entity_label' => trim((string) (($produk['kode_produk'] ?? '') . ' - ' . ($produk['nama_produk'] ?? ''))),
            'description' => 'Menghapus data barang',
            'id_produk' => $id_produk,
            'id_gudang' => $produk['id_gudang'] ?? null,
            'metadata_json' => $produk,
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>
