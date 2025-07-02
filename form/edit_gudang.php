<?php
// Mendapatkan ID gudang yang ingin diedit dari URL
$id_gudang = isset($_GET['id_gudang']) ? $_GET['id_gudang'] : '';

if ($id_gudang) {
    // Query untuk mengambil data gudang berdasarkan ID
    $query = "SELECT * FROM gudang WHERE id_gudang = '$id_gudang'";
    $result = $koneksi->query($query);
    $data = $result->fetch_assoc();
} else {
    echo "ID Gudang tidak ditemukan!";
    exit;
}
?>

<!-- Form Edit Gudang -->
<div class="form-container">
    <div class="form-header">
        <h5>Edit Data Gudang</h5>
    </div>
    <form action="actions/update_gudang.php" method="post">
        <input type="hidden" name="id_gudang" value="<?= $id_gudang ?>">
        
        <div class="mb-3">
            <label for="nama_gudang" class="form-label">Nama Gudang</label>
            <input type="text" class="form-control" id="nama_gudang" name="nama_gudang" value="<?= $data['nama_gudang']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="lokasi" class="form-label">Lokasi</label>
            <input type="text" class="form-control" id="lokasi" name="lokasi" value="<?= $data['lokasi']; ?>" required>
        </div>
        <div class="d-flex justify-content-between">
            <a href="index.php?page=data_gudang"><button type="button" class="btn btn-secondary">Kembali Ke Data Gudang</button></a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>
