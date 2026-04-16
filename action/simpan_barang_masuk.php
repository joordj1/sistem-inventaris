<?php
require '../koneksi/koneksi.php';
require_once __DIR__ . '/simpan_histori_log.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=barang_masuk',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=tambah_barang_masuk');
    exit();
}

$no_invoice = htmlspecialchars(trim((string) ($_POST['no_invoice'] ?? '')), ENT_QUOTES, 'UTF-8');
$id_produk = intval($_POST['kode_produk'] ?? 0);
$id_gudang = intval($_POST['id_gudang'] ?? 0);
$jumlah = $_POST['jumlah'] ?? 0;
$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$keterangan = htmlspecialchars(trim((string) ($_POST['keterangan'] ?? '')), ENT_QUOTES, 'UTF-8');
$harga_satuan = preg_replace('/[^0-9]/', '', (string) ($_POST['harga_satuan'] ?? ''));

if ($no_invoice === '') {
    header("Location: ../index.php?page=tambah_barang_masuk&error=No Invoice wajib diisi");
    exit();
}

if ($id_gudang <= 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Gudang wajib dipilih");
    exit();
}

$gudangValid = $koneksi->query("SELECT id_gudang FROM gudang WHERE id_gudang = " . intval($id_gudang) . " LIMIT 1");
if (!$gudangValid || $gudangValid->num_rows < 1) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Gudang tidak valid");
    exit();
}

if ($id_produk <= 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Produk harus dipilih");
    exit();
}

if ($harga_satuan === '' || intval($harga_satuan) <= 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Harga transaksi wajib diisi");
    exit();
}
$harga_satuan = intval($harga_satuan);

if (!is_numeric($jumlah) || intval($jumlah) <= 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Jumlah barang masuk harus lebih dari 0");
    exit();
}
$jumlah = intval($jumlah);

// Validasi tipe item (hanya consumable boleh masuk stok)
$produkStmt = $koneksi->prepare("SELECT kode_produk, tipe_barang, status, kondisi, lokasi_custom, id_gudang, id_user FROM produk WHERE id_produk = ? LIMIT 1");
if (!$produkStmt) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Gagal memvalidasi produk");
    exit();
}
$produkStmt->bind_param('i', $id_produk);
$produkStmt->execute();
$produkBefore = $produkStmt->get_result()->fetch_assoc();
if (!$produkBefore || $produkBefore['tipe_barang'] !== 'consumable') {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Produk tidak valid");
    exit();
}

// Cek apakah No Invoice sudah ada
$tanggal_sql = $koneksi->real_escape_string($tanggal);
$keterangan_sql = $koneksi->real_escape_string($keterangan);

$cekInvoiceStmt = $koneksi->prepare("SELECT id_transaksi FROM stoktransaksi WHERE no_invoice = ? LIMIT 1");
if (!$cekInvoiceStmt) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Gagal memvalidasi invoice");
    exit();
}
$cekInvoiceStmt->bind_param('s', $no_invoice);
$cekInvoiceStmt->execute();
$cekInvoice = $cekInvoiceStmt->get_result();
if ($cekInvoice && $cekInvoice->num_rows > 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=No Invoice sudah ada");
    exit();
}

// === TRANSACTION: INSERT transaksi + UPDATE stok produk + UPDATE stokgudang ===
$insertTransaksiStmt = $koneksi->prepare("INSERT INTO stoktransaksi (no_invoice, id_produk, jumlah, harga_satuan, tanggal, keterangan, tipe_transaksi) VALUES (?, ?, ?, ?, ?, ?, 'masuk')");
if (!$insertTransaksiStmt) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Gagal menyimpan transaksi masuk");
    exit();
}
$insertTransaksiStmt->bind_param('siiiss', $no_invoice, $id_produk, $jumlah, $harga_satuan, $tanggal_sql, $keterangan_sql);

