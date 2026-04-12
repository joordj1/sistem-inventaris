<?php
include '../koneksi/koneksi.php';
require_once __DIR__ . '/simpan_histori_log.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=barang_keluar',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=tambah_barang_keluar');
    exit();
}

$no_invoice = htmlspecialchars(trim((string) ($_POST['no_invoice'] ?? '')), ENT_QUOTES, 'UTF-8');
$id_produk = intval($_POST['kode_produk'] ?? 0);
$jumlah = $_POST['jumlah'] ?? 0;
$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$keterangan = htmlspecialchars(trim((string) ($_POST['keterangan'] ?? '')), ENT_QUOTES, 'UTF-8');

if ($no_invoice === '') {
    header("Location: ../index.php?page=tambah_barang_keluar&error=No Invoice wajib diisi");
    exit();
}

if ($id_produk <= 0) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Produk harus dipilih");
    exit();
}

if (!is_numeric($jumlah) || intval($jumlah) <= 0) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Jumlah barang keluar harus lebih dari 0");
    exit();
}
$jumlah = intval($jumlah);

if (!empty($_POST['gudang_tujuan_id']) || !empty($_POST['tujuan_gudang_id'])) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Perpindahan antar gudang tidak boleh dicatat sebagai barang keluar. Gunakan menu mutasi barang.");
    exit();
}

// Validasi tipe item (hanya consumable boleh keluar stok)
$produkTipeStmt = $koneksi->prepare("SELECT tipe_barang FROM produk WHERE id_produk = ? LIMIT 1");
if (!$produkTipeStmt) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Gagal memvalidasi produk");
    exit();
}
$produkTipeStmt->bind_param('i', $id_produk);
$produkTipeStmt->execute();
$produkTipe = $produkTipeStmt->get_result()->fetch_assoc();
if (!$produkTipe || $produkTipe['tipe_barang'] !== 'consumable') {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Hanya produk consumable yang bisa diproses sebagai barang keluar");
    exit();
}

// Ambil status awal untuk tracking sekaligus snapshot harga master
$produkStmt = $koneksi->prepare("SELECT kode_produk, status, kondisi, lokasi_custom, id_gudang, id_user, jumlah_stok, COALESCE(NULLIF(harga_default, 0), harga_satuan, 0) AS harga_master FROM produk WHERE id_produk = ? LIMIT 1");
if (!$produkStmt) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Gagal memuat data produk");
    exit();
}
$produkStmt->bind_param('i', $id_produk);
$produkStmt->execute();
$produk = $produkStmt->get_result()->fetch_assoc();
if (!$produk) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Produk tidak valid");
    exit();
}

if (intval($produk['jumlah_stok']) < $jumlah) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Stok tidak mencukupi");
    exit();
}
$harga_satuan = (int) round((float) ($produk['harga_master'] ?? 0));
$tanggal_sql = $koneksi->real_escape_string($tanggal);
$keterangan_sql = $koneksi->real_escape_string($keterangan);

// === TRANSACTION: kurangi stok + stokgudang + INSERT transaksi ===
$insertTransaksiStmt = $koneksi->prepare(
    "INSERT INTO stoktransaksi (no_invoice, id_produk, jumlah, harga_satuan, tanggal, keterangan, tipe_transaksi) VALUES (?, ?, ?, ?, ?, ?, 'keluar')"
);
if (!$insertTransaksiStmt) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Gagal menyimpan transaksi keluar");
    exit();
}
$insertTransaksiStmt->bind_param('siiiss', $no_invoice, $id_produk, $jumlah, $harga_satuan, $tanggal_sql, $keterangan_sql);

