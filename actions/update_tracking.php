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
        'pinjam' => 'dipinjam',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'digunakan',
        'sedang digunakan' => 'digunakan',
        'kembali' => 'tersedia',
        'dikembalikan' => 'tersedia',
        'perbaikan' => 'perbaikan',
    ];

    $normalized = normalize_tracking_value($activityType);
    return $activityMap[$normalized] ?? null;
}

function resolve_product_status_by_activity($activityType) {
    static $activityMap = [
        'pinjam' => 'dipinjam',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'sedang digunakan',
        'sedang digunakan' => 'sedang digunakan',
        'kembali' => 'tersedia',
        'dikembalikan' => 'tersedia',
        'perbaikan' => 'dalam perbaikan',
    ];

    $normalized = normalize_tracking_value($activityType);
    return $activityMap[$normalized] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=data_produk');
    exit;
}

$id_produk = intval($_POST['id_produk']);
$activity_type = normalize_tracking_value($_POST['activity_type'] ?? '');
$status_input = $_POST['status'] ?? '';
$kondisi_input = $_POST['kondisi'] ?? '';
$status_baru = normalize_unit_status($status_input);
$kondisi_baru = normalize_tracking_kondisi($kondisi_input);
$id_gudang_baru = isset($_POST['id_gudang']) && $_POST['id_gudang'] !== '' ? intval($_POST['id_gudang']) : null;
$lokasi_custom_baru = isset($_POST['lokasi_custom']) && trim((string) $_POST['lokasi_custom']) !== '' ? $koneksi->real_escape_string(trim((string) $_POST['lokasi_custom'])) : null;
$id_user_baru = isset($_POST['id_user']) && $_POST['id_user'] !== '' ? intval($_POST['id_user']) : null;
$note = isset($_POST['note']) && trim((string) $_POST['note']) !== '' ? $koneksi->real_escape_string(trim((string) $_POST['note'])) : null;
$operator = $_SESSION['id_user'] ?? null;

// Ambil data produk saat ini
$produk = $koneksi->query("SELECT * FROM produk WHERE id_produk = $id_produk")->fetch_assoc();
if (!$produk) {
    header('Location: ../index.php?page=data_produk&error=produk_not_found');
    exit;
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

// Untuk asset, lakukan update unit_barang/riwayat_unit_barang saja
if ($produk['tipe_barang'] === 'asset') {
    $id_units = [];
    if (!empty($_POST['id_unit_barang'])) {
        if (is_array($_POST['id_unit_barang'])) {
            $id_units = array_map('intval', $_POST['id_unit_barang']);
        } else {
            $id_units = [intval($_POST['id_unit_barang'])];
        }
    }

    if (empty($id_units)) {
        header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=id_unit_barang_required');
        exit;
    }

    $processedAny = 0;
    $updatedAny = false;

    foreach ($id_units as $id_unit) {
        $unit = get_unit_barang_by_id($koneksi, $id_unit);
        if (!$unit || $unit['id_produk'] !== $id_produk) {
            continue;
        }
        $processedAny++;

        $unitLokasiSebelum = $unit['lokasi_custom'] ?: ($unit['id_gudang'] ? ($koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = " . intval($unit['id_gudang']))->fetch_assoc()['nama_gudang'] ?? '') : '');
        $unitLokasiSesudah = $lokasi_custom_baru ?: ($id_gudang_baru ? $koneksi->query("SELECT nama_gudang FROM gudang WHERE id_gudang = $id_gudang_baru")->fetch_assoc()['nama_gudang'] : $unitLokasiSebelum);

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

        if (!empty($fields)) {
            $updated = update_unit_barang($koneksi, $id_unit, $fields);
            if (!$updated) {
                continue;
            }
            $updatedAny = true;
        }

        log_riwayat_unit_barang($koneksi, [
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
            'id_user_sesudah' => $fields['id_user'] ?? $unit['id_user'],
            'id_user_terkait' => $fields['id_user'] ?? $unit['id_user'],
            'note' => $note,
            'id_user_changed' => $operator
        ]);

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
                'lokasi_sesudah' => $unitLokasiSesudah,
                'id_user_sebelum' => $unit['id_user'],
                'id_user_sesudah' => $fields['id_user'] ?? $unit['id_user'],
                'note' => $note,
            ],
        ]);
    }

    if ($processedAny === 0) {
        header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=unit_update_failed');
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
        // Update stokgudang jika pindah gudang
        if ($activity_type === 'pindah') {
            $koneksi->query("UPDATE StokGudang SET id_gudang = $id_gudang_baru WHERE id_produk = $id_produk");
        }
    } elseif ($lokasi_custom_baru !== null) {
        $updateSql .= ", lokasi_custom = '$lokasi_custom_baru'";
    }
    if ($id_user_baru !== null) {
        $updateSql .= ", id_user = $id_user_baru";
    }
    $updateSql .= " WHERE id_produk = $id_produk";
    $koneksi->query($updateSql);

    // Insert historis tracking
    log_tracking_history($koneksi, [
        'id_produk' => $id_produk,
        'kode_produk' => $produk['kode_produk'] ?? null,
        'status_sebelum' => $produk['status'],
        'status_sesudah' => $status_baru_produk,
        'kondisi_sebelum' => $produk['kondisi'],
        'kondisi_sesudah' => $kondisi_baru_produk,
        'lokasi_sebelum' => $lokasi_sebelum,
        'lokasi_sesudah' => $lokasi_sesudah,
        'id_user_sebelum' => $produk['id_user'],
        'id_user_sesudah' => $id_user_baru,
        'id_user_terkait' => $id_user_baru,
        'activity_type' => $activity_type,
        'note' => $note,
        'id_user_changed' => $operator
    ]);

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
            'lokasi_sesudah' => $lokasi_sesudah,
            'id_user_sebelum' => $produk['id_user'],
            'id_user_sesudah' => $id_user_baru,
            'note' => $note,
        ],
    ]);

    if ($activity_type === 'pinjam') {
        $koneksi->query("UPDATE produk SET tersedia = 0 WHERE id_produk = $id_produk");
        $koneksi->query("INSERT INTO peminjaman (id_produk, id_user, jumlah, id_gudang, tanggal_pinjam, status, id_user_created) VALUES ($id_produk, $id_user_baru, 1, " . ($id_gudang_baru ?? 'NULL') . ", CURDATE(), 'dipinjam', " . ($operator ?? 'NULL') . ")");
    }
    if ($activity_type === 'kembali') {
        $koneksi->query("UPDATE produk SET tersedia = 1 WHERE id_produk = $id_produk");
        $koneksi->query("UPDATE peminjaman SET status = 'kembali', tanggal_kembali_aktual = CURDATE() WHERE id_produk = $id_produk AND id_user = $id_user_baru AND status = 'dipinjam'");
    }

    $koneksi->commit();
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&success=tracking_updated');
} catch (Exception $e) {
    $koneksi->rollback();
    header('Location: ../index.php?page=produk_info&id_produk=' . $id_produk . '&error=' . urlencode($e->getMessage()));
}
exit;
