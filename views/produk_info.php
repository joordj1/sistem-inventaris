<?php
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<?php
if (isset($_GET['id_produk'])) {
    $id = $_GET['id_produk'];
?>

<div class="container">
    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Informasi Detail Produk</h3>
                </div>
                <div class="panel-body">
                    <?php
                    // Query untuk mengambil detail data produk dan gudang terkait
                    $query = "SELECT produk.id_produk, produk.kode_produk, produk.nama_produk, kategori.nama_kategori, 
                                     produk.harga_satuan, produk.jumlah_stok, produk.satuan, produk.total_nilai, 
                                     produk.gambar_produk, gudang.nama_gudang
                              FROM produk 
                              LEFT JOIN kategori ON produk.kategori_id = kategori.id_kategori
                              LEFT JOIN StokGudang ON produk.id_produk = StokGudang.produk_id
                              LEFT JOIN Gudang ON StokGudang.gudang_id = Gudang.id_gudang
                              WHERE produk.id_produk = ?";
                    $stmt = $koneksi->prepare($query);
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $data = $result->fetch_assoc();
                    ?>

                    <table class="table table-bordered table-striped table-hover">
                        <tr>
                            <td width="200">Kode Produk</td>
                            <td><?= $data['kode_produk'] ?></td>
                        </tr>
                        <tr>
                            <td>Nama Produk</td>
                            <td><?= $data['nama_produk'] ?></td>
                        </tr>
                        <tr>
                            <td>Kategori</td>
                            <td><?= $data['nama_kategori'] ? $data['nama_kategori'] : 'Tidak ada'; ?></td>
                        </tr>
                        <tr>
                            <td>Gudang</td>
                            <td>
                                <?= $data['nama_gudang'] ? $data['nama_gudang'] : 'Produk tidak memiliki gudang'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Tersedia</td>
                            <td><?= $data['jumlah_stok'] ?></td>
                        </tr>
                        <tr>
                            <td>Satuan</td>
                            <td><?= $data['satuan'] ?></td>
                        </tr>
                        <tr>
                            <td>Harga</td>
                            <td><?= formatRupiah($data['harga_satuan']) ?></td>
                        </tr>
                        <tr>
                            <td>Gambar</td>
                            <td>
                                <?php if (!empty($data['gambar_produk'])): ?>
                                    <img src="uploads/<?= $data['gambar_produk']; ?>" alt="Gambar Produk" style="width: 300px; height: auto;">
                                <?php else: ?>
                                    <span>Tidak ada gambar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <?php } else {
                        echo "Data tidak ditemukan.";
                    }
                    ?>
                </div>
                <div class="panel-footer">
                    <a href="index.php?page=data_produk" class="btn btn-success btn-sm">Kembali ke Data Produk</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    echo "ID produk tidak tersedia.";
}
?>
