<?php
$canManageInventory = inventory_user_can_manage();
if (!$canManageInventory) {
    echo '<div class="alert alert-warning">Role viewer hanya dapat melihat data serah terima.</div>';
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

$userRows = get_active_user_rows($koneksi);
$currentUserName = get_current_user_name($koneksi) ?? '';

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
    $sql = "SELECT ub.id_unit_barang, ub.serial_number, ub.kondisi, p.id_produk, p.kode_produk, p.nama_produk
            FROM unit_barang ub
            INNER JOIN produk p ON ub.id_produk = p.id_produk
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
        <h2 class="mb-0">Form Serah Terima Barang</h2>
        <a href="index.php?page=serah_terima" class="btn btn-secondary">Kembali</a>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <form method="get" action="index.php" class="card card-body mb-4">
        <input type="hidden" name="page" value="serah_terima">
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

    <form action="action/simpan_serah_terima.php" method="post" enctype="multipart/form-data">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Serah Terima</label>
                        <input type="datetime-local" name="tanggal_serah_terima" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i')) ?>" required>
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
                        <label class="form-label">Jenis Tujuan</label>
                        <select name="jenis_tujuan" class="form-select" required>
                            <option value="user">User</option>
                            <option value="lokasi">Lokasi</option>
                            <option value="departemen">Departemen</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Penyerah (User)</label>
                        <select name="pihak_penyerah_user_id" class="form-select">
                            <option value="">Pilih User</option>
                            <?php foreach ($userRows as $user): ?>
                            <option value="<?= intval($user['id_user']) ?>" <?= current_user_id() === intval($user['id_user']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nama Penyerah Snapshot</label>
                        <input type="text" name="pihak_penyerah_nama" class="form-control" value="<?= htmlspecialchars($currentUserName) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Penerima (User)</label>
                        <select name="pihak_penerima_user_id" class="form-select">
                            <option value="">Pilih User jika tujuan user</option>
                            <?php foreach ($userRows as $user): ?>
                            <option value="<?= intval($user['id_user']) ?>"><?= htmlspecialchars($user['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nama Penerima Snapshot</label>
                        <input type="text" name="pihak_penerima_nama" class="form-control" placeholder="Nama penerima / departemen" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Lokasi / Departemen Tujuan</label>
                        <input type="text" name="lokasi_tujuan" class="form-control" placeholder="Opsional, terutama untuk tujuan lokasi / departemen">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Dokumen Serah Terima</label>
                        <input type="file" name="dokumen_serah_terima" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan</label>
                        <textarea name="catatan" class="form-control" rows="3" placeholder="Nomor BA, tujuan pemakaian, instruksi pengembalian, dst."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Barang Consumable</div>
            <div class="card-body table-container overflowy">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Stok Gudang Asal</th>
                            <th>Qty Serah</th>
                            <th>Kondisi Serah</th>
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
                                <td><input type="number" min="0" max="<?= intval($row['jumlah_stok']) ?>" name="consumable_qty[]" class="form-control" value="0"></td>
                                <td>
                                    <select name="consumable_kondisi_serah[]" class="form-select">
                                        <?php foreach (['baik', 'rusak', 'diperbaiki', 'usang', 'lainnya'] as $kondisi): ?>
                                        <option value="<?= htmlspecialchars($kondisi) ?>"><?= htmlspecialchars(ucfirst($kondisi)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="consumable_catatan_detail[]" class="form-control" placeholder="Catatan detail"></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="5" class="text-center">Tidak ada stok consumable yang bisa diserahterimakan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Unit Asset</div>
            <div class="card-body table-container overflowy">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Pilih</th>
                            <th>Unit</th>
                            <th>Produk</th>
                            <th>Kondisi Saat Ini</th>
                            <th>Kondisi Serah</th>
                            <th>Catatan Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assetRows)): ?>
                            <?php foreach ($assetRows as $row): ?>
                            <tr>
                                <td class="text-center"><input type="checkbox" name="asset_unit_barang_id[]" value="<?= intval($row['id_unit_barang']) ?>"></td>
                                <td><?= htmlspecialchars($row['serial_number'] ?? ('Unit #' . $row['id_unit_barang'])) ?></td>
                                <td><?= htmlspecialchars(($row['kode_produk'] ?? '-') . ' - ' . ($row['nama_produk'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($row['kondisi'] ?? '-') ?></td>
                                <td>
                                    <select name="asset_kondisi_serah[<?= intval($row['id_unit_barang']) ?>]" class="form-select">
                                        <?php foreach (['baik', 'rusak', 'diperbaiki', 'usang', 'lainnya'] as $kondisi): ?>
                                        <option value="<?= htmlspecialchars($kondisi) ?>" <?= ($row['kondisi'] ?? '') === $kondisi ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($kondisi)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="asset_catatan_detail[<?= intval($row['id_unit_barang']) ?>]" class="form-control" placeholder="Catatan detail unit"></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="6" class="text-center">Tidak ada unit asset tersedia untuk serah terima.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="index.php?page=serah_terima" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Serah Terima</button>
        </div>
    </form>
</div>
