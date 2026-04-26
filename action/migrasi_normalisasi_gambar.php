<?php
/**
 * Migrasi sekali-jalan: normalisasi kolom gambar_produk
 * Mengambil hanya nama file dari path yang mungkin disimpan sebagai full/relative path.
 *
 * Jalankan sekali oleh admin:
 *   http://localhost/sistem-inventaris/action/migrasi_normalisasi_gambar.php
 *
 * Setelah selesai, file ini AMAN untuk dihapus.
 */
include '../koneksi/koneksi.php';

require_auth_roles(['admin'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php',
]);

$dryRun = !isset($_GET['confirm']) || $_GET['confirm'] !== '1';
$results = [];

$stmt = $koneksi->query("SELECT id_produk, gambar_produk FROM produk WHERE gambar_produk IS NOT NULL AND gambar_produk != ''");
$updated = 0;
$skipped = 0;

while ($row = $stmt->fetch_assoc()) {
    $raw = trim((string) ($row['gambar_produk'] ?? ''));
    if ($raw === '') {
        $skipped++;
        continue;
    }

    $baseName = basename($raw);

    if ($baseName === $raw) {
        // Sudah berupa nama file saja — tidak perlu diubah
        $skipped++;
        continue;
    }

    $results[] = [
        'id' => $row['id_produk'],
        'before' => $raw,
        'after' => $baseName,
    ];

    if (!$dryRun) {
        $safeBaseName = $koneksi->real_escape_string($baseName);
        $koneksi->query("UPDATE produk SET gambar_produk = '$safeBaseName' WHERE id_produk = " . intval($row['id_produk']));
        $updated++;
    }
}

header('Content-Type: text/plain; charset=utf-8');
if ($dryRun) {
    echo "=== DRY RUN (tambahkan ?confirm=1 untuk menjalankan migrasi) ===\n\n";
} else {
    echo "=== MIGRASI DIJALANKAN ===\n\n";
}

echo "Total diproses: " . ($updated + $skipped + count($results)) . "\n";
echo "Dilewati (sudah bersih): $skipped\n";
echo "Akan/Sudah diupdate: " . count($results) . ($dryRun ? " (dry run)" : " (sudah dieksekusi)") . "\n\n";

foreach ($results as $r) {
    echo "ID {$r['id']}: '{$r['before']}' → '{$r['after']}'\n";
}

echo "\nSelesai.\n";
