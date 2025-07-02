<?php


// Ambil data user berdasarkan id_user
$id_user = isset($_GET['id_user']) ? $_GET['id_user'] : '';
if ($id_user) {
    $query = "SELECT * FROM user WHERE id_user = '$id_user'";
    $result = $koneksi->query($query);
    $data = $result->fetch_assoc();
} else {
    echo "ID User tidak ditemukan!";
    exit;
}

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $password = $_POST['password'] ? md5($_POST['password']) : $data['password']; // Jika password kosong, gunakan password lama
    $email = $_POST['email'];
    $role = $_POST['role'];

    // Cek apakah username sudah ada di database (selain user yang sedang diedit)
    $query_check = "SELECT id_user FROM user WHERE username = '$username' AND id_user != '$id_user'";
    $result_check = $koneksi->query($query_check);

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

    // Query untuk update data user
    $query_update = "UPDATE user SET nama = '$nama', username = '$username', password = '$password', email = '$email', role = '$role' WHERE id_user = '$id_user'";

    // Eksekusi query
    if ($koneksi->query($query_update) === TRUE) {
        // Redirect ke halaman data user setelah berhasil
        header('Location: index.php?page=user');
        exit;
    } else {
        // Jika gagal, tampilkan error
        echo "Error: " . $query_update . "<br>" . $koneksi->error;
    }
}
?>

<!-- Form Edit User -->
<div class="form-container">
    <div class="form-header">
        <h5>Edit Data User</h5>
    </div>
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
                <option value="admin" <?= ($data['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="leader" <?= ($data['role'] == 'leader') ? 'selected' : ''; ?>>Leader</option>
            </select>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=user" class="btn btn-secondary">Kembali Ke Data User</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>
