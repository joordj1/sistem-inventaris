<?php
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function formatTrackingHistoryChange($before, $after) {
    $before = trim((string) ($before ?? ''));
    $after = trim((string) ($after ?? ''));
    $before = $before !== '' ? $before : '-';
    $after = $after !== '' ? $after : '-';

    return $before === $after ? $after : ($before . ' -> ' . $after);
}

function resolveTrackingActivityDisplay($row) {
    return get_tracking_activity_label(infer_tracking_activity_type($row, 'update'));
}

function resolveTrackingNoteDisplay($row, $scope) {
    $note = trim((string) ($row['catatan'] ?? ($row['note'] ?? '')));
    if ($note !== '') {
        return $note;
    }

    return build_tracking_note_fallback([
        'aktivitas' => $row['aktivitas'] ?? null,
        'activity_type' => $row['activity_type'] ?? null,
        'status_sebelum' => $row['status_sebelum'] ?? null,
        'status_sesudah' => $row['status_sesudah'] ?? null,
        'kondisi_sebelum' => $row['kondisi_sebelum'] ?? null,
        'kondisi_sesudah' => $row['kondisi_sesudah'] ?? null,
        'lokasi_sebelum' => $row['lokasi_sebelum'] ?? null,
        'lokasi_sesudah' => $row['lokasi_sesudah'] ?? null,
        'id_user_sebelum' => $row['id_user_sebelum'] ?? null,
        'id_user_sesudah' => $row['id_user_sesudah'] ?? null,
        'id_user_terkait' => $row['related_user_id'] ?? ($row['id_user'] ?? null),
    ], $scope);
}

function resolveTrackingActorDisplay($row, $joinedField) {
    $snapshot = trim((string) ($row['actor_name_snapshot'] ?? ''));
    if ($snapshot !== '') {
        return $snapshot;
    }

    $joinedName = trim((string) ($row[$joinedField] ?? ''));
    return $joinedName !== '' ? $joinedName : '-';
}

$canManageInventory = inventory_user_can_manage();

