<h2>Daftar Kategori</h2>
<form action="index.php" method="get" class="input-group float-end mb-3" style="width: 200px;">
    <input type="hidden" name="page" value="kategori_barang">
    <input type="text" class="form-control" name="search" placeholder="Search Kategori" value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
</form>

<div class="clearfix"></div>

<div class="table-container overflowy">
    <table class="table table-bordered table-success table-striped table-hover">
        <thead class="text-center">
            <tr>
                <th>No</th>
                <th>Nama Kategori</th>
                <th>Total Stok</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php 
            // Query untuk mendapatkan data produk beserta nama kategorinya
            $query = "SELECT kategori.id_kategori, kategori.nama_kategori, COALESCE(SUM(produk.jumlah_stok), 0) AS total_stok
                      FROM kategori
                      LEFT JOIN produk ON kategori.id_kategori = produk.kategori_id";

            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $koneksi->real_escape_string($_GET['search']);
                $query .= " WHERE kategori.nama_kategori LIKE '%$search%'";
            }

            $query .= " GROUP BY kategori.id_kategori, kategori.nama_kategori";
            $result = $koneksi->query($query);
            $nomor = 1;
        ?>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?= $nomor++ ?></td>
                    <td><?= $row['nama_kategori'] ?></td>
                    <td class="text-end"><?= $row['total_stok'] ?></td>
                    <td class="text-center">
                        <a href="index.php?page=edit_kategori&id_kategori=<?= $row['id_kategori'] ?>"><i class="bi-pencil fs-4 me-3"></i></a>
                        <a href="javascript:void(0);" onclick="confirmDelete(<?= $row['id_kategori'] ?>)"><i class="bi-trash fs-4"></i></a>
                    </td>
                </tr>
            <?php endwhile;?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="text-center">Tidak ada data produk yang ditemukan.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<a href="index.php?page=tambah_kategori"><button class="btn btn-primary float-start mt-3">+ Tambah Kategori Baru</button></a>
<a href="index.php?page=dashboard"><button class="btn btn-secondary float-end mt-3">Tutup</button></a>
<div class="clearfix"></div>