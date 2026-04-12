<?php
$canManageInventory = inventory_user_can_manage();
$mutasiId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($mutasiId < 1) {
    echo '<div class="alert alert-warning">ID mutasi tidak valid.</div>';
    return;
}

$stmt = $koneksi->prepare(
    "SELECT mb.*,
            ga.nama_gudang AS nama_gudang_asal,
            gt.nama_gudang AS nama_gudang_tujuan
     FROM mutasi_barang mb
     LEFT JOIN gudang ga ON mb.gudang_asal_id = ga.id_gudang
     LEFT JOIN gudang gt ON mb.gudang_tujuan_id = gt.id_gudang
     WHERE mb.id = ?
     LIMIT 1"
);
if (!$stmt) {
    echo '<div class="alert alert-danger">Gagal memuat detail mutasi.</div>';
    return;
}
$stmt->bind_param('i', $mutasiId);
$stmt->execute();
$result = $stmt->get_result();
$mutasi = $result ? $result->fetch_assoc() : null;

if (!$mutasi) {
    echo '<div class="alert alert-warning">Data mutasi tidak ditemukan.</div>';
    return;
}

$detailRows = fetch_mutasi_barang_detail_rows($koneksi, $mutasiId);
$historiRows = fetch_histori_logs($koneksi, ['ref_type' => 'mutasi', 'ref_id' => $mutasiId], 100);
$documentationPhotos = [];
foreach ($historiRows as $historiRow) {
    $meta = decode_inventory_meta_json($historiRow['meta_json'] ?? null);
    $photoPath = trim((string) ($meta['foto_dokumentasi'] ?? ''));
    if ($photoPath === '') {
        continue;
    }

    if (!isset($documentationPhotos[$photoPath])) {
        $documentationPhotos[$photoPath] = [
            'path' => $photoPath,
            'tanggal' => $historiRow['created_at'] ?? null,
            'kondisi' => $meta['kondisi'] ?? ($meta['kondisi_sesudah'] ?? null),
            'deskripsi' => $historiRow['deskripsi'] ?? null,
        ];
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Detail Mutasi Barang</h2>
        <div class="d-flex gap-2">
            <a href="index.php?page=report_mutasi&tanggal_dari=<?= urlencode(substr((string) ($mutasi['tanggal_mutasi'] ?? ''), 0, 10)) ?>&tanggal_sampai=<?= urlencode(substr((string) ($mutasi['tanggal_mutasi'] ?? ''), 0, 10)) ?>" class="btn btn-outline-secondary">Lihat di Report</a>
            <a href="index.php?page=mutasi_barang" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr><th>Kode Mutasi</th><td><?= htmlspecialchars($mutasi['kode_mutasi'] ?? '-') ?></td></tr>
                            <tr><th>Tanggal</th><td><?= htmlspecialchars($mutasi['tanggal_mutasi'] ?? '-') ?></td></tr>
                            <tr><th>Gudang Asal</th><td><?= htmlspecialchars($mutasi['nama_gudang_asal'] ?? '-') ?></td></tr>
                            <tr><th>Gudang Tujuan</th><td><?= htmlspecialchars($mutasi['nama_gudang_tujuan'] ?? '-') ?></td></tr>
                            <tr><th>Jenis Barang</th><td><?= htmlspecialchars(ucfirst($mutasi['jenis_barang'] ?? '-')) ?></td></tr>
                            <tr><th>Status</th><td><?= htmlspecialchars(ucfirst($mutasi['status'] ?? '-')) ?></td></tr>
                            <tr><th>Dibuat Oleh</th><td><?= htmlspecialchars($mutasi['created_by_name'] ?? '-') ?></td></tr>
                            <tr><th>Disetujui Oleh</th><td><?= htmlspecialchars($mutasi['approved_by_name'] ?? '-') ?></td></tr>
                            <tr><th>Catatan</th><td><?= nl2br(htmlspecialchars($mutasi['catatan'] ?? '-')) ?></td></tr>
                            <tr>
                                <th>Dokumen</th>
                                <td>
                                    <?php if (!empty($mutasi['dokumen_file'])): ?>
                                    <a href="<?= htmlspecialchars($mutasi['dokumen_file']) ?>" target="_blank"><?= htmlspecialchars(basename((string) $mutasi['dokumen_file'])) ?></a>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Status Mutasi</div>
                <div class="card-body">
                    <div class="alert alert-info">
                        Mutasi barang adalah transaksi resmi perpindahan antar gudang. Lokasi unit asset tidak boleh lagi dipindah antar gudang lewat quick action biasa.
                    </div>
                    <?php if ($canManageInventory && ($mutasi['status'] ?? '') !== 'selesai'): ?>
                    <form action="action/update_mutasi_status.php" method="post">
                        <input type="hidden" name="id" value="<?= intval($mutasiId) ?>">
                        <div class="mb-3">
                            <label class="form-label">Ubah Status</label>
                            <select name="status" class="form-select" required>
                                <?php foreach (['draft', 'disetujui', 'selesai', 'dibatalkan'] as $statusOption): ?>
                                <option value="<?= htmlspecialchars($statusOption) ?>" <?= ($mutasi['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($statusOption)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan Status</label>
                            <textarea name="catatan_status" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Simpan Status</button>
                    </form>
                    <?php else: ?>
                    <p class="mb-0">Status mutasi sudah final atau Anda tidak memiliki hak ubah.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Detail Item Mutasi</div>
        <div class="card-body table-container overflowy">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Produk</th>
                        <th>Unit</th>
                        <th>Qty</th>
                        <th>Satuan</th>
                        <th>Kondisi Sebelum</th>
                        <th>Kondisi Sesudah</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($detailRows)): ?>
                        <?php foreach ($detailRows as $index => $detail): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars(($detail['kode_produk'] ?? '-') . ' - ' . ($detail['nama_produk'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($detail['kode_unit'] ?? '-') ?></td>
                            <td><?= intval($detail['qty'] ?? 0) ?></td>
                            <td><?= htmlspecialchars($detail['satuan_snapshot'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($detail['kondisi_sebelum'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($detail['kondisi_sesudah'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($detail['catatan_detail'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="8" class="text-center">Belum ada detail mutasi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($documentationPhotos)): ?>
    <div class="card mb-4">
        <div class="card-header">Foto Dokumentasi Mutasi</div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($documentationPhotos as $photo): ?>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <a href="<?= htmlspecialchars($photo['path']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($photo['path']) ?>" alt="Foto dokumentasi mutasi" class="img-fluid rounded mb-2">
                        </a>
                        <div class="small text-muted"><?= htmlspecialchars($photo['tanggal'] ?? '-') ?></div>
                        <div><strong>Kondisi:</strong> <?= htmlspecialchars($photo['kondisi'] ?? '-') ?></div>
                        <div><?= htmlspecialchars($photo['deskripsi'] ?? '-') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Histori Event Mutasi</div>
        <div class="card-body table-container overflowy">
            <table class="table table-sm table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Event</th>
                        <th>Produk / Unit</th>
                        <th>Oleh</th>
                        <th>Kondisi</th>
                        <th>Foto</th>
                        <th>Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($historiRows)): ?>
                        <?php foreach ($historiRows as $histori): ?>
                        <?php $meta = decode_inventory_meta_json($histori['meta_json'] ?? null); ?>
                        <tr>
                            <td><?= htmlspecialchars($histori['created_at'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($histori['event_type'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(($histori['produk_id'] ?? '-') . ' / ' . ($histori['unit_barang_id'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($histori['user_name_snapshot'] ?? ($histori['current_user_name'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($meta['kondisi'] ?? ($meta['kondisi_sesudah'] ?? '-')) ?></td>
                            <td>
                                <?php if (!empty($meta['foto_dokumentasi'])): ?>
                                <a href="<?= htmlspecialchars($meta['foto_dokumentasi']) ?>" target="_blank">Lihat Foto</a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($histori['deskripsi'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="7" class="text-center">Belum ada histori mutasi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
