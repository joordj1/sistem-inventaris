<?php
/**
 * Migrasi: Tambah nilai 'habis' ke ENUM status, dan perbaiki kolom ENUM di tracking_barang
 *
 * Cakupan:
 *  - produk.status            → tambah 'habis' ke ENUM
 *  - tracking_barang.status_sebelum → konversi ke VARCHAR(30)
 *  - tracking_barang.status_sesudah → konversi ke VARCHAR(30)
 *  - tracking_barang.activity_type  → konversi ke VARCHAR(50)
 *
 * Aman dijalankan berulang kali (idempotent).
 */

include __DIR__ . '/../koneksi/koneksi.php';

require_auth_roles(['admin'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

$messages = [];
$errors   = [];

// ============================================================
// [1] produk.status
// ============================================================
$checkSql = "SELECT COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'produk'
               AND COLUMN_NAME  = 'status'
             LIMIT 1";

$checkResult = $koneksi->query($checkSql);
$colInfo     = $checkResult ? $checkResult->fetch_assoc() : null;

if (!$colInfo) {
    $errors[] = "Tidak dapat membaca definisi kolom <strong>produk.status</strong>: " . htmlspecialchars($koneksi->error, ENT_QUOTES, 'UTF-8');
} elseif (stripos($colInfo['COLUMN_TYPE'], "'habis'") !== false) {
    $messages[] = "Tidak perlu diubah: kolom <strong>produk.status</strong> sudah mengandung nilai 'habis'. Tipe saat ini: " . htmlspecialchars($colInfo['COLUMN_TYPE'], ENT_QUOTES, 'UTF-8');
} else {
    $isEnum = stripos($colInfo['COLUMN_TYPE'], 'enum(') === 0;
    if ($isEnum) {
        $alterSql = "ALTER TABLE produk MODIFY COLUMN status
                     ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif','habis')
                     DEFAULT 'tersedia'";
    } else {
        $alterSql = "ALTER TABLE produk MODIFY COLUMN status VARCHAR(30) DEFAULT 'tersedia'";
    }

    if ($koneksi->query($alterSql)) {
        $messages[] = "✔ <strong>produk.status</strong> diperbarui — nilai 'habis' ditambahkan.";
        $confirmRes = $koneksi->query($checkSql);
        $newInfo = $confirmRes ? $confirmRes->fetch_assoc() : null;
        if ($newInfo) {
            $messages[] = "&nbsp;&nbsp;Tipe sekarang: " . htmlspecialchars($newInfo['COLUMN_TYPE'], ENT_QUOTES, 'UTF-8');
        }
    } else {
        $errors[] = "✘ Gagal ALTER produk.status: " . htmlspecialchars($koneksi->error, ENT_QUOTES, 'UTF-8');
    }
}

// ============================================================
// [2] tracking_barang — konversi kolom ENUM ke VARCHAR
// ============================================================
$tblCheck = $koneksi->query("SHOW TABLES LIKE 'tracking_barang'");
if (!$tblCheck || $tblCheck->num_rows === 0) {
    $messages[] = "Info: tabel <strong>tracking_barang</strong> belum ada — migrasi kolom tracking dilewati.";
} else {
    $trackingFixes = [
        'status_sebelum' => ['type' => 'VARCHAR(30)', 'default' => 'NULL'],
        'status_sesudah'  => ['type' => 'VARCHAR(30)', 'default' => 'NULL'],
        'activity_type'   => ['type' => 'VARCHAR(50)', 'default' => "'update'"],
    ];

    foreach ($trackingFixes as $col => $cfg) {
        $safeCol = $koneksi->real_escape_string($col);
        $colRes  = $koneksi->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'tracking_barang'
               AND COLUMN_NAME  = '$safeCol'
             LIMIT 1"
        );
        $colInfo = $colRes ? $colRes->fetch_assoc() : null;

        if (!$colInfo) {
            $messages[] = "Info: kolom <strong>tracking_barang.$col</strong> tidak ditemukan — dilewati.";
            continue;
        }

        $colType = strtolower((string) ($colInfo['COLUMN_TYPE'] ?? ''));

        if (strpos($colType, 'varchar') !== false) {
            $messages[] = "Tidak perlu diubah: <strong>tracking_barang.$col</strong> sudah VARCHAR. Tipe: " . htmlspecialchars($colInfo['COLUMN_TYPE'], ENT_QUOTES, 'UTF-8');
            continue;
        }

        $targetType = $cfg['type'];
        $default    = $cfg['default'];
        $alterSql   = "ALTER TABLE tracking_barang MODIFY COLUMN `$col` $targetType DEFAULT $default";

        if ($koneksi->query($alterSql)) {
            $messages[] = "✔ <strong>tracking_barang.$col</strong> dikonversi ke $targetType (dari: " . htmlspecialchars($colInfo['COLUMN_TYPE'], ENT_QUOTES, 'UTF-8') . ").";
        } else {
            $errors[] = "✘ Gagal ALTER tracking_barang.$col: " . htmlspecialchars($koneksi->error, ENT_QUOTES, 'UTF-8');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrasi: Status Habis & Tracking</title>
    <style>
        body { font-family: sans-serif; padding: 24px; max-width: 760px; }
        h2   { margin-bottom: 16px; }
        .ok  { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px 14px; border-radius: 6px; margin: 6px 0; }
        .err { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px 14px; border-radius: 6px; margin: 6px 0; }
        a    { margin-top: 16px; display: inline-block; }
        hr   { margin: 20px 0; border: none; border-top: 1px solid #dee2e6; }
    </style>
</head>
<body>
<h2>Migrasi: Status "habis" &amp; Tracking Schema</h2>
<p>Perbaiki ENUM <code>produk.status</code> dan konversi kolom ENUM di <code>tracking_barang</code> ke VARCHAR.</p>
<hr>

<?php foreach ($messages as $msg): ?>
    <div class="ok"><?= $msg ?></div>
<?php endforeach; ?>

<?php foreach ($errors as $err): ?>
    <div class="err"><?= $err ?></div>
<?php endforeach; ?>

<?php if (empty($errors)): ?>
    <hr>
    <a href="../index.php?page=dashboard">← Kembali ke Dashboard</a>
<?php endif; ?>
</body>
</html>


require_auth_roles(['admin'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

$messages = [];
$errors   = [];

// Baca definisi kolom status dari information_schema
$checkSql = "SELECT COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'produk'
               AND COLUMN_NAME  = 'status'";

$checkResult = $koneksi->query($checkSql);
$colInfo     = $checkResult ? $checkResult->fetch_assoc() : null;

if (!$colInfo) {
    $errors[] = "Tidak dapat membaca definisi kolom produk.status: " . $koneksi->error;
} elseif (stripos($colInfo['COLUMN_TYPE'], "'habis'") !== false) {
    $messages[] = "Tidak perlu diubah: kolom produk.status sudah mengandung nilai 'habis'.";
    $messages[] = "Tipe saat ini: " . $colInfo['COLUMN_TYPE'];
} else {
    // Cek apakah kolom ini ENUM (VARCHAR juga didukung — langsung ALTER jika VARCHAR)
    $isEnum = stripos($colInfo['COLUMN_TYPE'], 'enum(') === 0;

    if ($isEnum) {
        // Tambah 'habis' ke daftar ENUM yang sudah ada, pertahankan semua nilai lama
        $alterSql = "ALTER TABLE produk MODIFY COLUMN status
                     ENUM('tersedia','dipinjam','sedang digunakan','dipindahkan','dalam perbaikan','rusak','tidak aktif','habis')
                     DEFAULT 'tersedia'";
    } else {
        // VARCHAR: perlebar kolom agar cukup menampung semua nilai
        $alterSql = "ALTER TABLE produk MODIFY COLUMN status VARCHAR(30) DEFAULT 'tersedia'";
    }

    if ($koneksi->query($alterSql)) {
        $messages[] = "Berhasil: kolom <strong>produk.status</strong> diperbarui — nilai 'habis' ditambahkan.";
        $messages[] = "Tipe sebelumnya: " . htmlspecialchars($colInfo['COLUMN_TYPE'], ENT_QUOTES, 'UTF-8');

        // Konfirmasi tipe baru
        $confirmResult = $koneksi->query($checkSql);
        $newInfo = $confirmResult ? $confirmResult->fetch_assoc() : null;
        if ($newInfo) {
            $messages[] = "Tipe sekarang: " . htmlspecialchars($newInfo['COLUMN_TYPE'], ENT_QUOTES, 'UTF-8');
        }
    } else {
        $errors[] = "Gagal menjalankan ALTER TABLE: " . $koneksi->error;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrasi: Status Habis</title>
    <style>
        body { font-family: sans-serif; padding: 24px; max-width: 680px; }
        h2   { margin-bottom: 16px; }
        .ok  { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px 14px; border-radius: 6px; margin: 6px 0; }
        .err { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px 14px; border-radius: 6px; margin: 6px 0; }
        a    { margin-top: 16px; display: inline-block; }
    </style>
</head>
<body>
<h2>Migrasi: Tambah status "habis" ke produk</h2>

<?php foreach ($messages as $msg): ?>
    <div class="ok"><?= $msg ?></div>
<?php endforeach; ?>

<?php foreach ($errors as $err): ?>
    <div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<?php if (empty($errors)): ?>
    <a href="../index.php?page=dashboard">← Kembali ke Dashboard</a>
<?php endif; ?>
</body>
</html>
