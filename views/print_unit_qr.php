<?php
if (!isset($_GET['id_unit_barang'])) {
    echo '<div class="alert alert-warning">ID unit_barang tidak disediakan.</div>';
    exit;
}

$id_unit_barang = intval($_GET['id_unit_barang']);
$query = "SELECT ub.*, p.nama_produk AS nama_produk_master, p.kode_produk AS kode_produk_master FROM unit_barang ub LEFT JOIN produk p ON ub.id_produk = p.id_produk WHERE ub.id_unit_barang = ?";
$stmt = $koneksi->prepare($query);
$stmt->bind_param('i', $id_unit_barang);
$stmt->execute();
$result = $stmt->get_result();
$unit = $result->fetch_assoc();

if (!$unit) {
    echo '<div class="alert alert-danger">Unit barang tidak ditemukan.</div>';
    exit;
}

$qrValue = $unit['kode_qrcode'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Label - Unit <?= htmlspecialchars($unit['id_unit_barang']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .label-box { width: 320px; border: 1px solid #333; padding: 20px; margin: 0 auto; text-align: center; }
        .label-box h3 { margin: 5px 0; }
        .label-box p { margin: 4px 0; }
        .label-box img { margin: 10px 0; }
    </style>
</head>
<body>
    <div class="label-box">
        <h3>QR Label Unit Asset</h3>
        <p><strong>Unit ID:</strong> <?= htmlspecialchars($unit['id_unit_barang']) ?></p>
        <p><strong>Produk:</strong> <?= htmlspecialchars($unit['nama_produk_master'] ?? '-') ?></p>
        <p><strong>Serial:</strong> <?= htmlspecialchars($unit['serial_number'] ?? '-') ?></p>
        <?php if (!empty($qrValue)): ?>
            <img src="https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=<?= urlencode($qrValue) ?>&choe=UTF-8" alt="QR Code" />
            <p><small><?= htmlspecialchars($qrValue) ?></small></p>
        <?php else: ?>
            <p>QR belum tersedia.</p>
        <?php endif; ?>
        <button onclick="window.print();" style="margin-top:10px;padding:8px 16px;">Print</button>
    </div>
</body>
</html>
