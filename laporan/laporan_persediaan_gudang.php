<?php
include ("../koneksi/koneksi.php");

function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$query = "SELECT g.nama_gudang, g.lokasi, p.kode_produk, p.nama_produk,
                 p.jumlah_stok AS stok,
                 COALESCE(NULLIF(p.harga_default, 0), p.harga_satuan, 0) AS harga_default,
                 (p.jumlah_stok * COALESCE(NULLIF(p.harga_default, 0), p.harga_satuan, 0)) AS nilai_total
          FROM Produk p
          JOIN StokGudang s ON p.id_produk = s.id_produk
          JOIN Gudang g ON s.id_gudang = g.id_gudang
          ORDER BY g.nama_gudang, p.nama_produk";

$stmt = $koneksi->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$gudangData = [];
while ($produk = $result->fetch_assoc()) {
    $gudangData[$produk['nama_gudang']][] = $produk;
}

$periodeLabel = 'Semua tanggal s/d Sekarang';
$printedAtLabel = date('d-m-Y H:i');
$totalBaris = 0;
foreach ($gudangData as $produks) {
    $totalBaris += count($produks);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Persediaan Per Gudang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/laporan.css">
    <link rel="stylesheet" href="../assets/css/report-global.css">
</head>
<body>

<div class="container my-4 report-shell">
    <div class="report-toolbar no-print">
        <div>
            <h2 class="mb-1">Laporan Persediaan Per Gudang</h2>
            <p class="text-muted mb-0">Rekap stok per gudang beserta nilai persediaan.</p>
        </div>
        <div class="report-toolbar-actions">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
            <a href="../index.php?page=laporan" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <section class="report-paper">
        <header class="report-header">
            <h1>PT PLN Nusantara Power UP Brantas</h1>
            <h2>Laporan Persediaan Per Gudang</h2>
            <div class="report-meta">
                <div><strong>Periode:</strong> <?= htmlspecialchars($periodeLabel) ?></div>
                <div><strong>Total Gudang:</strong> <?= count($gudangData) ?></div>
                <div><strong>Total Baris:</strong> <?= intval($totalBaris) ?></div>
            </div>
        </header>

        <?php foreach ($gudangData as $gudang => $produks): ?>
            <?php
            $nomor = 1;
            $totalStok = 0;
            $totalSatuan = 0;
            $totalNilai = 0;
            ?>
            <h3 class="report-section-title">Gudang: <?= htmlspecialchars($gudang) ?></h3>
            <p class="text-muted mb-2">Lokasi: <?= htmlspecialchars($produks[0]['lokasi']) ?></p>
            <div class="report-table-wrap mb-3">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 48px;">No</th>
                            <th>Kode Produk</th>
                            <th>Nama Produk</th>
                            <th style="width: 90px;">Stok</th>
                            <th style="width: 140px;">Harga Satuan</th>
                            <th style="width: 150px;">Nilai Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produks as $produk): ?>
                        <tr>
                            <td class="text-center"><?= $nomor++ ?></td>
                            <td><?= htmlspecialchars($produk['kode_produk']) ?></td>
                            <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                            <td class="text-end"><?= intval($produk['stok']) ?></td>
                            <td class="text-end"><?= htmlspecialchars(formatRupiah($produk['harga_default'])) ?></td>
                            <td class="text-end"><?= htmlspecialchars(formatRupiah($produk['nilai_total'])) ?></td>
                        </tr>
                        <?php
                        $totalStok += $produk['stok'];
                        $totalSatuan += $produk['harga_default'];
                        $totalNilai += $produk['nilai_total'];
                        ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-center">Total <?= htmlspecialchars($gudang) ?>:</th>
                            <th class="text-end"><?= intval($totalStok) ?></th>
                            <th class="text-end"><?= htmlspecialchars(formatRupiah($totalSatuan)) ?></th>
                            <th class="text-end"><?= htmlspecialchars(formatRupiah($totalNilai)) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endforeach; ?>

        <footer class="report-footer">
            <strong>Tanggal Cetak:</strong> <?= htmlspecialchars($printedAtLabel) ?>
        </footer>
    </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
