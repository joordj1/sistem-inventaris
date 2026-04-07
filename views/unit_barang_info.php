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

if ($qrColumn !== null && empty($unit['qr_value'])) {
    $qrValue = build_asset_qr_value($unit['id_unit_barang']);
    $updateStmt = $koneksi->prepare("UPDATE unit_barang SET `$qrColumn` = ? WHERE id_unit_barang = ?");
    if ($updateStmt) {
        $updateStmt->bind_param('si', $qrValue, $unit['id_unit_barang']);
        $updateStmt->execute();
        $unit['qr_value'] = $qrValue;
    }
}

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

<div class="container">
    <h3>Detail Unit Asset</h3>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tbody>
                            <tr><th>Kode Unit</th><td><?= htmlspecialchars(valueOrDash($unit['kode_unit'] ?? null)) ?></td></tr>
                            <tr><th>Nama Produk</th><td><?= htmlspecialchars(valueOrDash($unit['nama_produk'] ?? null)) ?></td></tr>
                            <tr><th>Kode Produk</th><td><?= htmlspecialchars(valueOrDash($unit['kode_produk'] ?? null)) ?></td></tr>
                            <tr><th>Status</th><td><span class="badge <?= htmlspecialchars($currentStatusBadge) ?>"><?= htmlspecialchars($currentStatusLabel) ?></span></td></tr>
                            <tr><th>Kondisi</th><td><span class="badge bg-secondary"><?= htmlspecialchars(valueOrDash($unit['kondisi'] ?? null)) ?></span></td></tr>
                            <tr><th>Gudang Saat Ini</th><td><?= htmlspecialchars(valueOrDash($gudangSaatIni !== '' ? $gudangSaatIni : null)) ?></td></tr>
                            <tr><th>Lokasi Detail</th><td><?= htmlspecialchars(valueOrDash($lokasiDetail !== '' ? $lokasiDetail : null)) ?></td></tr>
                            <tr><th>Lokasi Custom</th><td><?= htmlspecialchars(valueOrDash($lokasiCustom !== '' ? $lokasiCustom : null)) ?></td></tr>
                            <tr><th>Lokasi Saat Ini</th><td><strong><?= htmlspecialchars(valueOrDash($displayLokasi)) ?></strong></td></tr>
                            <tr><th>User Saat Ini</th><td><?= htmlspecialchars(valueOrDash($unit['nama_user'] ?? null)) ?></td></tr>
                            <tr><th>QR Code Value</th><td><?= htmlspecialchars(valueOrDash($unit['qr_value'] ?? null)) ?></td></tr>
                            <tr><th>QR Code</th><td>
                                <?php if (!empty($unit['qr_value'])): ?>
                                    <img src="https://chart.googleapis.com/chart?cht=qr&chs=260x260&chl=<?= urlencode($unit['qr_value']) ?>&choe=UTF-8" alt="QR Code" />
                                    <br>
                                    <a href="index.php?page=print_unit_qr&id_unit_barang=<?= $unit['id_unit_barang'] ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">Print QR Label</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars(valueOrDash($unit['created_at'] ?? null)) ?></td></tr>
                            <tr><th>Updated At</th><td><?= htmlspecialchars(valueOrDash($unit['updated_at'] ?? null)) ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <p class="mb-2"><strong>Quick Actions</strong></p>
                        <div class="alert alert-secondary py-2">
                            <div><strong>Status aktif:</strong> <?= htmlspecialchars($currentStatusLabel) ?></div>
                            <div><strong>Aksi valid:</strong> <?= htmlspecialchars(!empty($availableActions) ? implode(', ', $availableActions) : 'Tidak ada aksi cepat yang tersedia') ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <a href="index.php?page=mutasi_barang&action=form&gudang_asal_id=<?= intval($unit['id_gudang'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">Mutasi Resmi</a>
                            <a href="index.php?page=serah_terima&action=form&gudang_asal_id=<?= intval($unit['id_gudang'] ?? 0) ?>" class="btn btn-sm btn-outline-success">Serah Terima Formal</a>
                        </div>
                        <div class="alert alert-light border">
                            Pindah antar gudang harus lewat mutasi resmi. Assign/release cepat tetap ada untuk operasi ringan tanpa berita acara.
                        </div>
                        <?php if (!$canManageInventory): ?>
                        <div class="alert alert-light border">Role `viewer` tidak dapat mengubah unit asset.</div>
                        <?php else: ?>
                        <?php if ($canMove): ?>
                        <form class="unit-action-form" data-url="action/update_unit_location.php" data-confirm="Pindahkan unit ini ke lokasi baru?">
                            <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Pindah ke Gudang</label>
                                <select name="id_gudang" class="form-select" id="action_gudang">
                                    <option value="">--Pilih Gudang--</option>
                                    <?php foreach ($gudangRows as $g): ?>
                                        <option value="<?= htmlspecialchars($g['id_gudang']) ?>" <?= ((string) $g['id_gudang'] === (string) ($unit['id_gudang'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_gudang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Atau Lokasi Kustom</label>
                                <input type="text" name="lokasi_custom" class="form-control" value="<?= htmlspecialchars($unit['lokasi_custom'] ?? '') ?>" placeholder="Contoh: Ruang IT">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary mb-2">Pindahkan</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($canAssign): ?>
                        <form class="unit-action-form" data-url="action/assign_unit_barang.php" data-confirm="Assign / pinjam unit ini ke user terpilih?">
                            <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Assign ke User</label>
                                <select name="id_user" class="form-select">
                                    <option value="">--Pilih User--</option>
                                    <?php foreach ($userRows as $u): ?>
                                        <option value="<?= htmlspecialchars($u['id_user']) ?>"><?= htmlspecialchars($u['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-success mb-2">Pinjam / Assign</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($canRelease): ?>
                        <form class="unit-action-form" data-url="action/release_unit_barang.php" data-confirm="Release / kembalikan unit ini menjadi tersedia?">
                            <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning mb-2">Kembali / Release</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($canSetTersedia): ?>
                        <form class="unit-action-form" data-url="action/update_unit_status.php" data-confirm="Set unit ini kembali menjadi tersedia?">
                            <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                            <input type="hidden" name="status" value="tersedia">
                            <button type="submit" class="btn btn-sm btn-outline-success mb-2">Set Tersedia</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($canMarkPerbaikan): ?>
                        <form class="unit-action-form" data-url="action/update_unit_status.php" data-confirm="Ubah status unit ini menjadi perbaikan?">
                            <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                            <input type="hidden" name="status" value="perbaikan">
                            <button type="submit" class="btn btn-sm btn-secondary mb-2">Mark Perbaikan</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($canMarkRusak): ?>
                        <form class="unit-action-form" data-url="action/update_unit_status.php" data-confirm="Tandai unit ini sebagai rusak?">
                            <input type="hidden" name="id_unit_barang" value="<?= $unit['id_unit_barang'] ?>">
                            <input type="hidden" name="status" value="rusak">
                            <button type="submit" class="btn btn-sm btn-danger">Mark Rusak</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Riwayat Unit</div>
        <div class="card-body table-container overflowy">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Waktu</th>
                        <th>Tipe Aksi</th>
                        <th>Aktivitas</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Lokasi</th>
                        <th>User Terkait</th>
                        <th>Oleh</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; ?>
                <?php foreach ($historyRows as $row): ?>
                    <?php
                    $activityValue = $row['aktivitas'] ?? null;
                    $activityGroup = get_asset_unit_activity_group($activityValue);
                    $activityLabel = formatHistoryActivityDetail($activityValue);
                    $relatedUser = $row['nama_user_terkait'] ?? null;
                    $actorUser = $row['nama_user_actor'] ?? null;
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars(valueOrDash($row['history_time'] ?? null)) ?></td>
                        <td><span class="badge bg-dark"><?= htmlspecialchars($activityGroup) ?></span></td>
                        <td><?= htmlspecialchars(valueOrDash($activityLabel)) ?></td>
                        <td><?= htmlspecialchars(formatHistoryChange($row['status_sebelum'] ?? null, $row['status_sesudah'] ?? null, 'formatUnitStatusValue')) ?></td>
                        <td><?= htmlspecialchars(formatHistoryChange($row['kondisi_sebelum'] ?? null, $row['kondisi_sesudah'] ?? null)) ?></td>
                        <td><?= htmlspecialchars(formatHistoryChange($row['lokasi_sebelum'] ?? null, $row['lokasi_sesudah'] ?? null)) ?></td>
                        <td><?= htmlspecialchars(valueOrDash($relatedUser)) ?></td>
                        <td><?= htmlspecialchars(valueOrDash($actorUser)) ?></td>
                        <td><?= htmlspecialchars(valueOrDash($row['catatan'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($historyRows)): ?>
                    <tr>
                        <td colspan="10" class="text-center">Belum ada riwayat unit.</td>
                    </tr>
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

    <div class="row g-4 mt-2">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Histori Mutasi</div>
                <div class="card-body table-container overflowy" style="max-height: 320px;">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Waktu</th><th>Event</th><th>Deskripsi</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mutasiUnitRows)): foreach ($mutasiUnitRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['event_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['deskripsi'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center">Belum ada histori mutasi resmi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Histori Serah Terima</div>
                <div class="card-body table-container overflowy" style="max-height: 320px;">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Waktu</th><th>Event</th><th>Target</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($handoverUnitRows)): foreach ($handoverUnitRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['event_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['target_user_name_snapshot'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center">Belum ada histori serah terima formal.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Histori Log Unit</div>
                <div class="card-body table-container overflowy" style="max-height: 320px;">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Waktu</th><th>Event</th><th>Oleh</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($unitLogRows)): foreach ($unitLogRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['event_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['user_name_snapshot'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center">Belum ada histori log unit.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
