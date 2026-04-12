<?php
$canManageInventory = inventory_user_can_manage();
if (!$canManageInventory) {
    echo '<div class="alert alert-warning">Role user hanya dapat melihat data serah terima.</div>';
    return;
}

$rawErrorMessage = trim((string) ($_GET['error'] ?? ''));
$errorAlertClass = 'alert alert-danger';
$displayErrorMessage = $rawErrorMessage;
if ($rawErrorMessage !== '') {
    $normalizedError = strtolower($rawErrorMessage);
    if (strpos($normalizedError, 'skema') !== false || strpos($normalizedError, 'migration priority 2') !== false) {
        $errorAlertClass = 'alert alert-info';
        $displayErrorMessage = 'Beberapa fitur dokumentasi belum aktif, namun proses tetap dapat dilanjutkan.';
    }
}

$documentationNotice = !schema_table_exists_now($koneksi, 'dokumen_transaksi');

if (!function_exists('collect_inventory_form_filter_options')) {
    function collect_inventory_form_filter_options($rows, $field) {
        $options = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $options[$value] = $value;
        }

        natcasesort($options);
        return $options;
    }
}

if (!function_exists('build_inventory_form_search_text')) {
    function build_inventory_form_search_text($parts) {
        $parts = array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $parts), function ($value) {
            return $value !== '';
        });

        return implode(' ', $parts);
    }
}

$selectedGudangAsal = isset($_GET['gudang_asal_id']) && $_GET['gudang_asal_id'] !== '' ? intval($_GET['gudang_asal_id']) : 0;
$gudangRows = [];
$selectedGudangName = '';
$gudangResult = $koneksi->query("SELECT id_gudang, nama_gudang FROM gudang ORDER BY nama_gudang ASC");
while ($gudangResult && ($row = $gudangResult->fetch_assoc())) {
    $gudangRows[] = $row;
    if ($selectedGudangAsal === 0) {
        $selectedGudangAsal = intval($row['id_gudang']);
    }
}

foreach ($gudangRows as $gudangRow) {
    if ((int) $gudangRow['id_gudang'] === $selectedGudangAsal) {
        $selectedGudangName = trim((string) ($gudangRow['nama_gudang'] ?? ''));
        break;
    }
}

$userRows = get_active_user_rows($koneksi);
$currentUserName = get_current_user_name($koneksi) ?? '';
$hasKategoriTable = schema_table_exists_now($koneksi, 'kategori') && schema_has_column_now($koneksi, 'produk', 'id_kategori');
$hasLokasiTable = schema_table_exists_now($koneksi, 'lokasi') && schema_has_column_now($koneksi, 'unit_barang', 'id_lokasi');

$consumableRows = [];
if ($selectedGudangAsal > 0) {
    $sql = "SELECT p.id_produk, p.kode_produk, p.nama_produk, p.satuan, p.kondisi, p.status, sg.jumlah_stok,
                   g.nama_gudang";
    $sql .= $hasKategoriTable
        ? ", COALESCE(k.nama_kategori, 'Tanpa kategori') AS nama_kategori"
        : ", 'Tanpa kategori' AS nama_kategori";
    $sql .= "
            FROM stokgudang sg
            INNER JOIN produk p ON sg.id_produk = p.id_produk
            LEFT JOIN gudang g ON sg.id_gudang = g.id_gudang";
    if ($hasKategoriTable) {
        $sql .= "
            LEFT JOIN kategori k ON p.id_kategori = k.id_kategori";
    }
    $sql .= "
            WHERE sg.id_gudang = " . intval($selectedGudangAsal) . "
              AND COALESCE(p.tipe_barang, 'consumable') = 'consumable'
              AND sg.jumlah_stok > 0
            ORDER BY p.nama_produk ASC";
    $result = $koneksi->query($sql);
    while ($result && ($row = $result->fetch_assoc())) {
        $row['nama_kategori'] = trim((string) ($row['nama_kategori'] ?? '')) !== '' ? $row['nama_kategori'] : 'Tanpa kategori';
        $row['lokasi_ringkas'] = trim((string) ($row['nama_gudang'] ?? '')) !== '' ? $row['nama_gudang'] : ($selectedGudangName !== '' ? $selectedGudangName : '-');
        $row['status_display'] = trim((string) ($row['status'] ?? '')) !== '' ? ucfirst(trim((string) $row['status'])) : 'Tersedia';
        $row['search_text'] = build_inventory_form_search_text([
            $row['kode_produk'] ?? '',
            $row['nama_produk'] ?? '',
            $row['nama_kategori'],
            $row['status_display'],
            $row['lokasi_ringkas'],
        ]);
        $consumableRows[] = $row;
    }
}

