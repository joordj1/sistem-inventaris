<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "inventaris_pjb";

$koneksi = new mysqli($host, $user, $pass, $db);

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Debug sementara untuk memastikan database aktif
$activeDbResult = $koneksi->query("SELECT DATABASE() AS dbname");
if ($activeDbResult) {
    $activeDbName = $activeDbResult->fetch_assoc()['dbname'];
    log_event('DEBUG', 'DB', 'Database aktif: ' . $activeDbName);
    // Gunakan echo hanya di environment dev, jika ingin dari UI tampilan.
    // echo "DEBUG: Database aktif adalah " . $activeDbName . "\n";
}

function log_event($level, $module, $message) {
    $level = strtoupper(trim((string) $level));
    $module = strtoupper(trim((string) $module));
    $message = trim((string) $message);

    if ($level === '') {
        $level = 'INFO';
    }
    if ($module === '') {
        $module = 'APP';
    }

    $line = sprintf('[%s] [%s] [%s] %s', date('Y-m-d H:i:s'), $level, $module, $message);
    error_log($line);
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
        log_event('WARNING', 'AUTH', 'Akses ditolak (401) - belum login | path=' . ($_SERVER['REQUEST_URI'] ?? '-'));
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
        $requiredRoles = is_array($roles) ? implode(',', array_map('normalize_user_role', $roles)) : normalize_user_role($roles);
        log_event('WARNING', 'AUTH', 'Akses ditolak (403) | user_id=' . intval($_SESSION['id_user'] ?? 0) . ' | role=' . (get_current_user_role() ?? '-') . ' | required=' . $requiredRoles . ' | path=' . ($_SERVER['REQUEST_URI'] ?? '-'));
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

function set_flash_message($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $type = strtolower(trim((string) $type));
    if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
        $type = 'info';
    }

    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => trim((string) $message),
    ];
}

function consume_flash_message() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $flash = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    if (!is_array($flash) || empty($flash['message'])) {
        return null;
    }

    $type = strtolower(trim((string) ($flash['type'] ?? 'info')));
    if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
        $type = 'info';
    }

    return [
        'type' => $type,
        'message' => trim((string) $flash['message']),
    ];
}

function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = trim((string) ($_SESSION['csrf_token'] ?? ''));
    if ($token === '' || strlen($token) < 32) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }

    return $token;
}

function get_request_csrf_token() {
    $posted = trim((string) ($_POST['csrf_token'] ?? ''));
    if ($posted !== '') {
        return $posted;
    }

    $headerToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($headerToken !== '') {
        return $headerToken;
    }

    return '';
}

function validate_csrf_token($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $expected = trim((string) ($_SESSION['csrf_token'] ?? ''));
    if ($expected === '') {
        return false;
    }

    $candidate = $token === null ? get_request_csrf_token() : trim((string) $token);
    if ($candidate === '') {
        return false;
    }

    return hash_equals($expected, $candidate);
}

function csrf_token_input_html() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function inventory_request_expects_json() {
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return strpos($accept, 'application/json') !== false || $xrw === 'xmlhttprequest';
}

function reject_invalid_csrf_request($responseType = 'auto') {
    $resolved = $responseType === 'auto' ? (inventory_request_expects_json() ? 'json' : 'page') : $responseType;
    http_response_code(403);
    log_event('WARNING', 'CSRF', 'Token tidak valid | path=' . ($_SERVER['REQUEST_URI'] ?? '-') . ' | ip=' . get_inventory_client_ip());

    if ($resolved === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Permintaan ditolak (CSRF).']);
        exit;
    }

    echo '<h3>403 Forbidden</h3><p>Permintaan ditolak (CSRF).</p>';
    exit;
}

function get_inventory_client_ip() {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) ($candidate ?? ''));
        if ($candidate === '') {
            continue;
        }

        if (strpos($candidate, ',') !== false) {
            $parts = explode(',', $candidate);
            $candidate = trim((string) ($parts[0] ?? ''));
        }

        if ($candidate !== '') {
            return $candidate;
        }
    }

    return 'unknown';
}

function inventory_rate_limit_check($scope, $maxRequests = 60, $windowSeconds = 60) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $scope = preg_replace('/[^a-zA-Z0-9_\-:]/', '_', (string) $scope);
    $maxRequests = max(1, intval($maxRequests));
    $windowSeconds = max(1, intval($windowSeconds));

    $ip = get_inventory_client_ip();
    $sessionId = session_id();
    $userId = intval($_SESSION['id_user'] ?? 0);
    $fingerprint = hash('sha256', $scope . '|' . $ip . '|' . $sessionId . '|' . $userId);

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inventaris_rate_limit';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return [
            'allowed' => true,
            'remaining' => $maxRequests,
            'retry_after' => 0,
        ];
    }

    $file = $dir . DIRECTORY_SEPARATOR . $fingerprint . '.json';
    $now = time();
    $state = [
        'window_start' => $now,
        'count' => 0,
    ];

    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return [
            'allowed' => true,
            'remaining' => $maxRequests,
            'retry_after' => 0,
        ];
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            return [
                'allowed' => true,
                'remaining' => $maxRequests,
                'retry_after' => 0,
            ];
        }

        $raw = stream_get_contents($fh);
        $parsed = json_decode((string) $raw, true);
        if (is_array($parsed) && isset($parsed['window_start'], $parsed['count'])) {
            $state['window_start'] = intval($parsed['window_start']);
            $state['count'] = intval($parsed['count']);
        }

        if (($now - $state['window_start']) >= $windowSeconds) {
            $state['window_start'] = $now;
            $state['count'] = 0;
        }

        $state['count']++;
        $allowed = $state['count'] <= $maxRequests;
        $retryAfter = $allowed ? 0 : max(1, $windowSeconds - ($now - $state['window_start']));

        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode($state));
        fflush($fh);
        flock($fh, LOCK_UN);

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxRequests - $state['count']),
            'retry_after' => $retryAfter,
        ];
    } finally {
        fclose($fh);
    }
}

