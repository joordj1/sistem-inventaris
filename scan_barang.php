<?php
include 'koneksi/koneksi.php';

$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';

$scanRate = inventory_rate_limit_check('scan_qr_public', 90, 60);
if (!$scanRate['allowed']) {
    http_response_code(429);
    echo '<!doctype html><html><head><meta charset="UTF-8"><title>Terlalu Banyak Permintaan</title></head><body>';
    echo '<h3>Terlalu banyak permintaan scan. Coba lagi dalam ' . intval($scanRate['retry_after']) . ' detik.</h3>';
    echo '</body></html>';
    exit;
}

// --- Canonical parameter: ?unit_id=123 ---
$rawQrHash = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$isValidQrHash = preg_match('/^[a-f0-9]{16,64}$/i', $rawQrHash) === 1;
$rawUnitId = isset($_GET['unit_id']) ? trim((string) $_GET['unit_id']) : '';
$unitId    = ctype_digit($rawUnitId) ? intval($rawUnitId) : 0;
$isValidUnitId = $unitId > 0;

if ($isValidQrHash && schema_table_exists_now($koneksi, 'unit_barang') && asset_qr_hash_column_exists($koneksi)) {
    $stmtHash = $koneksi->prepare('SELECT id_unit_barang FROM unit_barang WHERE qr_hash = ? LIMIT 1');
    if ($stmtHash) {
        $stmtHash->bind_param('s', $rawQrHash);
        $stmtHash->execute();
        $resHash = $stmtHash->get_result();
        $rowHash = $resHash ? $resHash->fetch_assoc() : null;
        if ($rowHash) {
            $unitId = intval($rowHash['id_unit_barang']);
            $isValidUnitId = $unitId > 0;
        }
    }
}

// Legacy compat 1: ?id_unit_barang=X → redirect to ?unit_id=X
if (!$isValidUnitId && isset($_GET['id_unit_barang'])) {
    $legacyId = intval($_GET['id_unit_barang']);
    if ($legacyId > 0) {
        header('Location: /scan_barang.php?unit_id=' . $legacyId, true, 302);
        exit;
    }
}

// Legacy compat 2: ?id=PRODUCT_ID (old product-based QR)
if (!$isValidUnitId && isset($_GET['id'])) {
    $legacyProductId = intval($_GET['id']);
    if ($legacyProductId > 0 && schema_table_exists_now($koneksi, 'unit_barang')) {
        $stmtLegacy = $koneksi->prepare(
            "SELECT id_unit_barang FROM unit_barang WHERE id_produk = ? ORDER BY id_unit_barang LIMIT 2"
        );
        if ($stmtLegacy) {
            $stmtLegacy->bind_param('i', $legacyProductId);
            $stmtLegacy->execute();
            $resLegacy = $stmtLegacy->get_result();
            $legacyUnits = [];
            while ($r = $resLegacy->fetch_assoc()) {
                $legacyUnits[] = intval($r['id_unit_barang']);
            }
            if (count($legacyUnits) === 1) {
                header('Location: /scan_barang.php?unit_id=' . $legacyUnits[0], true, 302);
                exit;
            }
            // Multiple or zero units: fall through to show message below.
        }
    }
}

// --- Main unit lookup ---
$unit = null;
if ($isValidUnitId) {
    $unitCodeCol = get_asset_unit_code_column($koneksi) ?? 'serial_number';
    $qrCol       = get_asset_unit_qr_column($koneksi);
    $selectQr    = $qrCol !== null ? ", ub.`$qrCol` AS qr_value" : ', NULL AS qr_value';

    $hasLokasiTable = schema_has_column($koneksi, 'unit_barang', 'id_lokasi')
                      && schema_table_exists_now($koneksi, 'lokasi');
    $lokasiJoin   = $hasLokasiTable ? 'LEFT JOIN lokasi l ON ub.id_lokasi = l.id_lokasi' : '';
    $lokasiSelect = $hasLokasiTable ? ', l.nama_lokasi' : ', NULL AS nama_lokasi';

    $sql = "SELECT ub.id_unit_barang, ub.id_produk,
                   ub.`$unitCodeCol` AS kode_unit,
                   ub.status, ub.kondisi,
                   ub.lokasi_custom, ub.id_gudang, ub.id_user,
                   ub.tersedia, ub.created_at, ub.updated_at,
                   p.nama_produk, p.kode_produk, p.tipe_barang,
                   g.nama_gudang,
                   u.nama AS nama_user
                   $selectQr
                   $lokasiSelect
            FROM unit_barang ub
            LEFT JOIN produk p ON ub.id_produk = p.id_produk
            LEFT JOIN gudang g ON ub.id_gudang = g.id_gudang
            LEFT JOIN user u  ON ub.id_user  = u.id_user
            $lokasiJoin
            WHERE ub.id_unit_barang = ?
            LIMIT 1";

    $stmtUnit = $koneksi->prepare($sql);
    if ($stmtUnit) {
        $stmtUnit->bind_param('i', $unitId);
        $stmtUnit->execute();
        $unit = $stmtUnit->get_result()->fetch_assoc() ?: null;
    }
}

