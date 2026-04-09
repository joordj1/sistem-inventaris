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

function normalize_user_role($role) {
    $role = strtolower(trim((string) ($role ?? '')));
    $map = [
        'admin' => 'admin',
        'petugas' => 'petugas',
        'leader' => 'petugas',
        'operator' => 'petugas',
        'viewer' => 'user',
        'user' => 'user',
    ];

    return $map[$role] ?? 'user';
}

function normalize_user_status($status) {
    $status = strtolower(trim((string) ($status ?? '')));
    $map = [
        'aktif' => 'aktif',
        'active' => 'aktif',
        'nonaktif' => 'nonaktif',
        'inactive' => 'nonaktif',
        'disabled' => 'nonaktif',
        'deleted' => 'nonaktif',
    ];

    return $map[$status] ?? 'aktif';
}

function normalize_user_category($category) {
    $category = strtolower(trim((string) ($category ?? '')));
    $map = [
        'staff' => 'staff',
        'pegawai' => 'staff',
        'dosen' => 'dosen',
        'lecturer' => 'dosen',
        'mahasiswa' => 'mahasiswa',
        'student' => 'mahasiswa',
        'umum' => 'umum',
        'public' => 'umum',
    ];

    return $map[$category] ?? 'umum';
}

function get_current_user_role() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['role'])) {
        return null;
    }

    $_SESSION['role'] = normalize_user_role($_SESSION['role']);
    return $_SESSION['role'];
}

function current_user_has_role($roles) {
    $currentRole = get_current_user_role();
    if ($currentRole === null) {
        return false;
    }

    $roles = is_array($roles) ? $roles : [$roles];
    $roles = array_map('normalize_user_role', $roles);

    return in_array($currentRole, $roles, true);
}

function inventory_user_can_manage() {
    return current_user_has_role(['admin', 'petugas']);
}

function inventory_user_is_admin() {
    return current_user_has_role('admin');
}

function inventory_user_is_view_only() {
    return current_user_has_role('user');
}

function require_auth_roles($roles, $options = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $responseType = $options['response'] ?? 'page';
    $loginRedirect = $options['login_redirect'] ?? 'login.php';
    $forbiddenRedirect = $options['forbidden_redirect'] ?? 'index.php';
    $message = $options['message'] ?? 'Anda tidak memiliki akses untuk melakukan aksi ini.';

    if (empty($_SESSION['id_user'])) {
        if ($responseType === 'json') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu.']);
            exit;
        }

        header('Location: ' . $loginRedirect);
        exit;
    }

    if (!current_user_has_role($roles)) {
        if ($responseType === 'json') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message]);
            exit;
        }

        header('Location: ' . $forbiddenRedirect);
        exit;
    }

    return get_current_user_role();
}

function schema_table_exists_now($koneksi, $tableName) {
    $safeTableName = $koneksi->real_escape_string($tableName);
    $result = $koneksi->query("SHOW TABLES LIKE '$safeTableName'");
    return (bool) ($result && $result->num_rows > 0);
}

function schema_has_column_now($koneksi, $tableName, $columnName) {
    if (!schema_table_exists_now($koneksi, $tableName)) {
        return false;
    }

    $safeTableName = $koneksi->real_escape_string($tableName);
    $safeColumnName = $koneksi->real_escape_string($columnName);
    $result = $koneksi->query("SHOW COLUMNS FROM `$safeTableName` LIKE '$safeColumnName'");
    return (bool) ($result && $result->num_rows > 0);
}

function schema_index_exists_now($koneksi, $tableName, $indexName) {
    if (!schema_table_exists_now($koneksi, $tableName)) {
        return false;
    }

    $safeTableName = $koneksi->real_escape_string($tableName);
    $safeIndexName = $koneksi->real_escape_string($indexName);
    $result = $koneksi->query("SHOW INDEX FROM `$safeTableName` WHERE Key_name = '$safeIndexName'");
    return (bool) ($result && $result->num_rows > 0);
}

function schema_has_unique_index_now($koneksi, $tableName, $columnName) {
    if (!schema_table_exists_now($koneksi, $tableName)) {
        return false;
    }

    $safeTableName = $koneksi->real_escape_string($tableName);
    $safeColumnName = $koneksi->real_escape_string($columnName);
    $result = $koneksi->query("SHOW INDEX FROM `$safeTableName` WHERE Column_name = '$safeColumnName'");
    if (!$result) {
        return false;
    }

    while ($row = $result->fetch_assoc()) {
        if ((int) ($row['Non_unique'] ?? 1) === 0) {
            return true;
        }
    }

    return false;
}

function schema_foreign_key_exists_now($koneksi, $tableName, $constraintName) {
    $safeTableName = $koneksi->real_escape_string($tableName);
    $safeConstraintName = $koneksi->real_escape_string($constraintName);
    $sql = "SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '$safeTableName'
              AND CONSTRAINT_NAME = '$safeConstraintName'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            LIMIT 1";
    $result = $koneksi->query($sql);
    return (bool) ($result && $result->num_rows > 0);
}

function nullable_int_id($value) {
    if ($value === null || $value === '') {
        return null;
    }

    $value = intval($value);
    return $value > 0 ? $value : null;
}

function hash_inventory_password($password) {
    return password_hash((string) $password, PASSWORD_DEFAULT);
}

function verify_inventory_password($password, $storedHash) {
    $password = (string) $password;
    $storedHash = (string) ($storedHash ?? '');
    if ($password === '' || $storedHash === '') {
        return false;
    }

    $hashInfo = password_get_info($storedHash);
    if (!empty($hashInfo['algo'])) {
        return password_verify($password, $storedHash);
    }

    if (preg_match('/^[a-f0-9]{32}$/i', $storedHash)) {
        return hash_equals(strtolower($storedHash), md5($password));
    }

    return false;
}

function inventory_password_needs_upgrade($storedHash) {
    $storedHash = (string) ($storedHash ?? '');
    if ($storedHash === '') {
        return false;
    }

    $hashInfo = password_get_info($storedHash);
    if (!empty($hashInfo['algo'])) {
        return password_needs_rehash($storedHash, PASSWORD_DEFAULT);
    }

    return preg_match('/^[a-f0-9]{32}$/i', $storedHash) === 1;
}

