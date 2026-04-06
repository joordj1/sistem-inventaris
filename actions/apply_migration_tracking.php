<?php
// Jalankan file ini sekali untuk menerapkan migrasi jika belum berada di skema.
include __DIR__ . '/../koneksi/koneksi.php';

$queries = file_get_contents(__DIR__ . '/../database/migration_tracking.sql');
if (!$queries) {
    die('Gagal membaca file migrasi.');
}

$sqls = explode(';', $queries);
foreach ($sqls as $sql) {
    $sql = trim($sql);
    if ($sql === '' || stripos($sql, '--') === 0) continue;
    $koneksi->query($sql);
    if ($koneksi->error) {
        echo 'Error: ' . $koneksi->error . "<br>SQL: $sql<br>";
        break;
    }
}

echo 'Migrasi tracking selesai. Pastikan cek tabel produk, tracking_barang, peminjaman.';