// --- Latest photo (from product histori_log) ---
$latestPhoto = null;
if ($unit) {
    $latestPhoto = get_latest_product_photo($koneksi, $unit['id_produk']);
}

// --- Unit-specific history (same schema-aware approach as halaman detail unit) ---
$historyRows = [];
if ($unit && schema_table_exists($koneksi, 'riwayat_unit_barang')) {
    $historyIdCol       = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['id', 'id_riwayat']);
    $historyActivityCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['aktivitas', 'activity_type']);
    $historyNoteCol     = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['catatan', 'note']);
    $historyTimeCol     = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['created_at', 'changed_at']);
    $historyActorCol    = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['id_user_changed', 'id_user']);
    $historyRelatedCol  = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['id_user_terkait', 'id_user_sesudah', 'id_user']);
    $historySnapshotCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['actor_name_snapshot', 'user_name_snapshot']);

    $historySelect = [
        ($historyIdCol       !== null ? "hr.`$historyIdCol`"       : '0')    . ' AS history_id',
        ($historyActivityCol !== null ? "hr.`$historyActivityCol`" : 'NULL') . ' AS aktivitas',
        'hr.status_sebelum', 'hr.status_sesudah',
        'hr.kondisi_sebelum', 'hr.kondisi_sesudah',
        'hr.lokasi_sebelum', 'hr.lokasi_sesudah',
        ($historyNoteCol     !== null ? "hr.`$historyNoteCol`"     : 'NULL') . ' AS catatan',
        ($historyTimeCol     !== null ? "hr.`$historyTimeCol`"     : 'NULL') . ' AS history_time',
        ($historyActorCol    !== null ? "hr.`$historyActorCol`"    : 'NULL') . ' AS actor_user_id',
        ($historyRelatedCol  !== null ? "hr.`$historyRelatedCol`"  : 'NULL') . ' AS related_user_id',
        ($historySnapshotCol !== null ? "hr.`$historySnapshotCol`" : 'NULL') . ' AS actor_name_snapshot',
    ];

    $hq = 'SELECT ' . implode(', ', $historySelect) . ",
                  u_actor.nama AS nama_user_actor,
                  u_related.nama AS nama_user_terkait
           FROM riwayat_unit_barang hr";

    $hq .= $historyActorCol !== null
        ? "\n           LEFT JOIN user u_actor ON hr.`$historyActorCol` = u_actor.id_user"
        : "\n           LEFT JOIN user u_actor ON 1 = 0";

    $hq .= $historyRelatedCol !== null
        ? "\n           LEFT JOIN user u_related ON hr.`$historyRelatedCol` = u_related.id_user"
        : "\n           LEFT JOIN user u_related ON 1 = 0";

    $hq .= "\n           WHERE hr.id_unit_barang = ?";
    if ($historyTimeCol !== null) {
        $hq .= "\n           ORDER BY hr.`$historyTimeCol` DESC";
        if ($historyIdCol !== null) {
            $hq .= ", hr.`$historyIdCol` DESC";
        }
    }
    $hq .= "\n           LIMIT 30";

    $stmtHist = $koneksi->prepare($hq);
    if ($stmtHist) {
        $stmtHist->bind_param('i', $unit['id_unit_barang']);
        $stmtHist->execute();
        $resHist = $stmtHist->get_result();
        while ($resHist && ($row = $resHist->fetch_assoc())) {
            $historyRows[] = $row;
        }
    }
}

function scan_badge_class($status) {
    $s = strtolower(trim((string) ($status ?? '')));
    if ($s === 'tersedia') return 'bg-success';
    if (in_array($s, ['dipinjam', 'digunakan', 'sedang digunakan'], true)) return 'bg-primary';
    if ($s === 'rusak') return 'bg-danger';
    if (in_array($s, ['perbaikan', 'dalam perbaikan', 'diperbaiki'], true)) return 'bg-warning text-dark';
    return 'bg-secondary';
}

