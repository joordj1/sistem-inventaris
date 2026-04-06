<?php
session_start();
include 'koneksi/koneksi.php';

$loginMessage = '';

if (isset($_POST['login'])) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Enkripsi password menggunakan MD5
    $passwordHash = md5($password);

    $stmt = $koneksi->prepare("SELECT * FROM user WHERE username = ? AND password = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $username, $passwordHash);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        $role = normalize_user_role($user['role'] ?? null);

        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;

        if ($user['role'] !== $role) {
            $updateRoleStmt = $koneksi->prepare("UPDATE user SET role = ? WHERE id_user = ?");
            if ($updateRoleStmt) {
                $userId = intval($user['id_user']);
                $updateRoleStmt->bind_param('si', $role, $userId);
                $updateRoleStmt->execute();
            }
        }

        $loginMessage = "success|Selamat datang, {$user['username']}!";
    } else {
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
        <p>Login sebagai admin, petugas, atau viewer.</p>

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
