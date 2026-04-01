<?php
session_start();
include 'koneksi/koneksi.php';

$loginMessage = '';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Enkripsi password menggunakan MD5
    $passwordHash = md5($password);

    // Query untuk mengecek username dan password
    $query = "SELECT * FROM user WHERE username = '$username' AND password = '$passwordHash'";
    $result = mysqli_query($koneksi, $query);

    if (mysqli_num_rows($result) > 0) {
        // Pengguna ditemukan
        $user = mysqli_fetch_assoc($result);

        // Normalisasi role legacy: leader menjadi user.
        $role = ($user['role'] === 'leader') ? 'user' : $user['role'];

        // Simpan data user dalam session
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;

        // Update role di DB bila ada nilai legacy
        if ($user['role'] !== $role) {
            $koneksi->query("UPDATE user SET role = 'user' WHERE id_user = " . intval($user['id_user']));
        }

        // Set pesan untuk login sukses
        $loginMessage = "success|Selamat datang, {$user['username']}!";
    } else {
        // Jika login gagal
        $loginMessage = "error|Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Inventaris</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="login-container">
    <form method="POST" action="login.php">
        <div class="logo">
            <i class="bi bi-box-seam logo-size"></i>
        </div>
        <h2>Sistem Informasi Inventaris</h2>
        <p>Login sebagai admin atau user!</p>

        <div class="input-group">
            <i class="bi bi-person-fill"></i>
            <input type="text" id="username" name="username" placeholder="username" required>
        </div>

        <div class="input-group">
            <i class="bi bi-lock-fill"></i>
            <input type="password" id="password" name="password" placeholder="password" required>
        </div>

        <button class="login-btn" name="login" type="submit">Login</button>
    </form>
</div>

<script>
// Cek apakah ada pesan login dari PHP
let loginMessage = "<?php echo $loginMessage; ?>";

if (loginMessage) {
    let messageParts = loginMessage.split('|');
    let messageType = messageParts[0];
    let messageText = messageParts[1];

    Swal.fire({
        icon: messageType,
        title: messageType === 'success' ? 'Login Berhasil' : 'Login Gagal',
        text: messageText
    }).then(function() {
        if (messageType === 'success') {
            window.location.href = 'index.php';
        }
    });
}
</script>
</body>
</html>
