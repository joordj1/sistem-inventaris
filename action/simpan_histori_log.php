<?php

if (!function_exists('ensure_histori_log_table')) {
    function ensure_histori_log_table($koneksi) {
        if (!isset($koneksi) || !$koneksi) {
            return false;
        }

        if (function_exists('schema_table_exists_now') && schema_table_exists_now($koneksi, 'histori_log')) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS histori_log (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    ref_type VARCHAR(50) NOT NULL,
                    ref_id INT(11) NOT NULL,
                    event_type VARCHAR(100) NOT NULL,
                    produk_id INT(11) DEFAULT NULL,
                    unit_barang_id INT(11) DEFAULT NULL,
                    gudang_id INT(11) DEFAULT NULL,
                    user_id INT(11) DEFAULT NULL,
                    user_name_snapshot VARCHAR(255) NOT NULL,
                    target_user_id INT(11) DEFAULT NULL,
                    target_user_name_snapshot VARCHAR(255) DEFAULT NULL,
                    deskripsi TEXT DEFAULT NULL,
                    meta_json LONGTEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_histori_ref (ref_type, ref_id),
                    KEY idx_histori_produk (produk_id),
                    KEY idx_histori_unit (unit_barang_id),
                    KEY idx_histori_gudang (gudang_id),
                    KEY idx_histori_user (user_id),
                    KEY idx_histori_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        if (!$koneksi->query($sql)) {
            return false;
        }

        return !function_exists('schema_table_exists_now') || schema_table_exists_now($koneksi, 'histori_log');
    }
}

if (!function_exists('save_official_histori_log_entry')) {
    function save_official_histori_log_entry($koneksi, array $data) {
        if (!ensure_histori_log_table($koneksi)) {
            return false;
        }

        if (!function_exists('save_histori_log_entry')) {
            return false;
        }

        return save_histori_log_entry($koneksi, $data);
    }
}

$isDirectRequest = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__;
if (!$isDirectRequest) {
    return;
}

require_once __DIR__ . '/../koneksi/koneksi.php';

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

$saved = save_official_histori_log_entry($koneksi, [
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