$koneksi->begin_transaction();
try {
    // Kurangi stok produk (prepared statement)
    $updProdukStmt = $koneksi->prepare(
        "UPDATE produk
            SET jumlah_stok = jumlah_stok - ?,
                status = CASE WHEN jumlah_stok - ? <= 0 THEN 'dipinjam' ELSE status END,
                tersedia = CASE WHEN jumlah_stok - ? <= 0 THEN 0 ELSE tersedia END,
                last_tracked_at = NOW()
          WHERE id_produk = ? AND jumlah_stok >= ?"
    );
    if (!$updProdukStmt) {
        throw new \RuntimeException('Gagal menyiapkan update stok produk.');
    }
    $updProdukStmt->bind_param('iiiii', $jumlah, $jumlah, $jumlah, $id_produk, $jumlah);
    $updProdukStmt->execute();
    if ($updProdukStmt->affected_rows < 1) {
        throw new \RuntimeException('Stok tidak mencukupi atau sudah berubah oleh transaksi lain.');
    }

    // Kurangi stokgudang
    if (!empty($produk['id_gudang'])) {
        $stokGudangUpdate = $koneksi->prepare("UPDATE stokgudang SET jumlah_stok = GREATEST(jumlah_stok - ?, 0) WHERE id_produk = ? AND id_gudang = ?");
        if ($stokGudangUpdate) {
            $stokGudangUpdate->bind_param('iii', $jumlah, $id_produk, $produk['id_gudang']);
            $stokGudangUpdate->execute();
        }
    }

    // Insert transaksi
    if (!$insertTransaksiStmt->execute()) {
        throw new \RuntimeException('Gagal menyimpan transaksi keluar.');
    }
    $id_transaksi = $koneksi->insert_id;

    $koneksi->commit();
} catch (\Throwable $txErr) {
    $koneksi->rollback();
    log_event('ERROR', 'STOK', 'simpan_barang_keluar gagal: ' . $txErr->getMessage());
    header("Location: ../index.php?page=tambah_barang_keluar&error=" . urlencode('Transaksi gagal disimpan. Silakan coba lagi.'));
    exit();
}
// === END TRANSACTION ===

// catat tracking
$operator = $_SESSION['id_user'] ?? null;
$location = '';
if ($produk['id_gudang']) {
    $lok = $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = " . intval($produk['id_gudang']))->fetch_assoc();
    $location = $lok['nama_gudang'] ?? '';
} elseif (!empty($produk['lokasi_custom'])) {
    $location = $produk['lokasi_custom'];
}
$status_sesudah = ($produk['jumlah_stok'] - $jumlah <= 0) ? 'dipinjam' : 'tersedia';
log_tracking_history($koneksi, [
    'id_produk' => $id_produk,
    'kode_produk' => $produk['kode_produk'] ?? null,
    'status_sebelum' => $produk['status'],
    'status_sesudah' => $status_sesudah,
    'kondisi_sebelum' => $produk['kondisi'],
    'kondisi_sesudah' => $produk['kondisi'],
    'lokasi_sebelum' => $location,
    'lokasi_sesudah' => $location,
    'id_user_sebelum' => $produk['id_user'],
    'id_user_sesudah' => $produk['id_user'],
    'id_user_terkait' => $produk['id_user'],
    'activity_type' => 'keluarmasuk',
    'note' => $keterangan,
    'id_user_changed' => $operator
]);

if (trim((string) $keterangan) !== '') {
    save_inventory_note($koneksi, [
        'tipe_target' => 'transaksi',
        'kategori_catatan' => 'transaksi',
        'judul' => 'Barang keluar ' . $no_invoice,
        'catatan' => $keterangan,
        'id_produk' => $id_produk,
        'id_transaksi' => $id_transaksi,
        'id_gudang' => $produk['id_gudang'] ?? null,
        'created_by' => $operator,
    ]);
}

log_activity($koneksi, [
    'id_user' => $operator,
    'role_user' => get_current_user_role(),
    'action_name' => 'transaksi_keluar',
    'entity_type' => 'transaksi',
    'entity_id' => $id_transaksi,
    'entity_label' => $no_invoice,
    'description' => 'Mencatat barang keluar',
    'id_produk' => $id_produk,
    'id_transaksi' => $id_transaksi,
    'id_gudang' => $produk['id_gudang'] ?? null,
    'metadata_json' => [
        'no_invoice' => $no_invoice,
        'jumlah' => intval($jumlah),
        'harga_satuan' => $harga_satuan,
        'tanggal' => $tanggal,
        'keterangan' => $keterangan,
    ],
]);

save_official_histori_log_entry($koneksi, [
    'ref_type' => 'barang_keluar',
    'ref_id' => $id_transaksi,
    'event_type' => 'barang_keluar_dicatat',
    'produk_id' => $id_produk,
    'gudang_id' => $produk['id_gudang'] ?? null,
    'user_id' => $operator,
    'user_name_snapshot' => get_current_user_name($koneksi) ?? 'System',
    'deskripsi' => 'Barang keluar tercatat dengan invoice ' . $no_invoice,
    'meta_json' => [
        'no_invoice' => $no_invoice,
        'jumlah' => intval($jumlah),
        'tanggal' => $tanggal,
        'keterangan' => $keterangan,
        'tipe_pemisahan' => 'barang_keluar_bukan_mutasi',
    ],
]);

header('Location: ../index.php?page=barang_keluar');
exit();
?>
