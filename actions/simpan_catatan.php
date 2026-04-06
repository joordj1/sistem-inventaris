<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=dashboard');
    exit;
}

$tipeTarget = trim((string) ($_POST['tipe_target'] ?? 'produk'));
$kategoriCatatan = trim((string) ($_POST['kategori_catatan'] ?? 'umum'));
$judul = trim((string) ($_POST['judul'] ?? ''));
$catatan = trim((string) ($_POST['catatan'] ?? ''));
$idProduk = isset($_POST['id_produk']) && $_POST['id_produk'] !== '' ? intval($_POST['id_produk']) : null;
$idTransaksi = isset($_POST['id_transaksi']) && $_POST['id_transaksi'] !== '' ? intval($_POST['id_transaksi']) : null;
$idUnitBarang = isset($_POST['id_unit_barang']) && $_POST['id_unit_barang'] !== '' ? intval($_POST['id_unit_barang']) : null;
$idGudang = isset($_POST['id_gudang']) && $_POST['id_gudang'] !== '' ? intval($_POST['id_gudang']) : null;
$createdBy = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;

if ($catatan === '') {
    if ($idProduk) {
        header('Location: ../index.php?page=produk_info&id_produk=' . $idProduk . '&error=catatan_kosong');
    } elseif ($idGudang) {
        header('Location: ../index.php?page=gudang_info&id_gudang=' . $idGudang . '&error=catatan_kosong');
    } else {
        header('Location: ../index.php?page=dashboard&error=catatan_kosong');
    }
    exit;
}

$saved = save_inventory_note($koneksi, [
    'tipe_target' => $tipeTarget,
    'kategori_catatan' => $kategoriCatatan,
    'judul' => $judul,
    'catatan' => $catatan,
    'id_produk' => $idProduk,
    'id_transaksi' => $idTransaksi,
    'id_unit_barang' => $idUnitBarang,
    'id_gudang' => $idGudang,
    'created_by' => $createdBy,
]);

if ($saved) {
    log_activity($koneksi, [
        'id_user' => $createdBy,
        'role_user' => get_current_user_role(),
        'action_name' => 'catatan_simpan',
        'entity_type' => $tipeTarget,
        'entity_id' => $idProduk ?? $idTransaksi ?? $idUnitBarang ?? $idGudang,
        'entity_label' => $judul !== '' ? $judul : 'Catatan inventaris',
        'description' => $catatan,
        'id_produk' => $idProduk,
        'id_transaksi' => $idTransaksi,
        'id_unit_barang' => $idUnitBarang,
        'id_gudang' => $idGudang,
        'metadata_json' => [
            'kategori_catatan' => $kategoriCatatan,
            'judul' => $judul,
        ],
    ]);
}

if ($idProduk) {
    header('Location: ../index.php?page=produk_info&id_produk=' . $idProduk . ($saved ? '&success=catatan_tersimpan' : '&error=catatan_gagal'));
    exit;
}

if ($idGudang) {
    header('Location: ../index.php?page=gudang_info&id_gudang=' . $idGudang . ($saved ? '&success=catatan_tersimpan' : '&error=catatan_gagal'));
    exit;
}

header('Location: ../index.php?page=dashboard' . ($saved ? '&success=catatan_tersimpan' : '&error=catatan_gagal'));
exit;
?>
