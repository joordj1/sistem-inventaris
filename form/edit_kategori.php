<?php


// Cek apakah ada ID kategori yang dikirim melalui URL
if (isset($_GET['id_kategori'])) {
    $id_kategori = $_GET['id_kategori'];

    // Query untuk mengambil data kategori berdasarkan ID
    $query = "SELECT * FROM kategori WHERE id_kategori = ?";
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("i", $id_kategori);
    $stmt->execute();
    $result = $stmt->get_result();

    // Periksa apakah data kategori ditemukan
    if ($result->num_rows > 0) {
        $kategori = $result->fetch_assoc();
    } else {
        echo "Data kategori tidak ditemukan.";
        exit;
    }
} else {
    echo "ID kategori tidak diberikan.";
    exit;
}

// Cek apakah form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_kategori = $_POST['nama_kategori'];

    // Query untuk memperbarui data kategori
    $update_query = "UPDATE kategori SET nama_kategori = ? WHERE id_kategori = ?";
    $stmt_update = $koneksi->prepare($update_query);
    $stmt_update->bind_param("si", $nama_kategori, $id_kategori);

    if ($stmt_update->execute()) {
        header("Location: index.php?page=kategori_barang"); // Redirect ke halaman data produk setelah update
        exit;
    } else {
        echo "Terjadi kesalahan saat menyimpan perubahan.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kategori</title>
    <link rel="stylesheet" href="style.css"> <!-- Sesuaikan dengan file CSS Anda -->
</head>
<body>
<div class="form-container">
    <form action="" method="post">
        <div class="form-header">
            <h5>Edit Data Produk</h5>
        </div>
        <div class="form-group mb-3">
            <label for="nama_kategori" class="form-label">Nama Kategori:</label>
            <input type="text" name="nama_kategori" id="nama_kategori" class="form-control" value="<?= htmlspecialchars($kategori['nama_kategori']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php?page=kategori_barang" class="btn btn-secondary">Batal</a>
    </form>
</div>
</body>
</html>
