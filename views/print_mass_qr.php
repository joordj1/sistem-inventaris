<?php
// Mass QR Label Print — grid of unit QR labels for selected products
// Called via: index.php?page=print_mass_qr&id_produk[]=1&id_produk[]=2

if (empty($_GET['id_produk'])) {
    echo '<div class="alert alert-warning">Tidak ada produk yang dipilih untuk dicetak QR.</div>';
    exit;
}

$produkIds = array_map('intval', (array)$_GET['id_produk']);
$produkIds = array_filter($produkIds, fn($id) => $id > 0);

if (empty($produkIds)) {
    echo '<div class="alert alert-warning">ID produk tidak valid.</div>';
    exit;
}

$unitCodeColumn = schema_find_existing_column($koneksi, 'unit_barang', ['kode_unit', 'serial_number']);
$qrColumn = get_asset_unit_qr_column($koneksi);

if ($unitCodeColumn === null) {
    echo '<div class="alert alert-danger">Kolom kode unit tidak ditemukan.</div>';
    exit;
}

$inClause = implode(',', $produkIds);
$qrColExpr = $qrColumn !== null ? "ub.`$qrColumn`" : "NULL";

$sql = "SELECT ub.id_unit_barang, ub.id_produk,
               ub.`$unitCodeColumn` AS kode_unit,
               ub.status,
               $qrColExpr AS stored_qr,
               p.nama_produk,
               p.kode_produk
        FROM unit_barang ub
        LEFT JOIN produk p ON ub.id_produk = p.id_produk
        WHERE ub.id_produk IN ($inClause)
        ORDER BY p.nama_produk ASC, ub.id_unit_barang ASC";

$result = $koneksi->query($sql);
$units = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $qrValue = build_asset_qr_value($row['id_unit_barang'], $row['id_produk'], $koneksi);
        $row['qr_value'] = $qrValue;
        $row['qr_image'] = ensure_asset_qr_file($row['id_unit_barang'], $qrValue) ?: get_asset_qr_relative_path($row['id_unit_barang']);
        $units[] = $row;
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Label Massal</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; background:#f5f5f5; }

        .screen-controls {
            background:#fff; border-bottom:1px solid #ddd; padding:14px 24px;
            display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:10;
        }
        .screen-controls h2 { font-size:1.1rem; font-weight:700; flex:1; }
        .btn-print { background:#2563eb; color:#fff; border:none; padding:9px 22px;
            border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; }
        .btn-print:hover { background:#1d4ed8; }
        .btn-close-win { background:#f1f5f9; color:#374151; border:1px solid #cbd5e1; padding:9px 18px;
            border-radius:8px; font-size:14px; cursor:pointer; }

        .grid-container { padding:24px; }
        .qr-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; }
        @media (min-width:900px) { .qr-grid { grid-template-columns: repeat(4, 1fr); } }

        .qr-label {
            background:#fff; border:1.5px solid #222; border-radius:8px;
            padding:12px 10px; text-align:center;
            display:flex; flex-direction:column; align-items:center; gap:6px;
            page-break-inside: avoid;
        }
        .qr-label img { width:120px; height:120px; }
        .qr-label .kode-unit { font-size:0.78rem; font-weight:800; font-family:monospace;
            background:#f1f5f9; padding:2px 8px; border-radius:4px; word-break:break-all; }
        .qr-label .nama-produk { font-size:0.72rem; color:#374151; font-weight:600; line-height:1.2; }
        .qr-label .kode-produk { font-size:0.65rem; color:#9ca3af; }
        .qr-label .status { font-size:0.65rem; color:#6b7280; margin-top:2px; }

        .empty-state { text-align:center; padding:48px; color:#6b7280; }

        @media print {
            .screen-controls { display:none !important; }
            body { background:#fff; }
            .grid-container { padding:0; }
            .qr-grid { grid-template-columns: repeat(3, 1fr); gap:8px; }
            .qr-label { border:1px solid #333; border-radius:4px; padding:8px 6px; }
            .qr-label img { width:100px; height:100px; }
        }
    </style>
</head>
<body>

<div class="screen-controls">
    <h2>
        <span style="color:#2563eb">&#128260;</span>
        Print QR Label Massal &mdash; <?= count($units) ?> unit
    </h2>
    <button class="btn-close-win" onclick="window.close()">Tutup</button>
    <button class="btn-print" onclick="window.print()">&#128438; Print / Save PDF</button>
</div>

<div class="grid-container">
<?php if (empty($units)): ?>
    <div class="empty-state">
        <p style="font-size:2rem">&#128683;</p>
        <p>Tidak ada unit asset ditemukan untuk produk yang dipilih.</p>
        <p style="font-size:0.85rem;margin-top:8px">Pastikan produk berjenis <strong>asset</strong> dan sudah memiliki unit terdaftar.</p>
    </div>
<?php else: ?>
    <div class="qr-grid">
    <?php foreach ($units as $unit): ?>
        <div class="qr-label">
              <?php if (!empty($unit['qr_value'])): ?>
              <img src="<?= htmlspecialchars($unit['qr_image']) ?>"
                 alt="QR <?= htmlspecialchars($unit['kode_unit'] ?? $unit['id_unit_barang']) ?>"
                  onerror="this.onerror=null;this.src='assets/qr/qr_fallback.svg';"
                 loading="lazy">
            <?php else: ?>
            <div style="width:120px;height:120px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;border-radius:4px;color:#9ca3af;font-size:2rem">&#128683;</div>
            <?php endif; ?>
            <div class="kode-unit"><?= htmlspecialchars($unit['kode_unit'] ?? 'ID:' . $unit['id_unit_barang']) ?></div>
            <div class="nama-produk"><?= htmlspecialchars($unit['nama_produk'] ?? '-') ?></div>
            <div class="kode-produk"><?= htmlspecialchars($unit['kode_produk'] ?? '') ?></div>
            <div class="status"><?= htmlspecialchars(ucfirst($unit['status'] ?? '-')) ?></div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

</body>
</html>
