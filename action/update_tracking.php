<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_produk',
]);

function normalize_tracking_value($value) {
    return strtolower(trim((string) ($value ?? '')));
}

function normalize_requested_activity($value) {
    static $map = [
        'pinjam' => 'dipinjam',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'dipinjam',
        'kembali' => 'dikembalikan',
        'dikembalikan' => 'dikembalikan',
        'release' => 'dikembalikan',
        'pindah' => 'mutasi',
        'mutasi' => 'mutasi',
        'serah_terima' => 'serah_terima',
        'serah terima' => 'serah_terima',
        'perbaikan' => 'perbaikan',
        'rusak' => 'rusak',
    ];

    $normalized = normalize_tracking_value($value);
    return $map[$normalized] ?? '';
}

function normalize_unit_status($value) {
    static $statusMap = [
        'sedang digunakan' => 'digunakan',
        'digunakan' => 'digunakan',
        'dipinjam' => 'dipinjam',
        'tersedia' => 'tersedia',
        'rusak' => 'rusak',
        'dalam perbaikan' => 'perbaikan',
        'perbaikan' => 'perbaikan',
    ];

    $normalized = normalize_tracking_value($value);
    return $statusMap[$normalized] ?? null;
}

function normalize_product_status($value) {
    static $statusMap = [
        'tersedia' => 'tersedia',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'sedang digunakan',
        'sedang digunakan' => 'sedang digunakan',
        'dipindahkan' => 'dipindahkan',
        'perbaikan' => 'dalam perbaikan',
        'dalam perbaikan' => 'dalam perbaikan',
        'rusak' => 'rusak',
        'tidak aktif' => 'tidak aktif',
    ];

    $normalized = normalize_tracking_value($value);
    return $statusMap[$normalized] ?? null;
}

function normalize_tracking_kondisi($value) {
    static $kondisiMap = [
        'baik' => 'baik',
        'rusak' => 'rusak',
        'diperbaiki' => 'diperbaiki',
        'usang' => 'usang',
        'lainnya' => 'lainnya',
    ];

    $normalized = normalize_tracking_value($value);
    return $kondisiMap[$normalized] ?? null;
}

function resolve_unit_status_by_activity($activityType) {
    static $activityMap = [
        'dipinjam' => 'dipinjam',
        'serah_terima' => 'dipinjam',
        'mutasi' => 'tersedia',
        'dikembalikan' => 'tersedia',
        'perbaikan' => 'perbaikan',
        'rusak' => 'rusak',
    ];

    $normalized = normalize_tracking_value($activityType);
    return $activityMap[$normalized] ?? null;
}

function resolve_product_status_by_activity($activityType) {
    static $activityMap = [
        'dipinjam' => 'dipinjam',
        'serah_terima' => 'dipinjam',
        'mutasi' => 'dipindahkan',
        'dikembalikan' => 'tersedia',
        'perbaikan' => 'dalam perbaikan',
        'rusak' => 'rusak',
    ];

    $normalized = normalize_tracking_value($activityType);
    return $activityMap[$normalized] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=data_produk');
    exit;
}

$id_produk = intval($_POST['id_produk'] ?? 0);
$activity_type = normalize_requested_activity($_POST['jenis_aktivitas'] ?? ($_POST['activity_type'] ?? ''));
$status_input = $_POST['status'] ?? '';
$kondisi_input = $_POST['kondisi'] ?? '';
$status_baru = normalize_unit_status($status_input);
$kondisi_baru = normalize_tracking_kondisi($kondisi_input);
$id_gudang_baru = isset($_POST['id_gudang']) && $_POST['id_gudang'] !== '' ? intval($_POST['id_gudang']) : null;
$lokasiInput = $_POST['lokasi'] ?? ($_POST['lokasi_custom'] ?? '');
$lokasi_custom_baru = trim((string) $lokasiInput) !== '' ? $koneksi->real_escape_string(htmlspecialchars(trim((string) $lokasiInput), ENT_QUOTES, 'UTF-8')) : null;
$id_user_baru = isset($_POST['user_terkait']) && $_POST['user_terkait'] !== ''
    ? intval($_POST['user_terkait'])
    : (isset($_POST['id_user']) && $_POST['id_user'] !== '' ? intval($_POST['id_user']) : null);
