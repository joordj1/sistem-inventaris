<?php
include '../koneksi/koneksi.php'; 
require_auth_roles(['admin'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

if (isset($_GET['id_user'])) {
    $id_user = intval($_GET['id_user']);

    if (!empty($_SESSION['id_user']) && intval($_SESSION['id_user']) === $id_user) {
        echo json_encode(['success' => false, 'error' => 'User aktif tidak boleh menghapus akunnya sendiri.']);
        exit;
    }

    $stmt = $koneksi->prepare("DELETE FROM user WHERE id_user = ?");
    $stmt->bind_param('i', $id_user);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'id_user tidak ditemukan']);
}
?>
