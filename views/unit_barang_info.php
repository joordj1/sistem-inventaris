<?php
    $canManageInventory = inventory_user_can_manage();
function valueOrDash($value) {
    return isset($value) && trim((string) $value) !== '' ? $value : '-';
}

function fetchAllRows($result) {
    $rows = [];

    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function formatHistoryChange($before, $after, $formatter = null) {
    $beforeValue = $formatter ? $formatter($before) : valueOrDash($before);
    $afterValue = $formatter ? $formatter($after) : valueOrDash($after);

    if ($beforeValue === $afterValue) {
        return $afterValue;
    }

    return $beforeValue . ' -> ' . $afterValue;
}

function formatUnitStatusValue($value) {
    if (trim((string) ($value ?? '')) === '') {
        return '-';
    }

    return get_asset_unit_status_label($value);
}

function formatHistoryActivityDetail($value) {
    return get_asset_unit_activity_type_label($value);
}

function formatHistoryNoteDetail($row) {
    $note = trim((string) ($row['catatan'] ?? ''));
    if ($note !== '') {
        return $note;
    }

    return build_tracking_note_fallback([
        'aktivitas' => $row['aktivitas'] ?? null,
        'status_sebelum' => $row['status_sebelum'] ?? null,
        'status_sesudah' => $row['status_sesudah'] ?? null,
        'kondisi_sebelum' => $row['kondisi_sebelum'] ?? null,
        'kondisi_sesudah' => $row['kondisi_sesudah'] ?? null,
        'lokasi_sebelum' => $row['lokasi_sebelum'] ?? null,
        'lokasi_sesudah' => $row['lokasi_sesudah'] ?? null,
        'id_user_sebelum' => $row['id_user_sebelum'] ?? null,
        'id_user_sesudah' => $row['id_user_sesudah'] ?? null,
        'id_user_terkait' => $row['related_user_id'] ?? null,
    ], 'unit');
}

if (!isset($_GET['id_unit_barang'])) {
    echo '<div class="alert alert-warning">ID unit_barang tidak disediakan.</div>';
    exit;
}

$id_unit_barang = intval($_GET['id_unit_barang']);
$unitCodeColumn = schema_find_existing_column($koneksi, 'unit_barang', ['kode_unit', 'serial_number']);
$qrColumn = get_asset_unit_qr_column($koneksi);
$hasLokasiRelation = schema_has_column($koneksi, 'unit_barang', 'id_lokasi') && schema_table_exists($koneksi, 'lokasi');

if ($unitCodeColumn === null) {
    echo '<div class="alert alert-danger">Kolom kode unit asset tidak ditemukan.</div>';
    exit;
}

$unitSelect = [
    'ub.id_unit_barang',
    'ub.id_produk',
    "ub.`$unitCodeColumn` AS kode_unit",
    'ub.status',
    'ub.kondisi',
    'ub.id_gudang',
    'ub.lokasi_custom',
    'ub.id_user',
    'ub.tersedia',
    'ub.created_at',
    'ub.updated_at',
    'p.nama_produk',
    'p.kode_produk',
    'g.nama_gudang',
    'u.nama AS nama_user',
];

if ($qrColumn !== null) {
    $unitSelect[] = "ub.`$qrColumn` AS qr_value";
} else {
    $unitSelect[] = "NULL AS qr_value";
}

if (schema_has_column($koneksi, 'unit_barang', 'id_lokasi')) {
    $unitSelect[] = 'ub.id_lokasi';
} else {
    $unitSelect[] = 'NULL AS id_lokasi';
}

if ($hasLokasiRelation) {
    $unitSelect[] = 'l.nama_lokasi';
} else {
    $unitSelect[] = "NULL AS nama_lokasi";
}

$query = "SELECT " . implode(",\n                 ", $unitSelect) . "
          FROM unit_barang ub
          LEFT JOIN produk p ON ub.id_produk = p.id_produk
          LEFT JOIN gudang g ON ub.id_gudang = g.id_gudang
          LEFT JOIN user u ON ub.id_user = u.id_user";

if ($hasLokasiRelation) {
    $query .= "\n          LEFT JOIN lokasi l ON ub.id_lokasi = l.id_lokasi";
}

$query .= "\n          WHERE ub.id_unit_barang = ?";

$stmt = $koneksi->prepare($query);
if (!$stmt) {
    echo '<div class="alert alert-danger">Gagal menyiapkan data detail unit.</div>';
    exit;
}

$stmt->bind_param('i', $id_unit_barang);
$stmt->execute();
$result = $stmt->get_result();
$unit = $result->fetch_assoc();

if (!$unit) {
    echo '<div class="alert alert-danger">Unit barang tidak ditemukan.</div>';
    exit;
}

$currentStatus = normalize_asset_unit_status($unit['status'] ?? null);
$currentStatusLabel = get_asset_unit_status_label($currentStatus);
$currentStatusBadge = get_asset_unit_status_badge_class($currentStatus);

$qrValue = build_asset_qr_value($unit['id_unit_barang'], $unit['id_produk'] ?? null, $koneksi);
if ($qrColumn !== null) {
    $storedQr = trim((string) ($unit['qr_value'] ?? ''));
    if ($storedQr !== $qrValue) {
        $updateStmt = $koneksi->prepare("UPDATE unit_barang SET `$qrColumn` = ? WHERE id_unit_barang = ?");
        if ($updateStmt) {
            $updateStmt->bind_param('si', $qrValue, $unit['id_unit_barang']);
            $updateStmt->execute();
        }
    }
}
$unit['qr_value'] = $qrValue;

$qrImagePath = ensure_asset_qr_file($unit['id_unit_barang'], $unit['qr_value'] ?? null);
if ($qrImagePath === null) {
    $qrImagePath = get_asset_qr_relative_path($unit['id_unit_barang']);
}
$qrFallbackPath = 'assets/qr/qr_fallback.svg';

$displayLokasi = get_asset_unit_location_text($koneksi, $unit);
$gudangSaatIni = trim((string) ($unit['nama_gudang'] ?? ''));
$lokasiDetail = trim((string) ($unit['nama_lokasi'] ?? ''));
$lokasiCustom = trim((string) ($unit['lokasi_custom'] ?? ''));

$canMove = is_asset_unit_action_allowed('move', $currentStatus);
$canAssign = is_asset_unit_action_allowed('assign', $currentStatus);
$canRelease = is_asset_unit_action_allowed('release', $currentStatus);
$canMarkPerbaikan = is_asset_unit_action_allowed('mark_perbaikan', $currentStatus);
$canMarkRusak = is_asset_unit_action_allowed('mark_rusak', $currentStatus);
$canSetTersedia = is_asset_unit_action_allowed('set_tersedia', $currentStatus);

$availableActions = [];
if ($canMove) {
    $availableActions[] = 'Pindah lokasi';
}
if ($canAssign) {
    $availableActions[] = 'Assign / Pinjam';
}
if ($canRelease) {
    $availableActions[] = 'Release / Kembali';
}
if ($canSetTersedia) {
    $availableActions[] = 'Set tersedia';
}
if ($canMarkPerbaikan) {
    $availableActions[] = 'Mark perbaikan';
}
if ($canMarkRusak) {
    $availableActions[] = 'Mark rusak';
}

$historyRows = [];
if (schema_table_exists($koneksi, 'riwayat_unit_barang')) {
    $historyIdCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['id', 'id_riwayat']);
    $historyActivityCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['aktivitas', 'activity_type']);
    $historyNoteCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['catatan', 'note']);
    $historyTimeCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['created_at', 'changed_at']);
    $historyActorUserCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['id_user_changed', 'id_user']);
    $historyRelatedUserCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['id_user_terkait', 'id_user_sesudah', 'id_user']);
    $historyActorSnapshotCol = schema_find_existing_column($koneksi, 'riwayat_unit_barang', ['actor_name_snapshot', 'user_name_snapshot']);

    $historySelect = [
        ($historyIdCol !== null ? "hr.`$historyIdCol`" : "0") . " AS history_id",
        "hr.id_unit_barang",
        ($historyActivityCol !== null ? "hr.`$historyActivityCol`" : "NULL") . " AS aktivitas",
        "hr.status_sebelum",
        "hr.status_sesudah",
        "hr.kondisi_sebelum",
        "hr.kondisi_sesudah",
        "hr.lokasi_sebelum",
        "hr.lokasi_sesudah",
        ($historyNoteCol !== null ? "hr.`$historyNoteCol`" : "NULL") . " AS catatan",
        ($historyTimeCol !== null ? "hr.`$historyTimeCol`" : "NULL") . " AS history_time",
        ($historyActorUserCol !== null ? "hr.`$historyActorUserCol`" : "NULL") . " AS actor_user_id",
        ($historyRelatedUserCol !== null ? "hr.`$historyRelatedUserCol`" : "NULL") . " AS related_user_id",
        ($historyActorSnapshotCol !== null ? "hr.`$historyActorSnapshotCol`" : "NULL") . " AS actor_name_snapshot",
    ];

    $historyQuery = "SELECT " . implode(",\n                        ", $historySelect) . ",
                            u_actor.nama AS nama_user_actor,
                            u_related.nama AS nama_user_terkait
                     FROM riwayat_unit_barang hr";

    if ($historyActorUserCol !== null) {
        $historyQuery .= "\n                     LEFT JOIN user u_actor ON hr.`$historyActorUserCol` = u_actor.id_user";
    } else {
        $historyQuery .= "\n                     LEFT JOIN user u_actor ON 1 = 0";
    }

    if ($historyRelatedUserCol !== null) {
        $historyQuery .= "\n                     LEFT JOIN user u_related ON hr.`$historyRelatedUserCol` = u_related.id_user";
    } else {
        $historyQuery .= "\n                     LEFT JOIN user u_related ON 1 = 0";
    }

    $historyQuery .= "\n                     WHERE hr.id_unit_barang = ?";
    if ($historyTimeCol !== null) {
        $historyQuery .= "\n                     ORDER BY hr.`$historyTimeCol` DESC";
        if ($historyIdCol !== null) {
            $historyQuery .= ", hr.`$historyIdCol` DESC";
        }
    }

    $historyStmt = $koneksi->prepare($historyQuery);
    if ($historyStmt) {
        $historyStmt->bind_param('i', $id_unit_barang);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();
        $historyRows = fetchAllRows($historyResult);
    }
}