$noteInput = $_POST['catatan'] ?? ($_POST['note'] ?? '');
$note = trim((string) $noteInput) !== '' ? $koneksi->real_escape_string(htmlspecialchars(trim((string) $noteInput), ENT_QUOTES, 'UTF-8')) : null;
$operator = $_SESSION['id_user'] ?? null;

if ($id_produk <= 0) {
    header('Location: ../index.php?page=data_produk&error=Produk tidak valid');
    exit;
}

if ($activity_type === '') {
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=Jenis aktivitas wajib dipilih');
    exit;
}

// Mutasi dan serah terima harus lewat modul resmi masing-masing
if (in_array($activity_type, ['mutasi', 'serah_terima'], true)) {
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=' . urlencode('Mutasi dan serah terima harus dilakukan melalui modul resmi, bukan melalui tracking.'));
    exit;
}

$requireUserActivities = ['dipinjam'];
if (in_array($activity_type, $requireUserActivities, true) && $id_user_baru === null) {
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=User/Penerima wajib dipilih untuk aktivitas ini');
    exit;
}

if ($activity_type === 'perbaikan' && $lokasi_custom_baru === null) {
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=Vendor atau lokasi perbaikan wajib diisi');
    exit;
}

if ($activity_type === 'rusak' && $note === null) {
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=Catatan kerusakan wajib diisi');
    exit;
}

if (trim((string) $status_input) === '') {
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=Status baru tidak boleh kosong');
    exit;
}

// Ambil data produk saat ini
$produk = $koneksi->query("SELECT * FROM produk WHERE id_produk = $id_produk")->fetch_assoc();
if (!$produk) {
    header('Location: ../index.php?page=data_produk&error=produk_not_found');
    exit;
}

$related_user_id = $id_user_baru
    ?? get_foundation_barang_borrower_id($produk)
    ?? nullable_int_id($produk['id_user'] ?? null);

$tujuanText = null;
if ($activity_type === 'mutasi') {
    $tujuanText = $id_gudang_baru ? get_gudang_name_by_id($koneksi, $id_gudang_baru) : null;
} elseif (in_array($activity_type, ['dipinjam', 'serah_terima'], true)) {
    $tujuanText = $related_user_id ? get_user_name_by_id($koneksi, $related_user_id) : null;
} elseif ($activity_type === 'perbaikan') {
    $tujuanText = $lokasi_custom_baru;
}

if ($tujuanText !== null) {
    $tujuanText = trim((string) $tujuanText);
    if ($tujuanText === '') {
        $tujuanText = null;
    }
}

$trackingNote = $note;
if ($tujuanText !== null) {
    $tujuanPrefix = 'Tujuan/Penerima: ' . $tujuanText;
    $trackingNote = $trackingNote ? ($tujuanPrefix . ' | ' . $trackingNote) : $tujuanPrefix;
}

$status_baru_produk = resolve_product_status_by_activity($activity_type)
    ?? normalize_product_status($status_input)
    ?? normalize_product_status($produk['status'])
    ?? 'tersedia';
$kondisi_baru_produk = $kondisi_baru
    ?? normalize_tracking_kondisi($produk['kondisi'])
    ?? 'baik';

$lokasi_sebelum = '-';
if (!empty($produk['id_gudang'])) {
    $lok = $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = " . intval($produk['id_gudang']))->fetch_assoc();
    $lokasi_sebelum = $lok['nama_gudang'] ?? '-';
} elseif (!empty($produk['lokasi_custom'])) {
    $lokasi_sebelum = $produk['lokasi_custom'];
}

