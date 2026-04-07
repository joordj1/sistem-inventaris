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

$totalDokumen = 0;
foreach ($rows as $row) {
    if (!empty($row['dokumen_file'])) {
        $totalDokumen++;
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Report Mutasi Barang</h2>
            <p class="text-muted mb-0">Filter mutasi antar gudang berdasarkan periode, gudang asal/tujuan, dan produk.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
            <a href="index.php?page=laporan" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <form method="get" action="index.php" class="card card-body mb-4">
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

    <div class="row g-3 mb-4">
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

    <div class="table-container overflowy">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
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
                        <td><?= $index + 1 ?></td>
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
                        <td>
                            <?php if (!empty($row['dokumen_file'])): ?>
                            <a href="<?= htmlspecialchars($row['dokumen_file']) ?>" target="_blank">Lihat</a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="9" class="text-center">Tidak ada data mutasi untuk filter ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
