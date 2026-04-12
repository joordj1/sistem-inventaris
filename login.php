<?php
session_start();
include 'koneksi/koneksi.php';

$loginMessage = '';

if (isset($_POST['login'])) {
    if (!validate_csrf_token()) {
        log_event('WARNING', 'CSRF', 'Token login tidak valid | ip=' . get_inventory_client_ip());
        $loginMessage = "error|Permintaan login ditolak (CSRF).";
    }

    if ($loginMessage !== '') {
        // stop processing
    } else {
    $loginRate = inventory_rate_limit_check('login_attempt', 10, 60);
    if (!$loginRate['allowed']) {
        log_event('WARNING', 'AUTH', 'Rate limit login terlampaui | ip=' . get_inventory_client_ip() . ' | retry_after=' . intval($loginRate['retry_after']));
        $loginMessage = "error|Terlalu banyak percobaan login. Coba lagi dalam " . intval($loginRate['retry_after']) . " detik.";
    }

    if ($loginMessage !== '') {
        // stop further auth processing when limited
    } else {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $loginSql = "SELECT * FROM user WHERE username = ? LIMIT 1";

    $stmt = $koneksi->prepare($loginSql);
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        $status = normalize_user_status($user['status'] ?? 'aktif');
        $isDeleted = schema_has_column_now($koneksi, 'user', 'deleted_at') && !empty($user['deleted_at']);

        if ($isDeleted || $status !== 'aktif') {
            log_event('WARNING', 'AUTH', 'Login ditolak akun nonaktif | user=' . $username . ' | ip=' . get_inventory_client_ip());
            $loginMessage = "error|Akun ini sedang nonaktif.";
        } elseif (!verify_inventory_password($password, $user['password'] ?? null)) {
            log_event('WARNING', 'AUTH', 'Login gagal password salah | user=' . $username . ' | ip=' . get_inventory_client_ip());
            $loginMessage = "error|Username atau Password salah!";
        } else {
            $role = normalize_user_role($user['role'] ?? null);
            $kategoriUser = normalize_user_category($user['kategori_user'] ?? 'umum');
            $bidangId = nullable_int_id($user['bidang_id'] ?? null);

            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama'] = $user['nama'] ?? $user['username'];
            $_SESSION['role'] = $role;
            $_SESSION['status'] = $status;
            $_SESSION['kategori_user'] = $kategoriUser;
            $_SESSION['bidang_id'] = $bidangId;

            if (inventory_password_needs_upgrade($user['password'] ?? null)) {
                $newPasswordHash = hash_inventory_password($password);
                $upgradeStmt = $koneksi->prepare("UPDATE user SET password = ?, updated_at = NOW() WHERE id_user = ?");
                if ($upgradeStmt) {
                    $userId = intval($user['id_user']);
                    $upgradeStmt->bind_param('si', $newPasswordHash, $userId);
                    $upgradeStmt->execute();
                }
            }

            if (($user['role'] ?? null) !== $role || ($user['status'] ?? 'aktif') !== $status) {
                $updateUserStmt = $koneksi->prepare("UPDATE user SET role = ?, status = ?, updated_at = NOW() WHERE id_user = ?");
                if ($updateUserStmt) {
                    $userId = intval($user['id_user']);
                    $updateUserStmt->bind_param('ssi', $role, $status, $userId);
                    $updateUserStmt->execute();
                }
            }

            $loginMessage = "success|Selamat datang, {$user['username']}!";
        }
    } else {
        log_event('WARNING', 'AUTH', 'Login gagal user tidak ditemukan | user=' . $username . ' | ip=' . get_inventory_client_ip());
        $loginMessage = "error|Username atau Password salah!";
    }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Login - Sistem Inventaris</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="login-container">
    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="logo">
            <i class="bi bi-box-seam logo-size"></i>
        </div>
        <h2>Sistem Informasi Inventaris</h2>
        <p>Login internal menggunakan username dan password.</p>

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
