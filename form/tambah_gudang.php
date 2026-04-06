<?php
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    include __DIR__ . '/../koneksi/koneksi.php';
}
require_auth_roles(['admin', 'petugas'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=data_gudang',
]);
?>
<div class="form-container">
    <div class="form-header">
        <h5>Form Tambah Data Gudang</h5>
    </div>
    <form action="actions/simpan_gudang.php" method="POST">
        <div class="mb-3">
            <label for="namaGudang" class="form-label">Nama Gudang</label>
            <input type="text" class="form-control" id="namaGudang" name="namaGudang" placeholder="Inputkan Nama Gudang" required>
        </div>
        <div class="mb-3">
            <label for="lokasiGudang" class="form-label">Lokasi Gudang</label>
            <input type="text" class="form-control" id="lokasiGudang" name="lokasiGudang" placeholder="Inputkan Lokasi Gudang" required>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=data_gudang" class="btn btn-secondary">Kembali Ke Data Gudang</a>
            <button type="submit" class="btn btn-primary">Simpan Data Gudang</button>
        </div>
    </form>
</div>
