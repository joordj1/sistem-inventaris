<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=barang_keluar',
]);

$no_invoice = trim((string) ($_POST['no_invoice'] ?? ''));
$id_produk = intval($_POST['kode_produk'] ?? 0);
$jumlah = $_POST['jumlah'] ?? 0;
$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$keterangan = trim((string) ($_POST['keterangan'] ?? ''));

if (!is_numeric($jumlah) || intval($jumlah) <= 0) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Jumlah barang keluar harus lebih dari 0");
    exit();
}
$jumlah = intval($jumlah);

// Validasi tipe item (hanya consumable boleh keluar stok)
$produkTipe = $koneksi->query("SELECT tipe_barang FROM produk WHERE id_produk = $id_produk")->fetch_assoc();
if (!$produkTipe || $produkTipe['tipe_barang'] !== 'consumable') {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Hanya produk consumable yang bisa diproses sebagai barang keluar");
    exit();
}

// Ambil status awal untuk tracking sekaligus snapshot harga master
$produk = $koneksi->query("SELECT kode_produk, status, kondisi, lokasi_custom, id_gudang, id_user, jumlah_stok, COALESCE(NULLIF(harga_default, 0), harga_satuan, 0) AS harga_master FROM produk WHERE id_produk = $id_produk")->fetch_assoc();
if (!$produk || intval($produk['jumlah_stok']) < $jumlah) {
    header("Location: ../index.php?page=tambah_barang_keluar&error=Stok tidak mencukupi untuk transaksi keluar");
    exit();
}
$harga_satuan = (int) round((float) ($produk['harga_master'] ?? 0));
$no_invoice_sql = $koneksi->real_escape_string($no_invoice);
$tanggal_sql = $koneksi->real_escape_string($tanggal);
$keterangan_sql = $koneksi->real_escape_string($keterangan);

// Query untuk mengurangi stok produk
$queryKurangiStok = "UPDATE produk SET jumlah_stok = jumlah_stok - $jumlah, status = CASE WHEN jumlah_stok - $jumlah <= 0 THEN 'dipinjam' ELSE status END, tersedia = CASE WHEN jumlah_stok - $jumlah <= 0 THEN 0 ELSE tersedia END, last_tracked_at = NOW() WHERE id_produk = $id_produk";
$koneksi->query($queryKurangiStok);

if (!empty($produk['id_gudang'])) {
    $stokGudangUpdate = $koneksi->prepare("UPDATE stokgudang SET jumlah_stok = GREATEST(jumlah_stok - ?, 0) WHERE id_produk = ? AND id_gudang = ?");
    if ($stokGudangUpdate) {
        $stokGudangUpdate->bind_param('iii', $jumlah, $id_produk, $produk['id_gudang']);
        $stokGudangUpdate->execute();
    }
}

// Simpan data barang keluar ke tabel stoktransaksi
$queryInsertTransaksi = "INSERT INTO stoktransaksi (no_invoice, id_produk, jumlah, harga_satuan, tanggal, keterangan, tipe_transaksi) 
                         VALUES ('$no_invoice_sql', $id_produk, $jumlah, '$harga_satuan', '$tanggal_sql', '$keterangan_sql', 'keluar')";
$koneksi->query($queryInsertTransaksi);
$id_transaksi = $koneksi->insert_id;

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

header('Location: ../index.php?page=barang_keluar');
exit();
?>
