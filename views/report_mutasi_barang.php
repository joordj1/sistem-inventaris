<?php
$filterTanggalDari = trim((string) ($_GET['tanggal_dari'] ?? ''));
$filterTanggalSampai = trim((string) ($_GET['tanggal_sampai'] ?? ''));
$filterGudangAsal = isset($_GET['gudang_asal_id']) && $_GET['gudang_asal_id'] !== '' ? intval($_GET['gudang_asal_id']) : null;
$filterGudangTujuan = isset($_GET['gudang_tujuan_id']) && $_GET['gudang_tujuan_id'] !== '' ? intval($_GET['gudang_tujuan_id']) : null;
$filterProduk = isset($_GET['produk_id']) && $_GET['produk_id'] !== '' ? intval($_GET['produk_id']) : null;

$gudangRows = [];
$gudangResult = $koneksi->query("SELECT id_gudang, nama_gudang FROM gudang ORDER BY nama_gudang ASC");
while ($gudangResult && ($row = $gudangResult->fetch_assoc())) {
    $gudangRows[] = $row;
}

$produkRows = [];
$produkResult = $koneksi->query("SELECT id_produk, kode_produk, nama_produk FROM produk ORDER BY nama_produk ASC");
while ($produkResult && ($row = $produkResult->fetch_assoc())) {
    $produkRows[] = $row;
}

$rows = fetch_mutasi_barang_rows($koneksi, [
    'tanggal_dari' => $filterTanggalDari,
    'tanggal_sampai' => $filterTanggalSampai,
    'gudang_asal_id' => $filterGudangAsal,
    'gudang_tujuan_id' => $filterGudangTujuan,
    'produk_id' => $filterProduk,
], 500);

// Debug sementara: aktifkan dengan ?debug_print=1 untuk cek dataset mode print.
$debugPrint = isset($_GET['debug_print']) && $_GET['debug_print'] === '1';

$totalDokumen = 0;
foreach ($rows as $row) {
    if (!empty($row['dokumen_file'])) {
        $totalDokumen++;
    }
}

$periodLabel = ($filterTanggalDari ?: 'Semua tanggal') . ' s/d ' . ($filterTanggalSampai ?: 'Sekarang');
$printedAtLabel = date('d-m-Y H:i');
?>

<link rel="stylesheet" href="assets/css/report-global.css">

<div class="container report-shell">
    <div class="report-toolbar no-print">
        <div>
            <h2 class="mb-1">Report Mutasi Barang</h2>
            <p class="text-muted mb-0">Filter mutasi antar gudang berdasarkan periode, gudang asal/tujuan, dan produk.</p>
        </div>
        <div class="report-toolbar-actions">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
            <a href="index.php?page=laporan" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <form method="get" action="index.php" class="card card-body mb-4 report-filter-panel no-print">
        <input type="hidden" name="page" value="report_mutasi">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Tanggal Dari</label>
                <input type="date" name="tanggal_dari" class="form-control" value="<?= htmlspecialchars($filterTanggalDari) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal Sampai</label>
                <input type="date" name="tanggal_sampai" class="form-control" value="<?= htmlspecialchars($filterTanggalSampai) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Gudang Asal</label>
                <select name="gudang_asal_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($gudangRows as $gudang): ?>
                    <option value="<?= intval($gudang['id_gudang']) ?>" <?= (string) $filterGudangAsal === (string) $gudang['id_gudang'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($gudang['nama_gudang']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Gudang Tujuan</label>
                <select name="gudang_tujuan_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($gudangRows as $gudang): ?>
                    <option value="<?= intval($gudang['id_gudang']) ?>" <?= (string) $filterGudangTujuan === (string) $gudang['id_gudang'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($gudang['nama_gudang']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Produk</label>
                <select name="produk_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($produkRows as $produk): ?>
                    <option value="<?= intval($produk['id_produk']) ?>" <?= (string) $filterProduk === (string) $produk['id_produk'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(($produk['kode_produk'] ?? '-') . ' - ' . ($produk['nama_produk'] ?? '-')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="index.php?page=report_mutasi" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4 report-summary-grid no-print">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Total Mutasi</div>
                    <div class="display-6"><?= count($rows) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Dokumen Tersimpan</div>
                    <div class="display-6"><?= intval($totalDokumen) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted">Periode</div>
                    <div class="fw-semibold"><?= htmlspecialchars(($filterTanggalDari ?: 'awal') . ' s/d ' . ($filterTanggalSampai ?: 'sekarang')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($debugPrint): ?>
    <div class="alert alert-warning">
        <strong>Debug Print Dataset</strong>
        <pre class="mb-0" style="white-space: pre-wrap;"><?php var_dump([
            'row_count' => count($rows),
            'filters' => [
                'tanggal_dari' => $filterTanggalDari,
                'tanggal_sampai' => $filterTanggalSampai,
                'gudang_asal_id' => $filterGudangAsal,
                'gudang_tujuan_id' => $filterGudangTujuan,
                'produk_id' => $filterProduk,
            ],
            'sample_rows' => array_slice($rows, 0, 3),
        ]); ?></pre>
    </div>
    <?php endif; ?>

    <section class="report-paper">
        <header class="report-header">
            <h1>PT PLN Nusantara Power UP Brantas</h1>
            <h2>Laporan Mutasi Barang</h2>
            <div class="report-meta">
                <div><strong>Periode:</strong> <?= htmlspecialchars($periodLabel) ?></div>
                <div><strong>Total Mutasi:</strong> <?= count($rows) ?> transaksi</div>
                <div><strong>Dokumen:</strong> <?= intval($totalDokumen) ?> lampiran</div>
            </div>
        </header>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Mutasi</th>
                        <th>Tanggal</th>
                        <th>Gudang Asal</th>
                        <th>Gudang Tujuan</th>
                        <th>Jenis</th>
                        <th>Status</th>
                        <th>Pembuat</th>
                        <th>Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td class="text-center"><?= $index + 1 ?></td>
                            <td>
                                <a href="index.php?page=mutasi_barang&view=detail&id=<?= intval($row['id']) ?>">
                                    <?= htmlspecialchars($row['kode_mutasi'] ?? '-') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($row['tanggal_mutasi'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['nama_gudang_asal'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['nama_gudang_tujuan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(ucfirst($row['jenis_barang'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars(ucfirst($row['status'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($row['created_by_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php if (!empty($row['dokumen_file'])): ?>
                                Ada
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="9" class="text-center">Tidak ada data mutasi untuk periode ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <footer class="report-footer">
            <div><strong>Periode Laporan:</strong> <?= htmlspecialchars($periodLabel) ?></div>
            <div><strong>Tanggal Cetak:</strong> <?= htmlspecialchars($printedAtLabel) ?></div>
        </footer>
    </section>
</div>
