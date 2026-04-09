<?php
// Fungsi untuk memformat angka ke dalam format Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

function normalizeProductLocationValue($value) {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function tableExists($koneksi, $tableName) {
    $tableName = $koneksi->real_escape_string($tableName);
    $result = $koneksi->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

function getAssetLocationMap($koneksi) {
    $locations = [];

    if (!tableExists($koneksi, 'unit_barang')) {
        return $locations;
    }

    $hasLokasiTable = tableExists($koneksi, 'lokasi');
    $locationParts = ["NULLIF(TRIM(COALESCE(ub.lokasi_custom, '')), '')"];

    if ($hasLokasiTable) {
        $locationParts[] = "NULLIF(TRIM(COALESCE(l.nama_lokasi, '')), '')";
    }

    $locationParts[] = "NULLIF(TRIM(COALESCE(g.nama_gudang, '')), '')";
    $locationExpr = "COALESCE(" . implode(', ', $locationParts) . ")";

    $query = "SELECT ub.id_produk,
                     GROUP_CONCAT(DISTINCT $locationExpr ORDER BY $locationExpr SEPARATOR ' | ') AS lokasi_asset_detail
              FROM unit_barang ub
              LEFT JOIN gudang g ON ub.id_gudang = g.id_gudang";

    if ($hasLokasiTable) {
        $query .= " LEFT JOIN lokasi l ON ub.id_lokasi = l.id_lokasi";
    }

    $query .= " WHERE $locationExpr IS NOT NULL
                GROUP BY ub.id_produk";

    $result = $koneksi->query($query);
    if (!$result) {
        return $locations;
    }

    while ($row = $result->fetch_assoc()) {
        $locations[(int) $row['id_produk']] = $row['lokasi_asset_detail'];
    }

    return $locations;
}

function resolveProductLocation($row, $assetLocationMap) {
    $masterGudang = normalizeProductLocationValue($row['nama_gudang_master'] ?? null);
    if ($masterGudang !== null) {
        return $masterGudang;
    }

    $stokGudang = normalizeProductLocationValue($row['nama_gudang_stok'] ?? null);
    if ($stokGudang !== null) {
        return $stokGudang;
    }

    return 'Tidak Memiliki Lokasi';
}

$assetLocationMap = getAssetLocationMap($koneksi);
?>

<!-- Kode HTML dan PHP Anda -->
<h2>Data Produk</h2>
<style>
    .product-name { font-weight: 700; color: #1f2937; }
    .product-meta { font-size: 0.9rem; color: #4b5563; }
    .badge-type { font-size: 0.75rem; vertical-align: middle; }
    .summary-chip { display: inline-block; margin: 2px 3px; padding: 3px 8px; font-size: 0.75rem; border-radius: 999px; background: #f3f4f6; color: #111827; }
    .action-icon { margin: 0 6px; color: #6b7280; transition: color .2s; }
    .action-icon:hover { color: #111827; }
    .table thead th { text-transform: uppercase; font-size: 0.78rem; letter-spacing: .04em; }
    .table td { vertical-align: middle; }
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
    <?php
    // Query untuk mengambil data kategori dari tabel kategori
    $sql_kategori = "SELECT * FROM kategori";
    $result_kategori = $koneksi->query($sql_kategori);

    // Mendapatkan filter kategori dari form
    $selected_kategori = isset($_POST['kategori']) ? $_POST['kategori'] : '';
    ?>

    <form action="index.php?page=data_produk" method="post">
        <label for="kategori">Pilih Kategori:</label>
        <select name="kategori" id="kategori" class="form-select w-auto mt-2" onchange="this.form.submit()">
            <option value="">Semua</option>
            <?php
            // Looping hasil query untuk membuat option pada select kategori
            if ($result_kategori->num_rows > 0) {
                while ($row_kategori = $result_kategori->fetch_assoc()) {
                    $selected = ($row_kategori['id_kategori'] == $selected_kategori) ? 'selected' : '';
                    echo '<option value="' . $row_kategori['id_kategori'] . '" ' . $selected . '>' . $row_kategori['nama_kategori'] . '</option>';
                }
            } else {
                echo '<option value="">Kategori tidak tersedia</option>';
            }
            ?>
        </select>
    </form>

    <form action="index.php" method="get" class="input-group" style="width: 200px;">
        <input type="hidden" name="page" value="data_produk">
        <input type="text" class="form-control" name="search" placeholder="Search by Kode" value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="table-container overflowy">
    <table class="table table-bordered table-success table-striped table-hover">
        <thead class="text-center">
            <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Tipe</th>
                <th>Kategori</th>
                <th>Gudang / Lokasi</th>
                <th>Ringkasan</th>
                <th>Harga</th>
                <th>Harga Total</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
<?php 
    $canManageInventory = inventory_user_can_manage();
    // Membuat query dasar untuk mendapatkan data produk dengan informasi gudang
    $query = "SELECT produk.id_produk, produk.kode_produk, produk.nama_produk, produk.tipe_barang, kategori.nama_kategori, 
                             COALESCE(NULLIF(produk.harga_default, 0), produk.harga_satuan, 0) AS harga_default_view,
                             produk.jumlah_stok, produk.satuan,
                             (COALESCE(NULLIF(produk.harga_default, 0), produk.harga_satuan, 0) * produk.jumlah_stok) AS total_nilai_view, 
                             produk.gambar_produk, produk.status, produk.kondisi, produk.lokasi_custom AS produk_lokasi_custom, produk.tersedia, 
                             gudang_stok.nama_gudang AS nama_gudang_stok,
                             gudang_master.nama_gudang AS nama_gudang_master
                      FROM produk
                      LEFT JOIN kategori ON produk.id_kategori = kategori.id_kategori
                      LEFT JOIN StokGudang ON produk.id_produk = StokGudang.id_produk
                      LEFT JOIN gudang AS gudang_stok ON StokGudang.id_gudang = gudang_stok.id_gudang
                      LEFT JOIN gudang AS gudang_master ON produk.id_gudang = gudang_master.id_gudang";

            if ($selected_kategori) {
                $query .= " WHERE produk.id_kategori = '$selected_kategori'";
            }
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $koneksi->real_escape_string($_GET['search']);
                $query .= $selected_kategori ? " AND" : " WHERE";
                $query .= " produk.kode_produk LIKE '%$search%'";
            }
            $result = $koneksi->query($query);
            $nomor = 1;
        ?>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?= $nomor++ ?></td>
                    <td><?= htmlspecialchars($row['kode_produk']) ?></td>
                    <td><div class="product-name"><?= htmlspecialchars($row['nama_produk']) ?></div>
                        <div class="product-meta">Kode: <?= htmlspecialchars($row['kode_produk']) ?></div></td>
                    <td><span class="badge badge-type <?= ($row['tipe_barang'] ?? 'consumable') === 'asset' ? 'bg-primary text-white' : 'bg-success text-white' ?>"><?= ucfirst($row['tipe_barang'] ?? 'consumable') ?></span></td>
                    <td><?= htmlspecialchars($row['nama_kategori'] ? $row['nama_kategori'] : 'Tidak ada') ?></td>
                    <td><?= htmlspecialchars(resolveProductLocation($row, $assetLocationMap)) ?></td>
                    <td>
                        <?php if (($row['tipe_barang'] ?? 'consumable') === 'asset'): 
                            $stats = $koneksi->query("SELECT COUNT(id_unit_barang) AS total_unit,
                                SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) = 'tersedia' THEN 1 ELSE 0 END) AS tersedia_unit,
                                SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('dipinjam','digunakan','sedang digunakan') THEN 1 ELSE 0 END) AS dipakai_unit,
                                SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('rusak','perbaikan','dalam perbaikan') THEN 1 ELSE 0 END) AS rusak_unit
                                FROM unit_barang
                                WHERE id_produk = " . intval($row['id_produk']))->fetch_assoc();
                            $totalUnit = intval($stats['total_unit'] ?? 0);
                            $tersediaUnit = intval($stats['tersedia_unit'] ?? 0);
                            $dipakaiUnit = intval($stats['dipakai_unit'] ?? 0);
                            $rusakUnit = intval($stats['rusak_unit'] ?? 0);
                            $stokProduk = max(0, intval($row['jumlah_stok'] ?? 0));

                            if ($totalUnit === 0 && $stokProduk > 0) {
                                $totalUnit = $stokProduk;
                                $dipakaiUnit = 0;
                                $rusakUnit = 0;
                                $tersediaUnit = $stokProduk;
                            } else {
                                $tersediaFallback = max($stokProduk - $dipakaiUnit - $rusakUnit, 0);
                                if ($stokProduk > 0 && $tersediaUnit === 0 && $dipakaiUnit === 0 && $rusakUnit === 0) {
                                    $tersediaUnit = $tersediaFallback;
                                }
                            }
                        ?>
                            <span class="summary-chip">Unit: <?= $totalUnit ?></span>
                            <span class="summary-chip">Tersedia: <?= $tersediaUnit ?></span>
                            <span class="summary-chip">Dipakai: <?= $dipakaiUnit ?></span>
                            <span class="summary-chip">Rusak: <?= $rusakUnit ?></span>
                        <?php else: ?>
                            <span class="summary-chip">Stok: <?= intval($row['jumlah_stok']) ?> <?= htmlspecialchars($row['satuan']) ?></span>
                            <span class="summary-chip"><?= ($row['tersedia'] ? 'Tersedia' : 'Tidak tersedia') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= formatRupiah($row['harga_default_view']) ?></td>
                    <td class="text-end"><?= formatRupiah($row['total_nilai_view']) ?></td>
                    <td class="text-center">
                        <a href="index.php?page=produk_info&id_produk=<?= $row['id_produk'] ?>" class="action-icon" title="Lihat"><i class="bi-eye fs-4"></i></a>
                        <?php if ($canManageInventory): ?>
                        <a href="index.php?page=edit_produk&id_produk=<?= $row['id_produk'] ?>" class="action-icon" title="Edit"><i class="bi-pencil fs-4"></i></a>
                        <a href="javascript:void(0);" onclick="confirmDeleteProduk(<?= $row['id_produk'] ?>)" class="action-icon text-danger" title="Hapus"><i class="bi-trash fs-4"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="10" class="text-center">Tidak ada data produk yang ditemukan.</td>
            </tr>
        <?php endif; ?>

        </tbody>
    </table>
</div>

<?php if ($canManageInventory): ?>
<a href="index.php?page=tambah_produk"><button class="btn btn-primary float-start mt-3">+ Tambah Produk Baru</button></a>
<?php endif; ?>
<a href="index.php?page=dashboard"><button class="btn btn-secondary float-end mt-3">Tutup</button></a>
<div class="clearfix"></div>
