<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    include __DIR__ . '/../koneksi/koneksi.php';
}
require_auth_roles(['admin', 'petugas'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=data_produk',
]);

function normalize_edit_value($value) {
    return strtolower(trim((string) ($value ?? '')));
}

function normalize_edit_kondisi($value) {
    static $kondisiMap = [
        'baik' => 'baik',
        'rusak' => 'rusak',
        'diperbaiki' => 'diperbaiki',
        'usang' => 'usang',
        'lainnya' => 'lainnya',
    ];

    $normalized = normalize_edit_value($value);
    return $kondisiMap[$normalized] ?? 'baik';
}

function normalize_asset_form_status($value) {
    static $statusMap = [
        'tersedia' => 'tersedia',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'digunakan',
        'sedang digunakan' => 'digunakan',
        'rusak' => 'rusak',
        'perbaikan' => 'perbaikan',
        'dalam perbaikan' => 'perbaikan',
    ];

    $normalized = normalize_edit_value($value);
    return $statusMap[$normalized] ?? 'tersedia';
}

function normalize_consumable_form_status($value) {
    static $statusMap = [
        'tersedia' => 'tersedia',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'sedang digunakan',
        'sedang digunakan' => 'sedang digunakan',
        'dipindahkan' => 'dipindahkan',
        'perbaikan' => 'dalam perbaikan',
        'dalam perbaikan' => 'dalam perbaikan',
        'rusak' => 'rusak',
        'tidak aktif' => 'tidak aktif',
    ];

    $normalized = normalize_edit_value($value);
    return $statusMap[$normalized] ?? 'tersedia';
}

