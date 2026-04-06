
<?php
require_auth_roles(['admin'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=dashboard',
]);
?>
<div class="container mb-5">
    <h2 class="text-center mb-4">Data User</h2>

    <div class="table-container">
        <table class="table table-bordered table-success table-striped table-hover">
            <thead class="text-center">
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php 
                // Query untuk mengambil data user
                $query = "SELECT id_user, nama, username, email, role, created_at FROM user ORDER BY created_at DESC";
                $result = $koneksi->query($query);
                $nomor = 1;

                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
            ?>
                <tr>
                    <td class="text-center"><?= $nomor++ ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td class="text-center">****</td> <!-- Password disembunyikan -->
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td class="text-center"><?= htmlspecialchars(normalize_user_role($row['role'])) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td class="text-center">
                        <a href="index.php?page=edit_user&id_user=<?= $row['id_user'] ?>"><i class="bi-pencil fs-4 mx-3"></i></a>
                        <!-- Tombol hapus user -->
                        <a href="javascript:void(0);" onclick="confirmDeleteUser(<?= $row['id_user'] ?>)"><i class="bi-trash fs-4"></i></a>


                    </td>
                </tr>
            <?php 
                    endwhile;
                else: 
            ?>
                <tr>
                    <td colspan="8" class="text-center">Tidak ada data user yang ditemukan.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="footer-buttons">
        <a href="index.php?page=tambah_user"><button class="btn btn-primary float-start">+ Tambah User Baru</button></a>
        <a href="index.php?page=dashboard"><button class="btn btn-secondary float-end">Tutup</button></a>
    </div>
    <div class="clear-fix"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDeleteUser(id_user) {
        if (confirm("Apakah Anda yakin ingin menghapus user ini?")) {
            window.location.href = "index.php?page=hapus_user&id_user=" + id_user;
        }
    }
</script>
