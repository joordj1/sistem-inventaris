<?php
include '../koneksi/koneksi.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=serah_terima',
]);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

try {
    $path = store_uploaded_inventory_document('dokumen_serah_terima', 'uploads/dokumen_serah_terima', 'STB');
    echo json_encode(['success' => true, 'file_path' => $path]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
