<?php
$canManageInventory = inventory_user_can_manage();

function getMutasiStatusBadge($status) {
    $status = strtolower(trim((string) $status));
    $map = [
        'draft' => 'bg-secondary',
        'disetujui' => 'bg-primary',
        'selesai' => 'bg-success',
        'dibatalkan' => 'bg-danger',
    ];

    return $map[$status] ?? 'bg-secondary';
}

$filterStatus = trim((string) ($_GET['status'] ?? ''));
$filterGudangAsal = isset($_GET['gudang_asal_id']) && $_GET['gudang_asal_id'] !== '' ? intval($_GET['gudang_asal_id']) : null;
$filterGudangTujuan = isset($_GET['gudang_tujuan_id']) && $_GET['gudang_tujuan_id'] !== '' ? intval($_GET['gudang_tujuan_id']) : null;
$filterTanggalDari = trim((string) ($_GET['tanggal_dari'] ?? ''));
$filterTanggalSampai = trim((string) ($_GET['tanggal_sampai'] ?? ''));

$gudangRows = [];
$gudangResult = $koneksi->query("SELECT id_gudang, nama_gudang FROM gudang ORDER BY nama_gudang ASC");
while ($gudangResult && ($row = $gudangResult->fetch_assoc())) {
    $gudangRows[] = $row;
}

$mutasiRows = fetch_mutasi_barang_rows($koneksi, [
    'status' => $filterStatus,
    'gudang_asal_id' => $filterGudangAsal,
    'gudang_tujuan_id' => $filterGudangTujuan,
    'tanggal_dari' => $filterTanggalDari,
    'tanggal_sampai' => $filterTanggalSampai,
], 250);
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Mutasi Barang Antar Gudang</h2>
            <p class="text-muted mb-0">Mutasi resmi dipisahkan dari barang keluar dan perpindahan lokasi internal.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?page=report_mutasi" class="btn btn-outline-secondary">Report Mutasi</a>
            <?php if ($canManageInventory): ?>
            <a href="index.php?page=mutasi_barang&action=form" class="btn btn-primary">+ Tambah Mutasi</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <form method="get" action="index.php" class="card card-body mb-4">
        <input type="hidden" name="page" value="mutasi_barang">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach (['draft', 'disetujui', 'selesai', 'dibatalkan'] as $statusOption): ?>
                    <option value="<?= htmlspecialchars($statusOption) ?>" <?= $filterStatus === $statusOption ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($statusOption)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label class="form-label">Tanggal Dari</label>
                <input type="date" name="tanggal_dari" class="form-control" value="<?= htmlspecialchars($filterTanggalDari) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Sampai</label>
                <input type="date" name="tanggal_sampai" class="form-control" value="<?= htmlspecialchars($filterTanggalSampai) ?>">
            </div>
            <div class="col-md-9 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
                <a href="index.php?page=mutasi_barang" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-container overflowy">
        <table class="table table-bordered table-striped table-hover">
            <thead class="text-center">
                <tr>
                    <th>No</th>
                    <th>Kode Mutasi</th>
                    <th>Tanggal</th>
                    <th>Gudang Asal</th>
                    <th>Gudang Tujuan</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th>Dibuat Oleh</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($mutasiRows)): ?>
                    <?php foreach ($mutasiRows as $index => $row): ?>
                    <tr>
                        <td class="text-center"><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($row['kode_mutasi'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['tanggal_mutasi'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['nama_gudang_asal'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['nama_gudang_tujuan'] ?? '-') ?></td>
                        <td class="text-center"><?= htmlspecialchars(ucfirst($row['jenis_barang'] ?? '-')) ?></td>
                        <td class="text-center"><span class="badge <?= getMutasiStatusBadge($row['status'] ?? '') ?>"><?= htmlspecialchars(ucfirst($row['status'] ?? '-')) ?></span></td>
                        <td><?= htmlspecialchars($row['created_by_name'] ?? '-') ?></td>
                        <td class="text-center">
                            <a href="index.php?page=mutasi_barang&view=detail&id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center">Belum ada data mutasi barang.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