$lokasi_sesudah = $lokasi_custom_baru ?: ($id_gudang_baru ? $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = $id_gudang_baru")->fetch_assoc()['nama_gudang'] : $lokasi_sebelum);

// Untuk asset, lakukan update unit_barang/tracking_barang saja
if ($produk['tipe_barang'] === 'asset') {
    $id_units = [];
    if (!empty($_POST['id_unit'])) {
        if (is_array($_POST['id_unit'])) {
            $id_units = array_merge($id_units, array_map('intval', $_POST['id_unit']));
        } else {
            $id_units[] = intval($_POST['id_unit']);
        }
    }
    if (!empty($_POST['id_unit_barang'])) {
        if (is_array($_POST['id_unit_barang'])) {
            $id_units = array_merge($id_units, array_map('intval', $_POST['id_unit_barang']));
        } else {
            $id_units[] = intval($_POST['id_unit_barang']);
        }
    }
    $id_units = array_values(array_unique(array_filter($id_units, static function ($id) {
        return intval($id) > 0;
    })));

    if (empty($id_units)) {
        header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=' . urlencode('Pilih minimal satu unit untuk diproses'));
        exit;
    }

    $processedAny = 0;
    $trackedAny = 0;
    $trackedUnitIds = [];
    $transactionAt = date('Y-m-d H:i:s');

    $koneksi->begin_transaction();
    try {

    foreach ($id_units as $id_unit) {
        $unit = get_unit_barang_by_id($koneksi, $id_unit);
        if (!$unit || $unit['id_produk'] !== $id_produk) {
            continue;
        }
        $processedAny++;

        $unitLokasiSebelum = $unit['lokasi_custom'] ?: ($unit['id_gudang'] ? ($koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = " . intval($unit['id_gudang']))->fetch_assoc()['nama_gudang'] ?? '') : '');

        $effectiveUserId = $id_user_baru ?? ($unit['id_user'] ?? null);
        $usedLocationText = 'Digunakan oleh user';
        if ($effectiveUserId) {
            $usedUserName = trim((string) (get_user_name_by_id($koneksi, $effectiveUserId) ?? ''));
            if ($usedUserName !== '') {
                $usedLocationText = 'Digunakan oleh ' . $usedUserName;
            }
        }

        if (in_array($activity_type, ['dipinjam', 'serah_terima'], true)) {
            $unitLokasiSesudah = $usedLocationText;
        } else {
            $unitLokasiSesudah = $lokasi_custom_baru ?: ($id_gudang_baru ? $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = $id_gudang_baru")->fetch_assoc()['nama_gudang'] : $unitLokasiSebelum);
        }

        $fields = [];
        $status_unit_saat_ini = normalize_unit_status($unit['status']);
        $status_baru_unit = resolve_unit_status_by_activity($activity_type)
            ?? $status_baru
            ?? $status_unit_saat_ini
            ?? 'tersedia';
        $fields['status'] = $status_baru_unit;
        $fields['tersedia'] = ($status_baru_unit === 'tersedia') ? 1 : 0;

        if (normalize_tracking_value($kondisi_input) !== '') {
            $fields['kondisi'] = $kondisi_baru
                ?? normalize_tracking_kondisi($unit['kondisi'])
                ?? 'baik';
        }
        if ($id_gudang_baru !== null) {
            $fields['id_gudang'] = $id_gudang_baru;
            $fields['lokasi_custom'] = null;
        }
        if ($lokasi_custom_baru !== null) {
            $fields['lokasi_custom'] = $lokasi_custom_baru;
        }
        if ($id_user_baru !== null) {
            $fields['id_user'] = $id_user_baru;
        }

        // Saat dikembalikan, pastikan id_user di-NULL-kan dan updated_at diperbarui
        if (in_array($activity_type, ['dikembalikan'], true)) {
            $fields['id_user'] = null;
            $fields['updated_at'] = date('Y-m-d H:i:s');
        }

        if (!empty($fields)) {
            $updated = update_unit_barang($koneksi, $id_unit, $fields);
            if (!$updated) {
                throw new Exception('Gagal update unit asset ID ' . $id_unit);
            }
        }

        $unitTrackingNote = $trackingNote;

        $trackingSaved = log_tracking_unit_barang($koneksi, [
            'id_unit' => $id_unit,
            'id_unit_barang' => $id_unit,
            'id_produk' => $id_produk,
            'activity_type' => $activity_type,
            'status_sebelum' => $unit['status'],
            'status_sesudah' => $fields['status'],
            'kondisi_sebelum' => $unit['kondisi'],
            'kondisi_sesudah' => $fields['kondisi'] ?? $unit['kondisi'],
            'lokasi_sebelum' => $unitLokasiSebelum,
            'lokasi_sesudah' => $unitLokasiSesudah,
            'id_user_sebelum' => $unit['id_user'],
            'id_user_sesudah' => array_key_exists('id_user', $fields) ? $fields['id_user'] : $unit['id_user'],
            'id_user_terkait' => $unit['id_user'] ?? $id_user_baru,
            'note' => $unitTrackingNote,
            'id_user_changed' => $operator,
            'changed_at' => $transactionAt,
            'created_at' => $transactionAt,
        ]);
        if (!$trackingSaved) {
            $dbError = trim((string) ($koneksi->error ?? ''));
            throw new Exception('Gagal menyimpan tracking unit ID ' . $id_unit . ($dbError !== '' ? (' | DB: ' . $dbError) : ''));
        }
        $trackedAny++;
        $trackedUnitIds[] = $id_unit;
        error_log('[TRACKING_DEBUG] insert tracking_barang OK id_produk=' . $id_produk . ' id_unit=' . $id_unit . ' ts=' . $transactionAt);

        log_activity($koneksi, [
            'id_user' => $operator,
            'role_user' => get_current_user_role(),
            'action_name' => 'tracking_unit_' . ($activity_type !== '' ? $activity_type : 'update'),
            'entity_type' => 'unit',
            'entity_id' => $id_unit,
            'entity_label' => $unit['kode_unit'] ?? $unit['serial_number'] ?? ('Unit #' . $id_unit),
            'description' => 'Tracking unit asset diperbarui',
            'id_produk' => $id_produk,
            'id_unit_barang' => $id_unit,
            'id_gudang' => $id_gudang_baru ?? ($unit['id_gudang'] ?? null),
            'metadata_json' => [
                'activity_type' => $activity_type,
                'status_sebelum' => $unit['status'],
                'status_sesudah' => $fields['status'] ?? $unit['status'],
                'lokasi_sebelum' => $unitLokasiSebelum,
                'lokasi_sesudah' => $tujuanText ?? $unitLokasiSesudah,
                'id_user_sebelum' => $unit['id_user'],
                'id_user_sesudah' => array_key_exists('id_user', $fields) ? $fields['id_user'] : $unit['id_user'],
                'tujuan' => $tujuanText,
                'note' => $unitTrackingNote,
            ],
        ]);
    }

        if ($processedAny === 0) {
            throw new Exception('Unit asset tidak ditemukan atau tidak valid');
        }

        if ($trackedAny === 0) {
            throw new Exception('Tracking unit tidak tersimpan');
        }

        sync_foundation_barang_from_units($koneksi, $id_produk, [
            'activity_type' => $activity_type,
            'sync_perbaikan' => in_array($activity_type, ['perbaikan', 'dikembalikan', 'rusak'], true),
            'actor_user_id' => $operator,
            'note' => $trackingNote,
        ]);

        $_SESSION['tracking_debug_insert'] = 'INSERT tracking sukses. id_produk=' . $id_produk
            . ' | unit=' . implode(',', $trackedUnitIds)
            . ' | jumlah=' . $trackedAny
            . ' | waktu=' . $transactionAt;

        $koneksi->commit();
    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['tracking_debug_insert'] = 'INSERT tracking gagal. id_produk=' . $id_produk . ' | error=' . $e->getMessage();
        error_log('[TRACKING_DEBUG] insert tracking_barang FAILED id_produk=' . $id_produk . ' error=' . $e->getMessage());
        header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=' . urlencode($e->getMessage()));
        exit;
    }

    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&success=tracking_updated');
    exit;
}

// Hitung ketersediaan dari status yang dipilih
$tersedia_baru = ($status_baru_produk === 'tersedia') ? 1 : 0;

$koneksi->begin_transaction();
try {
    // Update status produk
    $updateSql = "UPDATE produk SET status = '$status_baru_produk', kondisi = '$kondisi_baru_produk', tersedia = $tersedia_baru, last_tracked_at = NOW()";
    if ($id_gudang_baru !== null) {
        $updateSql .= ", id_gudang = $id_gudang_baru, lokasi_custom = NULL";
    } elseif ($lokasi_custom_baru !== null) {
        $updateSql .= ", lokasi_custom = '$lokasi_custom_baru'";
    }
    if ($id_user_baru !== null) {
        $updateSql .= ", id_user = $id_user_baru";
    }
    $updateSql .= " WHERE id_produk = $id_produk";
    $koneksi->query($updateSql);

    apply_foundation_barang_state($koneksi, $id_produk, [
        'activity_type' => $activity_type,
        'status' => $status_baru_produk,
        'id_user' => $related_user_id,
        'dipinjam_oleh' => $related_user_id,
        'sync_perbaikan' => in_array($activity_type, ['perbaikan', 'dikembalikan', 'rusak'], true),
        'actor_user_id' => $operator,
        'note' => $trackingNote,
    ]);

    // Insert historis tracking
    $trackingSaved = log_tracking_history($koneksi, [
        'id_produk' => $id_produk,
        'kode_produk' => $produk['kode_produk'] ?? null,
        'status_sebelum' => $produk['status'],
        'status_sesudah' => $status_baru_produk,
        'kondisi_sebelum' => $produk['kondisi'],
        'kondisi_sesudah' => $kondisi_baru_produk,
        'lokasi_sebelum' => $lokasi_sebelum,
        'lokasi_sesudah' => $lokasi_sesudah,
        'id_user_sebelum' => $produk['id_user'],
        'id_user_sesudah' => $activity_type === 'dikembalikan' ? null : $related_user_id,
        'id_user_terkait' => $related_user_id,
        'activity_type' => $activity_type,
        'note' => $trackingNote,
        'id_user_changed' => $operator
    ]);
    if (!$trackingSaved) {
        $dbError = trim((string) ($koneksi->error ?? ''));
        throw new Exception('Gagal menyimpan tracking produk' . ($dbError !== '' ? (' | DB: ' . $dbError) : ''));
    }
    error_log('[TRACKING_DEBUG] insert tracking_barang OK id_produk=' . $id_produk . ' scope=produk ts=' . date('Y-m-d H:i:s'));

    log_activity($koneksi, [
        'id_user' => $operator,
        'role_user' => get_current_user_role(),
        'action_name' => 'tracking_produk_' . ($activity_type !== '' ? $activity_type : 'update'),
        'entity_type' => 'produk',
        'entity_id' => $id_produk,
        'entity_label' => ($produk['kode_produk'] ?? 'Produk') . ' - ' . ($produk['nama_produk'] ?? ''),
        'description' => 'Tracking barang diperbarui',
        'id_produk' => $id_produk,
        'id_gudang' => $id_gudang_baru ?? ($produk['id_gudang'] ?? null),
        'metadata_json' => [
            'activity_type' => $activity_type,
            'status_sebelum' => $produk['status'],
            'status_sesudah' => $status_baru_produk,
            'kondisi_sebelum' => $produk['kondisi'],
            'kondisi_sesudah' => $kondisi_baru_produk,
            'lokasi_sebelum' => $lokasi_sebelum,
            'lokasi_sesudah' => $tujuanText ?? $lokasi_sesudah,
            'id_user_sebelum' => $produk['id_user'],
            'id_user_sesudah' => $activity_type === 'dikembalikan' ? null : $related_user_id,
            'tujuan' => $tujuanText,
            'note' => $trackingNote,
        ],
    ]);

    if (in_array($activity_type, ['dipinjam'], true)) {
        $koneksi->query("UPDATE produk SET tersedia = 0 WHERE id_produk = $id_produk");
        if ($related_user_id !== null) {
            $koneksi->query("INSERT INTO peminjaman (id_produk, id_user, jumlah, id_gudang, tanggal_pinjam, status, id_user_created) VALUES ($id_produk, $related_user_id, 1, " . ($id_gudang_baru ?? 'NULL') . ", CURDATE(), 'dipinjam', " . ($operator ?? 'NULL') . ")");
        }
    }
    if ($activity_type === 'dikembalikan') {
        $koneksi->query("UPDATE produk SET tersedia = 1 WHERE id_produk = $id_produk");
        if ($related_user_id !== null) {
            $koneksi->query("UPDATE peminjaman SET status = 'kembali', tanggal_kembali_aktual = CURDATE() WHERE id_produk = $id_produk AND id_user = $related_user_id AND status = 'dipinjam'");
        } else {
            $koneksi->query("UPDATE peminjaman SET status = 'kembali', tanggal_kembali_aktual = CURDATE() WHERE id_produk = $id_produk AND status = 'dipinjam'");
        }
    }

    $koneksi->commit();
    $_SESSION['tracking_debug_insert'] = 'INSERT tracking sukses. id_produk=' . $id_produk . ' | scope=produk';
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&success=tracking_updated');
} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['tracking_debug_insert'] = 'INSERT tracking gagal. id_produk=' . $id_produk . ' | error=' . $e->getMessage();
    error_log('[TRACKING_DEBUG] insert tracking_barang FAILED id_produk=' . $id_produk . ' error=' . $e->getMessage());
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=' . urlencode($e->getMessage()));
}
exit;
