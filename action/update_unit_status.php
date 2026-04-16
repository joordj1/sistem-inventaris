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
$new_status = isset($_POST['status']) ? trim($_POST['status']) : null;
$note = isset($_POST['note']) ? trim($_POST['note']) : '';
$vendor = isset($_POST['vendor']) ? trim($_POST['vendor']) : '';
$estimasiSelesai = isset($_POST['estimasi_selesai']) ? trim($_POST['estimasi_selesai']) : '';
$operator = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;

// Compose note from vendor/estimasi fields if provided
if ($vendor !== '' || $estimasiSelesai !== '') {
    $noteParts = [];
    if ($note !== '') $noteParts[] = $note;
    if ($vendor !== '') $noteParts[] = 'Vendor: ' . $vendor;
    if ($estimasiSelesai !== '') $noteParts[] = 'Estimasi selesai: ' . $estimasiSelesai;
    $note = implode(' | ', $noteParts);
}
if ($note === '') {
    $note = 'Update status unit asset';
}

if (!$id_unit || !$new_status) {
    http_response_code(400);
    echo json_encode(['error' => 'id_unit_barang dan status wajib diisi']);
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
$targetStatus = normalize_asset_unit_status($new_status);

if (!in_array($targetStatus, ['tersedia', 'dipakai', 'perbaikan', 'rusak'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Status target tidak valid']);
    exit;
}

if (!is_valid_asset_unit_status_transition($currentStatus, $targetStatus)) {
    http_response_code(400);
    echo json_encode(['error' => 'Transisi status tidak valid dari ' . get_asset_unit_status_label($currentStatus) . ' ke ' . get_asset_unit_status_label($targetStatus)]);
    exit;
}

$newStoredStatus = map_asset_unit_status_for_storage($targetStatus);
$previousLocation = get_asset_unit_location_text($koneksi, $unit);
$newCondition = $unit['kondisi'];
$fields = [
    'status' => $newStoredStatus,
    'tersedia' => ($targetStatus === 'tersedia') ? 1 : 0,
];

if ($targetStatus !== 'dipakai') {
    $fields['id_user'] = null;
}
if ($targetStatus === 'rusak') {
    $fields['kondisi'] = 'rusak';
    $newCondition = 'rusak';
}

$activityType = determine_asset_unit_activity_type($currentStatus, $targetStatus, 'update');

$koneksi->begin_transaction();
try {

    // 1. Update status unit utama
    $updated = update_unit_barang($koneksi, $id_unit, $fields);
    if (!$updated) {
        throw new Exception('Gagal update database');
    }

    // 2. Catat tracking/riwayat setelah status utama pasti berubah
    $trackingSaved = log_tracking_unit_barang($koneksi, [
        'id_unit' => $id_unit,
        'id_unit_barang' => $id_unit,
        'id_produk' => $unit['id_produk'],
        'activity_type' => $activityType,
        'status_sebelum' => map_asset_unit_status_for_storage($currentStatus),
        'status_sesudah' => $newStoredStatus,
        'kondisi_sebelum' => $unit['kondisi'],
        'kondisi_sesudah' => $newCondition,
        'lokasi_sebelum' => $previousLocation,
        'lokasi_sesudah' => $previousLocation,
        'id_user_sebelum' => $unit['id_user'],
        'id_user_sesudah' => $targetStatus === 'dipakai' ? $unit['id_user'] : null,
        'id_user_terkait' => $targetStatus === 'dipakai' ? $unit['id_user'] : null,
        'note' => $note,
        'id_user_changed' => $operator
    ]);
    if (!$trackingSaved) {
        $dbError = trim((string) ($koneksi->error ?? ''));
        throw new Exception('Gagal menyimpan histori tracking unit' . ($dbError !== '' ? (' | DB: ' . $dbError) : ''));
    }

    log_activity($koneksi, [
        'id_user' => $operator,
        'role_user' => get_current_user_role(),
        'action_name' => 'unit_update_status',
        'entity_type' => 'unit',
        'entity_id' => $id_unit,
        'entity_label' => $unit['kode_unit'] ?? $unit['serial_number'] ?? ('Unit #' . $id_unit),
        'description' => 'Memperbarui status unit asset',
        'id_produk' => $unit['id_produk'],
        'id_unit_barang' => $id_unit,
        'id_gudang' => $unit['id_gudang'] ?? null,
        'metadata_json' => [
            'status_sebelum' => $currentStatus,
            'status_sesudah' => $targetStatus,
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
