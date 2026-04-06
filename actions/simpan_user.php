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
include '../koneksi/koneksi.php';
require_auth_roles(['admin'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

$nama = trim((string) ($_POST['nama'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$passwordRaw = (string) ($_POST['password'] ?? '');
$password = md5($passwordRaw);
$email = trim((string) ($_POST['email'] ?? ''));
$role = normalize_user_role($_POST['role'] ?? null);

if ($nama === '' || $username === '' || $passwordRaw === '' || $email === '') {
    echo "Data user wajib diisi lengkap.";
    exit;
}

$stmtCheck = $koneksi->prepare("SELECT id_user FROM user WHERE username = ? LIMIT 1");
$stmtCheck->bind_param('s', $username);
$stmtCheck->execute();
$resultCheckUsername = $stmtCheck->get_result();

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
$stmtInsert = $koneksi->prepare("INSERT INTO user (nama, username, password, email, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmtInsert->bind_param('sssss', $nama, $username, $password, $email, $role);

if ($stmtInsert->execute()) {
    header('Location: ../index.php?page=user');
    exit;
} else {
    echo "Error: " . $koneksi->error;
}
?>




</body>
</html>
