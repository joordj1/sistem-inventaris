<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "inventaris_barang";

$koneksi = new mysqli($host, $user, $pass, $db);

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Debug sementara untuk memastikan database aktif
$activeDbResult = $koneksi->query("SELECT DATABASE() AS dbname");
if ($activeDbResult) {
    $activeDbName = $activeDbResult->fetch_assoc()['dbname'];
    error_log("[DEBUG] Database aktif: " . $activeDbName);
    // Gunakan echo hanya di environment dev, jika ingin dari UI tampilan.
    // echo "DEBUG: Database aktif adalah " . $activeDbName . "\n";
}

function log_tracking_history($koneksi, $data) {
    $availableCols = [];
    $result = $koneksi->query("SHOW COLUMNS FROM tracking_barang");
    while ($col = $result ? $result->fetch_assoc() : null) {
        if (!$col) break;
        $availableCols[] = $col['Field'];
    }
    $colSet = array_flip($availableCols);

    $columns = [];
    $values = [];
    $types = '';

    // Required tracking identity
    if (isset($colSet['id_produk'])) {
        $columns[] = 'id_produk';
        $values[] = $data['id_produk'];
        $types .= 'i';
    }

    // Variant kode field
    if (isset($colSet['kode_produk'])) {
        $columns[] = 'kode_produk';
        $values[] = $data['kode_produk'] ?? null;
        $types .= 's';
    } elseif (isset($colSet['kode_barang'])) {
        $columns[] = 'kode_barang';
        $values[] = $data['kode_barang'] ?? $data['kode_produk'] ?? null;
        $types .= 's';
    }

    foreach (['status_sebelum','status_sesudah','kondisi_sebelum','kondisi_sesudah','lokasi_sebelum','lokasi_sesudah','activity_type','note'] as $field) {
        if (isset($colSet[$field])) {
            $columns[] = $field;
            $values[] = $data[$field] ?? null;
            $types .= 's';
        }
    }

    foreach (['id_user_sebelum','id_user_sesudah','id_user_terkait','id_user_changed','id_user'] as $field) {
        if (isset($colSet[$field])) {
            $columns[] = $field;
            if ($field === 'id_user' && isset($colSet['id_user_terkait'])) {
                // Prioritize explicit related user when available
                continue;
            }
            $values[] = $data[$field] ?? ($field === 'id_user' ? ($data['id_user_terkait'] ?? $data['id_user_sesudah'] ?? $data['id_user_sebelum'] ?? null) : null);
            $types .= 'i';
        }
    }

    if (empty($columns)) {
        return false;
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $query = "INSERT INTO tracking_barang (" . implode(', ', $columns) . ") VALUES ($placeholders)";

    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        return false;
    }

    $bindParams = [];
    $bindParams[] = & $types;
    foreach ($values as $i => $value) {
        $bindParams[] = & $values[$i];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    return $stmt->execute();
}

function log_riwayat_unit_barang($koneksi, $data) {
    $availableCols = [];
    $result = $koneksi->query("SHOW COLUMNS FROM riwayat_unit_barang");
    while ($col = $result ? $result->fetch_assoc() : null) {
        if (!$col) break;
        $availableCols[] = $col['Field'];
    }
    $colSet = array_flip($availableCols);

    $columns = [];
    $values = [];
    $types = '';

    if (isset($colSet['id_unit_barang'])) {
        $columns[] = 'id_unit_barang';
        $values[] = $data['id_unit_barang'];
        $types .= 'i';
    }
    if (isset($colSet['id_produk'])) {
        $columns[] = 'id_produk';
        $values[] = $data['id_produk'];
        $types .= 'i';
    }
    foreach (['activity_type','status_sebelum','status_sesudah','kondisi_sebelum','kondisi_sesudah','lokasi_sebelum','lokasi_sesudah','note'] as $field) {
        if (isset($colSet[$field])) {
            $columns[] = $field;
            $values[] = $data[$field] ?? null;
            $types .= 's';
        }
    }
    foreach (['id_user_sebelum','id_user_sesudah','id_user_terkait','id_user_changed','id_user'] as $field) {
        if (isset($colSet[$field])) {
            $columns[] = $field;
            $values[] = $data[$field] ?? ($field === 'id_user' ? ($data['id_user_terkait'] ?? $data['id_user_sesudah'] ?? $data['id_user_sebelum'] ?? null) : null);
            $types .= 'i';
        }
    }

    if (empty($columns)) {
        return false;
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $query = "INSERT INTO riwayat_unit_barang (" . implode(', ', $columns) . ") VALUES ($placeholders)";

    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        return false;
    }

    $bindParams = [];
    $bindParams[] = & $types;
    foreach ($values as $i => $value) {
        $bindParams[] = & $values[$i];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    return $stmt->execute();
}

function get_produk_by_id($koneksi, $id_produk) {
    $id_produk = intval($id_produk);
    $stmt = $koneksi->prepare("SELECT * FROM produk WHERE id_produk = ?");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc();
}

function get_unit_barang_by_id($koneksi, $id_unit_barang) {
    $id_unit_barang = intval($id_unit_barang);
    $stmt = $koneksi->prepare("SELECT * FROM unit_barang WHERE id_unit_barang = ?");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id_unit_barang);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc();
}

function update_unit_barang($koneksi, $id_unit_barang, $fields) {
    if (empty($fields) || !is_array($fields)) {
        return false;
    }
    $setParts = [];
    $types = '';
    $values = [];
    foreach ($fields as $col => $val) {
        $setParts[] = "$col = ?";
        $types .= is_int($val) ? 'i' : 's';
        $values[] = $val;
    }
    $sql = "UPDATE unit_barang SET " . implode(', ', $setParts) . " WHERE id_unit_barang = ?";
    $types .= 'i';
    $values[] = intval($id_unit_barang);

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param($types, ...$values);
    return $stmt->execute();
}

function schema_table_exists($koneksi, $tableName) {
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    $safeTableName = $koneksi->real_escape_string($tableName);
    $result = $koneksi->query("SHOW TABLES LIKE '$safeTableName'");
    $cache[$tableName] = (bool) ($result && $result->num_rows > 0);

    return $cache[$tableName];
}

function schema_get_columns($koneksi, $tableName) {
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    $columns = [];
    if (!schema_table_exists($koneksi, $tableName)) {
        $cache[$tableName] = $columns;
        return $columns;
    }

    $result = $koneksi->query("SHOW COLUMNS FROM `$tableName`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $cache[$tableName] = $columns;
    return $columns;
}

function schema_has_column($koneksi, $tableName, $columnName) {
    return in_array($columnName, schema_get_columns($koneksi, $tableName), true);
}

function schema_find_existing_column($koneksi, $tableName, $candidates) {
    $columns = schema_get_columns($koneksi, $tableName);
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function normalize_asset_unit_status($value) {
    $normalized = strtolower(trim((string) ($value ?? '')));
    $map = [
        'tersedia' => 'tersedia',
        'dipakai' => 'dipakai',
        'dipinjam' => 'dipakai',
        'digunakan' => 'dipakai',
        'sedang digunakan' => 'dipakai',
        'perbaikan' => 'perbaikan',
        'dalam perbaikan' => 'perbaikan',
        'rusak' => 'rusak',
    ];

    return $map[$normalized] ?? 'tersedia';
}

function map_asset_unit_status_for_storage($value) {
    $normalized = normalize_asset_unit_status($value);
    $map = [
        'tersedia' => 'tersedia',
        'dipakai' => 'dipinjam',
        'perbaikan' => 'dalam perbaikan',
        'rusak' => 'rusak',
    ];

    return $map[$normalized] ?? 'tersedia';
}

function get_asset_unit_status_label($value) {
    $normalized = normalize_asset_unit_status($value);
    $labels = [
        'tersedia' => 'Tersedia',
        'dipakai' => 'Dipakai',
        'perbaikan' => 'Perbaikan',
        'rusak' => 'Rusak',
    ];

    return $labels[$normalized] ?? 'Tersedia';
}

function get_asset_unit_status_badge_class($value) {
    $normalized = normalize_asset_unit_status($value);
    $classes = [
        'tersedia' => 'bg-success',
        'dipakai' => 'bg-primary',
        'perbaikan' => 'bg-warning text-dark',
        'rusak' => 'bg-danger',
    ];

    return $classes[$normalized] ?? 'bg-secondary';
}

function is_valid_asset_unit_status_transition($fromStatus, $toStatus) {
    $from = normalize_asset_unit_status($fromStatus);
    $to = normalize_asset_unit_status($toStatus);

    $allowedTransitions = [
        'tersedia' => ['dipakai', 'perbaikan', 'rusak'],
        'dipakai' => ['tersedia', 'perbaikan', 'rusak'],
        'perbaikan' => ['tersedia', 'rusak'],
        'rusak' => ['perbaikan', 'tersedia'],
    ];

    if ($from === $to) {
        return false;
    }

    return in_array($to, $allowedTransitions[$from] ?? [], true);
}

function is_asset_unit_action_allowed($action, $status) {
    $status = normalize_asset_unit_status($status);
    $allowedActions = [
        'move' => ['tersedia', 'dipakai'],
        'assign' => ['tersedia'],
        'release' => ['dipakai'],
        'mark_perbaikan' => ['tersedia', 'dipakai', 'rusak'],
        'mark_rusak' => ['tersedia', 'dipakai', 'perbaikan'],
        'set_tersedia' => ['perbaikan', 'rusak'],
    ];

    return in_array($status, $allowedActions[$action] ?? [], true);
}

function get_asset_unit_activity_type_label($value) {
    $normalized = strtolower(trim((string) ($value ?? '')));
    $labels = [
        'tambah' => 'Registrasi',
        'pinjam' => 'Peminjaman',
        'kembali' => 'Pengembalian',
        'pindah' => 'Mutasi',
        'perbaikan' => 'Perbaikan',
        'rusak' => 'Rusak',
        'update' => 'Update Status',
        'arsip' => 'Arsip',
    ];

    return $labels[$normalized] ?? 'Riwayat';
}

function get_asset_unit_activity_group($value) {
    $normalized = strtolower(trim((string) ($value ?? '')));
    $groups = [
        'tambah' => 'REGISTRASI',
        'pinjam' => 'PEMINJAMAN',
        'kembali' => 'PENGEMBALIAN',
        'pindah' => 'MUTASI',
        'perbaikan' => 'PERBAIKAN',
        'rusak' => 'RUSAK',
        'update' => 'UPDATE STATUS',
        'arsip' => 'ARSIP',
    ];

    return $groups[$normalized] ?? 'RIWAYAT';
}

function determine_asset_unit_activity_type($fromStatus, $toStatus, $fallback = 'update') {
    $from = normalize_asset_unit_status($fromStatus);
    $to = normalize_asset_unit_status($toStatus);

    if ($from === 'dipakai' && $to === 'tersedia') {
        return 'kembali';
    }
    if ($to === 'perbaikan') {
        return 'perbaikan';
    }
    if ($to === 'rusak') {
        return 'rusak';
    }

    return $fallback;
}

function get_gudang_name_by_id($koneksi, $id_gudang) {
    $id_gudang = $id_gudang !== null ? intval($id_gudang) : 0;
    if ($id_gudang < 1) {
        return null;
    }

    $stmt = $koneksi->prepare("SELECT nama_gudang FROM gudang WHERE id_gudang = ?");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id_gudang);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return $row['nama_gudang'] ?? null;
}

function get_lokasi_name_by_id($koneksi, $id_lokasi) {
    if (!schema_table_exists($koneksi, 'lokasi')) {
        return null;
    }

    $id_lokasi = $id_lokasi !== null ? intval($id_lokasi) : 0;
    if ($id_lokasi < 1) {
        return null;
    }

    $stmt = $koneksi->prepare("SELECT nama_lokasi FROM lokasi WHERE id_lokasi = ?");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id_lokasi);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return $row['nama_lokasi'] ?? null;
}

function get_user_name_by_id($koneksi, $id_user) {
    $id_user = $id_user !== null ? intval($id_user) : 0;
    if ($id_user < 1) {
        return null;
    }

    $stmt = $koneksi->prepare("SELECT nama FROM user WHERE id_user = ?");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return $row['nama'] ?? null;
}

function asset_unit_user_exists($koneksi, $id_user) {
    return get_user_name_by_id($koneksi, $id_user) !== null;
}

function asset_unit_gudang_exists($koneksi, $id_gudang) {
    return get_gudang_name_by_id($koneksi, $id_gudang) !== null;
}

function asset_unit_lokasi_exists($koneksi, $id_lokasi) {
    return get_lokasi_name_by_id($koneksi, $id_lokasi) !== null;
}

function get_asset_unit_location_parts($koneksi, $unit) {
    $parts = [];

    $gudangName = trim((string) ($unit['nama_gudang'] ?? ''));
    if ($gudangName === '' && !empty($unit['id_gudang'])) {
        $gudangName = trim((string) (get_gudang_name_by_id($koneksi, $unit['id_gudang']) ?? ''));
    }

    $lokasiName = trim((string) ($unit['nama_lokasi'] ?? ''));
    if ($lokasiName === '' && !empty($unit['id_lokasi'])) {
        $lokasiName = trim((string) (get_lokasi_name_by_id($koneksi, $unit['id_lokasi']) ?? ''));
    }

    $lokasiCustom = trim((string) ($unit['lokasi_custom'] ?? ''));

    if ($gudangName !== '') {
        $parts[] = $gudangName;
    }
    if ($lokasiName !== '') {
        $parts[] = $lokasiName;
    }
    if ($lokasiCustom !== '') {
        $parts[] = $lokasiCustom;
    }

    return $parts;
}

function get_asset_unit_location_text($koneksi, $unit) {
    $parts = get_asset_unit_location_parts($koneksi, $unit);
    return !empty($parts) ? implode(' / ', $parts) : null;
}

function build_asset_unit_prefix($kode_produk) {
    $prefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper((string) $kode_produk));
    return $prefix !== '' ? $prefix : 'UNIT';
}

