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

$bidangRows = get_active_bidang_rows($koneksi, true);

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = !empty($_POST['password']) ? hash_inventory_password($_POST['password']) : $data['password'];
    $role = normalize_user_role($_POST['role'] ?? null);
    $status = normalize_user_status($_POST['status'] ?? ($data['status'] ?? 'aktif'));
    $kategoriUser = normalize_user_category($_POST['kategori_user'] ?? ($data['kategori_user'] ?? 'umum'));
    $bidangId = nullable_int_id($_POST['bidang_id'] ?? null);

    if ($kategoriUser === 'staff' && $bidangId === null) {
        echo "Bidang wajib dipilih untuk kategori staff.";
        exit;
    }

    if ($kategoriUser !== 'staff') {
        $bidangId = null;
    }

    if ($bidangId !== null && !inventory_bidang_exists($koneksi, $bidangId, true)) {
        echo "Bidang yang dipilih tidak valid.";
        exit;
    }

    $duplicateSql = "SELECT id_user FROM user WHERE username = ? AND id_user != ?";
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

    $updateSql = "UPDATE user SET nama = ?, username = ?, password = ?, role = ?, status = ?, kategori_user = ?, bidang_id = ?";
    if (schema_has_column_now($koneksi, 'user', 'deleted_at')) {
        $updateSql .= ", deleted_at = " . ($status === 'nonaktif' ? 'NOW()' : 'NULL');
    }
    $updateSql .= ", updated_at = NOW()";
    $updateSql .= " WHERE id_user = ?";
    $stmtUpdate = $koneksi->prepare($updateSql);
    $stmtUpdate->bind_param('ssssssii', $nama, $username, $password, $role, $status, $kategoriUser, $bidangId, $id_user);

    if ($stmtUpdate->execute()) {
        $actionName = ($status === 'nonaktif' && normalize_user_status($data['status'] ?? 'aktif') !== 'nonaktif') ? 'user_deactivate' : 'user_update';
        log_activity($koneksi, [
            'id_user' => current_user_id(),
            'role_user' => get_current_user_role(),
            'action_name' => $actionName,
            'entity_type' => 'user',
            'entity_id' => $id_user,
            'entity_label' => $nama . ' (' . $username . ')',
            'description' => $actionName === 'user_deactivate' ? 'Menonaktifkan user internal.' : 'Memperbarui data user internal.',
            'metadata_json' => [
                'old_role' => normalize_user_role($data['role'] ?? null),
                'new_role' => $role,
                'old_status' => normalize_user_status($data['status'] ?? 'aktif'),
                'new_status' => $status,
                'old_kategori_user' => normalize_user_category($data['kategori_user'] ?? 'umum'),
                'new_kategori_user' => $kategoriUser,
                'old_bidang_id' => nullable_int_id($data['bidang_id'] ?? null),
                'new_bidang_id' => $bidangId,
                'old_bidang_nama' => get_bidang_name_by_id($koneksi, $data['bidang_id'] ?? null),
                'new_bidang_nama' => get_bidang_name_by_id($koneksi, $bidangId),
            ],
        ]);

        if (!empty($_SESSION['id_user']) && intval($_SESSION['id_user']) === $id_user) {
            $_SESSION['username'] = $username;
            $_SESSION['nama'] = $nama;
            $_SESSION['role'] = $role;
            $_SESSION['status'] = $status;
            $_SESSION['kategori_user'] = $kategoriUser;
            $_SESSION['bidang_id'] = $bidangId;
        }

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
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role" required>
                <option value="admin" <?= (normalize_user_role($data['role']) === 'admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="petugas" <?= (normalize_user_role($data['role']) === 'petugas') ? 'selected' : ''; ?>>Petugas</option>
                <option value="user" <?= (normalize_user_role($data['role']) === 'user') ? 'selected' : ''; ?>>User</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status" required>
                <option value="aktif" <?= (normalize_user_status($data['status'] ?? 'aktif') === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                <option value="nonaktif" <?= (normalize_user_status($data['status'] ?? 'aktif') === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="kategori_user" class="form-label">Kategori User</label>
            <select class="form-select" id="kategori_user" name="kategori_user" required>
                <option value="staff" <?= (normalize_user_category($data['kategori_user'] ?? 'umum') === 'staff') ? 'selected' : ''; ?>>Staff</option>
                <option value="dosen" <?= (normalize_user_category($data['kategori_user'] ?? 'umum') === 'dosen') ? 'selected' : ''; ?>>Dosen</option>
                <option value="mahasiswa" <?= (normalize_user_category($data['kategori_user'] ?? 'umum') === 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa</option>
                <option value="umum" <?= (normalize_user_category($data['kategori_user'] ?? 'umum') === 'umum') ? 'selected' : ''; ?>>Umum</option>
            </select>
        </div>
        <div class="mb-3" id="bidang-wrapper">
            <label for="bidang_id" class="form-label">Bidang / Divisi</label>
            <select class="form-select" id="bidang_id" name="bidang_id">
                <option value="">--Pilih Bidang--</option>
                <?php foreach ($bidangRows as $bidang): ?>
                    <option value="<?= intval($bidang['id']) ?>" <?= (intval($data['bidang_id'] ?? 0) === intval($bidang['id'])) ? 'selected' : ''; ?>><?= htmlspecialchars($bidang['nama_bidang']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=user" class="btn btn-secondary">Kembali Ke Data User</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>

<script>
function toggleBidangEditUserForm() {
    const kategoriField = document.getElementById('kategori_user');
    const bidangWrapper = document.getElementById('bidang-wrapper');
    const bidangField = document.getElementById('bidang_id');
    const isStaff = kategoriField.value === 'staff';

    bidangWrapper.style.display = isStaff ? '' : 'none';
    bidangField.required = isStaff;
    if (!isStaff) {
        bidangField.value = '';
    }
}

document.getElementById('kategori_user').addEventListener('change', toggleBidangEditUserForm);
toggleBidangEditUserForm();
</script>
