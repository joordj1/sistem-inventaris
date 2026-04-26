<?php
/**
 * Migrasi data: pisahkan activity_type lama ('pinjam'/'dipinjam') menjadi:
 *   - 'serah_terima'  → record yang mengandung kata kunci BAST / serah terima resmi
 *   - 'assign'        → record assign cepat lainnya (tidak ada dokumen formal)
 *
 * Jalankan sekali saja secara manual.
 * Akses: ?execute=1 untuk eksekusi nyata, tanpa parameter = dry-run.
 */

require_once __DIR__ . '/../koneksi/koneksi.php';

if (!isset($koneksi) || $koneksi->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Koneksi database gagal.']);
    exit;
}

// Hanya admin
$allowedRoles = ['admin'];
if (!in_array(get_current_user_role(), $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

if (!schema_table_exists_now($koneksi, 'tracking_barang')) {
    echo json_encode(['error' => 'Tabel tracking_barang tidak ditemukan.']);
    exit;
}

// Dry-run jika tidak ada param ?execute=1
$isDryRun = !(isset($_GET['execute']) && $_GET['execute'] === '1');

// ─── Kandidat: pinjam/dipinjam dengan kata kunci BAST → serah_terima ──────────
$countBastSql = "SELECT COUNT(*) AS total FROM tracking_barang
                 WHERE activity_type IN ('pinjam', 'dipinjam')
                   AND (catatan LIKE '%serah terima%'
                        OR catatan LIKE '%BAST%'
                        OR catatan LIKE '%Serah terima resmi%')";
$countBastResult = $koneksi->query($countBastSql);
$totalBast = 0;
if ($countBastResult) {
    $row = $countBastResult->fetch_assoc();
    $totalBast = (int) ($row['total'] ?? 0);
}

// ─── Kandidat: pinjam/dipinjam tanpa kata kunci BAST → assign ─────────────────
$countAssignSql = "SELECT COUNT(*) AS total FROM tracking_barang
                   WHERE activity_type IN ('pinjam', 'dipinjam')
                     AND catatan NOT LIKE '%serah terima%'
                     AND catatan NOT LIKE '%BAST%'
                     AND catatan NOT LIKE '%Serah terima resmi%'";
$countAssignResult = $koneksi->query($countAssignSql);
$totalAssign = 0;
if ($countAssignResult) {
    $row = $countAssignResult->fetch_assoc();
    $totalAssign = (int) ($row['total'] ?? 0);
}

if ($isDryRun) {
    header('Content-Type: application/json');
    echo json_encode([
        'dry_run' => true,
        'kandidat_serah_terima' => $totalBast,
        'kandidat_assign' => $totalAssign,
        'pesan' => 'Ini adalah simulasi. Tambahkan ?execute=1 untuk menjalankan migrasi secara nyata.',
    ]);
    exit;
}

$errors = [];
$resultBast = 0;
$resultAssign = 0;

// ─── Update ke serah_terima ───────────────────────────────────────────────────
$updateBastSql = "UPDATE tracking_barang
                  SET activity_type = 'serah_terima'
                  WHERE activity_type IN ('pinjam', 'dipinjam')
                    AND (catatan LIKE '%serah terima%'
                         OR catatan LIKE '%BAST%'
                         OR catatan LIKE '%Serah terima resmi%')";
if ($koneksi->query($updateBastSql)) {
    $resultBast = $koneksi->affected_rows;
} else {
    $errors[] = 'Update serah_terima gagal: ' . $koneksi->error;
}

// ─── Update ke assign ─────────────────────────────────────────────────────────
$updateAssignSql = "UPDATE tracking_barang
                    SET activity_type = 'assign'
                    WHERE activity_type IN ('pinjam', 'dipinjam')
                      AND catatan NOT LIKE '%serah terima%'
                      AND catatan NOT LIKE '%BAST%'
                      AND catatan NOT LIKE '%Serah terima resmi%'";
if ($koneksi->query($updateAssignSql)) {
    $resultAssign = $koneksi->affected_rows;
} else {
    $errors[] = 'Update assign gagal: ' . $koneksi->error;
}

header('Content-Type: application/json');
if (!empty($errors)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => $errors]);
} else {
    echo json_encode([
        'success' => true,
        'diupdate_serah_terima' => $resultBast,
        'diupdate_assign' => $resultAssign,
        'pesan' => 'Migrasi selesai. '
            . $resultBast . ' record diubah ke serah_terima, '
            . $resultAssign . ' record diubah ke assign.',
    ]);
}

