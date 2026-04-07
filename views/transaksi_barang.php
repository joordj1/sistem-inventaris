<h2>Data Transaksi Barang</h2>
<div class="d-flex justify-content-between align-items-center mb-3">
    <?php
    // Mendapatkan filter tipe transaksi dari form
    $selected_tipe = isset($_POST['tipe_transaksi']) ? $_POST['tipe_transaksi'] : '';
    ?>

    <form action="index.php?page=transaksi_barang" method="post">
        <label for="tipe_transaksi">Pilih Tipe Transaksi:</label>
        <select name="tipe_transaksi" id="tipe_transaksi" class="form-select w-auto mt-2" onchange="this.form.submit()">
            <option value="">Semua Transaksi</option>
            <option value="masuk" <?= $selected_tipe == 'masuk' ? 'selected' : '' ?>>Masuk</option>
            <option value="keluar" <?= $selected_tipe == 'keluar' ? 'selected' : '' ?>>Keluar</option>
        </select>
    </form>

    <form action="index.php?page=transaksi_barang" method="get" class="input-group" style="width: 200px;">
        <input type="hidden" name="page" value="transaksi_barang">
        <input type="text" class="form-control" name="search" placeholder="Search by No Invoice" value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="alert alert-info">
    Tabel ini hanya untuk transaksi barang masuk dan keluar. Perpindahan antar gudang dicatat terpisah pada
    <a href="index.php?page=mutasi_barang" class="alert-link">modul mutasi barang</a>
    agar tidak tercampur dengan barang keluar.
</div>

<div class="table-container overflowy">
    <table class="table table-bordered table-success table-striped table-hover">
        <thead class="text-center">
            <tr>
                <th>No</th>
                <th>No Invoice</th>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th>Tipe Transaksi</th>
                <th>Jumlah Masuk</th>
                <th>Jumlah Keluar</th>
                <th>Harga Satuan</th>
                <th>Total</th>
                <th>Tanggal</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
        <?php 
            // Membuat query dasar untuk mendapatkan data transaksi barang
            $query = "SELECT stoktransaksi.id_transaksi, produk.kode_produk, produk.nama_produk, 
                             stoktransaksi.no_invoice, stoktransaksi.tipe_transaksi, 
                             CASE WHEN stoktransaksi.tipe_transaksi = 'masuk' THEN stoktransaksi.jumlah ELSE 0 END AS jumlah_masuk, 
                             CASE WHEN stoktransaksi.tipe_transaksi = 'keluar' THEN stoktransaksi.jumlah ELSE 0 END AS jumlah_keluar,
                             COALESCE(stoktransaksi.harga_satuan, produk.harga_default, produk.harga_satuan, 0) AS harga_satuan_transaksi,
                             (stoktransaksi.jumlah * COALESCE(stoktransaksi.harga_satuan, produk.harga_default, produk.harga_satuan, 0)) AS total_transaksi,
                             stoktransaksi.tanggal, stoktransaksi.keterangan
                      FROM stoktransaksi
                      INNER JOIN produk ON stoktransaksi.id_produk = produk.id_produk";

            // Menambahkan filter berdasarkan tipe transaksi jika dipilih
            if ($selected_tipe) {
                $query .= " WHERE stoktransaksi.tipe_transaksi = '$selected_tipe'";
            }

            // Menambahkan filter berdasarkan pencarian no invoice jika ada
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $koneksi->real_escape_string($_GET['search']);
                $query .= $selected_tipe ? " AND" : " WHERE";
                $query .= " stoktransaksi.no_invoice LIKE '%$search%'";
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
                    <td class="text-center"><?= ucfirst($row['tipe_transaksi']) ?></td>
                    <td class="text-end"><?= $row['jumlah_masuk'] ?></td>
                    <td class="text-end"><?= $row['jumlah_keluar'] ?></td>
                    <td class="text-end"><?= 'Rp ' . number_format((float) $row['harga_satuan_transaksi'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= 'Rp ' . number_format((float) $row['total_transaksi'], 0, ',', '.') ?></td>
                    <td><?= $row['tanggal'] ?></td>
                    <td><?= $row['keterangan'] ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="11" class="text-center">Tidak ada data transaksi yang ditemukan.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Tombol untuk kembali ke dashboard -->
<a href="index.php?page=dashboard"><button class="btn btn-secondary float-end mt-3">Tutup</button></a>
<div class="clearfix"></div>
