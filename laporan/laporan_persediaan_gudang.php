<?php
include ("../koneksi/koneksi.php");

// Fungsi untuk format Rupiah
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Query untuk mengambil data produk berdasarkan gudang
$query = "SELECT g.nama_gudang, g.lokasi, p.kode_produk, p.nama_produk,
                 p.jumlah_stok AS stok, p.harga_satuan,
                 (p.jumlah_stok * p.harga_satuan) AS nilai_total
          FROM Produk p
          JOIN StokGudang s ON p.id_produk = s.id_produk
          JOIN Gudang g ON s.id_gudang = g.id_gudang
          ORDER BY g.nama_gudang, p.nama_produk";

$stmt = $koneksi->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

// Inisialisasi variabel untuk menyimpan data per gudang
$gudangData = [];
while ($produk = $result->fetch_assoc()) {
    $gudangData[$produk['nama_gudang']][] = $produk;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Persediaan Per Gudang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css">
    
    <link rel="stylesheet" href="../assets/css/laporan.css">
</head>
<body>

<div class="container my-5 print-area">
    <h2 class="text-center mb-4">Laporan Persediaan Per Gudang</h2>

    <?php foreach ($gudangData as $gudang => $produks): ?>
        <!-- Heading untuk setiap gudang -->
        <h3 class="text-center my-4"><?= htmlspecialchars($gudang) ?></h3>
        <p class="text-center"><?= htmlspecialchars($produks[0]['lokasi']) ?></p>
        <div class="table-container">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr class="text-center bg-teal text-white">
                        <th>No</th>
                        <th>Kode Produk</th>
                        <th>Nama Produk</th>
                        <th>Stok</th>
                        <th>Harga Satuan</th>
                        <th>Nilai Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $nomor = 1;
                    $totalStok = 0;
                    $totalSatuan = 0;
                    $totalNilai = 0;
                    foreach ($produks as $produk) {
                        echo "<tr>";
                        echo "<td class='text-center'>{$nomor}</td>";
                        echo "<td>{$produk['kode_produk']}</td>";
                        echo "<td>{$produk['nama_produk']}</td>";
                        echo "<td class='text-end'>{$produk['stok']}</td>";
                        echo "<td class='text-end'>" . formatRupiah($produk['harga_satuan']) . "</td>";
                        echo "<td class='text-end'>" . formatRupiah($produk['nilai_total']) . "</td>";
                        echo "</tr>";
                        $totalStok += $produk['stok'];
                        $totalSatuan += $produk['harga_satuan'];
                        $totalNilai += $produk['nilai_total'];
                        $nomor++;
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-center">Total <?= htmlspecialchars($gudang) ?>:</th>
                        <th class="text-end"><?= $totalStok ?></th>
                        <th class="text-end"><?= formatRupiah($totalSatuan) ?></th>
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