function get_active_bidang_rows($koneksi, $includeInactive = false) {
    if (!schema_table_exists_now($koneksi, 'bidang')) {
        return [];
    }

    $sql = "SELECT id, nama_bidang, kode_bidang, status FROM bidang WHERE 1 = 1";
    if (!$includeInactive && schema_has_column_now($koneksi, 'bidang', 'status')) {
        $sql .= " AND status = 'aktif'";
    }
    $sql .= " ORDER BY nama_bidang ASC";

    $rows = [];
    $result = $koneksi->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function get_bidang_name_by_id($koneksi, $bidangId) {
    $bidangId = nullable_int_id($bidangId);
    if ($bidangId === null || !schema_table_exists_now($koneksi, 'bidang')) {
        return null;
    }

    $stmt = $koneksi->prepare("SELECT nama_bidang FROM bidang WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $bidangId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    return $row['nama_bidang'] ?? null;
}

function inventory_bidang_exists($koneksi, $bidangId, $includeInactive = false) {
    $bidangId = nullable_int_id($bidangId);
    if ($bidangId === null || !schema_table_exists_now($koneksi, 'bidang')) {
        return false;
    }

    $sql = "SELECT id FROM bidang WHERE id = ?";
    if (!$includeInactive && schema_has_column_now($koneksi, 'bidang', 'status')) {
        $sql .= " AND status = 'aktif'";
    }
    $sql .= " LIMIT 1";

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $bidangId);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool) ($result && $result->num_rows > 0);
}

function normalize_tracking_activity_type($value, $fallback = 'update') {
    $normalized = strtolower(trim((string) ($value ?? '')));
    $fallback = strtolower(trim((string) ($fallback ?? 'update')));

    $map = [
        'tambah' => 'tambah',
        'create' => 'tambah',
        'created' => 'tambah',
        'pinjam' => 'pinjam',
        'dipinjam' => 'pinjam',
        'digunakan' => 'pinjam',
        'sedang digunakan' => 'pinjam',
        'kembali' => 'kembali',
        'dikembalikan' => 'kembali',
        'release' => 'kembali',
        'pindah' => 'pindah',
        'dipindahkan' => 'pindah',
        'pindah lokasi' => 'pindah',
        'mutasi' => 'pindah',
        'perbaikan' => 'perbaikan',
        'dalam perbaikan' => 'perbaikan',
        'rusak' => 'rusak',
        'update' => 'update',
        'updated' => 'update',
        'keluarmasuk' => 'keluarmasuk',
        'barang_masuk' => 'keluarmasuk',
        'barang_keluar' => 'keluarmasuk',
        'arsip' => 'arsip',
    ];

    if ($normalized === '') {
        return $map[$fallback] ?? 'update';
    }

    return $map[$normalized] ?? ($map[$fallback] ?? 'update');
}

function get_tracking_activity_label($value) {
    $labels = [
        'tambah' => 'Registrasi',
        'pinjam' => 'Peminjaman',
        'kembali' => 'Pengembalian',
        'pindah' => 'Perpindahan lokasi',
        'perbaikan' => 'Perbaikan',
        'rusak' => 'Rusak',
        'update' => 'Update',
        'keluarmasuk' => 'Barang masuk/keluar',
        'arsip' => 'Arsip',
    ];

    $normalized = normalize_tracking_activity_type($value, 'update');
    return $labels[$normalized] ?? 'Riwayat';
}

function infer_tracking_activity_type($data, $fallback = 'update') {
    $explicitValue = $data['activity_type'] ?? ($data['aktivitas'] ?? null);
    if (trim((string) ($explicitValue ?? '')) !== '') {
        return normalize_tracking_activity_type($explicitValue, $fallback);
    }

    $statusBefore = strtolower(trim((string) ($data['status_sebelum'] ?? '')));
    $statusAfter = strtolower(trim((string) ($data['status_sesudah'] ?? '')));
    $kondisiBefore = strtolower(trim((string) ($data['kondisi_sebelum'] ?? '')));
    $kondisiAfter = strtolower(trim((string) ($data['kondisi_sesudah'] ?? '')));
    $lokasiBefore = trim((string) ($data['lokasi_sebelum'] ?? ''));
    $lokasiAfter = trim((string) ($data['lokasi_sesudah'] ?? ''));
    $userBefore = nullable_int_id($data['id_user_sebelum'] ?? null);
    $userAfter = nullable_int_id($data['id_user_sesudah'] ?? ($data['id_user_terkait'] ?? null));

    if ($statusBefore === '' && $statusAfter !== '') {
        return 'tambah';
    }
    if ($userBefore === null && $userAfter !== null) {
        return 'pinjam';
    }
    if ($userBefore !== null && $userAfter === null) {
        return 'kembali';
    }
    if ($statusBefore !== $statusAfter) {
        if (in_array($statusAfter, ['dipinjam', 'digunakan', 'sedang digunakan'], true)) {
            return 'pinjam';
        }
        if ($statusAfter === 'tersedia' && in_array($statusBefore, ['dipinjam', 'digunakan', 'sedang digunakan'], true)) {
            return 'kembali';
        }
        if (in_array($statusAfter, ['perbaikan', 'dalam perbaikan'], true)) {
            return 'perbaikan';
        }
        if ($statusAfter === 'rusak') {
            return 'rusak';
        }
    }
    if ($lokasiBefore !== $lokasiAfter && ($lokasiBefore !== '' || $lokasiAfter !== '')) {
        return 'pindah';
    }
    if ($kondisiBefore !== $kondisiAfter) {
        return $kondisiAfter === 'rusak' ? 'rusak' : 'update';
    }

    return normalize_tracking_activity_type($fallback, 'update');
}

function build_tracking_note_fallback($data, $scope = 'barang') {
    $activityType = infer_tracking_activity_type($data, 'update');
    $statusBefore = strtolower(trim((string) ($data['status_sebelum'] ?? '')));
    $statusAfter = strtolower(trim((string) ($data['status_sesudah'] ?? '')));
    $kondisiBefore = strtolower(trim((string) ($data['kondisi_sebelum'] ?? '')));
    $kondisiAfter = strtolower(trim((string) ($data['kondisi_sesudah'] ?? '')));
    $lokasiBefore = trim((string) ($data['lokasi_sebelum'] ?? ''));
    $lokasiAfter = trim((string) ($data['lokasi_sesudah'] ?? ''));
    $userBefore = nullable_int_id($data['id_user_sebelum'] ?? null);
    $userAfter = nullable_int_id($data['id_user_sesudah'] ?? ($data['id_user_terkait'] ?? null));
    $itemLabel = $scope === 'unit' ? 'unit barang' : 'barang';

    switch ($activityType) {
        case 'tambah':
            return ucfirst($itemLabel) . ' ditambahkan';
        case 'pinjam':
            return 'Barang dipinjam';
        case 'kembali':
            return 'Barang dikembalikan';
        case 'pindah':
            return 'Perpindahan lokasi';
        case 'perbaikan':
            return 'Barang masuk perbaikan';
        case 'rusak':
            return 'Update kondisi barang';
        case 'keluarmasuk':
            return 'Penyesuaian stok barang';
        case 'update':
        default:
            if ($kondisiBefore !== $kondisiAfter) {
                return 'Update kondisi barang';
            }
            if ($lokasiBefore !== $lokasiAfter) {
                return 'Perpindahan lokasi';
            }
            if ($userBefore !== $userAfter) {
                return 'Update user barang';
            }
            if ($statusBefore !== $statusAfter) {
                return 'Update status barang';
            }
            return 'Update data barang';
    }
}

