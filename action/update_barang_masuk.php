<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=barang_masuk',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ../index.php?page=barang_masuk');
    exit;
}

$id_transaksi = intval($_POST['id_transaksi'] ?? 0);
$no_invoice = trim((string) ($_POST['no_invoice'] ?? ''));
$id_produk = intval($_POST['kode_produk'] ?? 0);
$jumlah = intval($_POST['jumlah'] ?? 0);

if ($id_transaksi < 1 || $no_invoice === '' || $id_produk < 1 || $jumlah < 1) {
    header('Location: ../index.php?page=barang_masuk&status=error');
    exit;
}

// EXISTS: pastikan id_transaksi ada
$stmtCekTx = $koneksi->prepare('SELECT id_transaksi FROM stoktransaksi WHERE id_transaksi = ? LIMIT 1');
if (!$stmtCekTx) {
    log_event('ERROR', 'STOK', 'update_barang_masuk prepare EXISTS transaksi gagal - ' . $koneksi->error);
    header('Location: ../index.php?page=barang_masuk&status=error');
    exit;
}
$stmtCekTx->bind_param('i', $id_transaksi);
$stmtCekTx->execute();
if (!$stmtCekTx->get_result()->fetch_assoc()) {
    header('Location: ../index.php?page=barang_masuk&status=error');
    exit;
}

// EXISTS: pastikan id_produk ada
$stmtCekProduk = $koneksi->prepare('SELECT id_produk FROM produk WHERE id_produk = ? LIMIT 1');
if (!$stmtCekProduk) {
    log_event('ERROR', 'STOK', 'update_barang_masuk prepare EXISTS produk gagal - ' . $koneksi->error);
    header('Location: ../index.php?page=barang_masuk&status=error');
    exit;
}
$stmtCekProduk->bind_param('i', $id_produk);
$stmtCekProduk->execute();
if (!$stmtCekProduk->get_result()->fetch_assoc()) {
    header('Location: ../index.php?page=barang_masuk&status=error');
    exit;
}

$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$keterangan = trim((string) ($_POST['keterangan'] ?? ''));
$harga_satuan = preg_replace('/[^0-9]/', '', (string) ($_POST['harga_satuan'] ?? ''));

// Update data barang masuk
$query = "UPDATE stoktransaksi SET no_invoice = ?, id_produk = ?, jumlah = ?";
$types = "sii";
$params = [$no_invoice, $id_produk, $jumlah];

if (schema_has_column_now($koneksi, 'stoktransaksi', 'harga_satuan') && $harga_satuan !== '') {
    $query .= ", harga_satuan = ?";
    $types .= "i";
    $params[] = intval($harga_satuan);
}

$query .= ", tanggal = ?, keterangan = ? WHERE id_transaksi = ?";
$types .= "ssi";
$params[] = $tanggal;
$params[] = $keterangan;
$params[] = $id_transaksi;

$stmt = $koneksi->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    header("Location: ../index.php?page=barang_masuk&status=success");
} else {
    log_event('ERROR', 'STOK', 'update_barang_masuk UPDATE gagal id=' . $id_transaksi . ' - ' . $stmt->error);
    header("Location: ../index.php?page=barang_masuk&status=error");
}
?>