function get_asset_unit_code_column($koneksi) {
    return schema_find_existing_column($koneksi, 'unit_barang', ['kode_unit', 'serial_number']);
}

function get_asset_unit_qr_column($koneksi) {
    return schema_find_existing_column($koneksi, 'unit_barang', ['qr_code', 'kode_qrcode']);
}

function build_asset_unit_code_seed($koneksi, $id_produk, $kode_produk) {
    $codeColumn = get_asset_unit_code_column($koneksi);
    if ($codeColumn === null) {
        return null;
    }

    $prefix = build_asset_unit_prefix($kode_produk);
    $maxNumber = 0;
    $stmt = $koneksi->prepare("SELECT `$codeColumn` AS unit_code FROM unit_barang WHERE id_produk = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id_produk);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $unitCode = strtoupper(trim((string) ($row['unit_code'] ?? '')));
            if ($unitCode === '') {
                continue;
            }

            if (preg_match('/-(\d+)$/', $unitCode, $matches)) {
                $maxNumber = max($maxNumber, intval($matches[1]));
            }
        }
    }

    return [
        'column' => $codeColumn,
        'prefix' => $prefix,
        'next_number' => $maxNumber + 1,
    ];
}

function build_asset_qr_value($id_unit_barang) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $requestUri = isset($_SERVER['REQUEST_URI']) ? dirname($_SERVER['REQUEST_URI']) : '';
    $base = rtrim($host . $requestUri, '/\\');

    return $proto . '://' . $base . '/index.php?page=unit_barang_info&id_unit_barang=' . intval($id_unit_barang);
}

