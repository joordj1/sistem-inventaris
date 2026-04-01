<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php
session_start();
include '../koneksi/koneksi.php';

function showProdukAlert($icon, $title, $text, $redirect = '../index.php?page=data_produk') {
    $redirectJs = $redirect !== null
        ? "window.location.href = '" . addslashes($redirect) . "';"
        : "window.history.back();";

    echo "<script>
            Swal.fire({
                icon: '" . addslashes($icon) . "',
                title: '" . addslashes($title) . "',
                text: '" . addslashes($text) . "',
                showConfirmButton: true
            }).then(() => {
                $redirectJs
            });
          </script>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kodeProduk = trim((string) ($_POST['code'] ?? ''));
    $namaProduk = trim((string) ($_POST['namaproduk'] ?? ''));
    $satuan = trim((string) ($_POST['satuan'] ?? ''));
    $tipeBarangInput = trim((string) ($_POST['tipe_barang'] ?? 'consumable'));
    $tipeBarang = in_array($tipeBarangInput, ['consumable', 'asset'], true) ? $tipeBarangInput : null;

    $kategoriId = filter_var($_POST['kategori'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    $gudangId = filter_var($_POST['gudang'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    $stok = filter_var($_POST['stok'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0]
    ]);

    $hargaInput = trim((string) ($_POST['harga'] ?? ''));
    $hargaSatuan = preg_replace('/[^0-9]/', '', $hargaInput);
    if ($hargaSatuan === '') {
        $hargaSatuan = 0;
    }
    $hargaSatuan = (int) $hargaSatuan;
    if ($hargaSatuan < 1) {
        $hargaSatuan = 1;
    }
    if ($hargaSatuan > 1000000000) {
        $hargaSatuan = 1000000000;
    }

    if ($kodeProduk === '') {
        showProdukAlert('warning', 'Validasi Gagal', 'Kode produk wajib diisi.', '../index.php?page=tambah_produk');
        exit();
    }
    if ($namaProduk === '') {
        showProdukAlert('warning', 'Validasi Gagal', 'Nama produk wajib diisi.', '../index.php?page=tambah_produk');
        exit();
    }
    if ($kategoriId === false) {
        showProdukAlert('warning', 'Validasi Gagal', 'Kategori produk tidak valid.', '../index.php?page=tambah_produk');
        exit();
    }
    if ($gudangId === false) {
        showProdukAlert('warning', 'Validasi Gagal', 'Gudang produk tidak valid.', '../index.php?page=tambah_produk');
        exit();
    }
    if ($stok === false) {
        showProdukAlert('warning', 'Validasi Gagal', 'Stok harus berupa angka dan tidak boleh kurang dari 0.', '../index.php?page=tambah_produk');
        exit();
    }
    if ($satuan === '') {
        showProdukAlert('warning', 'Validasi Gagal', 'Satuan produk wajib diisi.', '../index.php?page=tambah_produk');
        exit();
    }
    if ($tipeBarang === null) {
        showProdukAlert('warning', 'Validasi Gagal', 'Tipe produk hanya boleh consumable atau asset.', '../index.php?page=tambah_produk');
        exit();
    }

    $kategoriCheck = $koneksi->query("SELECT COUNT(*) AS total FROM kategori WHERE id_kategori = " . intval($kategoriId));
    $kategoriExists = $kategoriCheck ? intval($kategoriCheck->fetch_assoc()['total']) > 0 : false;
    if (!$kategoriExists) {
        showProdukAlert('warning', 'Validasi Gagal', 'Kategori yang dipilih tidak ditemukan.', '../index.php?page=tambah_produk');
        exit();
    }

    $gudangCheck = $koneksi->query("SELECT COUNT(*) AS total FROM gudang WHERE id_gudang = " . intval($gudangId));
    $gudangExists = $gudangCheck ? intval($gudangCheck->fetch_assoc()['total']) > 0 : false;
    if (!$gudangExists) {
        showProdukAlert('warning', 'Validasi Gagal', 'Gudang yang dipilih tidak ditemukan.', '../index.php?page=tambah_produk');
        exit();
    }

    // Cek apakah kode produk sudah ada di database
    $kodeProdukEsc = $koneksi->real_escape_string($kodeProduk);
    $namaProdukEsc = $koneksi->real_escape_string($namaProduk);
    $satuanEsc = $koneksi->real_escape_string($satuan);
    $cekKodeQuery = "SELECT id_produk FROM produk WHERE kode_produk = '$kodeProdukEsc'";
    $cekKodeResult = $koneksi->query($cekKodeQuery);

    if ($cekKodeResult->num_rows > 0) {
        showProdukAlert('warning', 'Kode Produk Sudah Ada', 'Kode produk yang Anda masukkan sudah terdaftar. Silakan gunakan kode lain.', '../index.php?page=tambah_produk');
        exit();
    }

    // Handle upload gambar jika ada
    $targetDir = "../uploads/";
    $fileName = basename($_FILES["gambarproduk"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    $allowedTypes = array('jpg', 'jpeg', 'png', 'gif');

    if (!empty($fileName)) {
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["gambarproduk"]["tmp_name"], $targetFilePath)) {
                $gambarProduk = $fileName;
            } else {
                showProdukAlert('error', 'Gagal', 'Maaf, terjadi kesalahan saat mengunggah file.', '../index.php?page=data_produk');
                exit();
            }
        } else {
            showProdukAlert('error', 'File Tidak Didukung', 'Maaf, hanya file JPG, JPEG, PNG, & GIF yang diperbolehkan.', '../index.php?page=data_produk');
            exit();
        }
    } else {
        $gambarProduk = null;
    }

    // Tambahkan kolom status/kondisi/tersedia untuk tracking
    $statusDefault = 'tersedia';
    $kondisiDefault = 'baik';
    $tersediaDefault = 1;
    $jumlahUnit = ($tipeBarang === 'asset') ? intval($stok) : 0;
    $gambarProdukSql = $gambarProduk !== null ? "'" . $koneksi->real_escape_string($gambarProduk) . "'" : "NULL";

    $koneksi->begin_transaction();
    try {
        // Query untuk menyimpan data produk
        $queryProduk = "INSERT INTO produk (kode_produk, nama_produk, id_kategori, id_gudang, jumlah_stok, satuan, harga_satuan, gambar_produk, status, kondisi, tersedia, last_tracked_at, tipe_barang)
                        VALUES ('$kodeProdukEsc', '$namaProdukEsc', " . intval($kategoriId) . ", " . intval($gudangId) . ", " . intval($stok) . ", '$satuanEsc', " . intval($hargaSatuan) . ", $gambarProdukSql, '$statusDefault', '$kondisiDefault', $tersediaDefault, NOW(), '$tipeBarang')";

        if ($koneksi->query($queryProduk) !== TRUE) {
            throw new Exception($koneksi->error);
        }

        // Dapatkan ID produk yang baru saja disimpan
        $lastProdukId = $koneksi->insert_id;

        // Inisialisasi info operator/gudang untuk riwayat
        $userId = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0;
        $gudangName = '';
        $qGudang = $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = $gudangId");
        if ($qGudang && $qGudang->num_rows > 0) {
            $gudangName = $qGudang->fetch_assoc()['nama_gudang'];
        }

        // Simpan data stok produk berdasarkan gudang (hanya untuk consumable)
        if ($tipeBarang === 'consumable') {
            $queryStokGudang = "INSERT INTO StokGudang (id_gudang, id_produk, jumlah_stok) VALUES (" . intval($gudangId) . ", $lastProdukId, " . intval($stok) . ")";
            if (!$koneksi->query($queryStokGudang)) {
                throw new Exception($koneksi->error);
            }
        }

        // Untuk asset: pastikan jumlah unit detail sinkron dengan stok master.
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

        // Simpan history tracking awal (produk master)
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
            'id_user_changed' => $userId
        ]);

        $koneksi->commit();
        showProdukAlert('success', 'Sukses', 'Data produk berhasil disimpan!', '../index.php?page=data_produk');
    } catch (Exception $e) {
        $koneksi->rollback();
        showProdukAlert('error', 'Error', 'Terjadi kesalahan: ' . $e->getMessage(), null);
    }
} else {
    showProdukAlert('warning', 'Permintaan Tidak Valid', 'Hanya menerima permintaan POST.', null);
}
?>
</body>
</html>