$koneksi->begin_transaction();
try {
    if (!$insertTransaksiStmt->execute()) {
        throw new \RuntimeException('Gagal menyimpan transaksi masuk.');
    }
    $id_transaksi = $koneksi->insert_id;

    // Update stok produk (prepared statement, bukan string interpolation)
    $updProdukStmt = $koneksi->prepare(
        "UPDATE produk SET jumlah_stok = jumlah_stok + ?, status = 'tersedia', tersedia = 1, id_gudang = ?, last_tracked_at = NOW() WHERE id_produk = ?"
    );
    if (!$updProdukStmt) {
        throw new \RuntimeException('Gagal menyiapkan update stok produk.');
    }
    $updProdukStmt->bind_param('iii', $jumlah, $id_gudang, $id_produk);
    $updProdukStmt->execute();

    // Sync stokgudang dari data aktual
    sync_stok_gudang($koneksi, $id_produk);

    $koneksi->commit();
} catch (\Throwable $txErr) {
    $koneksi->rollback();
    log_event('ERROR', 'STOK', 'simpan_barang_masuk gagal: ' . $txErr->getMessage());
    header("Location: ../index.php?page=tambah_barang_masuk&error=" . urlencode('Transaksi gagal disimpan. Silakan coba lagi.'));
    exit();
}
// === END TRANSACTION ===

// catat tracking (setelah commit)
$produkAfterStmt = $koneksi->prepare("SELECT kode_produk, status, kondisi, lokasi_custom, id_gudang, id_user FROM produk WHERE id_produk = ? LIMIT 1");
$produkAfterStmt->bind_param('i', $id_produk);
$produkAfterStmt->execute();
$produkAfter = $produkAfterStmt->get_result()->fetch_assoc();
$operator = $_SESSION['id_user'] ?? null;
$location = '';
if ($produkAfter['id_gudang']) {
    $lok = $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = " . intval($produkAfter['id_gudang']))->fetch_assoc();
    $location = $lok['nama_gudang'] ?? '';
} elseif (!empty($produkAfter['lokasi_custom'])) {
    $location = $produkAfter['lokasi_custom'];
}
log_tracking_history($koneksi, [
    'id_produk' => $id_produk,
    'kode_produk' => $produkAfter['kode_produk'] ?? null,
    'status_sebelum' => $produkBefore['status'],
    'status_sesudah' => 'tersedia',
    'kondisi_sebelum' => $produkBefore['kondisi'],
    'kondisi_sesudah' => $produkAfter['kondisi'],
    'lokasi_sebelum' => $location,
    'lokasi_sesudah' => $location,
    'id_user_sebelum' => $produkBefore['id_user'],
    'id_user_sesudah' => $produkAfter['id_user'],
    'id_user_terkait' => $produkAfter['id_user'],
    'activity_type' => 'keluarmasuk',
    'note' => $keterangan,
    'id_user_changed' => $operator
]);

if (trim((string) $keterangan) !== '') {
    save_inventory_note($koneksi, [
        'tipe_target' => 'transaksi',
        'kategori_catatan' => 'transaksi',
        'judul' => 'Barang masuk ' . $no_invoice,
        'catatan' => $keterangan,
        'id_produk' => $id_produk,
        'id_transaksi' => $id_transaksi,
        'id_gudang' => $produkAfter['id_gudang'] ?? null,
        'created_by' => $operator,
    ]);
}

log_activity($koneksi, [
    'id_user' => $operator,
    'role_user' => get_current_user_role(),
    'action_name' => 'transaksi_masuk',
    'entity_type' => 'transaksi',
    'entity_id' => $id_transaksi,
    'entity_label' => $no_invoice,
    'description' => 'Mencatat barang masuk',
    'id_produk' => $id_produk,
    'id_transaksi' => $id_transaksi,
    'id_gudang' => $produkAfter['id_gudang'] ?? null,
    'metadata_json' => [
        'no_invoice' => $no_invoice,
        'jumlah' => intval($jumlah),
        'harga_satuan' => $harga_satuan,
        'tanggal' => $tanggal,
        'keterangan' => $keterangan,
    ],
]);

save_official_histori_log_entry($koneksi, [
    'ref_type' => 'barang_masuk',
    'ref_id' => $id_transaksi,
    'event_type' => 'barang_masuk_dicatat',
    'produk_id' => $id_produk,
    'gudang_id' => $produkAfter['id_gudang'] ?? null,
    'user_id' => $operator,
    'user_name_snapshot' => get_current_user_name($koneksi) ?? 'System',
    'deskripsi' => 'Barang masuk tercatat dengan invoice ' . $no_invoice,
    'meta_json' => [
        'no_invoice' => $no_invoice,
        'jumlah' => intval($jumlah),
        'tanggal' => $tanggal,
        'keterangan' => $keterangan,
    ],
]);

header("Location: ../index.php?page=barang_masuk&success=Barang masuk berhasil ditambahkan");
?>
