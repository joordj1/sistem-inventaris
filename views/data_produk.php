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

    $assetLokasi = normalizeProductLocationValue($assetLocationMap[(int)($row['id_produk'] ?? 0)] ?? null);
    if ($assetLokasi !== null) {
        return $assetLokasi;
    }

    return 'Tidak Memiliki Lokasi';
}

$assetLocationMap = getAssetLocationMap($koneksi);
$canManageInventory = in_array($_SESSION['role'] ?? '', ['admin', 'petugas'], true);
$flash = consume_flash_message();
?>

<!-- Data Produk -->
<style>
    .product-name { font-weight: 700; color: #1f2937; }
    .product-meta { font-size: 0.78rem; color: #6b7280; }
    .badge-type { font-size: 0.73rem; vertical-align: middle; }
    .summary-chip { display: inline-block; margin: 2px 3px; padding: 2px 8px; font-size: 0.72rem; border-radius: 999px; background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0; }
    .action-icon { margin: 0 5px; color: #6b7280; transition: color .18s; font-size: 1.1rem; }
    .action-icon:hover { color: #2563eb; }
    .tbl-sortable thead th[data-sort] { cursor: pointer; user-select: none; white-space: nowrap; }
    .tbl-sortable thead th[data-sort]:hover { background: #dde3ee; }
    .tbl-sortable thead th[data-sort]::after { content: ' \2195'; opacity: 0.35; font-size: 0.7rem; }
    .tbl-sortable thead th[data-sort].sort-asc::after { content: ' \2191'; opacity: 1; }
    .tbl-sortable thead th[data-sort].sort-desc::after { content: ' \2193'; opacity: 1; }
    .tbl-produk tbody tr:nth-child(odd) { background: #f8fafc; }
    .tbl-produk tbody tr:hover { background: #eef2ff !important; }
    .filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; box-shadow: 0 1px 4px rgba(10,10,40,.06); }
    .filter-bar select, .filter-bar input[type=text] { height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 0 12px; font-size: 14px; background: #f8fafc; }
    .filter-bar select:focus, .filter-bar input[type=text]:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
    .filter-bar .btn-search { height: 38px; padding: 0 18px; border-radius: 8px; font-size: 14px; font-weight: 600; }
    @media print {
        .no-print { display: none !important; }
        body.printing-qr * { visibility: hidden; }
        body.printing-qr #qr-print-area, body.printing-qr #qr-print-area * { visibility: visible; }
        body.printing-qr #qr-print-area { position: absolute; top: 0; left: 0; width: 100%; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Data Produk</h4>
    <div class="d-flex gap-2">
        <?php if ($canManageInventory): ?>
        <a href="index.php?page=tambah_produk" class="btn btn-primary btn-sm no-print"><i class="bi bi-plus-lg me-1"></i>Tambah Produk</a>
        <?php endif; ?>
        <button id="btn-print-qr" class="btn btn-outline-secondary btn-sm no-print" onclick="printSelectedQR()" style="display:none"><i class="bi bi-qr-code me-1"></i>Print QR Label</button>
    </div>
</div>

<?php if ($flash && !empty($flash['message'])): ?>
    <?php $flashClass = 'alert-info'; ?>
    <?php if ($flash['type'] === 'success') $flashClass = 'alert-success'; ?>
    <?php if ($flash['type'] === 'error') $flashClass = 'alert-danger'; ?>
    <?php if ($flash['type'] === 'warning') $flashClass = 'alert-warning'; ?>
    <div class="alert <?= htmlspecialchars($flashClass) ?> no-print"><?= htmlspecialchars((string) $flash['message']) ?></div>
<?php endif; ?>

<!-- Filter Bar -->
<?php
$sql_kategori = "SELECT * FROM kategori ORDER BY nama_kategori ASC";
$result_kategori = $koneksi->query($sql_kategori);
$selected_kategori = isset($_GET['kategori']) ? intval($_GET['kategori']) : '';
?>
<form action="index.php" method="get" class="filter-bar mb-3 no-print">
    <input type="hidden" name="page" value="data_produk">
    <select name="kategori" style="min-width:150px">
        <option value="">Semua Kategori</option>
        <?php if ($result_kategori && $result_kategori->num_rows > 0): while ($row_kategori = $result_kategori->fetch_assoc()): ?>
            <option value="<?= $row_kategori['id_kategori'] ?>" <?= ((int)$row_kategori['id_kategori'] === (int)$selected_kategori) ? 'selected' : '' ?>><?= htmlspecialchars($row_kategori['nama_kategori']) ?></option>
        <?php endwhile; endif; ?>
    </select>
    <input type="text" name="search" placeholder="Cari kode, nama, harga satuan, total harga..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="min-width:220px;flex:1">
    <button type="submit" class="btn btn-primary btn-search"><i class="bi bi-search"></i></button>
    <?php if (!empty($_GET['search']) || !empty($_GET['kategori'])): ?>
    <a href="index.php?page=data_produk" class="btn btn-outline-secondary btn-search">Reset</a>
    <?php endif; ?>
</form>

<div id="qr-print-area"></div>
<div class="table-container overflowy">
    <table class="table table-bordered tbl-sortable tbl-produk" id="tbl-produk">
        <thead class="text-center" style="background:#f1f5f9">
            <tr>
                <th style="width:36px"><input type="checkbox" id="chk-all" title="Pilih semua asset" onchange="toggleAllQR(this)"></th>
                <th style="width:42px">No</th>
                <th data-sort="text">Kode</th>
                <th data-sort="text">Nama Barang</th>
                <th>Tipe</th>
                <th>Kategori</th>
                <th>Lokasi</th>
                <th>Ringkasan</th>
                <th data-sort="num" class="text-end">Harga</th>
                <th data-sort="num" class="text-end">Total</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
<?php 
    // Membuat query dasar untuk mendapatkan data produk dengan informasi gudang
    $query = "SELECT produk.id_produk, produk.kode_produk, produk.nama_produk, produk.tipe_barang, kategori.nama_kategori, 
                             COALESCE(NULLIF(produk.harga_default, 0), produk.harga_satuan, 0) AS harga_default_view,
                             produk.jumlah_stok, produk.satuan,
                             (COALESCE(NULLIF(produk.harga_default, 0), produk.harga_satuan, 0) * produk.jumlah_stok) AS total_nilai_view, 
                             produk.gambar_produk, produk.status, produk.kondisi, produk.lokasi_custom AS produk_lokasi_custom, produk.tersedia, 
                             gudang_master.nama_gudang AS nama_gudang_master,
                             COUNT(ub.id_unit_barang) AS total_unit,
                             SUM(CASE WHEN LOWER(TRIM(COALESCE(ub.status, ''))) = 'tersedia' THEN 1 ELSE 0 END) AS tersedia_unit,
                             SUM(CASE WHEN LOWER(TRIM(COALESCE(ub.status, ''))) IN ('dipinjam','digunakan','sedang digunakan') THEN 1 ELSE 0 END) AS dipakai_unit,
                             SUM(CASE WHEN LOWER(TRIM(COALESCE(ub.kondisi, ''))) IN ('rusak') THEN 1 ELSE 0 END) AS rusak_unit
                      FROM produk
                      LEFT JOIN kategori ON produk.id_kategori = kategori.id_kategori
                      LEFT JOIN gudang AS gudang_master ON produk.id_gudang = gudang_master.id_gudang
                      LEFT JOIN unit_barang ub ON ub.id_produk = produk.id_produk AND ub.deleted_at IS NULL";

            if ($selected_kategori) {
                $query .= " WHERE produk.id_kategori = '$selected_kategori'";
            }
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $koneksi->real_escape_string($_GET['search']);
                $query .= $selected_kategori ? " AND" : " WHERE";
                $query .= " (produk.kode_produk LIKE '%$search%'
                            OR produk.nama_produk LIKE '%$search%'
                            OR CAST(produk.harga_satuan AS CHAR) LIKE '%$search%'
                            OR CAST((produk.jumlah_stok * produk.harga_satuan) AS CHAR) LIKE '%$search%')";
            }
            $query .= " GROUP BY produk.id_produk, produk.kode_produk, produk.nama_produk, produk.tipe_barang, kategori.nama_kategori,
                                    produk.harga_default, produk.harga_satuan, produk.jumlah_stok, produk.satuan,
                                    produk.gambar_produk, produk.status, produk.kondisi, produk.lokasi_custom, produk.tersedia,
                                    gudang_master.nama_gudang";
            $query .= " ORDER BY produk.kode_produk ASC";
            $result = $koneksi->query($query);
            $nomor = 1;
        ?>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php $isAsset = ($row['tipe_barang'] ?? 'consumable') === 'asset'; ?>
                <tr>
                    <td class="text-center">
                        <input type="checkbox"
                            class="chk-qr check_item"
                            id="chk-qr-<?= $row['id_produk'] ?>"
                            data-id="<?= $row['id_produk'] ?>"
                            data-name="<?= htmlspecialchars($row['nama_produk']) ?>"
                            data-kode="<?= htmlspecialchars($row['kode_produk']) ?>"
                            onchange="updateQRBtn()"
                            <?= !$isAsset ? 'disabled title="Hanya produk asset yang dapat dicetak QR"' : '' ?>
                        >
                    </td>
                    <td class="text-center"><?= $nomor++ ?></td>
                    <td><?= htmlspecialchars($row['kode_produk']) ?></td>
                    <td>
                        <div class="product-name"><?= htmlspecialchars($row['nama_produk']) ?></div>
                        <div class="product-meta"><?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa kategori') ?></div>
                    </td>
                    <td class="text-center"><span class="badge badge-type <?= $isAsset ? 'bg-primary' : 'bg-success' ?>"><?= $isAsset ? 'Asset' : 'Consumable' ?></span></td>
                    <td><?= htmlspecialchars($row['nama_kategori'] ? $row['nama_kategori'] : '-') ?></td>
                    <td style="max-width:160px;font-size:0.82rem"><?= htmlspecialchars(resolveProductLocation($row, $assetLocationMap)) ?></td>
                    <td>
                        <?php if (($row['tipe_barang'] ?? 'consumable') === 'asset'): 
                            $totalUnit = intval($row['total_unit'] ?? 0);
                            $tersediaUnit = intval($row['tersedia_unit'] ?? 0);
                            $dipakaiUnit = intval($row['dipakai_unit'] ?? 0);
                            $rusakUnit = intval($row['rusak_unit'] ?? 0);
                            $stokProduk = max(0, intval($row['jumlah_stok'] ?? 0));

                            if ($totalUnit === 0 && $stokProduk > 0) {
                                $totalUnit = $stokProduk;
                                $tersediaUnit = $stokProduk;
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
                    <td class="text-end" data-val="<?= $row['harga_default_view'] ?>"><?= formatRupiah($row['harga_default_view']) ?></td>
                    <td class="text-end" data-val="<?= $row['total_nilai_view'] ?>"><?= formatRupiah($row['total_nilai_view']) ?></td>
                    <td class="text-center no-print">
                        <a href="index.php?page=produk_info&id_produk=<?= $row['id_produk'] ?>" class="action-icon" title="Lihat Detail"><i class="bi-eye"></i></a>
                        <?php if ($canManageInventory): ?>
                        <a href="index.php?page=edit_produk&id_produk=<?= $row['id_produk'] ?>" class="action-icon" title="Edit"><i class="bi-pencil"></i></a>
                        <a href="javascript:void(0);" onclick="confirmDeleteProduk(<?= $row['id_produk'] ?>)" class="action-icon text-danger" title="Hapus"><i class="bi-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="11" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-4 d-block mb-1"></i>Tidak ada data produk yang ditemukan.</td>
            </tr>
        <?php endif; ?>

        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 no-print">
    <a href="index.php?page=dashboard" class="btn btn-secondary">Kembali</a>
</div>

<script>
// ---- Table Sorting ----
(function() {
    const table = document.getElementById('tbl-produk');
    if (!table) return;
    const headers = table.querySelectorAll('thead th[data-sort]');
    headers.forEach(function(th, idx) {
        let sortDir = 0;
        th.addEventListener('click', function() {
            sortDir = (sortDir + 1) % 3; // 0=off,1=asc,2=desc
            headers.forEach(h => h.classList.remove('sort-asc','sort-desc'));
            if (sortDir === 1) th.classList.add('sort-asc');
            else if (sortDir === 2) th.classList.add('sort-desc');
            else { return; }
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const col = Array.from(th.parentElement.children).indexOf(th) + 1;
            const type = th.getAttribute('data-sort');
            rows.sort(function(a, b) {
                const cellA = a.querySelector('td:nth-child(' + col + ')');
                const cellB = b.querySelector('td:nth-child(' + col + ')');
                if (!cellA || !cellB) return 0;
                let va = cellA.getAttribute('data-val') || cellA.textContent.trim();
                let vb = cellB.getAttribute('data-val') || cellB.textContent.trim();
                if (type === 'num') { va = parseFloat(va.replace(/[^0-9.-]/g,'')) || 0; vb = parseFloat(vb.replace(/[^0-9.-]/g,'')) || 0; }
                if (va < vb) return sortDir === 1 ? -1 : 1;
                if (va > vb) return sortDir === 1 ? 1 : -1;
                return 0;
            });
            rows.forEach(r => tbody.appendChild(r));
        });
    });
})();

// ---- Mass QR Print ----
function toggleAllQR(master) {
    document.querySelectorAll('.check_item:not(:disabled)').forEach(function(chk) {
        chk.checked = master.checked;
    });
    updateQRBtn();
}
function updateQRBtn() {
    const anyChecked = document.querySelectorAll('.chk-qr:checked').length > 0;
    const btn = document.getElementById('btn-print-qr');
    if (btn) btn.style.display = anyChecked ? '' : 'none';
}
function printSelectedQR() {
    const checked = Array.from(document.querySelectorAll('.chk-qr:checked'));
    if (!checked.length) return;
    const ids = checked.map(c => 'id_produk[]=' + encodeURIComponent(c.getAttribute('data-id'))).join('&');
    window.open('index.php?page=print_mass_qr&' + ids, '_blank');
}
</script>
