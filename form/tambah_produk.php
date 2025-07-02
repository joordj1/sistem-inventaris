<?php
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
    <form action="actions/simpan_produk.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="code" class="form-label">Code Produk</label>
            <input type="text" class="form-control" id="code" name="code" placeholder="Inputkan Code Produk" required>
        </div>
        <div class="mb-3">
            <label for="namaproduk" class="form-label">Nama Produk</label>
            <input type="text" class="form-control" id="namaproduk" name="namaproduk" placeholder="Inputkan Nama Produk" required>
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
            <input type="number" class="form-control" id="stok" name="stok" placeholder="Inputkan Stok Produk" required>
        </div>
        <div class="mb-3">
            <label for="satuan" class="form-label">Satuan</label>
            <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Inputkan Satuan Produk" required>
        </div>
        <div class="mb-3">
            <label for="harga" class="form-label">Harga</label>
            <input type="number" class="form-control" id="harga" name="harga" placeholder="Inputkan Harga Produk" required>
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
