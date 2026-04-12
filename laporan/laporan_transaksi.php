<?php
include ("../koneksi/koneksi.php");

function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$query = "SELECT st.no_invoice, p.kode_produk, p.nama_produk,
                 st.tipe_transaksi,
                 CASE WHEN st.tipe_transaksi = 'masuk' THEN st.jumlah ELSE 0 END AS jumlah_masuk,
                 CASE WHEN st.tipe_transaksi = 'keluar' THEN st.jumlah ELSE 0 END AS jumlah_keluar,
                 COALESCE(st.harga_satuan, p.harga_default, p.harga_satuan, 0) AS harga_satuan_transaksi,
                 (st.jumlah * COALESCE(st.harga_satuan, p.harga_default, p.harga_satuan, 0)) AS total_transaksi,
                 st.tanggal, st.keterangan
          FROM StokTransaksi st
          JOIN Produk p ON st.id_produk = p.id_produk";

$stmt = $koneksi->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$totalMasuk = 0;
$totalKeluar = 0;
$dataTransaksi = [];

while ($transaksi = $result->fetch_assoc()) {
    $dataTransaksi[] = $transaksi;
    $totalMasuk += $transaksi['jumlah_masuk'];
    $totalKeluar += $transaksi['jumlah_keluar'];
}

$periodeLabel = 'Semua tanggal s/d Sekarang';
$printedAtLabel = date('d-m-Y H:i');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/laporan.css">
    <link rel="stylesheet" href="../assets/css/report-global.css">
</head>
<body>

<div class="container my-4 report-shell">
    <div class="report-toolbar no-print">
        <div>
            <h2 class="mb-1">Laporan Transaksi</h2>
            <p class="text-muted mb-0">Riwayat barang masuk dan keluar berdasarkan transaksi stok.</p>
        </div>
        <div class="report-toolbar-actions">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
            <a href="../index.php?page=laporan" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <section class="report-paper">
        <header class="report-header">
            <h1>PT PLN Nusantara Power UP Brantas</h1>
            <h2>Laporan Transaksi</h2>
            <div class="report-meta">
                <div><strong>Periode:</strong> <?= htmlspecialchars($periodeLabel) ?></div>
                <div><strong>Total Aktivitas:</strong> <?= count($dataTransaksi) ?></div>
                <div><strong>Total Masuk/Keluar:</strong> <?= intval($totalMasuk) ?> / <?= intval($totalKeluar) ?></div>
            </div>
        </header>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Invoice</th>
                        <th>Kode Produk</th>
                        <th>Nama Produk</th>
                        <th>Tipe</th>
                        <th>Jumlah Masuk</th>
                        <th>Jumlah Keluar</th>
                        <th>Harga Satuan</th>
                        <th>Total</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $nomor = 1; ?>
                    <?php foreach ($dataTransaksi as $transaksi): ?>
                    <tr>
                        <td class="text-center"><?= $nomor++ ?></td>
                        <td><?= htmlspecialchars($transaksi['no_invoice']) ?></td>
                        <td><?= htmlspecialchars($transaksi['kode_produk']) ?></td>
                        <td><?= htmlspecialchars($transaksi['nama_produk']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($transaksi['tipe_transaksi']) ?></td>
                        <td class="text-end"><?= intval($transaksi['jumlah_masuk']) ?></td>
                        <td class="text-end"><?= intval($transaksi['jumlah_keluar']) ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatRupiah($transaksi['harga_satuan_transaksi'])) ?></td>
                        <td class="text-end"><?= htmlspecialchars(formatRupiah($transaksi['total_transaksi'])) ?></td>
                        <td class="text-center"><?= htmlspecialchars($transaksi['tanggal']) ?></td>
                        <td><?= htmlspecialchars($transaksi['keterangan']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-center">Total:</th>
                        <th class="text-end"><?= intval($totalMasuk) ?></th>
                        <th class="text-end"><?= intval($totalKeluar) ?></th>
                        <th colspan="4"></th>
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
