<?php
$filterTanggalDari = trim((string) ($_GET['tanggal_dari'] ?? ''));
$filterTanggalSampai = trim((string) ($_GET['tanggal_sampai'] ?? ''));
$filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : null;
$filterBarangId = isset($_GET['barang_id']) && $_GET['barang_id'] !== '' ? intval($_GET['barang_id']) : null;
$filterStatusBarang = trim((string) ($_GET['status_barang'] ?? ''));

$userRows = get_active_user_rows($koneksi);
$barangRows = [];
$barangResult = $koneksi->query("SELECT id_produk, kode_produk, nama_produk FROM produk ORDER BY nama_produk ASC");
while ($barangResult && ($row = $barangResult->fetch_assoc())) {
    $barangRows[] = $row;
}

$statusRows = ['tersedia', 'dipinjam', 'sedang digunakan', 'dalam perbaikan', 'rusak'];
$rows = fetch_inventory_usage_report_rows($koneksi, [
    'tanggal_dari' => $filterTanggalDari,
    'tanggal_sampai' => $filterTanggalSampai,
    'user_id' => $filterUserId,
    'barang_id' => $filterBarangId,
    'status_barang' => $filterStatusBarang,
], 500);

$periodeLabel = ($filterTanggalDari ?: 'Semua tanggal') . ' s/d ' . ($filterTanggalSampai ?: 'Sekarang');
$userFilterLabel = $filterUserId ? (get_user_name_by_id($koneksi, $filterUserId) ?? 'User tidak ditemukan') : 'Semua user';
$printedAtLabel = date('d-m-Y H:i');
?>

<link rel="stylesheet" href="assets/css/report-global.css">

<div class="container report-shell">
    <div class="report-toolbar no-print">
        <div>
            <h2 class="mb-1">Report Barang Berdasarkan User</h2>
            <p class="text-muted mb-0">Menampilkan histori penggunaan barang dari tracking dan transaksi yang sudah ada.</p>
        </div>
        <div class="report-toolbar-actions">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button>
            <a href="index.php?page=laporan" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <form method="get" action="index.php" class="card card-body mb-4 report-filter-panel no-print">
        <input type="hidden" name="page" value="report_persediaan_user">
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
                <label class="form-label">User</label>
                <select name="user_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($userRows as $user): ?>
                    <option value="<?= intval($user['id_user']) ?>" <?= (string) $filterUserId === (string) $user['id_user'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Barang</label>
                <select name="barang_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($barangRows as $barang): ?>
                    <option value="<?= intval($barang['id_produk']) ?>" <?= (string) $filterBarangId === (string) $barang['id_produk'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(($barang['kode_produk'] ?? '-') . ' - ' . ($barang['nama_produk'] ?? '-')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status Barang</label>
                <select name="status_barang" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($statusRows as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= $filterStatusBarang === $status ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </div>
        <div class="mt-3">
            <a href="index.php?page=report_persediaan_user" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
        </div>
    </form>

    <div class="row g-3 mb-4 report-summary-grid no-print">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body"><div class="text-muted">Total Aktivitas</div><div class="display-6"><?= count($rows) ?></div></div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body"><div class="text-muted">Filter User</div><div class="fw-semibold"><?= htmlspecialchars($userFilterLabel) ?></div></div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body"><div class="text-muted">Periode</div><div class="fw-semibold"><?= htmlspecialchars(($filterTanggalDari ?: 'awal') . ' s/d ' . ($filterTanggalSampai ?: 'sekarang')) ?></div></div></div>
        </div>
    </div>

    <section class="report-paper">
        <header class="report-header">
            <h1>PT PLN Nusantara Power UP Brantas</h1>
            <h2>Laporan Barang Berdasarkan User</h2>
            <div class="report-meta">
                <div><strong>Periode:</strong> <?= htmlspecialchars($periodeLabel) ?></div>
                <div><strong>Total Aktivitas:</strong> <?= count($rows) ?></div>
                <div><strong>User:</strong> <?= htmlspecialchars($userFilterLabel) ?></div>
            </div>
        </header>

        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 48px;">No</th>
                        <th>Nama Barang</th>
                        <th>Nama User</th>
                        <th style="width: 140px;">Status</th>
                        <th>Gudang / Lokasi</th>
                        <th style="width: 150px;">Tanggal Aktivitas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td class="text-center"><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars(($row['kode_produk'] ?? '-') . ' - ' . ($row['nama_produk'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['status_barang'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['gudang_lokasi'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['tanggal_aktivitas'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="6" class="text-center">Tidak ada data report untuk filter ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <footer class="report-footer">
            <strong>Tanggal Cetak:</strong> <?= htmlspecialchars($printedAtLabel) ?>
        </footer>
    </section>
</div>