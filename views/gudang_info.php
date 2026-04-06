<?php
function formatGudangRupiah($angka) {
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

$canManageInventory = inventory_user_can_manage();
$id_gudang = isset($_GET['id_gudang']) ? intval($_GET['id_gudang']) : 0;

if ($id_gudang < 1) {
    echo '<div class="alert alert-warning">ID gudang tidak valid.</div>';
    return;
}

$gudangStmt = $koneksi->prepare("SELECT * FROM gudang WHERE id_gudang = ?");
if (!$gudangStmt) {
    echo '<div class="alert alert-danger">Gagal memuat detail gudang.</div>';
    return;
}
$gudangStmt->bind_param('i', $id_gudang);
$gudangStmt->execute();
$gudang = $gudangStmt->get_result()->fetch_assoc();

if (!$gudang) {
    echo '<div class="alert alert-warning">Gudang tidak ditemukan.</div>';
    return;
}

$hasUnitTable = schema_table_exists($koneksi, 'unit_barang');
$unitExistsClause = $hasUnitTable
    ? " OR EXISTS (SELECT 1 FROM unit_barang ub4 WHERE ub4.id_produk = p.id_produk AND ub4.id_gudang = ?)"
    : '';
$unitCountSelect = $hasUnitTable
    ? "(SELECT COUNT(*) FROM unit_barang ub2 WHERE ub2.id_produk = p.id_produk AND ub2.id_gudang = ?) AS total_unit_gudang,
       (SELECT SUM(CASE WHEN LOWER(TRIM(COALESCE(ub3.status, ''))) = 'tersedia' THEN 1 ELSE 0 END)
        FROM unit_barang ub3 WHERE ub3.id_produk = p.id_produk AND ub3.id_gudang = ?) AS unit_tersedia_gudang"
    : "0 AS total_unit_gudang, 0 AS unit_tersedia_gudang";

$produkQuery = "SELECT p.id_produk, p.kode_produk, p.nama_produk, p.deskripsi, p.tipe_barang, p.satuan,
                       COALESCE(NULLIF(p.harga_default, 0), p.harga_satuan, 0) AS harga_default_view,
                       k.nama_kategori,
                       COALESCE(sg.jumlah_stok, 0) AS stok_gudang,
                       $unitCountSelect
                FROM produk p
                LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
                LEFT JOIN stokgudang sg ON sg.id_produk = p.id_produk AND sg.id_gudang = ?
                WHERE sg.id_produk IS NOT NULL OR p.id_gudang = ?$unitExistsClause
                ORDER BY p.nama_produk ASC";
$produkStmt = $koneksi->prepare($produkQuery);
$produkRows = [];

if ($produkStmt) {
    if ($hasUnitTable) {
        $produkStmt->bind_param('iiiii', $id_gudang, $id_gudang, $id_gudang, $id_gudang, $id_gudang);
    } else {
        $produkStmt->bind_param('ii', $id_gudang, $id_gudang);
    }
    $produkStmt->execute();
    $produkResult = $produkStmt->get_result();
    while ($row = $produkResult->fetch_assoc()) {
        $produkRows[] = $row;
    }
}

$unitRows = [];
if ($hasUnitTable) {
    $unitCodeSelect = get_asset_unit_code_column($koneksi);
    $unitQrColumn = get_asset_unit_qr_column($koneksi);
    $unitQuery = "SELECT ub.id_unit_barang, ub.status, ub.kondisi, ub.lokasi_custom,
                         " . ($unitQrColumn !== null ? "ub.`$unitQrColumn` AS qr_value" : "NULL AS qr_value") . ",
                         " . ($unitCodeSelect !== null ? "ub.`$unitCodeSelect` AS kode_unit" : "NULL AS kode_unit") . ",
                         p.id_produk, p.kode_produk, p.nama_produk, u.nama AS nama_user
                  FROM unit_barang ub
                  LEFT JOIN produk p ON ub.id_produk = p.id_produk
                  LEFT JOIN user u ON ub.id_user = u.id_user
                  WHERE ub.id_gudang = ?
                  ORDER BY p.nama_produk ASC, ub.id_unit_barang DESC";
    $unitStmt = $koneksi->prepare($unitQuery);
    if ($unitStmt) {
        $unitStmt->bind_param('i', $id_gudang);
        $unitStmt->execute();
        $unitResult = $unitStmt->get_result();
        while ($row = $unitResult->fetch_assoc()) {
            $unitRows[] = $row;
        }
    }
}

$activityRows = fetch_activity_logs($koneksi, ['id_gudang' => $id_gudang], 100);
$noteRows = fetch_inventory_notes($koneksi, ['id_gudang' => $id_gudang], 25);

$totalProduk = count($produkRows);
$totalStokConsumable = 0;
$totalUnitAsset = 0;
$totalUnitTersedia = 0;

foreach ($produkRows as $produkRow) {
    if (($produkRow['tipe_barang'] ?? 'consumable') === 'asset') {
        $totalUnitAsset += intval($produkRow['total_unit_gudang'] ?? 0);
        $totalUnitTersedia += intval($produkRow['unit_tersedia_gudang'] ?? 0);
    } else {
        $totalStokConsumable += intval($produkRow['stok_gudang'] ?? 0);
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Detail Gudang</h2>
        <a href="index.php?page=data_gudang" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h4 class="card-title mb-3"><?= htmlspecialchars($gudang['nama_gudang']) ?></h4>
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr><th class="ps-0">Lokasi</th><td><?= htmlspecialchars($gudang['lokasi'] ?? '-') ?></td></tr>
                            <tr><th class="ps-0">Total Barang</th><td><?= $totalProduk ?></td></tr>
                            <tr><th class="ps-0">Stok Consumable</th><td><?= $totalStokConsumable ?></td></tr>
                            <tr><th class="ps-0">Total Unit Asset</th><td><?= $totalUnitAsset ?></td></tr>
                            <tr><th class="ps-0">Unit Asset Tersedia</th><td><?= $totalUnitTersedia ?></td></tr>
                            <tr><th class="ps-0">Catatan</th><td><?= count($noteRows) ?></td></tr>
                            <tr><th class="ps-0">Aktivitas Tercatat</th><td><?= count($activityRows) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Ringkasan Isi Gudang</h5>
                    <div class="row g-3">
                        <?php foreach ($produkRows as $produkRow): ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($produkRow['nama_produk']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($produkRow['kode_produk']) ?> • <?= htmlspecialchars($produkRow['nama_kategori'] ?? '-') ?></div>
                                    </div>
                                    <span class="badge <?= ($produkRow['tipe_barang'] ?? 'consumable') === 'asset' ? 'bg-primary' : 'bg-success' ?>">
                                        <?= htmlspecialchars(ucfirst($produkRow['tipe_barang'] ?? 'consumable')) ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-2"><?= htmlspecialchars($produkRow['deskripsi'] ?? '-') ?></div>
                                <?php if (($produkRow['tipe_barang'] ?? 'consumable') === 'asset'): ?>
                                <div class="small">Unit di gudang: <strong><?= intval($produkRow['total_unit_gudang'] ?? 0) ?></strong></div>
                                <div class="small">Unit tersedia: <strong><?= intval($produkRow['unit_tersedia_gudang'] ?? 0) ?></strong></div>
                                <?php else: ?>
                                <div class="small">Stok gudang: <strong><?= intval($produkRow['stok_gudang'] ?? 0) ?> <?= htmlspecialchars($produkRow['satuan'] ?? '') ?></strong></div>
                                <div class="small">Harga default: <strong><?= formatGudangRupiah($produkRow['harga_default_view'] ?? 0) ?></strong></div>
                                <?php endif; ?>
                                <a href="index.php?page=produk_info&id_produk=<?= intval($produkRow['id_produk']) ?>" class="btn btn-sm btn-outline-primary mt-3">Lihat Detail Barang</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($produkRows)): ?>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0">Belum ada barang yang terhubung ke gudang ini.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Daftar Barang di Gudang</div>
        <div class="card-body table-container overflowy">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Barang</th>
                        <th>Tipe</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Ringkasan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($produkRows)): ?>
                        <?php foreach ($produkRows as $index => $produkRow): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($produkRow['kode_produk']) ?></td>
                            <td><?= htmlspecialchars($produkRow['nama_produk']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($produkRow['tipe_barang'] ?? 'consumable')) ?></td>
                            <td><?= htmlspecialchars($produkRow['nama_kategori'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($produkRow['deskripsi'] ?? '-') ?></td>
                            <td>
                                <?php if (($produkRow['tipe_barang'] ?? 'consumable') === 'asset'): ?>
                                    <?= intval($produkRow['total_unit_gudang'] ?? 0) ?> unit, <?= intval($produkRow['unit_tersedia_gudang'] ?? 0) ?> tersedia
                                <?php else: ?>
                                    <?= intval($produkRow['stok_gudang'] ?? 0) ?> <?= htmlspecialchars($produkRow['satuan'] ?? '') ?>
                                <?php endif; ?>
                            </td>
                            <td><a href="index.php?page=produk_info&id_produk=<?= intval($produkRow['id_produk']) ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Belum ada barang di gudang ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Unit / Asset di Gudang</div>
        <div class="card-body table-container overflowy">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Unit</th>
                        <th>Barang</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>User</th>
                        <th>Lokasi Custom</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($unitRows)): ?>
                        <?php foreach ($unitRows as $index => $unitRow): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($unitRow['kode_unit'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(($unitRow['kode_produk'] ?? '-') . ' - ' . ($unitRow['nama_produk'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($unitRow['status'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($unitRow['kondisi'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($unitRow['nama_user'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($unitRow['lokasi_custom'] ?? '-') ?></td>
                            <td><a href="index.php?page=unit_barang_info&id_unit_barang=<?= intval($unitRow['id_unit_barang']) ?>" class="btn btn-sm btn-outline-primary">Detail Unit</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Belum ada unit asset yang tersimpan di gudang ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Catatan Gudang</div>
                <div class="card-body">
                    <?php if ($canManageInventory): ?>
                    <form action="actions/simpan_catatan.php" method="post" class="border rounded p-3 mb-3 bg-light">
                        <input type="hidden" name="id_gudang" value="<?= $id_gudang ?>">
                        <input type="hidden" name="tipe_target" value="gudang">
                        <div class="mb-3">
                            <label class="form-label">Kategori Catatan</label>
                            <select name="kategori_catatan" class="form-select" required>
                                <option value="umum">Umum</option>
                                <option value="kerusakan">Kerusakan</option>
                                <option value="selisih">Selisih</option>
                                <option value="servis">Servis</option>
                                <option value="bug">Bug</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Judul</label>
                            <input type="text" name="judul" class="form-control" placeholder="Contoh: Selisih stok rak A">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="3" placeholder="Tulis catatan operasional gudang" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Simpan Catatan</button>
                    </form>
                    <?php endif; ?>

                    <div class="table-container overflowy" style="max-height: 360px;">
                        <table class="table table-sm table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kategori</th>
                                    <th>Judul</th>
                                    <th>Catatan</th>
                                    <th>Pembuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($noteRows)): ?>
                                    <?php foreach ($noteRows as $noteRow): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($noteRow['created_at'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($noteRow['kategori_catatan'] ?? 'umum')) ?></td>
                                        <td><?= htmlspecialchars($noteRow['judul'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($noteRow['catatan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($noteRow['nama_pembuat'] ?? '-') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">Belum ada catatan gudang.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Histori Tracking Gudang</div>
                <div class="card-body table-container overflowy">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Aksi</th>
                                <th>Entitas</th>
                                <th>Deskripsi</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($activityRows)): ?>
                                <?php foreach ($activityRows as $activityRow): ?>
                                <tr>
                                    <td><?= htmlspecialchars($activityRow['created_at'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($activityRow['action_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(($activityRow['entity_type'] ?? '-') . ' #' . ($activityRow['entity_id'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars($activityRow['description'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($activityRow['actor_name'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">Belum ada histori tracking untuk gudang ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