$gudangRows = fetchAllRows($koneksi->query("SELECT id_gudang, nama_gudang FROM gudang ORDER BY nama_gudang ASC"));
$userRows = get_active_user_rows($koneksi);
?>

<div class="container-lg px-0">
<style>
    .unit-info-section { background:#fff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 6px rgba(10,10,40,.06); padding:20px; margin-bottom:20px; }
    .unit-info-section h5 { font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; margin-bottom:14px; }
    .unit-field { display:flex; gap:12px; align-items:baseline; padding:7px 0; border-bottom:1px solid #f1f5f9; font-size:0.88rem; }
    .unit-field:last-child { border-bottom:none; }
    .unit-field-label { color:#64748b; min-width:130px; flex-shrink:0; font-weight:500; }
    .unit-field-value { color:#111827; font-weight:500; word-break:break-all; }
    .status-badge-lg { font-size:0.92rem; padding:5px 14px; border-radius:999px; font-weight:700; }
    .action-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:12px; }
    .action-card h6 { font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:10px; }
    .photo-placeholder { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; background:#f8fafc; border-radius:10px; color:#94a3b8; font-size:3rem; border:1px dashed #cbd5e1; }
    .qr-img-wrap { text-align:center; padding:16px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0; display:flex; flex-direction:column; align-items:center; gap:8px; }
    .history-table-wrap { max-height:320px; overflow-y:auto; }
</style>

    <!-- ===== TOP: Info + Photo ===== -->
    <div class="row g-3 mb-3">
        <!-- A. Info Utama (kiri) -->
        <div class="col-lg-7">
            <div class="unit-info-section h-100">
                <h5><i class="bi bi-info-circle me-1"></i>Info Unit Asset</h5>
                <div class="d-flex align-items-center gap-3 mb-3 pb-3" style="border-bottom:2px solid #f1f5f9">
                    <div>
                        <div style="font-size:1.3rem;font-weight:800;color:#111"><?= htmlspecialchars($unit['nama_produk'] ?? '-') ?></div>
                        <div style="color:#64748b;font-size:0.85rem"><?= htmlspecialchars($unit['kode_produk'] ?? '-') ?></div>
                    </div>
                    <span class="badge status-badge-lg <?= htmlspecialchars($currentStatusBadge) ?> ms-auto"><?= htmlspecialchars($currentStatusLabel) ?></span>
                </div>
                <div class="unit-field"><span class="unit-field-label">Kode Unit</span><span class="unit-field-value fw-bold font-monospace"><?= htmlspecialchars($unit['kode_unit'] ?? '-') ?></span></div>
                <div class="unit-field"><span class="unit-field-label">Kondisi</span><span class="unit-field-value"><span class="badge bg-secondary"><?= htmlspecialchars($unit['kondisi'] ?? '-') ?></span></span></div>
                <div class="unit-field"><span class="unit-field-label">Gudang</span><span class="unit-field-value"><?= htmlspecialchars($gudangSaatIni ?: '-') ?></span></div>
                <?php if ($lokasiDetail): ?>
                <div class="unit-field"><span class="unit-field-label">Lokasi Detail</span><span class="unit-field-value"><?= htmlspecialchars($lokasiDetail) ?></span></div>
                <?php endif; ?>
                <?php if ($lokasiCustom): ?>
                <div class="unit-field"><span class="unit-field-label">Lokasi Custom</span><span class="unit-field-value"><?= htmlspecialchars($lokasiCustom) ?></span></div>
                <?php endif; ?>
                <div class="unit-field"><span class="unit-field-label">Lokasi Saat Ini</span><span class="unit-field-value fw-bold"><?= htmlspecialchars($displayLokasi ?: '-') ?></span></div>
                <div class="unit-field"><span class="unit-field-label">Pengguna</span><span class="unit-field-value"><?= htmlspecialchars($unit['nama_user'] ?? '-') ?></span></div>
                <div class="unit-field"><span class="unit-field-label">Ditambahkan</span><span class="unit-field-value" style="color:#64748b"><?= htmlspecialchars($unit['created_at'] ?? '-') ?></span></div>
                <div class="unit-field"><span class="unit-field-label">Diperbarui</span><span class="unit-field-value" style="color:#64748b"><?= htmlspecialchars($unit['updated_at'] ?? '-') ?></span></div>
            </div>
        </div>

        <!-- B. Foto & QR (kanan) -->
        <div class="col-lg-5">
            <div class="unit-info-section h-100">
                <h5><i class="bi bi-qr-code me-1"></i>QR Code & Foto</h5>
                <?php if (!empty($unit['qr_value'])): ?>
                <div class="qr-img-wrap mb-3">
                    <img src="<?= htmlspecialchars($qrImagePath) ?>"
                         alt="QR Code Unit"
                         onerror="this.onerror=null;this.src='<?= htmlspecialchars($qrFallbackPath) ?>';"
                         style="width:100%;max-width:260px;min-width:180px;min-height:180px;border-radius:10px;border:1px solid #dbe3ef;background:#fff;object-fit:contain;padding:8px;">
                    <div style="font-size:0.7rem;color:#6b7280;margin-top:6px;word-break:break-all"><?= htmlspecialchars($unit['qr_value']) ?></div>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-2 no-print">
                        <a href="generate_qr.php?unit_id=<?= $unit['id_unit_barang'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-qr-code me-1"></i>Generate QR Manual</a>
                        <a href="index.php?page=print_unit_qr&id_unit_barang=<?= $unit['id_unit_barang'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer me-1"></i>Print QR Label</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="photo-placeholder mb-3"><i class="bi bi-qr-code-scan"></i></div>
                <p class="text-muted text-center small">QR belum tersedia</p>
                <?php endif; ?>

                <?php
                $latestPhoto = get_latest_product_photo($koneksi, $unit['id_produk'] ?? 0);
                 $photoPath = $latestPhoto['photo_path'] ?? null;
                ?>
                <?php if ($photoPath && file_exists(__DIR__ . '/../' . $photoPath)): ?>
                <h5 class="mt-3"><i class="bi bi-image me-1"></i>Foto Terkini</h5>
                 <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto Produk"
                     style="width:100%;border-radius:10px;border:1px solid #e2e8f0;object-fit:cover;max-height:200px">
                <div style="font-size:0.75rem;color:#6b7280;margin-top:4px">
                    <?= htmlspecialchars($latestPhoto['created_at'] ?? '') ?> &mdash; <?= htmlspecialchars($latestPhoto['event_type'] ?? '') ?>
                </div>
                <?php else: ?>
                <div class="photo-placeholder" style="aspect-ratio:16/9"><i class="bi bi-image" style="font-size:2rem"></i></div>
                <p class="text-muted text-center small mt-1">Belum ada foto dokumentasi</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== C. Aksi Tracking (bawah) ===== -->
    <?php if ($canManageInventory): ?>
    <div class="unit-info-section mb-3">
        <h5><i class="bi bi-lightning-charge me-1"></i>Aksi Tracking</h5>
        <div class="alert alert-light border mb-3 py-2" style="font-size:0.82rem">
            <i class="bi bi-info-circle me-1 text-primary"></i>
            Pindah antar gudang harus lewat <strong>mutasi resmi</strong>. Gunakan aksi cepat di bawah untuk operasi ringan tanpa berita acara.
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="index.php?page=mutasi_barang&action=form&gudang_asal_id=<?= intval($unit['id_gudang'] ?? 0) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left-right me-1"></i>Mutasi Resmi</a>
            <a href="index.php?page=serah_terima&action=form&gudang_asal_id=<?= intval($unit['id_gudang'] ?? 0) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-check me-1"></i>Serah Terima Formal</a>
        </div>

        <div class="row g-3">
            <?php if ($canMove): ?>
            <div class="col-md-6">
                <div class="action-card">
                    <h6><i class="bi bi-geo-alt me-1"></i>Pindah Lokasi</h6>
                    <form class="unit-action-form" data-url="action/update_unit_location.php" data-confirm="Pindahkan unit ini ke lokasi baru?">
                        <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                        <select name="id_gudang" class="form-select form-select-sm mb-2">
                            <option value="">-- Pilih Gudang --</option>
                            <?php foreach ($gudangRows as $g): ?>
                            <option value="<?= $g['id_gudang'] ?>" <?= ((string)$g['id_gudang'] === (string)($unit['id_gudang'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_gudang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="lokasi_custom" class="form-control form-control-sm mb-2" placeholder="Lokasi custom (misal: Ruang IT)">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-send me-1"></i>Pindahkan</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canAssign): ?>
            <div class="col-md-6">
                <div class="action-card">
                    <h6><i class="bi bi-person-check me-1"></i>Assign / Pinjam ke User</h6>
                    <form class="unit-action-form" data-url="action/assign_unit_barang.php" data-confirm="Assign / pinjam unit ini ke user terpilih?">
                        <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                        <select name="id_user" class="form-select form-select-sm mb-2">
                            <option value="">-- Pilih User --</option>
                            <?php foreach ($userRows as $u): ?>
                            <option value="<?= $u['id_user'] ?>"><?= htmlspecialchars($u['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-person-plus me-1"></i>Assign / Pinjam</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canMarkPerbaikan): ?>
            <div class="col-md-6">
                <div class="action-card" style="border-color:#fbbf24">
                    <h6 style="color:#b45309"><i class="bi bi-tools me-1"></i>Kirim Perbaikan</h6>
                    <form class="unit-action-form" data-url="action/update_unit_status.php" data-confirm="Kirim unit ini ke perbaikan?">
                        <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                        <input type="hidden" name="status" value="perbaikan">
                        <input type="text" name="vendor" class="form-control form-control-sm mb-2" placeholder="Nama vendor / teknisi (opsional)">
                        <input type="date" name="estimasi_selesai" class="form-control form-control-sm mb-2" title="Estimasi selesai perbaikan">
                        <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="Catatan tambahan">
                        <button type="submit" class="btn btn-sm btn-warning w-100"><i class="bi bi-tools me-1"></i>Kirim ke Perbaikan</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canSetTersedia): ?>
            <div class="col-md-6">
                <div class="action-card" style="border-color:#22c55e">
                    <h6 style="color:#15803d"><i class="bi bi-check-circle me-1"></i>Selesai Perbaikan / Set Tersedia</h6>
                    <form class="unit-action-form" data-url="action/update_unit_status.php" data-confirm="Set unit ini kembali menjadi tersedia?">
                        <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                        <input type="hidden" name="status" value="tersedia">
                        <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="Catatan (misal: Perbaikan selesai)">
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check-circle me-1"></i>Selesai / Set Tersedia</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canRelease): ?>
            <div class="col-md-4">
                <div class="action-card">
                    <h6><i class="bi bi-box-arrow-in-left me-1"></i>Release / Kembali</h6>
                    <p class="text-muted small mb-2">Kembalikan unit dari user, status menjadi tersedia.</p>
                    <form class="unit-action-form" data-url="action/release_unit_barang.php" data-confirm="Release / kembalikan unit ini menjadi tersedia?">
                        <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning w-100"><i class="bi bi-arrow-return-left me-1"></i>Release / Kembali</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canMarkRusak): ?>
            <div class="col-md-4">
                <div class="action-card" style="border-color:#ef4444">
                    <h6 style="color:#b91c1c"><i class="bi bi-exclamation-triangle me-1"></i>Tandai Rusak</h6>
                    <p class="text-muted small mb-2">Tandai unit sebagai rusak permanen.</p>
                    <form class="unit-action-form" data-url="action/update_unit_status.php" data-confirm="Tandai unit ini sebagai rusak?">
                        <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                        <input type="hidden" name="status" value="rusak">
                        <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="Keterangan kerusakan">
                        <button type="submit" class="btn btn-sm btn-danger w-100"><i class="bi bi-x-circle me-1"></i>Tandai Rusak</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!$canMove && !$canAssign && !$canRelease && !$canSetTersedia && !$canMarkPerbaikan && !$canMarkRusak): ?>
        <div class="alert alert-secondary py-2 mb-0">Tidak ada aksi cepat yang tersedia untuk status <strong><?= htmlspecialchars($currentStatusLabel) ?></strong>.</div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="unit-info-section mb-3">
        <div class="alert alert-secondary py-2 mb-0"><i class="bi bi-lock me-1"></i>Role <code>user</code> tidak dapat mengubah unit asset.</div>
    </div>
    <?php endif; ?>

    <!-- ===== Riwayat Unit ===== -->
    <div class="unit-info-section mb-3">
        <h5><i class="bi bi-clock-history me-1"></i>Riwayat Perubahan Unit</h5>
        <div class="history-table-wrap table-container overflowy">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem">
                <thead style="background:#f1f5f9;position:sticky;top:0">
                    <tr>
                        <th>No</th><th>Waktu</th><th>Tipe Aksi</th><th>Aktivitas</th>
                        <th>Status</th><th>Kondisi</th><th>Lokasi</th>
                        <th>User Terkait</th><th>Oleh</th><th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($historyRows as $row):
                    $activityValue = infer_tracking_activity_type($row, 'update');
                    $activityGroup = get_asset_unit_activity_group($activityValue);
                    $activityLabel = formatHistoryActivityDetail($activityValue);
                    $relatedUser = $row['nama_user_terkait'] ?? null;
                    $actorSnapshot = trim((string) ($row['actor_name_snapshot'] ?? ''));
                    $actorUser = $actorSnapshot !== '' ? $actorSnapshot : ($row['nama_user_actor'] ?? null);
                    $catatan = formatHistoryNoteDetail($row);
                    $isRepair = in_array(strtolower($activityValue ?? ''), ['perbaikan','mark_perbaikan','kirim_perbaikan','selesai_perbaikan']);
                ?>
                    <tr <?= $isRepair ? 'style="background:#fffbeb"' : '' ?>>
                        <td><?= $i++ ?></td>
                        <td style="white-space:nowrap"><?= htmlspecialchars($row['history_time'] ?? '-') ?></td>
                        <td><span class="badge bg-dark" style="font-size:0.68rem"><?= htmlspecialchars($activityGroup) ?></span></td>
                        <td><?= htmlspecialchars($activityLabel ?: '-') ?></td>
                        <td style="font-size:0.78rem"><?= htmlspecialchars(formatHistoryChange($row['status_sebelum'] ?? null, $row['status_sesudah'] ?? null, 'formatUnitStatusValue')) ?></td>
                        <td style="font-size:0.78rem"><?= htmlspecialchars(formatHistoryChange($row['kondisi_sebelum'] ?? null, $row['kondisi_sesudah'] ?? null)) ?></td>
                        <td style="font-size:0.78rem"><?= htmlspecialchars(formatHistoryChange($row['lokasi_sebelum'] ?? null, $row['lokasi_sesudah'] ?? null)) ?></td>
                        <td><?= htmlspecialchars($relatedUser ?: '-') ?></td>
                        <td><?= htmlspecialchars($actorUser ?: '-') ?></td>
                        <td style="max-width:200px;white-space:normal"><?= htmlspecialchars($catatan ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($historyRows)): ?>
                    <tr><td colspan="10" class="text-center py-3 text-muted">Belum ada riwayat unit.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $mutasiUnitRows = fetch_histori_logs($koneksi, ['ref_type' => 'mutasi', 'unit_barang_id' => $id_unit_barang], 25);
    $handoverUnitRows = fetch_histori_logs($koneksi, ['ref_type' => 'handover', 'unit_barang_id' => $id_unit_barang], 25);
    $unitLogRows = fetch_histori_logs($koneksi, ['ref_type' => 'unit', 'unit_barang_id' => $id_unit_barang], 25);
    ?>

    <!-- ===== Histori Formal ===== -->
    <div class="unit-info-section mb-3">
        <h5><i class="bi bi-journal-text me-1"></i>Histori Formal (Mutasi & Serah Terima)</h5>
        <div class="row g-3">
        <div class="col-lg-4">
            <div class="action-card" style="background:#fff;margin-bottom:0">
                <h6><i class="bi bi-arrow-left-right me-1"></i>Histori Mutasi</h6>
                <div class="table-container overflowy" style="max-height:260px">
                    <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem">
                        <thead style="background:#f1f5f9">
                            <tr><th>Waktu</th><th>Event</th><th>Deskripsi</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mutasiUnitRows)): foreach ($mutasiUnitRows as $row): ?>
                            <tr>
                                <td style="white-space:nowrap"><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['event_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['deskripsi'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-2">Belum ada histori mutasi resmi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="action-card" style="background:#fff;margin-bottom:0">
                <h6><i class="bi bi-file-earmark-check me-1"></i>Histori Serah Terima</h6>
                <div class="table-container overflowy" style="max-height:260px">
                    <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem">
                        <thead style="background:#f1f5f9">
                            <tr><th>Waktu</th><th>Event</th><th>Target</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($handoverUnitRows)): foreach ($handoverUnitRows as $row): ?>
                            <tr>
                                <td style="white-space:nowrap"><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['event_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['target_user_name_snapshot'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-2">Belum ada histori serah terima formal.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="action-card" style="background:#fff;margin-bottom:0">
                <h6><i class="bi bi-list-ul me-1"></i>Histori Log Unit</h6>
                <div class="table-container overflowy" style="max-height:260px">
                    <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem">
                        <thead style="background:#f1f5f9">
                            <tr><th>Waktu</th><th>Event</th><th>Oleh</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($unitLogRows)): foreach ($unitLogRows as $row): ?>
                            <tr>
                                <td style="white-space:nowrap"><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['event_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['user_name_snapshot'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-2">Belum ada histori log unit.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.unit-action-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var url = form.getAttribute('data-url');
        var confirmMessage = form.getAttribute('data-confirm');
        var formData = new FormData(form);

        if (url.indexOf('assign_unit_barang.php') !== -1 && !formData.get('id_user')) {
            alert('Pilih user tujuan terlebih dahulu.');
            return;
        }

        if (url.indexOf('update_unit_location.php') !== -1) {
            var gudang = (formData.get('id_gudang') || '').trim();
            var lokasiCustom = (formData.get('lokasi_custom') || '').trim();
            if (!gudang && !lokasiCustom) {
                alert('Pilih gudang tujuan atau isi lokasi custom.');
                return;
            }
        }

        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (data.success) {
                alert('Aksi berhasil. Halaman akan dimuat ulang.');
                window.location.reload();
            } else if (data.error) {
                alert('Terjadi kesalahan: ' + data.error);
            } else {
                alert('Respons tidak terduga.');
            }
        }).catch(function(err) {
            alert('Terjadi kesalahan jaringan: ' + err.message);
        });
    });
});
</script>
