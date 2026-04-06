<?php
    $canManageInventory = inventory_user_can_manage();
// Fungsi untuk menghitung total stok dari semua produk yang ada di gudang tertentu
function getTotalStokGudang($koneksi, $id_gudang) {
    $sql = "SELECT SUM(p.jumlah_stok) AS total_stok
            FROM Produk p
            JOIN StokGudang sg ON p.id_produk = sg.id_produk
            WHERE sg.id_gudang = $id_gudang";
    $result = $koneksi->query($sql);
    $row = $result->fetch_assoc();
    return $row['total_stok'] ? $row['total_stok'] : 0;
}

// Mendapatkan kata kunci pencarian jika ada
$search = isset($_GET['search']) ? $koneksi->real_escape_string($_GET['search']) : '';
?>

<h2>Data Gudang</h2>

<!-- Form Pencarian -->
<form method="GET" action="index.php" class="input-group float-end mb-3" style="width: 200px;">
    <input type="hidden" name="page" value="data_gudang">
    <div class="input-group mb-3">
        <input type="text" name="search" class="form-control" placeholder="Cari nama gudang..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="clearfix"></div>

<div class="table-container overflowy">
    <table class="table table-bordered table-success table-striped table-hover">
        <thead class="text-center">
            <tr>
                <th>No</th>
                <th>Nama Gudang</th>
                <th>Lokasi</th>
                <th>Jumlah Stok</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php 
            // Query untuk mengambil data gudang
            $query = "SELECT * FROM Gudang";
            if ($search) {
                $query .= " WHERE nama_gudang LIKE '%$search%'";
            }
            $result = $koneksi->query($query);
            $no = 1;
        ?>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_gudang']) ?></td>
                    <td><?= htmlspecialchars($row['lokasi']) ?></td>
                    <td class="text-end"><?= getTotalStokGudang($koneksi, $row['id_gudang']) ?></td>
                    <td class="text-center">
                        <a href="index.php?page=gudang_info&id_gudang=<?= $row['id_gudang'] ?>"><i class="bi-eye fs-4"></i></a>
                        <?php if ($canManageInventory): ?>
                        <a href="index.php?page=edit_gudang&id_gudang=<?= $row['id_gudang'] ?>"><i class="bi-pencil fs-4 mx-3"></i></a>
                        <a href="javascript:void(0);" onclick="confirmDeleteGudang(<?= $row['id_gudang'] ?>)"><i class="bi-trash fs-4"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center">Tidak ada data gudang yang ditemukan.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($canManageInventory): ?>
<a href="index.php?page=tambah_gudang"><button class="btn btn-primary float-start mt-3">+ Tambah Gudang Baru</button></a>
<?php endif; ?>
<a href="index.php?page=dashboard"><button class="btn btn-secondary float-end mt-3">Tutup</button></a>
<div class="clearfix"></div>
