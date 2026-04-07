<?php
$filterRefType = trim((string) ($_GET['ref_type'] ?? ''));
$filterUnit = isset($_GET['unit_barang_id']) && $_GET['unit_barang_id'] !== '' ? intval($_GET['unit_barang_id']) : null;
$filterProduk = isset($_GET['produk_id']) && $_GET['produk_id'] !== '' ? intval($_GET['produk_id']) : null;
$filterGudang = isset($_GET['gudang_id']) && $_GET['gudang_id'] !== '' ? intval($_GET['gudang_id']) : null;

$historiRows = fetch_histori_logs($koneksi, [
    'ref_type' => $filterRefType,
    'unit_barang_id' => $filterUnit,
    'produk_id' => $filterProduk,
    'gudang_id' => $filterGudang,
], 300);
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Histori Log</h2>
        <a href="index.php?page=dashboard" class="btn btn-secondary">Kembali</a>
    </div>

    <form method="get" action="index.php" class="card card-body mb-4">
        <input type="hidden" name="page" value="histori_log">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Ref Type</label>
                <select name="ref_type" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach (['mutasi', 'handover', 'barang_masuk', 'barang_keluar', 'tracking', 'unit'] as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= $filterRefType === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Produk ID</label>
                <input type="number" name="produk_id" class="form-control" value="<?= htmlspecialchars((string) $filterProduk) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Unit ID</label>
                <input type="number" name="unit_barang_id" class="form-control" value="<?= htmlspecialchars((string) $filterUnit) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Gudang ID</label>
                <input type="number" name="gudang_id" class="form-control" value="<?= htmlspecialchars((string) $filterGudang) ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
                <a href="index.php?page=histori_log" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-container overflowy">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Waktu</th>
                    <th>Ref</th>
                    <th>Event</th>
                    <th>User Snapshot</th>
                    <th>Target Snapshot</th>
                    <th>Deskripsi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($historiRows)): ?>
                    <?php foreach ($historiRows as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(($row['ref_type'] ?? '-') . ' #' . ($row['ref_id'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars($row['event_type'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['user_name_snapshot'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['target_user_name_snapshot'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['deskripsi'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="7" class="text-center">Belum ada histori log.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
