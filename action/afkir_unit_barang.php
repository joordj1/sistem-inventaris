<?php
/**
 * Afkirkan / hapus fungsional sebuah unit asset.
 * Status berubah menjadi 'afkir' – unit tetap ada di database tetapi
 * tidak lagi aktif dalam siklus operasional.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

include '../koneksi/koneksi.php';
require_once __DIR__ . '/simpan_histori_log.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_produk',
]);

$idUnitBarang = isset($_POST['id_unit_barang']) ? intval($_POST['id_unit_barang']) : 0;
$alasan = trim((string) ($_POST['alasan'] ?? ($_POST['note'] ?? '')));
$operatorId = current_user_id();
$operatorName = get_current_user_name($koneksi) ?? 'System';

if ($idUnitBarang < 1) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID unit tidak valid.']);
    exit;
}

$unit = get_unit_barang_by_id($koneksi, $idUnitBarang);
if (!$unit) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unit tidak ditemukan.']);
    exit;
}

$currentStatus = normalize_asset_unit_status($unit['status'] ?? null);

if ($currentStatus === 'afkir') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unit sudah berstatus afkir.']);
    exit;
}

if (!is_asset_unit_action_allowed('afkir', $currentStatus)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Tidak dapat mengafkirkan unit dengan status "' . get_asset_unit_status_label($currentStatus) . '". Selesaikan perbaikan atau tarik kembali unit terlebih dahulu.']);
    exit;
}

$statusAfkir = map_asset_unit_status_for_storage('afkir');
$note = 'Diafkirkan' . ($alasan !== '' ? ': ' . $alasan : '');

$stmt = $koneksi->prepare(
    "UPDATE unit_barang
     SET status = ?, id_user = NULL, lokasi_custom = 'afkir', tersedia = 0, kondisi = 'rusak', updated_at = NOW()
     WHERE id_unit_barang = ?"
);

if (!$stmt) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Gagal menyiapkan update unit.']);
    exit;
}

$stmt->bind_param('si', $statusAfkir, $idUnitBarang);
if (!$stmt->execute()) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Gagal mengafkirkan unit.']);
    exit;
}

log_tracking_unit_barang($koneksi, [
    'id_unit'         => $idUnitBarang,
    'id_unit_barang'  => $idUnitBarang,
    'id_produk'       => intval($unit['id_produk'] ?? 0),
    'activity_type'   => 'afkir',
    'status_sebelum'  => $unit['status'] ?? null,
    'status_sesudah'  => $statusAfkir,
    'kondisi_sebelum' => $unit['kondisi'] ?? null,
    'kondisi_sesudah' => 'rusak',
    'lokasi_sebelum'  => $unit['lokasi_custom'] ?? null,
    'lokasi_sesudah'  => 'afkir',
    'id_user_sebelum' => $unit['id_user'] ?? null,
    'id_user_sesudah' => null,
    'id_user_terkait' => null,
    'note'            => $note,
    'id_user_changed' => $operatorId,
    'actor_name_snapshot' => $operatorName,
]);

if (function_exists('sync_foundation_barang_from_units') && intval($unit['id_produk'] ?? 0) > 0) {
    sync_foundation_barang_from_units($koneksi, intval($unit['id_produk']), [
        'activity_type' => 'afkir',
        'actor_user_id' => $operatorId,
        'note'          => $note,
    ]);
}

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
