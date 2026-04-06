<?php
require '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=barang_masuk',
]);

$no_invoice = trim((string) ($_POST['no_invoice'] ?? ''));
$id_produk = intval($_POST['kode_produk'] ?? 0);
$jumlah = $_POST['jumlah'] ?? 0;
$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$keterangan = trim((string) ($_POST['keterangan'] ?? ''));
$harga_satuan = preg_replace('/[^0-9]/', '', (string) ($_POST['harga_satuan'] ?? ''));

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
$produkBefore = $koneksi->query("SELECT kode_produk, tipe_barang, status, kondisi, lokasi_custom, id_gudang, id_user FROM produk WHERE id_produk = '$id_produk'")->fetch_assoc();
if (!$produkBefore || $produkBefore['tipe_barang'] !== 'consumable') {
    header("Location: ../index.php?page=tambah_barang_masuk&error=Hanya produk consumable yang bisa diproses sebagai barang masuk");
    exit();
}

// Cek apakah No Invoice sudah ada
$no_invoice_sql = $koneksi->real_escape_string($no_invoice);
$tanggal_sql = $koneksi->real_escape_string($tanggal);
$keterangan_sql = $koneksi->real_escape_string($keterangan);

$cekInvoice = $koneksi->query("SELECT * FROM stoktransaksi WHERE no_invoice = '$no_invoice_sql'");
if ($cekInvoice->num_rows > 0) {
    header("Location: ../index.php?page=tambah_barang_masuk&error=No Invoice sudah ada");
    exit();
}

// Tambahkan data barang masuk ke tabel stoktransaksi
$query = "INSERT INTO stoktransaksi (no_invoice, id_produk, jumlah, harga_satuan, tanggal, keterangan, tipe_transaksi) 
          VALUES ('$no_invoice_sql', '$id_produk', '$jumlah', '$harga_satuan', '$tanggal_sql', '$keterangan_sql', 'masuk')";
$koneksi->query($query);
$id_transaksi = $koneksi->insert_id;

// Update jumlah stok di tabel produk
$koneksi->query("UPDATE produk SET jumlah_stok = jumlah_stok + $jumlah, status = 'tersedia', tersedia = 1, last_tracked_at = NOW() WHERE id_produk = '$id_produk'");

if (!empty($produkBefore['id_gudang'])) {
    $stokGudangUpdate = $koneksi->prepare("UPDATE stokgudang SET jumlah_stok = jumlah_stok + ? WHERE id_produk = ? AND id_gudang = ?");
    if ($stokGudangUpdate) {
        $stokGudangUpdate->bind_param('iii', $jumlah, $id_produk, $produkBefore['id_gudang']);
        $stokGudangUpdate->execute();
        if ($stokGudangUpdate->affected_rows < 1) {
            $stokGudangInsert = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
            if ($stokGudangInsert) {
                $stokGudangInsert->bind_param('iii', $produkBefore['id_gudang'], $id_produk, $jumlah);
                $stokGudangInsert->execute();
            }
        }
    }
}

// catat tracking
$produkAfter = $koneksi->query("SELECT kode_produk, status, kondisi, lokasi_custom, id_gudang, id_user FROM produk WHERE id_produk = '$id_produk'")->fetch_assoc();
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

header("Location: ../index.php?page=barang_masuk&success=Barang masuk berhasil ditambahkan");
?>
