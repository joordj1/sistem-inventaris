<?php
include '../koneksi/koneksi.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_produk',
]);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$unitId = filter_var($_POST['id_unit_barang'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($unitId === false || $unitId === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id_unit_barang tidak valid']);
    exit;
}

$newHash = regenerate_qr_hash($koneksi, $unitId);
if (!$newHash) {
    log_event('ERROR', 'QR', 'regenerate_qr_hash gagal untuk unit_id=' . intval($unitId));
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal regenerate QR hash']);
    exit;
}

$qrValue = 'scan_barang.php?q=' . rawurlencode($newHash);
$qrImage = ensure_asset_qr_file($unitId, $qrValue);

echo json_encode([
    'success' => true,
    'id_unit_barang' => intval($unitId),
    'qr_hash' => $newHash,
    'qr_value' => $qrValue,
    'qr_image' => $qrImage,
]);
