<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tambah Kategori</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php
// Hubungkan ke database
include '../koneksi/koneksi.php'; // Pastikan file koneksi ke database ada

// Cek apakah data dari form sudah dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $nama_kategori = $_POST['nama_ktgr'];

    // Validasi input (opsional)
    if (empty($nama_kategori)) {
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Oops...',
                text: 'Nama kategori tidak boleh kosong!',
                confirmButtonText: 'OK'
            });
        </script>";
        exit;
    }

    // Cek apakah nama kategori sudah ada
    $checkQuery = "SELECT * FROM kategori WHERE nama_kategori = ?";
    $stmtCheck = $koneksi->prepare($checkQuery);
    $stmtCheck->bind_param("s", $nama_kategori);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        // Jika nama kategori sudah ada, tampilkan alert SweetAlert2
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Nama Kategori Sudah Ada',
                text: 'Gunakan nama kategori lain.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = '../index.php?page=tambah_kategori';
            });
        </script>";
    } else {
        // Jika nama kategori belum ada, simpan ke database
        $query = "INSERT INTO kategori (nama_kategori) VALUES (?)";
        $stmt = $koneksi->prepare($query);
        $stmt->bind_param("s", $nama_kategori);

        // Eksekusi statement
        if ($stmt->execute()) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Kategori berhasil disimpan!',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '../index.php?page=kategori_barang';
                });
            </script>";
            exit;
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal menyimpan kategori: " . $koneksi->error . "',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '../index.php?page=tambah_kategori';
                });
            </script>";
        }

        // Tutup statement
        $stmt->close();
    }

    // Tutup statement pengecekan
    $stmtCheck->close();
}

// Tutup koneksi
$koneksi->close();
?>

</body>
</html>
