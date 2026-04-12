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

$flash = consume_flash_message();
$legacyError = trim((string) ($_SESSION['error'] ?? ''));
if ($legacyError !== '') {
    $flash = ['type' => 'error', 'message' => $legacyError];
    unset($_SESSION['error']);
}
?>

<div class="form-container product-form-modern">
    <div class="form-header">
        <h5>Form Tambah Data Produk</h5>
        <p class="form-header-subtitle mb-0">Lengkapi data dengan format yang benar untuk menjaga konsistensi inventaris.</p>
    </div>
    <?php if ($flash && !empty($flash['message'])): ?>
        <?php $flashClass = 'alert-info'; ?>
        <?php if ($flash['type'] === 'success') $flashClass = 'alert-success'; ?>
        <?php if ($flash['type'] === 'error') $flashClass = 'alert-danger'; ?>
        <?php if ($flash['type'] === 'warning') $flashClass = 'alert-warning'; ?>
        <div class="alert <?= htmlspecialchars($flashClass) ?> mb-3"><?= htmlspecialchars((string) $flash['message']) ?></div>
    <?php endif; ?>

    <form id="formTambahProduk" action="action/simpan_produk.php" method="POST" enctype="multipart/form-data" novalidate>
        <div class="card product-section-card mb-4">
            <div class="card-body">
                <h6 class="product-section-title mb-3">Informasi Produk</h6>
                <div class="row g-3 st-form-grid">
                    <div class="col-md-6">
                        <label for="code" class="form-label">Kode Produk</label>
                        <input type="text" class="form-control" id="code" name="code" placeholder="PRD-001" required>
                        <small class="text-muted">Kode harus unik dan mudah dilacak.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="namaproduk" class="form-label">Nama Produk</label>
                        <input type="text" class="form-control" id="namaproduk" name="namaproduk" placeholder="Kertas A4" required>
                        <small class="text-muted">Gunakan nama yang deskriptif dan konsisten.</small>
                    </div>
                    <div class="col-12">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" placeholder="Spesifikasi singkat"></textarea>
                        <small class="text-muted">Opsional untuk catatan spesifikasi.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="kategori" class="form-label">Kategori</label>
                        <select class="form-select" id="kategori" name="kategori" required>
                            <option value="">-- Pilih --</option>
                            <?php while ($kategori = $resultKategori->fetch_assoc()): ?>
                                <option value="<?php echo $kategori['id_kategori']; ?>"><?php echo $kategori['nama_kategori']; ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Pilih kategori sesuai jenis barang.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="gudang" class="form-label">Gudang</label>
                        <select class="form-select" id="gudang" name="gudang" required>
                            <option value="">-- Pilih --</option>
                            <?php while ($gudang = $resultGudang->fetch_assoc()): ?>
                                <option value="<?php echo $gudang['id_gudang']; ?>"><?php echo $gudang['nama_gudang']; ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Gudang awal penyimpanan produk.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="stok" class="form-label">Stok</label>
                        <input type="number" class="form-control" id="stok" name="stok" placeholder="10" min="1" step="1" required>
                        <small class="text-muted">Minimal 1 saat tambah produk.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="satuan" class="form-label">Satuan</label>
                        <input type="text" class="form-control" id="satuan" name="satuan" placeholder="pcs" required>
                        <small class="text-muted">Contoh: pcs, box, unit.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="tipe_barang" class="form-label">Tipe</label>
                        <select name="tipe_barang" id="tipe_barang" class="form-select" required>
                            <option value="consumable">Consumable</option>
                            <option value="asset">Asset</option>
                        </select>
                        <small class="text-muted">Asset akan membentuk unit tracking.</small>
                    </div>
                    <div class="col-md-6" id="unitCountContainer" style="display:none;">
                        <label for="jumlah_unit" class="form-label">Jumlah Unit Awal</label>
                        <input type="number" class="form-control" id="jumlah_unit" name="jumlah_unit" placeholder="Mengikuti stok" min="0" value="0" readonly>
                        <small class="text-muted">Otomatis mengikuti stok untuk asset.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="harga" class="form-label">Harga</label>
                        <input type="text" class="form-control" id="harga" name="harga" placeholder="150000" required inputmode="numeric" oninput="formatHargaInput(this)">
                        <small class="form-text text-muted">Masukkan angka tanpa titik/koma.</small>
                    </div>
                    <div class="col-12">
                        <label for="gambar_produk" class="form-label">Gambar Produk</label>
                        <input type="file" class="form-control" id="gambar_produk" name="gambarproduk" accept=".jpg,.jpeg,.png,.webp,.gif">
                        <small class="text-muted">Belum ada file dipilih.</small>
                        <div class="upload-preview"></div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="status" value="tersedia" />
        <input type="hidden" name="kondisi" value="baik" />

        <div class="d-flex justify-content-end align-items-end gap-2 mt-2 flex-wrap product-form-actions">
            <a href="index.php?page=data_produk" class="btn btn-secondary px-4">Kembali</a>
            <div class="d-flex flex-column align-items-end gap-2 product-submit-group">
                <small id="submitHint" class="text-muted">Lengkapi semua field wajib untuk mengaktifkan tombol simpan.</small>
                <button type="submit" id="btnSimpanProduk" class="btn btn-primary btn-lg px-4" disabled>Simpan Data Produk</button>
            </div>
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

function validateFormProdukRealtime() {
    const requiredIds = ['code', 'namaproduk', 'kategori', 'gudang', 'stok', 'satuan', 'tipe_barang', 'harga'];
    const hasEmpty = requiredIds.some(function (id) {
        const el = document.getElementById(id);
        if (!el) return true;
        return String(el.value || '').trim() === '';
    });

    const stokValue = parseInt(document.getElementById('stok').value || '0', 10);
    const validStok = !isNaN(stokValue) && stokValue >= 1;
    const canSubmit = !hasEmpty && validStok;

    const btn = document.getElementById('btnSimpanProduk');
    const hint = document.getElementById('submitHint');
    btn.disabled = !canSubmit;
    hint.textContent = canSubmit
        ? 'Data siap disimpan.'
        : 'Lengkapi semua field wajib, dan pastikan stok minimal 1.';
}

(function () {
    var input = document.getElementById('gambar_produk');
    var preview = document.querySelector('.upload-preview');

    if (!input || !preview) return;

    input.addEventListener('change', function () {
        var file = this.files[0];

        if (!file) {
            preview.innerHTML = '';
            return;
        }

        if (file.type.startsWith('image/')) {
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            preview.innerHTML = '';
            preview.appendChild(img);
        } else {
            preview.innerHTML = '<small>Bukan gambar</small>';
        }
    });
})();

document.getElementById('formTambahProduk').addEventListener('input', validateFormProdukRealtime);
document.getElementById('formTambahProduk').addEventListener('change', validateFormProdukRealtime);
document.getElementById('formTambahProduk').addEventListener('submit', function (e) {
    validateFormProdukRealtime();
    if (document.getElementById('btnSimpanProduk').disabled) {
        e.preventDefault();
    }
});

document.getElementById('tipe_barang').dispatchEvent(new Event('change'));
validateFormProdukRealtime();
</script>
