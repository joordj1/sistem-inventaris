<?php
include '../koneksi/koneksi.php'; 
require_auth_roles(['admin'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);
header('Content-Type: application/json');

if (schema_table_exists_now($koneksi, 'user')) {
    if (!schema_has_column_now($koneksi, 'user', 'deleted_at')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN deleted_at DATETIME NULL AFTER created_at");
    }
    if (!schema_has_column_now($koneksi, 'user', 'updated_at')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER deleted_at");
    }
}

if (!schema_has_column_now($koneksi, 'user', 'deleted_at')) {
    echo json_encode(['success' => false, 'error' => 'Skema soft delete user belum siap. Jalankan migration_user_soft_delete.sql terlebih dahulu.']);
    exit;
}

if (isset($_GET['id_user'])) {
    $id_user = intval($_GET['id_user']);

    if (!empty($_SESSION['id_user']) && intval($_SESSION['id_user']) === $id_user) {
        echo json_encode(['success' => false, 'error' => 'User aktif tidak boleh menghapus akunnya sendiri.']);
        exit;
    }

    $statusStmt = $koneksi->prepare("SELECT deleted_at FROM user WHERE id_user = ? LIMIT 1");
    if ($statusStmt) {
        $statusStmt->bind_param('i', $id_user);
        $statusStmt->execute();
        $statusResult = $statusStmt->get_result();
        $statusRow = $statusResult ? $statusResult->fetch_assoc() : null;

        if (!$statusRow) {
            echo json_encode(['success' => false, 'error' => 'Data user tidak ditemukan.']);
            exit;
        }

        if (!empty($statusRow['deleted_at'])) {
            echo json_encode(['success' => false, 'error' => 'User sudah berstatus nonaktif.']);
            exit;
        }
    }

    if (soft_delete_inventory_user($koneksi, $id_user)) {
        log_activity($koneksi, [
            'id_user' => current_user_id(),
            'role_user' => get_current_user_role(),
            'action_name' => 'user_soft_delete',
            'entity_type' => 'user',
            'entity_id' => $id_user,
            'entity_label' => get_user_name_by_id($koneksi, $id_user) ?? ('User #' . $id_user),
            'description' => 'User di-soft delete untuk menjaga histori tetap aman.',
            'actor_name_snapshot' => get_current_user_name($koneksi) ?? 'System',
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User gagal dinonaktifkan secara aman.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'id_user tidak ditemukan']);
}
?>
