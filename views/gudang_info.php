
<?php
// Mendapatkan ID gudang dari URL
$gudang_id = isset($_GET['id_gudang']) ? intval($_GET['id_gudang']) : 0;



// Query untuk mendapatkan data gudang
$gudangQuery = "SELECT * FROM Gudang WHERE id_gudang = $gudang_id";
$gudangResult = $koneksi->query($gudangQuery);
$gudang = $gudangResult->fetch_assoc();

// Jika gudang tidak ditemukan, tampilkan pesan
if (!$gudang) {
    echo "<p>Gudang tidak ditemukan.</p>";
    exit;
}

// Query untuk mendapatkan data produk di gudang tersebut
$produkQuery = "SELECT p.nama_produk, p.harga_satuan, sg.jumlah_stok
                FROM Produk p
                JOIN StokGudang sg ON p.id_produk = sg.produk_id
                WHERE sg.gudang_id = $gudang_id";
$produkResult = $koneksi->query($produkQuery);
?>

<h2>Produk di Gudang: <?= htmlspecialchars($gudang['nama_gudang']) ?></h2>
<p>Lokasi: <?= htmlspecialchars($gudang['lokasi']) ?></p>

<div class="table-container overflowy">
<?php
// Tangkap id_gudang dari URL
$gudang_id = isset($_GET['id_gudang']) ? (int)$_GET['id_gudang'] : 0;

?>

<?php
// Fungsi untuk memformat angka ke dalam format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>


<table class="table table-bordered table-success table-striped table-hover">
    <thead class="text-center">
        <tr>
            <th>Kode</th>
            <th>Nama Produk</th>
            <th>Kategori</th>
            <th>Tersedia</th>
            <th>Satuan</th>
            <th>Harga</th>
            <th>Harga Total</th>
        </tr>
    </thead>
    <tbody>
    <?php 
        // Query untuk mendapatkan data produk berdasarkan gudang tertentu
        $query = "SELECT produk.id_produk, produk.kode_produk, produk.nama_produk, kategori.nama_kategori, produk.harga_satuan, produk.jumlah_stok, produk.satuan, produk.total_nilai
                  FROM produk
                  LEFT JOIN kategori ON produk.kategori_id = kategori.id_kategori
                  LEFT JOIN StokGudang sg ON produk.id_produk = sg.produk_id
                  WHERE sg.gudang_id = $gudang_id";

        // Menambahkan filter berdasarkan pencarian kode produk jika ada
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $koneksi->real_escape_string($_GET['search']);
            $query .= " AND produk.kode_produk LIKE '%$search%'";
        }

        $result = $koneksi->query($query);
    ?>

    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['kode_produk']) ?></td>
                <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                <td><?= htmlspecialchars($row['nama_kategori']) ? htmlspecialchars($row['nama_kategori']) : 'Tidak ada'; ?></td>
                <td class="text-end"><?= htmlspecialchars($row['jumlah_stok']) ?></td>
                <td><?= htmlspecialchars($row['satuan']) ?></td>
                <td class="text-end"><?= formatRupiah($row['harga_satuan']) ?></td>
                <td class="text-end"><?= formatRupiah($row['total_nilai']) ?></td>
                
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" class="text-center">Tidak ada data produk yang ditemukan untuk gudang ini.</td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<a href="index.php?page=data_gudang"><button class="btn btn-secondary mt-3">Kembali ke Data Gudang</button></a>
