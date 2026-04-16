<?php
include '../koneksi/koneksi.php';
require_once __DIR__ . '/simpan_histori_log.php';
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
$note = isset($_POST['note']) ? trim($_POST['note']) : 'Release unit asset';
$operator = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;

if (!$id_unit) {
    http_response_code(400);
    echo json_encode(['error' => 'id_unit_barang wajib diisi']);
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
if (!is_asset_unit_action_allowed('release', $currentStatus)) {
    http_response_code(400);
    echo json_encode(['error' => 'Release hanya dapat dilakukan pada unit yang sedang dipakai']);
    exit;
}

$previousLocation = get_asset_unit_location_text($koneksi, $unit);
$unitLabel = trim((string) ($unit['kode_unit'] ?? ''));
$unitLabel = $unitLabel !== '' ? $unitLabel : ('Unit #' . $id_unit);

ensure_histori_log_table($koneksi);
$koneksi->begin_transaction();
try {
    $updated = update_unit_barang($koneksi, $id_unit, [
        'id_user' => null,
        'status' => map_asset_unit_status_for_storage('tersedia'),
        'tersedia' => 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    if (!$updated) {
        throw new Exception('Gagal update database');
    }

    $trackingSaved = log_tracking_unit_barang($koneksi, [
        'id_unit' => $id_unit,
        'id_unit_barang' => $id_unit,
        'id_produk' => $unit['id_produk'],
        'activity_type' => 'kembali',
        'status_sebelum' => map_asset_unit_status_for_storage($currentStatus),
        'status_sesudah' => map_asset_unit_status_for_storage('tersedia'),
        'kondisi_sebelum' => $unit['kondisi'],
        'kondisi_sesudah' => $unit['kondisi'],
        'lokasi_sebelum' => $previousLocation,
        'lokasi_sesudah' => $previousLocation,
        'id_user_sebelum' => $unit['id_user'],
        'id_user_sesudah' => null,
        'id_user_terkait' => $unit['id_user'],
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
        'action_name' => 'unit_release',
        'entity_type' => 'unit',
        'entity_id' => $id_unit,
        'entity_label' => $unitLabel,
        'description' => 'Release unit asset menjadi tersedia',
        'id_produk' => $unit['id_produk'],
        'id_unit_barang' => $id_unit,
        'id_gudang' => $unit['id_gudang'] ?? null,
        'metadata_json' => [
            'id_user_sebelum' => $unit['id_user'],
            'lokasi' => $previousLocation,
            'note' => $note,
        ],
    ]);

    save_official_histori_log_entry($koneksi, [
        'ref_type' => 'unit',
        'ref_id' => $id_unit,
        'event_type' => 'release_cepat',
        'produk_id' => $unit['id_produk'],
        'unit_barang_id' => $id_unit,
        'gudang_id' => $unit['id_gudang'] ?? null,
        'user_id' => $operator,
        'user_name_snapshot' => get_current_user_name($koneksi) ?? 'System',
        'target_user_id' => $unit['id_user'] ?? null,
        'target_user_name_snapshot' => get_user_name_by_id($koneksi, $unit['id_user'] ?? null),
        'deskripsi' => 'Release cepat unit asset. Untuk berita acara formal gunakan modul serah terima barang.',
        'meta_json' => [
            'lokasi' => $previousLocation,
            'note' => $note,
        ],
    ]);

    sync_foundation_barang_from_units($koneksi, $unit['id_produk'], [
        'activity_type' => 'kembali',
        'actor_user_id' => $operator,
        'note' => $note,
    ]);

    $koneksi->commit();
} catch (Exception $e) {
    $koneksi->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true]);