function resolve_tracking_actor_id($data) {
    $actorId = nullable_int_id($data['id_user_changed'] ?? null);
    if ($actorId !== null) {
        return $actorId;
    }

    $actorId = nullable_int_id($data['actor_id'] ?? null);
    if ($actorId !== null) {
        return $actorId;
    }

    return current_user_id();
}

function resolve_tracking_actor_name_snapshot($koneksi, $data) {
    $snapshot = trim((string) ($data['actor_name_snapshot'] ?? ($data['actor_nama_snapshot'] ?? '')));
    if ($snapshot !== '') {
        return $snapshot;
    }

    $actorId = resolve_tracking_actor_id($data);
    if ($actorId !== null) {
        $actorName = get_user_name_by_id($koneksi, $actorId);
        if (trim((string) ($actorName ?? '')) !== '') {
            return $actorName;
        }
    }

    return get_current_user_name($koneksi) ?? 'System';
}

function log_tracking_history($koneksi, $data) {
    if (!schema_table_exists_now($koneksi, 'tracking_barang')) {
        return false;
    }

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
    $activityType = infer_tracking_activity_type($data, 'update');
    $noteValue = trim((string) ($data['note'] ?? ($data['catatan'] ?? '')));
    if ($noteValue === '') {
        $noteValue = build_tracking_note_fallback($data, 'barang');
    }
    $actorId = resolve_tracking_actor_id($data);
    $actorNameSnapshot = resolve_tracking_actor_name_snapshot($koneksi, $data);

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

    $stringFields = [
        'status_sebelum' => $data['status_sebelum'] ?? null,
        'status_sesudah' => $data['status_sesudah'] ?? null,
        'kondisi_sebelum' => $data['kondisi_sebelum'] ?? null,
        'kondisi_sesudah' => $data['kondisi_sesudah'] ?? null,
        'lokasi_sebelum' => $data['lokasi_sebelum'] ?? null,
        'lokasi_sesudah' => $data['lokasi_sesudah'] ?? null,
        'activity_type' => $activityType,
        'note' => $noteValue,
        'actor_name_snapshot' => $actorNameSnapshot,
    ];

    foreach ($stringFields as $field => $fieldValue) {
        if (isset($colSet[$field])) {
            $columns[] = $field;
            $values[] = $fieldValue;
            $types .= 's';
        }
    }

    foreach (['id_user_sebelum','id_user_sesudah','id_user_terkait','id_user_changed','id_user'] as $field) {
        if (isset($colSet[$field])) {
            if ($field === 'id_user' && isset($colSet['id_user_terkait'])) {
                // Prioritize explicit related user when available
                continue;
            }
            $columns[] = $field;
            if ($field === 'id_user_changed') {
                $values[] = $actorId;
            } elseif ($field === 'id_user') {
                $values[] = $data['id_user'] ?? ($data['id_user_terkait'] ?? $data['id_user_sesudah'] ?? $data['id_user_sebelum'] ?? null);
            } else {
                $values[] = $data[$field] ?? null;
            }
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
    if (!schema_table_exists_now($koneksi, 'riwayat_unit_barang')) {
        return false;
    }

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
    $activityType = infer_tracking_activity_type($data, 'update');
    $noteValue = trim((string) ($data['note'] ?? ($data['catatan'] ?? '')));
    if ($noteValue === '') {
        $noteValue = build_tracking_note_fallback($data, 'unit');
    }
    $actorId = resolve_tracking_actor_id($data);
    $relatedUserId = nullable_int_id($data['id_user_terkait'] ?? ($data['id_user_sesudah'] ?? $data['id_user'] ?? null));
    $actorNameSnapshot = resolve_tracking_actor_name_snapshot($koneksi, $data);

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
    foreach (['status_sebelum','status_sesudah','kondisi_sebelum','kondisi_sesudah','lokasi_sebelum','lokasi_sesudah'] as $field) {
        if (isset($colSet[$field])) {
            $columns[] = $field;
            $values[] = $data[$field] ?? null;
            $types .= 's';
        }
    }

    $activityColumn = isset($colSet['aktivitas']) ? 'aktivitas' : (isset($colSet['activity_type']) ? 'activity_type' : null);
    if ($activityColumn !== null) {
        $columns[] = $activityColumn;
        $values[] = $activityType;
        $types .= 's';
    }

    $noteColumn = isset($colSet['catatan']) ? 'catatan' : (isset($colSet['note']) ? 'note' : null);
    if ($noteColumn !== null) {
        $columns[] = $noteColumn;
        $values[] = $noteValue;
        $types .= 's';
    }

    if (isset($colSet['actor_name_snapshot'])) {
        $columns[] = 'actor_name_snapshot';
        $values[] = $actorNameSnapshot;
        $types .= 's';
    }

    foreach (['id_user_sebelum','id_user_sesudah'] as $field) {
        if (isset($colSet[$field])) {
            $columns[] = $field;
            $values[] = $data[$field] ?? null;
            $types .= 'i';
        }
    }

    if (isset($colSet['id_user_changed'])) {
        $columns[] = 'id_user_changed';
        $values[] = $actorId;
        $types .= 'i';
    }
    if (isset($colSet['id_user_terkait'])) {
        $columns[] = 'id_user_terkait';
        $values[] = $relatedUserId;
        $types .= 'i';
    }
    if (isset($colSet['id_user'])) {
        $columns[] = 'id_user';
        $values[] = isset($colSet['id_user_terkait']) ? $relatedUserId : $actorId;
        $types .= 'i';
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
    return get_tracking_activity_label($value);
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

function current_user_id() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : null;
}

function get_current_user_name($koneksi) {
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $userId = current_user_id();
    $cache = $userId !== null ? get_user_name_by_id($koneksi, $userId) : null;

    return $cache;
}

function get_safe_user_filter_sql($koneksi, $alias = 'user') {
    $safeAlias = preg_replace('/[^A-Za-z0-9_]/', '', (string) $alias);
    if ($safeAlias === '') {
        $safeAlias = 'user';
    }

    $conditions = [];
    if (schema_has_column_now($koneksi, 'user', 'deleted_at')) {
        $conditions[] = "`$safeAlias`.deleted_at IS NULL";
    }
    if (schema_has_column_now($koneksi, 'user', 'status')) {
        $conditions[] = "`$safeAlias`.status = 'aktif'";
    }

    return empty($conditions) ? '' : (' AND ' . implode(' AND ', $conditions));
}

function get_active_user_rows($koneksi) {
    $sql = "SELECT id_user, nama, username, role";
    if (schema_has_column_now($koneksi, 'user', 'status')) {
        $sql .= ", status";
    }
    if (schema_has_column_now($koneksi, 'user', 'kategori_user')) {
        $sql .= ", kategori_user";
    }
    if (schema_has_column_now($koneksi, 'user', 'bidang_id')) {
        $sql .= ", bidang_id";
    }
    $sql .= " FROM user WHERE 1 = 1";
    if (schema_has_column_now($koneksi, 'user', 'deleted_at')) {
        $sql .= " AND deleted_at IS NULL";
    }
    if (schema_has_column_now($koneksi, 'user', 'status')) {
        $sql .= " AND status = 'aktif'";
    }
    $sql .= " ORDER BY nama ASC";

    $rows = [];
    $result = $koneksi->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function generate_reference_code($koneksi, $tableName, $columnName, $prefix) {
    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
    $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
    $prefix = strtoupper(trim((string) $prefix));

    if ($safeTable === '' || $safeColumn === '' || $prefix === '') {
        return null;
    }

    $datePrefix = date('Ymd');
    $likeValue = $koneksi->real_escape_string($prefix . '-' . $datePrefix . '-%');
    $query = "SELECT `$safeColumn` AS kode_ref
              FROM `$safeTable`
              WHERE `$safeColumn` LIKE '$likeValue'
              ORDER BY `$safeColumn` DESC
              LIMIT 1";

    $lastCode = null;
    $result = $koneksi->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $lastCode = $row['kode_ref'] ?? null;
    }

    $nextNumber = 1;
    if ($lastCode && preg_match('/-(\d{4})$/', $lastCode, $matches)) {
        $nextNumber = intval($matches[1]) + 1;
    }

    return sprintf('%s-%s-%04d', $prefix, $datePrefix, $nextNumber);
}

function ensure_directory_exists($path) {
    if (!is_dir($path)) {
        return @mkdir($path, 0777, true);
    }

    return true;
}

function store_uploaded_inventory_document($fileField, $targetFolder, $prefix = 'DOC') {
    if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
        return null;
    }

    $fileInfo = $_FILES[$fileField];
    if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($fileInfo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('Upload dokumen gagal diproses.');
    }

    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $originalName = (string) ($fileInfo['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Format dokumen tidak didukung. Gunakan PDF/JPG/PNG/WEBP.');
    }

    $baseDir = realpath(__DIR__ . '/..');
    if ($baseDir === false) {
        throw new Exception('Direktori project tidak ditemukan.');
    }

    $targetFolder = trim(str_replace(['..\\', '../'], '', $targetFolder), "\\/");
    $destinationDir = $baseDir . DIRECTORY_SEPARATOR . $targetFolder;
    if (!ensure_directory_exists($destinationDir)) {
        throw new Exception('Folder dokumen gagal dibuat.');
    }

    $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper((string) $prefix));
    if ($safePrefix === '') {
        $safePrefix = 'DOC';
    }

    $fileName = $safePrefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destinationPath = $destinationDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($fileInfo['tmp_name'], $destinationPath)) {
        throw new Exception('Dokumen gagal disimpan ke server.');
    }

    return str_replace('\\', '/', $targetFolder . '/' . $fileName);
}