function insert_asset_unit_row($koneksi, $productData, $unitCode, $operatorId = null, $note = 'Unit asset dibuat otomatis') {
    if (!schema_table_exists($koneksi, 'unit_barang')) {
        return ['success' => false, 'message' => 'Tabel unit_barang tidak tersedia.'];
    }

    $id_produk = intval($productData['id_produk'] ?? 0);
    if ($id_produk < 1) {
        return ['success' => false, 'message' => 'ID produk asset tidak valid.'];
    }

    $codeColumn = get_asset_unit_code_column($koneksi);
    if ($codeColumn === null) {
        return ['success' => false, 'message' => 'Kolom kode unit asset tidak ditemukan pada tabel unit_barang.'];
    }

    $kondisi = trim((string) ($productData['kondisi'] ?? 'baik'));
    if ($kondisi === '') {
        $kondisi = 'baik';
    }

    $columns = ['id_produk', $codeColumn, 'status', 'kondisi'];
    $values = [
        intval($id_produk),
        "'" . $koneksi->real_escape_string($unitCode) . "'",
        "'tersedia'",
        "'" . $koneksi->real_escape_string($kondisi) . "'",
    ];

    if (schema_has_column($koneksi, 'unit_barang', 'id_gudang')) {
        $id_gudang = isset($productData['id_gudang']) && $productData['id_gudang'] !== null ? intval($productData['id_gudang']) : null;
        $columns[] = 'id_gudang';
        $values[] = $id_gudang !== null ? (string) $id_gudang : 'NULL';
    }
    if (schema_has_column($koneksi, 'unit_barang', 'lokasi_custom')) {
        $columns[] = 'lokasi_custom';
        $values[] = 'NULL';
    }
    if (schema_has_column($koneksi, 'unit_barang', 'id_user')) {
        $columns[] = 'id_user';
        $values[] = 'NULL';
    }
    if (schema_has_column($koneksi, 'unit_barang', 'tersedia')) {
        $columns[] = 'tersedia';
        $values[] = '1';
    }

    $query = "INSERT INTO unit_barang (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
    if (!$koneksi->query($query)) {
        return ['success' => false, 'message' => $koneksi->error];
    }

    $idUnit = intval($koneksi->insert_id);
    $qrColumn = get_asset_unit_qr_column($koneksi);
    if ($qrColumn !== null && $idUnit > 0) {
        $qrValue = build_asset_qr_value($idUnit);
        $updateQrStmt = $koneksi->prepare("UPDATE unit_barang SET `$qrColumn` = ? WHERE id_unit_barang = ?");
        if ($updateQrStmt) {
            $updateQrStmt->bind_param('si', $qrValue, $idUnit);
            $updateQrStmt->execute();
        }
    }

    $locationName = get_gudang_name_by_id($koneksi, $productData['id_gudang'] ?? null);
    log_riwayat_unit_barang($koneksi, [
        'id_unit_barang' => $idUnit,
        'id_produk' => $id_produk,
        'activity_type' => 'tambah',
        'status_sebelum' => null,
        'status_sesudah' => 'tersedia',
        'kondisi_sebelum' => null,
        'kondisi_sesudah' => $kondisi,
        'lokasi_sebelum' => null,
        'lokasi_sesudah' => $locationName,
        'id_user_sebelum' => null,
        'id_user_sesudah' => null,
        'id_user_terkait' => null,
        'note' => $note,
        'id_user_changed' => $operatorId !== null ? intval($operatorId) : null,
    ]);

    return ['success' => true, 'id_unit_barang' => $idUnit];
}

