<?php
$canManageInventory = inventory_user_can_manage();
$serahTerimaId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($serahTerimaId < 1) {
    echo '<div class="alert alert-warning">ID serah terima tidak valid.</div>';
    return;
}

$stmt = $koneksi->prepare(
    "SELECT stb.*, g.nama_gudang AS nama_gudang_asal
     FROM serah_terima_barang stb
     LEFT JOIN gudang g ON stb.gudang_asal_id = g.id_gudang
     WHERE stb.id = ?
     LIMIT 1"
);
if (!$stmt) {
    echo '<div class="alert alert-danger">Gagal memuat detail serah terima.</div>';
    return;
}
$stmt->bind_param('i', $serahTerimaId);
$stmt->execute();
$result = $stmt->get_result();
$header = $result ? $result->fetch_assoc() : null;

if (!$header) {
    echo '<div class="alert alert-warning">Data serah terima tidak ditemukan.</div>';
    return;
}

$detailRows = fetch_serah_terima_detail_rows($koneksi, $serahTerimaId);
$historiRows = fetch_histori_logs($koneksi, ['ref_type' => 'handover', 'ref_id' => $serahTerimaId], 100);
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
            'kondisi' => $meta['kondisi'] ?? null,
            'deskripsi' => $historiRow['deskripsi'] ?? null,
        ];
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Detail Serah Terima Barang</h2>
        <a href="index.php?page=serah_terima" class="btn btn-secondary">Kembali</a>
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
                            <tr><th>Kode BA</th><td><?= htmlspecialchars($header['kode_serah_terima'] ?? '-') ?></td></tr>
                            <tr><th>Tanggal</th><td><?= htmlspecialchars($header['tanggal_serah_terima'] ?? '-') ?></td></tr>
                            <tr><th>Jenis Tujuan</th><td><?= htmlspecialchars(ucfirst($header['jenis_tujuan'] ?? '-')) ?></td></tr>
                            <tr><th>Penyerah</th><td><?= htmlspecialchars($header['pihak_penyerah_nama'] ?? '-') ?></td></tr>
                            <tr><th>Penerima</th><td><?= htmlspecialchars($header['pihak_penerima_nama'] ?? '-') ?></td></tr>
                            <tr><th>Gudang Asal</th><td><?= htmlspecialchars($header['nama_gudang_asal'] ?? '-') ?></td></tr>
                            <tr><th>Lokasi Tujuan</th><td><?= htmlspecialchars($header['lokasi_tujuan'] ?? '-') ?></td></tr>
                            <tr><th>Status</th><td><?= htmlspecialchars(ucfirst($header['status'] ?? '-')) ?></td></tr>
                            <tr><th>Catatan</th><td><?= nl2br(htmlspecialchars($header['catatan'] ?? '-')) ?></td></tr>
                            <tr>
                                <th>Dokumen</th>
                                <td>
                                    <?php if (!empty($header['dokumen_file'])): ?>
                                    <a href="<?= htmlspecialchars($header['dokumen_file']) ?>" target="_blank"><?= htmlspecialchars(basename((string) $header['dokumen_file'])) ?></a>
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
                <div class="card-header">Tindak Lanjut</div>
                <div class="card-body">
                    <div class="alert alert-info">
                        Modul ini menjadi opsi utama untuk handover formal. Assign/release cepat tetap ada, tetapi tidak menggantikan BA serah terima.
                    </div>
                    <?php if ($canManageInventory && ($header['status'] ?? '') === 'aktif'): ?>
                    <form action="action/selesai_serah_terima.php" method="post">
                        <input type="hidden" name="id" value="<?= intval($serahTerimaId) ?>">
                        <div class="mb-3">
                            <label class="form-label">Catatan Pengembalian / Penyelesaian</label>
                            <textarea name="catatan_kembali" class="form-control" rows="3" placeholder="Catatan kondisi saat kembali, verifikasi, dan penutupan BA."></textarea>
                        </div>
                        <?php foreach ($detailRows as $detail): ?>
                        <div class="mb-3 border rounded p-3 bg-light">
                            <div class="fw-semibold mb-2"><?= htmlspecialchars(($detail['kode_produk'] ?? '-') . ' - ' . ($detail['nama_produk'] ?? '-')) ?></div>
                            <label class="form-label">Kondisi Kembali</label>
                            <select name="kondisi_kembali[<?= intval($detail['id']) ?>]" class="form-select">
                                <?php foreach (['baik', 'rusak', 'diperbaiki', 'usang', 'lainnya'] as $kondisi): ?>
                                <option value="<?= htmlspecialchars($kondisi) ?>" <?= ($detail['kondisi_serah'] ?? '') === $kondisi ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($kondisi)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-outline-primary">Tandai Dikembalikan</button>
                    </form>
                    <?php else: ?>
                    <p class="mb-0">Serah terima ini sudah final atau Anda tidak memiliki akses penyelesaian.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Detail Item Serah Terima</div>
        <div class="card-body table-container overflowy">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Produk</th>
                        <th>Unit</th>
                        <th>Qty</th>
                        <th>Kondisi Serah</th>
                        <th>Kondisi Kembali</th>
                        <th>Tanggal Kembali</th>
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
                            <td><?= htmlspecialchars($detail['kondisi_serah'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($detail['kondisi_kembali'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($detail['tanggal_kembali'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($detail['catatan_detail'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="8" class="text-center">Belum ada detail serah terima.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($documentationPhotos)): ?>
    <div class="card mb-4">
        <div class="card-header">Foto Dokumentasi Serah Terima</div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($documentationPhotos as $photo): ?>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <a href="<?= htmlspecialchars($photo['path']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($photo['path']) ?>" alt="Foto dokumentasi serah terima" class="img-fluid rounded mb-2">
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
        <div class="card-header">Histori Event Serah Terima</div>
        <div class="card-body table-container overflowy">
            <table class="table table-sm table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Event</th>
                        <th>Oleh</th>
                        <th>Target</th>
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
                            <td><?= htmlspecialchars($histori['user_name_snapshot'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($histori['target_user_name_snapshot'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($meta['kondisi'] ?? '-') ?></td>
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
                    <tr><td colspan="7" class="text-center">Belum ada histori serah terima.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