$assetRows = [];
if ($selectedGudangAsal > 0 && schema_table_exists_now($koneksi, 'unit_barang')) {
    $assetSelect = [
        'ub.id_unit_barang',
        schema_has_column_now($koneksi, 'unit_barang', 'kode_unit') ? 'ub.kode_unit' : 'NULL AS kode_unit',
        'ub.status',
        'ub.kondisi',
        'ub.id_gudang',
        'g.nama_gudang',
        'p.id_produk',
        'p.kode_produk',
        'p.nama_produk',
        $hasKategoriTable ? "COALESCE(k.nama_kategori, 'Tanpa kategori') AS nama_kategori" : "'Tanpa kategori' AS nama_kategori",
        schema_has_column_now($koneksi, 'unit_barang', 'lokasi_custom') ? 'ub.lokasi_custom' : 'NULL AS lokasi_custom',
        schema_has_column_now($koneksi, 'unit_barang', 'id_lokasi') ? 'ub.id_lokasi' : 'NULL AS id_lokasi',
        $hasLokasiTable ? 'l.nama_lokasi' : 'NULL AS nama_lokasi',
    ];

    $sql = "SELECT " . implode(",\n                   ", $assetSelect) . "
            FROM unit_barang ub
            INNER JOIN produk p ON ub.id_produk = p.id_produk
            LEFT JOIN gudang g ON ub.id_gudang = g.id_gudang";
    if ($hasKategoriTable) {
        $sql .= "
            LEFT JOIN kategori k ON p.id_kategori = k.id_kategori";
    }
    if ($hasLokasiTable) {
        $sql .= "
            LEFT JOIN lokasi l ON ub.id_lokasi = l.id_lokasi";
    }
    $sql .= "
            WHERE ub.id_gudang = " . intval($selectedGudangAsal) . "
              AND COALESCE(p.tipe_barang, 'consumable') = 'asset'
              AND COALESCE(ub.id_user, 0) = 0
              AND LOWER(TRIM(COALESCE(ub.status, 'tersedia'))) = 'tersedia'
            ORDER BY p.nama_produk ASC, ub.id_unit_barang DESC";
    $result = $koneksi->query($sql);
    while ($result && ($row = $result->fetch_assoc())) {
        $row['nama_kategori'] = trim((string) ($row['nama_kategori'] ?? '')) !== '' ? $row['nama_kategori'] : 'Tanpa kategori';
        $row['lokasi_ringkas'] = get_asset_unit_location_text($koneksi, $row) ?? (trim((string) ($row['nama_gudang'] ?? '')) !== '' ? $row['nama_gudang'] : '-');
        $row['status_display'] = get_asset_unit_status_label($row['status'] ?? null);
        $row['search_text'] = build_inventory_form_search_text([
            $row['kode_unit'] ?? '',
            $row['kode_produk'] ?? '',
            $row['nama_produk'] ?? '',
            $row['nama_kategori'],
            $row['lokasi_ringkas'],
            $row['status_display'],
        ]);
        $assetRows[] = $row;
    }
}

$consumableCategories = collect_inventory_form_filter_options($consumableRows, 'nama_kategori');
$assetCategories = collect_inventory_form_filter_options($assetRows, 'nama_kategori');
$assetLocations = collect_inventory_form_filter_options($assetRows, 'lokasi_ringkas');
?>

