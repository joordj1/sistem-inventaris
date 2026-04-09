
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
                    <th>Role</th>
                    <th>Kategori</th>
                    <th>Bidang / Divisi</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php 
                // Query untuk mengambil data user
                $query = "SELECT u.id_user, u.nama, u.username, u.role, u.created_at";
                if (schema_has_column_now($koneksi, 'user', 'status')) {
                    $query .= ", u.status";
                }
                if (schema_has_column_now($koneksi, 'user', 'kategori_user')) {
                    $query .= ", u.kategori_user";
                }
                if (schema_has_column_now($koneksi, 'user', 'updated_at')) {
                    $query .= ", u.updated_at";
                }
                if (schema_has_column_now($koneksi, 'user', 'deleted_at')) {
                    $query .= ", u.deleted_at";
                }
                if (schema_has_column_now($koneksi, 'user', 'bidang_id') && schema_table_exists_now($koneksi, 'bidang')) {
                    $query .= ", b.nama_bidang";
                }
                $query .= " FROM user u";
                if (schema_has_column_now($koneksi, 'user', 'bidang_id') && schema_table_exists_now($koneksi, 'bidang')) {
                    $query .= " LEFT JOIN bidang b ON u.bidang_id = b.id";
                }
                $query .= " ORDER BY ";
                if (schema_has_column_now($koneksi, 'user', 'deleted_at')) {
                    $query .= "CASE WHEN u.deleted_at IS NULL THEN 0 ELSE 1 END, ";
                }
                $query .= "u.created_at DESC";
                $result = $koneksi->query($query);
                $nomor = 1;

                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                        $statusUser = normalize_user_status($row['status'] ?? (!empty($row['deleted_at']) ? 'nonaktif' : 'aktif'));
                        $isNonaktif = $statusUser !== 'aktif' || !empty($row['deleted_at']);
            ?>
                <tr>
                    <td class="text-center"><?= $nomor++ ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td class="text-center"><?= htmlspecialchars(normalize_user_role($row['role'])) ?></td>
                    <td class="text-center"><?= htmlspecialchars(normalize_user_category($row['kategori_user'] ?? 'umum')) ?></td>
                    <td><?= htmlspecialchars($row['nama_bidang'] ?? '-') ?></td>
                    <td class="text-center">
                        <span class="badge <?= $isNonaktif ? 'bg-danger' : 'bg-success' ?>">
                            <?= $isNonaktif ? 'Nonaktif' : 'Aktif' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['updated_at'] ?? '-') ?></td>
                    <td class="text-center">
                        <a href="index.php?page=edit_user&id_user=<?= $row['id_user'] ?>"><i class="bi-pencil fs-4 mx-3"></i></a>
                        <?php if (!$isNonaktif): ?>
                        <a href="javascript:void(0);" onclick="confirmDeleteUser(<?= $row['id_user'] ?>)"><i class="bi-trash fs-4"></i></a>
                        <?php else: ?>
                        <span class="badge bg-secondary">Sudah Nonaktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php 
                    endwhile;
                else: 
            ?>
                <tr>
                    <td colspan="10" class="text-center">Tidak ada data user yang ditemukan.</td>
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
