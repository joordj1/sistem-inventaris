<?php
require_auth_roles(['admin'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=dashboard',
]);
?>
<div class="form-container">
    <div class="form-header">
        <h5>Form Tambah Data User</h5>
    </div>
    <?php $bidangRows = get_active_bidang_rows($koneksi); ?>
    <form action="action/simpan_user.php" method="POST">
        <div class="mb-3">
            <label for="nama" class="form-label">Nama</label>
            <input type="text" class="form-control" id="nama" name="nama" placeholder="Inputkan Nama User" required>
        </div>
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Inputkan Username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Inputkan Password" required>
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role" required>
                <option value="">--Pilih Role--</option>
                <option value="admin">Admin</option>
                <option value="petugas">Petugas</option>
                <option value="user">User</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status" required>
                <option value="aktif" selected>Aktif</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="kategori_user" class="form-label">Kategori User</label>
            <select class="form-select" id="kategori_user" name="kategori_user" required>
                <option value="staff">Staff</option>
                <option value="dosen">Dosen</option>
                <option value="mahasiswa">Mahasiswa</option>
                <option value="umum" selected>Umum</option>
            </select>
        </div>
        <div class="mb-3" id="bidang-wrapper">
            <label for="bidang_id" class="form-label">Bidang / Divisi</label>
            <select class="form-select" id="bidang_id" name="bidang_id">
                <option value="">--Pilih Bidang--</option>
                <?php foreach ($bidangRows as $bidang): ?>
                    <option value="<?= intval($bidang['id']) ?>"><?= htmlspecialchars($bidang['nama_bidang']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Wajib diisi untuk kategori staff.</small>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=user" class="btn btn-secondary">Kembali Ke Data User</a>
            <button type="submit" class="btn btn-primary">Simpan Data User</button>
        </div>
    </form>
</div>

<script>
function toggleBidangUserForm() {
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

document.getElementById('kategori_user').addEventListener('change', toggleBidangUserForm);
toggleBidangUserForm();
</script>
