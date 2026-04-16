<?php
include ("../koneksi/koneksi.php");

function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$query = "SELECT p.kode_produk, p.nama_produk, p.tipe_barang,
                 k.nama_kategori AS kategori,
                 g.nama_gudang,
                 CASE
                     WHEN p.tipe_barang = 'asset' THEN COALESCE((
                         SELECT COUNT(*) FROM unit_barang ub
                         WHERE ub.id_produk = p.id_produk AND ub.id_gudang = s.id_gudang
                     ), s.jumlah_stok)
                     ELSE s.jumlah_stok
                 END AS stok,
                 COALESCE(NULLIF(p.harga_default, 0), p.harga_satuan, 0) AS harga_default,
                 CASE
                     WHEN p.tipe_barang = 'asset' THEN COALESCE((
                         SELECT COUNT(*) FROM unit_barang ub2
                         WHERE ub2.id_produk = p.id_produk AND ub2.id_gudang = s.id_gudang
                     ), s.jumlah_stok) * COALESCE(NULLIF(p.harga_default, 0), p.harga_satuan, 0)
                     ELSE s.jumlah_stok * COALESCE(NULLIF(p.harga_default, 0), p.harga_satuan, 0)
                 END AS nilai_total
          FROM Produk p
          JOIN Kategori k ON p.id_kategori = k.id_kategori
          JOIN StokGudang s ON p.id_produk = s.id_produk
          JOIN Gudang g ON s.id_gudang = g.id_gudang";

$stmt = $koneksi->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$totalStok = 0;
$totalHargaSatuan = 0;
$totalNilai = 0;
$dataProduk = [];

while ($produk = $result->fetch_assoc()) {
    $dataProduk[] = $produk;
    $totalStok += $produk['stok'];
    $totalHargaSatuan += $produk['harga_default'];
    $totalNilai += $produk['nilai_total'];
}

$periodeLabel = 'Semua tanggal s/d Sekarang';
$printedAtLabel = date('d-m-Y H:i');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Persediaan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/laporan.css">
    <link rel="stylesheet" href="../assets/css/report-global.css">
</head>
<body>

<div class="container my-4 report-shell">
    <div class="report-toolbar no-print">
        <div>
            <h2 class="mb-1">Laporan Persediaan</h2>
            <p class="text-muted mb-0">Ringkasan stok seluruh produk per gudang.</p>
        </div>
        <div class="report-toolbar-actions">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
            <a href="../index.php?page=laporan" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <section class="report-paper">
        <header class="report-header">
            <h1>PT PLN Nusantara Power UP Brantas</h1>
            <h2>Laporan Persediaan</h2>
            <div class="report-meta">
                <div><strong>Periode:</strong> <?= htmlspecialchars($periodeLabel) ?></div>
                <div><strong>Total Baris:</strong> <?= count($dataProduk) ?></div>
                <div><strong>Total Stok:</strong> <?= intval($totalStok) ?></div>
            </div>
        </header>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
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
                    <?php $nomor = 1; ?>
                    <?php foreach ($dataProduk as $produk): ?>
                    <tr>
                        <td class="text-center"><?= $nomor++ ?></td>
                        <td><?= htmlspecialchars($produk['kode_produk']) ?></td>
                        <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                        <td><?= htmlspecialchars($produk['kategori']) ?></td>
                        <td><?= htmlspecialchars($produk['nama_gudang']) ?></td>
                        <td class="text-end"><?= intval($produk['stok']) ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatRupiah($produk['harga_default'])) ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatRupiah($produk['nilai_total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-center">Total:</th>
                        <th class="text-end"><?= intval($totalStok) ?></th>
                        <th class="text-end"><?= htmlspecialchars(formatRupiah($totalHargaSatuan)) ?></th>
                        <th class="text-end"><?= htmlspecialchars(formatRupiah($totalNilai)) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <footer class="report-footer">
            <strong>Tanggal Cetak:</strong> <?= htmlspecialchars($printedAtLabel) ?>
        </footer>
    </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
