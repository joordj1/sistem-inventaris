<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <!-- SweetAlert CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    
<?php
session_start();
// Hanya admin boleh simpan user
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Akses ditolak";
    exit;
}

// Include koneksi database
include '../koneksi/koneksi.php';

// Ambil data dari form
$nama = $_POST['nama'];
$username = $_POST['username'];
$password = md5($_POST['password']); // Enkripsi password menggunakan MD5
$email = $_POST['email'];
$role = $_POST['role'];
// Normalisasi role: legacy values untuk pengalaman kompatibel
if ($role === 'leader') {
    $role = 'user';
}

// Query untuk memeriksa apakah username sudah ada
$queryCheckUsername = "SELECT * FROM user WHERE username = '$username'";
$resultCheckUsername = $koneksi->query($queryCheckUsername);

// Cek apakah username sudah ada
if ($resultCheckUsername->num_rows > 0) {
    // Jika username sudah ada, tampilkan SweetAlert dan berhenti
    echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Username sudah terdaftar!',
                text: 'Silakan gunakan username lain.',
                showConfirmButton: true
            }).then(() => {
                window.history.back(); // Kembali ke form sebelumnya
            });
          </script>";
    exit;
}

// Query untuk menyimpan data user ke database
$query = "INSERT INTO user (nama, username, password, email, role, created_at) 
          VALUES ('$nama', '$username', '$password', '$email', '$role', NOW())";

// Eksekusi query
if ($koneksi->query($query) === TRUE) {
    // Redirect ke halaman data user setelah berhasil
    header('Location: ../index.php?page=user');
    exit;
} else {
    // Jika gagal, tampilkan error
    echo "Error: " . $query . "<br>" . $koneksi->error;
}
?>




</body>
</html>
