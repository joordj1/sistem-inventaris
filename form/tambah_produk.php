<?php
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    include __DIR__ . '/../koneksi/koneksi.php';
}
require_auth_roles(['admin', 'petugas'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=data_produk',
]);

// Query untuk mengambil data kategori
$queryKategori = "SELECT * FROM kategori";
$resultKategori = $koneksi->query($queryKategori);

// Query untuk mengambil data gudang
$queryGudang = "SELECT * FROM gudang";
$resultGudang = $koneksi->query($queryGudang);
?>

<div class="form-container">
    <div class="form-header">
        <h5>Form Tambah Data Produk</h5>
    </div>
    <form action="action/simpan_produk.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="code" class="form-label">Code Produk</label>
            <input type="text" class="form-control" id="code" name="code" placeholder="Inputkan Code Produk" required>
        </div>
        <div class="mb-3">
            <label for="namaproduk" class="form-label">Nama Produk</label>
            <input type="text" class="form-control" id="namaproduk" name="namaproduk" placeholder="Inputkan Nama Produk" required>
        </div>
        <div class="mb-3">
            <label for="deskripsi" class="form-label">Deskripsi Barang</label>
            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" placeholder="Spesifikasi, ukuran, material, atau catatan identifikasi barang"></textarea>
        </div>
        <div class="mb-3">
            <label for="kategori" class="form-label">Kategori Produk</label>
            <select class="form-select" id="kategori" name="kategori" required>
                <option value="">--Pilih Kategori--</option>
                <?php while ($kategori = $resultKategori->fetch_assoc()): ?>
                    <option value="<?php echo $kategori['id_kategori']; ?>"><?php echo $kategori['nama_kategori']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="gudang" class="form-label">Pilih Gudang</label>
            <select class="form-select" id="gudang" name="gudang" required>
                <option value="">--Pilih Gudang--</option>
                <?php while ($gudang = $resultGudang->fetch_assoc()): ?>
                    <option value="<?php echo $gudang['id_gudang']; ?>"><?php echo $gudang['nama_gudang']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="stok" class="form-label">Stock Produk</label>
            <input type="number" class="form-control" id="stok" name="stok" placeholder="Inputkan Stok Produk" min="0" step="1" required>
            <small class="text-muted">Consumable: stok dipakai untuk barang masuk/keluar. Asset: stok mewakili jumlah unit awal asset.</small>
        </div>
        <div class="mb-3" id="unitCountContainer" style="display:none;">
            <label for="jumlah_unit" class="form-label">Jumlah Unit Awal (Otomatis dari Stok)</label>
            <input type="number" class="form-control" id="jumlah_unit" name="jumlah_unit" placeholder="Mengikuti stok asset" min="0" value="0" readonly>
            <small class="text-muted">Hanya informasi untuk asset. Sistem akan membuat unit awal sesuai nilai stok produk.</small>
        </div>
        <div class="mb-3">
            <label for="satuan" class="form-label">Satuan</label>
            <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Inputkan Satuan Produk" required>
        </div>
        <div class="mb-3">
            <label for="tipe_barang" class="form-label">Tipe Produk</label>
            <select name="tipe_barang" id="tipe_barang" class="form-select" required>
                <option value="consumable">Consumable (Stok In/Out)</option>
                <option value="asset">Asset (Unit Tracking)</option>
            </select>
            <small class="text-muted">Asset tidak diproses lewat barang masuk/keluar. Nilai stok akan dipakai sebagai jumlah unit awal.</small>
        </div>

        <input type="hidden" name="status" value="tersedia" />
        <input type="hidden" name="kondisi" value="baik" />

        <div class="mb-3">
            <label for="harga" class="form-label">Harga</label>
            <input type="text" class="form-control" id="harga" name="harga" placeholder="Inputkan Harga Produk (1 - 1000000000)" required inputmode="numeric" oninput="formatHargaInput(this)">
            <small class="form-text text-muted">Untuk ribuan, tidak perlu titik/koma; sistem akan mengubah otomatis. Contoh: 11000000 untuk 11 juta.</small>
        </div>
        <div class="mb-3">
            <label for="gambarproduk" class="form-label">Upload Gambar Produk</label>
            <input type="file" class="form-control" id="gambarproduk" name="gambarproduk">
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=data_produk" class="btn btn-secondary">Kembali Ke Data Produk</a>
            <button type="submit" class="btn btn-primary">Simpan Data Produk</button>
        </div>
    </form>
</div>

<script>
function formatHargaInput(input) {
    // Hapus karakter non-digit (termasuk titik/koma yang sering dipakai pemisah ribuan)
    let digits = input.value.replace(/[^0-9]/g, '');
    if (digits === '') {
        input.value = '';
        return;
    }

    // Batasi min 1 dan max 1.000.000.000
    let value = parseInt(digits, 10);
    if (isNaN(value)) {
        input.value = '';
        return;
    }
    if (value < 1) value = 1;
    if (value > 1000000000) value = 1000000000;

    input.value = value;
}
document.getElementById('tipe_barang').addEventListener('change', function () {
    var unitContainer = document.getElementById('unitCountContainer');
    var stokInput = document.getElementById('stok');
    var jumlahUnitInput = document.getElementById('jumlah_unit');
    if (this.value === 'asset') {
        unitContainer.style.display = 'block';
        jumlahUnitInput.value = Math.max(0, parseInt(stokInput.value || 0, 10) || 0);
    } else {
        unitContainer.style.display = 'none';
        jumlahUnitInput.value = 0;
    }
});

document.getElementById('stok').addEventListener('input', function () {
    if (document.getElementById('tipe_barang').value === 'asset') {
        document.getElementById('jumlah_unit').value = Math.max(0, parseInt(this.value || 0, 10) || 0);
    }
});

document.getElementById('tipe_barang').dispatchEvent(new Event('change'));
</script>
