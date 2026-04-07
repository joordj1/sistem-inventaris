<?php
require_auth_roles(['admin'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=dashboard',
]);

// Ambil data user berdasarkan id_user
$id_user = isset($_GET['id_user']) ? intval($_GET['id_user']) : 0;
if ($id_user) {
    $userSql = "SELECT * FROM user WHERE id_user = ?";
    $stmt = $koneksi->prepare($userSql);
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    if (!$data) {
        echo "Data user tidak ditemukan!";
        exit;
    }
} else {
    echo "ID User tidak ditemukan!";
    exit;
}

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = !empty($_POST['password']) ? md5($_POST['password']) : $data['password'];
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = normalize_user_role($_POST['role'] ?? null);

    $duplicateSql = "SELECT id_user FROM user WHERE username = ? AND id_user != ?";
    if (schema_has_column_now($koneksi, 'user', 'deleted_at')) {
        $duplicateSql .= " AND deleted_at IS NULL";
    }
    $stmtCheck = $koneksi->prepare($duplicateSql);
    $stmtCheck->bind_param('si', $username, $id_user);
    $stmtCheck->execute();
    $result_check = $stmtCheck->get_result();

    if ($result_check->num_rows > 0) {
        // Jika username sudah ada, tampilkan alert
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Username Sudah Ada',
                text: 'Username yang Anda masukkan sudah terdaftar. Silakan gunakan username lain.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit;
    }

    $updateSql = "UPDATE user SET nama = ?, username = ?, password = ?, email = ?, role = ?";
    if (schema_has_column_now($koneksi, 'user', 'updated_at')) {
        $updateSql .= ", updated_at = NOW()";
    }
    $updateSql .= " WHERE id_user = ?";
    $stmtUpdate = $koneksi->prepare($updateSql);
    $stmtUpdate->bind_param('sssssi', $nama, $username, $password, $email, $role, $id_user);

    if ($stmtUpdate->execute()) {
        header('Location: index.php?page=user');
        exit;
    } else {
        echo "Error: " . $koneksi->error;
    }
}
?>

<!-- Form Edit User -->
<div class="form-container">
    <div class="form-header">
        <h5>Edit Data User</h5>
    </div>
    <?php if (schema_has_column_now($koneksi, 'user', 'deleted_at')): ?>
    <div class="mb-3">
        <span class="badge <?= empty($data['deleted_at']) ? 'bg-success' : 'bg-danger' ?>">
            <?= empty($data['deleted_at']) ? 'Aktif' : 'Nonaktif' ?>
        </span>
    </div>
    <?php endif; ?>
    <form action="" method="post">
        <div class="mb-3">
            <label for="nama" class="form-label">Nama</label>
            <input type="text" class="form-control" id="nama" name="nama" value="<?= $data['nama']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="<?= $data['username']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password baru (kosongkan jika tidak diubah)">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= $data['email']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role" required>
                <option value="admin" <?= (normalize_user_role($data['role']) === 'admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="petugas" <?= (normalize_user_role($data['role']) === 'petugas') ? 'selected' : ''; ?>>Petugas</option>
                <option value="viewer" <?= (normalize_user_role($data['role']) === 'viewer') ? 'selected' : ''; ?>>Viewer</option>
            </select>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=user" class="btn btn-secondary">Kembali Ke Data User</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>