function get_asset_unit_rows($koneksi, $id_produk) {
    $columns = ['id_unit_barang', 'status'];

    foreach (['tersedia', 'id_user', 'id_gudang', 'lokasi_custom', 'id_lokasi', 'deleted_at'] as $optionalColumn) {
        if (schema_has_column($koneksi, 'unit_barang', $optionalColumn)) {
            $columns[] = $optionalColumn;
        }
    }

    $query = "SELECT " . implode(', ', $columns) . " FROM unit_barang WHERE id_produk = ? ORDER BY id_unit_barang DESC";
    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    return $rows;
}

function get_asset_unit_history_map($koneksi, $id_produk) {
    $historyMap = [];
    if (!schema_table_exists($koneksi, 'riwayat_unit_barang')) {
        return $historyMap;
    }

    $stmt = $koneksi->prepare("SELECT id_unit_barang, COUNT(*) AS total FROM riwayat_unit_barang WHERE id_produk = ? GROUP BY id_unit_barang");
    if (!$stmt) {
        return $historyMap;
    }

    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $historyMap[intval($row['id_unit_barang'])] = intval($row['total']);
    }

    return $historyMap;
}

function delete_asset_units_safely($koneksi, $id_produk, $removeCount) {
    $id_produk = intval($id_produk);
    $removeCount = intval($removeCount);

    if ($removeCount <= 0) {
        return ['success' => true, 'removed' => 0];
    }

    $unitRows = get_asset_unit_rows($koneksi, $id_produk);
    $historyMap = get_asset_unit_history_map($koneksi, $id_produk);
    $removableIds = [];

    foreach ($unitRows as $unitRow) {
        $unitId = intval($unitRow['id_unit_barang'] ?? 0);
        if ($unitId < 1) {
            continue;
        }

        if (array_key_exists($unitId, $historyMap) && intval($historyMap[$unitId]) > 0) {
            continue;
        }

        $status = normalize_asset_unit_status($unitRow['status'] ?? '');
        if ($status !== 'tersedia') {
            continue;
        }

        if (isset($unitRow['tersedia']) && intval($unitRow['tersedia']) !== 1) {
            continue;
        }

        if (isset($unitRow['id_user']) && !empty($unitRow['id_user'])) {
            continue;
        }

        $removableIds[] = $unitId;
        if (count($removableIds) >= $removeCount) {
            break;
        }
    }

    if (count($removableIds) < $removeCount) {
        return [
            'success' => false,
            'message' => 'Jumlah stok asset tidak bisa dikurangi karena masih ada unit aktif, rusak, sedang dipakai, atau sudah memiliki histori.',
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($removableIds), '?'));
    $types = str_repeat('i', count($removableIds) + 1);
    $values = array_merge([$id_produk], $removableIds);
    $query = "DELETE FROM unit_barang WHERE id_produk = ? AND id_unit_barang IN ($placeholders)";
    $stmt = $koneksi->prepare($query);

    if (!$stmt) {
        return ['success' => false, 'message' => $koneksi->error];
    }

    $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => $stmt->error ?: $koneksi->error];
    }

    return ['success' => true, 'removed' => count($removableIds)];
}

