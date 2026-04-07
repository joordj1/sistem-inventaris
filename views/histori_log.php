<?php
$filterRefType = trim((string) ($_GET['ref_type'] ?? ''));
$filterUnit = isset($_GET['unit_barang_id']) && $_GET['unit_barang_id'] !== '' ? intval($_GET['unit_barang_id']) : null;
$filterProduk = isset($_GET['produk_id']) && $_GET['produk_id'] !== '' ? intval($_GET['produk_id']) : null;
$filterGudang = isset($_GET['gudang_id']) && $_GET['gudang_id'] !== '' ? intval($_GET['gudang_id']) : null;

$hasOfficialHistoriTable = schema_table_exists_now($koneksi, 'histori_log');
$historiRows = $hasOfficialHistoriTable ? fetch_histori_logs($koneksi, [
    'ref_type' => $filterRefType,
    'unit_barang_id' => $filterUnit,
    'produk_id' => $filterProduk,
    'gudang_id' => $filterGudang,
], 300) : [];

$fallbackRows = [];

if (empty($historiRows) && schema_table_exists_now($koneksi, 'activity_log')) {
    $activitySql = "SELECT al.id_log, al.created_at, al.action_name, al.entity_type, al.entity_id, al.entity_label,
                           al.description, al.id_produk, al.id_unit_barang, al.id_gudang, u.nama AS actor_name
                    FROM activity_log al
                    LEFT JOIN user u ON al.id_user = u.id_user
                    WHERE 1 = 1";
    $activityTypes = '';
    $activityParams = [];

    if ($filterProduk !== null) {
        $activitySql .= " AND al.id_produk = ?";
        $activityTypes .= 'i';
        $activityParams[] = $filterProduk;
    }
    if ($filterUnit !== null) {
        $activitySql .= " AND al.id_unit_barang = ?";
        $activityTypes .= 'i';
        $activityParams[] = $filterUnit;
    }
    if ($filterGudang !== null) {
        $activitySql .= " AND al.id_gudang = ?";
        $activityTypes .= 'i';
        $activityParams[] = $filterGudang;
    }

    $activityRefMap = [
        'mutasi' => ['column' => 'al.entity_type', 'value' => 'mutasi'],
        'handover' => ['column' => 'al.entity_type', 'value' => 'handover'],
        'unit' => ['column' => 'al.entity_type', 'value' => 'unit'],
        'barang_masuk' => ['column' => 'al.action_name', 'value' => 'transaksi_masuk'],
        'barang_keluar' => ['column' => 'al.action_name', 'value' => 'transaksi_keluar'],
    ];
    if ($filterRefType !== '' && isset($activityRefMap[$filterRefType])) {
        $activitySql .= " AND " . $activityRefMap[$filterRefType]['column'] . " = ?";
        $activityTypes .= 's';
        $activityParams[] = $activityRefMap[$filterRefType]['value'];
    } elseif ($filterRefType === 'tracking') {
        $activitySql .= " AND 1 = 0";
    }

    $activitySql .= " ORDER BY al.created_at DESC, al.id_log DESC LIMIT 100";
    $activityStmt = $koneksi->prepare($activitySql);
    if ($activityStmt) {
        if ($activityTypes !== '') {
            $activityStmt->bind_param($activityTypes, ...$activityParams);
        }
        $activityStmt->execute();
        $activityResult = $activityStmt->get_result();
        while ($activityResult && ($row = $activityResult->fetch_assoc())) {
            $fallbackRows[] = [
                'source' => 'activity_log',
                'created_at' => $row['created_at'] ?? null,
                'ref' => trim((string) (($row['entity_type'] ?? 'activity') . ' #' . ($row['entity_id'] ?? $row['id_log']))),
                'event' => $row['action_name'] ?? '-',
                'user_name' => $row['actor_name'] ?? '-',
                'target_name' => '-',
                'deskripsi' => $row['description'] ?? ($row['entity_label'] ?? '-'),
            ];
        }
    }
}

if (empty($historiRows) && schema_table_exists_now($koneksi, 'tracking_barang') && ($filterRefType === '' || $filterRefType === 'tracking')) {
    $trackingSql = "SELECT tb.id_tracking, tb.changed_at, tb.id_produk, tb.activity_type, tb.note, tb.id_user_changed,
                           p.nama_produk, u.nama AS actor_name
                    FROM tracking_barang tb
                    LEFT JOIN produk p ON tb.id_produk = p.id_produk
                    LEFT JOIN user u ON tb.id_user_changed = u.id_user
                    WHERE 1 = 1";
    $trackingTypes = '';
    $trackingParams = [];

    if ($filterProduk !== null) {
        $trackingSql .= " AND tb.id_produk = ?";
        $trackingTypes .= 'i';
        $trackingParams[] = $filterProduk;
    }

    $trackingSql .= " ORDER BY tb.changed_at DESC, tb.id_tracking DESC LIMIT 100";
    $trackingStmt = $koneksi->prepare($trackingSql);
    if ($trackingStmt) {
        if ($trackingTypes !== '') {
            $trackingStmt->bind_param($trackingTypes, ...$trackingParams);
        }
        $trackingStmt->execute();
        $trackingResult = $trackingStmt->get_result();
        while ($trackingResult && ($row = $trackingResult->fetch_assoc())) {
            $fallbackRows[] = [
                'source' => 'tracking_barang',
                'created_at' => $row['changed_at'] ?? null,
                'ref' => 'produk #' . intval($row['id_produk'] ?? 0),
                'event' => $row['activity_type'] ?? '-',
                'user_name' => $row['actor_name'] ?? '-',
                'target_name' => '-',
                'deskripsi' => $row['note'] ?? ('Riwayat tracking untuk ' . ($row['nama_produk'] ?? 'produk')),
            ];
        }
    }
}

if (!empty($fallbackRows)) {
    usort($fallbackRows, static function ($left, $right) {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });
}
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

    <?php if (!empty($historiRows)): ?>
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
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        Belum ada histori resmi tercatat.
        <?php if (!$hasOfficialHistoriTable): ?>
            Tabel <code>histori_log</code> akan dibuat otomatis saat event resmi pertama berhasil disimpan.
        <?php endif; ?>
    </div>

    <?php if (!empty($fallbackRows)): ?>
    <div class="card">
        <div class="card-header">Fallback Read-Only dari Log Lama</div>
        <div class="card-body table-container overflowy">
            <table class="table table-bordered table-striped mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Sumber</th>
                        <th>Waktu</th>
                        <th>Ref</th>
                        <th>Event</th>
                        <th>User</th>
                        <th>Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fallbackRows as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($row['source'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['ref'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['event'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['deskripsi'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
