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
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$id_unit = isset($_POST['id_unit_barang']) ? intval($_POST['id_unit_barang']) : 0;
$id_user = isset($_POST['id_user']) ? intval($_POST['id_user']) : 0;
$note = isset($_POST['note']) ? trim($_POST['note']) : 'Assign unit to user';
$operator = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;

if (!$id_unit || !$id_user) {
    http_response_code(400);
    echo json_encode(['error' => 'id_unit_barang dan id_user wajib diisi']);
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

if (!asset_unit_user_exists($koneksi, $id_user)) {
    http_response_code(400);
    echo json_encode(['error' => 'User tujuan tidak ditemukan']);
    exit;
}

$currentStatus = normalize_asset_unit_status($unit['status'] ?? null);
if (!is_asset_unit_action_allowed('assign', $currentStatus)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unit hanya bisa di-assign saat status masih tersedia']);
    exit;
}

if (!empty($unit['id_user'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unit sudah terhubung ke user lain dan tidak bisa di-assign ulang langsung']);
    exit;
}

$previousLocation = get_asset_unit_location_text($koneksi, $unit);
$newStoredStatus = map_asset_unit_status_for_storage('dipakai');

$koneksi->begin_transaction();
try {
    $updated = update_unit_barang($koneksi, $id_unit, [
        'id_user' => $id_user,
        'status' => $newStoredStatus,
        'tersedia' => 0
    ]);

    if (!$updated) {
        throw new Exception('Gagal update database');
    }

    log_riwayat_unit_barang($koneksi, [
        'id_unit_barang' => $id_unit,
        'id_produk' => $unit['id_produk'],
        'activity_type' => 'pinjam',
        'status_sebelum' => map_asset_unit_status_for_storage($currentStatus),
        'status_sesudah' => $newStoredStatus,
        'kondisi_sebelum' => $unit['kondisi'],
        'kondisi_sesudah' => $unit['kondisi'],
        'lokasi_sebelum' => $previousLocation,
        'lokasi_sesudah' => $previousLocation,
        'id_user_sebelum' => $unit['id_user'],
        'id_user_sesudah' => $id_user,
        'id_user_terkait' => $id_user,
        'note' => $note,
        'id_user_changed' => $operator
    ]);

    log_activity($koneksi, [
        'id_user' => $operator,
        'role_user' => get_current_user_role(),
        'action_name' => 'unit_assign',
        'entity_type' => 'unit',
        'entity_id' => $id_unit,
        'entity_label' => $unit['kode_unit'] ?? $unit['serial_number'] ?? ('Unit #' . $id_unit),
        'description' => 'Assign unit asset ke user',
        'id_produk' => $unit['id_produk'],
        'id_unit_barang' => $id_unit,
        'id_gudang' => $unit['id_gudang'] ?? null,
        'metadata_json' => [
            'id_user_sebelum' => $unit['id_user'],
            'id_user_sesudah' => $id_user,
            'lokasi' => $previousLocation,
            'note' => $note,
        ],
    ]);

    $koneksi->commit();
} catch (Exception $e) {
    $koneksi->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true]);
