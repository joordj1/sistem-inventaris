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
include '../koneksi/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $kodeProduk = $_POST['code'];
    $namaProduk = $_POST['namaproduk'];
    $kategoriId = $_POST['kategori'];
    $gudangId = $_POST['gudang'];
    $stok = $_POST['stok'];
    $satuan = $_POST['satuan'];
    $hargaSatuan = $_POST['harga'];

    // Cek apakah kode produk sudah ada di database
    $cekKodeQuery = "SELECT * FROM produk WHERE kode_produk = '$kodeProduk'";
    $cekKodeResult = $koneksi->query($cekKodeQuery);

    if ($cekKodeResult->num_rows > 0) {
        echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Kode Produk Sudah Ada',
                    text: 'Kode produk yang Anda masukkan sudah terdaftar. Silakan gunakan kode lain.',
                    showConfirmButton: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../index.php?page=tambah_produk';
                    }
                });
              </script>";
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
                echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: 'Maaf, terjadi kesalahan saat mengunggah file.',
                            showConfirmButton: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = '../index.php?page=data_produk';
                            }
                        });
                      </script>";
                exit();
            }
        } else {
            echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'File Tidak Didukung',
                        text: 'Maaf, hanya file JPG, JPEG, PNG, & GIF yang diperbolehkan.',
                        showConfirmButton: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '../index.php?page=data_produk';
                        }
                    });
                  </script>";
            exit();
        }
    } else {
        $gambarProduk = null;
    }

    // Query untuk menyimpan data produk
    $queryProduk = "INSERT INTO produk (kode_produk, nama_produk, kategori_id, jumlah_stok, satuan, harga_satuan, gambar_produk)
                    VALUES ('$kodeProduk', '$namaProduk', '$kategoriId', '$stok', '$satuan', '$hargaSatuan', '$gambarProduk')";

    if ($koneksi->query($queryProduk) === TRUE) {
        // Dapatkan ID produk yang baru saja disimpan
        $lastProdukId = $koneksi->insert_id;

        // Simpan data stok produk berdasarkan gudang
        $queryStokGudang = "INSERT INTO StokGudang (gudang_id, produk_id, jumlah_stok) VALUES ('$gudangId', '$lastProdukId', '$stok')";
        $koneksi->query($queryStokGudang);

        echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Sukses',
                    text: 'Data produk berhasil disimpan!',
                    showConfirmButton: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../index.php?page=data_produk';
                    }
                });
              </script>";
    } else {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan: " . $koneksi->error . "',
                    showConfirmButton: true
                });
              </script>";
    }
} else {
    echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Permintaan Tidak Valid',
                text: 'Hanya menerima permintaan POST.',
                showConfirmButton: true
            });
          </script>";
}
?>
</body>
</html>
