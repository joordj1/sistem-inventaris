<?php
include 'koneksi/koneksi.php';

$unitIdRaw = trim((string) ($_GET['unit_id'] ?? ''));
if ($unitIdRaw === '' || !ctype_digit($unitIdRaw) || intval($unitIdRaw) < 1) {
    http_response_code(400);
    echo '<h3>Error: parameter unit_id wajib diisi dan valid.</h3>';
    exit;
}

$unitId = intval($unitIdRaw);

if (!schema_table_exists_now($koneksi, 'unit_barang')) {
    http_response_code(500);
    echo '<h3>Error: tabel unit_barang tidak tersedia.</h3>';
    exit;
}

$stmtUnit = $koneksi->prepare('SELECT id_unit_barang FROM unit_barang WHERE id_unit_barang = ? LIMIT 1');
if (!$stmtUnit) {
    http_response_code(500);
    echo '<h3>Error: gagal menyiapkan validasi unit.</h3>';
    exit;
}
$stmtUnit->bind_param('i', $unitId);
$stmtUnit->execute();
$resUnit = $stmtUnit->get_result();
if (!$resUnit || $resUnit->num_rows < 1) {
    http_response_code(404);
    echo '<h3>Error: unit_id tidak ditemukan.</h3>';
    exit;
}

$qrFolder = __DIR__ . '/assets/qr';
if (!is_dir($qrFolder) && !@mkdir($qrFolder, 0775, true) && !is_dir($qrFolder)) {
    http_response_code(500);
    echo '<h3>Error: gagal membuat folder assets/qr.</h3>';
    exit;
}

$qrFileName = 'qr_unit_' . $unitId . '.png';
$qrAbsolutePath = $qrFolder . '/' . $qrFileName;
$qrRelativePath = 'assets/qr/' . $qrFileName;
$qrText = build_asset_qr_value($unitId, null, $koneksi);

$phpQrCandidates = [
    __DIR__ . '/phpqrcode/qrlib.php',
    __DIR__ . '/lib/phpqrcode/qrlib.php',
    __DIR__ . '/vendor/phpqrcode/qrlib.php',
    __DIR__ . '/vendor/phpqrcode/phpqrcode/qrlib.php',
    __DIR__ . '/assets/phpqrcode/qrlib.php',
];

$phpQrPath = null;
foreach ($phpQrCandidates as $candidate) {
    if (is_file($candidate)) {
        $phpQrPath = $candidate;
        break;
    }
}

$generatorUsed = '';
$generationOk = false;
$errorMessage = '';

if ($phpQrPath !== null && function_exists('imagecreate')) {
    $cacheDir = dirname($phpQrPath) . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    require_once $phpQrPath;
    if (class_exists('QRcode')) {
        $prevReporting = error_reporting();
        error_reporting($prevReporting & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
        QRcode::png($qrText, $qrAbsolutePath, QR_ECLEVEL_M, 6, 2);
        error_reporting($prevReporting);
        $generationOk = is_file($qrAbsolutePath) && filesize($qrAbsolutePath) > 0;
        $generatorUsed = 'phpqrcode';
    } else {
        $errorMessage = 'Library phpqrcode ditemukan tetapi class QRcode tidak tersedia.';
    }
} elseif ($phpQrPath !== null && !function_exists('imagecreate')) {
    $errorMessage = 'phpqrcode tersedia, tetapi GD extension (imagecreate) tidak aktif.';
}

if (!$generationOk) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'InventarisManualQR/1.0',
        ],
    ]);
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&data=' . rawurlencode($qrText);
    $imageBytes = @file_get_contents($apiUrl, false, $ctx);
    if ($imageBytes !== false && strlen($imageBytes) > 100) {
        if (@file_put_contents($qrAbsolutePath, $imageBytes) !== false) {
            $generationOk = true;
            $generatorUsed = 'fallback-api';
        }
    }
}

if (!$generationOk) {
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&data=' . rawurlencode($qrText);
    $psScriptPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inventaris_qr_dl_' . $unitId . '.ps1';
    $psOutFile = str_replace('\\', '/', $qrAbsolutePath);
    $psContent = 'Invoke-WebRequest -Uri "' . $apiUrl . '" -OutFile "' . $psOutFile . '"';
    @file_put_contents($psScriptPath, $psContent);

    if (is_file($psScriptPath)) {
        $psCommand = 'powershell -NoProfile -ExecutionPolicy Bypass -File "' . str_replace('\\', '/', $psScriptPath) . '"';
        @shell_exec($psCommand);
        @unlink($psScriptPath);
    }

    if (is_file($qrAbsolutePath) && filesize($qrAbsolutePath) > 100) {
        $generationOk = true;
        $generatorUsed = 'fallback-powershell';
    }
}

if (!$generationOk) {
    http_response_code(500);
    $msg = $errorMessage !== '' ? $errorMessage : 'Gagal generate QR.';
    echo '<h3>Error: ' . htmlspecialchars($msg) . '</h3>';
    echo '<p>Pastikan phpqrcode tersedia pada salah satu path:</p><ul>';
    foreach ($phpQrCandidates as $candidate) {
        echo '<li>' . htmlspecialchars(str_replace(__DIR__ . '/', '', $candidate)) . '</li>';
    }
    echo '</ul>';
    exit;
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Manual</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 24px; }
        .box { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 3px 12px rgba(0,0,0,.06); }
        .title { margin: 0 0 14px; font-size: 20px; color: #111827; }
        .meta { margin: 8px 0; color: #334155; font-size: 14px; }
        .meta strong { color: #0f172a; }
        .img-wrap { margin: 16px auto; text-align: center; }
        .img-wrap img { width: 260px; max-width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; background: #fff; }
        .actions { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { text-decoration: none; padding: 9px 14px; border-radius: 8px; font-size: 14px; border: 1px solid #cbd5e1; color: #0f172a; background: #fff; }
        .btn.primary { background: #2563eb; color: #fff; border-color: #2563eb; }
    </style>
</head>
<body>
    <div class="box">
        <h1 class="title">Generate QR Manual Berhasil</h1>
        <div class="meta"><strong>Unit ID:</strong> <?= htmlspecialchars((string) $unitId) ?></div>
        <div class="meta"><strong>QR Value:</strong> <?= htmlspecialchars($qrText) ?></div>
        <div class="meta"><strong>Path File:</strong> <?= htmlspecialchars($qrRelativePath) ?></div>
        <div class="meta"><strong>Generator:</strong> <?= htmlspecialchars($generatorUsed) ?></div>

        <div class="img-wrap">
            <img src="<?= htmlspecialchars($qrRelativePath) ?>?t=<?= time() ?>" alt="QR Unit <?= htmlspecialchars((string) $unitId) ?>">
        </div>

        <div class="actions">
            <a class="btn primary" href="<?= htmlspecialchars($qrRelativePath) ?>" target="_blank">Buka File QR</a>
            <a class="btn" href="index.php?page=unit_barang_info&id_unit_barang=<?= urlencode((string) $unitId) ?>">Kembali ke Detail Unit</a>
        </div>
    </div>
</body>
</html>