function sync_asset_unit_gudang_with_master($koneksi, $productData, $oldGudangId = null) {
    if (!schema_table_exists($koneksi, 'unit_barang') || !schema_has_column($koneksi, 'unit_barang', 'id_gudang')) {
        return ['success' => true, 'updated' => 0];
    }

    $id_produk = intval($productData['id_produk'] ?? 0);
    $newGudangId = isset($productData['id_gudang']) && $productData['id_gudang'] !== null ? intval($productData['id_gudang']) : null;
    if ($id_produk < 1 || $newGudangId === null) {
        return ['success' => true, 'updated' => 0];
    }

    $conditions = ["id_produk = $id_produk"];

    if (schema_has_column($koneksi, 'unit_barang', 'lokasi_custom')) {
        $conditions[] = "(lokasi_custom IS NULL OR TRIM(lokasi_custom) = '')";
    }
    if (schema_has_column($koneksi, 'unit_barang', 'id_lokasi')) {
        $conditions[] = "id_lokasi IS NULL";
    }

    if ($oldGudangId !== null) {
        $conditions[] = "(id_gudang IS NULL OR id_gudang = " . intval($oldGudangId) . ")";
    } else {
        $conditions[] = "id_gudang IS NULL";
    }

    $query = "UPDATE unit_barang SET id_gudang = $newGudangId WHERE " . implode(' AND ', $conditions);
    if (!$koneksi->query($query)) {
        return ['success' => false, 'message' => $koneksi->error];
    }

    return ['success' => true, 'updated' => intval($koneksi->affected_rows)];
}

