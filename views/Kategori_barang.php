<?php
// Fungsi untuk memformat angka ke dalam format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>

<!-- Kode HTML dan PHP untuk Halaman Barang Keluar -->
<h2>Data Barang Keluar</h2>
<form action="index.php?page=barang_keluar" method="get" class="input-group float-end mb-3" style="width: 200px;">
    <input type="hidden" name="page" value="barang_keluar">
    <input type="text" class="form-control" name="search" placeholder="Search by No Invoice" value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
</form>

<div class="clearfix"></div>
<div class="table-container overflowy">
    <table class="table table-bordered table-danger table-striped table-hover">
        <thead class="text-center">
            <tr>
                <th>No</th>
                <th>No Invoice</th>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th>Jumlah</th>
                <th>Harga Satuan</th>
                <th>Total</th>
                <th>Tanggal</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
        <?php 
            // Query untuk mendapatkan data barang keluar
            $query = "SELECT stoktransaksi.id_transaksi, produk.kode_produk, produk.nama_produk, stoktransaksi.no_invoice, 
                             stoktransaksi.jumlah,
                             COALESCE(stoktransaksi.harga_satuan, produk.harga_default, produk.harga_satuan, 0) AS harga_satuan_transaksi,
                             (stoktransaksi.jumlah * COALESCE(stoktransaksi.harga_satuan, produk.harga_default, produk.harga_satuan, 0)) AS total_transaksi,
                             stoktransaksi.tanggal, stoktransaksi.keterangan
                      FROM stoktransaksi
                      INNER JOIN produk ON stoktransaksi.id_produk = produk.id_produk
                      WHERE stoktransaksi.tipe_transaksi = 'keluar'";

            // Menambahkan filter berdasarkan pencarian No Invoice jika ada
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $koneksi->real_escape_string($_GET['search']);
                $query .= " AND stoktransaksi.no_invoice LIKE '%$search%'";
            }

            $result = $koneksi->query($query);
            $nomor = 1;
        ?>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?= $nomor++ ?></td>
                    <td><?= $row['no_invoice'] ? $row['no_invoice'] : 'Tidak ada'; ?></td>
                    <td><?= $row['kode_produk'] ?></td>
                    <td><?= $row['nama_produk'] ?></td>
                    <td class="text-end"><?= $row['jumlah'] ?></td>
                    <td class="text-end"><?= formatRupiah($row['harga_satuan_transaksi']) ?></td>
                    <td class="text-end"><?= formatRupiah($row['total_transaksi']) ?></td>
                    <td><?= $row['tanggal'] ?></td>
                    <td><?= $row['keterangan'] ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="text-center">Tidak ada data barang keluar yang ditemukan.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Tombol untuk menambahkan barang keluar -->
<a href="index.php?page=tambah_barang_keluar"><button class="btn btn-primary float-start mt-3">+ Tambah Barang Keluar</button></a>
<a href="index.php?page=dashboard"><button class="btn btn-secondary float-end mt-3">Tutup</button></a>
<div class="clearfix"></div>
