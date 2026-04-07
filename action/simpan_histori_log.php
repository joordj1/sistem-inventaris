<?php
include '../koneksi/koneksi.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$saved = save_histori_log_entry($koneksi, [
    'ref_type' => $_POST['ref_type'] ?? null,
    'ref_id' => $_POST['ref_id'] ?? null,
    'event_type' => $_POST['event_type'] ?? null,
    'produk_id' => $_POST['produk_id'] ?? null,
    'unit_barang_id' => $_POST['unit_barang_id'] ?? null,
    'gudang_id' => $_POST['gudang_id'] ?? null,
    'user_id' => $_POST['user_id'] ?? current_user_id(),
    'user_name_snapshot' => $_POST['user_name_snapshot'] ?? get_current_user_name($koneksi) ?? 'System',
    'target_user_id' => $_POST['target_user_id'] ?? null,
    'target_user_name_snapshot' => $_POST['target_user_name_snapshot'] ?? null,
    'deskripsi' => $_POST['deskripsi'] ?? null,
    'meta_json' => $_POST['meta_json'] ?? null,
]);

if (!$saved) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Histori log gagal disimpan.']);
    exit;
}

echo json_encode(['success' => true]);
