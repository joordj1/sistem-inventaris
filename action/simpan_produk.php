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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

if ($kodeProduk === '' || $namaProduk === '' || $kategoriId === false || $gudangId === false || $satuan === '') {
    redirect_with_flash('error', 'Semua input wajib diisi: kode, nama, kategori, gudang, stok, satuan.', '../index.php?page=tambah_produk');
}

if ($stok === false) {
    redirect_with_flash('error', 'Stok harus berupa angka bulat.', '../index.php?page=tambah_produk');
}

if ($stok < 1) {
    $_SESSION['error'] = 'Stok minimal harus 1';
    redirect_with_flash('error', 'Stok minimal harus 1', '../index.php?page=tambah_produk');
}

if ($hargaSatuan < 1) {
    redirect_with_flash('error', 'Harga produk wajib diisi dan minimal 1.', '../index.php?page=tambah_produk');
}

if ($tipeBarang === null) {
    redirect_with_flash('error', 'Tipe produk hanya boleh consumable atau asset.', '../index.php?page=tambah_produk');
}

$cekKategoriStmt = $koneksi->prepare('SELECT id_kategori FROM kategori WHERE id_kategori = ? LIMIT 1');
if (!$cekKategoriStmt) {
    redirect_with_flash('error', 'Gagal memvalidasi kategori.', '../index.php?page=tambah_produk');
}
$cekKategoriStmt->bind_param('i', $kategoriId);
$cekKategoriStmt->execute();
if (!$cekKategoriStmt->get_result()->fetch_assoc()) {
    redirect_with_flash('error', 'Kategori yang dipilih tidak ditemukan.', '../index.php?page=tambah_produk');
}

$cekGudangStmt = $koneksi->prepare('SELECT id_gudang FROM gudang WHERE id_gudang = ? LIMIT 1');
if (!$cekGudangStmt) {
    redirect_with_flash('error', 'Gagal memvalidasi gudang.', '../index.php?page=tambah_produk');
}
$cekGudangStmt->bind_param('i', $gudangId);
$cekGudangStmt->execute();
if (!$cekGudangStmt->get_result()->fetch_assoc()) {
    redirect_with_flash('error', 'Gudang yang dipilih tidak ditemukan.', '../index.php?page=tambah_produk');
}

$cekKodeStmt = $koneksi->prepare('SELECT id_produk FROM produk WHERE kode_produk = ? LIMIT 1');
if (!$cekKodeStmt) {
    redirect_with_flash('error', 'Gagal memvalidasi kode produk.', '../index.php?page=tambah_produk');
}
$cekKodeStmt->bind_param('s', $kodeProduk);
$cekKodeStmt->execute();
if ($cekKodeStmt->get_result()->fetch_assoc()) {
    redirect_with_flash('error', 'Kode produk sudah terdaftar. Gunakan kode lain.', '../index.php?page=tambah_produk');
}

$gambarProduk = null;
if (!empty($_FILES['gambarproduk']['name'] ?? '')) {
    $targetDir = '../uploads/';
    $fileInfo = $_FILES['gambarproduk'];
    $extension = strtolower(pathinfo((string) $fileInfo['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($extension, $allowedTypes, true)) {
        redirect_with_flash('error', 'Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.', '../index.php?page=tambah_produk');
    }

    $safeName = 'PROD_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $targetDir . $safeName;

    if (!move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
        redirect_with_flash('error', 'Upload gambar produk gagal.', '../index.php?page=tambah_produk');
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

    if ($tipeBarang === 'consumable') {
        $insertStokStmt = $koneksi->prepare('INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)');
        if (!$insertStokStmt) {
            throw new Exception('Gagal menyiapkan stok gudang.');
        }
        $insertStokStmt->bind_param('iii', $gudangId, $lastProdukId, $stok);
        if (!$insertStokStmt->execute()) {
            throw new Exception('Stok gudang gagal disimpan.');
        }
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
    redirect_with_flash('error', 'Gagal menyimpan produk: ' . $e->getMessage(), '../index.php?page=tambah_produk');
}
