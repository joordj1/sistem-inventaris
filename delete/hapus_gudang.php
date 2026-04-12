<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_gudang',
]);

$id_gudang = intval($_GET['id_gudang'] ?? 0);

if ($id_gudang < 1) {
    echo json_encode(['success' => false, 'message' => 'ID gudang tidak valid.']);
    exit;
}

// Cek apakah gudang masih memiliki produk/stok terdaftar
$cekProdukStmt = $koneksi->prepare("SELECT COUNT(*) AS cnt FROM stokgudang WHERE id_gudang = ? AND jumlah_stok > 0 LIMIT 1");
if ($cekProdukStmt) {
    $cekProdukStmt->bind_param('i', $id_gudang);
    $cekProdukStmt->execute();
    $cekRow = $cekProdukStmt->get_result()->fetch_assoc();
    if (intval($cekRow['cnt'] ?? 0) > 0) {
        echo json_encode(['success' => false, 'message' => 'Gudang tidak dapat dihapus karena masih memiliki stok barang. Kosongkan stok terlebih dahulu.']);
        exit;
    }
}

$gudang = $koneksi->query("SELECT id_gudang, nama_gudang, lokasi FROM gudang WHERE id_gudang = " . $id_gudang)->fetch_assoc();

// Query untuk menghapus data gudang
$stmt = $koneksi->prepare("DELETE FROM Gudang WHERE id_gudang = ?");
$stmt->bind_param('i', $id_gudang);
if ($stmt->execute()) {
    log_activity($koneksi, [
        'id_user' => $_SESSION['id_user'] ?? null,
        'role_user' => get_current_user_role(),
        'action_name' => 'gudang_hapus',
        'entity_type' => 'gudang',
        'entity_id' => $id_gudang,
        'entity_label' => $gudang['nama_gudang'] ?? ('Gudang #' . $id_gudang),
        'description' => 'Menghapus data gudang',
        'id_gudang' => $id_gudang,
        'metadata_json' => $gudang,
    ]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus gudang']);
}
?>
