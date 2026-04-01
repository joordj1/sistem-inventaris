<div class="form-container">
    <div class="form-header">
        <h5>Form Tambah Kategori Baru</h5>
    </div>
    <form action="actions/simpan_kategori.php" method="POST">
        <div class="mb-3">
            <label for="nama_ktgr" class="form-label">Nama Kategori</label> 
            <input type="text" class="form-control" id="nama_ktgr" name="nama_ktgr" placeholder="Inputkan Nama Kategori" required>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=kategori_barang" class="btn btn-secondary">Kembali Ke Data Kategori</a>
            <button type="submit" class="btn btn-primary">Simpan Kategori</button>
        </div>
    </form>
</div>
