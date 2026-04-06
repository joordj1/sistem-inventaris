<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_gudang',
]);

$id_gudang = intval($_GET['id_gudang'] ?? 0);
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
