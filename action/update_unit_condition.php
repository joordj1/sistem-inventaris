<?php
session_start();
include '../koneksi/koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$id_unit = isset($_POST['id_unit_barang']) ? intval($_POST['id_unit_barang']) : 0;
$new_condition = isset($_POST['kondisi']) ? trim($_POST['kondisi']) : null;
$note = isset($_POST['note']) ? trim($_POST['note']) : 'Update kondisi unit asset';
$operator = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;

if (!$id_unit || !$new_condition) {
    http_response_code(400);
    echo json_encode(['error' => 'id_unit_barang dan kondisi wajib diisi']);
    exit;
}

$unit = get_unit_barang_by_id($koneksi, $id_unit);
if (!$unit) {
    http_response_code(404);
    echo json_encode(['error' => 'Unit barang tidak ditemukan']);
    exit;
}

$produk = get_produk_by_id($koneksi, $unit['id_produk']);
if (!$produk || $produk['tipe_barang'] !== 'asset') {
    http_response_code(400);
    echo json_encode(['error' => 'Unit barang tidak valid atau bukan asset']);
    exit;
}

$allowedConditions = ['baik', 'rusak', 'diperbaiki', 'usang', 'lainnya'];
if (!in_array(strtolower($new_condition), $allowedConditions, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Kondisi unit tidak valid']);
    exit;
}

$previousLocation = get_asset_unit_location_text($koneksi, $unit);

$koneksi->begin_transaction();
try {
    $updated = update_unit_barang($koneksi, $id_unit, ['kondisi' => strtolower($new_condition)]);
    if (!$updated) {
        throw new Exception('Gagal update database');
    }

    log_riwayat_unit_barang($koneksi, [
        'id_unit_barang' => $id_unit,
        'id_produk' => $unit['id_produk'],
        'activity_type' => 'update',
        'status_sebelum' => $unit['status'],
        'status_sesudah' => $unit['status'],
        'kondisi_sebelum' => $unit['kondisi'],
        'kondisi_sesudah' => strtolower($new_condition),
        'lokasi_sebelum' => $previousLocation,
        'lokasi_sesudah' => $previousLocation,
        'id_user_sebelum' => $unit['id_user'],
        'id_user_sesudah' => $unit['id_user'],
        'id_user_terkait' => $unit['id_user'],
        'note' => $note,
        'id_user_changed' => $operator
    ]);

    $koneksi->commit();
} catch (Exception $e) {
    $koneksi->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true]);