function get_stok_gudang_qty($koneksi, $id_gudang, $id_produk) {
    $stmt = $koneksi->prepare("SELECT jumlah_stok FROM stokgudang WHERE id_gudang = ? AND id_produk = ? LIMIT 1");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('ii', $id_gudang, $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return intval($row['jumlah_stok'] ?? 0);
}

function upsert_stok_gudang_quantity($koneksi, $id_gudang, $id_produk, $deltaQty) {
    $id_gudang = intval($id_gudang);
    $id_produk = intval($id_produk);
    $deltaQty = intval($deltaQty);

    if ($id_gudang < 1 || $id_produk < 1 || $deltaQty === 0) {
        return true;
    }

    $currentQty = get_stok_gudang_qty($koneksi, $id_gudang, $id_produk);
    $newQty = $currentQty + $deltaQty;
    if ($newQty < 0) {
        return false;
    }

    $existsStmt = $koneksi->prepare("SELECT id_stok_gudang FROM stokgudang WHERE id_gudang = ? AND id_produk = ? LIMIT 1");
    if (!$existsStmt) {
        return false;
    }

    $existsStmt->bind_param('ii', $id_gudang, $id_produk);
    $existsStmt->execute();
    $existsResult = $existsStmt->get_result();
    $existsRow = $existsResult ? $existsResult->fetch_assoc() : null;

    if ($existsRow) {
        $updateStmt = $koneksi->prepare("UPDATE stokgudang SET jumlah_stok = ? WHERE id_stok_gudang = ?");
        if (!$updateStmt) {
            return false;
        }
        $stokGudangId = intval($existsRow['id_stok_gudang']);
        $updateStmt->bind_param('ii', $newQty, $stokGudangId);
        return $updateStmt->execute();
    }

    $insertStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
    if (!$insertStmt) {
        return false;
    }
    $insertStmt->bind_param('iii', $id_gudang, $id_produk, $newQty);
    return $insertStmt->execute();
}

function save_histori_log_entry($koneksi, $data) {
    if (!schema_table_exists_now($koneksi, 'histori_log')) {
        return false;
    }

    $refType = trim((string) ($data['ref_type'] ?? 'tracking'));
    $refId = nullable_int_id($data['ref_id'] ?? null);
    $eventType = trim((string) ($data['event_type'] ?? 'updated'));
    $produkId = nullable_int_id($data['produk_id'] ?? null);
    $unitBarangId = nullable_int_id($data['unit_barang_id'] ?? null);
    $gudangId = nullable_int_id($data['gudang_id'] ?? null);
    $userId = nullable_int_id($data['user_id'] ?? current_user_id());
    $userNameSnapshot = trim((string) ($data['user_name_snapshot'] ?? get_current_user_name($koneksi) ?? 'System'));
    $targetUserId = nullable_int_id($data['target_user_id'] ?? null);
    $targetUserNameSnapshot = trim((string) ($data['target_user_name_snapshot'] ?? ''));
    $deskripsi = trim((string) ($data['deskripsi'] ?? ''));
    $metaJson = $data['meta_json'] ?? null;

    if ($refId === null || $userNameSnapshot === '') {
        return false;
    }

    if (is_array($metaJson)) {
        $metaJson = json_encode($metaJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $metaJson = $metaJson !== null ? (string) $metaJson : null;
    $targetUserNameSnapshot = $targetUserNameSnapshot !== '' ? $targetUserNameSnapshot : null;
    $deskripsi = $deskripsi !== '' ? $deskripsi : null;

    $stmt = $koneksi->prepare(
        "INSERT INTO histori_log (
            ref_type, ref_id, event_type, produk_id, unit_barang_id, gudang_id, user_id,
            user_name_snapshot, target_user_id, target_user_name_snapshot, deskripsi, meta_json, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'sisiiiisisss',
        $refType,
        $refId,
        $eventType,
        $produkId,
        $unitBarangId,
        $gudangId,
        $userId,
        $userNameSnapshot,
        $targetUserId,
        $targetUserNameSnapshot,
        $deskripsi,
        $metaJson
    );

    return $stmt->execute();
}

function fetch_histori_logs($koneksi, $filters = [], $limit = 100) {
    if (!schema_table_exists_now($koneksi, 'histori_log')) {
        return [];
    }

    $sql = "SELECT hl.*,
                   u.nama AS current_user_name,
                   tu.nama AS current_target_user_name
            FROM histori_log hl
            LEFT JOIN user u ON hl.user_id = u.id_user
            LEFT JOIN user tu ON hl.target_user_id = tu.id_user
            WHERE 1 = 1";
    $types = '';
    $values = [];

    foreach (['ref_id', 'produk_id', 'unit_barang_id', 'gudang_id', 'user_id', 'target_user_id'] as $field) {
        if (isset($filters[$field]) && $filters[$field] !== null && $filters[$field] !== '') {
            $sql .= " AND hl.$field = ?";
            $types .= 'i';
            $values[] = intval($filters[$field]);
        }
    }

    foreach (['ref_type', 'event_type'] as $field) {
        if (!empty($filters[$field])) {
            $sql .= " AND hl.$field = ?";
            $types .= 's';
            $values[] = trim((string) $filters[$field]);
        }
    }

    $limit = max(1, intval($limit));
    $sql .= " ORDER BY hl.created_at DESC, hl.id DESC LIMIT ?";
    $types .= 'i';
    $values[] = $limit;

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    return $rows;
}

function soft_delete_inventory_user($koneksi, $id_user) {
    if (!schema_table_exists_now($koneksi, 'user')) {
        return false;
    }

    $id_user = intval($id_user);
    if ($id_user < 1) {
        return false;
    }

    $selectColumns = ['id_user', 'nama', 'username'];
    if (schema_has_column_now($koneksi, 'user', 'email')) {
        $selectColumns[] = 'email';
    }

    $stmt = $koneksi->prepare("SELECT " . implode(', ', $selectColumns) . " FROM user WHERE id_user = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    if (!$user) {
        return false;
    }

    if (!schema_has_column_now($koneksi, 'user', 'deleted_at')) {
        $deleteStmt = $koneksi->prepare("DELETE FROM user WHERE id_user = ?");
        if (!$deleteStmt) {
            return false;
        }
        $deleteStmt->bind_param('i', $id_user);
        return $deleteStmt->execute();
    }

    $deletedSuffix = '__deleted_' . $id_user . '_' . date('YmdHis');
    $newUsername = substr((string) $user['username'], 0, 180) . $deletedSuffix;

    $setParts = ["deleted_at = NOW()", "username = ?"];
    $bindTypes = 's';
    $bindValues = [$newUsername];

    if (schema_has_column_now($koneksi, 'user', 'email')) {
        $newEmail = trim((string) ($user['email'] ?? ''));
        $newEmail = $newEmail !== '' ? (substr($newEmail, 0, 180) . $deletedSuffix) : null;
        $setParts[] = "email = ?";
        $bindTypes .= 's';
        $bindValues[] = $newEmail;
    }
    if (schema_has_column_now($koneksi, 'user', 'status')) {
        $setParts[] = "status = 'nonaktif'";
    }
    if (schema_has_column_now($koneksi, 'user', 'updated_at')) {
        $setParts[] = "updated_at = NOW()";
    }

    $updateSql = "UPDATE user SET " . implode(', ', $setParts) . " WHERE id_user = ?";
    $updateStmt = $koneksi->prepare($updateSql);
    if (!$updateStmt) {
        return false;
    }

    if (schema_has_column_now($koneksi, 'user', 'email')) {
        $newEmail = $bindValues[1] ?? null;
        $updateStmt->bind_param('ssi', $newUsername, $newEmail, $id_user);
    } else {
        $updateStmt->bind_param('si', $newUsername, $id_user);
    }
    return $updateStmt->execute();
}

function ensure_user_management_schema($koneksi) {
    static $done = false;

    if ($done) {
        return;
    }
    $done = true;

    if (!schema_table_exists_now($koneksi, 'user')) {
        return;
    }

    $koneksi->query("CREATE TABLE IF NOT EXISTS bidang (
        id INT NOT NULL AUTO_INCREMENT,
        nama_bidang VARCHAR(150) NOT NULL,
        kode_bidang VARCHAR(50) DEFAULT NULL,
        status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_bidang_nama (nama_bidang),
        UNIQUE KEY uniq_bidang_kode (kode_bidang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (!schema_has_column_now($koneksi, 'user', 'created_at')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }
    if (!schema_has_column_now($koneksi, 'user', 'updated_at')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }
    if (!schema_has_column_now($koneksi, 'user', 'deleted_at')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN deleted_at DATETIME NULL AFTER updated_at");
    }
    if (!schema_has_column_now($koneksi, 'user', 'status')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' AFTER role");
    }
    if (!schema_has_column_now($koneksi, 'user', 'kategori_user')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN kategori_user ENUM('staff','dosen','mahasiswa','umum') NOT NULL DEFAULT 'umum' AFTER status");
    }
    if (!schema_has_column_now($koneksi, 'user', 'bidang_id')) {
        $koneksi->query("ALTER TABLE user ADD COLUMN bidang_id INT NULL AFTER kategori_user");
    }

    if (schema_has_column_now($koneksi, 'user', 'email')) {
        $koneksi->query("ALTER TABLE user MODIFY COLUMN email VARCHAR(255) NULL DEFAULT NULL");
    }

    if (schema_has_column_now($koneksi, 'user', 'role')) {
        $koneksi->query("ALTER TABLE user MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'");
        $koneksi->query("UPDATE user SET role = 'petugas' WHERE LOWER(TRIM(COALESCE(role, ''))) IN ('leader', 'operator')");
        $koneksi->query("UPDATE user SET role = 'user' WHERE LOWER(TRIM(COALESCE(role, ''))) IN ('viewer', 'pemohon') OR role IS NULL OR TRIM(COALESCE(role, '')) = ''");
        $koneksi->query("ALTER TABLE user MODIFY COLUMN role ENUM('admin','petugas','user') NOT NULL DEFAULT 'user'");
    }

    if (schema_has_column_now($koneksi, 'user', 'status')) {
        $koneksi->query("ALTER TABLE user MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'aktif'");
        $koneksi->query("UPDATE user SET status = 'nonaktif' WHERE LOWER(TRIM(COALESCE(status, ''))) IN ('inactive', 'disabled', 'deleted')");
        if (schema_has_column_now($koneksi, 'user', 'deleted_at')) {
            $koneksi->query("UPDATE user SET status = 'nonaktif' WHERE deleted_at IS NOT NULL");
        }
        $koneksi->query("UPDATE user SET status = 'aktif' WHERE status IS NULL OR TRIM(COALESCE(status, '')) = ''");
        $koneksi->query("ALTER TABLE user MODIFY COLUMN status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'");
    }

    if (schema_has_column_now($koneksi, 'user', 'kategori_user')) {
        $koneksi->query("ALTER TABLE user MODIFY COLUMN kategori_user VARCHAR(20) NOT NULL DEFAULT 'umum'");
        $koneksi->query("UPDATE user SET kategori_user = 'staff' WHERE LOWER(TRIM(COALESCE(kategori_user, ''))) IN ('pegawai')");
        $koneksi->query("UPDATE user SET kategori_user = 'dosen' WHERE LOWER(TRIM(COALESCE(kategori_user, ''))) IN ('lecturer')");
        $koneksi->query("UPDATE user SET kategori_user = 'mahasiswa' WHERE LOWER(TRIM(COALESCE(kategori_user, ''))) IN ('student')");
        $koneksi->query("UPDATE user SET kategori_user = 'umum' WHERE kategori_user IS NULL OR TRIM(COALESCE(kategori_user, '')) = ''");
        $koneksi->query("ALTER TABLE user MODIFY COLUMN kategori_user ENUM('staff','dosen','mahasiswa','umum') NOT NULL DEFAULT 'umum'");
    }

    if (schema_has_column_now($koneksi, 'user', 'kategori_user') && schema_has_column_now($koneksi, 'user', 'bidang_id')) {
        $koneksi->query("UPDATE user SET bidang_id = NULL WHERE kategori_user <> 'staff'");
    }

    if (!schema_has_unique_index_now($koneksi, 'user', 'username')) {
        $koneksi->query("ALTER TABLE user ADD UNIQUE KEY uniq_user_username (username)");
    }
    if (!schema_index_exists_now($koneksi, 'user', 'idx_user_bidang_id')) {
        $koneksi->query("ALTER TABLE user ADD KEY idx_user_bidang_id (bidang_id)");
    }

    if (schema_has_column_now($koneksi, 'user', 'bidang_id') && schema_table_exists_now($koneksi, 'bidang')) {
        $koneksi->query("UPDATE user u LEFT JOIN bidang b ON u.bidang_id = b.id SET u.bidang_id = NULL WHERE u.bidang_id IS NOT NULL AND b.id IS NULL");
        if (!schema_foreign_key_exists_now($koneksi, 'user', 'fk_user_bidang')) {
            $koneksi->query("ALTER TABLE user ADD CONSTRAINT fk_user_bidang FOREIGN KEY (bidang_id) REFERENCES bidang(id) ON DELETE SET NULL ON UPDATE CASCADE");
        }
    }
}

function ensure_priority_one_schema($koneksi) {
    static $done = false;

    if ($done) {
        return;
    }
    $done = true;

    ensure_user_management_schema($koneksi);

    if (schema_table_exists_now($koneksi, 'produk') && !schema_has_column_now($koneksi, 'produk', 'deskripsi')) {
        $koneksi->query("ALTER TABLE produk ADD COLUMN deskripsi TEXT NULL AFTER nama_produk");
    }

    if (schema_table_exists_now($koneksi, 'produk') && !schema_has_column_now($koneksi, 'produk', 'harga_default')) {
        $koneksi->query("ALTER TABLE produk ADD COLUMN harga_default DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER satuan");
    }

    if (
        schema_table_exists_now($koneksi, 'produk')
        && schema_has_column_now($koneksi, 'produk', 'harga_default')
        && schema_has_column_now($koneksi, 'produk', 'harga_satuan')
    ) {
        $koneksi->query("UPDATE produk SET harga_default = harga_satuan WHERE harga_default <= 0 AND harga_satuan > 0");
    }

    if (schema_table_exists_now($koneksi, 'stoktransaksi') && !schema_has_column_now($koneksi, 'stoktransaksi', 'harga_satuan')) {
        $koneksi->query("ALTER TABLE stoktransaksi ADD COLUMN harga_satuan DECIMAL(15,2) NULL DEFAULT NULL AFTER jumlah");
    }

    if (
        schema_table_exists_now($koneksi, 'stoktransaksi')
        && schema_has_column_now($koneksi, 'stoktransaksi', 'harga_satuan')
        && schema_table_exists_now($koneksi, 'produk')
        && schema_has_column_now($koneksi, 'produk', 'harga_default')
        && schema_has_column_now($koneksi, 'produk', 'harga_satuan')
    ) {
        $koneksi->query(
            "UPDATE stoktransaksi st
             INNER JOIN produk p ON p.id_produk = st.id_produk
             SET st.harga_satuan = COALESCE(NULLIF(p.harga_default, 0), p.harga_satuan, 0)
             WHERE st.harga_satuan IS NULL"
        );
    }

    if (!schema_table_exists_now($koneksi, 'catatan_inventaris')) {
        $koneksi->query(
            "CREATE TABLE catatan_inventaris (
                id_catatan INT NOT NULL AUTO_INCREMENT,
                tipe_target ENUM('produk','transaksi','unit','gudang') NOT NULL DEFAULT 'produk',
                kategori_catatan ENUM('umum','kerusakan','selisih','servis','transaksi','bug') NOT NULL DEFAULT 'umum',
                judul VARCHAR(150) DEFAULT NULL,
                catatan TEXT NOT NULL,
                id_produk INT DEFAULT NULL,
                id_transaksi INT DEFAULT NULL,
                id_unit_barang INT DEFAULT NULL,
                id_gudang INT DEFAULT NULL,
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id_catatan),
                INDEX idx_catatan_target (tipe_target, kategori_catatan),
                INDEX idx_catatan_produk (id_produk),
                INDEX idx_catatan_transaksi (id_transaksi),
                INDEX idx_catatan_unit (id_unit_barang),
                INDEX idx_catatan_gudang (id_gudang),
                INDEX idx_catatan_created_by (created_by),
                INDEX idx_catatan_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    if (!schema_table_exists_now($koneksi, 'activity_log')) {
        $koneksi->query(
            "CREATE TABLE activity_log (
                id_log INT NOT NULL AUTO_INCREMENT,
                id_user INT DEFAULT NULL,
                role_user VARCHAR(50) DEFAULT NULL,
                action_name VARCHAR(100) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT DEFAULT NULL,
                entity_label VARCHAR(255) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                id_produk INT DEFAULT NULL,
                id_transaksi INT DEFAULT NULL,
                id_unit_barang INT DEFAULT NULL,
                id_gudang INT DEFAULT NULL,
                metadata_json LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id_log),
                INDEX idx_activity_entity (entity_type, entity_id),
                INDEX idx_activity_action (action_name),
                INDEX idx_activity_user (id_user),
                INDEX idx_activity_produk (id_produk),
                INDEX idx_activity_transaksi (id_transaksi),
                INDEX idx_activity_unit (id_unit_barang),
                INDEX idx_activity_gudang (id_gudang),
                INDEX idx_activity_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

function log_activity($koneksi, $data) {
    if (!schema_table_exists_now($koneksi, 'activity_log')) {
        return false;
    }

    $idUser = nullable_int_id($data['id_user'] ?? null);
    $roleUser = isset($data['role_user']) ? normalize_user_role($data['role_user']) : get_current_user_role();
    $actionName = trim((string) ($data['action_name'] ?? 'aktivitas'));
    $entityType = trim((string) ($data['entity_type'] ?? 'sistem'));
    $entityId = nullable_int_id($data['entity_id'] ?? null);
    $entityLabel = trim((string) ($data['entity_label'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $idProduk = nullable_int_id($data['id_produk'] ?? null);
    $idTransaksi = nullable_int_id($data['id_transaksi'] ?? null);
    $idUnit = nullable_int_id($data['id_unit_barang'] ?? null);
    $idGudang = nullable_int_id($data['id_gudang'] ?? null);
    $actorNameSnapshot = trim((string) ($data['actor_name_snapshot'] ?? get_current_user_name($koneksi) ?? ''));
    $metadata = $data['metadata_json'] ?? null;

    if (is_array($metadata)) {
        $metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $metadata = $metadata !== null ? (string) $metadata : null;

    $columns = [
        'id_user',
        'role_user',
        'action_name',
        'entity_type',
        'entity_id',
        'entity_label',
        'description',
    ];
    $types = 'isssiss';
    $values = [
        $idUser,
        $roleUser,
        $actionName,
        $entityType,
        $entityId,
        $entityLabel,
        $description,
    ];

    if (schema_has_column_now($koneksi, 'activity_log', 'actor_name_snapshot')) {
        $columns[] = 'actor_name_snapshot';
        $types .= 's';
        $values[] = $actorNameSnapshot !== '' ? $actorNameSnapshot : null;
    }

    foreach ([
        'id_produk' => $idProduk,
        'id_transaksi' => $idTransaksi,
        'id_unit_barang' => $idUnit,
        'id_gudang' => $idGudang,
    ] as $column => $value) {
        if (schema_has_column_now($koneksi, 'activity_log', $column)) {
            $columns[] = $column;
            $types .= 'i';
            $values[] = $value;
        }
    }

    if (schema_has_column_now($koneksi, 'activity_log', 'metadata_json')) {
        $columns[] = 'metadata_json';
        $types .= 's';
        $values[] = $metadata;
    }

    $query = "INSERT INTO activity_log (" . implode(', ', $columns) . ", created_at)
              VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", NOW())";

    $stmt = $koneksi->prepare($query);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param($types, ...$values);

    return $stmt->execute();
}

function save_inventory_note($koneksi, $data) {
    if (!schema_table_exists_now($koneksi, 'catatan_inventaris')) {
        return false;
    }

    $tipeTarget = trim((string) ($data['tipe_target'] ?? 'produk'));
    $kategoriCatatan = trim((string) ($data['kategori_catatan'] ?? 'umum'));
    $judul = trim((string) ($data['judul'] ?? ''));
    $catatan = trim((string) ($data['catatan'] ?? ''));

    if ($catatan === '') {
        return false;
    }

    $idProduk = nullable_int_id($data['id_produk'] ?? null);
    $idTransaksi = nullable_int_id($data['id_transaksi'] ?? null);
    $idUnit = nullable_int_id($data['id_unit_barang'] ?? null);
    $idGudang = nullable_int_id($data['id_gudang'] ?? null);
    $createdBy = nullable_int_id($data['created_by'] ?? null);
    $createdByName = trim((string) ($data['created_by_name_snapshot'] ?? get_current_user_name($koneksi) ?? ''));
    $judul = $judul !== '' ? $judul : null;

    $columns = ['tipe_target', 'kategori_catatan', 'judul', 'catatan'];
    $types = 'ssss';
    $values = [$tipeTarget, $kategoriCatatan, $judul, $catatan];

    foreach ([
        'id_produk' => $idProduk,
        'id_transaksi' => $idTransaksi,
        'id_unit_barang' => $idUnit,
        'id_gudang' => $idGudang,
        'created_by' => $createdBy,
    ] as $column => $value) {
        if (schema_has_column_now($koneksi, 'catatan_inventaris', $column)) {
            $columns[] = $column;
            $types .= 'i';
            $values[] = $value;
        }
    }

    if (schema_has_column_now($koneksi, 'catatan_inventaris', 'created_by_name_snapshot')) {
        $columns[] = 'created_by_name_snapshot';
        $types .= 's';
        $values[] = $createdByName !== '' ? $createdByName : null;
    }

    $query = "INSERT INTO catatan_inventaris (" . implode(', ', $columns) . ", created_at)
              VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", NOW())";

    $stmt = $koneksi->prepare($query);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param($types, ...$values);

    return $stmt->execute();
}

function fetch_inventory_notes($koneksi, $filters = [], $limit = 50) {
    if (!schema_table_exists_now($koneksi, 'catatan_inventaris')) {
        return [];
    }

    $creatorNameSelect = schema_has_column_now($koneksi, 'catatan_inventaris', 'created_by_name_snapshot')
        ? "COALESCE(ci.created_by_name_snapshot, u.nama) AS nama_pembuat"
        : "u.nama AS nama_pembuat";

    $sql = "SELECT ci.*, $creatorNameSelect, st.no_invoice
            FROM catatan_inventaris ci
            LEFT JOIN user u ON ci.created_by = u.id_user
            LEFT JOIN stoktransaksi st ON ci.id_transaksi = st.id_transaksi
            WHERE 1 = 1";
    $types = '';
    $values = [];

    foreach (['id_produk', 'id_transaksi', 'id_unit_barang', 'id_gudang'] as $field) {
        if (isset($filters[$field]) && $filters[$field] !== null) {
            $sql .= " AND ci.$field = ?";
            $types .= 'i';
            $values[] = intval($filters[$field]);
        }
    }

    if (!empty($filters['tipe_target'])) {
        $sql .= " AND ci.tipe_target = ?";
        $types .= 's';
        $values[] = trim((string) $filters['tipe_target']);
    }

    $limit = max(1, intval($limit));
    $sql .= " ORDER BY ci.created_at DESC, ci.id_catatan DESC LIMIT ?";
    $types .= 'i';
    $values[] = $limit;

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function fetch_activity_logs($koneksi, $filters = [], $limit = 100) {
    if (!schema_table_exists_now($koneksi, 'activity_log')) {
        return [];
    }

    $actorNameSelect = schema_has_column_now($koneksi, 'activity_log', 'actor_name_snapshot')
        ? "COALESCE(al.actor_name_snapshot, u.nama) AS actor_name"
        : "u.nama AS actor_name";

    $sql = "SELECT al.*, $actorNameSelect
            FROM activity_log al
            LEFT JOIN user u ON al.id_user = u.id_user
            WHERE 1 = 1";
    $types = '';
    $values = [];

    foreach (['id_produk', 'id_transaksi', 'id_unit_barang', 'id_gudang', 'entity_id'] as $field) {
        if (isset($filters[$field]) && $filters[$field] !== null) {
            $sql .= " AND al.$field = ?";
            $types .= 'i';
            $values[] = intval($filters[$field]);
        }
    }

    foreach (['entity_type', 'action_name'] as $field) {
        if (!empty($filters[$field])) {
            $sql .= " AND al.$field = ?";
            $types .= 's';
            $values[] = trim((string) $filters[$field]);
        }
    }

    $limit = max(1, intval($limit));
    $sql .= " ORDER BY al.created_at DESC, al.id_log DESC LIMIT ?";
    $types .= 'i';
    $values[] = $limit;

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function fetch_mutasi_barang_rows($koneksi, $filters = [], $limit = 100) {
    if (!schema_table_exists_now($koneksi, 'mutasi_barang')) {
        return [];
    }

    $sql = "SELECT mb.*,
                   ga.nama_gudang AS nama_gudang_asal,
                   gt.nama_gudang AS nama_gudang_tujuan
            FROM mutasi_barang mb
            LEFT JOIN gudang ga ON mb.gudang_asal_id = ga.id_gudang
            LEFT JOIN gudang gt ON mb.gudang_tujuan_id = gt.id_gudang
            WHERE 1 = 1";
    $types = '';
    $values = [];

    foreach (['gudang_asal_id', 'gudang_tujuan_id'] as $field) {
        if (!empty($filters[$field])) {
            $sql .= " AND mb.$field = ?";
            $types .= 'i';
            $values[] = intval($filters[$field]);
        }
    }

    foreach (['status', 'jenis_barang'] as $field) {
        if (!empty($filters[$field])) {
            $sql .= " AND mb.$field = ?";
            $types .= 's';
            $values[] = trim((string) $filters[$field]);
        }
    }

    if (!empty($filters['tanggal_dari'])) {
        $sql .= " AND DATE(mb.tanggal_mutasi) >= ?";
        $types .= 's';
        $values[] = trim((string) $filters['tanggal_dari']);
    }

    if (!empty($filters['tanggal_sampai'])) {
        $sql .= " AND DATE(mb.tanggal_mutasi) <= ?";
        $types .= 's';
        $values[] = trim((string) $filters['tanggal_sampai']);
    }

    if (!empty($filters['produk_id']) && schema_table_exists_now($koneksi, 'mutasi_barang_detail')) {
        $sql .= " AND EXISTS (
                    SELECT 1
                    FROM mutasi_barang_detail md
                    WHERE md.mutasi_id = mb.id
                      AND md.produk_id = ?
                 )";
        $types .= 'i';
        $values[] = intval($filters['produk_id']);
    }

    $limit = max(1, intval($limit));
    $sql .= " ORDER BY mb.tanggal_mutasi DESC, mb.id DESC LIMIT ?";
    $types .= 'i';
    $values[] = $limit;

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    return $rows;
}

function fetch_mutasi_barang_detail_rows($koneksi, $mutasiId) {
    if (!schema_table_exists_now($koneksi, 'mutasi_barang_detail')) {
        return [];
    }

    $mutasiId = intval($mutasiId);
    $sql = "SELECT md.*,
                   p.kode_produk,
                   p.nama_produk,
                   ub.serial_number AS kode_unit
            FROM mutasi_barang_detail md
            LEFT JOIN produk p ON md.produk_id = p.id_produk
            LEFT JOIN unit_barang ub ON md.unit_barang_id = ub.id_unit_barang
            WHERE md.mutasi_id = ?
            ORDER BY md.id ASC";
    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $mutasiId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    return $rows;
}

function fetch_serah_terima_rows($koneksi, $filters = [], $limit = 100) {
    if (!schema_table_exists_now($koneksi, 'serah_terima_barang')) {
        return [];
    }

    $sql = "SELECT stb.*, g.nama_gudang AS nama_gudang_asal
            FROM serah_terima_barang stb
            LEFT JOIN gudang g ON stb.gudang_asal_id = g.id_gudang
            WHERE 1 = 1";
    $types = '';
    $values = [];

    foreach (['status', 'jenis_tujuan'] as $field) {
        if (!empty($filters[$field])) {
            $sql .= " AND stb.$field = ?";
            $types .= 's';
            $values[] = trim((string) $filters[$field]);
        }
    }

    if (!empty($filters['gudang_asal_id'])) {
        $sql .= " AND stb.gudang_asal_id = ?";
        $types .= 'i';
        $values[] = intval($filters['gudang_asal_id']);
    }

    $limit = max(1, intval($limit));
    $sql .= " ORDER BY stb.tanggal_serah_terima DESC, stb.id DESC LIMIT ?";
    $types .= 'i';
    $values[] = $limit;

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    return $rows;
}

function fetch_serah_terima_detail_rows($koneksi, $serahTerimaId) {
    if (!schema_table_exists_now($koneksi, 'serah_terima_detail')) {
        return [];
    }

    $serahTerimaId = intval($serahTerimaId);
    $sql = "SELECT std.*,
                   p.kode_produk,
                   p.nama_produk,
                   ub.serial_number AS kode_unit
            FROM serah_terima_detail std
            LEFT JOIN produk p ON std.produk_id = p.id_produk
            LEFT JOIN unit_barang ub ON std.unit_barang_id = ub.id_unit_barang
            WHERE std.serah_terima_id = ?
            ORDER BY std.id ASC";
    $stmt = $koneksi->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $serahTerimaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    return $rows;
}

ensure_priority_one_schema($koneksi);

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['role'])) {
    $_SESSION['role'] = normalize_user_role($_SESSION['role']);
}

?>