if (PHP_SAPI !== 'cli' && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptBase = basename($scriptName);
    if (strpos($scriptName, '/action/') !== false || $scriptBase === 'index.php') {
        if (!validate_csrf_token()) {
            reject_invalid_csrf_request('auto');
        }
    }
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
        'pinjam' => 'dipinjam',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'dipinjam',
        'sedang digunakan' => 'dipinjam',
        'kembali' => 'dikembalikan',
        'dikembalikan' => 'dikembalikan',
        'release' => 'dikembalikan',
        'pindah' => 'mutasi',
        'dipindahkan' => 'mutasi',
        'pindah lokasi' => 'mutasi',
        'mutasi' => 'mutasi',
        'serah terima' => 'serah_terima',
        'serah_terima' => 'serah_terima',
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
        'dipinjam' => 'Dipinjam',
        'dikembalikan' => 'Dikembalikan',
        'mutasi' => 'Mutasi',
        'serah_terima' => 'Serah Terima',
        'perbaikan' => 'Perbaikan',
        'rusak' => 'Rusak',
        'update' => 'Perubahan',
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
        return 'dipinjam';
    }
    if ($userBefore !== null && $userAfter === null) {
        return 'dikembalikan';
    }
    if ($statusBefore !== $statusAfter) {
        if (in_array($statusAfter, ['dipinjam', 'digunakan', 'sedang digunakan'], true)) {
            return 'dipinjam';
        }
        if ($statusAfter === 'tersedia' && in_array($statusBefore, ['dipinjam', 'digunakan', 'sedang digunakan'], true)) {
            return 'dikembalikan';
        }
        if (in_array($statusAfter, ['perbaikan', 'dalam perbaikan'], true)) {
            return 'perbaikan';
        }
        if ($statusAfter === 'rusak') {
            return 'rusak';
        }
    }
    if ($lokasiBefore !== $lokasiAfter && ($lokasiBefore !== '' || $lokasiAfter !== '')) {
        return 'mutasi';
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
        case 'dipinjam':
            return 'Barang dipinjam';
        case 'dikembalikan':
            return 'Barang dikembalikan';
        case 'mutasi':
            return 'Perpindahan lokasi';
        case 'serah_terima':
            return 'Serah terima barang';
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
    $unitId = null;
    if (isset($data['id_unit']) && $data['id_unit'] !== null && $data['id_unit'] !== '') {
        $unitId = intval($data['id_unit']);
    } elseif (isset($data['id_unit_barang']) && $data['id_unit_barang'] !== null && $data['id_unit_barang'] !== '') {
        $unitId = intval($data['id_unit_barang']);
    }

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

    // Allow caller to force a shared transaction timestamp across multiple unit rows.
    foreach (['changed_at', 'created_at'] as $timeField) {
        if (isset($colSet[$timeField]) && isset($data[$timeField]) && trim((string) $data[$timeField]) !== '') {
            $columns[] = $timeField;
            $values[] = trim((string) $data[$timeField]);
            $types .= 's';
        }
    }

    if (isset($colSet['id_unit'])) {
        $columns[] = 'id_unit';
        $values[] = $unitId;
        $types .= 'i';
    }

    if (isset($colSet['id_unit_barang'])) {
        $columns[] = 'id_unit_barang';
        $values[] = $unitId;
        $types .= 'i';
    }

    // Unit-related payload should always carry a valid unit id in tracking.
    $isUnitPayload = array_key_exists('id_unit', $data) || array_key_exists('id_unit_barang', $data);
    if ($isUnitPayload && (isset($colSet['id_unit']) || isset($colSet['id_unit_barang'])) && ($unitId === null || $unitId < 1)) {
        return false;
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

function log_tracking_unit_barang($koneksi, $data) {
    return log_tracking_history($koneksi, $data);
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

function normalize_foundation_barang_status($value) {
    $normalized = strtolower(trim((string) ($value ?? '')));
    $map = [
        'tersedia' => 'tersedia',
        'available' => 'tersedia',
        'dipinjam' => 'dipinjam',
        'dipakai' => 'dipinjam',
        'digunakan' => 'dipinjam',
        'sedang digunakan' => 'dipinjam',
        'dipindahkan' => 'tersedia',
        'pindah' => 'tersedia',
        'rusak' => 'rusak',
        'perbaikan' => 'diperbaiki',
        'dalam perbaikan' => 'diperbaiki',
        'diperbaiki' => 'diperbaiki',
    ];

    return $map[$normalized] ?? 'tersedia';
}

function map_foundation_barang_status_for_storage($value) {
    $normalized = normalize_foundation_barang_status($value);
    $map = [
        'tersedia' => 'tersedia',
        'dipinjam' => 'dipinjam',
        'rusak' => 'rusak',
        'diperbaiki' => 'dalam perbaikan',
    ];

    return $map[$normalized] ?? 'tersedia';
}

function get_foundation_barang_borrower_id($produk) {
    $borrowerId = nullable_int_id($produk['dipinjam_oleh'] ?? null);
    if ($borrowerId !== null) {
        return $borrowerId;
    }

    return nullable_int_id($produk['id_user'] ?? null);
}

function sync_foundation_perbaikan_record($koneksi, $id_produk, $foundationStatus, $data = []) {
    ensure_priority_one_schema($koneksi);

    if (!schema_table_exists_now($koneksi, 'perbaikan_barang')) {
        return false;
    }

    $id_produk = intval($id_produk);
    if ($id_produk < 1) {
        return false;
    }

    $foundationStatus = normalize_foundation_barang_status($foundationStatus);
    $idUnitBarang = nullable_int_id($data['id_unit_barang'] ?? null);
    $actorId = nullable_int_id($data['actor_user_id'] ?? ($data['id_user'] ?? current_user_id()));
    $deskripsi = trim((string) ($data['deskripsi'] ?? ($data['note'] ?? '')));
    $startedAt = trim((string) ($data['tanggal_mulai'] ?? ''));
    $finishedAt = trim((string) ($data['tanggal_selesai'] ?? ''));
    $startedAt = $startedAt !== '' ? $startedAt : date('Y-m-d H:i:s');
    $finishedAt = $finishedAt !== '' ? $finishedAt : date('Y-m-d H:i:s');

    $openSql = "SELECT id_perbaikan
                FROM perbaikan_barang
                WHERE id_produk = ?
                  AND status = 'proses'";
    if ($idUnitBarang !== null && schema_has_column_now($koneksi, 'perbaikan_barang', 'id_unit_barang')) {
        $openSql .= " AND id_unit_barang = ?";
    }
    $openSql .= " ORDER BY id_perbaikan DESC LIMIT 1";

    $stmtOpen = $koneksi->prepare($openSql);
    if (!$stmtOpen) {
        return false;
    }

    if ($idUnitBarang !== null && schema_has_column_now($koneksi, 'perbaikan_barang', 'id_unit_barang')) {
        $stmtOpen->bind_param('ii', $id_produk, $idUnitBarang);
    } else {
        $stmtOpen->bind_param('i', $id_produk);
    }
    $stmtOpen->execute();
    $openResult = $stmtOpen->get_result();
    $openRow = $openResult ? $openResult->fetch_assoc() : null;

    if ($foundationStatus === 'diperbaiki') {
        if ($openRow) {
            return true;
        }

        $insertSql = "INSERT INTO perbaikan_barang (
                id_produk, id_unit_barang, tanggal_mulai, deskripsi, status, created_by, updated_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'proses', ?, ?, NOW(), NOW())";
        $stmtInsert = $koneksi->prepare($insertSql);
        if (!$stmtInsert) {
            return false;
        }

        $stmtInsert->bind_param('iissii', $id_produk, $idUnitBarang, $startedAt, $deskripsi, $actorId, $actorId);
        return $stmtInsert->execute();
    }

    if (!$openRow) {
        return false;
    }

    if (!in_array($foundationStatus, ['tersedia', 'rusak'], true)) {
        return true;
    }

    $finalStatus = $foundationStatus === 'tersedia' ? 'selesai' : 'tidak_dapat_diperbaiki';
    $appendNote = $deskripsi !== '' ? "\n[Status akhir] " . $deskripsi : '';
    $updateSql = "UPDATE perbaikan_barang
                  SET tanggal_selesai = ?,
                      status = ?,
                      deskripsi = CONCAT(COALESCE(deskripsi, ''), ?),
                      updated_by = ?,
                      updated_at = NOW()
                  WHERE id_perbaikan = ?";
    $stmtUpdate = $koneksi->prepare($updateSql);
    if (!$stmtUpdate) {
        return false;
    }

    $perbaikanId = intval($openRow['id_perbaikan']);
    $stmtUpdate->bind_param('sssii', $finishedAt, $finalStatus, $appendNote, $actorId, $perbaikanId);
    return $stmtUpdate->execute();
}

function apply_foundation_barang_state($koneksi, $id_produk, $data = []) {
    ensure_priority_one_schema($koneksi);

    if (!schema_table_exists_now($koneksi, 'produk')) {
        return false;
    }

    $produk = get_produk_by_id($koneksi, $id_produk);
    if (!$produk) {
        return false;
    }

    $activityType = normalize_tracking_activity_type($data['activity_type'] ?? 'update');
    $existingStatus = normalize_foundation_barang_status($produk['status'] ?? 'tersedia');
    $existingBorrowerId = get_foundation_barang_borrower_id($produk);
    $currentFoundationStatus = $existingStatus;

    if (array_key_exists('foundation_status', $data) || array_key_exists('status', $data)) {
        $currentFoundationStatus = normalize_foundation_barang_status($data['foundation_status'] ?? $data['status']);
    } else {
        $activityStatusMap = [
            'pinjam' => 'dipinjam',
            'dipinjam' => 'dipinjam',
            'kembali' => 'tersedia',
            'dikembalikan' => 'tersedia',
            'perbaikan' => 'diperbaiki',
            'rusak' => 'rusak',
        ];
        $currentFoundationStatus = $activityStatusMap[$activityType] ?? $existingStatus;
    }

    $borrowerId = array_key_exists('dipinjam_oleh', $data)
        ? nullable_int_id($data['dipinjam_oleh'])
        : nullable_int_id($data['id_user'] ?? $existingBorrowerId);

    if ($currentFoundationStatus !== 'dipinjam') {
        $borrowerId = null;
    }

    $assignedUserId = array_key_exists('id_user', $data)
        ? nullable_int_id($data['id_user'])
        : $borrowerId;

    if ($currentFoundationStatus !== 'dipinjam') {
        $assignedUserId = null;
    }

    $tanggalPinjam = array_key_exists('tanggal_pinjam', $data)
        ? trim((string) ($data['tanggal_pinjam'] ?? ''))
        : '';
    if ($tanggalPinjam === '') {
        if ($currentFoundationStatus === 'dipinjam') {
            $tanggalPinjam = !empty($produk['tanggal_pinjam']) ? $produk['tanggal_pinjam'] : date('Y-m-d H:i:s');
        } else {
            $tanggalPinjam = null;
        }
    }

    $tanggalKembali = array_key_exists('tanggal_kembali', $data)
        ? trim((string) ($data['tanggal_kembali'] ?? ''))
        : '';
    if ($tanggalKembali === '') {
        $hadBorrower = $existingBorrowerId !== null || !empty($produk['tanggal_pinjam']);
        if ($currentFoundationStatus === 'tersedia' && ($activityType === 'dikembalikan' || $hadBorrower)) {
            $tanggalKembali = date('Y-m-d H:i:s');
        } else {
            $tanggalKembali = null;
        }
    }

    if ($currentFoundationStatus === 'dipinjam') {
        $tanggalKembali = null;
    }

    $updateFields = [
        'status' => map_foundation_barang_status_for_storage($currentFoundationStatus),
        'tersedia' => $currentFoundationStatus === 'tersedia' ? 1 : 0,
    ];

    if (schema_has_column_now($koneksi, 'produk', 'id_user')) {
        $updateFields['id_user'] = $assignedUserId;
    }
    if (schema_has_column_now($koneksi, 'produk', 'dipinjam_oleh')) {
        $updateFields['dipinjam_oleh'] = $borrowerId;
    }
    if (schema_has_column_now($koneksi, 'produk', 'tanggal_pinjam')) {
        $updateFields['tanggal_pinjam'] = $tanggalPinjam;
    }
    if (schema_has_column_now($koneksi, 'produk', 'tanggal_kembali')) {
        $updateFields['tanggal_kembali'] = $tanggalKembali;
    }

    $setParts = [];
    foreach ($updateFields as $column => $value) {
        if ($value === null || $value === '') {
            $setParts[] = "$column = NULL";
        } elseif (is_int($value)) {
            $setParts[] = "$column = " . intval($value);
        } else {
            $setParts[] = "$column = '" . $koneksi->real_escape_string((string) $value) . "'";
        }
    }

    if (schema_has_column_now($koneksi, 'produk', 'last_tracked_at')) {
        $setParts[] = "last_tracked_at = NOW()";
    }

    if (empty($setParts)) {
        return false;
    }

    $query = "UPDATE produk SET " . implode(', ', $setParts) . " WHERE id_produk = " . intval($id_produk);
    $updated = (bool) $koneksi->query($query);

    if ($updated && !empty($data['sync_perbaikan'])) {
        sync_foundation_perbaikan_record($koneksi, $id_produk, $currentFoundationStatus, [
            'id_unit_barang' => $data['id_unit_barang'] ?? null,
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'deskripsi' => $data['deskripsi'] ?? ($data['note'] ?? ''),
            'tanggal_mulai' => $data['tanggal_pinjam'] ?? null,
            'tanggal_selesai' => $data['tanggal_kembali'] ?? null,
        ]);
    }

    return $updated;
}

function sync_foundation_barang_from_units($koneksi, $id_produk, $options = []) {
    ensure_priority_one_schema($koneksi);

    if (!schema_table_exists_now($koneksi, 'unit_barang')) {
        return false;
    }

    $id_produk = intval($id_produk);
    if ($id_produk < 1) {
        return false;
    }

    $stmt = $koneksi->prepare(
        "SELECT id_unit_barang, status, id_user, updated_at
         FROM unit_barang
         WHERE id_produk = ?
         ORDER BY CASE WHEN id_user IS NULL THEN 1 ELSE 0 END, updated_at DESC, id_unit_barang ASC"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalUnits = 0;
    $borrowerId = null;
    $tanggalPinjam = null;
    $dipakaiCount = 0;
    $perbaikanCount = 0;
    $rusakCount = 0;

    while ($row = $result->fetch_assoc()) {
        $totalUnits++;
        $status = normalize_asset_unit_status($row['status'] ?? null);
        if ($status === 'dipakai') {
            $dipakaiCount++;
            if ($borrowerId === null) {
                $borrowerId = nullable_int_id($row['id_user'] ?? null);
                $tanggalPinjam = !empty($row['updated_at']) ? $row['updated_at'] : date('Y-m-d H:i:s');
            }
        } elseif ($status === 'perbaikan') {
            $perbaikanCount++;
        } elseif ($status === 'rusak') {
            $rusakCount++;
        }
    }

    if ($totalUnits < 1) {
        return false;
    }

    $foundationStatus = 'tersedia';
    if ($dipakaiCount > 0) {
        $foundationStatus = 'dipinjam';
    } elseif ($perbaikanCount > 0) {
        $foundationStatus = 'diperbaiki';
    } elseif ($rusakCount === $totalUnits) {
        $foundationStatus = 'rusak';
    }

    return apply_foundation_barang_state($koneksi, $id_produk, [
        'foundation_status' => $foundationStatus,
        'dipinjam_oleh' => $borrowerId,
        'id_user' => $borrowerId,
        'tanggal_pinjam' => $tanggalPinjam,
        'activity_type' => $options['activity_type'] ?? 'update',
        'sync_perbaikan' => $options['sync_perbaikan'] ?? false,
        'id_unit_barang' => $options['id_unit_barang'] ?? null,
        'actor_user_id' => $options['actor_user_id'] ?? null,
        'note' => $options['note'] ?? null,
        'deskripsi' => $options['deskripsi'] ?? null,
    ]);
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
    $normalized = normalize_tracking_activity_type($value, 'update');
    $groups = [
        'tambah' => 'REGISTRASI',
        'dipinjam' => 'PEMINJAMAN',
        'dikembalikan' => 'PENGEMBALIAN',
        'mutasi' => 'MUTASI',
        'serah_terima' => 'SERAH TERIMA',
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
        return 'dikembalikan';
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

function get_inventory_public_base_url() {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : 'localhost';

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    $scriptDir = rtrim($scriptDir, '/');

    if ($scriptDir === '.' || $scriptDir === '/') {
        $appPath = '';
    } else {
        $appPath = $scriptDir;
        $parts = explode('/', trim($scriptDir, '/'));
        $last = strtolower((string) end($parts));
        if (in_array($last, ['action', 'views', 'form', 'pages', 'laporan', 'delete'], true)) {
            array_pop($parts);
            $appPath = !empty($parts) ? ('/' . implode('/', $parts)) : '';
        }
    }

    return rtrim($proto . '://' . $host . $appPath, '/');
}

function asset_qr_hash_column_exists($koneksi) {
    return schema_has_column_now($koneksi, 'unit_barang', 'qr_hash');
}

function ensure_asset_qr_hash_schema($koneksi) {
    static $done = false;
    if ($done) {
        return true;
    }
    $done = true;

    if (!schema_table_exists_now($koneksi, 'unit_barang')) {
        return false;
    }

    if (!asset_qr_hash_column_exists($koneksi)) {
        if (!$koneksi->query("ALTER TABLE unit_barang ADD COLUMN qr_hash VARCHAR(64) NULL")) {
            log_event('ERROR', 'QR', 'Gagal menambah kolom qr_hash: ' . $koneksi->error);
            return false;
        }
    }

    if (!schema_has_unique_index_now($koneksi, 'unit_barang', 'qr_hash')) {
        if (!$koneksi->query("ALTER TABLE unit_barang ADD UNIQUE KEY uk_unit_barang_qr_hash (qr_hash)")) {
            log_event('WARNING', 'QR', 'Gagal menambah unique index qr_hash (mungkin sudah ada nama lain): ' . $koneksi->error);
        }
    }

    return true;
}

function generate_inventory_qr_hash() {
    return bin2hex(random_bytes(16));
}

function get_or_create_asset_qr_hash($koneksi, $id_unit_barang) {
    $idUnit = intval($id_unit_barang);
    if ($idUnit < 1 || !ensure_asset_qr_hash_schema($koneksi) || !asset_qr_hash_column_exists($koneksi)) {
        return null;
    }

    $selectStmt = $koneksi->prepare("SELECT qr_hash FROM unit_barang WHERE id_unit_barang = ? LIMIT 1");
    if (!$selectStmt) {
        return null;
    }

    $selectStmt->bind_param('i', $idUnit);
    $selectStmt->execute();
    $row = $selectStmt->get_result()->fetch_assoc();
    $existing = trim((string) ($row['qr_hash'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    $updateStmt = $koneksi->prepare("UPDATE unit_barang SET qr_hash = ? WHERE id_unit_barang = ? AND (qr_hash IS NULL OR qr_hash = '')");
    if (!$updateStmt) {
        return null;
    }

    for ($i = 0; $i < 3; $i++) {
        $newHash = generate_inventory_qr_hash();
        $updateStmt->bind_param('si', $newHash, $idUnit);
        if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
            return $newHash;
        }

        $selectStmt->execute();
        $rowRetry = $selectStmt->get_result()->fetch_assoc();
        $existingRetry = trim((string) ($rowRetry['qr_hash'] ?? ''));
        if ($existingRetry !== '') {
            return $existingRetry;
        }
    }

    return null;
}

function regenerate_qr_hash($koneksi, $id_unit_barang) {
    $idUnit = intval($id_unit_barang);
    if ($idUnit < 1 || !ensure_asset_qr_hash_schema($koneksi) || !asset_qr_hash_column_exists($koneksi)) {
        return null;
    }

    $stmt = $koneksi->prepare("SELECT id_unit_barang FROM unit_barang WHERE id_unit_barang = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $idUnit);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        return null;
    }

    $updateStmt = $koneksi->prepare("UPDATE unit_barang SET qr_hash = ? WHERE id_unit_barang = ?");
    if (!$updateStmt) {
        return null;
    }

    for ($i = 0; $i < 5; $i++) {
        $newHash = generate_inventory_qr_hash();
        $updateStmt->bind_param('si', $newHash, $idUnit);
        if ($updateStmt->execute()) {
            return $newHash;
        }
    }

    return null;
}

function build_asset_qr_value($id_unit_barang, $id_produk = null, $koneksi = null) {
    // Prefer secure hash URL when qr_hash is available; fallback to legacy unit_id.
    $safeUnitId = intval($id_unit_barang);
    if ($safeUnitId < 1) {
        return 'scan_barang.php?unit_id=0';
    }

    if ($koneksi !== null) {
        $qrHash = get_or_create_asset_qr_hash($koneksi, $safeUnitId);
        if (!empty($qrHash)) {
            return 'scan_barang.php?q=' . rawurlencode($qrHash);
        }
    }

    return 'scan_barang.php?unit_id=' . $safeUnitId;
}

function get_asset_qr_relative_path($id_unit_barang) {
    return 'assets/qr/qr_unit_' . intval($id_unit_barang) . '.png';
}

function get_asset_qr_absolute_path($id_unit_barang) {
    return __DIR__ . '/../' . get_asset_qr_relative_path($id_unit_barang);
}

function log_asset_qr_error($message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
        return false;
    }

    $logFile = $logDir . '/qr_generation.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . trim((string) $message);
    if (!empty($context)) {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $line .= ' | ' . $encoded;
        }
    }
    $line .= PHP_EOL;

    return @file_put_contents($logFile, $line, FILE_APPEND) !== false;
}

function ensure_asset_qr_file($id_unit_barang, $qrValue = null) {
    $idUnit = intval($id_unit_barang);
    if ($idUnit < 1) {
        log_asset_qr_error('Invalid unit id for QR generation.', ['unit_id' => $id_unit_barang]);
        return null;
    }

    $relativePath = get_asset_qr_relative_path($idUnit);
    $absolutePath = get_asset_qr_absolute_path($idUnit);
    $qrDirectory = dirname($absolutePath);

    if (!is_dir($qrDirectory) && !@mkdir($qrDirectory, 0775, true) && !is_dir($qrDirectory)) {
        log_asset_qr_error('Failed to create QR directory.', ['unit_id' => $idUnit, 'directory' => $qrDirectory]);
        return null;
    }

    if (is_file($absolutePath) && @filesize($absolutePath) > 0) {
        return $relativePath;
    }

    if ($qrValue === null || trim((string) $qrValue) === '') {
        $qrValue = build_asset_qr_value($idUnit, null, $koneksi);
    }

    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&data=' . rawurlencode((string) $qrValue);
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'InventarisQR/1.0',
        ],
    ]);

    $imageBytes = @file_get_contents($qrUrl, false, $context);
    if ($imageBytes === false || strlen($imageBytes) < 100) {
        log_asset_qr_error('Failed to download QR image bytes.', ['unit_id' => $idUnit, 'url' => $qrUrl]);
        return null;
    }

    if (@file_put_contents($absolutePath, $imageBytes) === false) {
        log_asset_qr_error('Failed to write QR image file.', ['unit_id' => $idUnit, 'path' => $absolutePath]);
        return null;
    }

    return $relativePath;
}

function migrate_legacy_qr_urls_to_public($koneksi) {
    static $done = false;
    if ($done) {
        return 0;
    }
    $done = true;

    if (!schema_table_exists_now($koneksi, 'unit_barang')) {
        return 0;
    }

    $qrColumn = get_asset_unit_qr_column($koneksi);
    if ($qrColumn === null) {
        return 0;
    }

     $sql = "SELECT id_unit_barang, id_produk, `$qrColumn` AS qr_value
            FROM unit_barang
            WHERE `$qrColumn` IS NOT NULL
                  AND TRIM(`$qrColumn`) <> ''";
    $result = $koneksi->query($sql);
    if (!$result) {
        return 0;
    }

    $updated = 0;
    $updateStmt = $koneksi->prepare("UPDATE unit_barang SET `$qrColumn` = ? WHERE id_unit_barang = ?");
    if (!$updateStmt) {
        return 0;
    }

    while ($row = $result->fetch_assoc()) {
        $unitId = intval($row['id_unit_barang'] ?? 0);
        $produkId = nullable_int_id($row['id_produk'] ?? null);
        if ($unitId < 1) {
            continue;
        }

        if ($produkId === null) {
            continue;
        }

        $newQr = build_asset_qr_value($unitId, $produkId, $koneksi);
        $oldQr = trim((string) ($row['qr_value'] ?? ''));
        if ($newQr !== $oldQr) {
            $updateStmt->bind_param('si', $newQr, $unitId);
            if ($updateStmt->execute()) {
                $updated++;
            }
        }
    }

    return $updated;
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
    $qrValue = build_asset_qr_value($idUnit, $id_produk, $koneksi);
    $qrColumn = get_asset_unit_qr_column($koneksi);
    if ($qrColumn !== null && $idUnit > 0) {
        $updateQrStmt = $koneksi->prepare("UPDATE unit_barang SET `$qrColumn` = ? WHERE id_unit_barang = ?");
        if ($updateQrStmt) {
            $updateQrStmt->bind_param('si', $qrValue, $idUnit);
            $updateQrStmt->execute();
        }
    }

    if ($idUnit > 0 && ensure_asset_qr_file($idUnit, $qrValue) === null) {
        log_asset_qr_error('Automatic QR generation failed after unit insert.', [
            'unit_id' => $idUnit,
            'produk_id' => $id_produk,
            'source' => 'insert_asset_unit_row',
        ]);
    }

    $locationName = get_gudang_name_by_id($koneksi, $productData['id_gudang'] ?? null);
    log_tracking_unit_barang($koneksi, [
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
    if (!schema_table_exists($koneksi, 'tracking_barang')) {
        return $historyMap;
    }

    if (!schema_has_column($koneksi, 'tracking_barang', 'id_unit')) {
        return $historyMap;
    }

    $stmt = $koneksi->prepare("SELECT id_unit, COUNT(*) AS total FROM tracking_barang WHERE id_produk = ? AND id_unit IS NOT NULL GROUP BY id_unit");
    if (!$stmt) {
        return $historyMap;
    }

    $stmt->bind_param('i', $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $historyMap[intval($row['id_unit'])] = intval($row['total']);
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

    // Validasi ukuran file (maks 5 MB)
    $maxBytes = 5 * 1024 * 1024;
    if (($fileInfo['size'] ?? 0) > $maxBytes) {
        throw new Exception('Ukuran dokumen melebihi batas maksimal 5 MB.');
    }

    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    $originalName = (string) ($fileInfo['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Format dokumen tidak didukung. Gunakan PDF/JPG/PNG/WEBP.');
    }

    // Validasi MIME type dari konten file (bukan dari header HTTP)
    $tmpPath = (string) ($fileInfo['tmp_name'] ?? '');
    if ($tmpPath !== '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : null;
        if ($finfo) finfo_close($finfo);
        if ($detectedMime !== null && !in_array($detectedMime, $allowedMimes, true)) {
            throw new Exception('Tipe file dokumen tidak valid (' . htmlspecialchars($detectedMime) . ').');
        }
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

function store_uploaded_inventory_photo($fileField, $targetFolder, $prefix = 'IMG') {
    if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
        return null;
    }

    $fileInfo = $_FILES[$fileField];
    if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($fileInfo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('Upload foto dokumentasi gagal diproses.');
    }

    // Validasi ukuran file (maks 5 MB)
    $maxBytes = 5 * 1024 * 1024;
    if (($fileInfo['size'] ?? 0) > $maxBytes) {
        throw new Exception('Ukuran foto dokumentasi melebihi batas maksimal 5 MB.');
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $originalName = (string) ($fileInfo['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Format foto dokumentasi tidak didukung. Gunakan JPG/PNG/WEBP.');
    }

    // Validasi MIME type dari konten file dan verifikasi gambar valid
    $tmpPath = (string) ($fileInfo['tmp_name'] ?? '');
    if ($tmpPath !== '') {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : null;
            if ($finfo) finfo_close($finfo);
            if ($detectedMime !== null && !in_array($detectedMime, $allowedMimes, true)) {
                throw new Exception('Tipe file foto tidak valid (' . htmlspecialchars($detectedMime) . ').');
            }
        }
        // getimagesize memverifikasi bahwa file benar-benar data gambar yang valid
        if (@getimagesize($tmpPath) === false) {
            throw new Exception('File yang diunggah bukan gambar yang valid.');
        }
    }

    $baseDir = realpath(__DIR__ . '/..');
    if ($baseDir === false) {
        throw new Exception('Direktori project tidak ditemukan.');
    }

    $targetFolder = trim(str_replace(['..\\', '../'], '', $targetFolder), "\\/");
    $destinationDir = $baseDir . DIRECTORY_SEPARATOR . $targetFolder;
    if (!ensure_directory_exists($destinationDir)) {
        throw new Exception('Folder foto dokumentasi gagal dibuat.');
    }

    $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper((string) $prefix));
    if ($safePrefix === '') {
        $safePrefix = 'IMG';
    }

    $fileName = $safePrefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destinationPath = $destinationDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($fileInfo['tmp_name'], $destinationPath)) {
        throw new Exception('Foto dokumentasi gagal disimpan ke server.');
    }

    return str_replace('\\', '/', $targetFolder . '/' . $fileName);
}

function decode_inventory_meta_json($value) {
    if (is_array($value)) {
        return $value;
    }

    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
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

/**
 * Sinkronisasi stokgudang dari data aktual unit_barang (asset) atau produk (consumable).
 * Jika $id_produk diberikan, hanya sync produk tersebut.
 * Jika null, sync semua produk.
 */
function sync_stok_gudang($koneksi, $id_produk = null) {
    if (!schema_table_exists_now($koneksi, 'stokgudang')) {
        return false;
    }
    $hasUnitTable = schema_table_exists_now($koneksi, 'unit_barang');

    if ($id_produk !== null) {
        $id_produk = intval($id_produk);
        if ($id_produk < 1) {
            return false;
        }

        $stmt = $koneksi->prepare("SELECT id_produk, tipe_barang, jumlah_stok, id_gudang FROM produk WHERE id_produk = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id_produk);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();
        if (!$produk) {
            return false;
        }

        // Hapus stokgudang lama untuk produk ini
        $delStmt = $koneksi->prepare("DELETE FROM stokgudang WHERE id_produk = ?");
        if ($delStmt) {
            $delStmt->bind_param('i', $id_produk);
            $delStmt->execute();
        }

        if ($hasUnitTable && ($produk['tipe_barang'] ?? 'consumable') === 'asset') {
            // Asset: hitung dari unit_barang GROUP BY id_gudang
            $countStmt = $koneksi->prepare(
                "SELECT id_gudang, COUNT(*) AS qty
                 FROM unit_barang
                 WHERE id_produk = ? AND id_gudang IS NOT NULL
                 GROUP BY id_gudang"
            );
            if ($countStmt) {
                $countStmt->bind_param('i', $id_produk);
                $countStmt->execute();
                $countResult = $countStmt->get_result();

                $insStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
                if ($insStmt) {
                    while ($row = $countResult->fetch_assoc()) {
                        $gid = intval($row['id_gudang']);
                        $qty = intval($row['qty']);
                        $insStmt->bind_param('iii', $gid, $id_produk, $qty);
                        $insStmt->execute();
                    }
                }
            }

            // Update juga produk.jumlah_stok agar sinkron
            $totalStmt = $koneksi->prepare("SELECT COUNT(*) AS total FROM unit_barang WHERE id_produk = ? AND id_gudang IS NOT NULL");
            if ($totalStmt) {
                $totalStmt->bind_param('i', $id_produk);
                $totalStmt->execute();
                $totalRow = $totalStmt->get_result()->fetch_assoc();
                $totalUnit = intval($totalRow['total'] ?? 0);
                $updProduk = $koneksi->prepare("UPDATE produk SET jumlah_stok = ? WHERE id_produk = ?");
                if ($updProduk) {
                    $updProduk->bind_param('ii', $totalUnit, $id_produk);
                    $updProduk->execute();
                }
            }
        } else {
            // Consumable: gunakan produk.jumlah_stok + produk.id_gudang
            $gid = intval($produk['id_gudang'] ?? 0);
            $qty = max(0, intval($produk['jumlah_stok'] ?? 0));
            if ($gid > 0 && $qty > 0) {
                $insStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
                if ($insStmt) {
                    $insStmt->bind_param('iii', $gid, $id_produk, $qty);
                    $insStmt->execute();
                }
            }
        }

        update_gudang_kondisi_status($koneksi);
        return true;
    }

    // ===== Full sync: semua produk =====
    $koneksi->begin_transaction();
    try {
        // Hapus semua stokgudang
        $koneksi->query("DELETE FROM stokgudang");

        // Asset: hitung dari unit_barang
        if ($hasUnitTable) {
            $assetResult = $koneksi->query(
                "SELECT ub.id_gudang, ub.id_produk, COUNT(*) AS qty
                 FROM unit_barang ub
                 INNER JOIN produk p ON p.id_produk = ub.id_produk AND p.tipe_barang = 'asset'
                 WHERE ub.id_gudang IS NOT NULL
                 GROUP BY ub.id_gudang, ub.id_produk"
            );
            if ($assetResult) {
                $insStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
                if ($insStmt) {
                    while ($row = $assetResult->fetch_assoc()) {
                        $gid = intval($row['id_gudang']);
                        $pid = intval($row['id_produk']);
                        $qty = intval($row['qty']);
                        $insStmt->bind_param('iii', $gid, $pid, $qty);
                        $insStmt->execute();
                    }
                }
            }

            // Update produk.jumlah_stok untuk semua asset
            $koneksi->query(
                "UPDATE produk p
                 SET p.jumlah_stok = COALESCE((
                     SELECT COUNT(*) FROM unit_barang ub
                     WHERE ub.id_produk = p.id_produk AND ub.id_gudang IS NOT NULL
                 ), 0)
                 WHERE p.tipe_barang = 'asset'"
            );
        }

        // Consumable: dari produk.jumlah_stok + produk.id_gudang
        $consumableResult = $koneksi->query(
            "SELECT id_produk, id_gudang, jumlah_stok
             FROM produk
             WHERE (tipe_barang IS NULL OR tipe_barang = 'consumable')
               AND id_gudang IS NOT NULL AND jumlah_stok > 0"
        );
        if ($consumableResult) {
            $insStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
            if ($insStmt) {
                while ($row = $consumableResult->fetch_assoc()) {
                    $gid = intval($row['id_gudang']);
                    $pid = intval($row['id_produk']);
                    $qty = max(0, intval($row['jumlah_stok']));
                    $insStmt->bind_param('iii', $gid, $pid, $qty);
                    $insStmt->execute();
                }
            }
        }

        update_gudang_kondisi_status($koneksi);
        $koneksi->commit();
        return true;
    } catch (Exception $e) {
        $koneksi->rollback();
        return false;
    }
}

/**
 * Pastikan stokgudang punya UNIQUE INDEX pada (id_gudang, id_produk).
 * Deduplikasi otomatis sebelum menambahkan constraint.
 */
function ensure_stokgudang_unique_index($koneksi) {
    if (!schema_table_exists_now($koneksi, 'stokgudang')) {
        return false;
    }
    if (stokgudang_has_unique_pair_index($koneksi)) {
        return true;
    }

    // Deduplikasi: hapus baris duplikat, pertahankan yang jumlah_stok terbesar
    $koneksi->query(
        "DELETE s1 FROM stokgudang s1
         INNER JOIN stokgudang s2
         ON s1.id_gudang = s2.id_gudang
            AND s1.id_produk = s2.id_produk
            AND s1.id_stok_gudang > s2.id_stok_gudang"
    );

    // Tambahkan UNIQUE constraint
    $ok = $koneksi->query(
        "ALTER TABLE stokgudang ADD UNIQUE INDEX uq_gudang_produk (id_gudang, id_produk)"
    );

    if ($ok) {
        // Setelah constraint ditambahkan, sync ulang agar data akurat
        sync_stok_gudang($koneksi);
    }

    return (bool) $ok;
}

function stokgudang_has_unique_pair_index($koneksi) {
    if (!schema_table_exists_now($koneksi, 'stokgudang')) {
        return false;
    }

    $indexResult = $koneksi->query("SHOW INDEX FROM stokgudang");
    if (!$indexResult) {
        return false;
    }

    $indexColumns = [];
    while ($row = $indexResult->fetch_assoc()) {
        $indexName = (string) ($row['Key_name'] ?? '');
        $isUnique = intval($row['Non_unique'] ?? 1) === 0;
        if ($indexName === '' || !$isUnique) {
            continue;
        }

        $seq = intval($row['Seq_in_index'] ?? 0);
        $col = strtolower(trim((string) ($row['Column_name'] ?? '')));
        if ($seq < 1 || $col === '') {
            continue;
        }

        if (!isset($indexColumns[$indexName])) {
            $indexColumns[$indexName] = [];
        }
        $indexColumns[$indexName][$seq] = $col;
    }

    foreach ($indexColumns as $colsBySeq) {
        ksort($colsBySeq);
        $cols = array_values($colsBySeq);
        if ($cols === ['id_gudang', 'id_produk']) {
            return true;
        }
    }

    return false;
}

function update_gudang_kondisi_status($koneksi, $idGudang = null) {
    if (!schema_table_exists_now($koneksi, 'gudang') || !schema_table_exists_now($koneksi, 'stokgudang')) {
        return false;
    }

    $statusColumn = schema_find_existing_column($koneksi, 'gudang', ['status_gudang', 'status', 'kondisi_gudang', 'kondisi']);
    if ($statusColumn === null) {
        return true;
    }

    if ($idGudang !== null) {
        $idGudang = intval($idGudang);
        if ($idGudang < 1) {
            return false;
        }

        $sql = "UPDATE gudang g
                SET g.`$statusColumn` = (
                    CASE
                        WHEN COALESCE((
                            SELECT SUM(COALESCE(sg.jumlah_stok, 0))
                            FROM stokgudang sg
                            WHERE sg.id_gudang = g.id_gudang
                        ), 0) <= 0 THEN 'kosong'
                        ELSE 'terisi'
                    END
                )
                WHERE g.id_gudang = ?";
        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $idGudang);
        return $stmt->execute();
    }

    $sql = "UPDATE gudang g
            SET g.`$statusColumn` = (
                CASE
                    WHEN COALESCE((
                        SELECT SUM(COALESCE(sg.jumlah_stok, 0))
                        FROM stokgudang sg
                        WHERE sg.id_gudang = g.id_gudang
                    ), 0) <= 0 THEN 'kosong'
                    ELSE 'terisi'
                END
            )";

    return (bool) $koneksi->query($sql);
}

function upsert_stokgudang_additive($koneksi, $id_gudang, $id_produk, $jumlah_stok) {
    $id_gudang = intval($id_gudang);
    $id_produk = intval($id_produk);
    $jumlah_stok = intval($jumlah_stok);

    if ($id_gudang < 1 || $id_produk < 1 || $jumlah_stok === 0) {
        return true;
    }

    // Prefer ON DUPLICATE KEY UPDATE when pair index exists.
    if (stokgudang_has_unique_pair_index($koneksi)) {
        $stmt = $koneksi->prepare(
            "INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE jumlah_stok = jumlah_stok + VALUES(jumlah_stok)"
        );
        if ($stmt) {
            $stmt->bind_param('iii', $id_gudang, $id_produk, $jumlah_stok);
            $ok = $stmt->execute();
            if ($ok) {
                update_gudang_kondisi_status($koneksi, $id_gudang);
                return true;
            }
        }
    }

    // Fallback for legacy schema without unique composite index.
    $existsStmt = $koneksi->prepare("SELECT id_stok_gudang, jumlah_stok FROM stokgudang WHERE id_gudang = ? AND id_produk = ? ORDER BY id_stok_gudang ASC LIMIT 1 FOR UPDATE");
    if (!$existsStmt) {
        return false;
    }
    $existsStmt->bind_param('ii', $id_gudang, $id_produk);
    $existsStmt->execute();
    $existsResult = $existsStmt->get_result();
    $existsRow = $existsResult ? $existsResult->fetch_assoc() : null;

    if ($existsRow) {
        $newQty = intval($existsRow['jumlah_stok']) + $jumlah_stok;
        $updateStmt = $koneksi->prepare("UPDATE stokgudang SET jumlah_stok = ? WHERE id_stok_gudang = ?");
        if (!$updateStmt) {
            return false;
        }
        $stokGudangId = intval($existsRow['id_stok_gudang']);
        $updateStmt->bind_param('ii', $newQty, $stokGudangId);
        $ok = $updateStmt->execute();
        if ($ok) {
            update_gudang_kondisi_status($koneksi, $id_gudang);
        }
        return $ok;
    }

    $insertStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
    if (!$insertStmt) {
        return false;
    }
    $insertStmt->bind_param('iii', $id_gudang, $id_produk, $jumlah_stok);
    $ok = $insertStmt->execute();
    if ($ok) {
        update_gudang_kondisi_status($koneksi, $id_gudang);
    }
    return $ok;
}

function upsert_stok_gudang_quantity($koneksi, $id_gudang, $id_produk, $deltaQty) {
    $id_gudang = intval($id_gudang);
    $id_produk = intval($id_produk);
    $deltaQty = intval($deltaQty);

    if ($id_gudang < 1 || $id_produk < 1 || $deltaQty === 0) {
        return true;
    }

    // Guard decrement atomically to prevent negative stock during concurrent requests.
    if ($deltaQty < 0) {
        $updateDecStmt = $koneksi->prepare(
            "UPDATE stokgudang
             SET jumlah_stok = jumlah_stok + ?
             WHERE id_gudang = ? AND id_produk = ? AND jumlah_stok + ? >= 0"
        );
        if (!$updateDecStmt) {
            return false;
        }
        $updateDecStmt->bind_param('iiii', $deltaQty, $id_gudang, $id_produk, $deltaQty);
        if (!$updateDecStmt->execute()) {
            return false;
        }
        if ($updateDecStmt->affected_rows > 0) {
            update_gudang_kondisi_status($koneksi, $id_gudang);
            return true;
        }

        // If no row updated, either row missing or insufficient stock.
        return false;
    }

    $existsStmt = $koneksi->prepare("SELECT id_stok_gudang, jumlah_stok FROM stokgudang WHERE id_gudang = ? AND id_produk = ? LIMIT 1 FOR UPDATE");
    if (!$existsStmt) {
        return false;
    }

    $existsStmt->bind_param('ii', $id_gudang, $id_produk);
    $existsStmt->execute();
    $existsResult = $existsStmt->get_result();
    $existsRow = $existsResult ? $existsResult->fetch_assoc() : null;

    if ($existsRow) {
        $newQty = intval($existsRow['jumlah_stok']) + $deltaQty;
        $updateStmt = $koneksi->prepare("UPDATE stokgudang SET jumlah_stok = ? WHERE id_stok_gudang = ?");
        if (!$updateStmt) {
            return false;
        }
        $stokGudangId = intval($existsRow['id_stok_gudang']);
        $updateStmt->bind_param('ii', $newQty, $stokGudangId);
        $ok = $updateStmt->execute();
        if ($ok) {
            update_gudang_kondisi_status($koneksi, $id_gudang);
        }
        return $ok;
    }

    $insertStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
    if (!$insertStmt) {
        return false;
    }
    $insertStmt->bind_param('iii', $id_gudang, $id_produk, $deltaQty);
    $ok = $insertStmt->execute();
    if ($ok) {
        update_gudang_kondisi_status($koneksi, $id_gudang);
    }
    return $ok;
}

function sync_stokgudang_from_produk($koneksi) {
    if (!schema_table_exists_now($koneksi, 'produk') || !schema_table_exists_now($koneksi, 'stokgudang')) {
        return false;
    }

    $koneksi->begin_transaction();
    try {
        // Clean stale/duplicate rows for existing products before refill.
        $cleanupSql = "DELETE sg FROM stokgudang sg
                       INNER JOIN produk p ON p.id_produk = sg.id_produk";
        if (!$koneksi->query($cleanupSql)) {
            throw new Exception('Gagal membersihkan stokgudang lama.');
        }

        $sourceSql = "SELECT p.id_gudang, p.id_produk, SUM(COALESCE(p.jumlah_stok, 0)) AS total_stok
                      FROM produk p
                      WHERE p.id_gudang IS NOT NULL
                      GROUP BY p.id_gudang, p.id_produk";
        $sourceResult = $koneksi->query($sourceSql);
        if (!$sourceResult) {
            throw new Exception('Gagal membaca data produk untuk backfill.');
        }

        $insertStmt = $koneksi->prepare("INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)");
        if (!$insertStmt) {
            throw new Exception('Gagal menyiapkan insert backfill stokgudang.');
        }

        while ($row = $sourceResult->fetch_assoc()) {
            $idGudang = intval($row['id_gudang'] ?? 0);
            $idProduk = intval($row['id_produk'] ?? 0);
            $totalStok = max(0, intval($row['total_stok'] ?? 0));

            if ($idGudang < 1 || $idProduk < 1) {
                continue;
            }

            $insertStmt->bind_param('iii', $idGudang, $idProduk, $totalStok);
            if (!$insertStmt->execute()) {
                throw new Exception('Gagal mengisi ulang stokgudang dari produk.');
            }
        }

        if (!update_gudang_kondisi_status($koneksi)) {
            throw new Exception('Gagal memperbarui kondisi gudang.');
        }

        $koneksi->commit();
        return true;
    } catch (Exception $e) {
        $koneksi->rollback();
        log_event('ERROR', 'STOK', $e->getMessage());
        return false;
    }
}

function ensure_stokgudang_backfill_once($koneksi) {
    static $alreadyChecked = false;
    if ($alreadyChecked) {
        return true;
    }
    $alreadyChecked = true;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return sync_stokgudang_from_produk($koneksi);
    }

    $flagKey = 'stokgudang_backfill_done_v2';
    if (!empty($_SESSION[$flagKey])) {
        return true;
    }

    $ok = sync_stokgudang_from_produk($koneksi);
    if ($ok) {
        $_SESSION[$flagKey] = 1;
    }

    return $ok;
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

function fetch_inventory_usage_report_rows($koneksi, $filters = [], $limit = 500) {
    $reportParts = [];

    if (schema_table_exists_now($koneksi, 'tracking_barang') && schema_table_exists_now($koneksi, 'produk')) {
        $trackingUserCols = [];
        foreach (['id_user_sesudah', 'id_user_terkait', 'id_user', 'user_id', 'id_user_changed', 'id_user_sebelum'] as $column) {
            if (schema_has_column_now($koneksi, 'tracking_barang', $column)) {
                $trackingUserCols[] = 'tb.' . $column;
            }
        }

        $trackingStatusCols = [];
        foreach (['status_sesudah', 'status', 'status_sebelum'] as $column) {
            if (schema_has_column_now($koneksi, 'tracking_barang', $column)) {
                $trackingStatusCols[] = 'tb.' . $column;
            }
        }

        $trackingUserExpr = !empty($trackingUserCols) ? 'COALESCE(' . implode(', ', $trackingUserCols) . ')' : 'NULL';
        $trackingStatusExpr = !empty($trackingStatusCols) ? 'COALESCE(' . implode(', ', $trackingStatusCols) . ', p.status)' : 'p.status';
        $trackingDateCol = schema_find_existing_column($koneksi, 'tracking_barang', ['changed_at', 'created_at', 'updated_at']);
        $trackingActionCol = schema_find_existing_column($koneksi, 'tracking_barang', ['activity_type', 'aktivitas']);
        $trackingNoteCol = schema_find_existing_column($koneksi, 'tracking_barang', ['note', 'catatan']);

        $reportParts[] = "
            SELECT
                p.id_produk AS barang_id,
                p.kode_produk,
                p.nama_produk,
                $trackingUserExpr AS user_id,
                COALESCE(NULLIF(u.nama, ''), NULLIF(u.username, ''), '-') AS nama_user,
                $trackingStatusExpr AS status_barang,
                COALESCE(NULLIF(tb.lokasi_sesudah, ''), NULLIF(tb.lokasi_sebelum, ''), NULLIF(p.lokasi_custom, ''), NULLIF(g.nama_gudang, ''), '-') AS gudang_lokasi,
                " . ($trackingDateCol !== null ? ('tb.' . $trackingDateCol) : 'NULL') . " AS tanggal_aktivitas,
                " . ($trackingActionCol !== null ? ('tb.' . $trackingActionCol) : 'NULL') . " AS aktivitas,
                " . ($trackingNoteCol !== null ? ('tb.' . $trackingNoteCol) : 'NULL') . " AS catatan,
                'tracking_barang' AS sumber
            FROM tracking_barang tb
            INNER JOIN produk p ON tb.id_produk = p.id_produk
            LEFT JOIN gudang g ON p.id_gudang = g.id_gudang
            LEFT JOIN user u ON u.id_user = $trackingUserExpr
        ";
    }

    if (empty($reportParts)) {
        return [];
    }

    $sql = "SELECT * FROM (" . implode(" UNION ALL ", $reportParts) . ") usage_report WHERE 1 = 1";
    $types = '';
    $values = [];

    $filterUserId = nullable_int_id($filters['user_id'] ?? null);
    if ($filterUserId !== null) {
        $sql .= " AND usage_report.user_id = ?";
        $types .= 'i';
        $values[] = $filterUserId;
    }

    $filterBarangId = nullable_int_id($filters['barang_id'] ?? null);
    if ($filterBarangId !== null) {
        $sql .= " AND usage_report.barang_id = ?";
        $types .= 'i';
        $values[] = $filterBarangId;
    }

    $filterStatus = strtolower(trim((string) ($filters['status_barang'] ?? '')));
    if ($filterStatus !== '') {
        $sql .= " AND LOWER(TRIM(COALESCE(usage_report.status_barang, ''))) = ?";
        $types .= 's';
        $values[] = $filterStatus;
    }

    $tanggalDari = trim((string) ($filters['tanggal_dari'] ?? ''));
    if ($tanggalDari !== '') {
        $sql .= " AND DATE(usage_report.tanggal_aktivitas) >= ?";
        $types .= 's';
        $values[] = $tanggalDari;
    }

    $tanggalSampai = trim((string) ($filters['tanggal_sampai'] ?? ''));
    if ($tanggalSampai !== '') {
        $sql .= " AND DATE(usage_report.tanggal_aktivitas) <= ?";
        $types .= 's';
        $values[] = $tanggalSampai;
    }

    $limit = max(1, intval($limit));
    $sql .= " ORDER BY usage_report.tanggal_aktivitas DESC, usage_report.barang_id DESC LIMIT ?";
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

    // Seed data bidang jika tabel kosong
    $cekBidang = $koneksi->query("SELECT COUNT(*) AS cnt FROM bidang");
    if ($cekBidang && intval($cekBidang->fetch_assoc()['cnt']) === 0) {
        $daftarBidang = [
            ['SO', 'SO'],
            ['CBM', 'CBM'],
            ['MMRK', 'MMRK'],
            ['KEU', 'KEU'],
            ['SEKUM', 'SEKUM'],
            ['SDM', 'SDM'],
            ['PENGADAAN', 'PENGADAAN'],
            ['ICC', 'ICC'],
            ['OUTAGE', 'OUTAGE'],
            ['SIPIL & LINGK', 'SIPIL_LINGK'],
            ['K3 & KEAMANAN', 'K3_KEAMANAN'],
            ['RENDAL OP', 'RENDAL_OP'],
            ['RENDAL HAL', 'RENDAL_HAL'],
        ];
        $seedStmt = $koneksi->prepare("INSERT IGNORE INTO bidang (nama_bidang, kode_bidang, status) VALUES (?, ?, 'aktif')");
        if ($seedStmt) {
            foreach ($daftarBidang as $b) {
                $seedStmt->bind_param('ss', $b[0], $b[1]);
                $seedStmt->execute();
            }
        }
    }

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

    if (schema_table_exists_now($koneksi, 'produk') && !schema_has_column_now($koneksi, 'produk', 'dipinjam_oleh')) {
        $koneksi->query("ALTER TABLE produk ADD COLUMN dipinjam_oleh INT NULL AFTER id_user");
    }
    if (schema_table_exists_now($koneksi, 'produk') && !schema_has_column_now($koneksi, 'produk', 'tanggal_pinjam')) {
        $koneksi->query("ALTER TABLE produk ADD COLUMN tanggal_pinjam DATETIME NULL AFTER dipinjam_oleh");
    }
    if (schema_table_exists_now($koneksi, 'produk') && !schema_has_column_now($koneksi, 'produk', 'tanggal_kembali')) {
        $koneksi->query("ALTER TABLE produk ADD COLUMN tanggal_kembali DATETIME NULL AFTER tanggal_pinjam");
    }

    if (!schema_table_exists_now($koneksi, 'perbaikan_barang')) {
        $koneksi->query(
            "CREATE TABLE perbaikan_barang (
                id_perbaikan INT NOT NULL AUTO_INCREMENT,
                id_produk INT NOT NULL,
                id_unit_barang INT DEFAULT NULL,
                tanggal_mulai DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                tanggal_selesai DATETIME DEFAULT NULL,
                deskripsi TEXT DEFAULT NULL,
                status ENUM('proses','selesai','tidak_dapat_diperbaiki') NOT NULL DEFAULT 'proses',
                created_by INT DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id_perbaikan),
                INDEX idx_perbaikan_produk (id_produk),
                INDEX idx_perbaikan_unit (id_unit_barang),
                INDEX idx_perbaikan_status (status),
                INDEX idx_perbaikan_created_by (created_by),
                INDEX idx_perbaikan_updated_by (updated_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    if (schema_table_exists_now($koneksi, 'produk')) {
        if (!schema_index_exists_now($koneksi, 'produk', 'idx_produk_id_gudang') && schema_has_column_now($koneksi, 'produk', 'id_gudang')) {
            $koneksi->query("ALTER TABLE produk ADD KEY idx_produk_id_gudang (id_gudang)");
        }
        if (!schema_index_exists_now($koneksi, 'produk', 'idx_produk_id_user') && schema_has_column_now($koneksi, 'produk', 'id_user')) {
            $koneksi->query("ALTER TABLE produk ADD KEY idx_produk_id_user (id_user)");
        }
        if (!schema_index_exists_now($koneksi, 'produk', 'idx_produk_dipinjam_oleh') && schema_has_column_now($koneksi, 'produk', 'dipinjam_oleh')) {
            $koneksi->query("ALTER TABLE produk ADD KEY idx_produk_dipinjam_oleh (dipinjam_oleh)");
        }

        if (schema_table_exists_now($koneksi, 'gudang') && schema_has_column_now($koneksi, 'produk', 'id_gudang')) {
            $koneksi->query("UPDATE produk p LEFT JOIN gudang g ON p.id_gudang = g.id_gudang SET p.id_gudang = NULL WHERE p.id_gudang IS NOT NULL AND g.id_gudang IS NULL");
            if (!schema_foreign_key_exists_now($koneksi, 'produk', 'fk_produk_gudang_foundation')) {
                $koneksi->query("ALTER TABLE produk ADD CONSTRAINT fk_produk_gudang_foundation FOREIGN KEY (id_gudang) REFERENCES gudang(id_gudang) ON DELETE SET NULL ON UPDATE CASCADE");
            }
        }
        if (schema_table_exists_now($koneksi, 'user') && schema_has_column_now($koneksi, 'produk', 'id_user')) {
            $koneksi->query("UPDATE produk p LEFT JOIN user u ON p.id_user = u.id_user SET p.id_user = NULL WHERE p.id_user IS NOT NULL AND u.id_user IS NULL");
            if (!schema_foreign_key_exists_now($koneksi, 'produk', 'fk_produk_user_foundation')) {
                $koneksi->query("ALTER TABLE produk ADD CONSTRAINT fk_produk_user_foundation FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE");
            }
        }
        if (schema_table_exists_now($koneksi, 'user') && schema_has_column_now($koneksi, 'produk', 'dipinjam_oleh')) {
            $koneksi->query("UPDATE produk p LEFT JOIN user u ON p.dipinjam_oleh = u.id_user SET p.dipinjam_oleh = NULL WHERE p.dipinjam_oleh IS NOT NULL AND u.id_user IS NULL");
            if (!schema_foreign_key_exists_now($koneksi, 'produk', 'fk_produk_dipinjam_oleh_foundation')) {
                $koneksi->query("ALTER TABLE produk ADD CONSTRAINT fk_produk_dipinjam_oleh_foundation FOREIGN KEY (dipinjam_oleh) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE");
            }
        }
    }

    if (schema_table_exists_now($koneksi, 'perbaikan_barang')) {
        if (schema_table_exists_now($koneksi, 'produk') && !schema_foreign_key_exists_now($koneksi, 'perbaikan_barang', 'fk_perbaikan_produk_foundation')) {
            $koneksi->query("ALTER TABLE perbaikan_barang ADD CONSTRAINT fk_perbaikan_produk_foundation FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE ON UPDATE CASCADE");
        }
        if (schema_table_exists_now($koneksi, 'unit_barang') && schema_has_column_now($koneksi, 'perbaikan_barang', 'id_unit_barang') && !schema_foreign_key_exists_now($koneksi, 'perbaikan_barang', 'fk_perbaikan_unit_foundation')) {
            $koneksi->query("ALTER TABLE perbaikan_barang ADD CONSTRAINT fk_perbaikan_unit_foundation FOREIGN KEY (id_unit_barang) REFERENCES unit_barang(id_unit_barang) ON DELETE SET NULL ON UPDATE CASCADE");
        }
        if (schema_table_exists_now($koneksi, 'user') && !schema_foreign_key_exists_now($koneksi, 'perbaikan_barang', 'fk_perbaikan_created_by_foundation')) {
            $koneksi->query("ALTER TABLE perbaikan_barang ADD CONSTRAINT fk_perbaikan_created_by_foundation FOREIGN KEY (created_by) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE");
        }
        if (schema_table_exists_now($koneksi, 'user') && !schema_foreign_key_exists_now($koneksi, 'perbaikan_barang', 'fk_perbaikan_updated_by_foundation')) {
            $koneksi->query("ALTER TABLE perbaikan_barang ADD CONSTRAINT fk_perbaikan_updated_by_foundation FOREIGN KEY (updated_by) REFERENCES user(id_user) ON DELETE SET NULL ON UPDATE CASCADE");
        }
    }

    if (schema_table_exists_now($koneksi, 'user')) {
        $koneksi->query(
            "CREATE OR REPLACE VIEW users AS
             SELECT
                id_user AS id,
                nama,
                username,
                password,
                role,
                status,
                kategori_user,
                bidang_id AS bidang
             FROM `user`"
        );
    }

    if (schema_table_exists_now($koneksi, 'produk')) {
        $koneksi->query(
            "CREATE OR REPLACE VIEW barang AS
             SELECT
                p.id_produk AS id,
                p.kode_produk AS kode_barang,
                p.nama_produk AS nama,
                p.deskripsi,
                p.id_gudang AS gudang_id,
                COALESCE(p.dipinjam_oleh, p.id_user) AS dipinjam_oleh,
                p.tanggal_pinjam,
                p.tanggal_kembali,
                CASE
                    WHEN LOWER(TRIM(COALESCE(p.status, ''))) IN ('dipinjam', 'sedang digunakan', 'digunakan') THEN 'dipinjam'
                    WHEN LOWER(TRIM(COALESCE(p.status, ''))) IN ('rusak') THEN 'rusak'
                    WHEN LOWER(TRIM(COALESCE(p.status, ''))) IN ('dalam perbaikan', 'perbaikan', 'diperbaiki') THEN 'diperbaiki'
                    ELSE 'tersedia'
                END AS status
             FROM produk p"
        );
    }

    if (schema_table_exists_now($koneksi, 'tracking_barang')) {
        $logBarangUserCols = [];
        foreach (['id_user_changed', 'id_user_sesudah', 'id_user_terkait', 'id_user', 'user_id', 'id_user_sebelum'] as $candidateColumn) {
            if (schema_has_column_now($koneksi, 'tracking_barang', $candidateColumn)) {
                $logBarangUserCols[] = 'tb.' . $candidateColumn;
            }
        }

        $logBarangUserExpr = !empty($logBarangUserCols)
            ? 'COALESCE(' . implode(', ', $logBarangUserCols) . ')'
            : 'NULL';
        $logBarangActionCol = schema_find_existing_column($koneksi, 'tracking_barang', ['activity_type', 'aktivitas']);
        $logBarangDateCol = schema_find_existing_column($koneksi, 'tracking_barang', ['changed_at', 'created_at', 'updated_at']);
        $logBarangNoteCol = schema_find_existing_column($koneksi, 'tracking_barang', ['note', 'catatan']);

        $koneksi->query(
            "CREATE OR REPLACE VIEW log_barang AS
             SELECT
                tb.id_tracking AS id,
                tb.id_produk AS barang_id,
                $logBarangUserExpr AS user_id,
                " . ($logBarangActionCol !== null ? ('tb.' . $logBarangActionCol) : "NULL") . " AS aksi,
                " . ($logBarangDateCol !== null ? ('tb.' . $logBarangDateCol) : "NULL") . " AS tanggal,
                " . ($logBarangNoteCol !== null ? ('tb.' . $logBarangNoteCol) : "NULL") . " AS catatan
             FROM tracking_barang tb"
        );
    }

    if (schema_table_exists_now($koneksi, 'mutasi_barang') && schema_table_exists_now($koneksi, 'mutasi_barang_detail')) {
        $koneksi->query(
            "CREATE OR REPLACE VIEW mutasi AS
             SELECT
                d.id AS id,
                d.produk_id AS barang_id,
                h.gudang_asal_id AS dari_gudang,
                h.gudang_tujuan_id AS ke_gudang,
                h.tanggal_mutasi AS tanggal
             FROM mutasi_barang_detail d
             INNER JOIN mutasi_barang h ON h.id = d.mutasi_id"
        );
    }

    if (schema_table_exists_now($koneksi, 'serah_terima_barang') && schema_table_exists_now($koneksi, 'serah_terima_detail')) {
        $koneksi->query(
            "CREATE OR REPLACE VIEW serah_terima AS
             SELECT
                d.id AS id,
                d.produk_id AS barang_id,
                h.pihak_penerima_user_id AS user_id,
                h.tanggal_serah_terima AS tanggal,
                h.status
             FROM serah_terima_detail d
             INNER JOIN serah_terima_barang h ON h.id = d.serah_terima_id"
        );
    }

    if (schema_table_exists_now($koneksi, 'perbaikan_barang')) {
        $koneksi->query(
            "CREATE OR REPLACE VIEW perbaikan AS
             SELECT
                id_perbaikan AS id,
                id_produk AS barang_id,
                tanggal_mulai,
                tanggal_selesai,
                deskripsi,
                status
             FROM perbaikan_barang"
        );
    }
}

function ensure_priority_two_schema($koneksi) {
    static $done = false;
    static $isReady = false;

    if ($done) {
        return $isReady;
    }
    $done = true;

    $schemaQueries = [
        "CREATE TABLE IF NOT EXISTS mutasi_barang (
            id INT NOT NULL AUTO_INCREMENT,
            kode_mutasi VARCHAR(60) NOT NULL,
            tanggal_mutasi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            gudang_asal_id INT NOT NULL,
            gudang_tujuan_id INT NOT NULL,
            jenis_barang ENUM('asset','consumable','campuran') NOT NULL DEFAULT 'campuran',
            catatan TEXT NULL,
            dokumen_file VARCHAR(255) NULL,
            status ENUM('draft','disetujui','selesai','dibatalkan') NOT NULL DEFAULT 'draft',
            created_by INT NULL,
            created_by_name VARCHAR(255) NOT NULL,
            approved_by INT NULL,
            approved_by_name VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_mutasi_barang_kode (kode_mutasi),
            KEY idx_mutasi_barang_tanggal (tanggal_mutasi),
            KEY idx_mutasi_barang_gudang_asal (gudang_asal_id),
            KEY idx_mutasi_barang_gudang_tujuan (gudang_tujuan_id),
            KEY idx_mutasi_barang_status (status),
            KEY idx_mutasi_barang_created_by (created_by),
            KEY idx_mutasi_barang_approved_by (approved_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS mutasi_barang_detail (
            id INT NOT NULL AUTO_INCREMENT,
            mutasi_id INT NOT NULL,
            produk_id INT NOT NULL,
            unit_barang_id INT NULL,
            qty INT NOT NULL DEFAULT 1,
            satuan_snapshot VARCHAR(50) NULL,
            kondisi_sebelum VARCHAR(50) NULL,
            kondisi_sesudah VARCHAR(50) NULL,
            catatan_detail TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_mutasi_barang_detail_mutasi (mutasi_id),
            KEY idx_mutasi_barang_detail_produk (produk_id),
            KEY idx_mutasi_barang_detail_unit (unit_barang_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS serah_terima_barang (
            id INT NOT NULL AUTO_INCREMENT,
            kode_serah_terima VARCHAR(60) NOT NULL,
            tanggal_serah_terima DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            jenis_tujuan ENUM('user','lokasi','departemen') NOT NULL DEFAULT 'user',
            pihak_penyerah_user_id INT NULL,
            pihak_penyerah_nama VARCHAR(255) NOT NULL,
            pihak_penerima_user_id INT NULL,
            pihak_penerima_nama VARCHAR(255) NOT NULL,
            gudang_asal_id INT NULL,
            lokasi_tujuan VARCHAR(255) NULL,
            catatan TEXT NULL,
            dokumen_file VARCHAR(255) NULL,
            status ENUM('aktif','dikembalikan','dibatalkan') NOT NULL DEFAULT 'aktif',
            created_by INT NULL,
            created_by_name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_serah_terima_barang_kode (kode_serah_terima),
            KEY idx_serah_terima_tanggal (tanggal_serah_terima),
            KEY idx_serah_terima_status (status),
            KEY idx_serah_terima_gudang_asal (gudang_asal_id),
            KEY idx_serah_terima_penyerah (pihak_penyerah_user_id),
            KEY idx_serah_terima_penerima (pihak_penerima_user_id),
            KEY idx_serah_terima_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS serah_terima_detail (
            id INT NOT NULL AUTO_INCREMENT,
            serah_terima_id INT NOT NULL,
            produk_id INT NOT NULL,
            unit_barang_id INT NULL,
            qty INT NOT NULL DEFAULT 1,
            kondisi_serah VARCHAR(50) NOT NULL DEFAULT 'baik',
            kondisi_kembali VARCHAR(50) NULL,
            tanggal_kembali DATETIME NULL,
            catatan_detail TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_serah_terima_detail_header (serah_terima_id),
            KEY idx_serah_terima_detail_produk (produk_id),
            KEY idx_serah_terima_detail_unit (unit_barang_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS dokumen_transaksi (
            id INT NOT NULL AUTO_INCREMENT,
            ref_type ENUM('mutasi','handover','barang_masuk','barang_keluar') NOT NULL,
            ref_id INT NOT NULL,
            jenis_dokumen VARCHAR(100) NOT NULL DEFAULT 'lampiran',
            file_path VARCHAR(255) NOT NULL,
            uploaded_by INT NULL,
            uploaded_by_name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_dokumen_transaksi_ref (ref_type, ref_id),
            KEY idx_dokumen_transaksi_uploaded_by (uploaded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];

    foreach ($schemaQueries as $schemaQuery) {
        $koneksi->query($schemaQuery);
    }

    $requiredTables = [
        'mutasi_barang',
        'mutasi_barang_detail',
        'serah_terima_barang',
        'serah_terima_detail',
    ];

    $isReady = true;
    foreach ($requiredTables as $requiredTable) {
        if (!schema_table_exists_now($koneksi, $requiredTable)) {
            $isReady = false;
            break;
        }
    }

    return $isReady;
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
    $unitCodeColumn = schema_find_existing_column($koneksi, 'unit_barang', ['serial_number', 'kode_unit']);
    $sql = "SELECT md.*,
                   p.kode_produk,
                   p.nama_produk,
                   " . ($unitCodeColumn !== null ? ('ub.' . $unitCodeColumn) : 'NULL') . " AS kode_unit
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
    $unitCodeColumn = schema_find_existing_column($koneksi, 'unit_barang', ['serial_number', 'kode_unit']);
    $sql = "SELECT std.*,
                   p.kode_produk,
                   p.nama_produk,
                   " . ($unitCodeColumn !== null ? ('ub.' . $unitCodeColumn) : 'NULL') . " AS kode_unit
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

function get_latest_product_photo($koneksi, $id_produk) {
    if (!schema_table_exists_now($koneksi, 'histori_log')) {
        return null;
    }

    $id_produk = intval($id_produk);
    if ($id_produk < 1) {
        return null;
    }

    $query = "SELECT id, ref_type, user_name_snapshot, meta_json, created_at
              FROM histori_log
              WHERE produk_id = ?
                AND meta_json IS NOT NULL";

    if (PHP_VERSION_ID >= 50700) {
        $query .= " AND JSON_EXTRACT(meta_json, '$.foto_dokumentasi') IS NOT NULL";
    }

    $query .= " ORDER BY created_at DESC LIMIT 1";

    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id_produk);
    if (!$stmt->execute()) {
        return null;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row) {
        return null;
    }

    $metaJson = decode_inventory_meta_json($row['meta_json']);
    $photoPath = trim((string) ($metaJson['foto_dokumentasi'] ?? ''));

    if ($photoPath === '') {
        return null;
    }

    return [
        'photo_path' => $photoPath,
        'created_at' => $row['created_at'],
        'ref_type' => $row['ref_type'],
        'actor_name' => $row['user_name_snapshot'],
    ];
}

ensure_priority_one_schema($koneksi);
ensure_priority_two_schema($koneksi);
ensure_stokgudang_unique_index($koneksi);
migrate_legacy_qr_urls_to_public($koneksi);

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['role'])) {
    $_SESSION['role'] = normalize_user_role($_SESSION['role']);
}

?>