<style>
    .selection-subtext { font-size: 0.9rem; color: #6c757d; }
    .selection-row-clickable tbody tr { cursor: pointer; }
    .selection-row-clickable tbody tr td { vertical-align: middle; }
    .selection-meta { font-size: 0.86rem; color: #6c757d; }
    .selection-badge-group { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.35rem; }
    .st-form-grid .form-label { font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; }
    .st-form-grid .form-control,
    .st-form-grid .form-select,
    .st-form-grid textarea { font-size: 0.88rem; }
    .st-form-grid textarea[name="catatan"] { min-height: 110px; max-height: 110px; resize: none; }
    .st-upload-panel {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 0.95rem;
        background: #fcfdff;
        height: 100%;
    }
    .st-upload-title { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.7rem; }
    .st-file-meta { font-size: 0.78rem; min-height: 2.1em; }
    .st-preview-frame {
        width: 100%;
        max-width: 100%;
        min-height: 170px;
        max-height: 190px;
        border: 1px dashed #ced4da;
        border-radius: 0.45rem;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .st-preview-frame img {
        max-width: 100%;
        max-height: 180px;
        object-fit: contain;
        display: block;
    }
    @media (max-width: 991.98px) {
        .st-upload-panel { height: auto; }
        .st-preview-frame { min-height: 150px; max-height: 170px; }
        .st-preview-frame img { max-height: 160px; }
    }
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Form Serah Terima Barang</h2>
            <p class="text-muted mb-0">Pilih barang lebih cepat lewat pencarian, filter kategori, dan aksi massal.</p>
        </div>
        <a href="index.php?page=serah_terima" class="btn btn-secondary">Kembali</a>
    </div>

    <?php if ($displayErrorMessage !== ''): ?>
    <div class="<?= htmlspecialchars($errorAlertClass) ?>"><?= htmlspecialchars($displayErrorMessage) ?></div>
    <?php endif; ?>

    <?php if ($documentationNotice): ?>
    <div class="alert alert-info">Beberapa fitur dokumentasi belum aktif, namun proses tetap dapat dilanjutkan.</div>
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
                <div class="row g-4 st-form-grid">
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Serah Terima</label>
                                <input type="datetime-local" name="tanggal_serah_terima" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gudang Asal</label>
                                <select name="gudang_asal_id" class="form-select" required>
                                    <?php foreach ($gudangRows as $gudang): ?>
                                    <option value="<?= intval($gudang['id_gudang']) ?>" <?= (string) $selectedGudangAsal === (string) $gudang['id_gudang'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($gudang['nama_gudang']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jenis Tujuan</label>
                                <select name="jenis_tujuan" class="form-select" required>
                                    <option value="user">User</option>
                                    <option value="lokasi">Lokasi</option>
                                    <option value="departemen">Departemen</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lokasi / Departemen Tujuan</label>
                                <input type="text" name="lokasi_tujuan" class="form-control" placeholder="Opsional, terutama untuk tujuan lokasi / departemen">
                            </div>
                            <div class="col-md-6">
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
                            <div class="col-md-6">
                                <label class="form-label">Nama Penyerah Snapshot</label>
                                <input type="text" name="pihak_penyerah_nama" class="form-control" value="<?= htmlspecialchars($currentUserName) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Penerima (User)</label>
                                <select name="pihak_penerima_user_id" class="form-select">
                                    <option value="">Pilih User jika tujuan user</option>
                                    <?php foreach ($userRows as $user): ?>
                                    <option value="<?= intval($user['id_user']) ?>"><?= htmlspecialchars($user['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Penerima Snapshot</label>
                                <input type="text" name="pihak_penerima_nama" class="form-control" placeholder="Nama penerima / departemen" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan</label>
                                <textarea name="catatan" class="form-control" placeholder="Nomor BA, tujuan pemakaian, instruksi pengembalian, dst."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="st-upload-panel">
                            <div class="st-upload-title">Dokumentasi Serah Terima</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Dokumen Serah Terima</label>
                                    <input type="file" name="dokumen_serah_terima" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" data-file-preview-input data-preview-name-id="serah-dokumen-name" data-preview-image-id="serah-dokumen-image">
                                    <div id="serah-dokumen-name" class="text-muted mt-2 st-file-meta">Belum ada file dipilih.</div>
                                    <div class="st-preview-frame mt-2">
                                        <img id="serah-dokumen-image" class="d-none" alt="Preview dokumen serah terima" src="">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Foto Dokumentasi</label>
                                    <input type="file" name="foto_dokumentasi_serah_terima" class="form-control" accept=".jpg,.jpeg,.png,.webp" data-file-preview-input data-preview-name-id="serah-foto-name" data-preview-image-id="serah-foto-image">
                                    <div id="serah-foto-name" class="text-muted mt-2 st-file-meta">Belum ada file dipilih.</div>
                                    <div class="st-preview-frame mt-2">
                                        <img id="serah-foto-image" class="d-none" alt="Preview foto dokumentasi serah terima" src="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Barang Consumable</strong>
                    <div class="selection-subtext">Gudang aktif: <?= htmlspecialchars($selectedGudangName !== '' ? $selectedGudangName : '-') ?></div>
                </div>
                <span class="badge bg-light text-dark border" data-selection-counter>0 item aktif dari 0 item</span>
            </div>
            <div class="card-body" data-selection-panel data-selection-mode="qty">
                <div class="row g-2 mb-3">
                    <div class="col-lg-5 col-md-6">
                        <label class="form-label">Cari Barang</label>
                        <input type="text" class="form-control" placeholder="Cari kode, nama, kategori, lokasi" data-selection-search>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" data-selection-category>
                            <option value="">Semua kategori</option>
                            <?php foreach ($consumableCategories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 d-grid">
                        <label class="form-label">Aksi Cepat</label>
                        <button type="button" class="btn btn-outline-primary" data-selection-select-all>Pilih Semua</button>
                    </div>
                    <div class="col-lg-2 col-md-6 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-outline-secondary" data-selection-reset>Reset Pilihan</button>
                    </div>
                </div>

                <div class="table-responsive table-container overflowy">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Status & Lokasi</th>
                                <th>Stok Gudang Asal</th>
                                <th>Qty Serah</th>
                                <th>Kondisi Serah</th>
                                <th>Catatan Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($consumableRows)): ?>
                                <?php foreach ($consumableRows as $row): ?>
                                <tr data-selection-row
                                    data-search-text="<?= htmlspecialchars($row['search_text']) ?>"
                                    data-category="<?= htmlspecialchars($row['nama_kategori']) ?>"
                                    data-location="<?= htmlspecialchars($row['lokasi_ringkas']) ?>"
                                    data-status="<?= htmlspecialchars($row['status_display']) ?>">
                                    <td>
                                        <div><strong><?= htmlspecialchars($row['kode_produk']) ?></strong></div>
                                        <div><?= htmlspecialchars($row['nama_produk']) ?></div>
                                        <div class="selection-badge-group">
                                            <span class="badge bg-light text-dark border border-success"><?= htmlspecialchars($row['nama_kategori']) ?></span>
                                            <span class="badge bg-light text-dark border">Consumable</span>
                                        </div>
                                        <input type="hidden" name="consumable_produk_id[]" value="<?= intval($row['id_produk']) ?>">
                                    </td>
                                    <td>
                                        <div><span class="badge bg-success"><?= htmlspecialchars($row['status_display']) ?></span></div>
                                        <div class="selection-meta mt-1"><?= htmlspecialchars($row['lokasi_ringkas']) ?></div>
                                    </td>
                                    <td><?= intval($row['jumlah_stok']) ?> <?= htmlspecialchars($row['satuan']) ?></td>
                                    <td>
                                        <input type="number" min="0" max="<?= intval($row['jumlah_stok']) ?>" name="consumable_qty[]" class="form-control" value="0" data-selection-qty>
                                    </td>
                                    <td>
                                        <select name="consumable_kondisi_serah[]" class="form-select">
                                            <?php foreach (['baik', 'rusak', 'diperbaiki', 'usang', 'lainnya'] as $kondisi): ?>
                                            <option value="<?= htmlspecialchars($kondisi) ?>" <?= (($row['kondisi'] ?? 'baik') === $kondisi) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($kondisi)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="text" name="consumable_catatan_detail[]" class="form-control" placeholder="Catatan detail"></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="d-none" data-selection-empty-row>
                                    <td colspan="6" class="text-center text-muted">Tidak ada barang yang cocok dengan filter.</td>
                                </tr>
                            <?php else: ?>
                            <tr><td colspan="6" class="text-center">Tidak ada stok consumable yang bisa diserahterimakan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Unit Asset</strong>
                    <div class="selection-subtext">Klik baris unit untuk memilih lebih cepat.</div>
                </div>
                <span class="badge bg-light text-dark border" data-selection-counter>0 unit dipilih dari 0 unit</span>
            </div>
            <div class="card-body" data-selection-panel data-selection-mode="checkbox">
                <div class="row g-2 mb-3">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">Cari Unit / Barang</label>
                        <input type="text" class="form-control" placeholder="Cari kode unit, kode barang, nama, lokasi" data-selection-search>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" data-selection-category>
                            <option value="">Semua kategori</option>
                            <?php foreach ($assetCategories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Lokasi</label>
                        <select class="form-select" data-selection-location>
                            <option value="">Semua lokasi</option>
                            <?php foreach ($assetLocations as $location): ?>
                            <option value="<?= htmlspecialchars($location) ?>"><?= htmlspecialchars($location) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-6 d-grid">
                        <label class="form-label">Aksi</label>
                        <button type="button" class="btn btn-outline-primary" data-selection-select-all>Pilih Semua</button>
                    </div>
                    <div class="col-lg-1 col-md-6 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-outline-secondary" data-selection-reset>Reset</button>
                    </div>
                </div>

                <div class="table-responsive table-container overflowy">
                    <table class="table table-bordered table-striped selection-row-clickable align-middle">
                        <thead>
                            <tr>
                                <th class="text-center">Pilih</th>
                                <th>Unit</th>
                                <th>Barang</th>
                                <th>Status & Lokasi</th>
                                <th>Kondisi Saat Ini</th>
                                <th>Kondisi Serah</th>
                                <th>Catatan Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assetRows)): ?>
                                <?php foreach ($assetRows as $row): ?>
                                <tr data-selection-row
                                    data-search-text="<?= htmlspecialchars($row['search_text']) ?>"
                                    data-category="<?= htmlspecialchars($row['nama_kategori']) ?>"
                                    data-location="<?= htmlspecialchars($row['lokasi_ringkas']) ?>"
                                    data-status="<?= htmlspecialchars($row['status_display']) ?>">
                                    <td class="text-center"><input type="checkbox" name="asset_unit_barang_id[]" value="<?= intval($row['id_unit_barang']) ?>" data-selection-checkbox></td>
                                    <td>
                                        <?php $unitLabel = trim((string) ($row['kode_unit'] ?? '')); ?>
                                        <div><strong><?= htmlspecialchars($unitLabel !== '' ? $unitLabel : ('Unit #' . $row['id_unit_barang'])) ?></strong></div>
                                        <div class="selection-meta">ID: <?= intval($row['id_unit_barang']) ?></div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars(($row['kode_produk'] ?? '-') . ' - ' . ($row['nama_produk'] ?? '-')) ?></div>
                                        <div class="selection-badge-group">
                                            <span class="badge bg-light text-dark border border-primary"><?= htmlspecialchars($row['nama_kategori']) ?></span>
                                            <span class="badge bg-light text-dark border">Asset</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div><span class="badge <?= htmlspecialchars(get_asset_unit_status_badge_class($row['status'] ?? null)) ?>"><?= htmlspecialchars($row['status_display']) ?></span></div>
                                        <div class="selection-meta mt-1"><?= htmlspecialchars($row['lokasi_ringkas']) ?></div>
                                    </td>
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
                                <tr class="d-none" data-selection-empty-row>
                                    <td colspan="7" class="text-center text-muted">Tidak ada unit yang cocok dengan filter.</td>
                                </tr>
                            <?php else: ?>
                            <tr><td colspan="7" class="text-center">Tidak ada unit asset tersedia untuk serah terima.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="index.php?page=serah_terima" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Serah Terima</button>
        </div>
    </form>
</div>

<script src="assets/js/file-preview-ux.js"></script>
<script src="assets/js/inventory-selection-ux.js"></script>