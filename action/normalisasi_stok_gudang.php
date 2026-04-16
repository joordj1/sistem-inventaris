<?php
/**
 * Script normalisasi stok gudang (sekali jalan).
 * Rebuild stokgudang berdasarkan data aktual unit_barang.
 *
 * Jalankan via browser atau CLI, kemudian hapus file ini setelah selesai.
 */
include __DIR__ . '/../koneksi/koneksi.php';

require_auth_roles(['admin'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
$results = [];

$koneksi->begin_transaction();
try {
    // 1. Ambil data stok lama untuk perbandingan
    $oldStok = [];
    $oldResult = $koneksi->query("SELECT id_gudang, id_produk, jumlah_stok FROM stokgudang ORDER BY id_gudang, id_produk");
    if ($oldResult) {
        while ($row = $oldResult->fetch_assoc()) {
            $key = $row['id_gudang'] . '-' . $row['id_produk'];
            $oldStok[$key] = intval($row['jumlah_stok']);
        }
    }

    // 2. Hitung stok aktual dari unit_barang (hanya unit asset yang punya gudang)
    $countResult = $koneksi->query(
        "SELECT id_gudang, id_produk, COUNT(*) AS jumlah
         FROM unit_barang
         WHERE id_gudang IS NOT NULL AND id_gudang > 0
         GROUP BY id_gudang, id_produk"
    );
    $newStok = [];
    if ($countResult) {
        while ($row = $countResult->fetch_assoc()) {
            $key = $row['id_gudang'] . '-' . $row['id_produk'];
            $newStok[$key] = intval($row['jumlah']);
        }
    }

    // 3. Hitung juga stok consumable yang sudah ada di stokgudang tapi TIDAK punya unit_barang
    //    (produk tipe consumable mungkin tidak punya unit, jadi pertahankan stoknya)
    $consumableResult = $koneksi->query(
        "SELECT sg.id_gudang, sg.id_produk, sg.jumlah_stok
         FROM stokgudang sg
         INNER JOIN produk p ON sg.id_produk = p.id_produk
         WHERE (p.tipe_barang = 'consumable' OR p.tipe_barang IS NULL)
           AND sg.jumlah_stok > 0"
    );
    $consumableStok = [];
    if ($consumableResult) {
        while ($row = $consumableResult->fetch_assoc()) {
            $key = $row['id_gudang'] . '-' . $row['id_produk'];
            $consumableStok[$key] = intval($row['jumlah_stok']);
        }
    }

    // 4. Gabungkan: asset dari unit_barang, consumable dari stokgudang existing
    $finalStok = $newStok;
    foreach ($consumableStok as $key => $qty) {
        if (!isset($finalStok[$key])) {
            $finalStok[$key] = $qty;
        }
        // Jika key sudah ada di $newStok (ada unit_barang), gunakan hitungan unit
    }

    if (!$dryRun) {
        // 5. Kosongkan stokgudang
        $koneksi->query("DELETE FROM stokgudang");

        // 6. Insert ulang dari data yang sudah dihitung
        $insertStmt = $koneksi->prepare(
            "INSERT INTO stokgudang (id_gudang, id_produk, jumlah_stok) VALUES (?, ?, ?)"
        );
        if (!$insertStmt) {
            throw new Exception('Gagal menyiapkan insert stokgudang: ' . $koneksi->error);
        }

        foreach ($finalStok as $key => $qty) {
            list($gudangId, $produkId) = explode('-', $key);
            $gudangId = intval($gudangId);
            $produkId = intval($produkId);
            $insertStmt->bind_param('iii', $gudangId, $produkId, $qty);
            if (!$insertStmt->execute()) {
                throw new Exception("Gagal insert stokgudang gudang=$gudangId produk=$produkId: " . $koneksi->error);
            }
        }
    }

    // 7. Hitung perubahan
    $allKeys = array_unique(array_merge(array_keys($oldStok), array_keys($finalStok)));
    sort($allKeys);
    foreach ($allKeys as $key) {
        $old = $oldStok[$key] ?? 0;
        $new = $finalStok[$key] ?? 0;
        if ($old !== $new) {
            list($gId, $pId) = explode('-', $key);
            $results[] = [
                'gudang_id' => $gId,
                'produk_id' => $pId,
                'stok_lama' => $old,
                'stok_baru' => $new,
                'selisih' => $new - $old,
            ];
        }
    }

    if ($dryRun) {
        $koneksi->rollback();
    } else {
        $koneksi->commit();
    }
} catch (Exception $e) {
    $koneksi->rollback();
    echo '<div style="color:red;font-weight:bold">ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Normalisasi Stok Gudang</title>
<style>
body { font-family: sans-serif; padding: 20px; max-width: 900px; margin: auto; }
table { border-collapse: collapse; width: 100%; margin-top: 16px; }
th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: center; font-size: 14px; }
th { background: #f1f5f9; }
.positive { color: green; font-weight: bold; }
.negative { color: red; font-weight: bold; }
.badge { padding: 4px 10px; border-radius: 4px; font-weight: bold; }
.badge-dry { background: #fef3c7; color: #92400e; }
.badge-done { background: #d1fae5; color: #065f46; }
</style>
</head>
<body>
<h2>Normalisasi Stok Gudang</h2>
<p>
    Mode: <span class="badge <?= $dryRun ? 'badge-dry' : 'badge-done' ?>"><?= $dryRun ? 'DRY RUN (tidak ada perubahan)' : 'EKSEKUSI SELESAI' ?></span>
</p>

<?php if (empty($results)): ?>
    <p style="color:green;font-weight:bold">Stok gudang sudah sinkron dengan data unit. Tidak ada perubahan diperlukan.</p>
<?php else: ?>
    <p>Ditemukan <strong><?= count($results) ?></strong> perbedaan stok:</p>
    <table>
        <thead><tr><th>Gudang ID</th><th>Produk ID</th><th>Stok Lama</th><th>Stok Baru</th><th>Selisih</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?= $r['gudang_id'] ?></td>
                <td><?= $r['produk_id'] ?></td>
                <td><?= $r['stok_lama'] ?></td>
                <td><?= $r['stok_baru'] ?></td>
                <td class="<?= $r['selisih'] > 0 ? 'positive' : 'negative' ?>"><?= ($r['selisih'] > 0 ? '+' : '') . $r['selisih'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($dryRun): ?>
    <p style="margin-top:20px">
        <a href="?dry_run=0" onclick="return confirm('Yakin ingin menjalankan normalisasi stok? Data stokgudang akan di-rebuild.')" style="padding:10px 20px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold">Jalankan Normalisasi</a>
    </p>
<?php else: ?>
    <p style="margin-top:20px;color:#065f46;font-weight:bold">Normalisasi selesai. Silakan hapus file ini (action/normalisasi_stok_gudang.php) setelah selesai.</p>
<?php endif; ?>
</body>
</html>
