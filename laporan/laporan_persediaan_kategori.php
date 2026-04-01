<?php
include ("../koneksi/koneksi.php");

// Fungsi untuk format Rupiah
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Query untuk mengambil data produk berdasarkan kategori
$query = "SELECT k.nama_kategori AS kategori, p.kode_produk, p.nama_produk,
                 g.nama_gudang, p.jumlah_stok AS stok, p.harga_satuan,
                 (p.jumlah_stok * p.harga_satuan) AS nilai_total
          FROM Produk p
          JOIN Kategori k ON p.id_kategori = k.id_kategori
          JOIN StokGudang s ON p.id_produk = s.id_produk
          JOIN Gudang g ON s.id_gudang = g.id_gudang
          ORDER BY k.nama_kategori, p.nama_produk";

$stmt = $koneksi->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

// Inisialisasi variabel untuk menyimpan data per kategori
$kategoriData = [];
while ($produk = $result->fetch_assoc()) {
    $kategoriData[$produk['kategori']][] = $produk;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Persediaan Per Kategori</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css">
    
    <link rel="stylesheet" href="../assets/css/laporan.css">
</head>
<body>

<div class="container my-5 print-area">
    <h2 class="text-center mb-4">Laporan Persediaan Per Kategori</h2>

    <?php foreach ($kategoriData as $kategori => $produks): ?>
        <!-- Heading untuk setiap kategori -->
        <h3 class="text-center my-4"><?= htmlspecialchars($kategori) ?></h3>
        <div class="table-container">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr class="text-center bg-teal text-white">
                        <th>No</th>
                        <th>Kode Produk</th>
                        <th>Nama Produk</th>
                        <th>Gudang</th>
                        <th>Stok</th>
                        <th>Harga Satuan</th>
                        <th>Nilai Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $nomor = 1;
                    $totalStok = 0;
                    $totalHargaSatuan = 0;
                    $totalNilai = 0;
                    foreach ($produks as $produk) {
                        echo "<tr>";
                        echo "<td class='text-center'>{$nomor}</td>";
                        echo "<td>{$produk['kode_produk']}</td>";
                        echo "<td>{$produk['nama_produk']}</td>";
                        echo "<td>{$produk['nama_gudang']}</td>";
                        echo "<td class='text-end'>{$produk['stok']}</td>";
                        echo "<td class='text-end'>" . formatRupiah($produk['harga_satuan']) . "</td>";
                        echo "<td class='text-end'>" . formatRupiah($produk['nilai_total']) . "</td>";
                        echo "</tr>";
                        $totalStok += $produk['stok'];
                        $totalHargaSatuan += $produk['harga_satuan'];
                        $totalNilai += $produk['nilai_total'];
                        $nomor++;
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-center">Total <?= htmlspecialchars($kategori) ?>:</th>
                        <th class="text-end"><?= $totalStok ?></th>
                        <th class="text-end"><?= formatRupiah($totalHargaSatuan) ?></th>
                        <th class="text-end"><?= formatRupiah($totalNilai) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endforeach; ?>
</div>

<div class="container footer-buttons mb-3">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer"></i> Cetak Laporan
    </button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