function sync_asset_units_for_product($koneksi, $productData, $options = []) {
    if (!schema_table_exists($koneksi, 'unit_barang')) {
        return ['success' => false, 'message' => 'Tabel unit_barang tidak tersedia.'];
    }

    $id_produk = intval($productData['id_produk'] ?? 0);
    $targetCount = max(0, intval($productData['jumlah_stok'] ?? 0));
    if ($id_produk < 1) {
        return ['success' => false, 'message' => 'ID produk asset tidak valid.'];
    }

    $codeSeed = build_asset_unit_code_seed($koneksi, $id_produk, $productData['kode_produk'] ?? '');
    if ($codeSeed === null) {
        return ['success' => false, 'message' => 'Kolom kode unit asset tidak ditemukan pada tabel unit_barang.'];
    }

    $existingUnits = get_asset_unit_rows($koneksi, $id_produk);
    $existingCount = count($existingUnits);
    $created = 0;
    $removed = 0;

    if ($targetCount > $existingCount) {
        $missingCount = $targetCount - $existingCount;
        $nextNumber = intval($codeSeed['next_number']);

        for ($i = 0; $i < $missingCount; $i++) {
            $unitCode = sprintf('%s-%03d', $codeSeed['prefix'], $nextNumber++);
            $insertResult = insert_asset_unit_row(
                $koneksi,
                $productData,
                $unitCode,
                $options['operator_id'] ?? null,
                $options['create_note'] ?? 'Unit asset dibuat otomatis'
            );

            if (empty($insertResult['success'])) {
                return $insertResult;
            }

            $created++;
        }
    } elseif ($targetCount < $existingCount) {
        $removeResult = delete_asset_units_safely($koneksi, $id_produk, $existingCount - $targetCount);
        if (empty($removeResult['success'])) {
            return $removeResult;
        }
        $removed = intval($removeResult['removed'] ?? 0);
    }

    $syncGudangResult = sync_asset_unit_gudang_with_master(
        $koneksi,
        $productData,
        $options['old_gudang_id'] ?? null
    );

    if (empty($syncGudangResult['success'])) {
        return $syncGudangResult;
    }

    return [
        'success' => true,
        'created' => $created,
        'removed' => $removed,
        'gudang_synced' => intval($syncGudangResult['updated'] ?? 0),
    ];
}

?>
