<?php
// Fungsi untuk memformat angka ke dalam format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>

<!-- Kode HTML dan PHP Anda -->
<h2>Data Produk</h2>
<div class="d-flex justify-content-between align-items-center mb-3">
    <?php
    // Query untuk mengambil data kategori dari tabel kategori
    $sql_kategori = "SELECT * FROM kategori";
    $result_kategori = $koneksi->query($sql_kategori);

    // Mendapatkan filter kategori dari form
    $selected_kategori = isset($_POST['kategori']) ? $_POST['kategori'] : '';
    ?>

    <form action="index.php?page=data_produk" method="post">
        <label for="kategori">Pilih Kategori:</label>
        <select name="kategori" id="kategori" class="form-select w-auto mt-2" onchange="this.form.submit()">
            <option value="">Semua</option>
            <?php
            // Looping hasil query untuk membuat option pada select kategori
            if ($result_kategori->num_rows > 0) {
                while ($row_kategori = $result_kategori->fetch_assoc()) {
                    $selected = ($row_kategori['id_kategori'] == $selected_kategori) ? 'selected' : '';
                    echo '<option value="' . $row_kategori['id_kategori'] . '" ' . $selected . '>' . $row_kategori['nama_kategori'] . '</option>';
                }
            } else {
                echo '<option value="">Kategori tidak tersedia</option>';
            }
            ?>
        </select>
    </form>

    <form action="index.php" method="get" class="input-group" style="width: 200px;">
        <input type="hidden" name="page" value="data_produk">
        <input type="text" class="form-control" name="search" placeholder="Search by Kode" value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="table-container overflowy">
    <table class="table table-bordered table-success table-striped table-hover">
        <thead class="text-center">
            <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Gudang</th>
                <th>Tersedia</th>
                <th>Satuan</th>
                <th>Harga</th>
                <th>Harga Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php 
            // Membuat query dasar untuk mendapatkan data produk dengan informasi gudang
            $query = "SELECT produk.id_produk, produk.kode_produk, produk.nama_produk, kategori.nama_kategori, 
                             produk.harga_satuan, produk.jumlah_stok, produk.satuan, produk.total_nilai, 
                             produk.gambar_produk, gudang.nama_gudang
                      FROM produk
                      LEFT JOIN kategori ON produk.kategori_id = kategori.id_kategori
                      LEFT JOIN StokGudang ON produk.id_produk = StokGudang.produk_id
                      LEFT JOIN gudang ON StokGudang.gudang_id = gudang.id_gudang";

            // Menambahkan filter berdasarkan kategori jika dipilih
            if ($selected_kategori) {
                $query .= " WHERE produk.kategori_id = '$selected_kategori'";
            }

            // Menambahkan filter berdasarkan pencarian kode produk jika ada
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $koneksi->real_escape_string($_GET['search']);
                $query .= $selected_kategori ? " AND" : " WHERE";
                $query .= " produk.kode_produk LIKE '%$search%'";
            }

            $result = $koneksi->query($query);
            $nomor = 1;
        ?>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?= $nomor++?></td>
                    <td><?= $row['kode_produk'] ?></td>
                    <td><?= $row['nama_produk'] ?></td>
                    <td><?= $row['nama_kategori'] ? $row['nama_kategori'] : 'Tidak ada'; ?></td>
                    <td><?= $row['nama_gudang'] ? $row['nama_gudang'] : 'Tidak Memiliki Gudang'; ?></td> <!-- Tampilkan nama gudang -->
                    <td class="text-end"><?= $row['jumlah_stok'] ?></td>
                    <td><?= $row['satuan'] ?></td>
                    <td class="text-end"><?= formatRupiah($row['harga_satuan']) ?></td>
                    <td class="text-end"><?= formatRupiah($row['total_nilai']) ?></td>
                    <td class="text-center">
                        <a href="index.php?page=produk_info&id_produk=<?= $row['id_produk'] ?>"><i class="bi-eye fs-4"></i></a>
                        <a href="index.php?page=edit_produk&id_produk=<?= $row['id_produk'] ?>"><i class="bi-pencil fs-4 mx-3"></i></a>
                        <a href="javascript:void(0);" onclick="confirmDeleteProduk(<?= $row['id_produk'] ?>)"><i class="bi-trash fs-4"></i></a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="10" class="text-center">Tidak ada data produk yang ditemukan.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<a href="index.php?page=tambah_produk"><button class="btn btn-primary float-start mt-3">+ Tambah Produk Baru</button></a>
<a href="index.php?page=dashboard"><button class="btn btn-secondary float-end mt-3">Tutup</button></a>
<div class="clearfix"></div>