function normalize_edit_optional_text($value) {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function build_edit_produk_assignment_sql($koneksi, $id_gudang, $lokasi_custom, $id_user) {
    $assignments = [];
    $assignments[] = "id_gudang = " . ($id_gudang !== null ? intval($id_gudang) : "NULL");

    if ($lokasi_custom !== null) {
        $assignments[] = "lokasi_custom = '" . $koneksi->real_escape_string($lokasi_custom) . "'";
    } else {
        $assignments[] = "lokasi_custom = NULL";
    }

    $assignments[] = "id_user = " . ($id_user !== null ? intval($id_user) : "NULL");

    return implode(', ', $assignments);
}

function sync_edit_produk_stok_gudang($koneksi, $id_produk, $id_gudang) {
    $id_produk = intval($id_produk);
    $tableCheck = $koneksi->query("SHOW TABLES LIKE 'StokGudang'");

    if ($id_produk < 1 || !$tableCheck || intval($tableCheck->num_rows) === 0) {
        return;
    }

    if ($id_gudang === null) {
        $stmtDelete = $koneksi->prepare("DELETE FROM StokGudang WHERE id_produk = ?");
        if ($stmtDelete) {
            $stmtDelete->bind_param('i', $id_produk);
            $stmtDelete->execute();
        }
        return;
    }

    $id_gudang = intval($id_gudang);
    $stmtCheck = $koneksi->prepare("SELECT 1 FROM StokGudang WHERE id_produk = ? LIMIT 1");
    if (!$stmtCheck) {
        return;
    }

    $stmtCheck->bind_param('i', $id_produk);
    $stmtCheck->execute();
    $hasRow = $stmtCheck->get_result()->num_rows > 0;

    if ($hasRow) {
        $stmtUpdate = $koneksi->prepare("UPDATE StokGudang SET id_gudang = ? WHERE id_produk = ?");
        if ($stmtUpdate) {
            $stmtUpdate->bind_param('ii', $id_gudang, $id_produk);
            $stmtUpdate->execute();
        }
        return;
    }

    $stmtInsert = $koneksi->prepare("INSERT INTO StokGudang (id_produk, id_gudang, jumlah_stok) VALUES (?, ?, 0)");
    if ($stmtInsert) {
        $stmtInsert->bind_param('ii', $id_produk, $id_gudang);
        $stmtInsert->execute();
    }
}

function map_asset_status_to_product_status($value) {
    static $statusMap = [
        'tersedia' => 'tersedia',
        'dipinjam' => 'dipinjam',
        'digunakan' => 'sedang digunakan',
        'rusak' => 'rusak',
        'perbaikan' => 'dalam perbaikan',
    ];

    $normalized = normalize_asset_form_status($value);
    return $statusMap[$normalized] ?? 'tersedia';
}

$assetStatusOptions = [
    'tersedia' => 'Tersedia',
    'dipinjam' => 'Dipinjam',
    'digunakan' => 'Digunakan',
    'rusak' => 'Rusak',
    'perbaikan' => 'Perbaikan',
];

$consumableStatusOptions = [
    'tersedia' => 'Tersedia',
    'dipinjam' => 'Dipinjam',
    'sedang digunakan' => 'Sedang Digunakan',
    'dipindahkan' => 'Dipindahkan',
    'dalam perbaikan' => 'Dalam Perbaikan',
    'rusak' => 'Rusak',
    'tidak aktif' => 'Tidak Aktif',
];

// Ambil data produk berdasarkan id
$id_produk = isset($_GET['id_produk']) ? $_GET['id_produk'] : '';
if ($id_produk) {
    $query = "SELECT * FROM produk WHERE id_produk = '$id_produk'";
    $result = $koneksi->query($query);
    $data = $result->fetch_assoc();
    $current_harga_master = (int) round((float) (($data['harga_default'] ?? 0) > 0 ? $data['harga_default'] : ($data['harga_satuan'] ?? 0)));

    // Prioritaskan gudang master produk; StokGudang hanya fallback untuk data lama.
    $query_gudang = "SELECT id_gudang FROM StokGudang WHERE id_produk = '$id_produk'";
    $result_gudang = $koneksi->query($query_gudang);
    $gudang_data = $result_gudang->fetch_assoc();
    $current_id_gudang = $data['id_gudang'] ?? ($gudang_data['id_gudang'] ?? null);
    $is_asset_product = (($data['tipe_barang'] ?? 'consumable') === 'asset');
    $status_options = $is_asset_product ? $assetStatusOptions : $consumableStatusOptions;
    $selected_status = $is_asset_product
        ? normalize_asset_form_status($data['status'] ?? 'tersedia')
        : normalize_consumable_form_status($data['status'] ?? 'tersedia');
} else {
    echo "ID Produk tidak ditemukan!";
    exit;
}

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_produk = trim((string) ($_POST['kode_produk'] ?? ''));
    $nama_produk = trim((string) ($_POST['nama_produk'] ?? ''));
    $deskripsi = normalize_edit_optional_text($_POST['deskripsi'] ?? null);
    $id_kategori = filter_var($_POST['id_kategori'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    $jumlah_stok = filter_var($_POST['jumlah_stok'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0]
    ]);
    $satuan = trim((string) ($_POST['satuan'] ?? ''));
    $harga_satuan = preg_replace('/[^0-9]/', '', (string) ($_POST['harga_satuan'] ?? ''));
    if ($harga_satuan === '') {
        $harga_satuan = $current_harga_master;
    }
    $harga_satuan = (int)$harga_satuan;
    if ($harga_satuan < 1) {
        $harga_satuan = 1;
    }
    if ($harga_satuan > 1000000000) {
        $harga_satuan = 1000000000;
    }
    $total_nilai = $harga_satuan * (int) $jumlah_stok;
    $id_gudang = $_POST['id_gudang'];
    $tanggal = date("Y-m-d H:i:s");
    $keterangan = "Perubahan gudang produk";

    if ($kode_produk === '' || $nama_produk === '' || $satuan === '') {
        echo "<script>alert('Kode produk, nama produk, dan satuan wajib diisi.'); window.history.back();</script>";
        exit;
    }
    if ($id_kategori === false) {
        echo "<script>alert('Kategori produk tidak valid.'); window.history.back();</script>";
        exit;
    }
    if ($jumlah_stok === false) {
        echo "<script>alert('Stok produk harus berupa angka dan tidak boleh kurang dari 0.'); window.history.back();</script>";
        exit;
    }

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

    $tipe_barang = $_POST['tipe_barang'] ?? ($data['tipe_barang'] ?? 'consumable');
    if (!in_array($tipe_barang, ['consumable', 'asset'], true)) {
        echo "<script>alert('Tipe produk tidak valid.'); window.history.back();</script>";
        exit;
    }
    $status = ($tipe_barang === 'asset')
        ? map_asset_status_to_product_status($_POST['status'] ?? 'tersedia')
        : normalize_consumable_form_status($_POST['status'] ?? 'tersedia');
    $kondisi = normalize_edit_kondisi($_POST['kondisi'] ?? 'baik');
    $id_gudang = $_POST['id_gudang'] ? intval($_POST['id_gudang']) : null;
    $lokasi_custom = normalize_edit_optional_text($_POST['lokasi_custom'] ?? null);
    $id_user = filter_var($_POST['id_user'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    if ($id_user === false) {
        $id_user = null;
    }
    $operator = $_SESSION['id_user'] ?? null;

    $produk_before = $koneksi->query("SELECT status,kondisi,id_gudang,lokasi_custom,id_user FROM produk WHERE id_produk = '$id_produk'")->fetch_assoc();

    function insert_tracking_for_edit($conn, $id_produk, $kode_produk, $before, $after, $activity_type, $note, $user_op) {
        log_tracking_history($conn, [
            'id_produk' => $id_produk,
            'kode_produk' => $kode_produk,
            'status_sebelum' => $before['status'],
            'status_sesudah' => $after['status'],
            'kondisi_sebelum' => $before['kondisi'],
            'kondisi_sesudah' => $after['kondisi'],
            'lokasi_sebelum' => $before['lokasi_custom'] ?: $before['id_gudang'],
            'lokasi_sesudah' => $after['lokasi_custom'] ?: $after['id_gudang'],
            'id_user_sebelum' => $before['id_user'] ?? null,
            'id_user_sesudah' => $after['id_user'] ?? null,
            'id_user_terkait' => $after['id_user'] ?? $before['id_user'] ?? null,
            'activity_type' => $activity_type,
            'note' => $note,
            'id_user_changed' => $user_op
        ]);
    }

    $after_data = [
        'status' => $status,
        'kondisi' => $kondisi,
        'id_gudang' => $id_gudang,
        'lokasi_custom' => $lokasi_custom,
        'id_user' => $id_user,
    ];
    $deskripsi_sql = $deskripsi !== null ? "'" . $koneksi->real_escape_string($deskripsi) . "'" : "NULL";

    // Logika upload file dan update data produk
    if ($_FILES['gambar_produk']['name']) {
        $gambar_produk = $_FILES['gambar_produk']['name'];
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($gambar_produk);

        if (move_uploaded_file($_FILES['gambar_produk']['tmp_name'], $target_file)) {
            try {
                $koneksi->begin_transaction();

                // Update data produk tanpa menyentuh serial unit.
                $query_update = "UPDATE produk SET kode_produk = '$kode_produk', nama_produk = '$nama_produk', deskripsi = $deskripsi_sql, id_kategori = '$id_kategori', jumlah_stok = '$jumlah_stok', satuan = '$satuan', harga_default = '$harga_satuan', harga_satuan = '$harga_satuan', total_nilai = '$total_nilai', gambar_produk = '$gambar_produk', status = '$status', kondisi = '$kondisi', tersedia = 1, tipe_barang = '$tipe_barang', " . build_edit_produk_assignment_sql($koneksi, $id_gudang, $lokasi_custom, $id_user);
                $query_update .= " WHERE id_produk = '$id_produk'";
                if (!$koneksi->query($query_update)) {
                    throw new Exception($koneksi->error);
                }

                // Update data di StokGudang
                sync_edit_produk_stok_gudang($koneksi, $id_produk, $id_gudang);

                if ($tipe_barang === 'asset') {
                    $assetSyncResult = sync_asset_units_for_product($koneksi, [
                        'id_produk' => $id_produk,
                        'kode_produk' => $kode_produk,
                        'jumlah_stok' => $jumlah_stok,
                        'id_gudang' => $id_gudang,
                        'kondisi' => $kondisi,
                    ], [
                        'operator_id' => $operator,
                        'create_note' => 'Unit asset ditambahkan dari sinkronisasi edit produk',
                        'old_gudang_id' => $produk_before['id_gudang'] ?? null,
                    ]);

                    if (empty($assetSyncResult['success'])) {
                        throw new Exception($assetSyncResult['message'] ?? 'Sinkronisasi unit asset gagal.');
                    }
                }

                insert_tracking_for_edit($koneksi, $id_produk, $kode_produk, $produk_before, $after_data, 'update', 'Edit data produk lewat form', $operator);
                log_activity($koneksi, [
                    'id_user' => $operator,
                    'role_user' => get_current_user_role(),
                    'action_name' => 'produk_edit',
                    'entity_type' => 'produk',
                    'entity_id' => $id_produk,
                    'entity_label' => $kode_produk . ' - ' . $nama_produk,
                    'description' => 'Memperbarui data barang',
                    'id_produk' => $id_produk,
                    'id_gudang' => $id_gudang,
                    'metadata_json' => [
                        'nama_produk' => $nama_produk,
                        'deskripsi' => $deskripsi,
                        'tipe_barang' => $tipe_barang,
                        'jumlah_stok' => $jumlah_stok,
                    ],
                ]);

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

            // Update data produk tanpa gambar (unit asset tidak disinkronkan dengan kode produk baru)
            $query_update = "UPDATE produk SET kode_produk = '$kode_produk', nama_produk = '$nama_produk', deskripsi = $deskripsi_sql, id_kategori = '$id_kategori', jumlah_stok = '$jumlah_stok', satuan = '$satuan', harga_default = '$harga_satuan', harga_satuan = '$harga_satuan', total_nilai = '$total_nilai', status = '$status', kondisi = '$kondisi', tersedia = 1, tipe_barang = '$tipe_barang', " . build_edit_produk_assignment_sql($koneksi, $id_gudang, $lokasi_custom, $id_user);
            $query_update .= " WHERE id_produk = '$id_produk'";
            if (!$koneksi->query($query_update)) {
                throw new Exception($koneksi->error);
            }

            // Update data di StokGudang
            sync_edit_produk_stok_gudang($koneksi, $id_produk, $id_gudang);

            if ($tipe_barang === 'asset') {
                $assetSyncResult = sync_asset_units_for_product($koneksi, [
                    'id_produk' => $id_produk,
                    'kode_produk' => $kode_produk,
                    'jumlah_stok' => $jumlah_stok,
                    'id_gudang' => $id_gudang,
                    'kondisi' => $kondisi,
                ], [
                    'operator_id' => $operator,
                    'create_note' => 'Unit asset ditambahkan dari sinkronisasi edit produk',
                    'old_gudang_id' => $produk_before['id_gudang'] ?? null,
                ]);

                if (empty($assetSyncResult['success'])) {
                    throw new Exception($assetSyncResult['message'] ?? 'Sinkronisasi unit asset gagal.');
                }
            }

            insert_tracking_for_edit($koneksi, $id_produk, $kode_produk, $produk_before, $after_data, 'update', 'Edit data produk lewat form', $operator);
            log_activity($koneksi, [
                'id_user' => $operator,
                'role_user' => get_current_user_role(),
                'action_name' => 'produk_edit',
                'entity_type' => 'produk',
                'entity_id' => $id_produk,
                'entity_label' => $kode_produk . ' - ' . $nama_produk,
                'description' => 'Memperbarui data barang',
                'id_produk' => $id_produk,
                'id_gudang' => $id_gudang,
                'metadata_json' => [
                    'nama_produk' => $nama_produk,
                    'deskripsi' => $deskripsi,
                    'tipe_barang' => $tipe_barang,
                    'jumlah_stok' => $jumlah_stok,
                ],
            ]);

            $koneksi->commit();
            header("Location: index.php?page=data_produk");
            exit;
        } catch (Exception $e) {
            $koneksi->rollback();
            echo "Error: " . $e->getMessage();
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
            <label for="deskripsi" class="form-label">Deskripsi Barang</label>
            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($data['deskripsi'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label for="id_kategori" class="form-label">Kategori Produk</label>
            <select name="id_kategori" id="id_kategori" class="form-select">
                <option value="">--Pilih Kategori--</option>
                <?php
                $kategori_query = "SELECT * FROM kategori";
                $kategori_result = $koneksi->query($kategori_query);
                while ($kategori = $kategori_result->fetch_assoc()):
                    $selected = ($kategori['id_kategori'] == $data['id_kategori']) ? 'selected' : '';
                    echo '<option value="'.$kategori['id_kategori'].'" '.$selected.'>'.$kategori['nama_kategori'].'</option>';
                endwhile;
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="tipe_barang" class="form-label">Tipe Produk</label>
            <select name="tipe_barang" id="tipe_barang" class="form-select">
                <option value="consumable" <?= $data['tipe_barang'] == 'consumable' ? 'selected' : '' ?>>Consumable</option>
                <option value="asset" <?= $data['tipe_barang'] == 'asset' ? 'selected' : '' ?>>Asset</option>
            </select>
            <small class="text-muted">Asset akan menambah unit, bukan lewat stok transaksi.</small>
        </div>
        <div class="mb-3">
            <label for="id_gudang" class="form-label">Gudang</label>
            <select name="id_gudang" id="id_gudang" class="form-select">
                <option value="">--Pilih Gudang--</option>
                <?php
                $query_gudang_options = "SELECT * FROM gudang";
                $result_gudang_options = $koneksi->query($query_gudang_options);
                while ($gudang = $result_gudang_options->fetch_assoc()):
                    $selected = ($gudang['id_gudang'] == $current_id_gudang) ? 'selected' : '';
                    echo "<option value='{$gudang['id_gudang']}' {$selected}>{$gudang['nama_gudang']}</option>";
                endwhile;
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="lokasi_custom" class="form-label">Lokasi Custom</label>
            <input type="text" class="form-control" id="lokasi_custom" name="lokasi_custom" value="<?= htmlspecialchars($data['lokasi_custom'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php foreach ($status_options as $value => $label): ?>
                <option value="<?= $value ?>" <?= ($selected_status === $value) ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="kondisi" class="form-label">Kondisi</label>
            <select name="kondisi" class="form-select">
                <option value="baik" <?= ($data['kondisi'] == 'baik') ? 'selected' : '' ?>>Baik</option>
                <option value="rusak" <?= ($data['kondisi'] == 'rusak') ? 'selected' : '' ?>>Rusak</option>
                <option value="diperbaiki" <?= ($data['kondisi'] == 'diperbaiki') ? 'selected' : '' ?>>Diperbaiki</option>
                <option value="usang" <?= ($data['kondisi'] == 'usang') ? 'selected' : '' ?>>Usang</option>
                <option value="lainnya" <?= ($data['kondisi'] == 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="id_user" class="form-label">User Terkait</label>
            <select name="id_user" class="form-select">
                <option value="">--Pilih User--</option>
                <?php
                $quser = $koneksi->query("SELECT id_user, nama FROM user");
                while ($usr = $quser->fetch_assoc()):
                    $selectedUser = ($usr['id_user'] == $data['id_user']) ? 'selected' : '';
                    echo "<option value='{$usr['id_user']}' {$selectedUser}>{$usr['nama']}</option>";
                endwhile;
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="jumlah_stok" class="form-label">Stok Produk</label>
            <input type="number" class="form-control" id="jumlah_stok" name="jumlah_stok" value="<?= $data['jumlah_stok']; ?>" min="0" step="1">
        </div>
        <div class="mb-3">
            <label for="satuan" class="form-label">Satuan</label>
            <input type="text" class="form-control" id="satuan" name="satuan" value="<?= $data['satuan']; ?>">
        </div>
        <div class="mb-3">
            <label for="harga_satuan" class="form-label">Harga Default</label>
            <input type="text" class="form-control" id="harga_satuan" name="harga_satuan" value="<?= $current_harga_master; ?>" inputmode="numeric" oninput="formatHargaInput(this)">
            <small class="form-text text-muted">Harga master hanya bisa diubah dari halaman edit produk. Jika field dikosongkan, sistem mempertahankan harga sebelumnya.</small>
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

<script>
function formatHargaInput(input) {
    let digits = input.value.replace(/[^0-9]/g, '');
    if (digits === '') {
        input.value = '';
        return;
    }

    let value = parseInt(digits, 10);
    if (isNaN(value)) {
        input.value = '';
        return;
    }
    if (value < 1) value = 1;
    if (value > 1000000000) value = 1000000000;

    input.value = value;
}
</script>
