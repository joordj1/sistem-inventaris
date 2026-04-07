<?php
$canManageInventory = inventory_user_can_manage();
if (!$canManageInventory) {
    echo '<div class="alert alert-warning">Role viewer hanya dapat melihat data mutasi.</div>';
    return;
}

$selectedGudangAsal = isset($_GET['gudang_asal_id']) && $_GET['gudang_asal_id'] !== '' ? intval($_GET['gudang_asal_id']) : 0;
$gudangRows = [];
$gudangResult = $koneksi->query("SELECT id_gudang, nama_gudang FROM gudang ORDER BY nama_gudang ASC");
while ($gudangResult && ($row = $gudangResult->fetch_assoc())) {
    $gudangRows[] = $row;
    if ($selectedGudangAsal === 0) {
        $selectedGudangAsal = intval($row['id_gudang']);
    }
}

$consumableRows = [];
if ($selectedGudangAsal > 0) {
    $sql = "SELECT p.id_produk, p.kode_produk, p.nama_produk, p.satuan, sg.jumlah_stok
            FROM stokgudang sg
            INNER JOIN produk p ON sg.id_produk = p.id_produk
            WHERE sg.id_gudang = " . intval($selectedGudangAsal) . "
              AND COALESCE(p.tipe_barang, 'consumable') = 'consumable'
              AND sg.jumlah_stok > 0
            ORDER BY p.nama_produk ASC";
    $result = $koneksi->query($sql);
    while ($result && ($row = $result->fetch_assoc())) {
        $consumableRows[] = $row;
    }
}

$assetRows = [];
if ($selectedGudangAsal > 0 && schema_table_exists_now($koneksi, 'unit_barang')) {
    $sql = "SELECT ub.id_unit_barang, ub.kode_unit, ub.status, ub.kondisi, ub.id_gudang, g.nama_gudang,
                   p.id_produk, p.kode_produk, p.nama_produk
            FROM unit_barang ub
            INNER JOIN produk p ON ub.id_produk = p.id_produk
            LEFT JOIN gudang g ON ub.id_gudang = g.id_gudang
            WHERE ub.id_gudang = " . intval($selectedGudangAsal) . "
              AND COALESCE(p.tipe_barang, 'consumable') = 'asset'
              AND COALESCE(ub.id_user, 0) = 0
              AND LOWER(TRIM(COALESCE(ub.status, 'tersedia'))) = 'tersedia'
            ORDER BY p.nama_produk ASC, ub.id_unit_barang DESC";
    $result = $koneksi->query($sql);
    while ($result && ($row = $result->fetch_assoc())) {
        $assetRows[] = $row;
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Form Mutasi Barang</h2>
        <a href="index.php?page=mutasi_barang" class="btn btn-secondary">Kembali</a>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <form method="get" action="index.php" class="card card-body mb-4">
        <input type="hidden" name="page" value="mutasi_barang">
        <input type="hidden" name="action" value="form">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Muat Data dari Gudang Asal</label>
                <select name="gudang_asal_id" class="form-select">
                    <?php foreach ($gudangRows as $gudang): ?>
                    <option value="<?= intval($gudang['id_gudang']) ?>" <?= (string) $selectedGudangAsal === (string) $gudang['id_gudang'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($gudang['nama_gudang']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-outline-primary">Refresh Barang</button>
            </div>
        </div>
    </form>

    <form action="action/simpan_mutasi_barang.php" method="post" enctype="multipart/form-data">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Mutasi</label>
                        <input type="datetime-local" name="tanggal_mutasi" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gudang Asal</label>
                        <select name="gudang_asal_id" class="form-select" required>
                            <?php foreach ($gudangRows as $gudang): ?>
                            <option value="<?= intval($gudang['id_gudang']) ?>" <?= (string) $selectedGudangAsal === (string) $gudang['id_gudang'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gudang['nama_gudang']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gudang Tujuan</label>
                        <select name="gudang_tujuan_id" class="form-select" required>
                            <option value="">Pilih Gudang Tujuan</option>
                            <?php foreach ($gudangRows as $gudang): ?>
                            <option value="<?= intval($gudang['id_gudang']) ?>" <?= (string) $selectedGudangAsal === (string) $gudang['id_gudang'] ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($gudang['nama_gudang']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Catatan Mutasi</label>
                        <textarea name="catatan" class="form-control" rows="3" placeholder="Catatan mutasi, alasan perpindahan, nomor dokumen internal, dst."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Dokumen Mutasi</label>
                        <input type="file" name="dokumen_mutasi" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Barang Consumable dari Gudang Asal</div>
            <div class="card-body table-container overflowy">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Stok Gudang Asal</th>
                            <th>Qty Mutasi</th>
                            <th>Catatan Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($consumableRows)): ?>
                            <?php foreach ($consumableRows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['kode_produk']) ?></strong><br>
                                    <span class="text-muted"><?= htmlspecialchars($row['nama_produk']) ?></span>
                                    <input type="hidden" name="consumable_produk_id[]" value="<?= intval($row['id_produk']) ?>">
                                </td>
                                <td><?= intval($row['jumlah_stok']) ?> <?= htmlspecialchars($row['satuan']) ?></td>
                                <td style="width: 180px;">
                                    <input type="number" min="0" max="<?= intval($row['jumlah_stok']) ?>" name="consumable_qty[]" class="form-control" value="0">
                                </td>
                                <td>
                                    <input type="text" name="consumable_catatan_detail[]" class="form-control" placeholder="Catatan detail">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">Tidak ada stok consumable di gudang asal.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Unit Asset dari Gudang Asal</div>
            <div class="card-body table-container overflowy">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Pilih</th>
                            <th>Unit</th>
                            <th>Produk</th>
                            <th>Gudang</th>
                            <th>Status</th>
                            <th>Kondisi Sebelum</th>
                            <th>Kondisi Sesudah</th>
                            <th>Catatan Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assetRows)): ?>
                            <?php foreach ($assetRows as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="asset_unit_barang_id[]" value="<?= intval($row['id_unit_barang']) ?>">
                                </td>
                                <td>
                                    <?php $unitLabel = trim((string) ($row['kode_unit'] ?? '')); ?>
                                    <?= htmlspecialchars($unitLabel !== '' ? $unitLabel : ('Unit #' . $row['id_unit_barang'])) ?><br>
                                    <span class="text-muted">ID: <?= intval($row['id_unit_barang']) ?></span>
                                </td>
                                <td><?= htmlspecialchars(($row['kode_produk'] ?? '-') . ' - ' . ($row['nama_produk'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($row['nama_gudang'] ?? ('Gudang #' . intval($row['id_gudang'] ?? 0))) ?></td>
                                <td><?= htmlspecialchars(get_asset_unit_status_label($row['status'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($row['kondisi'] ?? '-') ?></td>
                                <td>
                                    <select name="asset_kondisi_sesudah[<?= intval($row['id_unit_barang']) ?>]" class="form-select">
                                        <?php foreach (['baik', 'rusak', 'diperbaiki', 'usang', 'lainnya'] as $kondisi): ?>
                                        <option value="<?= htmlspecialchars($kondisi) ?>" <?= ($row['kondisi'] ?? '') === $kondisi ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(ucfirst($kondisi)) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="asset_catatan_detail[<?= intval($row['id_unit_barang']) ?>]" class="form-control" placeholder="Catatan detail unit">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada unit asset tersedia di gudang asal.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="index.php?page=mutasi_barang" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Mutasi Resmi</button>
        </div>
    </form>
</div>
