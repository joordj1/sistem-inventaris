<?php
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    include __DIR__ . '/../koneksi/koneksi.php';
}
require_auth_roles(['admin', 'petugas'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=data_gudang',
]);

// Mendapatkan ID gudang yang ingin diedit dari URL
$id_gudang = isset($_GET['id_gudang']) ? intval($_GET['id_gudang']) : 0;

if ($id_gudang) {
    $stmt = $koneksi->prepare("SELECT * FROM gudang WHERE id_gudang = ?");
    $stmt->bind_param('i', $id_gudang);
    $stmt->execute();
    $result = $stmt->get_result();
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
