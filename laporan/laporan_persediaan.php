<?php
include ("../koneksi/koneksi.php");
// Fungsi untuk format Rupiah
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Query untuk mengambil data produk
$query = "SELECT p.kode_produk, p.nama_produk, k.nama_kategori AS kategori, 
                 g.nama_gudang, p.jumlah_stok AS stok, p.harga_satuan,
                 (p.jumlah_stok * p.harga_satuan) AS nilai_total
          FROM Produk p
          JOIN Kategori k ON p.id_kategori = k.id_kategori
          JOIN StokGudang s ON p.id_produk = s.id_produk
          JOIN Gudang g ON s.id_gudang = g.id_gudang";

$stmt = $koneksi->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

// Inisialisasi variabel untuk menghitung total
$totalStok = 0;
$totalHargaSatuan = 0;
$totalNilai = 0;
$dataProduk = [];

// Loop untuk mengambil data
while ($produk = $result->fetch_assoc()) {
    $dataProduk[] = $produk;
    $totalStok += $produk['stok'];
    $totalHargaSatuan += $produk['harga_satuan'];
    $totalNilai += $produk['nilai_total'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Persediaan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="../assets/css/laporan.css">
</head>
<body>

<div class="container my-5 print-area">
    <h2 class="text-center mb-4">Laporan Persediaan</h2>

    <div class="table-container">
        <table class="table  table-bordered table-striped table-hover">
            <thead>
                <tr class="text-center bg-teal text-white">
                    <th>No</th>
                    <th>Kode Produk</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Gudang</th>
                    <th>Stok</th>
                    <th>Harga Satuan</th>
                    <th>Nilai Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $nomor = 1;
                foreach ($dataProduk as $produk) {
                    echo "<tr>";
                    echo "<td class='text-center'>{$nomor}</td>";
                    echo "<td>{$produk['kode_produk']}</td>";
                    echo "<td>{$produk['nama_produk']}</td>";
                    echo "<td>{$produk['kategori']}</td>";
                    echo "<td>{$produk['nama_gudang']}</td>";
                    echo "<td class='text-end'>{$produk['stok']}</td>";
                    echo "<td class='text-end'>" . formatRupiah($produk['harga_satuan']) . "</td>";
                    echo "<td class='text-end'>" . formatRupiah($produk['nilai_total']) . "</td>";
                    echo "</tr>";
                    $nomor++;
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-center">Total:</th>
                    <th class="text-end"><?= $totalStok ?></th>
                    <th class="text-end"><?= formatRupiah($totalHargaSatuan) ?></th>
                    <th class="text-end"><?= formatRupiah($totalNilai) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="container footer-buttons mb-3">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer"></i> Cetak Laporan
    </button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
