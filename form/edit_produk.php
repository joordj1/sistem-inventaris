<?php

// Ambil data produk berdasarkan id
$id_produk = isset($_GET['id_produk']) ? $_GET['id_produk'] : '';
if ($id_produk) {
    $query = "SELECT * FROM produk WHERE id_produk = '$id_produk'";
    $result = $koneksi->query($query);
    $data = $result->fetch_assoc();

    // Ambil id gudang saat ini dari StokGudang
    $query_gudang = "SELECT gudang_id FROM StokGudang WHERE produk_id = '$id_produk'";
    $result_gudang = $koneksi->query($query_gudang);
    $gudang_data = $result_gudang->fetch_assoc();
    $current_gudang_id = $gudang_data['gudang_id'] ?? null;
} else {
    echo "ID Produk tidak ditemukan!";
    exit;
}

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_produk = $_POST['kode_produk'];
    $nama_produk = $_POST['nama_produk'];
    $kategori_id = $_POST['kategori_id'];
    $jumlah_stok = $_POST['jumlah_stok'];
    $satuan = $_POST['satuan'];
    $harga_satuan = $_POST['harga_satuan'];
    $gudang_id = $_POST['gudang_id'];
    $tanggal = date("Y-m-d H:i:s");
    $keterangan = "Perubahan gudang produk";

    // Cek apakah kode produk sudah ada di database (selain produk yang sedang diedit)
    $query_check = "SELECT id_produk FROM produk WHERE kode_produk = '$kode_produk' AND id_produk != '$id_produk'";
    $result_check = $koneksi->query($query_check);

    if ($result_check->num_rows > 0) {
        // Jika kode produk sudah ada, tampilkan alert
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Kode Produk Sudah Ada',
                text: 'Kode produk yang Anda masukkan sudah terdaftar. Silakan gunakan kode lain.'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit;
    }
    
    

    else {

    // Logika upload file dan update data produk
    if ($_FILES['gambar_produk']['name']) {
        $gambar_produk = $_FILES['gambar_produk']['name'];
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($gambar_produk);

        if (move_uploaded_file($_FILES['gambar_produk']['tmp_name'], $target_file)) {
            try {
                $koneksi->begin_transaction();

                // Update data produk
                $query_update = "UPDATE produk SET kode_produk = '$kode_produk', nama_produk = '$nama_produk', kategori_id = '$kategori_id', jumlah_stok = '$jumlah_stok', satuan = '$satuan', harga_satuan = '$harga_satuan', gambar_produk = '$gambar_produk' WHERE id_produk = '$id_produk'";
                $koneksi->query($query_update);

                // Update data di StokGudang
                $query_update_gudang = "UPDATE StokGudang SET gudang_id = ? WHERE produk_id = ?";
                $stmt_update_gudang = $koneksi->prepare($query_update_gudang);
                $stmt_update_gudang->bind_param("ii", $gudang_id, $id_produk);
                $stmt_update_gudang->execute();

                

                $koneksi->commit();
                header("Location: index.php?page=data_produk");
                exit;
            } catch (Exception $e) {
                $koneksi->rollback();
                echo "Error: " . $e->getMessage();
            }
        } else {
            echo "Gagal mengupload gambar.";
            exit;
        }
    } else {
        try {
            $koneksi->begin_transaction();

            // Update data produk tanpa gambar
            $query_update = "UPDATE produk SET kode_produk = '$kode_produk', nama_produk = '$nama_produk', kategori_id = '$kategori_id', jumlah_stok = '$jumlah_stok', satuan = '$satuan', harga_satuan = '$harga_satuan' WHERE id_produk = '$id_produk'";
            $koneksi->query($query_update);

            // Update data di StokGudang
            $query_update_gudang = "UPDATE StokGudang SET gudang_id = ? WHERE produk_id = ?";
            $stmt_update_gudang = $koneksi->prepare($query_update_gudang);
            $stmt_update_gudang->bind_param("ii", $gudang_id, $id_produk);
            $stmt_update_gudang->execute();

            

            $koneksi->commit();
            header("Location: index.php?page=data_produk");
            exit;
        } catch (Exception $e) {
            $koneksi->rollback();
            echo "Error: " . $e->getMessage();
        }
    }
    }
}
?>

<!-- Form Edit Produk -->
<div class="form-container">
    <div class="form-header">
        <h5>Edit Data Produk</h5>
    </div>
    <form action="" method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="kode_produk" class="form-label">Kode Produk</label>
            <input type="text" class="form-control" id="kode_produk" name="kode_produk" value="<?= $data['kode_produk']; ?>">
        </div>
        <div class="mb-3">
            <label for="nama_produk" class="form-label">Nama Produk</label>
            <input type="text" class="form-control" id="nama_produk" name="nama_produk" value="<?= $data['nama_produk']; ?>">
        </div>
        <div class="mb-3">
            <label for="kategori_id" class="form-label">Kategori Produk</label>
            <select name="kategori_id" id="kategori_id" class="form-select">
                <option value="">--Pilih Kategori--</option>
                <?php
                $kategori_query = "SELECT * FROM kategori";
                $kategori_result = $koneksi->query($kategori_query);
                while ($kategori = $kategori_result->fetch_assoc()):
                    $selected = ($kategori['id_kategori'] == $data['kategori_id']) ? 'selected' : '';
                    echo '<option value="'.$kategori['id_kategori'].'" '.$selected.'>'.$kategori['nama_kategori'].'</option>';
                endwhile;
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="gudang_id" class="form-label">Gudang</label>
            <select name="gudang_id" id="gudang_id" class="form-select">
                <option value="">--Pilih Gudang--</option>
                <?php
                $query_gudang_options = "SELECT * FROM gudang";
                $result_gudang_options = $koneksi->query($query_gudang_options);
                while ($gudang = $result_gudang_options->fetch_assoc()):
                    $selected = ($gudang['id_gudang'] == $current_gudang_id) ? 'selected' : '';
                    echo "<option value='{$gudang['id_gudang']}' {$selected}>{$gudang['nama_gudang']}</option>";
                endwhile;
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="jumlah_stok" class="form-label">Stok Produk</label>
            <input type="number" class="form-control" id="jumlah_stok" name="jumlah_stok" value="<?= $data['jumlah_stok']; ?>">
        </div>
        <div class="mb-3">
            <label for="satuan" class="form-label">Satuan</label>
            <input type="text" class="form-control" id="satuan" name="satuan" value="<?= $data['satuan']; ?>">
        </div>
        <div class="mb-3">
            <label for="harga_satuan" class="form-label">Harga Satuan</label>
            <input type="number" class="form-control" id="harga_satuan" name="harga_satuan" value="<?= $data['harga_satuan']; ?>">
        </div>
        <div class="mb-3">
            <label for="gambar_produk" class="form-label">Upload Gambar Produk</label><br>
            <img src="uploads/<?= $data['gambar_produk']; ?>" alt="Gambar Produk" style="width: 300px; height: auto;">
            <input type="file" class="form-control mt-2" id="gambar_produk" name="gambar_produk">
        </div>

        <div class="d-flex justify-content-between">
            <a href="index.php?page=data_produk"><button type="button" class="btn btn-secondary">Kembali Ke Data Produk</button></a>
            <button type="submit" class="btn btn-primary">Simpan Prubahan</button>
        </div>

        
    </form>
</div>