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
$id_gudang = isset($_POST['id_gudang']) && $_POST['id_gudang'] !== '' ? intval($_POST['id_gudang']) : null;
$id_lokasi = isset($_POST['id_lokasi']) && $_POST['id_lokasi'] !== '' ? intval($_POST['id_lokasi']) : null;
$lokasi_custom = isset($_POST['lokasi_custom']) ? trim($_POST['lokasi_custom']) : null;
$note = isset($_POST['note']) ? trim($_POST['note']) : 'Update lokasi unit asset';
$operator = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;

if (!$id_unit || ($id_gudang === null && $id_lokasi === null && empty($lokasi_custom))) {
    http_response_code(400);
    echo json_encode(['error' => 'id_unit_barang dan lokasi baru wajib diisi']);
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

$currentStatus = normalize_asset_unit_status($unit['status'] ?? null);
if (!is_asset_unit_action_allowed('move', $currentStatus)) {
    http_response_code(400);
    echo json_encode(['error' => 'Pindah lokasi hanya diizinkan saat unit tersedia atau sedang dipakai']);
    exit;
}

if ($id_gudang !== null && !asset_unit_gudang_exists($koneksi, $id_gudang)) {
    http_response_code(400);
    echo json_encode(['error' => 'Gudang tujuan tidak ditemukan']);
    exit;
}

if ($id_lokasi !== null && !asset_unit_lokasi_exists($koneksi, $id_lokasi)) {
    http_response_code(400);
    echo json_encode(['error' => 'Lokasi detail tidak ditemukan']);
    exit;
}

$old_location = get_asset_unit_location_text($koneksi, $unit);
$fields = [];
$hasLokasiColumn = schema_has_column($koneksi, 'unit_barang', 'id_lokasi');
if ($id_gudang !== null) {
    $fields['id_gudang'] = $id_gudang;
    if ($hasLokasiColumn) {
        $fields['id_lokasi'] = null;
    }
    $fields['lokasi_custom'] = null;
}
if ($id_lokasi !== null && $hasLokasiColumn) {
    $fields['id_lokasi'] = $id_lokasi;
}
if (!empty($lokasi_custom)) {
    $fields['lokasi_custom'] = $lokasi_custom;
}

if (empty($fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Perubahan lokasi tidak valid']);
    exit;
}

$previewUnit = $unit;
foreach ($fields as $field => $value) {
    $previewUnit[$field] = $value;
}
$new_location = get_asset_unit_location_text($koneksi, $previewUnit);

if (($old_location ?? '') === ($new_location ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Lokasi unit tidak berubah']);
    exit;
}

$koneksi->begin_transaction();
try {
    $updated = update_unit_barang($koneksi, $id_unit, $fields);
    if (!$updated) {
        throw new Exception('Gagal update database');
    }

    log_riwayat_unit_barang($koneksi, [
        'id_unit_barang' => $id_unit,
        'id_produk' => $unit['id_produk'],
        'activity_type' => 'pindah',
        'status_sebelum' => $unit['status'],
        'status_sesudah' => $unit['status'],
        'kondisi_sebelum' => $unit['kondisi'],
        'kondisi_sesudah' => $unit['kondisi'],
        'lokasi_sebelum' => $old_location,
        'lokasi_sesudah' => $new_location,
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