if (isset($_GET['id_produk']) || isset($_GET['kode_produk'])) {
    $id = null;
    $kode = null;
$query = "SELECT produk.id_produk, produk.kode_produk, produk.nama_produk, kategori.nama_kategori,
                 produk.deskripsi,
                 COALESCE(NULLIF(produk.harga_default, 0), produk.harga_satuan, 0) AS harga_default_view,
                 produk.jumlah_stok, produk.satuan,
                 (COALESCE(NULLIF(produk.harga_default, 0), produk.harga_satuan, 0) * produk.jumlah_stok) AS total_nilai_view,
                 produk.gambar_produk, produk.status, produk.kondisi, produk.lokasi_custom, produk.tersedia,
                 produk.last_tracked_at, produk.id_user, produk.dipinjam_oleh, produk.tanggal_pinjam, produk.tanggal_kembali,
                 produk.id_gudang, produk.tipe_barang,
                 gudang.nama_gudang
              FROM produk
              LEFT JOIN kategori ON produk.id_kategori = kategori.id_kategori
              LEFT JOIN StokGudang ON produk.id_produk = StokGudang.id_produk
              LEFT JOIN gudang ON StokGudang.id_gudang = gudang.id_gudang
              WHERE ";
    if (isset($_GET['id_produk'])) {
        $id = intval($_GET['id_produk']);
        $query .= " produk.id_produk = ?";
    } else {
        $kode = $koneksi->real_escape_string($_GET['kode_produk']);
        $query .= " produk.kode_produk = ?";
    }

    $stmt = $koneksi->prepare($query);
    if ($id !== null) {
        $stmt->bind_param("i", $id);
    } else {
        $stmt->bind_param("s", $kode);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    if (!$data) {
        echo "Data tidak ditemukan.";
        exit;
    }

    $current_pengguna = null;
    $borrowerId = intval($data['dipinjam_oleh'] ?? 0);
    if ($borrowerId < 1) {
        $borrowerId = intval($data['id_user'] ?? 0);
    }
    if ($borrowerId > 0) {
        $rsUser = $koneksi->query("SELECT nama FROM user WHERE id_user = " . $borrowerId);
        $userRow = $rsUser->fetch_assoc();
        $current_pengguna = $userRow['nama'] ?? null;
    }

    $isAsset = isset($data['tipe_barang']) && $data['tipe_barang'] === 'asset';

    $trackings = [];
    $unitList = [];
    $unitHistory = [];

    if ($isAsset) {
        $unitCodeExpr = "CONCAT('ID-', ub.id_unit_barang)";
        if (schema_has_column_now($koneksi, 'unit_barang', 'kode_unit')) {
            $unitCodeExpr = "COALESCE(NULLIF(TRIM(ub.kode_unit), ''), " . $unitCodeExpr . ")";
        }
        if (schema_has_column_now($koneksi, 'unit_barang', 'serial_number')) {
            $unitCodeExpr = "COALESCE(NULLIF(TRIM(ub.serial_number), ''), " . $unitCodeExpr . ")";
        }
        if (schema_has_column_now($koneksi, 'unit_barang', 'kode_qrcode')) {
            $unitCodeExpr = "COALESCE(NULLIF(TRIM(ub.kode_qrcode), ''), " . $unitCodeExpr . ")";
        }

        $unitResult = $koneksi->query("SELECT ub.id_unit_barang,
                             COALESCE(NULLIF(TRIM(ub.serial_number), ''), NULLIF(TRIM(ub.kode_qrcode), ''), CONCAT('ID-', ub.id_unit_barang)) AS kode_unit_tampil,
                             ub.status, ub.kondisi, ub.id_user, ub.lokasi_custom, ub.id_gudang, u.nama AS nama_user, g.nama_gudang
                                      FROM unit_barang ub
                                      LEFT JOIN user u ON ub.id_user = u.id_user
                                      LEFT JOIN gudang g ON ub.id_gudang = g.id_gudang
                                      WHERE ub.id_produk = " . intval($data['id_produk']));
        while ($u = $unitResult->fetch_assoc()) {
            $unitList[] = $u;
        }

        $historyActivityCol = schema_find_existing_column($koneksi, 'tracking_barang', ['aktivitas', 'activity_type']);
        $historyNoteCol = schema_find_existing_column($koneksi, 'tracking_barang', ['catatan', 'note']);
        $historyTimeCol = schema_find_existing_column($koneksi, 'tracking_barang', ['created_at', 'changed_at']);
        $historyActorUserCol = schema_find_existing_column($koneksi, 'tracking_barang', ['id_user_changed']);
        $historyRelatedUserCol = schema_find_existing_column($koneksi, 'tracking_barang', ['id_user_terkait', 'id_user_sesudah', 'id_user']);
        $historyActorSnapshotCol = schema_find_existing_column($koneksi, 'tracking_barang', ['actor_name_snapshot', 'user_name_snapshot']);
        $historyUnitIdCol = schema_find_existing_column($koneksi, 'tracking_barang', ['id_unit']);
        $historyFallbackUnitIdCol = schema_find_existing_column($koneksi, 'tracking_barang', ['id_unit_barang']);

        $historyQuery = "SELECT hr.*,
                                u_related.nama AS user_name,
                                u_actor.nama AS nama_user_actor,
                                " . ($historyActivityCol !== null ? "hr.`$historyActivityCol`" : "NULL") . " AS aktivitas,
                                " . ($historyNoteCol !== null ? "hr.`$historyNoteCol`" : "NULL") . " AS catatan,
                                " . ($historyTimeCol !== null ? "hr.`$historyTimeCol`" : "NULL") . " AS history_time,
                                " . ($historyActorSnapshotCol !== null ? "hr.`$historyActorSnapshotCol`" : "NULL") . " AS actor_name_snapshot,
                                " . ($historyRelatedUserCol !== null ? "hr.`$historyRelatedUserCol`" : "NULL") . " AS related_user_id,
                                " . ($historyUnitIdCol !== null ? "hr.`$historyUnitIdCol`" : "NULL") . " AS id_unit,
                                " . ($historyFallbackUnitIdCol !== null ? "hr.`$historyFallbackUnitIdCol`" : "NULL") . " AS id_unit_barang,
                                    $unitCodeExpr AS unit_serial
                         FROM tracking_barang hr";
        if ($historyRelatedUserCol !== null) {
            $historyQuery .= "\n                         LEFT JOIN user u_related ON hr.`$historyRelatedUserCol` = u_related.id_user";
        } else {
            $historyQuery .= "\n                         LEFT JOIN user u_related ON 1 = 0";
        }
        if ($historyActorUserCol !== null) {
            $historyQuery .= "\n                         LEFT JOIN user u_actor ON hr.`$historyActorUserCol` = u_actor.id_user";
        } else {
            $historyQuery .= "\n                         LEFT JOIN user u_actor ON 1 = 0";
        }
        if ($historyUnitIdCol !== null) {
            $historyQuery .= "\n                         LEFT JOIN unit_barang ub ON hr.`$historyUnitIdCol` = ub.id_unit_barang
                         WHERE hr.`$historyUnitIdCol` IS NOT NULL AND ub.id_produk = ?";
        } elseif ($historyFallbackUnitIdCol !== null) {
            $historyQuery .= "\n                         LEFT JOIN unit_barang ub ON hr.`$historyFallbackUnitIdCol` = ub.id_unit_barang
                         WHERE ub.id_produk = ?";
        } else {
            $historyQuery .= "\n                         LEFT JOIN unit_barang ub ON 1 = 0
                         WHERE hr.id_produk = ?";
        }
        if ($historyTimeCol !== null) {
            $historyQuery .= " ORDER BY hr.`$historyTimeCol` DESC";
        } else {
            $historyQuery .= " ORDER BY hr.id DESC";
        }
        $stmtHist = $koneksi->prepare($historyQuery);
        $stmtHist->bind_param('i', $data['id_produk']);
        $stmtHist->execute();
        $resultHist = $stmtHist->get_result();
        while ($h = $resultHist->fetch_assoc()) {
            $unitHistory[] = $h;
        }
    } else {
        $trackingActorSnapshotCol = schema_find_existing_column($koneksi, 'tracking_barang', ['actor_name_snapshot', 'actor_nama_snapshot', 'user_name_snapshot']);
        $sqlHistory = "SELECT tb.*, " . ($trackingActorSnapshotCol !== null ? "tb.`$trackingActorSnapshotCol`" : "NULL") . " AS actor_name_snapshot,
                      COALESCE(NULLIF(u.nama, ''), NULLIF(u.username, '')) AS changed_by,
                      uc.nama AS user_name FROM tracking_barang tb
                       LEFT JOIN user u ON tb.id_user_changed = u.id_user
                       LEFT JOIN user uc ON tb.id_user_sesudah = uc.id_user
                       WHERE tb.id_produk = ? ORDER BY tb.changed_at DESC";
        $stmtHist = $koneksi->prepare($sqlHistory);
        $stmtHist->bind_param('i', $data['id_produk']);
        $stmtHist->execute();
        $resultHist = $stmtHist->get_result();
        while ($h = $resultHist->fetch_assoc()) {
            $trackings[] = $h;
        }
    }

    $statusOptions = ['tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif'];
    $kondisiOptions = ['baik','rusak','diperbaiki','usang','lainnya'];
    $users = $koneksi->query("SELECT id_user, nama FROM user");
    $gudangs = $koneksi->query("SELECT id_gudang, nama_gudang FROM gudang");
    $inventoryNotes = fetch_inventory_notes($koneksi, ['id_produk' => $data['id_produk']], 50);

    ?>

    <style>
        .unit-checkbox-scroll {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            background-color: #fff;
        }

        .compact-table-container {
            max-height: 500px;
            overflow-y: auto;
        }

        .compact-table-container table {
            margin-bottom: 0;
        }
    </style>

    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <h3>Detail Produk dan Tracking</h3>
                <table class="table table-bordered table-hover">
                    <tr><td>Kode Produk</td><td><?= htmlspecialchars($data['kode_produk']) ?></td></tr>
                    <tr><td>Nama Produk</td><td><?= htmlspecialchars($data['nama_produk']) ?></td></tr>
                    <tr><td>Deskripsi</td><td><?= nl2br(htmlspecialchars($data['deskripsi'] ?? '-')) ?></td></tr>
                    <tr><td>Kategori</td><td><?= htmlspecialchars($data['nama_kategori'] ?? 'Tidak ada') ?></td></tr>
                    <tr><td>Gudang</td><td><?= htmlspecialchars($data['nama_gudang'] ?? 'Tidak ada') ?></td></tr>
                    <tr><td>Lokasi Custom</td><td><?= htmlspecialchars($data['lokasi_custom'] ?? '-') ?></td></tr>
                    <?php if ($isAsset):
                        $totalUnits = count($unitList);
                        $tersediaUnits = count(array_filter($unitList, function($u){
                            $s = strtolower(trim($u['status'] ?? ''));
                            return $s === 'tersedia';
                        }));
                        $dipakaiUnits = count(array_filter($unitList, function($u){
                            $s = strtolower(trim($u['status'] ?? ''));
                            return in_array($s, ['dipinjam','digunakan'], true);
                        }));
                        $rusakUnits = count(array_filter($unitList, function($u){
                            $s = strtolower(trim($u['status'] ?? ''));
                            return in_array($s, ['rusak','perbaikan'], true);
                        }));
                    ?>
                    <tr><td>Status</td><td><span class="badge bg-info">Asset (unit-level)</span></td></tr>
                    <tr><td>Kondisi</td><td><span class="badge bg-secondary">Dilihat per unit</span></td></tr>
                    <tr><td>Total Unit</td><td><?= $totalUnits ?></td></tr>
                    <tr><td>Unit Tersedia</td><td><?= $tersediaUnits ?></td></tr>
                    <tr><td>Unit Dipakai</td><td><?= $dipakaiUnits ?></td></tr>
                    <tr><td>Unit Rusak/Perbaikan</td><td><?= $rusakUnits ?></td></tr>
                    <?php else: ?>
                    <tr><td>Status</td><td><span class="badge bg-info"><?= htmlspecialchars($data['status']) ?></span></td></tr>
                    <tr><td>Kondisi</td><td><span class="badge bg-secondary"><?= htmlspecialchars($data['kondisi']) ?></span></td></tr>
                    <tr><td>Tersedia</td><td><?= $data['tersedia'] ? 'Ya' : 'Tidak' ?></td></tr>
                    <tr><td>Jumlah Stok</td><td><?= intval($data['jumlah_stok']) ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Harga Default</td><td><?= formatRupiah($data['harga_default_view'] ?? 0) ?></td></tr>
                    <tr><td>Total Nilai</td><td><?= formatRupiah($data['total_nilai_view'] ?? 0) ?></td></tr>
                    <tr><td>Dipinjam/Oleh</td><td><?= htmlspecialchars($current_pengguna ?? '-') ?></td></tr>
                    <tr><td>Tanggal Pinjam</td><td><?= htmlspecialchars($data['tanggal_pinjam'] ?? '-') ?></td></tr>
                    <tr><td>Tanggal Kembali</td><td><?= htmlspecialchars($data['tanggal_kembali'] ?? '-') ?></td></tr>
                    <tr><td>Update Terakhir</td><td><?= htmlspecialchars($data['last_tracked_at']) ?></td></tr>
                </table>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <h4>Aksi Tracking</h4>
                <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars((string) $_GET['error']) ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars((string) $_GET['success']) ?></div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['tracking_debug_insert'])): ?>
                <div class="alert alert-warning"><?= htmlspecialchars((string) $_SESSION['tracking_debug_insert']) ?></div>
                <?php unset($_SESSION['tracking_debug_insert']); ?>
                <?php endif; ?>
                <?php if (!$canManageInventory): ?>
                <div class="alert alert-secondary">Role `user` hanya dapat melihat detail dan riwayat tracking.</div>
                <?php else: ?>
                <form action="action/update_tracking.php" method="post" id="form-tracking-produk">
                    <input type="hidden" name="id_produk" value="<?= $data['id_produk'] ?>">

                    <?php if ($isAsset): ?>
                    <div class="alert alert-info">
                        <strong>Asset tracking dilakukan per unit.</strong><br>
                        Pilih satu atau beberapa unit dari daftar berikut. Untuk tindakan aset, sistem akan memperbarui unit_unit terpilih.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pilih Unit Asset (boleh lebih dari satu)</label>
                        <?php if (count($unitList) === 0): ?>
                            <div class="form-text">Belum ada unit untuk produk ini.</div>
                        <?php endif; ?>
                        <?php if (count($unitList) > 0): ?>
                        <div class="unit-checkbox-scroll">
                            <?php foreach ($unitList as $unit): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="id_unit[]" value="<?= $unit['id_unit_barang'] ?>" id="unit_<?= $unit['id_unit_barang'] ?>">
                                    <label class="form-check-label" for="unit_<?= $unit['id_unit_barang'] ?>"><?= htmlspecialchars($unit['kode_unit_tampil']) ?> (<?= htmlspecialchars($unit['status']) ?>)</label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <small class="text-muted">Jika lebih dari satu unit dipilih, aktivitas akan terekam untuk semuanya.</small>
                    </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Jenis Aktivitas</label>
                            <select name="jenis_aktivitas" id="activity_type" class="form-select" required>
                                <option value="">--Pilih--</option>
                                <option value="dipinjam">Dipinjam</option>
                                <option value="dikembalikan">Dikembalikan</option>
                                <option value="perbaikan">Perbaikan</option>
                                <option value="rusak">Rusak</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status Baru</label>
                            <select name="status" class="form-select" required>
                                <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?= $opt ?>" <?= $data['status'] == $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kondisi Baru</label>
                            <select name="kondisi" class="form-select" required>
                                <?php foreach ($kondisiOptions as $opt): ?>
                                <option value="<?= $opt ?>" <?= $data['kondisi'] == $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3" id="field-tujuan-wrap">
                            <label class="form-label" id="label-tujuan">Tujuan / Penerima</label>
                            <select name="id_gudang" class="form-select">
                                <option value="">--Tidak Diubah--</option>
                                <?php while ($g = $gudangs->fetch_assoc()): ?>
                                <option value="<?= $g['id_gudang'] ?>" <?= $data['id_gudang'] == $g['id_gudang'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_gudang']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="field-lokasi-vendor-wrap">
                            <label class="form-label" id="label-lokasi-vendor">Vendor / Lokasi</label>
                            <input type="text" name="lokasi" value="<?= htmlspecialchars($data['lokasi_custom'] ?? '') ?>" class="form-control" placeholder="Contoh: Vendor PT Maju atau Ruang IT" />
                        </div>
                        <div class="col-md-4" id="field-user-wrap">
                            <label class="form-label" id="label-user">User Terkait</label>
                            <select name="user_terkait" class="form-select">
                                <option value="">--Pilih User--</option>
                                <?php while ($u = $users->fetch_assoc()): ?>
                                <option value="<?= $u['id_user'] ?>" <?= $data['id_user'] == $u['id_user'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="field-catatan-wrap">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="catatan" class="form-control" placeholder="Catatan aktivitas" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan Tracking</button>
                        <a href="index.php?page=data_produk" class="btn btn-secondary">Kembali</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAsset): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h4>Daftar Unit Barang</h4>
                <div class="table-responsive compact-table-container">
                <table class="table table-sm table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Unit</th>
                            <th>Status</th>
                            <th>Kondisi</th>
                            <th>Lokasi</th>
                            <th>Assigned User</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($unitList as $index => $unitRow): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($unitRow['kode_unit_tampil']) ?></td>
                            <td><?= htmlspecialchars($unitRow['status']) ?></td>
                            <td><?= htmlspecialchars($unitRow['kondisi']) ?></td>
                            <td><?= htmlspecialchars(trim($unitRow['lokasi_custom'] ?? '') !== '' ? $unitRow['lokasi_custom'] : (trim($unitRow['nama_gudang'] ?? '') !== '' ? $unitRow['nama_gudang'] : '-')) ?></td>
                            <td><?= htmlspecialchars(trim($unitRow['nama_user'] ?? '') !== '' ? $unitRow['nama_user'] : '-') ?></td>
                            <td><a href="index.php?page=unit_barang_info&id_unit_barang=<?= $unitRow['id_unit_barang'] ?>" class="btn btn-sm btn-primary">Lihat Unit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <h4>Catatan Barang & Transaksi</h4>
                <?php if ($canManageInventory): ?>
                <form action="action/simpan_catatan.php" method="post" class="border rounded p-3 mb-3 bg-light">
                    <input type="hidden" name="id_produk" value="<?= intval($data['id_produk']) ?>">
                    <input type="hidden" name="id_gudang" value="<?= intval($data['id_gudang'] ?? 0) ?>">
                    <input type="hidden" name="tipe_target" value="produk">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Kategori Catatan</label>
                            <select name="kategori_catatan" class="form-select" required>
                                <option value="umum">Umum</option>
                                <option value="kerusakan">Kerusakan</option>
                                <option value="selisih">Selisih</option>
                                <option value="servis">Servis</option>
                                <option value="bug">Bug</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Judul</label>
                            <input type="text" name="judul" class="form-control" placeholder="Contoh: Selisih stok opname">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="catatan" class="form-control" placeholder="Isi catatan barang atau transaksi" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-outline-primary">Simpan Catatan</button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="table-responsive compact-table-container">
                <table class="table table-sm table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Target</th>
                            <th>Judul</th>
                            <th>Catatan</th>
                            <th>Ref. Transaksi</th>
                            <th>Pembuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inventoryNotes)): ?>
                            <?php foreach ($inventoryNotes as $index => $noteRow): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($noteRow['created_at'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(ucfirst($noteRow['kategori_catatan'] ?? 'umum')) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($noteRow['tipe_target'] ?? 'produk')) ?></td>
                                    <td><?= htmlspecialchars($noteRow['judul'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($noteRow['catatan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($noteRow['no_invoice'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($noteRow['nama_pembuat'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center">Belum ada catatan barang/transaksi.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <h4>Riwayat Tracking</h4>
                <div class="table-responsive compact-table-container">
                <table class="table table-sm table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Unit</th>
                            <th>Aktivitas</th>
                            <th>Status</th>
                            <th>Kondisi</th>
                            <th>Lokasi</th>
                            <th>User</th>
                            <th>Oleh</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($isAsset ? count($unitHistory) > 0 : count($trackings) > 0): ?>
                            <?php $historyData = $isAsset ? $unitHistory : $trackings; ?>
                            <?php foreach ($historyData as $index => $t): ?>
                                <?php
                                $activityLabel = resolveTrackingActivityDisplay($t);
                                $displayNote = resolveTrackingNoteDisplay($t, $isAsset ? 'unit' : 'barang');
                                $displayActor = resolveTrackingActorDisplay($t, $isAsset ? 'nama_user_actor' : 'changed_by');
                                $displayUser = trim((string) ($t['user_name'] ?? '')) !== '' ? $t['user_name'] : '-';
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($isAsset ? ($t['history_time'] ?? ($t['created_at'] ?? '-')) : ($t['changed_at'] ?? '-')) ?></td>
                                    <td>
                                        <?php
                                        if (!empty($t['unit_serial'])) {
                                            echo htmlspecialchars($t['unit_serial']);
                                        } elseif (isset($t['id_unit_barang']) && $t['id_unit_barang'] !== null && $t['id_unit_barang'] !== '') {
                                            echo 'ID:' . intval($t['id_unit_barang']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($activityLabel) ?></td>
                                    <td><?= htmlspecialchars(($t['status_sebelum'] ?? '-') . ' → ' . ($t['status_sesudah'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars(($t['kondisi_sebelum'] ?? '-') . ' → ' . ($t['kondisi_sesudah'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars(($t['lokasi_sebelum'] ?? '-') . ' → ' . ($t['lokasi_sesudah'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars($displayUser) ?></td>
                                    <td><?= htmlspecialchars($displayActor) ?></td>
                                    <td><?= htmlspecialchars($displayNote) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center">Belum ada riwayat tracking</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const activityEl = document.getElementById('activity_type');
        const tujuanWrapEl = document.getElementById('field-tujuan-wrap');
        const tujuanLabelEl = document.getElementById('label-tujuan');
        const gudangSelectEl = tujuanWrapEl ? tujuanWrapEl.querySelector('select[name="id_gudang"]') : null;
        const userWrapEl = document.getElementById('field-user-wrap');
        const userLabelEl = document.getElementById('label-user');
        const userSelectEl = userWrapEl ? userWrapEl.querySelector('select[name="user_terkait"]') : null;
        const lokasiVendorWrapEl = document.getElementById('field-lokasi-vendor-wrap');
        const lokasiVendorLabelEl = document.getElementById('label-lokasi-vendor');
        const lokasiVendorInputEl = lokasiVendorWrapEl ? lokasiVendorWrapEl.querySelector('input[name="lokasi"]') : null;
        const catatanWrapEl = document.getElementById('field-catatan-wrap');
        const catatanInputEl = catatanWrapEl ? catatanWrapEl.querySelector('input[name="catatan"]') : null;

        if (!activityEl || !tujuanWrapEl || !tujuanLabelEl || !gudangSelectEl || !userWrapEl || !userLabelEl || !userSelectEl || !lokasiVendorWrapEl || !lokasiVendorLabelEl || !lokasiVendorInputEl || !catatanWrapEl || !catatanInputEl) {
            return;
        }

        function showEl(el) {
            el.style.display = '';
        }

        function hideEl(el) {
            el.style.display = 'none';
        }

        function resetField(field) {
            if (field.tagName === 'SELECT') {
                field.value = '';
            } else {
                field.value = '';
            }
            field.required = false;
            field.disabled = true;
        }

        function enableField(field, required) {
            field.disabled = false;
            field.required = !!required;
        }

        function updateTujuanVisibility() {
            const value = String(activityEl.value || '').trim().toLowerCase();

            showEl(tujuanWrapEl);
            showEl(userWrapEl);
            showEl(lokasiVendorWrapEl);
            showEl(catatanWrapEl);

            resetField(gudangSelectEl);
            resetField(userSelectEl);
            resetField(lokasiVendorInputEl);
            resetField(catatanInputEl);

            tujuanLabelEl.textContent = 'Tujuan / Penerima';
            userLabelEl.textContent = 'User Terkait';
            lokasiVendorLabelEl.textContent = 'Vendor / Lokasi';

            if (value === 'dipinjam') {
                enableField(userSelectEl, true);
                hideEl(tujuanWrapEl);
                hideEl(lokasiVendorWrapEl);
                enableField(catatanInputEl, false);
                userLabelEl.textContent = 'User Terkait (Peminjam)';
                return;
            }

            if (value === 'perbaikan') {
                enableField(lokasiVendorInputEl, true);
                hideEl(tujuanWrapEl);
                hideEl(userWrapEl);
                enableField(catatanInputEl, false);
                lokasiVendorLabelEl.textContent = 'Vendor atau Lokasi Perbaikan';
                return;
            }

            if (value === 'rusak') {
                enableField(catatanInputEl, true);
                hideEl(tujuanWrapEl);
                hideEl(userWrapEl);
                hideEl(lokasiVendorWrapEl);
                return;
            }

            if (value === 'dikembalikan') {
                enableField(catatanInputEl, false);
                hideEl(tujuanWrapEl);
                hideEl(userWrapEl);
                hideEl(lokasiVendorWrapEl);
                return;
            }
        }

        activityEl.addEventListener('change', updateTujuanVisibility);
        updateTujuanVisibility();
    })();
    </script>

    <?php
} else {
    echo "ID produk tidak tersedia.";
}
?>
