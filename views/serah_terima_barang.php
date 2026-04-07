<?php
$canManageInventory = inventory_user_can_manage();
$filterStatus = trim((string) ($_GET['status'] ?? ''));
$filterJenisTujuan = trim((string) ($_GET['jenis_tujuan'] ?? ''));

$rows = fetch_serah_terima_rows($koneksi, [
    'status' => $filterStatus,
    'jenis_tujuan' => $filterJenisTujuan,
], 250);
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Serah Terima Barang</h2>
            <p class="text-muted mb-0">Berita acara formal untuk penyerahan asset dan barang, terpisah dari assign cepat.</p>
        </div>
        <?php if ($canManageInventory): ?>
        <a href="index.php?page=serah_terima&action=form" class="btn btn-primary">+ Tambah Serah Terima</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <form method="get" action="index.php" class="card card-body mb-4">
        <input type="hidden" name="page" value="serah_terima">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach (['aktif', 'dikembalikan', 'dibatalkan'] as $statusOption): ?>
                    <option value="<?= htmlspecialchars($statusOption) ?>" <?= $filterStatus === $statusOption ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($statusOption)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Jenis Tujuan</label>
                <select name="jenis_tujuan" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach (['user', 'lokasi', 'departemen'] as $jenis): ?>
                    <option value="<?= htmlspecialchars($jenis) ?>" <?= $filterJenisTujuan === $jenis ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($jenis)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
                <a href="index.php?page=serah_terima" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="table-container overflowy">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Tanggal</th>
                    <th>Penyerah</th>
                    <th>Penerima</th>
                    <th>Jenis Tujuan</th>
                    <th>Gudang Asal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($row['kode_serah_terima'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['tanggal_serah_terima'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['pihak_penyerah_nama'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['pihak_penerima_nama'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(ucfirst($row['jenis_tujuan'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars($row['nama_gudang_asal'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(ucfirst($row['status'] ?? '-')) ?></td>
                        <td><a href="index.php?page=serah_terima&view=detail&id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="9" class="text-center">Belum ada data serah terima.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
