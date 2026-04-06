<?php
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    include __DIR__ . '/../koneksi/koneksi.php';
}
require_auth_roles(['admin', 'petugas'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=barang_keluar',
]);

// Query untuk mengambil data produk yang stoknya lebih dari 0
$queryProduk = "SELECT * FROM produk WHERE jumlah_stok > 0";
$resultProduk = $koneksi->query($queryProduk);
?>

<div class="form-container">
    <div class="form-header">
        <h5>Form Tambah Barang Keluar</h5>
    </div>
    <form action="actions/simpan_barang_keluar.php" method="POST">
        <div class="mb-3">
            <label for="no_invoice" class="form-label">No Invoice</label>
            <input type="text" class="form-control" id="no_invoice" name="no_invoice" placeholder="Inputkan No Invoice" required>
        </div>
        <div class="mb-3">
            <label for="kode_produk" class="form-label">Kode Produk</label>
            <select class="form-select" id="kode_produk" name="kode_produk" required onchange="updateNamaProduk()">
                <option value="">--Pilih Kode Produk--</option>
                <?php while ($produk = $resultProduk->fetch_assoc()): ?>
                    <?php if ($produk['tipe_barang'] !== 'consumable') continue; ?>
                    <option value="<?php echo $produk['id_produk']; ?>" data-nama-produk="<?php echo $produk['nama_produk']; ?>" data-stok="<?php echo $produk['jumlah_stok']; ?>">
                        <?php echo $produk['kode_produk'] . " - " . $produk['nama_produk'] . " (Stok: " . $produk['jumlah_stok'] . ")"; ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <small class="text-muted d-block">Hanya produk consumable ditampilkan di sini.</small>
        </div>
        <div class="mb-3">
            <label for="nama_produk" class="form-label">Nama Produk</label>
            <input type="text" class="form-control" id="nama_produk" name="nama_produk" placeholder="Nama Produk" readonly>
        </div>
        <div class="mb-3">
            <label for="jumlah" class="form-label">Jumlah</label>
            <input type="number" class="form-control" id="jumlah" name="jumlah" placeholder="Jumlah Barang Keluar" required min="1">
        </div>
        <div class="mb-3">
            <label for="tanggal" class="form-label">Tanggal</label>
            <input type="date" class="form-control" id="tanggal" name="tanggal" required>
        </div>
        <div class="mb-3">
            <label for="keterangan" class="form-label">Keterangan</label>
            <textarea class="form-control" id="keterangan" name="keterangan" placeholder="Keterangan" rows="3"></textarea>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=barang_keluar" class="btn btn-secondary">Kembali</a>
            <button type="submit" class="btn btn-primary">Simpan Barang Keluar</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function updateNamaProduk() {
    const selectedOption = document.querySelector('#kode_produk option:checked');
    document.getElementById('nama_produk').value = selectedOption.dataset.namaProduk || '';
}

// Cek No Invoice dengan SweetAlert
document.querySelector("form").addEventListener("submit", function (e) {
    e.preventDefault();
    const noInvoice = document.getElementById("no_invoice").value;

    fetch(`actions/cek_invoice.php?no_invoice=${noInvoice}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                Swal.fire({
                    icon: "error",
                    title: "No Invoice sudah ada!",
                    text: "Silakan gunakan No Invoice yang berbeda.",
                });
            } else {
                this.submit();
            }
        });
});
</script>
