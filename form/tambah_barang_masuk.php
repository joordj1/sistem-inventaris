<?php
// Query untuk mengambil data produk
$queryProduk = "SELECT * FROM produk";
$resultProduk = $koneksi->query($queryProduk);
?>

<div class="form-container">
    <div class="form-header">
        <h5>Form Tambah Barang Masuk</h5>
    </div>
    <form action="actions/simpan_barang_masuk.php" method="POST">
        <div class="mb-3">
            <label for="no_invoice" class="form-label">No Invoice</label>
            <input type="text" class="form-control" id="no_invoice" name="no_invoice" placeholder="Inputkan No Invoice" required>
        </div>
        <div class="mb-3">
            <label for="kode_produk" class="form-label">Kode Produk</label>
            <select class="form-select" id="kode_produk" name="kode_produk" required onchange="updateNamaProduk()">
                <option value="">--Pilih Kode Produk--</option>
                <?php while ($produk = $resultProduk->fetch_assoc()): ?>
                    <option value="<?php echo $produk['id_produk']; ?>" data-nama-produk="<?php echo $produk['nama_produk']; ?>">
                        <?php echo $produk['kode_produk'] . " - " . $produk['nama_produk']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="nama_produk" class="form-label">Nama Produk</label>
            <input type="text" class="form-control" id="nama_produk" name="nama_produk" placeholder="Nama Produk" readonly>
        </div>
        <div class="mb-3">
            <label for="jumlah" class="form-label">Jumlah</label>
            <input type="number" class="form-control" id="jumlah" name="jumlah" placeholder="Jumlah Barang Masuk" required>
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
            <a href="index.php?page=barang_masuk" class="btn btn-secondary">Kembali</a>
            <button type="submit" class="btn btn-primary">Simpan Barang Masuk</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function updateNamaProduk() {
    const selectedOption = document.querySelector('#kode_produk option:checked');
    document.getElementById('nama_produk').value = selectedOption.dataset.namaProduk || '';
}

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
