<?php
session_start();
include '../koneksi/koneksi.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_produk',
]);

function redirect_with_flash($type, $message, $location) {
    set_flash_message($type, $message);
    header('Location: ' . $location);
    exit();
}

function is_ajax_request() {
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function respond_json($statusCode, array $payload) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function redirect_with_form_state(array $errors, $message = '') {
    $_SESSION['old_input_tambah_produk'] = $_POST;
    $_SESSION['form_errors_tambah_produk'] = $errors;

    if ($message !== '') {
        set_flash_message('warning', $message);
    }

    header('Location: ../index.php?page=tambah_produk');
    exit();
}

function validation_failed(array $errors, $message = 'Validasi form gagal.') {
    if (is_ajax_request()) {
        respond_json(422, [
            'success' => false,
            'type' => 'validation_error',
            'message' => $message,
            'errors' => $errors,
        ]);
    }

    redirect_with_form_state($errors, $message);
}

function process_failed($message) {
    if (is_ajax_request()) {
        respond_json(500, [
            'success' => false,
            'type' => 'process_error',
            'message' => $message,
        ]);
    }

    redirect_with_flash('error', $message, '../index.php?page=tambah_produk');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (is_ajax_request()) {
        respond_json(405, [
            'success' => false,
            'type' => 'invalid_method',
            'message' => 'Permintaan tidak valid.',
        ]);
    }
    redirect_with_flash('warning', 'Permintaan tidak valid.', '../index.php?page=tambah_produk');
}

$kodeProduk = htmlspecialchars(trim((string) ($_POST['code'] ?? '')), ENT_QUOTES, 'UTF-8');
$namaProduk = htmlspecialchars(trim((string) ($_POST['namaproduk'] ?? '')), ENT_QUOTES, 'UTF-8');
$deskripsi = htmlspecialchars(trim((string) ($_POST['deskripsi'] ?? '')), ENT_QUOTES, 'UTF-8');
$satuan = htmlspecialchars(trim((string) ($_POST['satuan'] ?? '')), ENT_QUOTES, 'UTF-8');
$tipeBarangInput = trim((string) ($_POST['tipe_barang'] ?? 'consumable'));
$tipeBarang = in_array($tipeBarangInput, ['consumable', 'asset'], true) ? $tipeBarangInput : null;

$kategoriId = filter_var($_POST['kategori'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$gudangId = filter_var($_POST['gudang'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$stok = filter_var($_POST['stok'] ?? null, FILTER_VALIDATE_INT);

$hargaInput = preg_replace('/[^0-9]/', '', (string) ($_POST['harga'] ?? ''));
$hargaSatuan = ($hargaInput === '') ? 0 : intval($hargaInput);

$errors = [];

if ($kodeProduk === '') {
    $errors['code'] = 'Kode produk wajib diisi.';
}

if ($namaProduk === '') {
    $errors['namaproduk'] = 'Nama produk wajib diisi.';
}

if ($kategoriId === false) {
    $errors['kategori'] = 'Kategori wajib dipilih.';
}

if ($gudangId === false) {
    $errors['gudang'] = 'Gudang wajib dipilih.';
}

if ($stok === false) {
    $errors['stok'] = 'Stok harus berupa angka bulat.';
} elseif ($stok < 1) {
    $errors['stok'] = 'Stok minimal harus 1.';
}

if ($satuan === '') {
    $errors['satuan'] = 'Satuan wajib diisi.';
}

if ($hargaSatuan < 1) {
    $errors['harga'] = 'Harga produk wajib diisi dan minimal 1.';
}

if ($tipeBarang === null) {
    $errors['tipe_barang'] = 'Tipe produk hanya boleh consumable atau asset.';
}

if (!empty($errors)) {
    validation_failed($errors);
}

$cekKategoriStmt = $koneksi->prepare('SELECT id_kategori FROM kategori WHERE id_kategori = ? LIMIT 1');
if (!$cekKategoriStmt) {
    process_failed('Gagal memvalidasi kategori.');
}
$cekKategoriStmt->bind_param('i', $kategoriId);
$cekKategoriStmt->execute();
if (!$cekKategoriStmt->get_result()->fetch_assoc()) {
    validation_failed([
        'kategori' => 'Kategori yang dipilih tidak ditemukan.',
    ]);
}

$cekGudangStmt = $koneksi->prepare('SELECT id_gudang FROM gudang WHERE id_gudang = ? LIMIT 1');
if (!$cekGudangStmt) {
    process_failed('Gagal memvalidasi gudang.');
}
$cekGudangStmt->bind_param('i', $gudangId);
$cekGudangStmt->execute();
if (!$cekGudangStmt->get_result()->fetch_assoc()) {
    validation_failed([
        'gudang' => 'Gudang yang dipilih tidak ditemukan.',
    ]);
}

$cekKodeStmt = $koneksi->prepare('SELECT id_produk FROM produk WHERE kode_produk = ? LIMIT 1');
if (!$cekKodeStmt) {
    process_failed('Gagal memvalidasi kode produk.');
}
$cekKodeStmt->bind_param('s', $kodeProduk);
$cekKodeStmt->execute();
if ($cekKodeStmt->get_result()->fetch_assoc()) {
    validation_failed([
        'code' => 'Kode produk sudah terdaftar. Gunakan kode lain.',
    ]);
}

$gambarProduk = null;
if (!empty($_FILES['gambarproduk']['name'] ?? '')) {
    $targetDir = '../uploads/';
    $fileInfo = $_FILES['gambarproduk'];
    $extension = strtolower(pathinfo((string) $fileInfo['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($extension, $allowedTypes, true)) {
        validation_failed([
            'gambarproduk' => 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.',
        ]);
    }

    $safeName = 'PROD_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $targetDir . $safeName;

    if (!move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
        validation_failed([
            'gambarproduk' => 'Upload gambar produk gagal.',
        ]);
    }

    $gambarProduk = $safeName;
}

$statusDefault = 'tersedia';
$kondisiDefault = 'baik';
$tersediaDefault = 1;
$jumlahUnit = ($tipeBarang === 'asset') ? intval($stok) : 0;

$koneksi->begin_transaction();
try {
    $insertProdukStmt = $koneksi->prepare(
        'INSERT INTO produk (
            kode_produk, nama_produk, deskripsi, id_kategori, id_gudang, jumlah_stok, satuan,
            harga_default, harga_satuan, gambar_produk, status, kondisi, tersedia, last_tracked_at, tipe_barang
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
    );

    if (!$insertProdukStmt) {
        throw new Exception('Gagal menyiapkan penyimpanan produk.');
    }

    $deskripsiValue = $deskripsi !== '' ? $deskripsi : null;
    $gambarValue = $gambarProduk;

    $insertProdukStmt->bind_param(
        'sssiiisiisssis',
        $kodeProduk,
        $namaProduk,
        $deskripsiValue,
        $kategoriId,
        $gudangId,
        $stok,
        $satuan,
        $hargaSatuan,
        $hargaSatuan,
        $gambarValue,
        $statusDefault,
        $kondisiDefault,
        $tersediaDefault,
        $tipeBarang
    );

    if (!$insertProdukStmt->execute()) {
        throw new Exception('Data produk gagal disimpan.');
    }

    $lastProdukId = intval($koneksi->insert_id);
    $userId = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0;

    $gudangName = '';
    $qGudangStmt = $koneksi->prepare('SELECT nama_gudang FROM gudang WHERE id_gudang = ? LIMIT 1');
    if ($qGudangStmt) {
        $qGudangStmt->bind_param('i', $gudangId);
        $qGudangStmt->execute();
        $qGudangRow = $qGudangStmt->get_result()->fetch_assoc();
        $gudangName = $qGudangRow['nama_gudang'] ?? '';
    }

    if (!upsert_stokgudang_additive($koneksi, $gudangId, $lastProdukId, $stok)) {
        throw new Exception('Stok gudang gagal disinkronkan.');
    }

    if ($tipeBarang === 'asset' && $jumlahUnit > 0) {
        $assetSyncResult = sync_asset_units_for_product($koneksi, [
            'id_produk' => $lastProdukId,
            'kode_produk' => $kodeProduk,
            'jumlah_stok' => $jumlahUnit,
            'id_gudang' => $gudangId,
            'kondisi' => $kondisiDefault,
        ], [
            'operator_id' => $userId,
            'create_note' => 'Unit asset dibuat otomatis dari tambah produk',
            'old_gudang_id' => null,
        ]);

        if (empty($assetSyncResult['success'])) {
            throw new Exception($assetSyncResult['message'] ?? 'Sinkronisasi unit asset gagal.');
        }
    }

    log_tracking_history($koneksi, [
        'id_produk' => $lastProdukId,
        'kode_produk' => $kodeProduk,
        'status_sebelum' => null,
        'status_sesudah' => $statusDefault,
        'kondisi_sebelum' => null,
        'kondisi_sesudah' => $kondisiDefault,
        'lokasi_sebelum' => null,
        'lokasi_sesudah' => $gudangName,
        'id_user_sebelum' => null,
        'id_user_sesudah' => $userId,
        'id_user_terkait' => $userId,
        'activity_type' => 'tambah',
        'note' => 'Produk ditambahkan dari form',
        'id_user_changed' => $userId,
    ]);

    log_activity($koneksi, [
        'id_user' => $userId,
        'role_user' => get_current_user_role(),
        'action_name' => 'produk_tambah',
        'entity_type' => 'produk',
        'entity_id' => $lastProdukId,
        'entity_label' => $kodeProduk . ' - ' . $namaProduk,
        'description' => 'Menambahkan data barang baru',
        'id_produk' => $lastProdukId,
        'id_gudang' => $gudangId,
        'metadata_json' => [
            'kode_produk' => $kodeProduk,
            'nama_produk' => $namaProduk,
            'tipe_barang' => $tipeBarang,
            'jumlah_stok' => $stok,
            'deskripsi' => $deskripsi,
        ],
    ]);

    $koneksi->commit();
    redirect_with_flash('success', 'Data produk berhasil disimpan.', '../index.php?page=data_produk');
} catch (Exception $e) {
    $koneksi->rollback();
    process_failed('Gagal menyimpan produk: ' . $e->getMessage());
}
