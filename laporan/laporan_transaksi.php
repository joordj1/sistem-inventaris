<?php
include ("../koneksi/koneksi.php");
// Fungsi untuk format Rupiah
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Query untuk mengambil data transaksi
$query = "SELECT st.no_invoice, p.kode_produk, p.nama_produk, 
                 st.tipe_transaksi, 
                 CASE WHEN st.tipe_transaksi = 'masuk' THEN st.jumlah ELSE 0 END AS jumlah_masuk,
                 CASE WHEN st.tipe_transaksi = 'keluar' THEN st.jumlah ELSE 0 END AS jumlah_keluar,
                 st.tanggal, st.keterangan
          FROM StokTransaksi st
          JOIN Produk p ON st.produk_id = p.id_produk";

$stmt = $koneksi->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

// Inisialisasi variabel untuk menghitung total
$totalMasuk = 0;
$totalKeluar = 0;
$dataTransaksi = [];

// Loop untuk mengambil data
while ($transaksi = $result->fetch_assoc()) {
    $dataTransaksi[] = $transaksi;
    $totalMasuk += $transaksi['jumlah_masuk'];
    $totalKeluar += $transaksi['jumlah_keluar'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/laporan.css">
</head>
<body>

<div class="container my-5 print-area">
    <h2 class="text-center mb-4">Laporan Transaksi</h2>

    <div class="table-container">
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr class="text-center bg-teal text-white">
                    <th>No</th>
                    <th>No Invoice</th>
                    <th>Kode Produk</th>
                    <th>Nama Produk</th>
                    <th>Tipe Transaksi</th>
                    <th>Jumlah Masuk</th>
                    <th>Jumlah Keluar</th>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $nomor = 1;
                foreach ($dataTransaksi as $transaksi) {
                    echo "<tr>";
                    echo "<td class='text-center'>{$nomor}</td>";
                    echo "<td>{$transaksi['no_invoice']}</td>";
                    echo "<td>{$transaksi['kode_produk']}</td>";
                    echo "<td>{$transaksi['nama_produk']}</td>";
                    echo "<td class='text-center'>{$transaksi['tipe_transaksi']}</td>";
                    echo "<td class='text-end'>{$transaksi['jumlah_masuk']}</td>";
                    echo "<td class='text-end'>{$transaksi['jumlah_keluar']}</td>";
                    echo "<td class='text-center'>{$transaksi['tanggal']}</td>";
                    echo "<td>{$transaksi['keterangan']}</td>";
                    echo "</tr>";
                    $nomor++;
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-center">Total:</th>
                    <th class="text-end"><?= $totalMasuk ?></th>
                    <th class="text-end"><?= $totalKeluar ?></th>
                    <th colspan="2"></th>
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