function scan_kondisi_badge($kondisi) {
    $k = strtolower(trim((string) ($kondisi ?? '')));
    if ($k === 'baik') return 'bg-success';
    if (in_array($k, ['rusak', 'usang'], true)) return 'bg-danger';
    if (in_array($k, ['diperbaiki'], true)) return 'bg-warning text-dark';
    return 'bg-secondary';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Unit Asset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(180deg, #f8fbff 0%, #f1f5f9 100%);
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        .scan-wrap {
            max-width: 920px;
            margin: 0 auto;
            padding: 1rem;
        }
        .scan-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08);
        }
        .photo-frame {
            min-height: 180px;
            max-height: 300px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            overflow: hidden;
        }
        .photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .history-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem;
            background: #fff;
        }
        .unit-id-badge {
            font-size: 0.7rem;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
<div class="scan-wrap py-3 py-md-4">
    <div class="mb-3 text-center">
        <h1 class="h4 mb-1">Detail Unit Asset</h1>
        <p class="text-muted mb-0">Halaman publik hasil scan QR</p>
    </div>

    <?php if ($debugMode): ?>
        <div class="alert alert-secondary small">
            Debug QR: raw q = <strong><?= htmlspecialchars($rawQrHash) ?></strong>,
            Debug QR: raw unit_id = <strong><?= htmlspecialchars($rawUnitId) ?></strong>,
            parsed = <strong><?= $unitId ?></strong>
        </div>
    <?php endif; ?>

    <?php if (!$isValidUnitId): ?>
        <div class="card scan-card">
            <div class="card-body text-center py-5">
                <h2 class="h5">Parameter tidak valid</h2>
                <p class="text-muted mb-0">Gunakan format URL: <strong>/scan_barang.php?q=HASH</strong> atau <strong>/scan_barang.php?unit_id=123</strong></p>
            </div>
        </div>
    <?php elseif (!$unit): ?>
        <div class="card scan-card">
            <div class="card-body text-center py-5">
                <h2 class="h5">Unit tidak ditemukan</h2>
                <p class="text-muted mb-0">Kode QR tidak valid atau data unit asset tidak tersedia.</p>
            </div>
        </div>
    <?php else: ?>
        <?php
            $lokasiTampil = trim((string) ($unit['lokasi_custom'] ?? ''));
            if ($lokasiTampil === '') $lokasiTampil = trim((string) ($unit['nama_lokasi'] ?? ''));
            if ($lokasiTampil === '') $lokasiTampil = '-';
        ?>
        <div class="card scan-card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-7">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <h2 class="h5 mb-0"><?= htmlspecialchars($unit['nama_produk'] ?? '-') ?></h2>
                            <span class="badge bg-light text-secondary unit-id-badge border">Unit #<?= $unit['id_unit_barang'] ?></span>
                        </div>
                        <div class="mb-2"><strong>Kode Unit:</strong> <?= htmlspecialchars($unit['kode_unit'] ?? '-') ?></div>
                        <div class="mb-2"><strong>Kode Produk:</strong> <?= htmlspecialchars($unit['kode_produk'] ?? '-') ?></div>
                        <div class="mb-2"><strong>Gudang:</strong> <?= htmlspecialchars($unit['nama_gudang'] ?? '-') ?></div>
                        <div class="mb-2"><strong>Lokasi:</strong> <?= htmlspecialchars($lokasiTampil) ?></div>
                        <div class="mb-2">
                            <strong>Status:</strong>
                            <span class="badge <?= scan_badge_class($unit['status'] ?? '') ?>">
                                <?= htmlspecialchars($unit['status'] ?? '-') ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Kondisi:</strong>
                            <span class="badge <?= scan_kondisi_badge($unit['kondisi'] ?? '') ?>">
                                <?= htmlspecialchars($unit['kondisi'] ?? '-') ?>
                            </span>
                        </div>
                        <?php if (!empty($unit['nama_user'])): ?>
                            <div class="mb-2"><strong>Pengguna:</strong> <?= htmlspecialchars($unit['nama_user']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-5">
                        <div class="photo-frame">
                            <?php if (!empty($latestPhoto['photo_path'])): ?>
                                <img src="<?= htmlspecialchars($latestPhoto['photo_path']) ?>" alt="Foto terbaru barang">
                            <?php else: ?>
                                <div class="text-center text-muted px-3">
                                    <div class="fw-semibold">Belum ada foto</div>
                                    <small>Foto terbaru akan tampil setelah dokumentasi mutasi/serah terima diunggah.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card scan-card">
            <div class="card-body">
                <h3 class="h6 mb-3">Riwayat Unit</h3>
                <?php if (empty($historyRows)): ?>
                    <p class="text-muted mb-0">Belum ada riwayat untuk unit ini.</p>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($historyRows as $hrow): ?>
                            <?php
                                $hAktivitas  = trim((string) ($hrow['aktivitas'] ?? ''));
                                $hAktLabel   = $hAktivitas !== '' ? get_asset_unit_activity_type_label($hAktivitas) : '-';
                                $hWaktu      = $hrow['history_time'] ?? '-';
                                $hCatatan    = trim((string) ($hrow['catatan'] ?? ''));
                                if ($hCatatan === '') {
                                    $hCatatan = build_tracking_note_fallback([
                                        'aktivitas'      => $hrow['aktivitas'] ?? null,
                                        'status_sebelum' => $hrow['status_sebelum'] ?? null,
                                        'status_sesudah' => $hrow['status_sesudah'] ?? null,
                                        'kondisi_sebelum'=> $hrow['kondisi_sebelum'] ?? null,
                                        'kondisi_sesudah'=> $hrow['kondisi_sesudah'] ?? null,
                                        'lokasi_sebelum' => $hrow['lokasi_sebelum'] ?? null,
                                        'lokasi_sesudah' => $hrow['lokasi_sesudah'] ?? null,
                                        'id_user_terkait'=> $hrow['related_user_id'] ?? null,
                                    ], 'unit');
                                }
                                $hStatusSebelum  = get_asset_unit_status_label($hrow['status_sebelum'] ?? null);
                                $hStatusSesudah  = get_asset_unit_status_label($hrow['status_sesudah'] ?? null);
                                $hKondisiSebelum = trim((string) ($hrow['kondisi_sebelum'] ?? ''));
                                $hKondisiSesudah = trim((string) ($hrow['kondisi_sesudah'] ?? ''));
                                $hLokasiSesudah  = trim((string) ($hrow['lokasi_sesudah'] ?? ''));
                                $hLokasiSebelum  = trim((string) ($hrow['lokasi_sebelum'] ?? ''));
                                $hActor = trim((string) ($hrow['actor_name_snapshot'] ?? $hrow['nama_user_actor'] ?? ''));
                                $hUserTerkait = trim((string) ($hrow['nama_user_terkait'] ?? ''));
                                $showStatusChange  = $hStatusSebelum !== $hStatusSesudah && $hStatusSesudah !== '-' && $hStatusSesudah !== '';
                                $showKondisiChange = $hKondisiSebelum !== $hKondisiSesudah && $hKondisiSesudah !== '';
                                $showLokasiChange  = $hLokasiSesudah !== '';
                            ?>
                            <div class="history-item">
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-1">
                                    <span class="badge bg-secondary"><?= htmlspecialchars($hAktLabel) ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($hWaktu) ?></small>
                                </div>
                                <?php if ($hCatatan !== ''): ?>
                                    <div class="mb-1"><?= htmlspecialchars($hCatatan) ?></div>
                                <?php endif; ?>
                                <?php if ($showStatusChange): ?>
                                    <small class="text-muted d-block">Status: <?= htmlspecialchars($hStatusSebelum) ?> &rarr; <strong><?= htmlspecialchars($hStatusSesudah) ?></strong></small>
                                <?php endif; ?>
                                <?php if ($showKondisiChange): ?>
                                    <small class="text-muted d-block">Kondisi: <?= htmlspecialchars($hKondisiSebelum ?: '-') ?> &rarr; <strong><?= htmlspecialchars($hKondisiSesudah) ?></strong></small>
                                <?php endif; ?>
                                <?php if ($showLokasiChange): ?>
                                    <small class="text-muted d-block">Lokasi: <?php if ($hLokasiSebelum !== ''): ?><?= htmlspecialchars($hLokasiSebelum) ?> &rarr; <?php endif; ?><strong><?= htmlspecialchars($hLokasiSesudah) ?></strong></small>
                                <?php endif; ?>
                                <?php if ($hActor !== ''): ?>
                                    <small class="text-muted d-block">Oleh: <?= htmlspecialchars($hActor) ?><?= $hUserTerkait !== '' && $hUserTerkait !== $hActor ? ' &bull; Ke: ' . htmlspecialchars($hUserTerkait) : '' ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
