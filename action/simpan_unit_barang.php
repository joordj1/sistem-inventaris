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

$id_produk = isset($_POST['id_produk']) ? intval($_POST['id_produk']) : 0;
$serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : null;
$kode_qrcode = isset($_POST['kode_qrcode']) ? trim($_POST['kode_qrcode']) : null;
$id_gudang = isset($_POST['id_gudang']) && $_POST['id_gudang'] !== '' ? intval($_POST['id_gudang']) : null;
$id_lokasi = isset($_POST['id_lokasi']) && $_POST['id_lokasi'] !== '' ? intval($_POST['id_lokasi']) : null;
$lokasi_custom = isset($_POST['lokasi_custom']) ? trim($_POST['lokasi_custom']) : null;
$status = isset($_POST['status']) ? trim($_POST['status']) : 'tersedia';
$kondisi = isset($_POST['kondisi']) ? trim($_POST['kondisi']) : 'baik';
$id_user = isset($_POST['id_user']) && $_POST['id_user'] !== '' ? intval($_POST['id_user']) : null;
$operator = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;
$note = isset($_POST['note']) ? trim($_POST['note']) : 'Penambahan unit barang asset';
$lokasiSesudah = $lokasi_custom ?: get_gudang_name_by_id($koneksi, $id_gudang);

$produk = get_produk_by_id($koneksi, $id_produk);
if (!$produk) {
    http_response_code(404);
    echo json_encode(['error' => 'Produk not found']);
    exit;
}

if ($produk['tipe_barang'] !== 'asset') {
    http_response_code(400);
    echo json_encode(['error' => 'Produk bukan asset. Gunakan proses stok konsumsi untuk item non-asset.']);
    exit;
}

$stmt = $koneksi->prepare("INSERT INTO unit_barang (id_produk, serial_number, kode_qrcode, id_gudang, id_lokasi, lokasi_custom, status, kondisi, id_user, tersedia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $koneksi->error]);
    exit;
}
$tersedia = ($status === 'tersedia') ? 1 : 0;
$stmt->bind_param('ississsisi', $id_produk, $serial_number, $kode_qrcode, $id_gudang, $id_lokasi, $lokasi_custom, $status, $kondisi, $id_user, $tersedia);
$exec = $stmt->execute();
if (!$exec) {
    http_response_code(500);
    echo json_encode(['error' => $stmt->error]);
    exit;
}

$id_unit = $stmt->insert_id;

// Generate and persist unique QR value pointing to public scan page.
$qrValue = build_asset_qr_value($id_unit, $id_produk, $koneksi);

$updateQrStmt = $koneksi->prepare("UPDATE unit_barang SET kode_qrcode = ? WHERE id_unit_barang = ?");
if ($updateQrStmt) {
    $updateQrStmt->bind_param('si', $qrValue, $id_unit);
    $updateQrStmt->execute();
}

if (ensure_asset_qr_file($id_unit, $qrValue) === null) {
    log_asset_qr_error('Automatic QR generation failed after unit insert.', [
        'unit_id' => $id_unit,
        'produk_id' => $id_produk,
        'source' => 'simpan_unit_barang',
    ]);
}

log_tracking_unit_barang($koneksi, [
    'id_unit' => $id_unit,
    'id_unit_barang' => $id_unit,
    'id_produk' => $id_produk,
    'activity_type' => 'tambah',
    'status_sebelum' => null,
    'status_sesudah' => $status,
    'kondisi_sebelum' => null,
    'kondisi_sesudah' => $kondisi,
    'lokasi_sebelum' => null,
    'lokasi_sesudah' => $lokasiSesudah,
    'id_user_sebelum' => null,
    'id_user_sesudah' => $id_user,
    'id_user_terkait' => $id_user,
    'note' => $note,
    'id_user_changed' => $operator,
]);

log_activity($koneksi, [
    'id_user' => $operator,
    'role_user' => get_current_user_role(),
    'action_name' => 'unit_tambah',
    'entity_type' => 'unit',
    'entity_id' => $id_unit,
    'entity_label' => $serial_number ?: ('Unit #' . $id_unit),
    'description' => 'Menambahkan unit asset baru',
    'id_produk' => $id_produk,
    'id_unit_barang' => $id_unit,
    'id_gudang' => $id_gudang,
    'metadata_json' => [
        'serial_number' => $serial_number,
        'status' => $status,
        'kondisi' => $kondisi,
        'lokasi' => $lokasiSesudah,
        'id_user' => $id_user,
        'note' => $note,
    ],
]);

echo json_encode(['success' => true, 'id_unit_barang' => $id_unit]);
